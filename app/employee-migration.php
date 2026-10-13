<?php
declare(strict_types=1);
function appointmentEmployeeTarget(): string|false
{
    return query("SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointments' AND COLUMN_NAME='employee_id' AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchColumn();
}
// Run during deployment, with application writes stopped. MySQL DDL commits implicitly.
function migrateEmployees(): void
{
    $target = appointmentEmployeeTarget();
    if (!in_array($target, ['users', 'employees'], true)) throw new RuntimeException('Relación de citas no compatible.');
    foreach (explode(';', file_get_contents(ROOT . '/database/migrations/009-employees.sql')) as $sql) {
        if (trim($sql) !== '') db()->exec($sql);
    }
    if (!query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employees' AND COLUMN_NAME='appointment_color'")->fetchColumn()) {
        db()->exec("ALTER TABLE employees ADD appointment_color CHAR(7) NOT NULL DEFAULT '#0E3D3E'");
    }
    query('INSERT INTO employees (user_id, active) SELECT u.id, u.active FROM users u LEFT JOIN employees e ON e.user_id=u.id WHERE e.id IS NULL');
    // Protect every SQL insertion path, including the future appointments module.
    foreach (['INSERT' => '', 'UPDATE' => "IF NEW.employee_id <> OLD.employee_id OR NEW.starts_at <> OLD.starts_at OR NEW.ends_at <> OLD.ends_at OR (OLD.status = 'cancelled' AND NEW.status <> 'cancelled') THEN"] as $event => $condition) {
        $name = 'appointments_active_employee_' . strtolower($event);
        $lookup = $target === 'employees' ? 'e.id=NEW.employee_id' : 'u.id=NEW.employee_id';
        $existing = query('SELECT ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME=?', [$name])->fetchColumn();
        if ($existing && str_contains($existing, $lookup)) continue;
        if ($existing) db()->exec("DROP TRIGGER `$name`");
        $end = $condition === '' ? '' : 'END IF;';
        db()->exec("CREATE TRIGGER $name BEFORE $event ON appointments FOR EACH ROW BEGIN
            DECLARE enabled INT DEFAULT 0;
            $condition
            SELECT (u.active = 1 AND COALESCE(e.active, 1) = 1) INTO enabled
              FROM users u LEFT JOIN employees e ON e.user_id=u.id WHERE $lookup FOR SHARE;
            IF COALESCE(enabled, 0) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El empleado está inactivo o no existe'; END IF;
            $end
        END");
    }
}
