<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require_once ROOT . '/app/configuration_center.php';
require_once dirname(__DIR__) . '/app/configuration_design.php';
require_once ROOT . '/app/service_categories.php';
require_once ROOT . '/app/services.php';
require_once ROOT . '/app/employees.php';
require_once ROOT . '/app/permissions.php';
require_once ROOT . '/app/audit.php';

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

        render('message', [
            'title' => 'La sesión del formulario venció',
            'message' => 'Volvé a abrir la página e intentá de nuevo.',
            'user' => $user,
            'page' => $page,
        ]);

        exit;
    }

    if ($page === 'login') {
        if ($user) {
            redirect('dashboard');
        }

        $email = input($_POST, 'email');

        if ($method === 'POST') {
            $email = strtolower(substr($email, 0, 190));
            $fingerprint = hash('sha256', $email);
            $ipKey = hash(
                'sha256',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            );

            // Los intentos quedan registrados en la base de datos.
            $attempts = (int) query(
                'SELECT COUNT(*) FROM login_attempts
                 WHERE (identity_hash = ? OR ip_hash = ?)
                 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)',
                [$fingerprint, $ipKey]
            )->fetchColumn();

            if ($attempts >= 10) {
                http_response_code(429);

                $errors['login'] =
                    'Demasiados intentos. Esperá 15 minutos antes de volver a intentar.';
            } else {
                query('INSERT INTO login_attempts (identity_hash, ip_hash) VALUES (?, ?)', [$fingerprint, $ipKey]);
                $account = query('SELECT u.* FROM users u LEFT JOIN employees e ON e.user_id=u.id WHERE u.email = ? AND u.active = 1 AND COALESCE(e.active,1)=1', [$email])->fetch();
                $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
                $hash = $account['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
                if (strlen($password) <= 1024 && password_verify($password, $hash) && $account) {
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $account['id'];
                    $_SESSION['csrf'] = bin2hex(random_bytes(32));

                    query(
                        'DELETE FROM login_attempts WHERE identity_hash = ?',
                        [$fingerprint]
                    );

                    redirect('dashboard');
                }

                http_response_code(422);
                $errors['login'] =
                    'El correo o la contraseña no son correctos.';
            }
        }

        render(
            'login',
            compact('email', 'errors', 'page') + ['title' => 'Ingresar']
        );

        exit;
    }

    if (!$user) {
        redirect('login');
    }

    if ($page === 'logout' && $method === 'POST') {
        $_SESSION = [];
        session_destroy();

        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'secure' => getenv('APP_SECURE_COOKIE') === '1',
            'samesite' => 'Lax',
        ]);

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
    if ($page === 'client') {
        require ROOT . '/app/client-routes.php';
    }
    if ($page === 'dashboard' && $method === 'GET') {
        $today = date('Y-m-d');

        $stats = [
            'clients' => query(
                'SELECT COUNT(*) FROM clients'
            )->fetchColumn(),

            'appointments' => query(
                "SELECT COUNT(*) FROM appointments
                 WHERE starts_at >= ? AND starts_at < ?
                 AND status <> 'cancelled'",
                [
                    $today,
                    date('Y-m-d', strtotime('+1 day')),
                ]
            )->fetchColumn(),

            'services' => query(
                'SELECT COUNT(*) FROM services WHERE active = 1'
            )->fetchColumn(),
        ];
        $appointments = appointmentsBetween($today, date('Y-m-d', strtotime('+1 day')), 6);
        render('dashboard', compact('user', 'page', 'stats', 'appointments') + ['title' => 'Tu centro, en un vistazo']);
    } elseif ($page === 'clients' && $method === 'GET') {
        if (!hasPermission($user, 'clients.view')) {
            http_response_code(403);
            render('message', compact('user', 'page') + [
                'title' => 'Acceso restringido',
                'message' => 'No tenés permiso para ver clientes.',
            ]);
            exit;
        }

        $search = substr(input($_GET, 'q'), 0, 100);
        $number = max(1, min(100000, (int) input($_GET, 'p', '1')));

        $where = '';
        $params = [];

        if ($search !== '') {
            $where = " WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR CONCAT_WS(' ', first_name, last_name) LIKE ? OR CONCAT_WS(' ', last_name, first_name) LIKE ?";
            $params = array_fill(0, 6, '%' . $search . '%');
        }

        $count = (int) query(
            'SELECT COUNT(*) FROM clients' . $where,
            $params
        )->fetchColumn();

        $pages = max(1, (int) ceil($count / 12));
        $number = min($number, $pages);
        $offset = ($number - 1) * 12;

        $clients = query(
            'SELECT * FROM clients' . $where .
            " ORDER BY last_name, first_name, id
              LIMIT 12 OFFSET $offset",
            $params
        )->fetchAll();

        render(
            'clients',
            compact(
                'user',
                'page',
                'clients',
                'search',
                'number',
                'pages',
                'count'
            ) + ['title' => 'Clientes']
        );

    } elseif (
        in_array($page, ['client-new', 'client-edit'], true)
        && in_array($method, ['GET', 'POST'], true)
    ) {
        $id = max(0, (int) input($_GET, 'id'));
        $permission = $page === 'client-new' ? 'clients.create' : 'clients.edit';

        if (!hasPermission($user, $permission)) {
            if ($method === 'POST') {
                recordAudit(
                    isset($user['id']) ? (int) $user['id'] : null,
                    $permission,
                    'clients',
                    $page === 'client-edit' && $id > 0 ? $id : null,
                    'denied'
                );
            }

            http_response_code(403);
            render('message', compact('user', 'page') + [
                'title' => 'Acceso restringido',
                'message' => 'No tenés permiso para realizar esta acción.',
            ]);
            exit;
        }


        $client = array_fill_keys([
            'first_name',
            'last_name',
            'email',
            'phone',
            'birth_date',
            'notes',
        ], '');
        $draftToken=$page==='client-new'?input($_GET,'draft'):'';
        $draft=$draftToken!==''?appointmentDraft($draftToken):null;
        if ($draftToken!=='' && !$draft) { http_response_code(410); render('message',compact('user','page')+['title'=>'Borrador vencido','message'=>'Volvé a la agenda para iniciar el turno.']); exit; }
        $returnTarget=$draft?appointmentDraftTarget($draft,$draftToken):null;
        if ($draft && !empty($draft['client_created'])) redirect($returnTarget['page'],$returnTarget['params']);
        $clientBackUrl=$returnTarget?url($returnTarget['page'],$returnTarget['params']):url('clients');

        if ($page === 'client-edit') {
            $client = query(
                'SELECT * FROM clients WHERE id = ?',
                [$id]
            )->fetch();

            if (!$client) {
                http_response_code(404);

                render('message', compact('user', 'page') + [
                    'title' => 'Cliente no encontrado',
                    'message' => 'Revisá el enlace o volvé al listado.',
                ]);

                exit;
            }
        }

        if ($method === 'POST') {
            [$client, $errors] = validateClient($_POST);

            if (!$errors) {
                $values = [
                    $client['first_name'],
                    $client['last_name'],
                    $client['email'] ?: null,
                    $client['phone'] ?: null,
                    $client['birth_date'] ?: null,
                    $client['notes'] ?: null,
                ];

                $connection = db();
                $connection->beginTransaction();
                try {
                    if ($page === 'client-new') {
                        query(
                            'INSERT INTO clients
                             (first_name, last_name, email, phone, birth_date, notes)
                             VALUES (?, ?, ?, ?, ?, ?)',
                            $values
                        );
                        $clientId = (int) $connection->lastInsertId();
                    } else {
                        $exists = query(
                            'SELECT id FROM clients WHERE id = ? FOR UPDATE',
                            [$id]
                        )->fetchColumn();
                        if ($exists === false) {
                            throw new RuntimeException('El cliente ya no existe.');
                        }
                        query(
                            'UPDATE clients
                             SET first_name = ?, last_name = ?, email = ?,
                                 phone = ?, birth_date = ?, notes = ?,
                                 updated_at = CURRENT_TIMESTAMP
                             WHERE id = ?',
                            [...$values, $id]
                        );
                        $clientId = $id;
                    }

                    recordAudit(
                        isset($user['id']) ? (int) $user['id'] : null,
                        $permission,
                        'clients',
                        $clientId,
                        'success'
                    );
                    $connection->commit();
                } catch (Throwable $exception) {
                    if ($connection->inTransaction()) {
                        $connection->rollBack();
                    }
                    throw $exception;
                }

                if ($page === 'client-new' && $draft) {
                    $_SESSION['appointment_drafts'][$draftToken]['form']['client_id'] = (string) $clientId;
                    $_SESSION['appointment_drafts'][$draftToken]['client_created'] = true;
                    flash('Cliente creado. Revisá y guardá el turno; todavía no está reservado.');
                    redirect($returnTarget['page'], $returnTarget['params']);
                }

                flash(
                    $page === 'client-new'
                        ? 'Cliente creado correctamente.'
                        : 'Los cambios se guardaron.'
                );

                redirect('clients');
            }

            http_response_code(422);
        }
        render('client-form', compact('user', 'page', 'client', 'errors', 'id','draftToken','clientBackUrl') + ['title' => $page === 'client-new' ? 'Nuevo cliente' : 'Editar cliente']);
    } elseif (in_array($page, ['agenda','agenda-print','agenda-export'], true) && $method === 'GET') {
        extract(agendaView($_GET));
        if ($filterError) http_response_code(422);
        if ($page === 'agenda-export') {
            if ($filterError) { render('message', compact('user', 'page') + ['title' => 'No se pudo exportar', 'message' => 'Los filtros no son válidos. Volvé a la agenda y elegí un empleado y un estado de la lista.']); exit; }
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="agenda-' . $period['from'] . '-' . $mode . '.csv"');
            echo agendaCsv($appointments);
            exit;
        }
        $saleByAppointment = canEdit($user) ? activeSalesByAppointment(array_column($appointments, 'id')) : [];
        render($page === 'agenda-print' ? 'agenda-print' : 'agenda', compact('user', 'page', 'date', 'mode', 'employees', 'employeeFilter', 'statusFilter', 'filterError', 'period', 'appointments', 'saleByAppointment') + ['title' => 'Agenda']);

    } elseif (
        $page === 'configuracion'
        && input($_GET, 'seccion') === 'centro'
    ) {
        if (($user['role'] ?? '') !== 'admin') {
            http_response_code(403);

            render('403', [
                'user' => $user,
                'page' => $page,
                'title' => 'Acceso denegado',
            ]);

            exit;
        }

        $errors = [];

        if ($method === 'POST') {
            [$center, $errors] = validateCenterSettings($_POST);

            if (!$errors) {
                saveCenterSettings($center);

                flash('Los datos del centro se guardaron correctamente.');

                redirect('configuracion', [
                    'seccion' => 'centro',
                ]);
            }
        } else {
            $center = centerSettings();
        }

        render(
            'configuracion-centro',
            compact('user', 'page', 'center', 'errors')
                + ['title' => 'Centro']
        );

    } elseif (
        $page === 'configuracion'
        && input($_GET, 'seccion') === 'diseno'
    ) {
        if (($user['role'] ?? '') !== 'admin') {
            http_response_code(403);

            render('403', [
                'user' => $user,
                'page' => $page,
                'title' => 'Acceso denegado',
            ]);

            exit;
        }

        $errors = [];
        $design = designSettings();

        if ($method === 'POST') {
            if (input($_POST, 'action') === 'upload_logo') {
                try {
                    if (empty($_FILES['logo'])) {
                        throw new RuntimeException(
                            'Seleccioná una imagen.'
                        );
                    }

                    $path = storeBrandingLogo($_FILES['logo']);

                    saveDesignLogoPath($path);

                    flash('El logo se guardó correctamente.');

                    redirect('configuracion', [
                        'seccion' => 'diseno',
                    ]);

                } catch (RuntimeException $exception) {
                    $errors[] = $exception->getMessage();
                }
            } else {
                [$settings, $errors] =
                    validateDesignSettings($_POST);

                if (!$errors) {
                    saveDesignSettings($settings);

                    flash('El color principal se guardó correctamente.');

                    redirect('configuracion', [
                        'seccion' => 'diseno',
                    ]);
                }
            }
        }

        $design = designSettings();

        render(
            'configuracion-diseno',
            compact('user', 'page', 'design', 'errors')
                + ['title' => 'Diseño']
        );
    
    } elseif (
        $page === 'configuracion'
        && input($_GET, 'seccion') === 'categorias'
    ) {
        if (($user['role'] ?? '') !== 'admin') {
            http_response_code(403);

            render('403', [
                'user' => $user,
                'page' => $page,
                'title' => 'Acceso denegado',
            ]);

            exit;
        }

        $errors = [];
        $editingCategory = null;
        $form = [
            'name' => '',
            'color' => '#8B5CF6',
            'sort_order' => '0',
        ];

        $editId = filter_var(
            input($_GET, 'editar'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($editId !== false && $editId !== null) {
            $editingCategory = findServiceCategory((int) $editId);

            if (!$editingCategory) {
                http_response_code(404);

                render('message', [
                    'user' => $user,
                    'page' => $page,
                    'title' => 'Categoría no encontrada',
                    'message' => 'La categoría solicitada no existe.',
                ]);

                exit;
            }

            $form = $editingCategory;
        }

        if ($method === 'POST') {
            $action = input($_POST, 'action');

            if ($action === 'deactivate' || $action === 'activate') {
                $categoryId = filter_var(
                    input($_POST, 'category_id'),
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                $category = $categoryId
                    ? findServiceCategory((int) $categoryId)
                    : null;

                if (!$category) {
                    flash('La categoría no existe.');
                } else {
                    setServiceCategoryActive(
                        (int) $categoryId,
                        $action === 'activate'
                    );

                    flash(
                        $action === 'activate'
                            ? 'La categoría se reactivó correctamente.'
                            : 'La categoría se desactivó correctamente.'
                    );
                }

                redirect('configuracion', [
                    'seccion' => 'categorias',
                ]);
            }

            $postedId = filter_var(
                input($_POST, 'category_id'),
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            $id = $action === 'update' && $postedId
                ? (int) $postedId
                : null;

            if (
                !in_array($action, ['create', 'update'], true)
                || ($action === 'update' && !$id)
            ) {
                $errors['general'] = 'La operación solicitada no es válida.';
            } else {
                if ($id !== null) {
                    $editingCategory = findServiceCategory($id);

                    if (!$editingCategory) {
                        $errors['general'] = 'La categoría que intentás editar ya no existe.';
                    }
                }

                [$form, $validationErrors] =
                    validateServiceCategory($_POST, $id);

                $errors = array_merge($errors, $validationErrors);

                if (!$errors) {
                    saveServiceCategory($form, $id);

                    flash(
                        $id === null
                            ? 'La categoría se creó correctamente.'
                            : 'La categoría se actualizó correctamente.'
                    );

                    redirect('configuracion', [
                        'seccion' => 'categorias',
                    ]);
                }
            }
        }

        $search = input($_GET, 'buscar');
        $categories = listServiceCategories($search);

        render(
            'configuracion-categorias',
            compact(
                'user',
                'page',
                'categories',
                'search',
                'form',
                'errors',
                'editingCategory'
            ) + ['title' => 'Categorías de servicios']
        );

    } elseif (
        $page === 'configuracion'
        && input($_GET, 'seccion') === 'servicios'
    ) {
        if (($user['role'] ?? '') !== 'admin') {
            http_response_code(403);

            render('403', [
                'user' => $user,
                'page' => $page,
                'title' => 'Acceso denegado',
            ]);

            exit;
        }

        $errors = [];
        $search = input($_GET, 'buscar');

        $service = [
            'id' => null,
            'reference' => '',
            'category_id' => '',
            'name' => '',
            'duration_minutes' => '',
            'price' => '',
            'tax_percentage' => '0',
            'sort_order' => '0',
            'active' => 1,
        ];

        $editId = filter_var(
            input($_GET, 'editar'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($editId !== false && $editId !== null) {
            $foundService = findService((int) $editId);

            if (!$foundService) {
                http_response_code(404);

                render('message', [
                    'user' => $user,
                    'page' => $page,
                    'title' => 'Servicio no encontrado',
                    'message' => 'El servicio solicitado no existe.',
                ]);

                exit;
            }

            $service = $foundService;
        }

        if ($method === 'POST') {
            $action = input($_POST, 'action');

            $postedId = filter_var(
                input($_POST, 'id'),
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($action === 'toggle') {
                $serviceId = $postedId !== false && $postedId !== null
                    ? (int) $postedId
                    : 0;

                $foundService = $serviceId > 0
                    ? findService($serviceId)
                    : null;

                if (!$foundService) {
                    flash('El servicio no existe.');
                } else {
                    $active = input($_POST, 'active') === '1';

                    setServiceActive($serviceId, $active);

                    flash(
                        $active
                            ? 'El servicio se activó correctamente.'
                            : 'El servicio se desactivó correctamente.'
                    );
                }

                redirect('configuracion', [
                    'seccion' => 'servicios',
                ]);
            }

            $id = $postedId !== false && $postedId !== null
                ? (int) $postedId
                : null;

            if ($action !== 'save') {
                $errors['general'] =
                    'La operación solicitada no es válida.';
            } else {
                if ($id !== null && !findService($id)) {
                    $errors['general'] =
                        'El servicio que intentás editar ya no existe.';
                }

                [$data, $validationErrors] =
                    validateService($_POST, $id);

                $errors = array_merge($errors, $validationErrors);

                if (!$errors) {
                    saveService($data, $id);

                    flash(
                        $id === null
                            ? 'El servicio se creó correctamente.'
                            : 'El servicio se actualizó correctamente.'
                    );

                    redirect('configuracion', [
                        'seccion' => 'servicios',
                    ]);
                }

                $service = array_merge($service, $_POST);

                if ($id !== null) {
                    $service['id'] = $id;
                }
            }
        }

        $services = listServices($search);
        $categories = listServiceCategories('');

        render(
            'configuracion-servicios',
            compact(
                'user',
                'page',
                'services',
                'categories',
                'service',
                'errors',
                'search'
            ) + ['title' => 'Servicios y tratamientos']
        );
    } elseif ($page === 'auditoria' && $method === 'GET') {
        if (!hasPermission($user, 'audit.view')) {
            recordAudit((int) $user['id'], 'audit_view', 'audit_logs', null, 'denied');
            http_response_code(403);
            render('403', ['user' => $user, 'page' => $page, 'title' => 'Acceso denegado']);
            exit;
        }
        $logs = query(
            'SELECT al.id, al.action, al.entity, al.entity_id, al.outcome, al.details,
                    al.created_at, u.name AS actor_name
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.actor_user_id
             ORDER BY al.created_at DESC, al.id DESC
             LIMIT 250'
        )->fetchAll();
        render('auditoria', compact('user', 'page', 'logs') + ['title' => 'Registro de acciones']);

    } elseif (
        $page === 'configuracion'
        && $method === 'GET'
    ) {
        render(
            'configuracion',
            compact('user', 'page') + ['title' => 'Configuración']
        );

    } else {
        http_response_code(404);

        render('message', compact('user', 'page') + [
            'title' => 'Página no encontrada',
            'message' => 'Este enlace no está disponible. Podés volver al inicio.',
        ]);
    }

} catch (PDOException $exception) {
    error_log('Bloome database error: ' . $exception->getMessage());

    http_response_code(503);

    render('message', [
        'user' => $user,
        'page' => $page,
        'title' => 'No pudimos conectar con los datos',
        'message' => 'Revisá la conexión a MySQL y ejecutá la instalación indicada en README.md. No se guardaron cambios en esta operación.',
    ]);
}
