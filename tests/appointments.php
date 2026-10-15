<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require ROOT.'/app/employee-migration.php';
if (($argv[1]??'')==='--worker') {
    try { saveAppointment(1,null,['client_id'=>'1','employee_id'=>'501','starts_at'=>'2026-11-03T14:00','status'=>'confirmed','notes'=>'race','services'=>['1']]); echo 'saved'; }
    catch(DomainException $e) { echo $e->getCode()===409?'conflict':'error'; }
    exit;
}
$database='bloome_test_appointments_'.bin2hex(random_bytes(5));
$host=getenv('DB_HOST')?:'127.0.0.1'; $port=getenv('DB_PORT')?:'3306'; $password=getenv('MYSQL_ROOT_PASSWORD')?:'';
$root=new PDO("mysql:host=$host;port=$port;charset=utf8mb4",'root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$checks=0; $server=null; $webPort=random_int(25001,28000); $cookies=[];
function apCheck(bool $ok,string $label): void { global $checks; if(!$ok) throw new RuntimeException($label); $checks++; echo "OK: $label\n"; }
function apReject(callable $fn,int $code,string $label): void { try { $fn(); } catch(DomainException $e) { apCheck($e->getCode()===$code,$label); return; } throw new RuntimeException('Se aceptó: '.$label); }
function apRequest(string $page,?array $data=null): array {
    global $webPort,$cookies;
    $ctx=stream_context_create(['http'=>['method'=>$data===null?'GET':'POST','header'=>"Content-Type: application/x-www-form-urlencoded\r\nCookie: ".implode('; ',$cookies),'content'=>$data===null?'':http_build_query($data),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>10]]);
    $body=file_get_contents("http://127.0.0.1:$webPort/?page=$page",false,$ctx);
    $headers=http_get_last_response_headers(); preg_match('/\s(\d{3})\s/',$headers[0],$m);
    foreach($headers as $header) if(preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i',$header,$cookie)) $cookies[$cookie[1]]=$cookie[1].'='.$cookie[2];
    return [(int)$m[1],$body];
}
function apToken(string $body,string $name='csrf'): string { preg_match('/name="'.$name.'" value="([a-f0-9]+)"/',$body,$m); return $m[1]??''; }
try {
    $root->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    putenv('DB_DATABASE='.$database); putenv('DB_USERNAME=root'); putenv('DB_PASSWORD='.$password);
    foreach(explode(';',file_get_contents(ROOT.'/database/schema.sql')) as $sql) if(trim($sql)!=='') db()->exec($sql);
    $loginPassword=bin2hex(random_bytes(16)); $hash=password_hash($loginPassword,PASSWORD_DEFAULT);
    foreach([[1,'admin'],[2,'professional'],[3,'reception']] as [$uid,$role]) query('INSERT INTO users(id,name,email,password_hash,role) VALUES (?,?,?,?,?)',[$uid,'QA '.$role,$role.'@example.test',$hash,$role]);
    query('INSERT INTO employees(id,user_id) VALUES (501,1),(502,2),(503,3)'); migrateEmployees();
    query("INSERT INTO clients(id,first_name,last_name) VALUES (1,'QA','Cliente')");
    query("INSERT INTO services(id,name,duration_minutes,price) VALUES (1,'Primero',30,100),(2,'Segundo',20,200),(3,'Inactivo',15,50)");
    query('UPDATE services SET active=0 WHERE id=3');
    for($day=1;$day<=7;$day++) {
        query("INSERT INTO business_schedules(day_of_week,start_time,end_time,is_open) VALUES (?,'09:00','18:00',1)",[$day]);
        foreach([1,2,3] as $uid) query("INSERT INTO employee_schedules(user_id,day_of_week,start_time,end_time,is_working) VALUES (?,?,'09:00','18:00',1)",[$uid,$day]);
    }
    $data=['client_id'=>'1','employee_id'=>'501','starts_at'=>'2026-11-02T10:00','status'=>'confirmed','notes'=>'<script>test</script>','services'=>['1','2']];
    $id=saveAppointment(1,null,$data); $record=appointmentRecord($id);
    apCheck($record['ends_at']==='2026-11-02 10:50:00' && count($record['services'])===2,'Duración total y dos servicios persistidos');
    apReject(fn()=>saveAppointment(2,null,$data),403,'Profesional no puede escribir');
    apReject(fn()=>saveAppointment(1,null,$data),409,'Doble reserva rechazada');
    foreach(['09:45','10:15','10:40'] as $time) apReject(fn()=>saveAppointment(1,null,array_replace($data,['starts_at'=>'2026-11-02T'.$time])),409,'Solapamiento '.$time);
    apCheck(saveAppointment(3,null,array_replace($data,['starts_at'=>'2026-11-02T10:50']))>0,'Recepción puede reservar en límite exacto');
    apCheck(saveAppointment(1,null,array_replace($data,['employee_id'=>'502']))>0,'Otro empleado admite el mismo horario');
    foreach([['client_id'=>'999'],['employee_id'=>'999'],['services'=>['3']],['services'=>['1','1']],['services'=>[['1']]],['services'=>'1'],['services'=>[]],['starts_at'=>'2026-02-30T10:00'],['starts_at'=>'2026-11-02T08:00'],['starts_at'=>'2026-11-02T17:30'],['starts_at'=>'2026-11-02T23:50'],['status'=>'invalid'],['notes'=>str_repeat('a',5001)]] as $bad) apReject(fn()=>saveAppointment(1,null,array_replace($data,$bad)),422,'Rechaza datos inválidos: '.implode(',',array_keys($bad)));
    query("INSERT INTO holidays(holiday_date,description) VALUES ('2026-11-04','QA')");
    apReject(fn()=>saveAppointment(1,null,array_replace($data,['starts_at'=>'2026-11-04T10:00'])),422,'Feriado bloqueado');
    query("INSERT INTO employee_vacations(user_id,description,starts_at,ends_at) VALUES (1,'QA','2026-11-05','2026-11-05')");
    apReject(fn()=>saveAppointment(1,null,array_replace($data,['starts_at'=>'2026-11-05T10:00'])),422,'Ausencia bloqueada');
    query('UPDATE employees SET active=0 WHERE id=502');
    apReject(fn()=>saveAppointment(1,null,array_replace($data,['employee_id'=>'502','starts_at'=>'2026-11-06T12:00'])),422,'Empleado inactivo no recibe turnos');
    query('UPDATE employees SET active=1 WHERE id=502');
    query('DELETE FROM employee_schedules WHERE user_id=2 AND day_of_week=5');
    apReject(fn()=>saveAppointment(1,null,array_replace($data,['employee_id'=>'502','starts_at'=>'2026-11-06T12:00'])),422,'Empleado sin horario no recibe turnos');
    $beforeFailedEdit=appointmentRecord($id);
    apReject(fn()=>saveAppointment(1,$id,array_replace($data,['starts_at'=>'2026-11-02T11:00','version'=>appointmentVersion($beforeFailedEdit)])),409,'Reprogramación comprueba solapamiento');
    apCheck(appointmentRecord($id)===$beforeFailedEdit,'Edición rechazada conserva íntegramente la cita');
    query('UPDATE services SET price=999,duration_minutes=90 WHERE id=1');
    saveAppointment(1,$id,$data+['version'=>appointmentVersion($record)]);
    $current=appointmentRecord($id);
    apCheck($current['services']===$record['services'] && $current['ends_at']===$record['ends_at'],'Edición conserva precio y duración históricos');
    $stale=appointmentVersion($current);
    saveAppointment(1,$id,array_replace($data,['notes'=>'modificado','version'=>$stale]));
    apReject(fn()=>saveAppointment(1,$id,$data+['version'=>$stale]),409,'Edición obsoleta no sobrescribe cambios');
    $current=appointmentRecord($id); saveAppointment(1,$id,['version'=>appointmentVersion($current)],true);
    apCheck(appointmentRecord($id)['status']==='cancelled' && appointmentRecord($id)['services']===$current['services'],'Cancelación conserva servicios e historial');
    query('UPDATE services SET duration_minutes=30 WHERE id=1');
    $replacement=saveAppointment(1,null,$data);
    apCheck($replacement>0,'Cancelar libera el horario');
    apReject(fn()=>saveAppointment(1,$id,$data+['version'=>appointmentVersion(appointmentRecord($id))]),409,'Reabrir comprueba conflictos');
    // Two real PHP processes race for a previously empty slot.
    $workers=[];
    for($i=0;$i<2;$i++) { $pipes=[]; $proc=proc_open([PHP_BINARY,__FILE__,'--worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ROOT); fclose($pipes[0]); $workers[]=[$proc,$pipes]; }
    $results=[]; foreach($workers as [$proc,$pipes]) { $results[]=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); if(proc_close($proc)!==0) throw new RuntimeException($err); } sort($results);
    apCheck($results===['conflict','saved'],'Concurrencia: exactamente una reserva aceptada');
    apCheck((int)query("SELECT COUNT(*) FROM appointment_audit WHERE appointment_id=?",[$id])->fetchColumn()===4,'Auditoría registra altas, cambios y cancelación');
    $server=proc_open([PHP_BINARY,'-S',"127.0.0.1:$webPort",'-t',ROOT.'/public',ROOT.'/public/router.php'],[0=>['pipe','r'],1=>['file','php://temp','w'],2=>['file','php://temp','w']],$pipes,ROOT);
    for($i=0;$i<50;$i++) { $socket=@fsockopen('127.0.0.1',$webPort); if($socket) { fclose($socket); break; } usleep(100000); }
    apCheck(apRequest('appointment-new')[0]===303,'Formulario protegido sin sesión');
    [, $body]=apRequest('login'); apRequest('login',['csrf'=>apToken($body),'email'=>'admin@example.test','password'=>$loginPassword]);
    [$status,$body]=apRequest('appointment-new'); $csrf=apToken($body);
    apCheck($status===200 && str_contains($body,'name="services[]"'),'Formulario ofrece servicios múltiples');
    apCheck(apRequest('appointment-new',$data)[0]===419,'POST exige CSRF');
    apCheck(apRequest('appointment-new',array_replace($data,['csrf'=>$csrf,'starts_at'=>'2026-11-06T10:00']))[0]===303,'Alta HTTP guarda y redirige');
    $httpId=(int)query('SELECT MAX(id) FROM appointments')->fetchColumn();
    [$status,$body]=apRequest('appointment-edit&id='.$httpId);
    apCheck($status===200 && str_contains($body,'&lt;script&gt;test&lt;/script&gt;'),'Edición escapa notas');
    apCheck(apRequest('appointment-cancel&id='.$httpId)[0]===405,'Cancelar por GET está prohibido');
    apCheck(apRequest('appointment-cancel&id='.$httpId,['csrf'=>$csrf,'version'=>apToken($body,'version')])[0]===303,'Cancelación HTTP válida');
    apCheck(apRequest('appointment-edit&id=999999')[0]===404,'ID inexistente devuelve 404');
    apRequest('logout',['csrf'=>$csrf]); [, $body]=apRequest('login'); apRequest('login',['csrf'=>apToken($body),'email'=>'professional@example.test','password'=>$loginPassword]);
    apCheck(apRequest('appointment-new')[0]===403 && apRequest('appointment-edit&id='.$id)[0]===403,'Profesional sin controles de escritura');
    echo "\n$checks pruebas de turnos aprobadas.\n";
} finally {
    if(is_resource($server)) { proc_terminate($server); proc_close($server); }
    if(preg_match('/^bloome_test_appointments_[a-f0-9]{10}$/D',$database)) $root->exec("DROP DATABASE IF EXISTS `$database`");
}
