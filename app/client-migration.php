<?php
declare(strict_types=1);

/**
 * Agrega a `clients` el género (opcional) que usan las estadísticas por género (idempotente).
 * Las instalaciones nuevas ya lo traen en database/schema.sql. Antes de usarla: respaldar la base (ALTER TABLE confirma implícitamente).
 */
function migrateClientGender(): void
{
    $has = (bool)query('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', ['clients', 'gender'])->fetchColumn();
    if (!$has) db()->exec("ALTER TABLE clients ADD COLUMN gender ENUM('female','male','other') NULL AFTER birth_date");
}
