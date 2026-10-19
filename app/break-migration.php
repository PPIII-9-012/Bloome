<?php
declare(strict_types=1);

function migrateScheduleBreaks(): void {
    // Add both nullable columns atomically. Existing schedules remain continuous.
    $columns=query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employee_schedules' AND COLUMN_NAME IN ('break_start','break_end')")->fetchAll(PDO::FETCH_COLUMN);
    if (!$columns) db()->exec('ALTER TABLE employee_schedules ADD break_start TIME NULL, ADD break_end TIME NULL');
    elseif (count($columns)!==2) throw new RuntimeException('Migración de descansos incompleta: revisar columnas antes de continuar.');
}
