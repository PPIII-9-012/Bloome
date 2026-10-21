<?php
declare(strict_types=1);

function canManageEmployees(array $user): bool { return $user['role'] === 'admin'; }
function employeeRoles(): array { return ['admin' => 'Administrador', 'reception' => 'Recepción', 'professional' => 'Profesional']; }
function employeeRecord(int $userId): array|false {
    return query('SELECT u.id, u.name, u.email, u.role, u.active, e.phone, e.appointment_color, e.specialty, e.hire_date, e.commission_percentage FROM users u LEFT JOIN employees e ON e.user_id=u.id WHERE u.id=?', [$userId])->fetch();
}
function employeeColor(?string $color): string { return preg_match('/^#[0-9a-fA-F]{6}$/D', $color ?? '') ? $color : '#0E3D3E'; }
function employeeSwatch(?string $color): string {
    // SVG attribute, not inline CSS: works with the existing strict CSP.
    return '<svg class="employee-swatch" width="18" height="18" aria-hidden="true" viewBox="0 0 18 18"><circle cx="9" cy="9" r="8" fill="' . e(employeeColor($color)) . '"/></svg>';
}
function validateEmployee(array $source, bool $self, bool $new): array {
    $data = [];
    foreach (['name', 'email', 'phone'] as $field) $data[$field] = input($source, $field);
    $data['email'] = strtolower($data['email']);
    $errors = [];
    if ($data['name'] === '' || textLength($data['name']) > 100) $errors['name'] = 'Ingresá nombre y apellido (hasta 100 caracteres).';
    if (strlen($data['email']) > 190 || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Ingresá un correo válido.';
    if ($data['phone'] !== '' && !preg_match('/^[0-9+() .\-]{6,30}$/D', $data['phone'])) $errors['phone'] = 'Ingresá un teléfono válido (6 a 30 caracteres).';
    if (!$self) {
        $data['role'] = input($source, 'role');
        $data['active'] = input($source, 'active');
        $data['appointment_color'] = strtoupper(input($source, 'appointment_color'));
        if (!isset(employeeRoles()[$data['role']])) $errors['role'] = 'Seleccioná un rol válido.';
        if (!in_array($data['active'], ['0', '1'], true)) $errors['active'] = 'Seleccioná un estado válido.';
        $data['specialty'] = input($source, 'specialty');
        $data['hire_date'] = input($source, 'hire_date');
        $data['commission_percentage'] = str_replace(',', '.', input($source, 'commission_percentage'));
        if (textLength($data['specialty']) > 120) $errors['specialty'] = 'La especialidad admite hasta 120 caracteres.';
        if ($data['hire_date'] !== '') {
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $data['hire_date']);
            if (!$d || $d->format('Y-m-d') !== $data['hire_date'] || $data['hire_date'] > date('Y-m-d')) $errors['hire_date'] = 'Ingresá una fecha de ingreso válida que no sea futura.';
        }
        if ($data['commission_percentage'] !== '' && (!preg_match('/^\d{1,3}(\.\d{1,2})?$/D', $data['commission_percentage']) || (float)$data['commission_percentage'] > 100)) $errors['commission_percentage'] = 'Ingresá una comisión entre 0 y 100 (hasta 2 decimales).';
        if (!preg_match('/^#[0-9A-F]{6}$/D', $data['appointment_color'])) $errors['appointment_color'] = 'Ingresá un color hexadecimal válido.';
        if ($new) {
            $data['password'] = isset($source['password']) && is_string($source['password']) ? $source['password'] : '';
            if (strlen($data['password']) < 12 || strlen($data['password']) > 72 || str_contains($data['password'], "\0")) $errors['password'] = 'Usá una contraseña de 12 a 72 bytes.';
        }
    }
    return [$data, $errors];
}
function saveEmployee(int $actorId, ?int $id, array $data, bool $self): int {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Same deterministic lock order for every mutation: prevents concurrent loss of the last admin.
        $accounts = query('SELECT u.id, u.role, u.active, COALESCE(e.active,1) AS employee_active FROM users u LEFT JOIN employees e ON e.user_id=u.id ORDER BY u.id FOR UPDATE')->fetchAll();
        $actor = null;
        $target = null;
        $admins = 0;
        foreach ($accounts as $account) {
            if ((int)$account['id'] === $actorId) $actor = $account;
            if ((int)$account['id'] === $id) $target = $account;
            if ($account['role'] === 'admin' && (int)$account['active'] === 1 && (int)$account['employee_active'] === 1) $admins++;
        }
        $employeeActive = query('SELECT active FROM employees WHERE user_id=? FOR UPDATE', [$actorId])->fetchColumn();
        if (!$actor || !(int)$actor['active'] || $employeeActive === 0 || (!$self && !canManageEmployees($actor)) || ($self && $id !== $actorId)) throw new DomainException('No tenés permiso para realizar esta operación.', 403);
        if ($id !== null && !$target) throw new DomainException('Empleado no encontrado.', 404);
        if (!$self && $target && $target['role'] === 'admin' && (int)$target['active'] === 1 && (int)$target['employee_active'] === 1 && ($data['role'] !== 'admin' || $data['active'] !== '1') && $admins <= 1) throw new DomainException('Debe quedar al menos un administrador activo.', 422);
        if ($id === null) {
            query('INSERT INTO users (name,email,password_hash,role,active) VALUES (?,?,?,?,?)', [$data['name'], $data['email'], password_hash($data['password'], PASSWORD_DEFAULT), $data['role'], $data['active']]);
            $id = (int)$pdo->lastInsertId();
        } elseif ($self) {
            query('UPDATE users SET name=?, email=? WHERE id=?', [$data['name'], $data['email'], $id]);
        } else {
            query('UPDATE users SET name=?, email=?, role=?, active=? WHERE id=?', [$data['name'], $data['email'], $data['role'], $data['active'], $id]);
        }
        query('INSERT INTO employees (user_id, active) SELECT id,active FROM users WHERE id=? AND NOT EXISTS (SELECT 1 FROM employees WHERE user_id=?)', [$id, $id]);
        if ($self) query('UPDATE employees SET phone=? WHERE user_id=?', [$data['phone'] ?: null, $id]);
        else query('UPDATE employees SET phone=?, appointment_color=?, active=?, specialty=?, hire_date=?, commission_percentage=? WHERE user_id=?', [$data['phone'] ?: null, $data['appointment_color'], $data['active'], ($data['specialty'] ?? '') ?: null, ($data['hire_date'] ?? '') ?: null, ($data['commission_percentage'] ?? '') !== '' ? $data['commission_percentage'] : null, $id]);
        query('INSERT INTO employee_audit (actor_id,user_id,action) VALUES (?,?,?)', [$actorId, $id, $self ? 'profile_updated' : ($target ? 'employee_updated' : 'employee_created')]);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
