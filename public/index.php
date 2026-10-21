<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$page = input($_GET, 'page', 'dashboard');
$method = $_SERVER['REQUEST_METHOD'];
$user = null;
$errors = [];
try {
    if (!empty($_SESSION['user_id'])) {
        $user = query('SELECT u.id, u.name, u.email, u.role FROM users u LEFT JOIN employees e ON e.user_id=u.id WHERE u.id = ? AND u.active = 1 AND COALESCE(e.active,1)=1', [$_SESSION['user_id']])->fetch() ?: null;
        if (!$user) unset($_SESSION['user_id']);
    }
    if ($method === 'POST' && !validCsrf(input($_POST, 'csrf'))) {
        http_response_code(419);
        render('message', ['title' => 'La sesión del formulario venció', 'message' => 'Volvé a abrir la página e intentá de nuevo.', 'user' => $user, 'page' => $page]);
        exit;
    }
    if ($page === 'login') {
        if ($user) redirect('dashboard');
        $email = input($_POST, 'email');
        if ($method === 'POST') {
            $email = strtolower(substr($email, 0, 190));
            $fingerprint = hash('sha256', $email);
            $ipKey = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            // Shared database throttling survives a new browser session.
            $attempts = (int) query('SELECT COUNT(*) FROM login_attempts WHERE (identity_hash = ? OR ip_hash = ?) AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)', [$fingerprint, $ipKey])->fetchColumn();
            if ($attempts >= 10) {
                http_response_code(429);
                $errors['login'] = 'Demasiados intentos. Esperá 15 minutos antes de volver a intentar.';
            } else {
                query('INSERT INTO login_attempts (identity_hash, ip_hash) VALUES (?, ?)', [$fingerprint, $ipKey]);
                $account = query('SELECT u.* FROM users u LEFT JOIN employees e ON e.user_id=u.id WHERE u.email = ? AND u.active = 1 AND COALESCE(e.active,1)=1', [$email])->fetch();
                $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
                $hash = $account['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
                if (strlen($password) <= 1024 && password_verify($password, $hash) && $account) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $account['id'];
                    $_SESSION['csrf'] = bin2hex(random_bytes(32));
                    query('DELETE FROM login_attempts WHERE identity_hash = ?', [$fingerprint]);
                    redirect('dashboard');
                }
                http_response_code(422);
                $errors['login'] = 'El correo o la contraseña no son correctos.';
            }
        }
        render('login', compact('email', 'errors', 'page') + ['title' => 'Ingresar']);
        exit;
    }
    if (!$user) redirect('login');
    if ($page === 'logout' && $method === 'POST') {
        $_SESSION = [];
        session_destroy();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'secure' => getenv('APP_SECURE_COOKIE') === '1', 'samesite' => 'Lax']);
        redirect('login');
    }
    if (in_array($page, ['employees','employee','employee-new','employee-edit','profile','profile-edit','employee-vacation-new','employee-vacation-delete','employee-schedule-save'], true)) {
        require ROOT . '/app/employee-routes.php';
    }
    if (in_array($page, ['holidays','holiday-new','holiday-delete','schedule'], true)) {
        require ROOT . '/app/schedule-routes.php';
    }
    if (in_array($page, ['sale-new','sale','sales'], true)) {
        require ROOT . '/app/sale-routes.php';
    }
    if (in_array($page, ['caja','caja-movement','caja-close'], true)) {
        require ROOT . '/app/cash-routes.php';
    }
    if (in_array($page, ['sale-pay','sale-void','deposit-new'], true)) {
        require ROOT . '/app/payment-routes.php';
    }
    if (in_array($page, ['appointment-new','appointment-edit','appointment-cancel'], true)) {
        require ROOT . '/app/appointment-routes.php';
    }
    if ($page === 'dashboard' && $method === 'GET') {
        $today = date('Y-m-d');
        $stats = [
            'clients' => query('SELECT COUNT(*) FROM clients')->fetchColumn(),
            'appointments' => query("SELECT COUNT(*) FROM appointments WHERE starts_at >= ? AND starts_at < ? AND status <> 'cancelled'", [$today, date('Y-m-d', strtotime('+1 day'))])->fetchColumn(),
            'services' => query('SELECT COUNT(*) FROM services WHERE active = 1')->fetchColumn(),
        ];
        $appointments = appointmentsBetween($today, date('Y-m-d', strtotime('+1 day')), 6);
        render('dashboard', compact('user', 'page', 'stats', 'appointments') + ['title' => 'Tu centro, en un vistazo']);
    } elseif ($page === 'clients' && $method === 'GET') {
        $search = substr(input($_GET, 'q'), 0, 100);
        $number = max(1, min(100000, (int) input($_GET, 'p', '1')));
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = ' WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?';
            $params = array_fill(0, 4, '%' . $search . '%');
        }
        $count = (int) query('SELECT COUNT(*) FROM clients' . $where, $params)->fetchColumn();
        $pages = max(1, (int) ceil($count / 12));
        $number = min($number, $pages);
        $offset = ($number - 1) * 12;
        $clients = query('SELECT * FROM clients' . $where . " ORDER BY last_name, first_name, id LIMIT 12 OFFSET $offset", $params)->fetchAll();
        render('clients', compact('user', 'page', 'clients', 'search', 'number', 'pages', 'count') + ['title' => 'Clientes']);
    } elseif (in_array($page, ['client-new', 'client-edit'], true) && in_array($method, ['GET', 'POST'], true)) {
        if (!canEdit($user)) {
            http_response_code(403);
            render('message', compact('user', 'page') + ['title' => 'Acceso restringido', 'message' => 'Tu perfil no tiene permiso para editar clientes.']);
            exit;
        }
        $id = max(0, (int) input($_GET, 'id'));
        $client = array_fill_keys(['first_name', 'last_name', 'email', 'phone', 'birth_date', 'notes'], '');
        if ($page === 'client-edit') {
            $client = query('SELECT * FROM clients WHERE id = ?', [$id])->fetch();
            if (!$client) { http_response_code(404); render('message', compact('user', 'page') + ['title' => 'Cliente no encontrado', 'message' => 'Revisá el enlace o volvé al listado.']); exit; }
        }
        if ($method === 'POST') {
            [$client, $errors] = validateClient($_POST);
            if (!$errors) {
                $values = [$client['first_name'], $client['last_name'], $client['email'] ?: null, $client['phone'] ?: null, $client['birth_date'] ?: null, $client['notes'] ?: null];
                if ($page === 'client-new') {
                    query('INSERT INTO clients (first_name, last_name, email, phone, birth_date, notes) VALUES (?, ?, ?, ?, ?, ?)', $values);
                } else {
                    query('UPDATE clients SET first_name=?, last_name=?, email=?, phone=?, birth_date=?, notes=?, updated_at=CURRENT_TIMESTAMP WHERE id=?', [...$values, $id]);
                }
                flash($page === 'client-new' ? 'Cliente creado correctamente.' : 'Los cambios se guardaron.');
                redirect('clients');
            }
            http_response_code(422);
        }
        render('client-form', compact('user', 'page', 'client', 'errors', 'id') + ['title' => $page === 'client-new' ? 'Nuevo cliente' : 'Editar cliente']);
    } elseif ($page === 'agenda' && $method === 'GET') {
        $date = input($_GET, 'date', date('Y-m-d'));
        if (!validDate($date)) $date = date('Y-m-d');
        $mode = input($_GET, 'mode', 'day');
        if (!in_array($mode, ['day','week','month'], true)) $mode='day';
        $employeeFilter = input($_GET, 'employee');
        $statusFilter = input($_GET, 'status');
        $employees = query('SELECT e.id,u.name,e.active,u.active AS user_active FROM employees e JOIN users u ON u.id=e.user_id ORDER BY u.name,e.id')->fetchAll();
        $filterError = ($employeeFilter !== '' && (!ctype_digit($employeeFilter) || !in_array($employeeFilter,array_map('strval',array_column($employees,'id')),true))) || ($statusFilter !== '' && !isset(appointmentStatuses()[$statusFilter]));
        $period = agendaPeriod($date, $mode);
        if ($filterError) http_response_code(422);
        $appointments = $filterError ? [] : appointmentsBetween($period['from'], $period['until'], null, $employeeFilter === '' ? null : (int)$employeeFilter, $statusFilter === '' ? null : $statusFilter);
        render('agenda', compact('user', 'page', 'date', 'mode', 'employees', 'employeeFilter', 'statusFilter', 'filterError', 'period', 'appointments') + ['title' => 'Agenda']);
    } else {
        http_response_code(404);
        render('message', compact('user', 'page') + ['title' => 'Página no encontrada', 'message' => 'Este enlace no está disponible. Podés volver al inicio.']);
    }
} catch (PDOException $exception) {
    error_log('Bloome database error: ' . $exception->getMessage());
    http_response_code(503);
    render('message', ['user' => $user, 'page' => $page, 'title' => 'No pudimos conectar con los datos', 'message' => 'Revisá la conexión a MySQL y ejecutá la instalación indicada en README.md. No se guardaron cambios en esta operación.']);
}
