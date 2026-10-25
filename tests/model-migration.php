<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT . '/app/employee-migration.php';
require ROOT . '/app/model-migration.php';
$database = 'bloome_test_model_' . bin2hex(random_bytes(5));
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$password = getenv('MYSQL_ROOT_PASSWORD') ?: '';
$root = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", 'root', $password, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$checks = 0;
$freshDatabase = $database . '_fresh';
function modelCheck(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException($label);
    $checks++; echo "OK: $label\n";
}
try {
    $root->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    putenv('DB_DATABASE='.$database); putenv('DB_USERNAME=root'); putenv('DB_PASSWORD='.$password);
    foreach (explode(';', file_get_contents(ROOT.'/tests/fixtures/schema-legacy.sql')) as $sql) {
        if (trim($sql) !== '') db()->exec($sql);
    }
    query("INSERT INTO users (id,name,email,password_hash,role,active) VALUES
        (1,'Activo','active@example.test','unused','admin',1),
        (2,'Histórico','inactive@example.test','unused','professional',0)");
    query("INSERT INTO employees (id,user_id,active) VALUES (501,1,1),(702,2,0)");
    query("INSERT INTO clients (id,first_name,last_name) VALUES (1,'QA','Historia')");
    query("INSERT INTO services (id,name,duration_minutes,price) VALUES (1,'Servicio A',30,100),(2,'Servicio B',20,200)");
    query("INSERT INTO appointments (id,client_id,employee_id,service_id,starts_at,ends_at,status,notes) VALUES
        (11,1,1,1,'2026-01-01 09:00','2026-01-01 09:30','completed','Conservar A'),
        (12,1,2,2,'2026-01-01 10:00','2026-01-01 10:30','cancelled','Conservar B')");
    $before = query('SELECT id,client_id,starts_at,ends_at,status,notes,created_at FROM appointments ORDER BY id')->fetchAll();
    migrateEmployees();
    // An interrupted preparation step must be safe to resume.
    db()->exec('ALTER TABLE appointments ADD employee_id_model BIGINT UNSIGNED NULL');
    query("INSERT INTO employee_schedules(user_id,day_of_week,start_time,end_time,is_working) VALUES (1,1,'09:00','18:00',1)");
    $scheduleBefore=query('SELECT id,user_id,day_of_week,start_time,end_time,is_working,created_at FROM employee_schedules')->fetchAll();
    migrateAppointmentModel();
    modelCheck((int)query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employee_schedules' AND COLUMN_NAME IN ('break_start','break_end')")->fetchColumn() === 2, 'Migración agrega campos de descanso al esquema anterior');
    modelCheck(query('SELECT id,user_id,day_of_week,start_time,end_time,is_working,created_at FROM employee_schedules')->fetchAll()===$scheduleBefore && query('SELECT break_start FROM employee_schedules')->fetchColumn()===null,'Migración conserva jornada previa sin inventar descanso');
    query("UPDATE employee_schedules SET break_start='13:00',break_end='15:00'");
    modelCheck(appointmentEmployeeTarget() === 'employees', 'La FK apunta a employees');
    modelCheck(query('SELECT id,client_id,starts_at,ends_at,status,notes,created_at FROM appointments ORDER BY id')->fetchAll() === $before, 'Conserva IDs, fechas, estados, notas e historial inactivo');
    modelCheck(array_map('intval', query('SELECT employee_id FROM appointments ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)) === [501,702], 'Convierte IDs distintos de usuario y empleado');
    modelCheck(array_map('intval', query('SELECT legacy_employee_user_id FROM appointments ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)) === [1,2], 'Conserva referencia de usuario anterior');
    modelCheck((int)query('SELECT COUNT(*) FROM appointment_services')->fetchColumn() === 2, 'Copia los servicios de todas las citas');
    $details = query('SELECT * FROM appointment_services ORDER BY id')->fetchAll();
    query('UPDATE services SET price=999,duration_minutes=90 WHERE id=1');
    migrateAppointmentModel();
    modelCheck(query('SELECT break_start FROM employee_schedules')->fetchColumn()==='13:00:00','Repetir migración conserva descansos configurados');
    modelCheck(query('SELECT * FROM appointment_services ORDER BY id')->fetchAll() === $details, 'Repetir no duplica ni modifica las instantáneas');
    query("INSERT INTO appointments (client_id,employee_id,starts_at,ends_at,status) VALUES (1,501,'2026-01-01 12:00','2026-01-01 13:00','missed')");
    $id = db()->lastInsertId();
    query('INSERT INTO appointment_services (appointment_id,service_id,price,duration_minutes) VALUES (?,1,150,30),(?,2,250,20)', [$id,$id]);
    $list = appointmentsBetween('2026-01-01','2026-01-02');
    modelCheck(count($list) === 3 && $list[2]['service_name'] === 'Servicio A · Servicio B', 'Agenda muestra varios servicios sin duplicar la cita');
    modelCheck($list[1]['employee_name'] === 'Histórico', 'Agenda conserva profesionales inactivos');
    modelCheck(count(appointmentsBetween('2026-01-01','2026-01-02',2)) === 2, 'Límite del dashboard cuenta citas');
    modelCheck(appointmentsBetween('2030-01-01','2030-01-02') === [], 'Intervalo vacío funciona');
    $rejected = false;
    try { query("INSERT INTO appointments (client_id,employee_id,starts_at,ends_at) VALUES (1,702,'2026-02-01 09:00','2026-02-01 10:00')"); }
    catch (PDOException $e) { if ($e->getCode() !== '45000') throw $e; $rejected=true; }
    modelCheck($rejected, 'Trigger actualizado bloquea al empleado inactivo por su ID canónico');
    $rejected = false;
    try { query("UPDATE appointments SET status='confirmed' WHERE id=12"); }
    catch (PDOException $e) { if ($e->getCode() !== '45000') throw $e; $rejected=true; }
    modelCheck($rejected, 'No permite reabrir cita cancelada de empleado inactivo');
    $root->exec("CREATE DATABASE `$freshDatabase` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    putenv('DB_DATABASE='.$freshDatabase);
    putenv('ADMIN_EMAIL=fresh@example.test'); putenv('ADMIN_PASSWORD='.bin2hex(random_bytes(16)));
    $fresh = new PDO("mysql:host=$host;port=$port;dbname=$freshDatabase;charset=utf8mb4", 'root', $password, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    foreach ([['bin/install.php'],['bin/install.php'],['bin/seed-demo.php','--confirm']] as $args) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, ...$args], [0=>['pipe','r'],1=>['file','php://temp','w'],2=>['file','php://temp','w']], $pipes, ROOT);
        if (!is_resource($process)) throw new RuntimeException('No se pudo ejecutar instalación aislada');
        fclose($pipes[0]);
        modelCheck(proc_close($process) === 0, 'Instalación o demo canónica: '.implode(' ', $args));
    }
    modelCheck((int)$fresh->query('SELECT COUNT(*) FROM appointments')->fetchColumn() === 3 && (int)$fresh->query('SELECT COUNT(*) FROM appointment_services')->fetchColumn() === 3, 'Demo vincula cada cita con su detalle');
    $process = proc_open([PHP_BINARY,'bin/seed-demo.php','--confirm'], [0=>['pipe','r'],1=>['file','php://temp','w'],2=>['file','php://temp','w']], $pipes, ROOT);
    fclose($pipes[0]);
    modelCheck(proc_close($process) === 1 && (int)$fresh->query('SELECT COUNT(*) FROM appointments')->fetchColumn() === 3, 'Demo repetida se rechaza sin duplicar datos');
    echo "\n$checks pruebas de migración aprobadas.\n";
} finally {
    if (preg_match('/^bloome_test_model_[a-f0-9]{10}$/D', $database)) $root->exec("DROP DATABASE IF EXISTS `$database`");
    if (preg_match('/^bloome_test_model_[a-f0-9]{10}_fresh$/D', $freshDatabase)) $root->exec("DROP DATABASE IF EXISTS `$freshDatabase`");
}
