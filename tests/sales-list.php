<?php
declare(strict_types=1);
// Pruebas HTTP de listado y búsqueda de ventas (#17). Usa su propia base temporal.
require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT . '/app/employee-migration.php';

$database = 'bloome_test_list_' . bin2hex(random_bytes(5));
$host = getenv('DB_HOST') ?: '127.0.0.1'; $port = getenv('DB_PORT') ?: '3306'; $rootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: '';
$root = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", 'root', $rootPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0; $server = null; $webPort = random_int(34001, 36000); $cookies = [];
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
    query("INSERT INTO clients(id,first_name,last_name) VALUES (1,'Ana','Uno'),(2,'Beto','Dos'),(3,'Carla','Tres')");
    query("INSERT INTO services(id,name,duration_minutes,price) VALUES (1,'Facial',60,1000)");
    $mk = fn(string $client) => createSale(3, ['client_id' => $client, 'employee_id' => '503', 'lines' => [['service_id' => '1', 'quantity' => '1', 'discount_type' => 'none', 'discount_value' => '']]]);
    openCashSession(3, '0');
    $ids = [];
    for ($i = 0; $i < 14; $i++) $ids[] = $mk('1');           // 14 ventas de Ana (1..14), sin cobrar
    $partial = $mk('2'); $paid = $mk('2'); $voided = $mk('3');  // 15, 16, 17
    recordPayment(3, ['idempotency_key' => 'lista-parcial-001', 'method' => 'cash', 'amount' => '300', 'sale_id' => (string)$partial]);
    recordPayment(3, ['idempotency_key' => 'lista-pagada-0001', 'method' => 'cash', 'amount' => '1000', 'sale_id' => (string)$paid]);
    voidSale(1, $voided, 'Venta de prueba anulada');
    query('UPDATE sales SET created_at=? WHERE id=?', ['2026-01-10 10:00:00', $ids[0]]);
    $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$webPort", '-t', ROOT . '/public', ROOT . '/public/router.php'], [0 => ['pipe', 'r'], 1 => ['file', 'php://temp', 'w'], 2 => ['file', 'php://temp', 'w']], $pipes, ROOT);
    for ($i = 0; $i < 50; $i++) { $socket = @fsockopen('127.0.0.1', $webPort); if ($socket) { fclose($socket); break; } usleep(100000); }
    $rows = fn(string $body) => substr_count($body, '<td data-label="N.º"');

    slCheck(slRequest('page=sales')[0] === 303, 'El listado exige sesión');
    slLogin('professional@example.test', $password);
    [$status, $body] = slRequest('page=sales');
    slCheck($status === 403 && !str_contains($body, 'sales-table'), 'Un profesional no ve el listado de ventas');
    slLogin('reception@example.test', $password);
    [$status, $body] = slRequest('page=sales');
    slCheck($status === 200 && $rows($body) === 12 && str_contains($body, '17 ventas · Página 1 de 2') && str_contains($body, 'page=sales&amp;p=2'), 'Muestra 12 ventas por página y pagina las 17');
    slCheck(str_contains($body, 'aria-current="page"') && str_contains($body, 'Nueva venta'), 'El menú marca Ventas y ofrece Nueva venta');
    slCheck(strpos($body, '#17') < strpos($body, '#16') && strpos($body, '#6') !== false, 'Lista primero las más nuevas');
    [, $body2] = slRequest('page=sales&p=2');
    slCheck($rows($body2) === 5 && str_contains($body2, 'Página 2 de 2'), 'La segunda página trae las 5 restantes');
    [$status, $body] = slRequest('page=sales&p=99');
    slCheck($status === 200 && str_contains($body, 'Página 2 de 2'), 'Una página fuera de rango vuelve a la última');

    [, $body] = slRequest('page=sales&q=Beto');
    slCheck($rows($body) === 2 && !str_contains($body, 'Ana Uno'), 'Busca por nombre del cliente');
    [, $body] = slRequest('page=sales&q=' . urlencode('Dos Beto'));
    slCheck($rows($body) === 2, 'Busca también por "apellido nombre"');
    [, $body] = slRequest('page=sales&q=' . urlencode('#16'));
    slCheck($rows($body) === 1 && str_contains($body, 'Cobrada'), 'Busca por número de venta (#16)');
    [$status, $body] = slRequest('page=sales&q=' . urlencode("%' OR 1=1 --"));
    slCheck($status === 200 && $rows($body) === 0 && str_contains($body, 'No encontramos ventas'), 'Los comodines y comillas del buscador no amplían la búsqueda');

    [, $body] = slRequest('page=sales&status=unpaid'); slCheck(str_contains($body, '14 ventas'), 'El filtro sin cobrar cuenta 14 ventas');
    foreach (['unpaid' => 12, 'partial' => 1, 'paid' => 1, 'voided' => 1] as $state => $expected) {
        [, $body] = slRequest("page=sales&status=$state");
        slCheck($rows($body) === $expected, "El filtro de estado '$state' devuelve $expected en la página");
    }
    [, $body] = slRequest('page=sales&status=partial');
    slCheck(str_contains($body, 'Cobro parcial') && str_contains($body, '$ 700,00'), 'La venta parcial muestra saldo 700');
    [, $body] = slRequest('page=sales&status=voided');
    slCheck(str_contains($body, 'Anulada') && str_contains($body, '$ 0,00'), 'La anulada muestra saldo 0');
    [$status, $body] = slRequest('page=sales&status=hack');
    slCheck($status === 200 && str_contains($body, '17 ventas'), 'Un estado desconocido se ignora');

    [, $body] = slRequest('page=sales&from=2026-01-10&to=2026-01-10');
    slCheck($rows($body) === 1 && str_contains($body, '#' . $ids[0]), 'El rango de fechas es inclusivo (una sola venta del 10/01)');
    [$status, $body] = slRequest('page=sales&from=2026-02-01&to=2026-01-01');
    slCheck($status === 422 && str_contains($body, 'no puede ser posterior') && str_contains($body, '17 ventas'), 'Un rango invertido avisa y no filtra');
    [$status, $body] = slRequest('page=sales&from=ayer');
    slCheck($status === 422 && str_contains($body, 'Revisá las fechas'), 'Una fecha inválida avisa');
    [, $body] = slRequest('page=sales&q=Ana&status=unpaid&p=2');
    slCheck($rows($body) === 2 && str_contains($body, 'Página 2 de 2') && str_contains($body, 'value="Ana"'), 'Los filtros se combinan con la paginación (14 de Ana: 12 + 2)');
    slCheck(str_contains($body, 'q=Ana') && str_contains($body, 'status=unpaid') && str_contains($body, 'Limpiar'), 'Los enlaces conservan los filtros y se puede limpiar');
    [$status, $body] = slRequest('page=sales&q=Zzz');
    slCheck($status === 200 && str_contains($body, 'No encontramos ventas con esos filtros') && !str_contains($body, 'onclick='), 'Sin resultados muestra un estado vacío');
    [$status, , ] = slRequest('page=sales', ['csrf' => 'x']);
    slCheck($status !== 200 && (int)query('SELECT COUNT(*) FROM sales')->fetchColumn() === 17, 'El listado no acepta POST');
    slLogin('admin@example.test', $password);
    slCheck(slRequest('page=sales')[0] === 200, 'Administración también ve el listado');

    // --- Filtros por empleado y medio de pago, y totales ---
    slLogin('reception@example.test', $password);
    [, $body] = slRequest('page=sales');
    slCheck(str_contains($body, 'Vendido') && str_contains($body, '$ 16.000,00') && str_contains($body, '$ 1.300,00') && str_contains($body, '$ 14.700,00'), 'Totales de todo el conjunto: vendido 16.000, cobrado 1.300, pendiente 14.700 (sin la anulada)');
    [, $body] = slRequest('page=sales&q=Beto');
    slCheck(str_contains($body, '$ 2.000,00') && str_contains($body, '$ 1.300,00') && str_contains($body, '$ 700,00'), 'Los totales siguen los filtros');
    [, $body] = slRequest('page=sales&employee=503');
    slCheck(str_contains($body, '17 ventas'), 'El filtro por empleado incluye las ventas de ese empleado');
    [, $body] = slRequest('page=sales&employee=502');
    slCheck(str_contains($body, 'No encontramos ventas') && str_contains($body, '$ 0,00'), 'Otro empleado sin ventas: vacío y totales en cero');
    [, $body] = slRequest('page=sales&employee=999999');
    slCheck(str_contains($body, '17 ventas'), 'Un empleado inexistente se ignora');
    [, $body] = slRequest('page=sales&method=cash');
    slCheck($rows($body) === 2 && str_contains($body, '2 ventas'), 'El filtro por medio de pago devuelve las ventas cobradas en efectivo');
    [, $body] = slRequest('page=sales&method=transfer');
    slCheck(str_contains($body, 'No encontramos ventas'), 'Sin cobros por transferencia no hay resultados');
    [, $body] = slRequest('page=sales&method=bitcoin');
    slCheck(str_contains($body, '17 ventas'), 'Un medio desconocido se ignora');
    [, $body] = slRequest('page=sales&employee=503&method=cash&status=partial');
    slCheck($rows($body) === 1 && str_contains($body, 'Cobro parcial') && str_contains($body, '1 ventas'), 'Empleado, medio de pago y estado se combinan');
    [, $body] = slRequest('page=sales&employee=503&p=2');
    slCheck(str_contains($body, 'Página 2 de 2') && str_contains($body, 'page=sales&amp;employee=503&amp;p=1'), 'Los enlaces de paginación conservan el filtro de empleado');

    echo "\n$checks pruebas de listado aprobadas.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if (preg_match('/^bloome_test_list_[a-f0-9]{10}$/D', $database)) $root->exec("DROP DATABASE IF EXISTS `$database`");
}
