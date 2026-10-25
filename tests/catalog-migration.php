<?php
declare(strict_types=1);
// Migración del catálogo de servicios sobre una base que ya tenía la tabla `services` simple. Usa su propia base temporal.
require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT . '/app/catalog-migration.php';

$database = 'bloome_test_catalog_' . bin2hex(random_bytes(5));
$host = getenv('DB_HOST') ?: '127.0.0.1'; $port = getenv('DB_PORT') ?: '3306'; $rootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: '';
$root = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", 'root', $rootPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0;
function catCheck(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException('FALLÓ: ' . $label); $checks++; echo "OK: $label\n"; }
$columns = fn() => array_column(query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='services'")->fetchAll(), 'COLUMN_NAME');
try {
    $root->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    putenv('DB_DATABASE=' . $database); putenv('DB_USERNAME=root'); putenv('DB_PASSWORD=' . $rootPassword);
    // Base anterior: `services` solo con nombre, duración, precio y estado.
    db()->exec("CREATE TABLE services (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, duration_minutes SMALLINT UNSIGNED NOT NULL, price DECIMAL(12,2) NOT NULL, active BOOLEAN NOT NULL DEFAULT TRUE, CONSTRAINT valid_duration CHECK (duration_minutes > 0), CONSTRAINT valid_price CHECK (price >= 0)) ENGINE=InnoDB");
    query("INSERT INTO services(name,duration_minutes,price) VALUES ('Facial',60,1000),('Masaje',45,800)");
    catCheck(!in_array('category_id', $columns(), true), 'La base de partida no tiene las columnas nuevas');

    migrateServiceCatalog();
    foreach (['reference', 'category_id', 'tax_percentage', 'sort_order'] as $column) catCheck(in_array($column, $columns(), true), "Agrega la columna $column");
    catCheck((int)query('SELECT COUNT(*) FROM services')->fetchColumn() === 2, 'Conserva los servicios existentes');
    catCheck((string)query("SELECT tax_percentage FROM services WHERE name='Facial'")->fetchColumn() === '0.00', 'Los servicios existentes quedan sin impuesto');
    catCheck((bool)query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='service_categories'")->fetchColumn(), 'Crea la tabla de categorías');

    migrateServiceCatalog();
    catCheck(count($columns()) === 9, 'Repetir la migración no duplica nada');
    $root->exec("INSERT INTO `$database`.service_categories(id,name) VALUES (1,'Faciales')");
    query("UPDATE services SET category_id=1, reference='FAC-01' WHERE name='Facial'");
    $duplicate = false;
    try { query("UPDATE services SET reference='FAC-01' WHERE name='Masaje'"); } catch (PDOException $e) { $duplicate = true; }
    catCheck($duplicate, 'La referencia queda única');
    $badTax = false;
    try { query("UPDATE services SET tax_percentage=150 WHERE name='Masaje'"); } catch (PDOException $e) { $badTax = true; }
    catCheck($badTax, 'El impuesto debe estar entre 0 y 100');
    $badCategory = false;
    try { query("UPDATE services SET category_id=999 WHERE name='Masaje'"); } catch (PDOException $e) { $badCategory = true; }
    catCheck($badCategory, 'La categoría debe existir');

    echo "\n$checks pruebas de migración del catálogo aprobadas.\n";
} finally {
    if (preg_match('/^bloome_test_catalog_[a-f0-9]{10}$/D', $database)) $root->exec("DROP DATABASE IF EXISTS `$database`");
}
