<?php
declare(strict_types=1);

function db(): PDO
{
    static $connection;
    if (!$connection) {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $database = getenv('DB_DATABASE') ?: 'bloome';
        $connection = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", getenv('DB_USERNAME') ?: 'bloome', getenv('DB_PASSWORD') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $timezone = $connection->prepare('SET time_zone = ?');
        $timezone->execute([date('P')]);
    }
    return $connection;
}

function query(string $sql, array $params = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}
