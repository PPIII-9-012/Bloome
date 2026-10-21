<?php
declare(strict_types=1);
// Creates and destroys its own isolated database; never changes the configured application database.
require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT . '/app/employee-migration.php';
if (isset($argv[1]) && $argv[1] === '--worker') {
    $id = (int)$argv[2];
    $record = employeeRecord($id);
    $record['role'] = 'professional'; $record['active'] = '1';
    try { saveEmployee($id, $id, $record, false); echo 'saved'; }
    catch (DomainException $e) { echo 'denied'; }
    exit;
}
$database = 'bloome_test_employees_' . bin2hex(random_bytes(5));
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$rootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: '';
$root = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", 'root', $rootPassword, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$server = null; $pipes = []; $checks = 0; $jars = []; $failed = false;
$webPort = random_int(18000, 22000);
function checkEmployee(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException($label); echo "OK: $label\n"; $checks++; }
function httpEmployee(string $jar, string $page, ?array $fields = null): array {
    global $jars, $webPort;
    $cookie = $jars[$jar] ?? '';
    $ctx = stream_context_create(['http'=>['method'=>$fields === null ? 'GET':'POST', 'header'=>"Content-Type: application/x-www-form-urlencoded\r\nCookie: $cookie", 'content'=>$fields === null ? '' : http_build_query($fields), 'ignore_errors'=>true, 'follow_location'=>0, 'timeout'=>10]]);
    $body = file_get_contents("http://127.0.0.1:$webPort/?page=$page", false, $ctx);
    $headers = http_get_last_response_headers();
    preg_match('/\s(\d{3})\s/', $headers[0], $status);
    foreach ($headers as $header) if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $header, $m)) $jars[$jar] = $m[1];
    return [(int)$status[1], $body];
}
function employeeToken(string $body): string { preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $m); return $m[1] ?? ''; }
function loginEmployee(string $jar, string $email, string $password): int {
    [, $body] = httpEmployee($jar, 'login');
    return httpEmployee($jar, 'login', ['csrf'=>employeeToken($body),'email'=>$email,'password'=>$password])[0];
}
function rejectedSql(callable $fn): bool { try { $fn(); return false; } catch (PDOException $e) { if ($e->getCode() !== '45000') throw $e; return true; } }
try {
    $root->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    putenv('DB_DATABASE=' . $database); putenv('DB_USERNAME=root'); putenv('DB_PASSWORD=' . $rootPassword);
    foreach (explode(';', file_get_contents(ROOT . '/database/schema.sql')) as $sql) if (trim($sql) !== '') db()->exec($sql);
    $password = bin2hex(random_bytes(12));
    query("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,'admin')", ['QA admin','admin@example.test',password_hash($password,PASSWORD_DEFAULT)]);
    $admin = (int)db()->lastInsertId();
    migrateEmployees();
    $adminEmployee = (int)query('SELECT id FROM employees WHERE user_id=?', [$admin])->fetchColumn();
    // Existing history must survive migration, including repeat execution.
    query("INSERT INTO clients (first_name,last_name) VALUES ('QA','Historia')"); $client = db()->lastInsertId();
    query("INSERT INTO services (name,duration_minutes,price) VALUES ('QA',30,100)"); $service = db()->lastInsertId();
    query("INSERT INTO appointments (client_id,employee_id,starts_at,ends_at,status) VALUES (?,?,'2026-01-01 09:00','2026-01-01 09:30','completed')", [$client,$adminEmployee]);
    $old = query('SELECT * FROM appointments')->fetchAll();
    migrateEmployees(); migrateEmployees();
    checkEmployee(query('SELECT * FROM appointments')->fetchAll() === $old, 'Migración repetible conserva citas y sus relaciones');
    checkEmployee((int)query('SELECT COUNT(*) FROM employees')->fetchColumn() === 1, 'Backfill único de usuarios existentes');
    $server = proc_open([PHP_BINARY,'-S',"127.0.0.1:$webPort",'-t',ROOT.'/public',ROOT.'/public/router.php'], [0=>['pipe','r'],1=>['file','php://temp','w'],2=>['file','php://temp','w']], $pipes, ROOT);
    if (!is_resource($server)) throw new RuntimeException('No se pudo iniciar servidor de pruebas');
    for ($i=0;$i<50;$i++) { $socket=@fsockopen('127.0.0.1',$webPort); if ($socket) { fclose($socket); break; } usleep(100000); }
    checkEmployee(httpEmployee('anon','employees')[0] === 303, 'Sin sesión no accede a empleados');
    checkEmployee(loginEmployee('admin','admin@example.test',$password) === 303, 'Administrador inicia sesión');
    [, $body]=httpEmployee('admin','employee-new'); $csrf=employeeToken($body);
    $fields=['csrf'=>$csrf,'name'=>'QA <script>','email'=>'worker@example.test','phone'=>'+54 11 123456','role'=>'professional','active'=>'1','appointment_color'=>'#FF8800','password'=>$password];
    checkEmployee(httpEmployee('admin','employee-new',array_diff_key($fields,['csrf'=>1]))[0]===419, 'Alta sin CSRF rechazada');
    checkEmployee(httpEmployee('admin','employee-new',array_replace($fields,['role'=>'superadmin','appointment_color'=>'red','active'=>'yes','password'=>'short']))[0]===422, 'Valida rol, estado, color y contraseña en PHP');
    checkEmployee(httpEmployee('admin','employee-new',$fields)[0]===303,'Alta de empleado persistida');
    $worker=(int)query('SELECT id FROM users WHERE email=?',[$fields['email']])->fetchColumn();
    $record=employeeRecord($worker);
    checkEmployee($record['appointment_color']==='#FF8800' && $record['role']==='professional', 'Rol y color guardados en MySQL');
    [, $body]=httpEmployee('admin','employee-edit&id='.$worker); $editCsrf=employeeToken($body);
    $extra=['csrf'=>$editCsrf,'name'=>$fields['name'],'email'=>$fields['email'],'phone'=>'','role'=>'professional','active'=>'1','appointment_color'=>'#FF8800'];
    foreach ([['commission_percentage','101'],['commission_percentage','abc'],['commission_percentage','-5'],['hire_date','2999-01-01'],['hire_date','2026-02-31'],['specialty',str_repeat('a',121)]] as [$k,$v])
        checkEmployee(httpEmployee('admin','employee-edit&id='.$worker,array_replace($extra,[$k=>$v]))[0]===422,'Rechaza '.$k.' inválido: '.substr($v,0,10));
    checkEmployee(httpEmployee('admin','employee-edit&id='.$worker,array_replace($extra,['specialty'=>'Masajista','hire_date'=>'2024-03-15','commission_percentage'=>'12,5']))[0]===303,'Admin guarda especialidad, ingreso y comisión');
    $record=employeeRecord($worker);
    checkEmployee($record['specialty']==='Masajista' && $record['hire_date']==='2024-03-15' && (float)$record['commission_percentage']===12.5,'Campos de empleado persistidos en MySQL');
    checkEmployee(httpEmployee('admin','employee-edit&id='.$worker,array_replace($extra,['specialty'=>'','hire_date'=>'','commission_percentage'=>'']))[0]===303 && employeeRecord($worker)['commission_percentage']===null,'Campos opcionales se pueden vaciar');
    httpEmployee('admin','employee-edit&id='.$worker,array_replace($extra,['specialty'=>'Masajista','commission_percentage'=>'10']));
    checkEmployee(httpEmployee('admin','employee-new',$fields)[0]===422 && (int)query('SELECT COUNT(*) FROM users')->fetchColumn()===2,'Correo duplicado no deja usuario parcial');
    [, $body]=httpEmployee('admin','employees&q=worker');
    checkEmployee(str_contains($body,'QA &lt;script&gt;') && !str_contains($body,'QA <script>'), 'Listado busca y escapa HTML almacenado');
    checkEmployee(httpEmployee('admin','employee-edit&id=99999999')[0]===404,'Empleado inexistente devuelve 404');
    checkEmployee(loginEmployee('worker',$fields['email'],$password)===303,'Profesional activo inicia sesión');
    [, $body]=httpEmployee('worker','profile-edit'); $workerCsrf=employeeToken($body);
    foreach (['employees','employee-new','employee-edit&id='.$admin,'employee&id='.$admin] as $page) checkEmployee(httpEmployee('worker',$page)[0]===403, 'Profesional no accede a '.$page);
    checkEmployee(httpEmployee('worker','employee-edit&id='.$admin,array_replace($fields,['csrf'=>$workerCsrf]))[0]===403,'POST administrativo ajeno rechazado');
    checkEmployee(httpEmployee('worker','profile-edit&id='.$admin)[0]===403,'ID ajeno en perfil rechazado');
    foreach (['role'=>'admin','active'=>'1','user_id'=>$admin,'appointment_color'=>'#FFFFFF','password_hash'=>'x'] as $key=>$value) {
        checkEmployee(httpEmployee('worker','profile-edit',['csrf'=>$workerCsrf,'name'=>'Ataque','email'=>$fields['email'],'phone'=>'',$key=>$value])[0]===403,'Perfil rechaza campo protegido '.$key);
    }
    checkEmployee(employeeRecord($worker)['role']==='professional' && employeeRecord($admin)['name']==='QA admin','Solicitudes manipuladas no alteran privilegios ni otro usuario');
    checkEmployee(httpEmployee('worker','profile-edit',['csrf'=>$workerCsrf,'name'=>'Perfil actualizado','email'=>'worker-new@example.test','phone'=>'12345678'])[0]===303,'Edición propia permitida y persistida');
    checkEmployee(employeeRecord($worker)['phone']==='12345678','Teléfono de perfil guardado');
    checkEmployee(employeeRecord($worker)['commission_percentage']==='10.00','Edición propia no modifica comisión');
    [, $body]=httpEmployee('worker','profile'); checkEmployee(str_contains($body,'Comisión') && str_contains($body,'Masajista'),'Profesional ve su propia comisión y especialidad');
    checkEmployee(httpEmployee('worker','profile-edit',['csrf'=>$workerCsrf,'name'=>'X','email'=>'worker-new@example.test','phone'=>'','commission_percentage'=>'90'])[0]===403,'Perfil propio rechaza comisión');
    checkEmployee(httpEmployee('worker','profile-edit',['csrf'=>$workerCsrf,'name'=>'QA','email'=>'admin@example.test','phone'=>''])[0]===422,'Perfil rechaza correo duplicado sin cambiar datos');
    $workerEmployee = (int)query('SELECT id FROM employees WHERE user_id=?', [$worker])->fetchColumn();
    query("INSERT INTO appointments (client_id,employee_id,starts_at,ends_at,status) VALUES (?,?,'2026-01-02 09:00','2026-01-02 09:30','completed')",[$client,$workerEmployee]);
    $history=query('SELECT * FROM appointments WHERE employee_id=?',[$workerEmployee])->fetchAll();
    $edit=array_replace($fields,['email'=>'worker-new@example.test','active'=>'0']); unset($edit['password']);
    checkEmployee(httpEmployee('admin','employee-edit&id='.$worker,$edit)[0]===303,'Administrador desactiva empleado');
    checkEmployee((int)employeeRecord($worker)['active']===0 && (int)query('SELECT active FROM employees WHERE user_id=?',[$worker])->fetchColumn()===0,'Desactivación sincroniza usuario y empleado');
    checkEmployee(httpEmployee('worker','profile')[0]===303,'Sesión ya abierta pierde acceso al desactivar');
    checkEmployee(loginEmployee('inactive','worker-new@example.test',$password)===422,'Empleado inactivo no puede iniciar sesión');
    checkEmployee(query('SELECT * FROM appointments WHERE employee_id=?',[$workerEmployee])->fetchAll()===$history,'Desactivar conserva historial íntegro');
    checkEmployee(rejectedSql(fn()=>query("INSERT INTO appointments (client_id,employee_id,starts_at,ends_at) VALUES (?,?,'2027-01-01 09:00','2027-01-01 09:30')",[$client,$workerEmployee])),'MySQL impide nueva cita con empleado inactivo');
    checkEmployee(rejectedSql(fn()=>query('UPDATE appointments SET employee_id=? WHERE id=?',[$workerEmployee,$old[0]['id']])),'MySQL impide reasignar cita a inactivo');
    checkEmployee(rejectedSql(fn()=>query("UPDATE appointments SET starts_at='2027-01-01 09:00',ends_at='2027-01-01 09:30' WHERE id=?",[$history[0]['id']])),'MySQL impide reprogramar empleado inactivo');
    [, $body]=httpEmployee('admin','agenda&date=2026-01-02');
    checkEmployee(str_contains($body,'QA &lt;script&gt;') && str_contains($body,'#FF8800'),'Agenda conserva empleado inactivo y muestra su color');
    $edit['active']='1';
    checkEmployee(httpEmployee('admin','employee-edit&id='.$worker,$edit)[0]===303 && loginEmployee('reactivated','worker-new@example.test',$password)===303,'Reactivar devuelve acceso');
    $adminData=['csrf'=>$csrf,'name'=>'QA admin','email'=>'admin@example.test','phone'=>'','role'=>'professional','active'=>'1','appointment_color'=>'#0E3D3E'];
    checkEmployee(httpEmployee('admin','employee-edit&id='.$admin,$adminData)[0]===422,'No permite degradar al último administrador');
    $adminData['role']='admin'; $adminData['active']='0';
    checkEmployee(httpEmployee('admin','employee-edit&id='.$admin,$adminData)[0]===422,'No permite desactivar al último administrador');
    $reception=array_replace($fields,['email'=>'reception@example.test','role'=>'reception']);
    checkEmployee(httpEmployee('admin','employee-new',$reception)[0]===303 && loginEmployee('reception',$reception['email'],$password)===303,'Acceso de recepción');
    [, $body]=httpEmployee('reception','profile'); $rc=employeeToken($body);
    checkEmployee(httpEmployee('reception','employees')[0]===403 && httpEmployee('reception','employee-new',array_replace($fields,['csrf'=>$rc]))[0]===403,'Recepción no administra empleados por GET ni POST');
    checkEmployee(httpEmployee('worker','profile-edit',['name'=>'Sin CSRF','email'=>'worker-new@example.test'])[0]===419,'Perfil exige CSRF para modificar datos');
    // Partial legacy employee state must also be respected by auth and assignment.
    query('UPDATE employees SET active=0 WHERE user_id=?',[$worker]);
    checkEmployee(httpEmployee('reactivated','profile')[0]===303,'Estado inactivo de employees también revoca sesión');
    checkEmployee(rejectedSql(fn()=>query("INSERT INTO appointments (client_id,employee_id,starts_at,ends_at) VALUES (?,?,'2027-02-01 09:00','2027-02-01 09:30')",[$client,$workerEmployee])),'Estado inactivo de employees también impide nuevas citas');
    query('UPDATE employees SET active=1 WHERE user_id=?',[$worker]);
    $dormant=saveEmployee($admin,null,['name'=>'QA dormant','email'=>'dormant@example.test','phone'=>'','role'=>'admin','active'=>'1','appointment_color'=>'#123456','password'=>$password],false);
    query('UPDATE employees SET active=0 WHERE user_id=?',[$dormant]);
    checkEmployee(httpEmployee('admin','employee-edit&id='.$admin,$adminData)[0]===422,'Un administrador sin acceso efectivo no cuenta como respaldo');
    // Two real PHP processes race to demote themselves; exactly one active administrator must survive.
    $other=saveEmployee($admin,null,['name'=>'QA other','email'=>'other@example.test','phone'=>'','role'=>'admin','active'=>'1','appointment_color'=>'#123456','password'=>$password],false);
    $children=[];
    foreach ([$admin,$other] as $id) { $cp=[]; $process=proc_open([PHP_BINARY,__FILE__,'--worker',(string)$id],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$cp,ROOT); $children[]=[$process,$cp]; }
    $outcomes=[];
    foreach ($children as [$process,$cp]) { fclose($cp[0]); $outcomes[]=stream_get_contents($cp[1]); $err=stream_get_contents($cp[2]); fclose($cp[1]);fclose($cp[2]); if(proc_close($process)!==0) throw new RuntimeException($err); }
    sort($outcomes);
    checkEmployee($outcomes===['denied','saved'] && (int)query("SELECT COUNT(*) FROM users u LEFT JOIN employees e ON e.user_id=u.id WHERE u.role='admin' AND u.active=1 AND COALESCE(e.active,1)=1")->fetchColumn()===1,'Concurrencia: dos degradaciones conservan un administrador activo');
    checkEmployee((int)query('SELECT COUNT(*) FROM employee_audit')->fetchColumn()>=6,'Mutaciones registran actor y usuario afectado');
    echo "\n$checks pruebas de empleados HTTP/MySQL aprobadas.\n";
} catch (Throwable $e) { fwrite(STDERR, 'FAIL: '.$e->getMessage()."\n"); $failed=true; }
finally {
    if (is_resource($server)) { proc_terminate($server); foreach($pipes as $pipe) if(is_resource($pipe)) fclose($pipe); proc_close($server); }
    if (preg_match('/^bloome_test_employees_[a-f0-9]{10}$/D',$database)) $root->exec("DROP DATABASE IF EXISTS `$database`");
}
exit($failed?1:0);
