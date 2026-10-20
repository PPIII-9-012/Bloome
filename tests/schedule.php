<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$testDatabase='bloome_test_schedule_'.bin2hex(random_bytes(5));
$host=getenv('DB_HOST')?:'127.0.0.1'; $port=getenv('DB_PORT')?:'3306';
$rootPassword=getenv('MYSQL_ROOT_PASSWORD')?:'';
$root=new PDO("mysql:host=$host;port=$port;charset=utf8mb4",'root',$rootPassword,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$server=null; $pipes=[];
$webPort=random_int(22001,25000);
$origin='http://127.0.0.1:'.$webPort;
$cookies = [];
$checks = 0;

function req(string $page, array $fields = [], bool $post = false): array {
    global $origin, $cookies;
    $headers = ['Content-Type: application/x-www-form-urlencoded'];
    if ($cookies) {
        $headers[] = 'Cookie: ' . implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($cookies), $cookies));
    }
    $context = stream_context_create([
        'http' => [
            'method' => $post ? 'POST' : 'GET',
            'header' => implode("\r\n", $headers),
            'content' => $post ? http_build_query($fields) : '',
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 10
        ]
    ]);
    $body = file_get_contents($origin . '/?page=' . $page, false, $context);
    $responseHeaders = http_get_last_response_headers();
    preg_match('/\s(\d{3})\s/', $responseHeaders[0], $status);
    foreach ($responseHeaders as $header) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $match)) {
            $cookies[$match[1]] = $match[2];
        }
    }
    $location='';
    foreach($responseHeaders as $header) if(str_starts_with($header,'Location: ')) $location=substr($header,10);
    return [(int)$status[1], $body, $location];
}

function extractToken(string $body): string {
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $match);
    return $match[1] ?? '';
}

function testAssert(bool $condition, string $label): void {
    global $checks;
    if (!$condition) {
        throw new RuntimeException("Fallo en prueba: $label");
    }
    echo "OK: $label\n";
    $checks++;
}

try {
    $root->exec("CREATE DATABASE `$testDatabase` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    putenv('DB_DATABASE='.$testDatabase); putenv('DB_USERNAME=root'); putenv('DB_PASSWORD='.$rootPassword);
    putenv('ADMIN_EMAIL=schedule-admin@example.test'); putenv('ADMIN_PASSWORD='.bin2hex(random_bytes(12)));
    foreach(explode(';',file_get_contents(ROOT.'/database/schema.sql')) as $sql) if(trim($sql)!=='') db()->exec($sql);
    query("INSERT INTO users(name,email,password_hash,role) VALUES (?,?,?,'admin')",['QA admin',getenv('ADMIN_EMAIL'),password_hash(getenv('ADMIN_PASSWORD'),PASSWORD_DEFAULT)]);
    $server=proc_open([PHP_BINARY,'-S',"127.0.0.1:$webPort",'-t',ROOT.'/public',ROOT.'/public/router.php'],[0=>['pipe','r'],1=>['file','php://temp','w'],2=>['file','php://temp','w']],$pipes,ROOT);
    if(!is_resource($server)) throw new RuntimeException('No se pudo iniciar el servidor aislado.');
    for($i=0;$i<50;$i++) { $socket=@fsockopen('127.0.0.1',$webPort); if($socket) { fclose($socket); break; } usleep(100000); }
    // 1. Acceso a festivos sin sesión redirige a login
    [$status] = req('holidays');
    testAssert($status === 303, 'Festivos protegido sin sesión');

    // 2. Login como admin
    [, $loginBody] = req('login');
    [$status] = req('login', ['email' => getenv('ADMIN_EMAIL'), 'password' => getenv('ADMIN_PASSWORD'), 'csrf' => extractToken($loginBody)], true);
    testAssert($status === 303, 'Login exitoso como administrador');

    // 3. Admin accede a festivos
    [$status, $holidaysBody] = req('holidays');
    testAssert($status === 200 && str_contains($holidaysBody, 'Festivos y Feriados'), 'Admin accede a vista de Festivos');

    // 4. Agregar feriado de prueba
    $token = extractToken($holidaysBody);
    $testHolidayDate = '2026-12-25';
    [$status] = req('holiday-new', ['csrf' => $token, 'date' => $testHolidayDate, 'description' => 'Navidad Bloome'], true);
    testAssert($status === 303, 'Creación de feriado redirige');

    $holidayInDb = query('SELECT * FROM holidays WHERE holiday_date = ?', [$testHolidayDate])->fetch();
    testAssert((bool)$holidayInDb && $holidayInDb['description'] === 'Navidad Bloome', 'Feriado persistido en base de datos');

    // 5. Rechaza duplicado de feriado en la misma fecha
    [, $bodyAfter] = req('holidays');
    [$status] = req('holiday-new', ['csrf' => extractToken($bodyAfter), 'date' => $testHolidayDate, 'description' => 'Duplicado'], true);
    testAssert($status === 422, 'Feriado duplicado devuelve error de validación');

    // 6. Admin accede a Horarios del centro
    [$status, $schedBody] = req('schedule');
    testAssert($status === 200 && str_contains($schedBody, 'Configuración del Horario'), 'Admin accede a horario de atención');

    // 7. Guardar horario de atención del centro
    $schedToken = extractToken($schedBody);
    $bizDays = [
        1 => ['is_open' => '1', 'start_time' => '08:30', 'end_time' => '19:30'],
        2 => ['is_open' => '1', 'start_time' => '08:30', 'end_time' => '19:30'],
        3 => ['is_open' => '1', 'start_time' => '08:30', 'end_time' => '19:30'],
        4 => ['is_open' => '1', 'start_time' => '08:30', 'end_time' => '19:30'],
        5 => ['is_open' => '1', 'start_time' => '08:30', 'end_time' => '19:30'],
        6 => ['is_open' => '1', 'start_time' => '09:00', 'end_time' => '13:00'],
        7 => ['is_open' => '0', 'start_time' => '', 'end_time' => ''],
    ];
    [$status] = req('schedule', ['csrf' => $schedToken, 'days' => $bizDays], true);
    testAssert($status === 303, 'Guardado de horario del centro redirige');

    $savedMon = query('SELECT * FROM business_schedules WHERE day_of_week = 1')->fetch();
    testAssert($savedMon['start_time'] === '08:30:00' && $savedMon['end_time'] === '19:30:00', 'Horario de lunes persistido en MySQL');

    // 8. Crear empleado de prueba para probar vacaciones y horarios individuales
    $empEmail = 'qa-sched-' . bin2hex(random_bytes(4)) . '@bloome.test';
    [, $newEmpBody] = req('employee-new');
    $empFields = [
        'csrf' => extractToken($newEmpBody),
        'name' => 'Profesional Horarios',
        'email' => $empEmail,
        'role' => 'professional',
        'active' => '1',
        'appointment_color' => '#147D71',
        'password' => 'BloomePass12345!'
    ];
    req('employee-new', $empFields, true);
    $empUser = query('SELECT id FROM users WHERE email = ?', [$empEmail])->fetch();
    testAssert((bool)$empUser, 'Empleado de prueba creado');
    $empId = (int)$empUser['id'];

    // 9. Guardar horario de trabajo individual del empleado
    [, $profBody] = req("employee&id=$empId");
    testAssert(str_contains($profBody, 'Vacaciones y ausencias'), 'Perfil muestra sección de vacaciones');
    testAssert(str_contains($profBody, 'Configuración del Horario'), 'Perfil muestra sección de horarios');

    $empScheduleData = [
        1 => ['is_working' => '1', 'start_time' => '10:00', 'end_time' => '16:00'],
        2 => ['is_working' => '1', 'start_time' => '10:00', 'end_time' => '16:00'],
        3 => ['is_working' => '1', 'start_time' => '10:00', 'end_time' => '16:00'],
        4 => ['is_working' => '1', 'start_time' => '10:00', 'end_time' => '16:00'],
        5 => ['is_working' => '1', 'start_time' => '10:00', 'end_time' => '16:00'],
        6 => ['is_working' => '0', 'start_time' => '', 'end_time' => ''],
        7 => ['is_working' => '0', 'start_time' => '', 'end_time' => ''],
    ];
    [$status, , $savedLocation] = req('employee-schedule-save', ['csrf' => extractToken($profBody), 'user_id' => $empId, 'schedule' => $empScheduleData], true);
    testAssert($status === 303 && $savedLocation === url('employee',['id'=>$empId]), 'Horario guardado redirige a una ruta válida');

    $savedEmpMon = query('SELECT * FROM employee_schedules WHERE user_id = ? AND day_of_week = 1', [$empId])->fetch();
    testAssert($savedEmpMon['start_time'] === '10:00:00' && $savedEmpMon['end_time'] === '16:00:00', 'Horario de empleado persistido en MySQL');

    // 10. Añadir período de vacaciones al empleado
    [, $profBody2] = req("employee&id=$empId");
    $vacStarts = '2026-11-10';
    $vacEnds = '2026-11-20';
    [$status] = req('employee-vacation-new', [
        'csrf' => extractToken($profBody2),
        'user_id' => $empId,
        'description' => 'Vacaciones Anuales',
        'starts_at' => $vacStarts,
        'ends_at' => $vacEnds
    ], true);
    testAssert($status === 303, 'Alta de vacaciones redirige');

    $vacationRow = query('SELECT * FROM employee_vacations WHERE user_id = ? AND starts_at = ?', [$empId, $vacStarts])->fetch();
    testAssert((bool)$vacationRow && $vacationRow['description'] === 'Vacaciones Anuales', 'Vacaciones persistidas en MySQL');

    // 11. Validador de disponibilidad: rechaza citas en día feriado
    $errHoliday = checkAppointmentAvailability($empId, '2026-12-25 11:00:00', '2026-12-25 12:00:00');
    testAssert($errHoliday !== null && str_contains($errHoliday, 'feriados'), 'Disponibilidad rechaza fecha de feriado');

    // 12. Validador de disponibilidad: rechaza citas en período de vacaciones del profesional
    $errVacation = checkAppointmentAvailability($empId, '2026-11-15 11:00:00', '2026-11-15 12:00:00');
    testAssert($errVacation !== null && str_contains($errVacation, 'vacaciones'), 'Disponibilidad rechaza fecha de vacaciones del empleado');

    // 13. Validador de disponibilidad: rechaza citas fuera del horario del profesional (atiende hasta las 16:00)
    // 2026-11-02 es Lunes
    $errHour = checkAppointmentAvailability($empId, '2026-11-02 17:00:00', '2026-11-02 18:00:00');
    testAssert($errHour !== null && str_contains($errHour, 'no atiende'), 'Disponibilidad rechaza hora fuera del turno del profesional');

    // 14. Validador de disponibilidad: aprueba cita dentro del horario laboral, sin feriado ni vacaciones
    $okAvailability = checkAppointmentAvailability($empId, '2026-11-02 11:00:00', '2026-11-02 12:00:00');
    testAssert($okAvailability === null, 'Disponibilidad aprueba horario regular válido');

    // 15. Eliminar feriado
    [$status] = req('holiday-delete', ['csrf' => extractToken($profBody2), 'id' => (int)$holidayInDb['id']], true);
    testAssert($status === 303, 'Eliminar feriado redirige');
    $checkDeleted = query('SELECT COUNT(*) FROM holidays WHERE id = ?', [(int)$holidayInDb['id']])->fetchColumn();
    testAssert((int)$checkDeleted === 0, 'Feriado eliminado de la base de datos');

    $before=query('SELECT * FROM business_schedules ORDER BY day_of_week')->fetchAll();
    $bad=$bizDays; $bad[1]['start_time']='07:00'; $bad[7]=['is_open'=>'1','start_time'=>['malicioso'],'end_time'=>'25:00'];
    req('schedule',['csrf'=>$schedToken,'days'=>$bad],true);
    testAssert(query('SELECT * FROM business_schedules ORDER BY day_of_week')->fetchAll()===$before,'Horario inválido no guarda parcialmente días anteriores');
    [$status]=req('schedule',['csrf'=>$schedToken,'days'=>'cadena'],true);
    testAssert($status===422 && query('SELECT * FROM business_schedules ORDER BY day_of_week')->fetchAll()===$before,'Estructura manipulada no produce error 500 ni modifica horarios');
    [$status]=req('employee-schedule-save',['user_id'=>$empId,'schedule'=>$empScheduleData],true);
    testAssert($status===419,'Guardar horario exige CSRF');
    [$status]=req('employee-schedule-save',['csrf'=>$schedToken,'user_id'=>99999999,'schedule'=>$empScheduleData],true);
    testAssert($status===404,'Empleado inexistente rechazado antes de escribir');
    foreach([['2026-02-30 10:00','2026-02-30 11:00'],['2026-11-02 12:00','2026-11-02 11:00'],['2026-11-02 12:00','2026-11-02 12:00'],['2026-11-02 12:00','2026-11-03 13:00']] as [$start,$end]) testAssert(checkAppointmentAvailability($empId,$start,$end)!==null,'Disponibilidad rechaza intervalo inválido '.$start.' / '.$end);
    testAssert(checkAppointmentAvailability($empId,'2026-11-02 10:00','2026-11-02 16:00')===null,'Límites exactos del horario permitidos');
    query('UPDATE users SET active=0 WHERE id=?',[$empId]);
    testAssert(checkAppointmentAvailability($empId,'2026-11-02 11:00','2026-11-02 12:00')!==null,'Disponibilidad rechaza empleado inactivo');
    query('UPDATE users SET active=1 WHERE id=?',[$empId]);
    query('DELETE FROM employee_schedules WHERE user_id=? AND day_of_week=1',[$empId]);
    testAssert(checkAppointmentAvailability($empId,'2026-11-02 11:00','2026-11-02 12:00')!==null,'Sin horario individual no hay disponibilidad implícita');
    saveEmployeeSchedule($empId,$empScheduleData);
    query('DELETE FROM business_schedules WHERE day_of_week=1');
    testAssert(checkAppointmentAvailability($empId,'2026-11-02 11:00','2026-11-02 12:00')!==null,'Sin horario del centro no hay disponibilidad implícita');
    saveBusinessSchedule($bizDays);
    [, $cleanProfile]=req('employee&id='.$empId);
    testAssert(!str_contains($cleanProfile,'onclick=') && !str_contains($cleanProfile,'<script>') && str_contains($cleanProfile,'<details'),'Formularios funcionan con CSP sin JavaScript inline');
    $split=$empScheduleData; $split[1]=['is_working'=>'1','start_time'=>'09:00','end_time'=>'18:00','break_start'=>'13:00','break_end'=>'15:00'];
    [$status]=req('employee-schedule-save',['csrf'=>$schedToken,'user_id'=>$empId,'schedule'=>$split],true);
    testAssert($status===303 && employeeSchedule($empId)[1]['break_start']==='13:00:00','Descanso guardado por HTTP');
    foreach([['12:00','13:00',true],['15:00','16:00',true],['12:30','13:30',false],['13:00','14:00',false],['14:30','15:30',false],['12:00','16:00',false]] as [$from,$to,$allowed]) {
        testAssert((checkAppointmentAvailability($empId,'2026-11-02 '.$from,'2026-11-02 '.$to)===null)===$allowed,'Jornada partida: '.$from.' a '.$to);
    }
    $saved=employeeSchedule($empId);
    foreach([['break_end'=>''],['break_start'=>''],['break_start'=>'16:00'],['break_start'=>'09:00'],['break_end'=>'18:00'],['break_start'=>'25:00'],['break_start'=>['13:00']],['break_end'=>'12:00']] as $invalid) {
        $bad=$split; $bad[1]=array_replace($bad[1],$invalid);
        [$status]=req('employee-schedule-save',['csrf'=>$schedToken,'user_id'=>$empId,'schedule'=>$bad],true);
        testAssert($status===422 && employeeSchedule($empId)===$saved,'Descanso inválido no guarda parcialmente: '.json_encode($invalid));
    }
    $continuous=$split; $continuous[1]['break_start']=''; $continuous[1]['break_end']='';
    saveEmployeeSchedule($empId,$continuous);
    testAssert(checkAppointmentAvailability($empId,'2026-11-02 13:00','2026-11-02 14:00')===null,'Eliminar descanso restaura jornada continua');
    $closed=$split; $closed[1]['is_working']='0'; saveEmployeeSchedule($empId,$closed);
    testAssert(employeeSchedule($empId)[1]['break_start']==='' && !employeeSchedule($empId)[1]['is_working'],'Día cerrado no conserva descanso');
    saveEmployeeSchedule($empId,$split);
    [, $splitBody]=req('employee&id='.$empId);
    testAssert(str_contains($splitBody,'[break_start]') && str_contains($splitBody,'13:00'),'Perfil muestra controles y descanso guardado');
    $oldForm=$split; unset($oldForm[1]['break_start'],$oldForm[1]['break_end']);
    saveEmployeeSchedule($empId,$oldForm);
    testAssert(employeeSchedule($empId)[1]['break_start']==='13:00:00','Formulario antiguo conserva descanso existente');
    $oldForm[1]['end_time']='14:00';
    try { saveEmployeeSchedule($empId,$oldForm); throw new RuntimeException('Se aceptó jornada incompatible'); }
    catch(DomainException $e) { testAssert($e->getCode()===422 && employeeSchedule($empId)[1]['end_time']==='18:00:00','Jornada incompatible no borra descanso ni guarda cambios'); }
    // 16. Logout y verificación de permisos denegados a usuario profesional
    [, $lastBody] = req('holidays');
    req('logout', ['csrf' => extractToken($lastBody)], true);

    [, $loginBodyProf] = req('login');
    req('login', ['email' => $empEmail, 'password' => 'BloomePass12345!', 'csrf' => extractToken($loginBodyProf)], true);

    [$status] = req('holidays');
    testAssert($status === 403, 'Profesional no tiene acceso a festivos (403)');

    [$status] = req('schedule');
    testAssert($status === 403, 'Profesional no tiene acceso a configuración de horarios (403)');

    [, $ownProfile]=req('profile'); $ownCsrf=extractToken($ownProfile);
    testAssert(!str_contains($ownProfile,'employee-schedule-save') && !str_contains($ownProfile,'employee-vacation-new'),'Profesional consulta disponibilidad sin controles administrativos');
    foreach(['employee-schedule-save','employee-vacation-new','employee-vacation-delete'] as $route) {
        [$status]=req($route,['csrf'=>$ownCsrf,'user_id'=>1,'schedule'=>$empScheduleData],true);
        testAssert($status===403,'Profesional no modifica disponibilidad por '.$route);
    }
    echo "\n$checks pruebas de Horarios, Vacaciones y Festivos aprobadas.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "\nFAIL: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    $failed = true;
} finally {
    if(is_resource($server)) { proc_terminate($server); foreach($pipes as $pipe) if(is_resource($pipe)) fclose($pipe); proc_close($server); }
    if(preg_match('/^bloome_test_schedule_[a-f0-9]{10}$/D',$testDatabase)) $root->exec("DROP DATABASE IF EXISTS `$testDatabase`");
}
exit(isset($failed)?1:0);
