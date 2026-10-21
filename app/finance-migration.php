<?php
declare(strict_types=1);

/**
 * Lleva una base existente al modelo financiero de la tarjeta #14 (idempotente).
 * Las instalaciones nuevas ya lo traen en database/schema.sql.
 * Antes de usarla: respaldar la base y detener las escrituras (ALTER TABLE confirma implícitamente).
 */
function migrateFinanceModel(): void
{
    $pdo = db();
    $hasColumn = static fn(string $table, string $column): bool => (bool)query('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', [$table, $column])->fetchColumn();
    $hasIndex = static fn(string $table, string $index): bool => (bool)query('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?', [$table, $index])->fetchColumn();
    $hasConstraint = static fn(string $table, string $name): bool => (bool)query('SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?', [$table, $name])->fetchColumn();

    // Tablas nuevas, tomadas del esquema para no duplicar su definición.
    $schema = file_get_contents(ROOT . '/database/schema.sql');
    foreach (explode(';', $schema) as $statement) {
        if (preg_match('/^\s*(?:--[^\n]*\n\s*)*CREATE TABLE IF NOT EXISTS (sales|sale_items|cash_sessions|cash_movements)\b/', $statement)) $pdo->exec($statement);
    }

    // Categorías de gasto y clave de operación del libro de caja (tarjeta #19).
    $pdo->exec("INSERT IGNORE INTO expense_categories (name) VALUES ('Insumos'), ('Servicios'), ('Alquiler'), ('Sueldos y comisiones'), ('Limpieza y mantenimiento'), ('Otros')");
    if (!$hasColumn('cash_movements', 'category_id')) $pdo->exec('ALTER TABLE cash_movements ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER reverses_id');
    if (!$hasColumn('cash_movements', 'idempotency_key')) $pdo->exec('ALTER TABLE cash_movements ADD COLUMN idempotency_key CHAR(64) NULL AFTER category_id');
    if (!$hasIndex('cash_movements', 'cash_movements_idempotency')) $pdo->exec('ALTER TABLE cash_movements ADD UNIQUE KEY cash_movements_idempotency (idempotency_key)');
    if (!$hasConstraint('cash_movements', 'cash_movements_category')) $pdo->exec('ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_category FOREIGN KEY (category_id) REFERENCES expense_categories(id)');

    if (!$hasColumn('sales', 'notes')) $pdo->exec('ALTER TABLE sales ADD COLUMN notes VARCHAR(500) NULL AFTER total');

    foreach ([
        'kind' => "ADD COLUMN kind ENUM('payment','deposit') NOT NULL DEFAULT 'payment' AFTER status",
        'idempotency_key' => 'ADD COLUMN idempotency_key CHAR(64) NULL AFTER kind',
        'created_by' => 'ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER idempotency_key',
        'refunded_at' => 'ADD COLUMN refunded_at TIMESTAMP NULL AFTER created_at',
        'refunded_by' => 'ADD COLUMN refunded_by BIGINT UNSIGNED NULL AFTER refunded_at',
    ] as $column => $alter) if (!$hasColumn('payments', $column)) $pdo->exec("ALTER TABLE payments $alter");
    if (!$hasIndex('payments', 'payments_idempotency')) $pdo->exec('ALTER TABLE payments ADD UNIQUE KEY payments_idempotency (idempotency_key)');
    if (!$hasConstraint('payments', 'payments_creator')) $pdo->exec('ALTER TABLE payments ADD CONSTRAINT payments_creator FOREIGN KEY (created_by) REFERENCES users(id)');
    if (!$hasConstraint('payments', 'payments_refunder')) $pdo->exec('ALTER TABLE payments ADD CONSTRAINT payments_refunder FOREIGN KEY (refunded_by) REFERENCES users(id)');

    if (!$hasColumn('payment_allocations', 'sale_id')) {
        $pdo->exec('ALTER TABLE payment_allocations MODIFY appointment_id BIGINT UNSIGNED NULL, ADD COLUMN sale_id BIGINT UNSIGNED NULL AFTER appointment_id, ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD COLUMN released_at TIMESTAMP NULL');
        $pdo->exec('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_sale FOREIGN KEY (sale_id) REFERENCES sales(id), ADD INDEX payment_allocations_sale (sale_id)');
        $pdo->exec('ALTER TABLE payment_allocations ADD CONSTRAINT valid_allocation_target CHECK ((appointment_id IS NULL) <> (sale_id IS NULL))');
    }
    // El modelo anterior limitaba un cobro a una asignación por cita; ahora puede aplicarse a varias ventas.
    if (!$hasIndex('payment_allocations', 'payment_allocations_payment')) $pdo->exec('ALTER TABLE payment_allocations ADD INDEX payment_allocations_payment (payment_id)');
    if ($hasIndex('payment_allocations', 'payment_id')) $pdo->exec('ALTER TABLE payment_allocations DROP INDEX payment_id');
}
