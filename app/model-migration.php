<?php
declare(strict_types=1);

/** Run with application writes stopped: MySQL DDL commits implicitly. */
function migrateAppointmentModel(): void
{
    $lock = 'bloome-model-' . substr(hash('sha256', (string)query('SELECT DATABASE()')->fetchColumn()), 0, 32);
    if ((int)query('SELECT GET_LOCK(?, 0)', [$lock])->fetchColumn() !== 1) {
        throw new RuntimeException('Otra migración del modelo está en curso.');
    }
    try {
        foreach (explode(';', file_get_contents(ROOT . '/database/schema.sql')) as $sql) {
            if (trim($sql) !== '') db()->exec($sql);
        }
        query('INSERT INTO employees (user_id, active) SELECT u.id, u.active FROM users u LEFT JOIN employees e ON e.user_id=u.id WHERE e.id IS NULL');
        $target = appointmentEmployeeTarget();
        if (!in_array($target, ['users', 'employees'], true)) {
            throw new RuntimeException('La relación de empleados de citas no es compatible. No se convirtieron los IDs.');
        }
        $hasLegacyService = (bool)query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointments' AND COLUMN_NAME='service_id'")->fetchColumn();
        if ($hasLegacyService && $target === 'users') {
            // The old schema had no price snapshot. Capture the catalog value at migration time.
            query('INSERT INTO appointment_services (appointment_id, service_id, price, duration_minutes)
                SELECT a.id, a.service_id, s.price, s.duration_minutes FROM appointments a
                JOIN services s ON s.id=a.service_id
                LEFT JOIN appointment_services aps ON aps.appointment_id=a.id AND aps.service_id=a.service_id
                WHERE aps.id IS NULL');
        }
        if ($target === 'users') {
            if (!query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointments' AND COLUMN_NAME='employee_id_model'")->fetchColumn()) {
                db()->exec('ALTER TABLE appointments ADD employee_id_model BIGINT UNSIGNED NULL');
            }
            query('UPDATE appointments a JOIN employees e ON e.user_id=a.employee_id SET a.employee_id_model=e.id');
            if (query('SELECT COUNT(*) FROM appointments WHERE employee_id_model IS NULL')->fetchColumn()) {
                throw new RuntimeException('Hay citas sin empleado asociado; no se convirtieron las relaciones.');
            }
            $foreignKey = query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointments' AND COLUMN_NAME='employee_id' AND REFERENCED_TABLE_NAME='users'")->fetchColumn();
            if (!is_string($foreignKey) || !preg_match('/^[a-zA-Z0-9_]+$/D', $foreignKey)) {
                throw new RuntimeException('Restricción de empleado no reconocida.');
            }
            // Both renames and the FK change are one atomic MySQL 8 DDL statement.
            db()->exec("ALTER TABLE appointments
                DROP FOREIGN KEY `$foreignKey`, DROP INDEX appointments_schedule,
                CHANGE COLUMN employee_id legacy_employee_user_id BIGINT UNSIGNED NULL,
                CHANGE COLUMN employee_id_model employee_id BIGINT UNSIGNED NOT NULL,
                ADD CONSTRAINT appointments_employee_model FOREIGN KEY (employee_id) REFERENCES employees(id),
                ADD INDEX appointments_schedule (employee_id, starts_at, ends_at)");
        }
        if ($hasLegacyService) {
            // Kept as an optional migration trace, never used by new reads/writes.
            db()->exec('ALTER TABLE appointments MODIFY service_id BIGINT UNSIGNED NULL');
        }
        $statusType = query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointments' AND COLUMN_NAME='status'")->fetchColumn();
        if (!str_contains((string)$statusType, "'missed'")) {
            db()->exec("ALTER TABLE appointments MODIFY status ENUM('pending','confirmed','completed','cancelled','missed') NOT NULL DEFAULT 'pending'");
        }
        migrateEmployees();
        db()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
        query("INSERT IGNORE INTO schema_migrations (version) VALUES ('003-employees-appointment-services')");
    } finally {
        query('SELECT RELEASE_LOCK(?)', [$lock]);
    }
}
