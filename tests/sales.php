<?php
declare(strict_types=1);
// Pruebas HTTP de "Nueva venta" y "Detalle de venta" (#15). Usa su propia base temporal.
require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT . '/app/employee-migration.php';

$database = 'bloome_test_sales_' . bin2hex(random_bytes(5));
$host = getenv('DB_HOST') ?: '127.0.0.1'; $port = getenv('DB_PORT') ?: '3306'; $rootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: '';
$root = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", 'root', $rootPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0; $server = null; $webPort = random_int(28001, 30000); $cookies = [];
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
    query("INSERT INTO services(id,name,duration_minutes,price) VALUES (1,'Facial',60,1000),(2,'Masaje',30,500)");
    query("INSERT INTO appointments(id,client_id,employee_id,starts_at,ends_at,status) VALUES (10,1,503,'2026-11-02 10:00:00','2026-11-02 11:30:00','confirmed'),(11,1,503,'2026-11-03 10:00:00','2026-11-03 10:30:00','cancelled')");
    query('INSERT INTO appointment_services(appointment_id,service_id,price,duration_minutes) VALUES (10,1,800,60),(10,2,500,30)'); // la cita acordó 800 por el facial
    $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$webPort", '-t', ROOT . '/public', ROOT . '/public/router.php'], [0 => ['pipe', 'r'], 1 => ['file', 'php://temp', 'w'], 2 => ['file', 'php://temp', 'w']], $pipes, ROOT);
    for ($i = 0; $i < 50; $i++) { $socket = @fsockopen('127.0.0.1', $webPort); if ($socket) { fclose($socket); break; } usleep(100000); }

    slCheck(slRequest('page=sale-new')[0] === 303 && slRequest('page=sale&id=1')[0] === 303, 'Nueva venta y detalle exigen sesión');
    slLogin('professional@example.test', $password);
    slCheck(slRequest('page=sale-new')[0] === 403 && slRequest('page=sale&id=1')[0] === 403, 'Un profesional no accede a ventas');

    slLogin('reception@example.test', $password);
    [$status, $body] = slRequest('page=sale-new&client=2');
    slCheck($status === 200 && str_contains($body, 'Nueva venta') && str_contains($body, '<option value="2" selected>'), 'La ficha abre la venta con el cliente elegido');
    slCheck(str_contains($body, 'Facial — $ 1.000,00') && str_contains($body, 'Masaje — $ 500,00') && !str_contains($body, 'onclick='), 'Muestra los servicios con su precio y sin controladores inline');
    $csrf = slToken($body);

    // Vista previa y agregar línea
    $lines = [['service_id' => '1', 'quantity' => '2', 'discount_type' => 'percent', 'discount_value' => '10'], ['service_id' => '', 'quantity' => '1', 'discount_type' => 'none', 'discount_value' => '']];
    $form = ['csrf' => $csrf, 'client_id' => '2', 'employee_id' => '503', 'notes' => '<b>nota</b>', 'lines' => $lines];
    [$status, $body] = slRequest('page=sale-new&client=2', $form + ['intent' => 'preview']);
    slCheck($status === 200 && str_contains($body, '$ 2.000,00') && str_contains($body, '$ 1.800,00') && str_contains($body, 'vista previa'), 'La vista previa calcula 2 x 1.000 con 10 % = 1.800');
    slCheck((int)query('SELECT COUNT(*) FROM sales')->fetchColumn() === 0, 'La vista previa no guarda nada');
    [, $body] = slRequest('page=sale-new&client=2', $form + ['intent' => 'add-line']);
    slCheck(substr_count($body, 'name="lines[') === 12, 'Agregar servicio suma una fila y conserva las demás');

    // Validaciones
    [$status, $body] = slRequest('page=sale-new', ['csrf' => $csrf, 'client_id' => '', 'lines' => $lines, 'intent' => 'save']);
    slCheck($status === 422 && str_contains($body, 'role="alert"'), 'Sin cliente se rechaza con mensaje');
    [$status, $body] = slRequest('page=sale-new', ['csrf' => $csrf, 'client_id' => '2', 'lines' => [['service_id' => '', 'quantity' => '1', 'discount_type' => 'none', 'discount_value' => '']], 'intent' => 'save']);
    slCheck($status === 422, 'Sin servicios se rechaza');
    [$status, $body] = slRequest('page=sale-new', ['csrf' => $csrf, 'client_id' => '2', 'lines' => [['service_id' => '1', 'quantity' => '1', 'discount_type' => 'amount', 'discount_value' => '5000']], 'intent' => 'save']);
    slCheck($status === 422 && str_contains($body, 'descuento'), 'Un descuento mayor que la línea se rechaza');
    slCheck((int)query('SELECT COUNT(*) FROM sales')->fetchColumn() === 0 && slRequest('page=sale-new', ['client_id' => '2'])[0] === 419, 'Los rechazos no guardan nada y el POST exige CSRF');

    // Guardado desde ficha
    [$status, , $location] = slRequest('page=sale-new&client=2', $form + ['intent' => 'save']);
    slCheck($status === 303 && preg_match('/page=sale&id=(\d+)/', $location, $m) === 1, 'Guardar redirige al detalle');
    $saleId = (int)$m[1];
    $row = query('SELECT * FROM sales WHERE id=?', [$saleId])->fetch();
    slCheck($row['total'] === '1800.00' && (int)$row['client_id'] === 2 && (int)$row['employee_id'] === 503 && $row['notes'] === '<b>nota</b>' && $row['appointment_id'] === null && (int)$row['created_by'] === 3, 'La venta se guarda con total del servidor, empleado, notas y autor');
    [$status, $body] = slRequest($location ? substr($location, 2) : '');
    slCheck($status === 200 && str_contains($body, 'Venta #' . $saleId) && str_contains($body, '$ 1.800,00') && str_contains($body, 'Sin cobrar') && str_contains($body, '&lt;b&gt;nota&lt;/b&gt;') && !str_contains($body, '<b>nota</b>'), 'El detalle muestra total, saldo y escapa las observaciones');

    // Venta desde una cita: precio acordado, un solo cliente, una sola venta
    [$status, $body] = slRequest('page=sale-new&appointment=10');
    slCheck($status === 200 && substr_count($body, ' selected>Facial') === 1 && substr_count($body, ' selected>Masaje') === 1 && !str_contains($body, 'Beto, Dos'), 'Desde la cita precarga cliente y servicios, y bloquea otros clientes');
    $csrf = slToken($body);
    $appointmentForm = ['csrf' => $csrf, 'client_id' => '1', 'employee_id' => '503', 'notes' => '', 'intent' => 'save', 'lines' => [['service_id' => '1', 'quantity' => '1', 'discount_type' => 'none', 'discount_value' => ''], ['service_id' => '2', 'quantity' => '1', 'discount_type' => 'none', 'discount_value' => '']]];
    slCheck(slRequest('page=sale-new&appointment=10', array_replace($appointmentForm, ['client_id' => '2']))[0] === 422 && (int)query('SELECT COUNT(*) FROM sales')->fetchColumn() === 1, 'No se puede vender la cita a otro cliente');
    [$status, , $location] = slRequest('page=sale-new&appointment=10', $appointmentForm);
    preg_match('/id=(\d+)/', $location, $m); $fromAppointment = (int)$m[1];
    slCheck($status === 303 && query('SELECT total FROM sales WHERE id=?', [$fromAppointment])->fetchColumn() === '1300.00' && query('SELECT unit_price FROM sale_items WHERE sale_id=? ORDER BY id LIMIT 1', [$fromAppointment])->fetchColumn() === '800.00', 'La venta de la cita usa el precio acordado en la cita (800 + 500)');
    [$status, , $location] = slRequest('page=sale-new&appointment=10');
    slCheck($status === 303 && str_contains($location, 'page=sale&id=' . $fromAppointment), 'Abrir otra venta de la misma cita lleva a la existente');
    slCheck(slRequest('page=sale-new&appointment=10', $appointmentForm)[0] === 303 && (int)query('SELECT COUNT(*) FROM sales WHERE appointment_id=10')->fetchColumn() === 1, 'Reenviar el formulario no duplica la venta de la cita');
    slCheck(slRequest('page=sale-new&appointment=11')[0] === 422 && slRequest('page=sale-new&appointment=999')[0] === 404 && slRequest('page=sale-new&appointment=abc')[0] === 404, 'Citas canceladas o inexistentes no se venden');
    slCheck(slRequest('page=sale&id=999')[0] === 404 && slRequest('page=sale&id=abc')[0] === 404, 'Venta inexistente da 404');

    // El formulario no acepta precios: se usa siempre el del catálogo (o el acordado en la cita)
    [, $body] = slRequest('page=sale-new&client=1'); $csrf = slToken($body);
    $priced = ['csrf' => $csrf, 'client_id' => '1', 'intent' => 'save', 'lines' => [['service_id' => '2', 'quantity' => '1', 'unit_price' => '1', 'discount_type' => 'none', 'discount_value' => '']]];
    [$status, , $location] = slRequest('page=sale-new&client=1', $priced);
    preg_match('/id=(\d+)/', $location, $m);
    slCheck($status === 303 && query('SELECT total FROM sales WHERE id=?', [$m[1]])->fetchColumn() === '500.00', 'Un precio enviado por formulario se ignora: rige el del catálogo');
    slLogin('admin@example.test', $password);
    slCheck(slRequest('page=sale&id=' . $saleId)[0] === 200, 'Administración también ve las ventas');

    echo "\n$checks pruebas de ventas aprobadas.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if (preg_match('/^bloome_test_sales_[a-f0-9]{10}$/D', $database)) $root->exec("DROP DATABASE IF EXISTS `$database`");
}
