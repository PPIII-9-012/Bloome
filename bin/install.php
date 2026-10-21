<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (PHP_SAPI !== 'cli') exit;
$email = getenv('ADMIN_EMAIL') ?: '';
$password = getenv('ADMIN_PASSWORD') ?: '';
$name = getenv('ADMIN_NAME') ?: 'Administrador';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Configurá ADMIN_EMAIL y ADMIN_PASSWORD (12 a 72 bytes) en .env antes de instalar.\n");
    exit(1);
}
try {
    $sqlFiles = [
    'schema.sql',
    'permissions_audit.sql'
];

foreach ($sqlFiles as $file) {

    $path = ROOT . '/database/' . $file;

    $sql = file_get_contents($path);

    if ($sql === false) {
        throw new RuntimeException(
            "No se pudo leer el archivo: " . $file
        );
    }

    foreach (explode(';', $sql) as $statement) {

        if (trim($statement) !== '') {
            db()->exec($statement);
        }

    }
}
    $exists = query('SELECT id FROM users WHERE email = ?', [$email])->fetchColumn();
    if (!$exists) query('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)', [$name, $email, password_hash($password, PASSWORD_DEFAULT), 'admin']);
    echo "Esquema instalado. Administrador " . ($exists ? 'existente conservado' : 'creado') . ".\n";
} catch (Throwable $e) {
    fwrite(STDERR, "No se pudo instalar. Revisá MySQL, sus permisos y .env. " . $e->getCode() . "\n");
    exit(1);
}
