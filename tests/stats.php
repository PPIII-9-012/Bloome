<?php
declare(strict_types=1);
// Pruebas de estadísticas (#27): cálculo contra MySQL, conciliación con el listado de ventas, permisos y páginas HTTP. Base temporal propia.
require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT . '/app/employee-migration.php';
require ROOT . '/app/client-migration.php';
require ROOT . '/app/stats.php';

$database = 'bloome_test_stats_' . bin2hex(random_bytes(5));
$host = getenv('DB_HOST') ?: '127.0.0.1'; $port = getenv('DB_PORT') ?: '3306'; $rootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: '';
$root = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", 'root', $rootPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0; $server = null; $webPort = random_int(40001, 42000); $cookies = [];
function stCheck(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException('FALLÓ: ' . $label); $checks++; echo "OK: $label\n"; }
function stRequest(string $query, ?array $data = null): array {
    global $webPort, $cookies;
    $ctx = stream_context_create(['http' => ['method' => $data === null ? 'GET' : 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: " . implode('; ', $cookies), 'content' => $data === null ? '' : http_build_query($data), 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
    $body = file_get_contents("http://127.0.0.1:$webPort/?$query", false, $ctx);
    $headers = http_get_last_response_headers(); preg_match('/\s(\d{3})\s/', $headers[0], $m);
    foreach ($headers as $header) if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $cookie)) $cookies[$cookie[1]] = $cookie[1] . '=' . $cookie[2];
    return [(int)$m[1], (string)$body];
}
function stToken(string $body): string { preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $m); return $m[1] ?? ''; }
function stLogin(string $email, string $password): void {
    global $cookies; $cookies = [];
    [, $body] = stRequest('page=login'); stRequest('page=login', ['csrf' => stToken($body), 'email' => $email, 'password' => $password]);
}
try {
    $root->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    putenv('DB_DATABASE=' . $database); putenv('DB_USERNAME=root'); putenv('DB_PASSWORD=' . $rootPassword);
    foreach (explode(';', file_get_contents(ROOT . '/database/schema.sql')) as $sql) if (trim($sql) !== '') db()->exec($sql);
    $password = bin2hex(random_bytes(16)); $hash = password_hash($password, PASSWORD_DEFAULT);
    foreach ([[1, 'admin'], [2, 'professional'], [3, 'reception']] as [$uid, $role]) query('INSERT INTO users(id,name,email,password_hash,role) VALUES (?,?,?,?,?)', [$uid, 'QA ' . $role, $role . '@example.test', $hash, $role]);
    query('INSERT INTO employees(id,user_id) VALUES (501,1),(502,2),(503,3)'); migrateEmployees();
    query("INSERT INTO service_categories(id,name) VALUES (1,'Faciales'),(2,'Corporales <b>')");
    query("INSERT INTO services(id,name,category_id,duration_minutes,price) VALUES (1,'Facial',1,60,1000),(2,'Masaje',2,60,2000)");
    query("INSERT INTO clients(id,first_name,last_name,gender) VALUES (1,'Ana','Uno','female'),(2,'Beto','Dos','male'),(3,'Caro','Tres',NULL)");
    $mk = fn(string $client, array $services) => createSale(3, ['client_id' => $client, 'employee_id' => '503', 'lines' => array_map(fn($s) => ['service_id' => (string)$s, 'quantity' => '1', 'discount_type' => 'none', 'discount_value' => ''], $services)]);
    openCashSession(3, '500');
    $s1 = $mk('1', [1, 2]); $s2 = $mk('2', [2]); $s3 = $mk('3', [1]); $s4 = $mk('1', [1]); $s5 = $mk('1', [1]);
    recordPayment(3, ['idempotency_key' => 'est-s1-0000001', 'method' => 'cash', 'amount' => '1000', 'sale_id' => (string)$s1]);
    recordPayment(3, ['idempotency_key' => 'est-s2-0000001', 'method' => 'transfer', 'amount' => '2000', 'sale_id' => (string)$s2]);
    voidSale(1, $s4, 'Venta cargada por error');
    query('UPDATE sales SET created_at=? WHERE id=?', ['2026-01-05 10:00:00', $s5]);
    $today = date('Y-m-d'); [$monthStart] = statsDefaultRange();
    $cat = fn(string $name) => (string)query('SELECT id FROM expense_categories WHERE name=?', [$name])->fetchColumn();
    recordCashMovement(3, 'expense', 'cash', '150,50', 'Cremas', $cat('Insumos'), 'est-gasto-00001');
    recordCashMovement(3, 'expense', 'cash', '100', 'Luz', $cat('Servicios'), 'est-gasto-00002');
    $c = recordCashMovement(3, 'expense', 'cash', '40', 'Sin rubro', '', 'est-gasto-00003');
    reverseCashMovement(1, $c, 'Se cargó dos veces');
    recordCashMovement(3, 'withdrawal', 'cash', '50', 'Retiro', '', 'est-retiro-0001');
    query("INSERT INTO appointments(client_id,employee_id,starts_at,ends_at,status) VALUES (1,503,'$today 08:00','$today 09:00','completed'),(2,503,'$today 09:00','$today 10:30','completed'),(3,503,'$today 11:00','$today 12:00','cancelled'),(1,501,'$today 12:00','$today 12:30','completed'),(1,501,'2026-01-05 12:00','2026-01-05 13:00','completed')");

    // --- Reparto proporcional
    stCheck(statsProrate(100000, [1 => 100000, 2 => 200000]) === [1 => 33333, 2 => 66667], 'El cobrado se reparte por importe y suma exactamente');
    foreach ([[1, [5 => 1, 6 => 1, 7 => 1]], [10, [1 => 3, 2 => 3, 3 => 3]], [0, [1 => 5]], [7, [1 => 0, 2 => 0]], [99999, [1 => 12345, 2 => 67890, 3 => 11111, 4 => 1]]] as [$total, $weights]) {
        $parts = statsProrate($total, $weights);
        stCheck(array_sum($parts) === ($total > 0 && array_sum($weights) > 0 ? $total : 0) && min($parts) >= 0, "Reparto de $total entre " . count($weights) . ' partes suma exacto y sin negativos');
    }

    // --- Ventas
    [$f] = statsFilterInput([]);
    stCheck($f['from'] === $monthStart && $f['to'] === $today, 'Sin fechas usa el mes en curso');
    $g = statsSales('general', $f);
    stCheck($g['totals'] === ['sold' => 600000, 'collected' => 300000, 'pending' => 300000] && $g['sales'] === 3, 'General: vendido 6.000, cobrado 3.000, pendiente 3.000 (sin anuladas ni otros períodos)');
    [, , $list] = searchSales(['from' => $f['from'], 'to' => $f['to']], 1);
    stCheck($list['sold'] === $g['totals']['sold'] && $list['collected'] === $g['totals']['collected'] && $list['pending'] === $g['totals']['pending'], 'Las cifras concilian con los totales del listado de Ventas');
    $c = statsSales('categoria', $f);
    $byLabel = fn(array $rows) => array_column($rows, null, 'label');
    $cr = $byLabel($c['rows']);
    stCheck($cr['Faciales']['sold'] === 200000 && $cr['Corporales <b>']['sold'] === 400000 && $cr['Faciales']['collected'] === 33333 && $cr['Corporales <b>']['collected'] === 266667, 'Por categoría: vendido y cobrado proporcional por ítem');
    stCheck(array_sum(array_column($c['rows'], 'sold')) === $g['totals']['sold'] && array_sum(array_column($c['rows'], 'collected')) === $g['totals']['collected'], 'Las filas por categoría suman el total general');
    stCheck($c['rows'][0]['label'] === 'Corporales <b>', 'Se ordena por vendido descendente');
    $sv = statsSales('servicio', $f); $sr = $byLabel($sv['rows']);
    stCheck($sr['Facial']['sold'] === 200000 && $sr['Masaje']['sold'] === 400000 && $sr['Facial']['sales'] === 2 && $sr['Masaje']['sales'] === 2, 'Por servicio: importe y cantidad de ventas');
    $ge = statsSales('genero', $f); $gr = $byLabel($ge['rows']);
    stCheck($gr['Femenino']['sold'] === 300000 && $gr['Masculino']['sold'] === 200000 && $gr['Sin dato']['sold'] === 100000, 'Por género: clientes sin género van a "Sin dato"');
    [$fg] = statsFilterInput(['gender' => 'none']);
    stCheck(statsSales('general', $fg)['totals']['sold'] === 100000, 'Filtro de género "Sin dato"');
    [$fc] = statsFilterInput(['category' => '1']);
    stCheck(statsSales('general', $fc)['totals']['sold'] === 200000, 'Filtro por categoría');
    [$fs] = statsFilterInput(['service' => '2', 'gender' => 'male']);
    stCheck(statsSales('general', $fs)['totals'] === ['sold' => 200000, 'collected' => 200000, 'pending' => 0], 'Filtros combinados (servicio y género)');
    [$fo] = statsFilterInput(['from' => '2026-01-01', 'to' => '2026-01-31']);
    stCheck(statsSales('general', $fo)['totals']['sold'] === 100000 && statsSales('general', $fo)['rows'][0]['label'] === '2026-01-05', 'Otro período (hasta 31 días): solo la venta de enero, agrupada por día');
    [$fq] = statsFilterInput(['from' => '2026-01-01', 'to' => '2026-03-31']);
    stCheck(statsSales('general', $fq)['rows'][0]['label'] === '2026-01' && statsSales('general', $fq)['bucket'] === 'month', 'Períodos largos se agrupan por mes');
    [$fe] = statsFilterInput(['from' => '2030-01-01', 'to' => '2030-01-31']);
    stCheck(statsSales('general', $fe)['rows'] === [] && statsSales('general', $fe)['totals']['sold'] === 0, 'Período sin ventas devuelve vacío');
    [$bad, $err] = statsFilterInput(['from' => '2026-02-31', 'to' => 'x', 'category' => '1; DROP', 'gender' => 'zzz']);
    stCheck($err !== '' && $bad['category'] === '' && $bad['gender'] === '' && $bad['from'] === $monthStart, 'Filtros inválidos se descartan con aviso');
    [, $err] = statsFilterInput(['from' => '2026-05-10', 'to' => '2026-05-01']);
    stCheck($err !== '', 'Desde posterior a hasta es un error');

    // --- Gastos
    $e = statsExpenses('general', $f);
    stCheck($e['total'] === 25050 && $e['count'] === 2, 'Gastos: 250,50 y 2 movimientos, netos de la corrección y sin retiros');
    $ec = $byLabel(statsExpenses('categoria', $f)['rows']);
    stCheck($ec['Insumos']['amount'] === 15050 && $ec['Servicios']['amount'] === 10000 && !isset($ec['Sin categoría']), 'Por categoría: la categoría corregida a cero no aparece');
    [$fx] = statsFilterInput(['category' => $cat('Insumos')]);
    stCheck(statsExpenses('general', $fx)['total'] === 15050, 'Filtro de gastos por categoría');
    [$fn] = statsFilterInput(['category' => 'none']);
    stCheck(statsExpenses('general', $fn)['total'] === 0, 'Filtro "sin categoría" neto de corrección');

    // --- Horas
    $h = statsHours($f);
    stCheck($h['minutes'] === 180 && $h['count'] === 3 && $h['rows'][0]['label'] === 'QA reception' && $h['rows'][0]['minutes'] === 150, 'Horas: solo citas completadas del período (150 + 30 min)');
    [$fh] = statsFilterInput(['employee' => '501']);
    stCheck(statsHours($fh)['minutes'] === 30, 'Filtro por empleado');
    stCheck(statsDuration(150) === '2 h 30 min' && statsDuration(60) === '1 h' && statsDuration(45) === '45 min', 'Formato de duración');

    // --- Migración idempotente
    db()->exec("UPDATE clients SET gender=NULL"); db()->exec('ALTER TABLE clients DROP COLUMN gender'); migrateClientGender(); migrateClientGender();
    stCheck((bool)query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clients' AND COLUMN_NAME='gender'")->fetchColumn(), 'La migración de género es repetible');
    query("UPDATE clients SET gender='female' WHERE id=1"); query("UPDATE clients SET gender='male' WHERE id=2");

    // --- HTTP
    $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$webPort", '-t', ROOT . '/public', ROOT . '/public/router.php'], [0 => ['pipe', 'r'], 1 => ['file', 'php://temp', 'w'], 2 => ['file', 'php://temp', 'w']], $pipes, ROOT);
    for ($i = 0; $i < 50; $i++) { $socket = @fsockopen('127.0.0.1', $webPort); if ($socket) { fclose($socket); break; } usleep(100000); }
    $pages = ['estadisticas', 'estadisticas-ventas&v=general', 'estadisticas-ventas&v=categoria', 'estadisticas-ventas&v=servicio', 'estadisticas-ventas&v=genero', 'estadisticas-gastos&v=general', 'estadisticas-gastos&v=categoria', 'estadisticas-horas'];
    foreach ($pages as $p) stCheck(stRequest("page=$p")[0] === 303, "$p exige sesión");
    stLogin('professional@example.test', $password);
    foreach ($pages as $p) stCheck(stRequest("page=$p")[0] === 403, "Un profesional no accede a $p");
    stLogin('reception@example.test', $password);
    [$status, $body] = stRequest('page=estadisticas');
    stCheck($status === 200 && substr_count($body, 'class="stats-link"') === 7 && str_contains($body, 'Horas totales empleadas') && !str_contains($body, 'Próx.'), 'El menú ofrece los 7 informes y la barra lateral ya no marca Estadísticas como próxima');
    foreach (array_slice($pages, 1) as $p) { [$status, $body] = stRequest("page=$p"); stCheck($status === 200 && str_contains($body, '<table') && str_contains($body, '<svg role="img"'), "$p muestra tabla y gráfico"); }
    [, $body] = stRequest('page=estadisticas-ventas&v=categoria');
    stCheck(str_contains($body, 'Corporales &lt;b&gt;') && !str_contains($body, 'Corporales <b>') && str_contains($body, '$ 6.000,00') && str_contains($body, '$ 3.000,00'), 'Escapa HTML almacenado y muestra vendido y cobrado');
    [, $body] = stRequest('page=estadisticas-ventas&v=genero');
    stCheck(str_contains($body, 'Sin dato') && str_contains($body, 'Femenino') && str_contains($body, 'Masculino'), 'Por género muestra Sin dato');
    [, $body] = stRequest('page=estadisticas-gastos&v=general');
    stCheck(str_contains($body, '$ 250,50'), 'Gastos: muestra 250,50');
    [, $body] = stRequest('page=estadisticas-horas');
    stCheck(str_contains($body, '2 h 30 min') && str_contains($body, '3 h'), 'Horas: muestra 2 h 30 min y el total de 3 h');
    [, $body] = stRequest('page=estadisticas-ventas&v=general&from=2030-01-01&to=2030-01-31');
    stCheck(str_contains($body, 'No hay ventas con esos filtros'), 'Estado vacío');
    [$status, $body] = stRequest('page=estadisticas-ventas&v=general&from=2026-02-31');
    stCheck($status === 422 && str_contains($body, 'Revisá las fechas'), 'Fecha inválida: 422 con aviso');
    stCheck(stRequest('page=estadisticas-ventas&v=inexistente')[0] === 404 && stRequest('page=estadisticas-gastos&v=genero')[0] === 404, 'Informe inexistente: 404');
    [, $body] = stRequest('page=estadisticas'); $csrf = stToken($body);
    stCheck(stRequest('page=estadisticas', ['csrf' => $csrf])[0] === 405, 'POST no permitido (solo lectura)');

    // --- Género en el alta y la edición de clientes
    [, $body] = stRequest('page=client-new');
    stCheck(str_contains($body, 'name="gender"') && str_contains($body, 'Sin dato'), 'El formulario de cliente ofrece género opcional');
    $csrf = stToken($body); $new = ['csrf' => $csrf, 'first_name' => 'Dora', 'last_name' => 'Cuatro', 'email' => '', 'phone' => '', 'birth_date' => '', 'notes' => ''];
    stCheck(stRequest('page=client-new', $new + ['gender' => 'robot'])[0] === 422 && (int)query("SELECT COUNT(*) FROM clients WHERE last_name='Cuatro'")->fetchColumn() === 0, 'Género inválido rechazado sin crear el cliente');
    stCheck(stRequest('page=client-new', $new + ['gender' => 'other'])[0] === 303, 'Alta con género');
    $dora = (int)query("SELECT id FROM clients WHERE last_name='Cuatro'")->fetchColumn();
    stCheck(query('SELECT gender FROM clients WHERE id=?', [$dora])->fetchColumn() === 'other', 'Género guardado en MySQL');
    [, $body] = stRequest("page=client&id=$dora"); stCheck(str_contains($body, 'Género') && str_contains($body, 'Otro'), 'La ficha muestra el género');
    [, $body] = stRequest("page=client-edit&id=$dora"); stCheck(str_contains($body, 'value="other" selected'), 'La edición preselecciona el género');
    stCheck(stRequest("page=client-edit&id=$dora", ['csrf' => stToken($body), 'first_name' => 'Dora', 'last_name' => 'Cuatro', 'email' => '', 'phone' => '', 'birth_date' => '', 'gender' => '', 'notes' => ''])[0] === 303 && query('SELECT gender FROM clients WHERE id=?', [$dora])->fetchColumn() === null, 'Se puede volver a "Sin dato"');
    echo "$checks pruebas de estadísticas aprobadas.\n";
} finally {
    if (is_resource($server)) proc_terminate($server);
    $root->exec("DROP DATABASE IF EXISTS `$database`");
}
