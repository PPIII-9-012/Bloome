<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
$envFile = ROOT . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        if (getenv(trim($key)) === false) putenv(trim($key) . '=' . trim($value));
    }
}
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'America/Argentina/Buenos_Aires');
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/database.php';
if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    session_name('bloome_session');
    session_set_cookie_params(['httponly' => true, 'secure' => getenv('APP_SECURE_COOKIE') === '1', 'samesite' => 'Lax']);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
    header('Cache-Control: no-store');
}
