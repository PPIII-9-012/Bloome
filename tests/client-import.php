<?php
declare(strict_types=1);
// Pruebas de importación y exportación de clientes en CSV (#22). Base temporal propia.
require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT . '/app/employee-migration.php';
require ROOT . '/app/client-migration.php';
require ROOT . '/app/client-import.php';

$database = 'bloome_test_clientcsv_' . bin2hex(random_bytes(5));
$host = getenv('DB_HOST') ?: '127.0.0.1'; $port = getenv('DB_PORT') ?: '3306'; $rootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: '';
$root = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", 'root', $rootPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0; $server = null; $webPort = random_int(44001, 46000); $cookies = [];
function ciCheck(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException('FALLÓ: ' . $label); $checks++; echo "OK: $label\n"; }
function ciRequest(string $query, ?array $data = null, ?array $file = null): array {
    global $webPort, $cookies;
    $headers = "Cookie: " . implode('; ', $cookies) . "\r\n";
    if ($file !== null) {
        $boundary = 'b' . bin2hex(random_bytes(8)); $body = '';
        foreach (($data ?? []) as $k => $v) $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";
        $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"file\"; filename=\"{$file['name']}\"\r\nContent-Type: text/csv\r\n\r\n{$file['content']}\r\n--$boundary--\r\n";
        $headers .= "Content-Type: multipart/form-data; boundary=$boundary\r\n"; $content = $body; $method = 'POST';
    } else {
        $headers .= "Content-Type: application/x-www-form-urlencoded\r\n"; $content = $data === null ? '' : http_build_query($data); $method = $data === null ? 'GET' : 'POST';
    }
    $ctx = stream_context_create(['http' => ['method' => $method, 'header' => $headers, 'content' => $content, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 20]]);
    $body = file_get_contents("http://127.0.0.1:$webPort/?$query", false, $ctx);
    $h = http_get_last_response_headers(); preg_match('/\s(\d{3})\s/', $h[0], $m);
    $type = '';
    foreach ($h as $header) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $c)) $cookies[$c[1]] = $c[1] . '=' . $c[2];
        if (stripos($header, 'Content-Type:') === 0) $type = trim(substr($header, 13));
    }
    return [(int)$m[1], (string)$body, $type];
}
function ciToken(string $body): string { preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $m); return $m[1] ?? ''; }
function ciLogin(string $email, string $password): void {
    global $cookies; $cookies = [];
    [, $body] = ciRequest('page=login'); ciRequest('page=login', ['csrf' => ciToken($body), 'email' => $email, 'password' => $password]);
}
function ciWrite(string $content): string { $p = tempnam(sys_get_temp_dir(), 'cli'); file_put_contents($p, $content); return $p; }
$H = "nombre;apellido;email;telefono;fecha_nacimiento;genero;observaciones\r\n";
try {
    $root->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    putenv('DB_DATABASE=' . $database); putenv('DB_USERNAME=root'); putenv('DB_PASSWORD=' . $rootPassword);
    foreach (explode(';', file_get_contents(ROOT . '/database/schema.sql')) as $sql) if (trim($sql) !== '') db()->exec($sql);
    $password = bin2hex(random_bytes(16)); $hash = password_hash($password, PASSWORD_DEFAULT);
    foreach ([[1, 'admin'], [2, 'professional'], [3, 'reception']] as [$uid, $role]) query('INSERT INTO users(id,name,email,password_hash,role) VALUES (?,?,?,?,?)', [$uid, 'QA ' . $role, $role . '@example.test', $hash, $role]);
    query('INSERT INTO employees(id,user_id) VALUES (501,1),(502,2),(503,3)'); migrateEmployees();
    query("INSERT INTO clients(first_name,last_name,email,phone,birth_date) VALUES ('Existente','Uno','existe@example.test','+54 11 5555-1111','1990-05-05'),('=cmd','Peligro',NULL,NULL,NULL)");

    // --- Lectura y validación del archivo
    $p = ciWrite($H . "Ana;Lopez;ana@example.test;1155550001;1991-02-03;Femenino;Nota\r\n"); $r = parseClientCsv($p);
    ciCheck($r['fatal'] === null && count($r['rows']) === 1 && !$r['rows'][0]['errors'] && $r['rows'][0]['data']['gender'] === 'female', 'Una fila correcta se lee y normaliza el género');
    $r = parseClientCsv(ciWrite("\xEF\xBB\xBF" . $H . "Ana;Lopez;;;03/02/1991;otro;\r\n"));
    ciCheck($r['fatal'] === null && $r['rows'][0]['data']['birth_date'] === '1991-02-03' && $r['rows'][0]['data']['gender'] === 'other', 'Acepta BOM, fecha DD/MM/AAAA y género "otro"');
    $r = parseClientCsv(ciWrite(str_replace(';', ',', $H) . "Ana,Lopez,,,,,\r\n"));
    ciCheck($r['fatal'] === null && count($r['rows']) === 1, 'Acepta coma como separador');
    ciCheck(parseClientCsv(ciWrite("nombre;apellido;mail\r\nA;B;c\r\n"))['fatal'] !== null, 'Encabezados distintos a la plantilla se rechazan');
    ciCheck(parseClientCsv(ciWrite($H))['fatal'] !== null, 'Un archivo sin filas se rechaza');
    ciCheck(parseClientCsv(ciWrite(''))['fatal'] !== null, 'Un archivo vacío se rechaza');
    ciCheck(str_contains((string)parseClientCsv(ciWrite($H . "Mar\xEDa;L\xF3pez;;;;;\r\n"))['fatal'], 'UTF-8'), 'Un archivo que no es UTF-8 se rechaza con un mensaje claro');
    ciCheck(parseClientCsv(ciWrite($H . str_repeat("A;B;;;;;\r\n", CLIENT_CSV_MAX_ROWS + 1)))['fatal'] !== null, 'Más de 1.000 filas se rechaza');
    ciCheck(parseClientCsv(ciWrite($H . str_repeat('x', CLIENT_CSV_MAX_BYTES)))['fatal'] !== null, 'Más de 1 MB se rechaza');
    $r = parseClientCsv(ciWrite($H . "Sin;Mail;mal;123;1800-01-01;robot;\r\n;SinNombre;;;;;\r\nFila;Corta\r\n\r\nOk;Fila;;;;;\r\n"));
    ciCheck(count($r['rows']) === 4 && count($r['rows'][0]['errors']) >= 4 && isset($r['rows'][1]['errors']['first_name']) && isset($r['rows'][2]['errors']['row']) && !$r['rows'][3]['errors'] && $r['rows'][3]['line'] === 6, 'Cada fila inválida se identifica con su número de línea y sus errores; las líneas vacías se ignoran');

    // --- Importación: persistencia, duplicados y filas con errores
    $csv = $H . "Nueva;Persona;nueva@example.test;1155550002;1992-03-04;Masculino;<b>x</b>\r\n"
        . "Repite;Correo;EXISTE@example.test;;;;\r\n"
        . "Repite;Telefono;;(011) 5555 1111;;;\r\n"
        . "Existente;Uno;;;1990-05-05;;\r\n"
        . "Doble;Archivo;;1155550002;;;\r\n"
        . "Mala;Fila;correo-malo;;;;\r\n"
        . "Otra;Valida;otra@example.test;;;;\r\n";
    $parsed = parseClientCsv(ciWrite($csv)); $res = importClients(1, $parsed['rows']);
    ciCheck($res['created'] === 2 && count($res['duplicates']) === 4 && count($res['invalid']) === 1, 'Importa las válidas, omite 4 duplicados y reporta 1 inválida');
    ciCheck(array_column($res['duplicates'], 'line') === [3, 4, 5, 6] && $res['invalid'][0]['line'] === 7, 'Los duplicados y errores indican su fila del archivo');
    ciCheck((int)query('SELECT COUNT(*) FROM clients')->fetchColumn() === 4, 'Solo se persistieron las 2 filas nuevas');
    $row = query("SELECT * FROM clients WHERE email='nueva@example.test'")->fetch();
    ciCheck($row && $row['gender'] === 'male' && $row['birth_date'] === '1992-03-04' && $row['notes'] === '<b>x</b>', 'Los datos importados se guardan completos (sin interpretar HTML)');
    ciCheck((int)query("SELECT COUNT(*) FROM clients WHERE email='existe@example.test'")->fetchColumn() === 1 && query("SELECT notes FROM clients WHERE email='existe@example.test'")->fetchColumn() === null, 'Un duplicado no modifica ni fusiona al cliente existente');
    ciCheck((int)query("SELECT COUNT(*) FROM audit_logs WHERE action='clients.import' AND outcome='success'")->fetchColumn() === 1, 'La importación queda auditada');
    $again = importClients(1, parseClientCsv(ciWrite($csv))['rows']);
    ciCheck($again['created'] === 0 && (int)query('SELECT COUNT(*) FROM clients')->fetchColumn() === 4, 'Importar el mismo archivo otra vez no duplica nada');

    // --- Exportación segura y reimportable
    $out = clientsCsv();
    ciCheck(str_starts_with($out, "\xEF\xBB\xBFnombre;apellido;email") && str_contains($out, "'=cmd") && !preg_match('/(^|;)=cmd/m', $out), 'La exportación neutraliza fórmulas y trae encabezados de la plantilla');
    ciCheck(str_contains($out, "'+54 11 5555-1111"), 'Los teléfonos que empiezan con + se protegen en la planilla');
    $re = parseClientCsv(ciWrite($out));
    ciCheck($re['fatal'] === null && count($re['rows']) === 4 && !array_filter(array_column($re['rows'], 'errors')) && importClients(1, $re['rows'])['created'] === 0, 'Un archivo exportado vuelve a importarse sin errores ni duplicados nuevos');
    ciCheck(str_starts_with(clientsCsvTemplate(), "\xEF\xBB\xBFnombre;apellido;email;telefono;fecha_nacimiento;genero;observaciones"), 'La plantilla trae solo los encabezados fijos');

    // --- HTTP: permisos, subida y resultados
    $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$webPort", '-t', ROOT . '/public', ROOT . '/public/router.php'], [0 => ['pipe', 'r'], 1 => ['file', 'php://temp', 'w'], 2 => ['file', 'php://temp', 'w']], $pipes, ROOT);
    for ($i = 0; $i < 50; $i++) { $socket = @fsockopen('127.0.0.1', $webPort); if ($socket) { fclose($socket); break; } usleep(100000); }
    foreach (['clients-export', 'clients-template', 'clients-import'] as $pg) ciCheck(ciRequest("page=$pg")[0] === 303, "$pg exige sesión");
    ciLogin('professional@example.test', $password);
    foreach (['clients-export', 'clients-template', 'clients-import'] as $pg) ciCheck(ciRequest("page=$pg")[0] === 403, "Un profesional no accede a $pg");
    [, $body] = ciRequest('page=clients'); ciCheck(!str_contains($body, 'clients-import') && !str_contains($body, 'clients-export'), 'El profesional no ve los botones de importar ni exportar');
    ciCheck((int)query("SELECT COUNT(*) FROM audit_logs WHERE action IN ('clients.export','clients.import') AND outcome='denied'")->fetchColumn() >= 3, 'Los accesos denegados quedan auditados');
    ciLogin('reception@example.test', $password);
    [, $body] = ciRequest('page=clients'); ciCheck(str_contains($body, 'page=clients-import') && str_contains($body, 'page=clients-export') && !str_contains($body, 'onclick='), 'Recepción ve los botones reales de importar y exportar');
    [$status, $body, $type] = ciRequest('page=clients-export'); ciCheck($status === 200 && str_contains($type, 'text/csv') && str_contains($body, 'Persona'), 'Recepción descarga el CSV de clientes');
    [$status, $body] = ciRequest('page=clients-template'); ciCheck($status === 200 && str_contains($body, 'fecha_nacimiento'), 'Recepción descarga la plantilla');
    [$status, $body] = ciRequest('page=clients-import'); $csrf = ciToken($body);
    ciCheck($status === 200 && str_contains($body, 'enctype="multipart/form-data"'), 'El formulario de importación se muestra');
    ciCheck(ciRequest('page=clients-import', [], ['name' => 'c.csv', 'content' => $H . "A;B;;;;;\r\n"])[0] === 419, 'La subida exige CSRF');
    [$status, $body] = ciRequest('page=clients-import', ['csrf' => $csrf], ['name' => 'c.csv', 'content' => $H . "Http;Subida;http@example.test;;;;\r\nMala;Fila;xx;;;;\r\nExistente;Uno;;;1990-05-05;;\r\n"]);
    ciCheck($status === 200 && str_contains($body, '1</strong> cliente importado') && str_contains($body, 'Filas con errores') && str_contains($body, 'Filas omitidas por repetidas') && (int)query("SELECT COUNT(*) FROM clients WHERE email='http@example.test'")->fetchColumn() === 1, 'La subida importa, y el resultado detalla errores y repetidos');
    [$status, $body] = ciRequest('page=clients-import', ['csrf' => $csrf], ['name' => 'c.csv', 'content' => "a;b\r\n1;2\r\n"]);
    ciCheck($status === 422 && str_contains($body, 'encabezados') && str_contains($body, 'No se importó ningún cliente'), 'Un archivo con otros encabezados da error claro y no importa nada');
    [$status, $body] = ciRequest('page=clients-import', ['csrf' => $csrf], ['name' => 'virus.exe', 'content' => $H . "A;B;;;;;\r\n"]);
    ciCheck($status === 422 && str_contains($body, '.csv'), 'Una extensión que no es .csv se rechaza');
    [$status] = ciRequest('page=clients-import', ['csrf' => $csrf]); ciCheck($status === 422, 'Enviar sin archivo da error');
    ciCheck((int)query('SELECT COUNT(*) FROM clients')->fetchColumn() === 5, 'Los intentos fallidos no dejaron clientes de más');
    echo "$checks pruebas de importación y exportación aprobadas.\n";
} finally {
    if (is_resource($server)) proc_terminate($server);
    $root->exec("DROP DATABASE IF EXISTS `$database`");
}
