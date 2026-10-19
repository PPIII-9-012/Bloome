<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$sqlFile = ROOT . '/database/migrations/010-schedules-vacations-holidays.sql';
if (!is_file($sqlFile)) {
    fwrite(STDERR, "Archivo de migración no encontrado: $sqlFile\n");
    exit(1);
}

$pdo = db();
$sql = file_get_contents($sqlFile);
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $stmt) {
    if ($stmt === '') continue;
    try {
        $pdo->exec($stmt);
    } catch (PDOException $e) {
        fwrite(STDERR, "Error ejecutando migración: " . $e->getMessage() . "\n");
        exit(1);
    }
}

require ROOT . '/app/break-migration.php';
migrateScheduleBreaks();

// Inicializar horario general del centro si está vacío (Lunes a Viernes 08:00 a 20:00, Sábados 09:00 a 14:00, Domingos cerrado)
$existing = (int)$pdo->query('SELECT COUNT(*) FROM business_schedules')->fetchColumn();
if ($existing === 0) {
    $defaults = [
        1 => ['08:00:00', '20:00:00', 1],
        2 => ['08:00:00', '20:00:00', 1],
        3 => ['08:00:00', '20:00:00', 1],
        4 => ['08:00:00', '20:00:00', 1],
        5 => ['08:00:00', '20:00:00', 1],
        6 => ['09:00:00', '14:00:00', 1],
        7 => [null, null, 0],
    ];
    $insert = $pdo->prepare('INSERT INTO business_schedules (day_of_week, start_time, end_time, is_open) VALUES (?, ?, ?, ?)');
    foreach ($defaults as $day => [$start, $end, $open]) {
        $insert->execute([$day, $start, $end, $open]);
    }
}

echo "Migración 010 (horarios, vacaciones y feriados) aplicada correctamente.\n";
