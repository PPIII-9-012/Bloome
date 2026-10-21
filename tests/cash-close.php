<?php
declare(strict_types=1);
// Pruebas HTTP de arqueo y cierre de caja (#20). Usa su propia base temporal.
require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT . '/app/employee-migration.php';

$database = 'bloome_test_close_' . bin2hex(random_bytes(5));
$host = getenv('DB_HOST') ?: '127.0.0.1'; $port = getenv('DB_PORT') ?: '3306'; $rootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: '';
$root = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", 'root', $rootPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0; $server = null; $webPort = random_int(38001, 40000); $cookies = [];
function slCheck(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException('FALLÓ: ' . $label); $checks++; echo "OK: $label\n"; }
function slRequest(string $query, ?array $data = null): array {
    global $webPort, $cookies;
    $ctx = stream_context_create(['http' => ['method' => $data === null ? 'GET' : 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: " . implode('; ', $cookies), 'content' => $data === null ? '' : http_build_query($data), 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
    $body = file_get_contents("http://127.0.0.1:$webPort/?$query", false, $ctx);
    $headers = http_get_last_response_headers(); preg_match('/\s(\d{3})\s/', $headers[0], $m);
    foreach ($headers as $header) if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $cookie)) $cookies[$cookie[1]] = $cookie[1] . '=' . $cookie[2];
    $location = ''; foreach ($headers as $header) if (str_starts_with($header, 'Location: ')) $location = substr($header, 10);
    return [(int)$m[1], $body, $location];
}
function slToken(string $body): string { preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $m); return $m[1] ?? ''; }
function slLogin(string $email, string $password): void {
    global $cookies; $cookies = [];
    [, $body] = slRequest('page=login'); slRequest('page=login', ['csrf' => slToken($body), 'email' => $email, 'password' => $password]);
}
try {
    $root->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    putenv('DB_DATABASE=' . $database); putenv('DB_USERNAME=root'); putenv('DB_PASSWORD=' . $rootPassword);
    foreach (explode(';', file_get_contents(ROOT . '/database/schema.sql')) as $sql) if (trim($sql) !== '') db()->exec($sql);
    $password = bin2hex(random_bytes(16)); $hash = password_hash($password, PASSWORD_DEFAULT);
    foreach ([[1, 'admin'], [2, 'professional'], [3, 'reception']] as [$uid, $role]) query('INSERT INTO users(id,name,email,password_hash,role) VALUES (?,?,?,?,?)', [$uid, 'QA ' . $role, $role . '@example.test', $hash, $role]);
    query('INSERT INTO employees(id,user_id) VALUES (501,1),(502,2),(503,3)'); migrateEmployees();
    query("INSERT INTO clients(id,first_name,last_name) VALUES (1,'Ana','Uno')");
    query("INSERT INTO services(id,name,duration_minutes,price) VALUES (1,'Facial',60,1000)");
    query("INSERT INTO appointments(id,client_id,employee_id,starts_at,ends_at,status) VALUES (10,1,503,'2026-11-02 10:00:00','2026-11-02 11:00:00','confirmed')");
    $mk = fn() => createSale(3, ['client_id' => '1', 'employee_id' => '503', 'lines' => [['service_id' => '1', 'quantity' => '1', 'discount_type' => 'none', 'discount_value' => '']]]);
    $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$webPort", '-t', ROOT . '/public', ROOT . '/public/router.php'], [0 => ['pipe', 'r'], 1 => ['file', 'php://temp', 'w'], 2 => ['file', 'php://temp', 'w']], $pipes, ROOT);
    for ($i = 0; $i < 50; $i++) { $socket = @fsockopen('127.0.0.1', $webPort); if ($socket) { fclose($socket); break; } usleep(100000); }

    slCheck(slRequest('page=caja-close')[0] === 303, 'El cierre exige sesión');
    slLogin('professional@example.test', $password);
    slCheck(slRequest('page=caja-close')[0] === 403, 'Un profesional no cierra la caja');
    slLogin('reception@example.test', $password);
    [$status, , $location] = slRequest('page=caja-close');
    slCheck($status === 303 && str_contains($location, 'page=caja'), 'Sin caja abierta no hay nada que cerrar');

    // Escenario de aceptación: inicial 1000 + efectivo 600 + tarjeta 400 - retiro 100 = efectivo esperado 1500
    openCashSession(3, '1000');
    $sale = $mk(); $card = $mk();
    recordPayment(3, ['idempotency_key' => 'cierre-efectivo-01', 'method' => 'cash', 'amount' => '600', 'sale_id' => (string)$sale]);
    recordPayment(3, ['idempotency_key' => 'cierre-tarjeta-001', 'method' => 'debit_card', 'amount' => '400', 'sale_id' => (string)$card]);
    recordCashMovement(3, 'withdrawal', 'cash', '100', 'Retiro para banco');
    [$dep] = recordPayment(3, ['idempotency_key' => 'cierre-sena-000001', 'method' => 'cash', 'amount' => '300', 'appointment_id' => '10']);
    $sale3 = $mk(); applyDeposit(3, $dep, $sale3, '300');   // la seña entró una vez; aplicarla no mueve caja
    $session = (int)currentCashSession()['id'];
    slCheck(cashExpectedCents($session) === 180000, 'El efectivo esperado incluye la seña una sola vez: 1.000 + 600 − 100 + 300 = 1.800');
    query('DELETE FROM cash_movements WHERE kind=\'payment\' AND method=\'cash\' AND amount=300'); // sacamos la seña para reproducir el 1.500 de la tarjeta
    slCheck(cashExpectedCents($session) === 150000, 'Con inicial 1000, efectivo 600, tarjeta 400 y retiro 100 el esperado es 1.500 y la tarjeta no cuenta');

    [$status, $body] = slRequest('page=caja-close'); $csrf = slToken($body);
    slCheck($status === 200 && str_contains($body, 'Cerrar caja #' . $session) && str_contains($body, '$ 1.500,00') && str_contains($body, 'Tarjeta de débito') && str_contains($body, '$ 400,00') && str_contains($body, 'se concilian aparte'), 'La pantalla de cierre muestra el esperado 1.500 y la tarjeta separada');
    slCheck(str_contains(slRequest('page=caja')[1], 'Cerrar caja'), 'La caja abierta ofrece el botón Cerrar caja');

    // Vista previa sin cerrar
    [$status, $body] = slRequest('page=caja-close', ['csrf' => $csrf, 'intent' => 'preview', 'counted_cash' => '1480', 'note' => '']);
    slCheck($status === 200 && str_contains($body, '-$ 20,00') && str_contains($body, 'falta efectivo'), 'La vista previa calcula la diferencia (faltan 20)');
    slCheck(currentCashSession() !== false && query('SELECT status FROM cash_sessions WHERE id=?', [$session])->fetchColumn() === 'open', 'Ver la diferencia no cierra la caja');
    foreach (['', 'abc', '-5', '10,555', '1e3'] as $bad) {
        slCheck(slRequest('page=caja-close', ['csrf' => $csrf, 'intent' => 'close', 'counted_cash' => $bad, 'note' => 'x'])[0] === 422, "Un efectivo contado '$bad' se rechaza");
    }
    [$status, $body] = slRequest('page=caja-close', ['csrf' => $csrf, 'intent' => 'close', 'counted_cash' => '1480', 'note' => '']);
    slCheck($status === 422 && str_contains($body, 'Explicala en la observación') && currentCashSession() !== false, 'Una diferencia sin observación no cierra la caja');
    slCheck(slRequest('page=caja-close', ['csrf' => $csrf, 'intent' => 'close', 'counted_cash' => '1480', 'note' => 'ab'])[0] === 422, 'Una observación demasiado corta no alcanza');
    slCheck(slRequest('page=caja-close', ['csrf' => $csrf, 'intent' => 'close', 'counted_cash' => '1500', 'note' => str_repeat('x', 256)])[0] === 422, 'Una observación de más de 255 caracteres se rechaza');
    slCheck(slRequest('page=caja-close', ['intent' => 'close', 'counted_cash' => '1500'])[0] === 419 && currentCashSession() !== false, 'El POST de cierre exige CSRF');

    // Cierre con diferencia
    [$status, , $location] = slRequest('page=caja-close', ['csrf' => $csrf, 'intent' => 'close', 'counted_cash' => '1480', 'note' => 'Faltan 20 pesos, vuelto mal dado']);
    slCheck($status === 303 && str_contains($location, 'session=' . $session), 'Cerrar redirige al detalle de la caja cerrada');
    $row = query('SELECT * FROM cash_sessions WHERE id=?', [$session])->fetch();
    slCheck($row['status'] === 'closed' && $row['expected_cash'] === '1500.00' && $row['counted_cash'] === '1480.00' && $row['difference'] === '-20.00' && $row['closing_note'] === 'Faltan 20 pesos, vuelto mal dado' && (int)$row['closed_by'] === 3 && $row['closed_at'] !== null && $row['open_key'] === null, 'El cierre guarda esperado, contado, diferencia, observación, usuario y fecha');
    $snapshot = json_decode($row['closing_summary'], true);
    slCheck($snapshot['cash']['net'] === '500.00' && $snapshot['debit_card']['net'] === '400.00' && $snapshot['cash']['out'] === '100.00', 'Guarda el resumen por medio: efectivo y tarjeta separados');
    [, $body] = slRequest('page=caja&session=' . $session);
    slCheck(str_contains($body, 'Cerrada') && str_contains($body, 'Efectivo contado') && str_contains($body, '$ 1.480,00') && str_contains($body, 'Diferencia') && str_contains($body, 'Faltan 20 pesos') && !str_contains($body, 'caja-close'), 'La caja cerrada muestra el arqueo y no ofrece cerrarla de nuevo');

    // Bloqueo tras el cierre
    $movementsAfterClose = (int)query('SELECT COUNT(*) FROM cash_movements')->fetchColumn();
    $threw = function (callable $fn, int $code): bool { try { $fn(); return false; } catch (DomainException $e) { return $e->getCode() === $code; } };
    slCheck($threw(fn() => closeCashSession(3, $session, '1480', 'otra vez'), 409) && slRequest('page=caja-close', ['csrf' => $csrf, 'intent' => 'close', 'counted_cash' => '1500', 'note' => ''])[0] === 303, 'Cerrar dos veces se rechaza (409) y la pantalla manda a la caja');
    slCheck($threw(fn() => recordPayment(3, ['idempotency_key' => 'cierre-tardio-001', 'method' => 'cash', 'amount' => '10', 'sale_id' => (string)$sale3]), 409)
        && $threw(fn() => recordCashMovement(3, 'income', 'cash', '10', 'tarde'), 409)
        && $threw(fn() => reverseCashMovement(1, (int)query("SELECT id FROM cash_movements WHERE kind='withdrawal'")->fetchColumn(), 'Corrección tardía'), 409)
        && (int)query('SELECT COUNT(*) FROM cash_movements')->fetchColumn() === $movementsAfterClose, 'Tras el cierre no entran cobros, movimientos ni correcciones a esa caja');
    $after = query('SELECT expected_cash, counted_cash, difference, closing_summary, closed_at FROM cash_sessions WHERE id=?', [$session])->fetch();

    // Lo posterior va a la caja nueva y no toca el cierre
    $other = $mk();
    openCashSession(3, '200');
    recordPayment(3, ['idempotency_key' => 'cierre-nueva-0001', 'method' => 'cash', 'amount' => '50', 'sale_id' => (string)$other]);
    voidSale(1, $sale, 'Anulada después del cierre');
    $new = (int)currentCashSession()['id'];
    slCheck($new !== $session && (int)query("SELECT COUNT(*) FROM cash_movements WHERE cash_session_id=? AND kind='refund'", [$new])->fetchColumn() === 1 && (int)query('SELECT COUNT(*) FROM cash_movements WHERE cash_session_id=?', [$session])->fetchColumn() === $movementsAfterClose, 'Una anulación posterior reintegra en la caja nueva, no en la cerrada');
    slCheck(query('SELECT expected_cash, counted_cash, difference, closing_summary, closed_at FROM cash_sessions WHERE id=?', [$session])->fetch() == $after, 'El cierre anterior queda idéntico tras las operaciones posteriores');

    // Cierre sin diferencia, sin observación, por administración (la anulación dejó el efectivo en negativo: un ingreso lo repone)
    recordCashMovement(3, 'income', 'cash', '1000', 'Reposición de efectivo');
    slLogin('admin@example.test', $password);
    [, $body] = slRequest('page=caja-close'); $csrf = slToken($body);
    $expected = cashExpectedCents($new);
    [$status, $body] = slRequest('page=caja-close', ['csrf' => $csrf, 'intent' => 'preview', 'counted_cash' => centsToDecimal($expected), 'note' => '']);
    slCheck($status === 200 && str_contains($body, 'sin diferencias'), 'Contar justo muestra "sin diferencias"');
    slCheck(slRequest('page=caja-close', ['csrf' => $csrf, 'intent' => 'close', 'counted_cash' => centsToDecimal($expected), 'note' => ''])[0] === 303, 'Administración cierra sin observación cuando no hay diferencia');
    $row = query('SELECT difference, closing_note, closed_by FROM cash_sessions WHERE id=?', [$new])->fetch();
    slCheck($row['difference'] === '0.00' && $row['closing_note'] === null && (int)$row['closed_by'] === 1, 'Diferencia 0, sin observación y cerrada por administración');

    // Sobrante
    openCashSession(3, '0');
    $third = (int)currentCashSession()['id'];
    slCheck(slRequest('page=caja-close', ['csrf' => $csrf, 'intent' => 'close', 'counted_cash' => '5', 'note' => 'Sobran 5, propina'])[0] === 303 && query('SELECT difference FROM cash_sessions WHERE id=?', [$third])->fetchColumn() === '5.00', 'Un sobrante se registra como diferencia positiva');
    [, $body] = slRequest('page=caja&session=' . $third);
    slCheck(str_contains($body, 'Sobran 5, propina') && str_contains($body, 'amount-in'), 'El detalle marca el sobrante');

    echo "\n$checks pruebas de cierre de caja aprobadas.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if (preg_match('/^bloome_test_close_[a-f0-9]{10}$/D', $database)) $root->exec("DROP DATABASE IF EXISTS `$database`");
}
