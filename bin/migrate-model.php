<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT . '/app/employee-migration.php';
require ROOT . '/app/model-migration.php';
if (PHP_SAPI !== 'cli') exit;
if (!in_array('--maintenance', $argv, true)) {
    fwrite(STDERR, "Detené las escrituras y creá un respaldo. Luego ejecutá con --maintenance.\n");
    exit(1);
}
if (in_array('--as-root', $argv, true)) {
    putenv('DB_USERNAME=root');
    putenv('DB_PASSWORD=' . (getenv('MYSQL_ROOT_PASSWORD') ?: ''));
}
try {
    migrateAppointmentModel();
    echo "Modelo integrado: citas vinculadas a employees y detalle appointment_services.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Migración incompleta; mantener mantenimiento y corregir/reintentar: " . $e->getMessage() . "\n");
    exit(1);
}
