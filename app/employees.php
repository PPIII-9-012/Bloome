<?php
declare(strict_types=1);

function canManageEmployees(array $user): bool
{
    return ($user['role'] ?? '') === 'admin';
}

function employeeRoles(): array
{
    return ['admin' => 'Administrador', 'reception' => 'Recepción', 'professional' => 'Profesional'];
}

/** Busca un empleado y devuelve los campos que utiliza el formulario. */
function findEmployee(int $id): array|false
{
    return query(
        'SELECT u.id, u.name, u.email, u.role, u.active AS user_active,
                e.phone, e.appointment_color, e.specialty, e.hire_date,
                e.commission_percentage, e.active AS employee_active
         FROM users u
         LEFT JOIN employees e ON e.user_id = u.id
         WHERE u.id = ?',
        [$id]
    )->fetch();
}

/** Lista de empleados con filtros por texto y estado. */
function listEmployees(string $search = '', string $status = 'all'): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(u.name LIKE ? OR u.email LIKE ? OR e.phone LIKE ? OR e.specialty LIKE ?)';
        $term = '%' . $search . '%';
        array_push($params, $term, $term, $term, $term);
    }
    if ($status === 'active') {
        $where[] = 'u.active = 1 AND COALESCE(e.active, 1) = 1';
    } elseif ($status === 'inactive') {
        $where[] = '(u.active = 0 OR COALESCE(e.active, 1) = 0)';
    } elseif ($status !== 'all') {
        $status = 'all';
    }
    $sql = 'SELECT u.id, u.name, u.email, u.role, u.active AS user_active,
                   e.phone, e.specialty, e.hire_date, e.commission_percentage,
                   COALESCE(e.active, 1) AS employee_active
            FROM users u LEFT JOIN employees e ON e.user_id = u.id';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY u.name, u.id';
    return query($sql, $params)->fetchAll();
}

/** Valida los datos enviados por el formulario de empleados. */
function validateEmployee(array $source, ?int $id = null): array
{
    $data = [
        'name' => input($source, 'name'),
        'email' => strtolower(input($source, 'email')),
        'role' => input($source, 'role', 'professional'),
        'phone' => input($source, 'phone'),
        'specialty' => input($source, 'specialty'),
        'hire_date' => input($source, 'hire_date'),
        'commission_percentage' => input($source, 'commission_percentage'),
        'password' => isset($source['password']) && is_string($source['password']) ? $source['password'] : '',
    ];
    $errors = [];
    if ($data['name'] === '' || textLength($data['name']) > 100) $errors['name'] = 'Ingresá nombre y apellido (hasta 100 caracteres).';
    if (strlen($data['email']) > 190 || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Ingresá un correo electrónico válido.';
    if (!isset(employeeRoles()[$data['role']])) $errors['role'] = 'Seleccioná un rol válido.';
    if ($data['phone'] !== '' && (!preg_match('/^[0-9+() .\-]{6,30}$/D', $data['phone']))) $errors['phone'] = 'Ingresá un teléfono válido.';
    if (textLength($data['specialty']) > 120) $errors['specialty'] = 'La especialidad no puede superar los 120 caracteres.';
    if ($data['hire_date'] !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['hire_date']);
        if (!$date || $date->format('Y-m-d') !== $data['hire_date']) $errors['hire_date'] = 'Ingresá una fecha de ingreso válida.';
    }
    if ($data['commission_percentage'] !== '') {
        if (!is_numeric($data['commission_percentage']) || (float)$data['commission_percentage'] < 0 || (float)$data['commission_percentage'] > 100) {
            $errors['commission_percentage'] = 'La comisión debe estar entre 0 y 100.';
        } else {
            $data['commission_percentage'] = number_format((float)$data['commission_percentage'], 2, '.', '');
        }
    }
    if ($id === null && (strlen($data['password']) < 8 || strlen($data['password']) > 72 || str_contains($data['password'], "\0"))) {
        $errors['password'] = 'La contraseña debe tener entre 8 y 72 bytes.';
    }
    if ($id !== null && $data['password'] !== '' && (strlen($data['password']) < 8 || strlen($data['password']) > 72 || str_contains($data['password'], "\0"))) {
        $errors['password'] = 'La contraseña debe tener entre 8 y 72 bytes.';
    }
    $data['phone'] = $data['phone'] !== '' ? $data['phone'] : null;
    $data['specialty'] = $data['specialty'] !== '' ? $data['specialty'] : null;
    $data['hire_date'] = $data['hire_date'] !== '' ? $data['hire_date'] : null;
    $data['commission_percentage'] = $data['commission_percentage'] !== '' ? $data['commission_percentage'] : null;
    if ($id !== null) $data['id'] = $id;
    return [$data, $errors];
}

/** Crea o actualiza usuario y perfil de empleado en una única transacción. */
function saveEmployee(array $data, ?int $id = null): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $oldRole = null;
        if ($id !== null) {
            $existing = query('SELECT u.id, u.active, e.active AS employee_active FROM users u LEFT JOIN employees e ON e.user_id=u.id WHERE u.id=? FOR UPDATE', [$id])->fetch();
            if (!$existing) throw new RuntimeException('El empleado no existe.');
            $oldRole = query('SELECT role FROM users WHERE id=?', [$id])->fetchColumn() ?: null;
            query('UPDATE users SET name=?, email=?, role=? WHERE id=?', [$data['name'], $data['email'], $data['role'], $id]);
            if (($data['password'] ?? '') !== '') query('UPDATE users SET password_hash=? WHERE id=?', [password_hash($data['password'], PASSWORD_DEFAULT), $id]);
            query('INSERT INTO employees (user_id) SELECT ? WHERE NOT EXISTS (SELECT 1 FROM employees WHERE user_id=?)', [$id, $id]);
            query('UPDATE employees SET phone=?, specialty=?, hire_date=?, commission_percentage=? WHERE user_id=?', [$data['phone'], $data['specialty'], $data['hire_date'], $data['commission_percentage'], $id]);
            $action = 'employee_updated';
        } else {
            query('INSERT INTO users (name,email,password_hash,role,active) VALUES (?,?,?,?,1)', [$data['name'], $data['email'], password_hash($data['password'], PASSWORD_DEFAULT), $data['role']]);
            $id = (int)$pdo->lastInsertId();
            query('INSERT INTO employees (user_id,phone,specialty,hire_date,commission_percentage,active) VALUES (?,?,?,?,?,1)', [$id, $data['phone'], $data['specialty'], $data['hire_date'], $data['commission_percentage']]);
            $action = 'employee_created';
        }
        $actorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : $id;
        query('INSERT INTO employee_audit (actor_id,user_id,action) VALUES (?,?,?)', [$actorId, $id, $action]);
        if (function_exists('recordAudit')) recordAudit($actorId, $action, 'employees', $id, 'success', [
            'role_before' => $oldRole,
            'role_after' => $data['role'],
            'role_changed' => $oldRole !== null && $oldRole !== $data['role'],
        ]);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException && $e->getCode() === '23000') throw new DomainException('Ese correo electrónico ya está registrado.');
        throw $e;
    }
}

/** Activa/desactiva tanto la cuenta como el perfil, sin borrar historial. */
function setEmployeeActive(int $id, bool $active): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $accounts = query("SELECT u.id,u.role,u.active,COALESCE(e.active,1) AS employee_active FROM users u LEFT JOIN employees e ON e.user_id=u.id ORDER BY u.id FOR UPDATE")->fetchAll();
        $target = null;
        $activeAdmins = 0;
        foreach ($accounts as $account) {
            if ((int)$account['id'] === $id) $target = $account;
            if ($account['role'] === 'admin' && (int)$account['active'] === 1 && (int)$account['employee_active'] === 1) $activeAdmins++;
        }
        if (!$target) throw new RuntimeException('El empleado no existe.');
        if (!$active && $target['role'] === 'admin' && (int)$target['active'] === 1 && (int)$target['employee_active'] === 1 && $activeAdmins <= 1) {
            throw new DomainException('Debe quedar al menos un administrador activo.');
        }
        query('UPDATE users SET active=? WHERE id=?', [$active ? 1 : 0, $id]);
        query('INSERT INTO employees (user_id,active) SELECT id,active FROM users WHERE id=? AND NOT EXISTS (SELECT 1 FROM employees WHERE user_id=?)', [$id, $id]);
        query('UPDATE employees SET active=? WHERE user_id=?', [$active ? 1 : 0, $id]);
        $actorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : $id;
        query('INSERT INTO employee_audit (actor_id,user_id,action) VALUES (?,?,?)', [$actorId, $id, $active ? 'employee_activated' : 'employee_deactivated']);
        if (function_exists('recordAudit')) recordAudit($actorId, $active ? 'employee_activated' : 'employee_deactivated', 'employees', $id, 'success');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function employeeColor(?string $color): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/D', $color ?? '') ? $color : '#0E3D3E';
}
function employeeSwatch(?string $color): string
{
    return '<svg class="employee-swatch" width="18" height="18" aria-hidden="true" viewBox="0 0 18 18"><circle cx="9" cy="9" r="8" fill="' . e(employeeColor($color)) . '"/></svg>';
}
