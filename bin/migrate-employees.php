<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT . '/app/employee-migration.php';
if (PHP_SAPI !== 'cli') exit;
if (in_array('--as-root', $argv, true)) {
    putenv('DB_USERNAME=root');
    putenv('DB_PASSWORD=' . (getenv('MYSQL_ROOT_PASSWORD') ?: ''));
}
try {
    migrateEmployees();
    echo "Migración 009 aplicada; usuarios y citas conservados.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
