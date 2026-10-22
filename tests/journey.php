<?php
declare(strict_types=1);
// Recorrido integrado (#29, parte C): cliente -> turno -> venta -> cobro -> caja -> estadísticas, con persistencia, conciliación y permisos por rol. Base temporal propia.
require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT . '/app/employee-migration.php';
require ROOT . '/app/client-migration.php';
require ROOT . '/app/stats.php';

$database = 'bloome_test_journey_' . bin2hex(random_bytes(5));
$host = getenv('DB_HOST') ?: '127.0.0.1'; $port = getenv('DB_PORT') ?: '3306'; $rootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: '';
$root = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", 'root', $rootPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0; $server = null; $webPort = random_int(42001, 44000); $cookies = [];
function jCheck(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException('FALLÓ: ' . $label); $checks++; echo "OK: $label\n"; }
function jRequest(string $query, ?array $data = null): array {
    global $webPort, $cookies;
    $ctx = stream_context_create(['http' => ['method' => $data === null ? 'GET' : 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: " . implode('; ', $cookies), 'content' => $data === null ? '' : http_build_query($data), 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15]]);
    $body = file_get_contents("http://127.0.0.1:$webPort/?$query", false, $ctx);
    $headers = http_get_last_response_headers(); preg_match('/\s(\d{3})\s/', $headers[0], $m);
    $location = '';
    foreach ($headers as $header) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $cookie)) $cookies[$cookie[1]] = $cookie[1] . '=' . $cookie[2];
        if (stripos($header, 'Location:') === 0) $location = trim(substr($header, 9));
    }
    return [(int)$m[1], (string)$body, $location];
}
function jToken(string $body): string { preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $m); return $m[1] ?? ''; }
function jLogin(string $email, string $password): void {
    global $cookies; $cookies = [];
    [, $body] = jRequest('page=login'); jRequest('page=login', ['csrf' => jToken($body), 'email' => $email, 'password' => $password]);
}
try {
    $root->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    putenv('DB_DATABASE=' . $database); putenv('DB_USERNAME=root'); putenv('DB_PASSWORD=' . $rootPassword);
    foreach (explode(';', file_get_contents(ROOT . '/database/schema.sql')) as $sql) if (trim($sql) !== '') db()->exec($sql);
    $password = bin2hex(random_bytes(16)); $hash = password_hash($password, PASSWORD_DEFAULT);
    foreach ([[1, 'admin'], [2, 'professional'], [3, 'reception']] as [$uid, $role]) query('INSERT INTO users(id,name,email,password_hash,role) VALUES (?,?,?,?,?)', [$uid, 'QA ' . $role, $role . '@example.test', $hash, $role]);
    query('INSERT INTO employees(id,user_id) VALUES (501,1),(502,2),(503,3)'); migrateEmployees();
    query("INSERT INTO service_categories(id,name) VALUES (1,'Faciales')");
    query("INSERT INTO services(id,name,category_id,duration_minutes,price) VALUES (1,'Facial',1,60,10000)");
    foreach ([1, 2, 3, 4, 5, 6, 7] as $day) {
        query("INSERT INTO business_schedules(day_of_week,start_time,end_time,is_open) VALUES (?,'09:00','18:00',1)", [$day]);
        foreach ([1, 2, 3] as $uid) query("INSERT INTO employee_schedules(user_id,day_of_week,start_time,end_time,is_working) VALUES (?,?,'09:00','18:00',1)", [$uid, $day]);
    }
    $today = date('Y-m-d');

    $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$webPort", '-t', ROOT . '/public', ROOT . '/public/router.php'], [0 => ['pipe', 'r'], 1 => ['file', 'php://temp', 'w'], 2 => ['file', 'php://temp', 'w']], $pipes, ROOT);
    for ($i = 0; $i < 50; $i++) { $socket = @fsockopen('127.0.0.1', $webPort); if ($socket) { fclose($socket); break; } usleep(100000); }

    // --- Inicio sin caja ni ventas
    jLogin('reception@example.test', $password);
    [$status, $body] = jRequest('page=dashboard');
    jCheck($status === 200 && str_contains($body, 'Vendido hoy') && str_contains($body, 'Cerrada'), 'Inicio: sin ventas y con la caja cerrada lo dice con claridad');
    jCheck(!str_contains($body, 'style="') && !str_contains($body, 'onclick='), 'Inicio no usa estilos ni eventos en línea (la política de seguridad los bloquearía)');

    // --- Cliente (recepción)
    [, $body] = jRequest('page=client-new'); $csrf = jToken($body);
    [$status, , $location] = jRequest('page=client-new', ['csrf' => $csrf, 'first_name' => 'Lucía', 'last_name' => 'Recorrido', 'email' => 'lucia@example.test', 'phone' => '1155550000', 'birth_date' => '', 'gender' => 'female', 'notes' => '']);
    $clientId = (int)query("SELECT id FROM clients WHERE email='lucia@example.test'")->fetchColumn();
    jCheck($status === 303 && $clientId > 0, 'Alta de cliente por HTTP guarda y redirige');
    [$status, $body] = jRequest("page=client&id=$clientId");
    jCheck($status === 200 && str_contains($body, 'Lucía') && str_contains($body, 'Femenino'), 'La ficha muestra al cliente recién creado');

    // --- Turno
    [, $body] = jRequest('page=appointment-new'); $csrf = jToken($body);
    [$status] = jRequest('page=appointment-new', ['csrf' => $csrf, 'client_id' => (string)$clientId, 'employee_id' => '503', 'starts_at' => $today . 'T10:00', 'status' => 'confirmed', 'notes' => '', 'services' => ['1']]);
    $appointmentId = (int)query('SELECT MAX(id) FROM appointments')->fetchColumn();
    jCheck($status === 303 && $appointmentId > 0, 'Alta de turno por HTTP guarda y redirige');
    [, $body] = jRequest('page=dashboard');
    jCheck(str_contains($body, 'Lucía'), 'El turno de hoy aparece en el Inicio');
    [, $body] = jRequest("page=agenda&date=$today");
    jCheck(str_contains($body, 'Lucía'), 'El turno aparece en la agenda del día');

    // --- Venta y cobro
    openCashSession(3, '1000');
    $sale = createSale(3, ['client_id' => (string)$clientId, 'employee_id' => '503', 'appointment_id' => (string)$appointmentId, 'lines' => [['service_id' => '1', 'quantity' => '1', 'discount_type' => 'none', 'discount_value' => '']]]);
    [, $body] = jRequest("page=sale-pay&id=$sale"); $csrf = jToken($body);
    preg_match('/name="idempotency_key" value="([a-f0-9]+)"/', $body, $m);
    [$status] = jRequest("page=sale-pay&id=$sale", ['csrf' => $csrf, 'amount' => '40', 'method' => 'cash', 'idempotency_key' => $m[1] ?? '']);
    jCheck($status === 303, 'El cobro parcial se registra por HTTP');
    [, $body] = jRequest("page=sale&id=$sale");
    jCheck(str_contains($body, 'Parcial') || str_contains($body, 'parcial'), 'El detalle de la venta refleja el cobro parcial');

    // --- Persistencia: la información sigue ahí tras volver a entrar
    jLogin('reception@example.test', $password);
    [, $body] = jRequest("page=client&id=$clientId");
    jCheck(str_contains($body, 'Lucía') && str_contains($body, (string)$sale), 'Tras volver a entrar, la ficha conserva la venta del cliente');

    // --- Inicio, Ventas, Caja y Estadísticas dicen lo mismo
    $expected = decimalToCents('10000'); $collected = decimalToCents('40');
    [, $body] = jRequest('page=dashboard');
    jCheck(str_contains($body, moneyFormat($expected)) && str_contains($body, moneyFormat($collected)) && !str_contains($body, 'Cerrada'), 'Inicio muestra lo vendido, lo cobrado y la caja abierta');
    [, , $totals] = searchSales(['from' => $today, 'to' => $today], 1);
    jCheck($totals['sold'] === $expected && $totals['collected'] === $collected && $totals['pending'] === $expected - $collected, 'El listado de Ventas concilia con el recorrido');
    $stats = statsSales('general', ['from' => $today, 'to' => $today, 'category' => '', 'service' => '', 'gender' => '']);
    jCheck($stats['totals']['sold'] === $expected && $stats['totals']['collected'] === $collected, 'Estadísticas concilian con Ventas');
    jCheck(cashExpectedCents((int)currentCashSession()['id']) === decimalToCents('1000') + $collected, 'La caja espera apertura + lo cobrado en efectivo');
    [$status, $body] = jRequest('page=caja');
    jCheck($status === 200 && str_contains($body, '<h1') && str_contains($body, 'Abierta'), 'Caja tiene título y muestra la caja abierta');
    [$status, $body] = jRequest('page=sales');
    jCheck($status === 200 && str_contains($body, '<h1') && str_contains($body, 'Recorrido'), 'Ventas tiene título y lista la venta');

    // --- Rastreo: ningún enlace del menú está roto, por rol
    foreach (['admin' => true, 'reception' => true, 'professional' => false] as $role => $finance) {
        jLogin($role . '@example.test', $password);
        [, $home] = jRequest('page=dashboard');
        jCheck(str_contains($home, 'Vendido hoy') === $finance, "Inicio de $role " . ($finance ? 'muestra' : 'oculta') . ' los indicadores de dinero');
        preg_match_all('/href="(\/\?page=[^"#]+)"/', $home, $links);
        $bad = [];
        foreach (array_unique($links[1]) as $href) {
            $path = html_entity_decode(substr($href, 2));
            if (str_contains($path, 'logout') || str_contains($path, 'export') || str_contains($path, 'print')) continue;
            [$code] = jRequest($path);
            if ($code >= 400) $bad[] = "$path => $code";
        }
        jCheck($bad === [], "Ningún enlace del Inicio de $role responde con error" . ($bad ? ': ' . implode(', ', $bad) : ''));
    }
    echo "$checks pruebas del recorrido aprobadas.\n";
} finally {
    if (is_resource($server)) proc_terminate($server);
    $root->exec("DROP DATABASE IF EXISTS `$database`");
}
