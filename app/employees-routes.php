<?php
declare(strict_types=1);
// Included after authentication and global CSRF verification.
$self = in_array($page, ['profile', 'profile-edit'], true);
$new = $page === 'employee-new';
$editing = in_array($page, ['employee-new', 'employee-edit', 'profile-edit'], true);
$isPostAction = in_array($page, ['employee-vacation-new', 'employee-vacation-delete', 'employee-schedule-save'], true);
$deny = static function (int $status, string $message) use ($user, $page): never {
    http_response_code($status);
    render('message', compact('user', 'page', 'message') + ['title' => $status === 404 ? 'Empleado no encontrado' : ($status === 422 ? 'Revisá los datos' : 'Acceso restringido')]);
    exit;
};
if (!$self && !canManageEmployees($user)) $deny(403, 'Solo un administrador puede gestionar empleados.');
if (!in_array($method, ['GET', 'POST'], true) || ($method === 'POST' && !$editing && !$isPostAction)) $deny(405, 'Método no permitido.');
// Identity and protected fields are never accepted from the self-service request.
if ($self && (array_key_exists('id', $_GET) || array_intersect(['id','user_id','employee_id','role','active','appointment_color','password','password_hash'], array_keys($_POST)))) $deny(403, 'El perfil propio solo permite editar nombre, correo y teléfono.');
if ($page === 'employees') {
    $search = substr(input($_GET, 'q'), 0, 100);
    $filter = input($_GET, 'state', 'all');
    if (!in_array($filter, ['all','1','0'], true)) $filter = 'all';
    $where = ' WHERE (u.name LIKE ? OR u.email LIKE ?)';
    $params = ['%' . $search . '%', '%' . $search . '%'];
    if ($filter !== 'all') { $where .= ' AND u.active=?'; $params[] = $filter; }
    $count = (int)query('SELECT COUNT(*) FROM users u' . $where, $params)->fetchColumn();
    $pages = max(1, (int)ceil($count / 12));
    $number = max(1, min($pages, (int)input($_GET, 'p', '1')));
    $offset = ($number - 1) * 12;
    $employees = query('SELECT u.id,u.name,u.email,u.role,u.active,e.appointment_color FROM users u LEFT JOIN employees e ON e.user_id=u.id' . $where . " ORDER BY u.name,u.id LIMIT 12 OFFSET $offset", $params)->fetchAll();
    render('employees', compact('user','page','employees','search','filter','count','pages','number') + ['title'=>'Empleados']);
    exit;
}

if ($isPostAction) {
    if ($method !== 'POST') $deny(405, 'Método no permitido.');
    if (!employeeRecord((int)input($_POST, 'user_id'))) $deny(404, 'Empleado no encontrado.');
}

if ($page === 'employee-vacation-new' && $method === 'POST') {
    $targetId = (int)input($_POST, 'user_id', input($_GET, 'id'));
    if ($targetId !== (int)$user['id'] && !canManageEmployees($user)) $deny(403, 'No podés gestionar vacaciones de otros empleados.');
    try {
        addEmployeeVacation($targetId, input($_POST, 'description'), input($_POST, 'starts_at'), input($_POST, 'ends_at'));
        flash('Período de vacaciones guardado.');
    } catch (DomainException $e) {
        $deny(422, $e->getMessage());
    }
    redirect('employee', ['id' => $targetId]);
}

if ($page === 'employee-vacation-delete' && $method === 'POST') {
    $vacationId = (int)input($_POST, 'vacation_id');
    $targetId = (int)input($_POST, 'user_id', input($_GET, 'id'));
    if ($targetId !== (int)$user['id'] && !canManageEmployees($user)) $deny(403, 'Acceso denegado.');
    deleteEmployeeVacation($vacationId, $targetId, canManageEmployees($user));
    flash('Vacación eliminada.');
    redirect('employee', ['id' => $targetId]);
}

if ($page === 'employee-schedule-save' && $method === 'POST') {
    $targetId = (int)input($_POST, 'user_id', input($_GET, 'id'));
    if (!canManageEmployees($user)) $deny(403, 'Solo el administrador puede definir horarios de empleados.');
    try {
        saveEmployeeSchedule($targetId, $_POST['schedule'] ?? []);
        flash('Horario de trabajo actualizado.');
    } catch (DomainException $e) {
        $deny(422, $e->getMessage());
    }
    redirect('employee', ['id' => $targetId]);
}
$id = $self ? (int)$user['id'] : (int)input($_GET, 'id');
$employee = $new ? ['name'=>'','email'=>'','phone'=>'','role'=>'professional','active'=>'1','appointment_color'=>'#0E3D3E'] : employeeRecord($id);
if (!$employee) $deny(404, 'Revisá el enlace o volvé al listado.');
if ($method === 'POST') {
    [$data, $errors] = validateEmployee($_POST, $self, $new);
    $employee = array_replace($employee, $data);
    unset($employee['password']);
    if (!$errors) {
        try {
            $id = saveEmployee((int)$user['id'], $new ? null : $id, $data, $self);
            flash($new ? 'Empleado creado correctamente.' : 'Los cambios se guardaron.');
            redirect($self ? 'profile' : 'employees');
        } catch (DomainException $e) {
            if ($e->getCode() !== 422) $deny($e->getCode(), $e->getMessage());
            $errors['form'] = $e->getMessage();
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) !== 1062) throw $e;
            $errors['email'] = 'Ese correo ya está registrado.';
        }
    }
    http_response_code(422);
}
$title = $self ? ($editing ? 'Editar mi perfil' : 'Mi perfil') : ($new ? 'Nuevo empleado' : ($editing ? 'Editar empleado' : 'Perfil del empleado'));
$vacations = (!$editing && $id) ? employeeVacations($id) : [];
$schedule = (!$editing && $id) ? employeeSchedule($id) : [];
render($editing ? 'employee-form' : 'employee-profile', compact('user','page','employee','errors','id','self','new','title','vacations','schedule'));
exit;

