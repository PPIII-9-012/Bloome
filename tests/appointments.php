<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require ROOT.'/app/employee-migration.php';
if (($argv[1]??'')==='--break-worker') {
    echo "ready\n"; flush();
    try { saveAppointment(1,null,['client_id'=>'1','employee_id'=>'501','starts_at'=>'2026-11-10T14:00','status'=>'confirmed','notes'=>'break race','services'=>['1']]); echo 'saved'; }
    catch(DomainException $e) { echo $e->getCode()===422?'unavailable':'error'; }
    exit;
}
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
    $location=''; foreach($headers as $header) if(str_starts_with($header,'Location: ')) $location=substr($header,10);
    return [(int)$m[1],$body,$location];
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
    [$status,$body]=apRequest('appointment-new&date=2026-11-13&mode=week&employee=501');
    apCheck($status===200 && str_contains($body,'value="2026-11-13T09:00"') && str_contains($body,'value="501" selected'),'Alta contextual precarga fecha y empleado');
    $draftData=array_replace($data,['starts_at'=>'2026-11-13T10:00','csrf'=>$csrf,'intent'=>'new-client','agenda'=>['date'=>'2026-11-13','mode'=>'week','employee'=>'501']]);
    apCheck(apRequest('appointment-new',array_diff_key($draftData,['csrf'=>true]))[0]===419,'Crear borrador exige CSRF');
    [$status,,$location]=apRequest('appointment-new',$draftData);
    parse_str(parse_url($location,PHP_URL_QUERY),$destination); $draftToken=$destination['draft']??'';
    apCheck($status===303 && strlen($draftToken)===32 && $destination['page']==='client-new','Alta de cliente abre borrador privado');
    [$status,$body]=apRequest('client-new&draft='.$draftToken);
    apCheck($status===200 && str_contains($body,'Volver al turno') && str_contains($body,'draft='.$draftToken),'Formulario existente conserva retorno al turno');
    apCheck(apRequest('client-new&draft='.$draftToken,['csrf'=>$csrf,'first_name'=>'','last_name'=>''])[0]===422,'Cliente inválido permite corregir sin perder borrador');
    [, $body]=apRequest('appointment-new&draft='.$draftToken);
    apCheck(str_contains($body,'value="2026-11-13T10:00"') && str_contains($body,'&lt;script&gt;test&lt;/script&gt;') && str_contains($body,'value="2" checked'),'Volver sin crear cliente conserva fecha, notas y servicios');
    $clientCount=(int)query('SELECT COUNT(*) FROM clients')->fetchColumn();
    $newClient=['csrf'=>$csrf,'first_name'=>'Contextual','last_name'=>'Prueba'];
    [$status,,$location]=apRequest('client-new&draft='.$draftToken,$newClient);
    $createdClient=(string)query('SELECT MAX(id) FROM clients')->fetchColumn();
    apCheck($status===303 && str_contains($location,'appointment-new') && (int)query('SELECT COUNT(*) FROM clients')->fetchColumn()===$clientCount+1,'Crear cliente retorna al borrador sin reservar turno');
    apRequest('client-new&draft='.$draftToken,$newClient);
    apCheck((int)query('SELECT COUNT(*) FROM clients')->fetchColumn()===$clientCount+1,'Reenviar alta contextual no duplica cliente');
    [$status,$body]=apRequest('appointment-new&draft='.$draftToken);
    apCheck(str_contains($body,'value="'.$createdClient.'" selected') && str_contains($body,'name="agenda[mode]" value="week"'),'Retorno selecciona cliente nuevo y conserva contexto');
    $otherCookies=$cookies; $cookies=[];
    [, $body]=apRequest('login'); apRequest('login',['csrf'=>apToken($body),'email'=>'reception@example.test','password'=>$loginPassword]);
    apCheck(apRequest('client-new&draft='.$draftToken)[0]===410,'Otra sesión no accede al borrador'); $cookies=$otherCookies;
    $saveDraft=array_replace($draftData,['client_id'=>$createdClient]); unset($saveDraft['intent']);
    [$status,,$location]=apRequest('appointment-new&draft='.$draftToken,$saveDraft);
    apCheck($status===303 && str_contains($location,'mode=week') && str_contains($location,'employee=501'),'Guardar turno vuelve al período y filtros originales');
    apCheck(apRequest('appointment-new&draft='.$draftToken)[0]===410,'Guardar consume el borrador');
    $beforeDraft=appointmentRecord($id);
    $editDraft=array_replace($draftData,['version'=>appointmentVersion($beforeDraft)]);
    [,,$editLocation]=apRequest('appointment-edit&id='.$id,$editDraft);
    parse_str(parse_url($editLocation,PHP_URL_QUERY),$editDestination); $editToken=$editDestination['draft'];
    query('UPDATE appointments SET notes=? WHERE id=?',['cambio de otra persona',$id]);
    $editSave=$editDraft; unset($editSave['intent']);
    apCheck(apRequest('appointment-edit&id='.$id.'&draft='.$editToken,$editSave)[0]===409,'Borrador de edición no sobrescribe cambios concurrentes');
    apCheck(appointmentRecord($id)['notes']==='cambio de otra persona','Conflicto del borrador conserva datos actuales');
    apRequest('logout',['csrf'=>$csrf]); [, $body]=apRequest('login'); apRequest('login',['csrf'=>apToken($body),'email'=>'professional@example.test','password'=>$loginPassword]);
    [, $body]=apRequest('dashboard');
    apCheck(apRequest('appointment-new',array_replace($draftData,['csrf'=>apToken($body)]))[0]===403,'Profesional no puede iniciar alta contextual');
    apCheck(apRequest('appointment-new')[0]===403 && apRequest('appointment-edit&id='.$id)[0]===403,'Profesional sin controles de escritura');
    $historical=appointmentRecord($replacement);
    query("UPDATE employee_schedules SET break_start='10:00',break_end='12:00' WHERE user_id=1 AND day_of_week=1");
    apCheck(appointmentRecord($replacement)===$historical,'Nuevo descanso conserva citas históricas');
    apReject(fn()=>saveAppointment(1,null,array_replace($data,['starts_at'=>'2026-11-09T10:00'])),422,'Crear turno dentro del descanso se rechaza');
    apReject(fn()=>saveAppointment(1,$replacement,array_replace($data,['starts_at'=>'2026-11-09T09:45','version'=>appointmentVersion($historical)])),422,'Reprogramar cruzando descanso se rechaza');
    apCheck(appointmentRecord($replacement)===$historical,'Reprogramación rechazada conserva cita y servicios');
    saveAppointment(1,$replacement,array_replace($data,['notes'=>'historia conservada','version'=>appointmentVersion($historical)]));
    apCheck(appointmentRecord($replacement)['notes']==='historia conservada','Notas históricas pueden editarse sin mover el turno');
    // A booking must see a concurrent break update after waiting for the schedule row.
    db()->beginTransaction();
    query("UPDATE employee_schedules SET break_start='13:00',break_end='15:00' WHERE user_id=1 AND day_of_week=2");
    $worker=proc_open([PHP_BINARY,__FILE__,'--break-worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$workerPipes,ROOT);
    fclose($workerPipes[0]);
    apCheck(trim(fgets($workerPipes[1]))==='ready','Proceso concurrente iniciado con horario bloqueado');
    usleep(200000);
    db()->commit();
    $out=stream_get_contents($workerPipes[1]); $err=stream_get_contents($workerPipes[2]);
    fclose($workerPipes[1]); fclose($workerPipes[2]);
    apCheck(proc_close($worker)===0 && $out==='unavailable','Reserva concurrente ve el descanso confirmado y se rechaza');
    apCheck((int)query("SELECT COUNT(*) FROM appointments WHERE notes='break race'")->fetchColumn()===0,'No se guarda cita parcial durante cambio concurrente');
    $legacyData=['client_id'=>'1','employee_id'=>'501','starts_at'=>'2026-11-12T10:00','status'=>'confirmed','notes'=>'antes','services'=>['1']];
    $legacyId=saveAppointment(1,null,$legacyData);
    query("UPDATE appointments SET ends_at='2026-11-12 11:00:00' WHERE id=?",[$legacyId]);
    $legacy=appointmentRecord($legacyId);
    saveAppointment(1,$legacyId,array_replace($legacyData,['notes'=>'solo notas','version'=>appointmentVersion($legacy)]));
    apCheck(appointmentRecord($legacyId)['ends_at']===$legacy['ends_at'],'Editar notas conserva intervalo histórico distinto del detalle');
    $legacy=appointmentRecord($legacyId);
    saveAppointment(1,$legacyId,array_replace($legacyData,['starts_at'=>'2026-11-12T12:00','version'=>appointmentVersion($legacy)]));
    apCheck(appointmentRecord($legacyId)['ends_at']==='2026-11-12 12:30:00','Reprogramar calcula la duración de servicios');
    [, $agendaBody]=apRequest('agenda');
    apCheck(!str_contains($agendaBody,'onclick=') && !str_contains($agendaBody,'$ 0,00') && !str_contains($agendaBody,'income-pill'),'Agenda no ofrece acciones simuladas ni ingresos ficticios');
    $calendar=appointmentsBetween('2026-11-12','2026-11-13',null,501,'confirmed');
    apCheck(count($calendar)===1 && (int)$calendar[0]['id']===$legacyId,'Calendario combina fecha, empleado canónico y estado');
    apCheck(appointmentsBetween('2026-11-11','2026-11-12',null,501,'confirmed')===[],'Límite final del período excluye día siguiente');
    apCheck(appointmentsBetween('2026-11-12','2026-11-13',null,501,'cancelled')===[],'Filtro de estado excluye otras citas');
    [$status,$body]=apRequest('agenda&date=2026-11-12&mode=month&employee=501&status=confirmed');
    apCheck($status===200 && str_contains($body,'agenda-month') && str_contains($body,'12:00–12:30') && str_contains($body,'30/11'),'Mes muestra citas reales y último día');
    apCheck(str_contains($body,'employee=501&amp;status=confirmed&amp;date=2026-12-01'),'Navegar conserva ambos filtros');
    apCheck(!str_contains($body,'Editar turno') && !str_contains($body,'Nuevo turno'),'Calendario profesional no muestra acciones de escritura');
    [$status,$body]=apRequest('agenda&date=2026-11-12&mode=week&employee=501&status=cancelled');
    apCheck($status===200 && str_contains($body,'agenda-week') && str_contains($body,'No hay turnos para este período y estos filtros.'),'Semana vacía informa alcance de filtros');
    apCheck(apRequest('agenda&employee=999999')[0]===422 && apRequest('agenda&status=invalid')[0]===422,'Filtros desconocidos se rechazan en servidor');
    [$status,$body]=apRequest('agenda&date=2026-11-12&mode=unknown');
    apCheck($status===200 && str_contains($body,'agenda-day'),'Vista inválida vuelve al día');
    query("INSERT INTO clients(id,first_name,last_name) VALUES (77,'=HYPERLINK(\"http://x.test\")','Fórmula; \"Ñandú\"')");
    saveAppointment(1,null,['client_id'=>'77','employee_id'=>'501','starts_at'=>'2026-11-20T10:00','status'=>'confirmed','notes'=>'privada','services'=>['1']]);
    [$status,$body]=apRequest('agenda-export&date=2026-11-20&mode=day&employee=501&status=confirmed');
    apCheck($status===200 && str_starts_with($body,"\xEF\xBB\xBFFecha;Inicio;Fin;Cliente;Servicios;Empleado;Estado\r\n"),'CSV UTF-8 con encabezados en español');
    apCheck(str_contains($body,'20/11/2026;10:00;10:30;"\'=HYPERLINK(""http://x.test"") Fórmula; ""Ñandú""";Primero;"QA admin";Confirmada') && !str_contains($body,'privada'),'CSV protege fórmulas, escapa separadores y omite notas');
    apCheck(substr_count(rtrim($body),"\r\n")===1,'CSV exporta solo lo consultado');
    [$status,$body]=apRequest('agenda-export&date=2026-11-12&mode=week&employee=501&status=cancelled');
    apCheck($status===200 && substr_count(rtrim($body),"\r\n")===0,'CSV de período vacío contiene solo encabezados');
    apCheck(apRequest('agenda-export&employee=999999')[0]===422 && apRequest('agenda-export&status=invalid')[0]===422,'Exportación rechaza filtros inválidos');
    [$status,$body]=apRequest('agenda-print&date=2026-11-20&mode=week&employee=501&status=confirmed');
    apCheck($status===200 && str_contains($body,'agenda-print-table') && str_contains($body,'20/11/2026') && str_contains($body,'=HYPERLINK(&quot;http://x.test&quot;)') && !str_contains($body,'privada'),'Vista imprimible muestra turnos escapados y sin notas');
    apCheck(str_contains($body,'agenda-export&amp;mode=week&amp;employee=501&amp;status=confirmed&amp;date=2026-11-20'),'Vista imprimible conserva filtros hacia el CSV');
    [$status,$body]=apRequest('agenda-print&date=2026-11-12&mode=week&employee=501&status=cancelled');
    apCheck($status===200 && str_contains($body,'No hay turnos para este período y estos filtros.'),'Vista imprimible vacía informa alcance');
    apCheck(apRequest('agenda-print&status=invalid')[0]===422,'Vista imprimible rechaza filtros inválidos');
    $cookiesBackup=$cookies; $cookies=[];
    apCheck(apRequest('agenda-export')[0]===303 && apRequest('agenda-print')[0]===303,'Exportación e impresión exigen sesión');
    $cookies=$cookiesBackup;
    echo "\n$checks pruebas de turnos aprobadas.\n";
} finally {
    if(db()->inTransaction()) db()->rollBack();
    if(is_resource($server)) { proc_terminate($server); proc_close($server); }
    if(preg_match('/^bloome_test_appointments_[a-f0-9]{10}$/D',$database)) $root->exec("DROP DATABASE IF EXISTS `$database`");
}
