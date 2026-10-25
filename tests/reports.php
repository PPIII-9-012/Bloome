<?php
declare(strict_types=1);
// Pruebas HTTP de informes imprimibles y exportación CSV de ventas y caja (#28). Usa su propia base temporal.
require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT . '/app/employee-migration.php';

$database = 'bloome_test_report_' . bin2hex(random_bytes(5));
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
function slRaw(string $query): array {
    global $webPort, $cookies;
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'header' => 'Cookie: ' . implode('; ', $cookies), 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
    $body = file_get_contents("http://127.0.0.1:$webPort/?$query", false, $ctx);
    $headers = http_get_last_response_headers(); preg_match('/\s(\d{3})\s/', $headers[0], $m);
    return [(int)$m[1], (string)$body, implode("\n", $headers)];
}
function slCsv(string $csv): array {
    return array_map(fn($line) => str_getcsv($line, ';', '"', ''), explode("\r\n", rtrim(substr($csv, 3), "\r\n")));
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
    query("INSERT INTO clients(id,first_name,last_name) VALUES (1,'Ana','Uno'),(2,'Beto','Dos'),(3,'=HYPERLINK(\"x\")','Hack')");
    query("INSERT INTO services(id,name,duration_minutes,price) VALUES (1,'Facial',60,1000)");
    $mk = fn(string $client) => createSale(3, ['client_id' => $client, 'employee_id' => '503', 'lines' => [['service_id' => '1', 'quantity' => '1', 'discount_type' => 'none', 'discount_value' => '']]]);
    openCashSession(3, '500');
    for ($i = 0; $i < 14; $i++) $mk('1');                 // ventas 1..14 de Ana, sin cobrar (más que una página de 12)
    $partial = $mk('2'); $paid = $mk('2'); $hack = $mk('3'); // 15, 16, 17
    recordPayment(3, ['idempotency_key' => 'inf-parcial-0001', 'method' => 'cash', 'amount' => '300', 'sale_id' => (string)$partial]);
    recordPayment(3, ['idempotency_key' => 'inf-pagada-00001', 'method' => 'transfer', 'amount' => '1000', 'sale_id' => (string)$paid]);
    query('UPDATE sales SET created_at=? WHERE id=1', ['2026-01-10 10:00:00']);
    $catId = (string)query("SELECT id FROM expense_categories WHERE name='Insumos'")->fetchColumn();
    recordCashMovement(3, 'expense', 'cash', '150,50', '=1+1 cremas', $catId, 'inf-gasto-000001');
    recordCashMovement(3, 'income', 'cash', '80', 'Propina', '', 'inf-ingreso-00001');
    $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$webPort", '-t', ROOT . '/public', ROOT . '/public/router.php'], [0 => ['pipe', 'r'], 1 => ['file', 'php://temp', 'w'], 2 => ['file', 'php://temp', 'w']], $pipes, ROOT);
    for ($i = 0; $i < 50; $i++) { $socket = @fsockopen('127.0.0.1', $webPort); if ($socket) { fclose($socket); break; } usleep(100000); }
    $rows = fn(string $body) => substr_count($body, '<td>#');

    // --- Acceso
    foreach (['sales-print', 'sales-export', 'caja-print', 'caja-export'] as $p) slCheck(slRequest("page=$p")[0] === 303, "$p exige sesión");
    slLogin('professional@example.test', $password);
    foreach (['sales-print', 'sales-export', 'caja-print', 'caja-export'] as $p) slCheck(slRaw("page=$p")[0] === 403, "Un profesional no accede a $p");
    slLogin('reception@example.test', $password);

    // --- Informe de ventas (pantalla)
    [$status, $body] = slRequest('page=sales-print');
    slCheck($status === 200 && $rows($body) === 17 && str_contains($body, '17 ventas'), 'El informe trae TODAS las ventas (17), no solo la página de 12');
    slCheck(str_contains($body, 'data-print') && str_contains($body, 'Exportar CSV') && str_contains($body, '/assets/print.js') && str_contains($body, 'Emitido el'), 'Ofrece imprimir, exportar y deja constancia de quién y cuándo lo emitió');
    slCheck(str_contains($body, '$ 17.000,00') && str_contains($body, '$ 1.300,00') && str_contains($body, '$ 15.700,00'), 'Muestra vendido 17.000, cobrado 1.300 y pendiente 15.700');
    [, $body] = slRequest('page=sales-print&q=Beto&status=partial');
    slCheck($rows($body) === 1 && str_contains($body, 'Cobro parcial') && str_contains($body, 'Estado: Cobro parcial') && str_contains($body, 'Búsqueda: Beto'), 'El informe respeta búsqueda y estado y los describe en el encabezado');
    [, $body] = slRequest('page=sales-print&from=2026-01-10&to=2026-01-10');
    slCheck($rows($body) === 1 && str_contains($body, '10/01/2026 al 10/01/2026'), 'El informe respeta el período');
    [, $body] = slRequest('page=sales-print&method=transfer');
    slCheck($rows($body) === 1 && str_contains($body, 'Medio de pago: Transferencia'), 'El informe respeta el medio de pago');
    [$status, $body] = slRequest('page=sales-print&q=Zzz');
    slCheck($status === 200 && str_contains($body, 'No hay ventas con estos filtros') && $rows($body) === 0, 'Sin resultados avisa en vez de imprimir una tabla vacía');
    [$status, $body] = slRequest('page=sales-print&from=ayer');
    slCheck($status === 422 && str_contains($body, 'Revisá las fechas'), 'Una fecha inválida no genera el informe');
    [$status, $body] = slRequest('page=sales');
    slCheck(str_contains($body, 'page=sales-print') && str_contains($body, 'page=sales-export'), 'El listado ofrece Imprimir y Exportar CSV');
    [, $body] = slRequest('page=sales&status=unpaid&employee=503');
    slCheck(str_contains($body, 'sales-export&amp;status=unpaid&amp;employee=503') || str_contains($body, 'page=sales-export&amp;status=unpaid'), 'Los botones del listado conservan los filtros');

    // --- Exportación CSV de ventas
    [$status, $csv, $headers] = slRaw('page=sales-export');
    $lines = explode("\r\n", rtrim($csv, "\r\n"));
    slCheck($status === 200 && stripos($headers, 'Content-Type: text/csv') !== false && stripos($headers, 'attachment; filename="ventas-') !== false, 'La exportación es un CSV descargable');
    slCheck(str_starts_with($csv, "\xEF\xBB\xBF" . 'N.º;Fecha;Cliente;Empleado;Total;Cobrado;Saldo;Estado') && count($lines) === 19, 'UTF-8 con BOM, encabezado y 17 ventas + fila de totales');
    $table = slCsv($csv);
    $byId = array_column($table, null, 0);
    slCheck($byId['15'][4] === '1000,00' && $byId['15'][5] === '300,00' && $byId['15'][6] === '700,00' && $byId['15'][7] === 'Cobro parcial' && $byId['15'][3] === 'QA reception', 'Los importes salen con coma decimal y sin símbolo, y trae el empleado');
    slCheck(str_starts_with($byId['17'][2], "'=HYPERLINK") && $byId['17'][2][0] === "'", 'Una celda que empieza con = se neutraliza (no corre como fórmula)');
    slCheck(end($table) === ['', '', '', 'Total (sin anuladas)', '17000,00', '1300,00', '15700,00', ''], 'La última fila trae los totales');
    [, $csv] = slRaw('page=sales-export&status=paid');
    slCheck(count(slCsv($csv)) === 3 && str_contains($csv, 'Cobrada'), 'El CSV respeta los filtros (1 venta cobrada + totales)');
    slCheck(slRaw('page=sales-export&to=ma%C3%B1ana')[0] === 422, 'Una fecha inválida no exporta');
    [$status, $post] = slRequest('page=sales-export', ['csrf' => 'x']);
    slCheck($status !== 200 && !str_contains($post, 'N.º;Fecha'), 'Los informes no aceptan POST');

    // --- Caja: informe y exportación
    [$status, $body] = slRequest('page=caja');
    slCheck(str_contains($body, 'page=caja-print') && str_contains($body, 'page=caja-export'), 'La caja ofrece Imprimir y Exportar CSV');
    [$status, $body] = slRequest('page=caja-print');
    slCheck($status === 200 && str_contains($body, 'Caja #1') && str_contains($body, 'Abierta') && str_contains($body, 'Resumen por medio de pago') && str_contains($body, 'data-print'), 'El informe de caja muestra estado, resumen y botones');
    slCheck(str_contains($body, '$ 150,50') && str_contains($body, 'Cremas') === false && str_contains($body, '=1+1 cremas') && str_contains($body, 'Insumos'), 'Incluye el gasto con su categoría y detalle');
    slCheck(strpos($body, 'Cobro') < strpos($body, 'Gasto') && strpos($body, 'Gasto') < strpos($body, 'Ingreso'), 'Los movimientos van del más antiguo al más reciente');
    slCheck(str_contains($body, '$ 1.229,50'), 'Efectivo esperado 1.229,50 (500 + 300 + 80 − 150,50)');
    [, $body] = slRequest('page=caja-print&kind=expense');
    slCheck(str_contains($body, '1 movimientos') && str_contains($body, 'Tipo: Gasto') && !str_contains($body, 'Propina'), 'El informe respeta el filtro de tipo');
    [$status, $csv, $headers] = slRaw('page=caja-export');
    $lines = explode("\r\n", rtrim($csv, "\r\n"));
    slCheck($status === 200 && stripos($headers, 'attachment; filename="caja-1-') !== false && str_starts_with($csv, "\xEF\xBB\xBF" . 'Fecha;Tipo;Origen;Detalle;Medio;Entrada;Salida;Usuario'), 'La caja exporta un CSV con encabezado');
    $table = slCsv($csv);
    $gasto = array_values(array_filter($table, fn($r) => ($r[1] ?? '') === 'Gasto'))[0];
    $ingreso = array_values(array_filter($table, fn($r) => ($r[1] ?? '') === 'Ingreso'))[0];
    slCheck(array_slice($gasto, 2) === ['Manual · Insumos', "'=1+1 cremas", 'Efectivo', '', '150,50', 'QA reception'] && array_slice($ingreso, 2) === ['Manual', 'Propina', 'Efectivo', '80,00', '', 'QA reception'], 'Entradas y salidas en columnas separadas; la fórmula del detalle queda neutralizada');
    $summaryRows = array_column(array_filter($table, fn($r) => ($r[4] ?? '') !== '' && isset($r[5]) && str_contains($r[5], ',')), null, 4);
    slCheck($summaryRows['Efectivo'][7] === '229,50' && $summaryRows['Transferencia'][5] === '1000,00' && in_array('Resumen por medio de pago', array_column($table, 0), true), 'El CSV cierra con el resumen por medio de pago');
    [, $csv] = slRaw('page=caja-export&method=transfer');
    slCheck(str_contains($csv, 'Venta #' . $paid) && !str_contains($csv, 'Propina'), 'El CSV respeta el filtro por medio de pago');

    // --- Caja cerrada y otras cajas
    $closeOk = closeCashSession(3, (int)currentCashSession()['id'], '1200', 'Faltan 29,50 por vuelto');
    [$status, $body] = slRequest('page=caja-print&session=1');
    slCheck($status === 200 && str_contains($body, 'Cerrada') && str_contains($body, 'Efectivo contado') && str_contains($body, 'Faltan 29,50 por vuelto') && str_contains($body, 'Diferencia'), 'Una caja cerrada muestra arqueo, diferencia y observación');
    [$status, $body] = slRequest('page=caja-print&session=999');
    slCheck($status === 200 && str_contains($body, 'Caja #1'), 'Una caja inexistente cae en la última, sin error');
    [$status, $post] = slRequest('page=caja-export', ['csrf' => 'x']);
    slCheck($status !== 200 && !str_contains($post, 'Fecha;Tipo'), 'Los informes de caja no aceptan POST');
    query('DELETE FROM cash_movements'); query('DELETE FROM payment_allocations'); query('DELETE FROM payments'); query('DELETE FROM cash_sessions');
    [$status, $body] = slRequest('page=caja-print');
    slCheck($status === 404 && str_contains($body, 'Todavía no hay cajas'), 'Sin cajas registradas el informe avisa');

    slLogin('admin@example.test', $password);
    slCheck(slRaw('page=sales-export')[0] === 200, 'Administración también exporta');

    echo "\n$checks pruebas de informes aprobadas.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if (preg_match('/^bloome_test_report_[a-f0-9]{10}$/D', $database)) $root->exec("DROP DATABASE IF EXISTS `$database`");
}
