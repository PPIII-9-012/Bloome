<?php
declare(strict_types=1);
// Pruebas HTTP de cobros, señas y cobro parcial (#16). Usa su propia base temporal.
require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT . '/app/employee-migration.php';

$database = 'bloome_test_pay_' . bin2hex(random_bytes(5));
$host = getenv('DB_HOST') ?: '127.0.0.1'; $port = getenv('DB_PORT') ?: '3306'; $rootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: '';
$root = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", 'root', $rootPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0; $server = null; $webPort = random_int(30001, 32000); $cookies = [];
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
    query("INSERT INTO clients(id,first_name,last_name) VALUES (1,'Ana','Uno'),(2,'Beto','Dos')");
    query("INSERT INTO services(id,name,duration_minutes,price) VALUES (1,'Facial',60,1000)");
    query("INSERT INTO appointments(id,client_id,employee_id,starts_at,ends_at,status) VALUES (10,1,503,'2026-11-02 10:00:00','2026-11-02 11:00:00','confirmed'),(11,1,503,'2026-11-03 10:00:00','2026-11-03 11:00:00','cancelled')");
    $sale = createSale(3, ['client_id' => '1', 'employee_id' => '503', 'appointment_id' => '10', 'lines' => [['service_id' => '1', 'quantity' => '1', 'discount_type' => 'none', 'discount_value' => '']]]);
    $other = createSale(3, ['client_id' => '2', 'employee_id' => '503', 'lines' => [['service_id' => '1', 'quantity' => '1', 'discount_type' => 'none', 'discount_value' => '']]]);
    $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$webPort", '-t', ROOT . '/public', ROOT . '/public/router.php'], [0 => ['pipe', 'r'], 1 => ['file', 'php://temp', 'w'], 2 => ['file', 'php://temp', 'w']], $pipes, ROOT);
    for ($i = 0; $i < 50; $i++) { $socket = @fsockopen('127.0.0.1', $webPort); if ($socket) { fclose($socket); break; } usleep(100000); }

    slCheck(slRequest("page=sale-pay&id=$sale")[0] === 303 && slRequest('page=deposit-new&appointment=10')[0] === 303, 'Cobrar y registrar seña exigen sesión');
    slLogin('professional@example.test', $password);
    slCheck(slRequest("page=sale-pay&id=$sale")[0] === 403 && slRequest('page=deposit-new&appointment=10')[0] === 403, 'Un profesional no puede cobrar');

    slLogin('reception@example.test', $password);
    [$status, $body] = slRequest("page=sale-pay&id=$sale");
    slCheck($status === 200 && str_contains($body, 'No hay una caja abierta') && !str_contains($body, 'Registrar cobro'), 'Sin caja abierta se ofrece abrirla y no se puede cobrar');
    $csrf = slToken($body);
    slCheck(slRequest("page=sale-pay&id=$sale", ['csrf' => $csrf, 'amount' => '100', 'method' => 'cash', 'idempotency_key' => 'clave-sin-caja-1'])[0] === 409 && (int)query('SELECT COUNT(*) FROM payments')->fetchColumn() === 0, 'Un POST directo sin caja abierta se rechaza y no guarda');
    slCheck(slRequest("page=sale-pay&id=$sale", ['csrf' => $csrf, 'intent' => 'open-cash', 'opening_cash' => '500'])[0] === 303 && currentCashSession() !== false, 'Se abre la caja desde la pantalla de cobro');

    // Cobro parcial
    [, $body] = slRequest("page=sale-pay&id=$sale"); $csrf = slToken($body);
    preg_match('/name="idempotency_key" value="([a-f0-9]+)"/', $body, $m); $key = $m[1];
    slCheck(str_contains($body, 'value="1000.00"') && str_contains($body, 'Registrar cobro'), 'El importe se sugiere con el saldo pendiente');
    $pay = ['csrf' => $csrf, 'amount' => '400', 'method' => 'cash', 'idempotency_key' => $key];
    [$status, , $location] = slRequest("page=sale-pay&id=$sale", $pay);
    slCheck($status === 303 && str_contains($location, "page=sale&id=$sale") && saleBalance($sale)['paid'] === 40000 && saleBalance($sale)['collection'] === 'partial', 'Un cobro parcial queda como "parcial" con saldo 600');
    [$status, , $location] = slRequest("page=sale-pay&id=$sale", $pay);
    slCheck($status === 303 && (int)query('SELECT COUNT(*) FROM payments')->fetchColumn() === 1 && (int)query('SELECT COUNT(*) FROM cash_movements WHERE kind=\'payment\'')->fetchColumn() === 1, 'Reenviar (doble clic) no duplica el cobro ni el movimiento de caja');
    [$status, $body] = slRequest("page=sale-pay&id=$sale", ['amount' => '401'] + $pay);
    slCheck($status === 409 && str_contains($body, 'otros datos'), 'La misma clave con otro importe se rechaza');
    [$status, $body] = slRequest("page=sale-pay&id=$sale", ['csrf' => $csrf, 'amount' => '700', 'method' => 'cash', 'idempotency_key' => 'otra-clave-0001']);
    slCheck($status === 422 && str_contains($body, 'supera el saldo') && str_contains($body, 'value="700"'), 'Cobrar más que el saldo se rechaza y conserva lo escrito');
    foreach (['0', '-5', 'abc', '10,555', ''] as $i => $bad) {
        slCheck(slRequest("page=sale-pay&id=$sale", ['csrf' => $csrf, 'amount' => $bad, 'method' => 'cash', 'idempotency_key' => "mala-clave-$i-xx"])[0] === 422, "Importe inválido '$bad' se rechaza");
    }
    slCheck(slRequest("page=sale-pay&id=$sale", ['csrf' => $csrf, 'amount' => '10', 'method' => 'bitcoin', 'idempotency_key' => 'medio-malo-001'])[0] === 422, 'Un medio de pago inexistente se rechaza');
    [, $body] = slRequest("page=sale&id=$sale");
    slCheck(str_contains($body, 'Cobro parcial') && str_contains($body, '$ 400,00') && str_contains($body, '$ 600,00') && str_contains($body, 'Vigente'), 'El detalle muestra cobrado, saldo y el cobro vigente');

    // Seña de la cita, aplicada después a la venta
    [$status, $body] = slRequest('page=deposit-new&appointment=10'); $csrf = slToken($body);
    preg_match('/name="idempotency_key" value="([a-f0-9]+)"/', $body, $m);
    slCheck($status === 200 && str_contains($body, 'Registrar seña') && slRequest('page=deposit-new&appointment=11')[0] === 409 && slRequest('page=deposit-new&appointment=999')[0] === 404, 'La seña se pide solo para citas vigentes');
    $dep = ['csrf' => $csrf, 'amount' => '250', 'method' => 'transfer', 'idempotency_key' => $m[1]];
    [$status] = slRequest('page=deposit-new&appointment=10', $dep); slRequest('page=deposit-new&appointment=10', $dep);
    $depositId = (int)query("SELECT id FROM payments WHERE kind='deposit'")->fetchColumn();
    slCheck($status === 303 && (int)query("SELECT COUNT(*) FROM payments WHERE kind='deposit'")->fetchColumn() === 1 && depositAvailableCents($depositId) === 25000 && saleBalance($sale)['paid'] === 40000, 'La seña se registra una sola vez y todavía no reduce el saldo de la venta');
    [, $body] = slRequest("page=sale-pay&id=$sale");
    slCheck(str_contains($body, 'Señas disponibles') && str_contains($body, 'Seña #' . $depositId) && str_contains($body, 'value="250.00"'), 'La pantalla de cobro ofrece la seña disponible');
    $csrf = slToken($body);
    $movementsBefore = (int)query('SELECT COUNT(*) FROM cash_movements')->fetchColumn();
    slCheck(slRequest("page=sale-pay&id=$other", ['csrf' => $csrf, 'intent' => 'apply-deposit', 'payment_id' => $depositId, 'amount' => '100'])[0] === 422, 'Una seña no se aplica a la venta de otro cliente');
    slCheck(slRequest("page=sale-pay&id=$sale", ['csrf' => $csrf, 'intent' => 'apply-deposit', 'payment_id' => $depositId, 'amount' => '300'])[0] === 422, 'No se aplica más que lo disponible de la seña');
    slCheck(slRequest("page=sale-pay&id=$sale", ['csrf' => $csrf, 'intent' => 'apply-deposit', 'payment_id' => $depositId, 'amount' => '250'])[0] === 303, 'Se aplica la seña completa');
    $b = saleBalance($sale);
    slCheck($b['paid'] === 65000 && $b['balance'] === 35000 && depositAvailableCents($depositId) === 0 && (int)query('SELECT COUNT(*) FROM cash_movements')->fetchColumn() === $movementsBefore, 'Aplicar la seña baja el saldo a 350 sin mover la caja otra vez');
    slCheck(slRequest("page=sale-pay&id=$sale", ['csrf' => $csrf, 'intent' => 'apply-deposit', 'payment_id' => $depositId, 'amount' => '1'])[0] === 422, 'La seña ya aplicada no se puede reutilizar');

    // Saldar y cerrar
    [, $body] = slRequest("page=sale-pay&id=$sale"); $csrf = slToken($body); preg_match('/name="idempotency_key" value="([a-f0-9]+)"/', $body, $m);
    slCheck(slRequest("page=sale-pay&id=$sale", ['csrf' => $csrf, 'amount' => '350', 'method' => 'debit_card', 'idempotency_key' => $m[1]])[0] === 303 && saleBalance($sale)['collection'] === 'paid', 'El último cobro deja la venta pagada');
    [$status, $body] = slRequest("page=sale-pay&id=$sale");
    slCheck($status === 200 && str_contains($body, 'no tiene saldo pendiente') && !str_contains($body, 'Registrar cobro'), 'Una venta pagada no ofrece más cobros');
    $totals = cashTotalsByMethod((int)currentCashSession()['id']);
    slCheck(cashExpectedCents((int)currentCashSession()['id']) === 50000 + 40000 && $totals['transfer'] === 25000 && $totals['debit_card'] === 35000, 'La caja cuenta cada importe una vez: 400 + 250 + 350 entran, la seña aplicada no suma otra vez');

    // Venta anulada
    voidSale(1, $other, 'Prueba de anulación');
    slCheck(slRequest("page=sale-pay&id=$other")[0] === 409, 'No se cobra una venta anulada');
    slLogin('admin@example.test', $password);
    slCheck(slRequest("page=sale-pay&id=$sale")[0] === 200, 'Administración también puede abrir la pantalla de cobro');

    echo "\n$checks pruebas de cobros aprobadas.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if (preg_match('/^bloome_test_pay_[a-f0-9]{10}$/D', $database)) $root->exec("DROP DATABASE IF EXISTS `$database`");
}
