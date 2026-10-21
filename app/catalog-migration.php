<?php
declare(strict_types=1);

/**
 * Lleva una base existente al catálogo de servicios de Configuración (idempotente).
 * Agrega a `services` la referencia, categoría, impuesto y orden. Las instalaciones nuevas ya lo traen en database/schema.sql.
 * Antes de usarla: respaldar la base (ALTER TABLE confirma implícitamente).
 */
function migrateServiceCatalog(): void
{
    $pdo = db();
    $hasColumn = static fn(string $column): bool => (bool)query('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', ['services', $column])->fetchColumn();
    $hasIndex = static fn(string $index): bool => (bool)query('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?', ['services', $index])->fetchColumn();
    $hasConstraint = static fn(string $name): bool => (bool)query('SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?', ['services', $name])->fetchColumn();

    // La tabla de categorías se toma del esquema para no duplicar su definición.
    foreach (explode(';', (string)file_get_contents(ROOT . '/database/schema.sql')) as $statement) {
        if (preg_match('/^\s*(?:--[^\n]*\n\s*)*CREATE TABLE IF NOT EXISTS service_categories\b/', $statement)) $pdo->exec($statement);
    }
    if (!$hasColumn('reference')) $pdo->exec('ALTER TABLE services ADD COLUMN reference VARCHAR(50) NULL AFTER id');
    if (!$hasColumn('category_id')) $pdo->exec('ALTER TABLE services ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER reference');
    if (!$hasColumn('tax_percentage')) $pdo->exec('ALTER TABLE services ADD COLUMN tax_percentage DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER price');
    if (!$hasColumn('sort_order')) $pdo->exec('ALTER TABLE services ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER tax_percentage');
    if (!$hasConstraint('valid_service_tax')) $pdo->exec('ALTER TABLE services ADD CONSTRAINT valid_service_tax CHECK (tax_percentage BETWEEN 0 AND 100)');
    if (!$hasConstraint('fk_services_category')) $pdo->exec('ALTER TABLE services ADD CONSTRAINT fk_services_category FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE RESTRICT');
    if (!$hasIndex('unique_services_reference')) $pdo->exec('ALTER TABLE services ADD UNIQUE KEY unique_services_reference (reference)');
    if (!$hasIndex('idx_services_category')) $pdo->exec('ALTER TABLE services ADD INDEX idx_services_category (category_id)');
    if (!$hasIndex('idx_services_order')) $pdo->exec('ALTER TABLE services ADD INDEX idx_services_order (sort_order, name)');
}
