<?php
declare(strict_types=1);
// Run only against the local development instance configured by .env.
require dirname(__DIR__) . '/app/bootstrap.php';
$origin = 'http://127.0.0.1:8000';
$cookies = [];
$checks = 0;
$testEmail = 'qa-' . bin2hex(random_bytes(5)) . '@example.test';
$professionalEmail = 'qa-reader-' . bin2hex(random_bytes(5)) . '@example.test';
$professionalId = null;
$createdId = null;
function request(string $page, array $fields = [], bool $post = false): array {
    global $origin, $cookies;
    $headers = ['Content-Type: application/x-www-form-urlencoded'];
    if ($cookies) $headers[] = 'Cookie: ' . implode('; ', array_map(fn($key, $value) => "$key=$value", array_keys($cookies), $cookies));
    $context = stream_context_create(['http'=>['method'=>$post?'POST':'GET','header'=>implode("\r\n",$headers),'content'=>$post?http_build_query($fields):'','ignore_errors'=>true,'follow_location'=>0,'timeout'=>10]]);
    $body = file_get_contents($origin . '/?page=' . $page, false, $context);
    $responseHeaders = http_get_last_response_headers();
    preg_match('/\s(\d{3})\s/', $responseHeaders[0], $status);
    foreach ($responseHeaders as $header) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $match)) $cookies[$match[1]]=$match[2];
    }
    return [(int)$status[1], $body];
}
function token(string $body): string { preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $match); return $match[1] ?? ''; }
function expect(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException($label); echo "OK: $label\n"; $checks++; }
try {
    [$status] = request('clients');
    expect($status === 303, 'Listado protegido sin sesión');
    [$status,$body] = request('login');
    $csrf = token($body);
    expect($status === 200 && strlen($csrf) === 64, 'Login genera CSRF');
    [$status] = request('login',['email'=>getenv('ADMIN_EMAIL'),'password'=>getenv('ADMIN_PASSWORD')],true);
    expect($status === 419, 'POST sin CSRF rechazado');
    [$status] = request('login',['email'=>getenv('ADMIN_EMAIL'),'password'=>getenv('ADMIN_PASSWORD'),'csrf'=>$csrf],true);
    expect($status === 303, 'Login real contra MySQL');
    [$status,$body] = request('dashboard');
    expect($status === 200 && str_contains($body,'Clientes registrados'), 'Panel autenticado consulta MySQL');
    [$status,$body] = request('client-new');
    $csrf = token($body);
    [$status,$body] = request('client-new',['csrf'=>$csrf,'first_name'=>'','last_name'=>'','email'=>'invalid'],true);
    expect($status === 422 && str_contains($body,'aria-invalid'), 'Servidor rechaza cliente inválido');
    $fields = ['csrf'=>$csrf,'first_name'=>'QA <script>','last_name'=>'Prueba','email'=>$testEmail,'phone'=>'+54 11 1234 5678','birth_date'=>'1992-02-29','notes'=>'Prueba HTTP'];
    [$status] = request('client-new',$fields,true);
    expect($status === 303, 'Alta válida redirige después de guardar');
    $createdId = query('SELECT id FROM clients WHERE email=?',[$testEmail])->fetchColumn();
    expect((bool)$createdId,'Alta persistida en MySQL');
    [$status,$body] = request('clients&q=' . urlencode($testEmail));
    expect($status === 200 && str_contains($body,'QA &lt;script&gt;') && !str_contains($body,'QA <script>'), 'Búsqueda y escape de contenido guardado');
    $fields['first_name']='QA actualizada';
    [$status] = request('client-edit&id=' . $createdId,$fields,true);
    expect($status === 303 && query('SELECT first_name FROM clients WHERE id=?',[$createdId])->fetchColumn()==='QA actualizada','Edición persistida');
    [$status] = request('client-edit&id=999999999');
    expect($status === 404,'Cliente inexistente retorna 404');
    [$status,$body] = request('agenda&date=2026-02-30');
    expect($status === 200 && !str_contains($body,'value="2026-02-30"'),'Agenda normaliza filtro de fecha inválida');
    [$status] = request('logout',['csrf'=>$csrf],true);
    expect($status === 303,'Logout invalida sesión');
    [$status] = request('dashboard');
    expect($status === 303,'Panel bloqueado después de logout');
    $readerPassword=bin2hex(random_bytes(12));
    query('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)',['QA profesional',$professionalEmail,password_hash($readerPassword,PASSWORD_DEFAULT),'professional']);
    $professionalId=db()->lastInsertId();
    [, $body] = request('login');
    [$status] = request('login',['email'=>$professionalEmail,'password'=>$readerPassword,'csrf'=>token($body)],true);
    expect($status===303,'Acceso de profesional');
    [$status,$body] = request('clients');
    expect($status===200 && !str_contains($body,'client-new'),'Profesional ve clientes sin acción de alta');
    $csrf=token($body);
    [$status] = request('client-new');
    expect($status===403,'Permiso comprobado en servidor para GET');
    [$status] = request('client-edit&id='.$createdId,['csrf'=>$csrf,'first_name'=>'Intrusión','last_name'=>'Prueba'],true);
    expect($status===403 && query('SELECT first_name FROM clients WHERE id=?',[$createdId])->fetchColumn()==='QA actualizada','Permiso comprobado para POST sin alterar datos');
    [, $body] = request('clients');
    request('logout',['csrf'=>token($body)],true);
    echo "\n$checks pruebas HTTP/MySQL aprobadas.\n";
} catch (Throwable $e) {
    fwrite(STDERR,'FAIL: '.$e->getMessage()."\n");
    $failed=true;
} finally {
    if ($createdId) query('DELETE FROM clients WHERE id=? AND email=?',[$createdId,$testEmail]);
    if ($professionalId) query('DELETE FROM users WHERE id=? AND email=?',[$professionalId,$professionalEmail]);
    query('DELETE FROM login_attempts WHERE identity_hash=?',[hash('sha256',$professionalEmail)]);
}
exit(isset($failed)?1:0);
