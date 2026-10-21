<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (PHP_SAPI !== 'cli') exit;
if (in_array('--as-root', $argv, true)) {
    putenv('DB_USERNAME=root');
    putenv('DB_PASSWORD=' . (getenv('MYSQL_ROOT_PASSWORD') ?: ''));
}
$email = getenv('ADMIN_EMAIL') ?: '';
$password = getenv('ADMIN_PASSWORD') ?: '';
$name = getenv('ADMIN_NAME') ?: 'Administrador';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Configurá ADMIN_EMAIL y ADMIN_PASSWORD (12 a 72 bytes) en .env antes de instalar.\n");
    exit(1);
}
try {
    require ROOT . '/app/employee-migration.php';
    require ROOT . '/app/model-migration.php';
    require ROOT . '/app/finance-migration.php';
    require ROOT . '/app/catalog-migration.php';
    require ROOT . '/app/client-migration.php';
    if (appointmentEmployeeTarget() === 'users' && !in_array('--maintenance', $argv, true)) {
        throw new RuntimeException('Respaldá la base, detené las escrituras y ejecutá con --maintenance.');
    }
    $schema = file_get_contents(ROOT . '/database/schema.sql');
    foreach (explode(';', $schema) as $statement) {
        if (trim($statement) !== '') db()->exec($statement);
    }
    $exists = query('SELECT id FROM users WHERE email = ?', [$email])->fetchColumn();
    if (!$exists) query('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)', [$name, $email, password_hash($password, PASSWORD_DEFAULT), 'admin']);
    migrateAppointmentModel();
    migrateFinanceModel();
    migrateServiceCatalog();
    migrateClientGender();
    echo "Esquema instalado. Administrador " . ($exists ? 'existente conservado' : 'creado') . ".\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Instalación incompleta. Revisá MySQL, permisos y el procedimiento de docs/MODELO-DATOS.md. " . $e->getCode() . "\n");
    exit(1);
}
