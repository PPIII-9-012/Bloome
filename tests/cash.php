<?php
declare(strict_types=1);
// Pruebas HTTP de caja diaria, movimientos y gastos (#19). Usa su propia base temporal.
require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT . '/app/employee-migration.php';

$database = 'bloome_test_cash_' . bin2hex(random_bytes(5));
$host = getenv('DB_HOST') ?: '127.0.0.1'; $port = getenv('DB_PORT') ?: '3306'; $rootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: '';
$root = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", 'root', $rootPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0; $server = null; $webPort = random_int(36001, 38000); $cookies = [];
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
    $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$webPort", '-t', ROOT . '/public', ROOT . '/public/router.php'], [0 => ['pipe', 'r'], 1 => ['file', 'php://temp', 'w'], 2 => ['file', 'php://temp', 'w']], $pipes, ROOT);
    for ($i = 0; $i < 50; $i++) { $socket = @fsockopen('127.0.0.1', $webPort); if ($socket) { fclose($socket); break; } usleep(100000); }
    $rows = fn(string $body) => substr_count($body, '<td data-label="Fecha"');
    $fresh = function (string $path) { [, $b] = slRequest($path); preg_match('/name="idempotency_key" value="([a-f0-9]+)"/', $b, $m); return [slToken($b), $m[1] ?? '']; };

    slCheck(slRequest('page=caja')[0] === 303 && slRequest('page=caja-movement')[0] === 303, 'La caja exige sesión');
    slLogin('professional@example.test', $password);
    slCheck(slRequest('page=caja')[0] === 403 && slRequest('page=caja-movement')[0] === 403, 'Un profesional no accede a la caja');
    slLogin('reception@example.test', $password);
    [$status, $body] = slRequest('page=caja');
    slCheck($status === 200 && str_contains($body, 'Todavía no hay cajas') && str_contains($body, 'Abrir caja') && !str_contains($body, 'Registrar gasto'), 'Sin cajas ofrece abrir la primera y no ofrece gastos');
    $csrf = slToken($body);
    [$status, $body] = slRequest('page=caja-movement');
    slCheck($status === 200 && str_contains($body, 'No hay una caja abierta') && !str_contains($body, 'Registrar movimiento'), 'El formulario de movimientos pide abrir la caja primero');
    slCheck(slRequest('page=caja', ['csrf' => $csrf, 'intent' => 'open-cash', 'opening_cash' => '-5'])[0] === 422, 'Un efectivo inicial inválido se rechaza');
    slCheck(slRequest('page=caja', ['csrf' => $csrf, 'intent' => 'open-cash', 'opening_cash' => '1000'])[0] === 303 && currentCashSession() !== false, 'Se abre la caja con 1000 de efectivo inicial');
    slCheck(slRequest('page=caja', ['csrf' => $csrf, 'intent' => 'open-cash', 'opening_cash' => '1'])[0] === 409 && (int)query('SELECT COUNT(*) FROM cash_sessions')->fetchColumn() === 1, 'No se abre una segunda caja a la vez');

    // Cobros automáticos: venta 600 en efectivo, seña 200 por transferencia (aplicada), todo con origen
    $sale = createSale(3, ['client_id' => '1', 'employee_id' => '503', 'lines' => [['service_id' => '1', 'quantity' => '1', 'discount_type' => 'none', 'discount_value' => '']]]);
    recordPayment(3, ['idempotency_key' => 'caja-cobro-0001', 'method' => 'cash', 'amount' => '600', 'sale_id' => (string)$sale]);
    [$dep] = recordPayment(3, ['idempotency_key' => 'caja-sena-00001', 'method' => 'transfer', 'amount' => '200', 'appointment_id' => '10']);
    applyDeposit(3, $dep, $sale, '200');
    [$status, $body] = slRequest('page=caja');
    slCheck($status === 200 && str_contains($body, 'Caja #1') && str_contains($body, 'Abierta') && str_contains($body, 'Registrar gasto') && $rows($body) === 2, 'La caja abierta lista los 2 cobros (la seña aplicada no suma otra fila)');
    slCheck(str_contains($body, 'Venta #' . $sale) && str_contains($body, 'cita #10') && str_contains($body, 'Seña'), 'Cada cobro muestra su origen: venta o seña de cita');
    slCheck(str_contains($body, '$ 1.600,00') && str_contains($body, '$ 600,00') && str_contains($body, '$ 200,00'), 'Efectivo esperado 1.600 (1.000 + 600) y entradas por medio de pago');

    // Gastos manuales
    [$status, $body] = slRequest('page=caja-movement&kind=expense');
    slCheck($status === 200 && str_contains($body, 'Insumos') && str_contains($body, 'Alquiler') && str_contains($body, '<option value="expense" selected>'), 'El formulario trae las categorías por defecto y preselecciona el tipo');
    [$csrf, $key] = $fresh('page=caja-movement&kind=expense');
    $insumos = (string)query("SELECT id FROM expense_categories WHERE name='Insumos'")->fetchColumn();
    $expense = ['csrf' => $csrf, 'kind' => 'expense', 'method' => 'cash', 'amount' => '150,50', 'category_id' => $insumos, 'description' => 'Cremas', 'idempotency_key' => $key];
    [$status, , $location] = slRequest('page=caja-movement', $expense);
    slCheck($status === 303 && str_contains($location, 'page=caja'), 'Registrar un gasto vuelve a la caja');
    slRequest('page=caja-movement', $expense);
    $mv = query("SELECT * FROM cash_movements WHERE kind='expense'")->fetchAll();
    slCheck(count($mv) === 1 && $mv[0]['amount'] === '150.50' && $mv[0]['direction'] === 'out' && (string)$mv[0]['category_id'] === $insumos && (int)$mv[0]['created_by'] === 3, 'El gasto se guarda una sola vez (doble clic) con categoría, salida y autor');
    [$status, $body] = slRequest('page=caja-movement', ['amount' => '151'] + $expense);
    slCheck($status === 409 && str_contains($body, 'otros datos'), 'La misma clave con otro importe se rechaza');
    [$csrf, $key] = $fresh('page=caja-movement');
    $bad = ['csrf' => $csrf, 'kind' => 'expense', 'method' => 'cash', 'amount' => '10', 'category_id' => $insumos, 'description' => 'x', 'idempotency_key' => $key];
    slCheck(slRequest('page=caja-movement', array_replace($bad, ['category_id' => '']))[0] === 422, 'Un gasto sin categoría se rechaza');
    slCheck(slRequest('page=caja-movement', array_replace($bad, ['description' => '']))[0] === 422, 'Un gasto sin descripción se rechaza');
    slCheck(slRequest('page=caja-movement', array_replace($bad, ['category_id' => '9999']))[0] === 422, 'Una categoría inexistente se rechaza');
    slCheck(slRequest('page=caja-movement', array_replace($bad, ['amount' => '0']))[0] === 422 && slRequest('page=caja-movement', array_replace($bad, ['amount' => '1e3']))[0] === 422, 'Importes en cero o con formato raro se rechazan');
    slCheck(slRequest('page=caja-movement', array_replace($bad, ['kind' => 'refund']))[0] === 422 && slRequest('page=caja-movement', array_replace($bad, ['kind' => 'payment']))[0] === 422, 'Desde acá no se cargan cobros ni reintegros');
    [$status, $body] = slRequest('page=caja-movement', array_replace($bad, ['amount' => '5000']));
    slCheck($status === 422 && str_contains($body, 'supera lo disponible') && str_contains($body, 'value="5000"'), 'Un gasto mayor que el efectivo disponible se rechaza y conserva lo escrito');
    slCheck(slRequest('page=caja-movement', array_replace($bad, ['method' => 'transfer', 'amount' => '300']))[0] === 422, 'No se gasta por transferencia más de lo que entró por transferencia (200)');
    slCheck(slRequest('page=caja-movement', array_replace($bad, ['amount' => '10']) + [])[0] === 303, 'Un gasto válido de 10 se acepta');
    // Ingreso y retiro
    [$csrf, $key] = $fresh('page=caja-movement&kind=income');
    slCheck(slRequest('page=caja-movement', ['csrf' => $csrf, 'kind' => 'income', 'method' => 'cash', 'amount' => '100', 'category_id' => '', 'description' => '', 'idempotency_key' => $key])[0] === 303, 'Un ingreso no exige descripción ni categoría');
    [$csrf, $key] = $fresh('page=caja-movement&kind=withdrawal');
    slCheck(slRequest('page=caja-movement', ['csrf' => $csrf, 'kind' => 'withdrawal', 'method' => 'cash', 'amount' => '200', 'category_id' => '', 'description' => 'Depósito en banco', 'idempotency_key' => $key])[0] === 303, 'Un retiro con descripción se acepta');
    $session = (int)currentCashSession()['id'];
    slCheck(cashExpectedCents($session) === 100000 + 60000 - 15050 - 1000 + 10000 - 20000, 'El efectivo esperado cierra: 1.000 + 600 − 150,50 − 10 + 100 − 200 = 1.339,50');

    // Pantalla, filtros y trazabilidad
    [, $body] = slRequest('page=caja');
    slCheck($rows($body) === 6 && str_contains($body, 'Manual · Insumos') && str_contains($body, 'Cremas') && str_contains($body, 'Depósito en banco') && str_contains($body, '$ 1.339,50'), 'El libro muestra los 6 movimientos con categoría, detalle y el esperado 1.339,50');
    [, $body] = slRequest('page=caja&method=transfer');
    slCheck($rows($body) === 1 && str_contains($body, 'Transferencia'), 'Filtra por medio de pago');
    [, $body] = slRequest('page=caja&kind=expense');
    slCheck($rows($body) === 2, 'Filtra por tipo (2 gastos)');
    [, $body] = slRequest('page=caja&kind=expense&method=transfer');
    slCheck($rows($body) === 0 && str_contains($body, 'No hay movimientos con esos filtros'), 'Los filtros se combinan y muestran estado vacío');
    slCheck(slRequest('page=caja&method=hack&kind=hack')[0] === 200, 'Filtros desconocidos se ignoran');
    slCheck(!str_contains($body, 'Corregir') && !str_contains(slRequest('page=caja')[1], 'name="intent" value="reverse"'), 'Recepción no ve acciones de corrección');

    // Permisos de recepción para corregir y crear categorías
    [, $b] = slRequest('page=caja'); $csrf = slToken($b);
    $expenseId = (int)query("SELECT id FROM cash_movements WHERE kind='expense' ORDER BY id LIMIT 1")->fetchColumn();
    slCheck(slRequest('page=caja', ['csrf' => $csrf, 'intent' => 'reverse', 'movement_id' => $expenseId, 'reason' => 'Intento de recepción'])[0] === 403 && slRequest('page=caja', ['csrf' => $csrf, 'intent' => 'add-category', 'name' => 'Hack'])[0] === 403, 'Recepción no puede corregir ni crear categorías');

    // Administración: corrección y categorías
    slLogin('admin@example.test', $password);
    [, $body] = slRequest('page=caja'); $csrf = slToken($body);
    slCheck(str_contains($body, 'name="intent" value="reverse"') && str_contains($body, 'Categorías de gasto'), 'Administración ve las correcciones y las categorías');
    slCheck(slRequest('page=caja', ['csrf' => $csrf, 'intent' => 'add-category', 'name' => 'Publicidad'])[0] === 303 && (int)query("SELECT COUNT(*) FROM expense_categories WHERE name='Publicidad'")->fetchColumn() === 1, 'Administración crea una categoría');
    slCheck(slRequest('page=caja', ['csrf' => $csrf, 'intent' => 'add-category', 'name' => 'publicidad'])[0] === 409 && slRequest('page=caja', ['csrf' => $csrf, 'intent' => 'add-category', 'name' => 'X'])[0] === 422, 'Una categoría repetida o demasiado corta se rechaza');
    slCheck(slRequest('page=caja', ['csrf' => $csrf, 'intent' => 'reverse', 'movement_id' => $expenseId, 'reason' => 'abc'])[0] === 422, 'La corrección exige un motivo de al menos 5 caracteres');
    $paymentMovement = (int)query("SELECT id FROM cash_movements WHERE kind='payment' ORDER BY id LIMIT 1")->fetchColumn();
    slCheck(slRequest('page=caja', ['csrf' => $csrf, 'intent' => 'reverse', 'movement_id' => $paymentMovement, 'reason' => 'Corregir un cobro'])[0] === 409, 'Un cobro no se corrige desde la caja: se anula la venta');
    slCheck(slRequest('page=caja', ['csrf' => $csrf, 'intent' => 'reverse', 'movement_id' => '9999', 'reason' => 'No existe'])[0] === 404, 'Corregir un movimiento inexistente da 404');
    slCheck(slRequest('page=caja', ['csrf' => $csrf, 'intent' => 'reverse', 'movement_id' => $expenseId, 'reason' => 'Gasto cargado dos veces'])[0] === 303, 'Administración corrige un gasto');
    $rev = query('SELECT * FROM cash_movements WHERE reverses_id=?', [$expenseId])->fetch();
    slCheck($rev && $rev['direction'] === 'in' && $rev['amount'] === '150.50' && $rev['kind'] === 'expense' && (int)query('SELECT COUNT(*) FROM cash_movements WHERE id=? AND amount=150.50', [$expenseId])->fetchColumn() === 1, 'La corrección es un movimiento inverso y el original queda intacto');
    slCheck(slRequest('page=caja', ['csrf' => $csrf, 'intent' => 'reverse', 'movement_id' => $expenseId, 'reason' => 'Otra vez'])[0] === 409 && slRequest('page=caja', ['csrf' => $csrf, 'intent' => 'reverse', 'movement_id' => $rev['id'], 'reason' => 'Corregir la corrección'])[0] === 409, 'No se corrige dos veces ni se corrige una corrección');
    slCheck(cashExpectedCents($session) === 133950 + 15050, 'El efectivo esperado vuelve a sumar los 150,50 corregidos (1.490,00)');
    [, $body] = slRequest('page=caja');
    slCheck(str_contains($body, '(corregido)') && str_contains($body, 'corrección de #' . $expenseId), 'El libro marca el movimiento corregido y su corrección');
    $before = (int)query('SELECT COUNT(*) FROM cash_movements')->fetchColumn();
    slCheck(slRequest('page=caja', ['intent' => 'reverse', 'movement_id' => $expenseId, 'reason' => 'sin csrf'])[0] === 419 && (int)query('SELECT COUNT(*) FROM cash_movements')->fetchColumn() === $before, 'Los POST exigen CSRF');

    // Anulación de venta: el reintegro aparece con su origen
    voidSale(1, $sale, 'Prueba de anulación');
    [, $body] = slRequest('page=caja&kind=refund');
    slCheck($rows($body) === 1 && str_contains($body, 'Venta #' . $sale) && str_contains($body, '(anulada)') && str_contains($body, 'Reintegro'), 'El reintegro de una venta anulada muestra la venta de origen');

    // Cajas anteriores
    query("UPDATE cash_sessions SET status='closed', closed_at=NOW(), closed_by=1 WHERE status='open'");
    openCashSession(3, '50');
    [, $body] = slRequest('page=caja');
    slCheck(str_contains($body, 'Caja #2') && str_contains($body, 'Cajas anteriores') && str_contains($body, 'session=1') && str_contains($body, 'Esta caja todavía no tiene movimientos'), 'Con una caja nueva muestra la abierta y enlaza la anterior');
    [, $body] = slRequest('page=caja&session=1');
    slCheck(str_contains($body, 'Caja #1') && str_contains($body, 'Cerrada') && $rows($body) >= 6 && !str_contains($body, 'name="intent" value="reverse"'), 'Se consulta una caja cerrada y no se corrige sobre ella');
    slCheck(slRequest('page=caja&session=abc')[0] === 200 && slRequest('page=caja&session=999')[0] === 200, 'Una caja inexistente cae en la abierta, sin error');

    echo "\n$checks pruebas de caja aprobadas.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if (preg_match('/^bloome_test_cash_[a-f0-9]{10}$/D', $database)) $root->exec("DROP DATABASE IF EXISTS `$database`");
}
