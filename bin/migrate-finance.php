<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT . '/app/finance-migration.php';
if (PHP_SAPI !== 'cli') exit;
if (in_array('--as-root', $argv, true)) {
    putenv('DB_USERNAME=root');
    putenv('DB_PASSWORD=' . (getenv('MYSQL_ROOT_PASSWORD') ?: ''));
}
try {
    migrateFinanceModel();
    echo "Migración 011 (modelo financiero) aplicada correctamente.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Migración incompleta: ' . $e->getMessage() . "\nRespaldá la base y revisá docs/MODELO-FINANCIERO.md antes de repetirla.\n");
    exit(1);
}
