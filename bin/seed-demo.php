<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (PHP_SAPI !== 'cli') exit;
// Opt-in only. All people and contact details below are fictional.
if (!in_array('--confirm', $argv, true)) {
    fwrite(STDERR, "Agrega --confirm para cargar ejemplos ficticios en la base configurada.\n");
    exit(1);
}
try {
    db()->beginTransaction();
    query("INSERT INTO seed_runs (name) VALUES ('demo-v1')");
    $employee = query('SELECT id FROM users WHERE role = ? ORDER BY id LIMIT 1', ['admin'])->fetchColumn();
    if (!$employee) throw new RuntimeException('Primero ejecutá bin/install.php.');
    $people = [['Lucía', 'Ejemplo', 'lucia@example.test'], ['Martina', 'Demo', 'martina@example.test'], ['Sofía', 'Prueba', 'sofia@example.test'], ['Camila', 'Muestra', 'camila@example.test']];
    $ids = [];
    foreach ($people as [$first, $last, $email]) {
        query('INSERT INTO clients (first_name,last_name,email,notes) VALUES (?,?,?,?)', [$first,$last,$email,'Registro ficticio para demostración.']);
        $ids[] = db()->lastInsertId();
    }
    query('INSERT INTO services (name,duration_minutes,price) VALUES (?,?,?)', ['Limpieza facial',60,'25000.00']);
    $service = db()->lastInsertId();
    foreach ([10,12,15] as $index => $hour) {
        query('INSERT INTO appointments (client_id,employee_id,service_id,starts_at,ends_at,status,notes) VALUES (?,?,?,?,?,?,?)', [$ids[$index],$employee,$service,date('Y-m-d') . " $hour:00:00",date('Y-m-d') . ' ' . ($hour+1) . ':00:00',$index===2?'pending':'confirmed','Cita ficticia de demostración.']);
    }
    db()->commit();
    echo "Datos ficticios cargados para hoy. No se repiten al ejecutar otra vez.\n";
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    fwrite(STDERR, "No se cargaron datos. La demo puede estar ya instalada; verificá instalación y conexión.\n");
    exit(1);
}
