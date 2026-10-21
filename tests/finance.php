<?php
declare(strict_types=1);
// Pruebas del modelo financiero (#14). Crea y elimina su propia base; no toca la base configurada.
require dirname(__DIR__) . '/app/bootstrap.php';
require ROOT . '/app/finance-migration.php';
if (isset($argv[1]) && $argv[1] === '--worker') { // cobro concurrente con la misma clave
    try { [$id, $replayed] = recordPayment((int)$argv[2], ['sale_id' => $argv[3], 'amount' => '100', 'method' => 'cash', 'idempotency_key' => 'carrera-0001']); echo $replayed ? "replayed:$id" : "created:$id"; }
    catch (Throwable $e) { echo 'error:' . $e->getMessage(); }
    exit;
}

$checks = 0;
function checkFinance(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException("FALLÓ: $label"); echo "OK: $label\n"; $checks++; }
function denied(callable $fn, int $code): bool { try { $fn(); return false; } catch (DomainException $e) { return $e->getCode() === $code; } }
function sqlRejected(callable $fn): bool { try { $fn(); return false; } catch (PDOException $e) { return $e->getCode() === '23000' || $e->getCode() === 'HY000' && str_contains($e->getMessage(), 'Check constraint'); } }

// ---- Cálculo puro ----
checkFinance(moneyToCents('1234.5') === 123450 && moneyToCents('1234,50') === 123450 && moneyToCents('0') === 0, 'importes con punto o coma a centavos');
checkFinance(moneyToCents('1.234,50') === null && moneyToCents('-5') === null && moneyToCents('1.234') === null && moneyToCents('abc') === null && moneyToCents('') === null, 'importes inválidos rechazados');
checkFinance(centsToDecimal(5) === '0.05' && centsToDecimal(123450) === '1234.50' && decimalToCents('1234.50') === 123450 && decimalToCents('0.1') === 10, 'conversión DECIMAL <-> centavos');
checkFinance(moneyFormat(123450) === '$ 1.234,50', 'formato de pantalla');
checkFinance(roundedDiv(5, 10) === 1 && roundedDiv(4, 10) === 0 && roundedDiv(15, 10) === 2, 'redondeo mitad hacia arriba');
checkFinance(rateToBasisPoints('21') === 2100 && rateToBasisPoints('10,5') === 1050 && rateToBasisPoints('100') === 10000 && rateToBasisPoints('100.01') === null && rateToBasisPoints('-1') === null, 'porcentajes a puntos básicos');

$line = calcSaleLine(100000, 3, ['type' => 'percent', 'value' => 1000], 2100, false); // 3 x 1000, 10 % desc., 21 % agregado
checkFinance($line === ['gross' => 300000, 'discount' => 30000, 'net' => 270000, 'tax' => 56700, 'total' => 326700], 'descuento % e impuesto agregado');
$line = calcSaleLine(100000, 3, ['type' => 'percent', 'value' => 1000], 2100, true); // igual, impuesto incluido
checkFinance($line === ['gross' => 300000, 'discount' => 30000, 'net' => 223140, 'tax' => 46860, 'total' => 270000], 'impuesto incluido: el total no cambia y net + tax = total');
$line = calcSaleLine(5, 1, ['type' => 'none'], 1000, false); // 0,05 + 10 % = 0,005 -> sube a 0,01
checkFinance($line['tax'] === 1 && $line['total'] === 6, 'el impuesto redondea a la mitad hacia arriba');
$line = calcSaleLine(10000, 1, ['type' => 'amount', 'value' => 2500], 0, true);
checkFinance($line['total'] === 7500 && $line['tax'] === 0 && $line['net'] === 7500, 'descuento por importe sin impuesto');
checkFinance(denied(fn() => calcSaleLine(10000, 1, ['type' => 'amount', 'value' => 10001], 0, true), 422), 'descuento mayor que la línea rechazado');
checkFinance(denied(fn() => calcSaleLine(10000, 1, ['type' => 'percent', 'value' => 10001], 0, true), 422) && denied(fn() => calcSaleLine(10000, 0, ['type' => 'none'], 0, true), 422) && denied(fn() => calcSaleLine(10000, 1, ['type' => 'otro'], 0, true), 422), 'porcentaje, cantidad y tipo inválidos rechazados');
$sum = sumSaleLines([calcSaleLine(33333, 1, ['type' => 'none'], 2100, true), calcSaleLine(33333, 1, ['type' => 'none'], 2100, true), calcSaleLine(33334, 1, ['type' => 'none'], 2100, true)]);
checkFinance($sum['total'] === 100000 && $sum['net'] + $sum['tax'] === $sum['total'], 'los totales son la suma de líneas y concilian');
checkFinance(collectionStatus(1000, 0) === 'unpaid' && collectionStatus(1000, 400) === 'partial' && collectionStatus(1000, 1000) === 'paid', 'estado de cobro');

// ---- Base de datos ----
$database = 'bloome_test_finance_' . bin2hex(random_bytes(5));
$host = getenv('DB_HOST') ?: '127.0.0.1'; $port = getenv('DB_PORT') ?: '3306'; $rootPassword = getenv('MYSQL_ROOT_PASSWORD') ?: '';
$root = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", 'root', $rootPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
try {
    $root->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    putenv('DB_DATABASE=' . $database); putenv('DB_USERNAME=root'); putenv('DB_PASSWORD=' . $rootPassword);
    foreach (explode(';', file_get_contents(ROOT . '/database/schema.sql')) as $statement) if (trim($statement) !== '') db()->exec($statement);
    foreach (['admin' => 'admin', 'reception' => 'reception', 'professional' => 'professional'] as $name => $role) {
        query('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)', [$name, "$name@test.local", 'x', $role]);
    }
    [$admin, $reception, $professional] = array_map('intval', array_column(query('SELECT id FROM users ORDER BY id')->fetchAll(), 'id'));
    query('INSERT INTO employees (user_id) VALUES (?)', [$reception]);
    $employee = (int)db()->lastInsertId();
    foreach ([['Ana', 'Uno'], ['Beto', 'Dos']] as $c) query('INSERT INTO clients (first_name,last_name) VALUES (?,?)', $c);
    [$clientA, $clientB] = array_map('intval', array_column(query('SELECT id FROM clients ORDER BY id')->fetchAll(), 'id'));
    query("INSERT INTO services (name,duration_minutes,price) VALUES ('Limpieza facial',60,1000.00),('Masaje',30,500.00)");
    [$facial, $masaje] = array_map('intval', array_column(query('SELECT id FROM services ORDER BY id')->fetchAll(), 'id'));
    query("INSERT INTO services (name,duration_minutes,price,active) VALUES ('Discontinuado',30,100.00,0)");
    $inactive = (int)db()->lastInsertId();
    $slot = fn(int $hour, int $client) => (function () use ($hour, $client, $employee) {
        query('INSERT INTO appointments (client_id,employee_id,starts_at,ends_at) VALUES (?,?,?,?)', [$client, $employee, sprintf('2026-11-02 %02d:00:00', $hour), sprintf('2026-11-02 %02d:30:00', $hour)]);
        return (int)db()->lastInsertId();
    })();
    $appointment = $slot(10, $clientA);
    $other = $slot(11, $clientB);
    $cashOf = fn() => cashExpectedCents((int)currentCashSession()['id']);
    $count = fn(string $table) => (int)query("SELECT COUNT(*) FROM $table")->fetchColumn();

    // Migración idempotente sobre el esquema ya actual: no falla ni duplica nada.
    migrateFinanceModel(); migrateFinanceModel();
    checkFinance(true, 'la migración se puede repetir sin errores');

    // Restricciones de la base
    checkFinance(sqlRejected(fn() => query("INSERT INTO payments (client_id,amount) VALUES (?,0)", [$clientA])), 'la base rechaza cobros en cero');
    checkFinance(sqlRejected(fn() => query("INSERT INTO sales (client_id,subtotal,total,created_by,status) VALUES (?,10,10,?,'voided')", [$clientA, $admin])), 'la base rechaza anular sin motivo, usuario y fecha');

    // Permisos y caja cerrada
    checkFinance(denied(fn() => openCashSession($professional, '0'), 403), 'un profesional no abre caja');
    checkFinance(denied(fn() => createSale($professional, ['client_id' => $clientA, 'lines' => [['service_id' => $facial, 'quantity' => 1]]]), 403), 'un profesional no crea ventas');
    checkFinance(denied(fn() => openCashSession($reception, '-1'), 422) && denied(fn() => openCashSession($reception, '10.999'), 422), 'efectivo inicial inválido rechazado');
    $saleId = createSale($reception, ['client_id' => $clientA, 'employee_id' => $employee, 'lines' => [['service_id' => $facial, 'quantity' => 1]]]);
    checkFinance(denied(fn() => recordPayment($reception, ['sale_id' => $saleId, 'amount' => '100', 'method' => 'cash', 'idempotency_key' => 'sin-caja-0001']), 409), 'no se cobra con la caja cerrada');
    checkFinance($count('payments') === 0 && $count('cash_movements') === 0, 'un cobro rechazado no deja rastros');

    // Caja: ejemplo de conciliación de la hoja de ruta (1.000 + 600 efectivo + 400 tarjeta - 100 retiro = 1.500)
    $session = openCashSession($reception, '1000');
    checkFinance(denied(fn() => openCashSession($admin, '0'), 409), 'una sola caja abierta por centro');
    $big = createSale($reception, ['client_id' => $clientB, 'lines' => [['service_id' => $facial, 'quantity' => 1]]]);
    recordPayment($reception, ['sale_id' => $big, 'amount' => '600', 'method' => 'cash', 'idempotency_key' => 'conciliacion-efectivo']);
    recordPayment($reception, ['sale_id' => $big, 'amount' => '400', 'method' => 'debit_card', 'idempotency_key' => 'conciliacion-tarjeta']);
    recordCashMovement($reception, 'withdrawal', 'cash', '100', 'Retiro de prueba');
    checkFinance($cashOf() === 150000, 'efectivo esperado 1.500, no 1.900');
    $byMethod = cashTotalsByMethod($session);
    checkFinance($byMethod['cash'] === 50000 && $byMethod['debit_card'] === 40000, 'la tarjeta figura separada del efectivo');
    checkFinance(saleBalance($big)['collection'] === 'paid' && saleBalance($big)['balance'] === 0, 'venta pagada con dos medios');
    checkFinance(denied(fn() => recordCashMovement($reception, 'withdrawal', 'cash', '5000', 'x'), 422), 'no se retira más efectivo del disponible');
    checkFinance(denied(fn() => recordCashMovement($reception, 'otro', 'cash', '5', 'x'), 422) && denied(fn() => recordCashMovement($reception, 'income', 'cash', '0', 'x'), 422), 'movimientos inválidos rechazados');
    recordCashMovement($reception, 'income', 'cash', '50', 'Ingreso de prueba');
    checkFinance($cashOf() === 155000, 'un ingreso manual suma al efectivo');
    // Se vuelve a la caja de la prueba siguiente: retiro del ingreso para dejar 1.500.
    recordCashMovement($admin, 'expense', 'cash', '50', 'Gasto de prueba');
    checkFinance($cashOf() === 150000, 'un gasto resta del efectivo');

    // Ventas: servidor calcula, precios y permisos
    $s1 = createSale($reception, ['client_id' => $clientA, 'appointment_id' => (string)$appointment, 'employee_id' => (string)$employee, 'lines' => [
        ['service_id' => $facial, 'quantity' => 1, 'discount_type' => 'percent', 'discount_value' => '10', 'tax_rate' => '21', 'tax_included' => '0'],
        ['service_id' => $masaje, 'quantity' => 2, 'tax_rate' => '21', 'tax_included' => '0'],
    ]]);
    $row = query('SELECT * FROM sales WHERE id=?', [$s1])->fetch();
    checkFinance($row['subtotal'] === '2000.00' && $row['discount_total'] === '100.00' && $row['tax_total'] === '399.00' && $row['total'] === '2299.00', 'la venta se calcula en el servidor (1.000 - 10 % + 2 x 500, 21 %)');
    $items = query('SELECT * FROM sale_items WHERE sale_id=? ORDER BY id', [$s1])->fetchAll();
    checkFinance(count($items) === 2 && $items[0]['unit_price'] === '1000.00' && $items[0]['total_amount'] === '1089.00' && $items[1]['total_amount'] === '1210.00', 'el detalle guarda precio, descuento e impuesto históricos');
    query('UPDATE services SET price=1500 WHERE id=?', [$facial]);
    checkFinance(query('SELECT unit_price FROM sale_items WHERE id=?', [$items[0]['id']])->fetchColumn() === '1000.00', 'un cambio de precio posterior no altera ventas anteriores');
    query('UPDATE services SET price=1000 WHERE id=?', [$facial]);
    checkFinance(denied(fn() => createSale($reception, ['client_id' => $clientA, 'appointment_id' => (string)$appointment, 'lines' => [['service_id' => $masaje, 'quantity' => 1]]]), 409), 'una cita no tiene dos ventas vigentes');
    checkFinance(denied(fn() => createSale($reception, ['client_id' => $clientA, 'appointment_id' => (string)$other, 'lines' => [['service_id' => $masaje, 'quantity' => 1]]]), 422), 'la cita debe ser del mismo cliente');
    checkFinance(denied(fn() => createSale($reception, ['client_id' => $clientA, 'lines' => [['service_id' => $masaje, 'quantity' => 1, 'unit_price' => '1']]]), 403), 'recepción no cambia precios');
    $custom = createSale($admin, ['client_id' => $clientA, 'lines' => [['service_id' => $masaje, 'quantity' => 1, 'unit_price' => '450,50']]]);
    checkFinance(query('SELECT total FROM sales WHERE id=?', [$custom])->fetchColumn() === '450.50', 'administración puede fijar un precio');
    checkFinance(denied(fn() => createSale($reception, ['client_id' => $clientA, 'lines' => [['service_id' => $inactive, 'quantity' => 1]]]), 422), 'servicio inactivo rechazado');
    checkFinance(denied(fn() => createSale($reception, ['client_id' => 99999, 'lines' => [['service_id' => $masaje, 'quantity' => 1]]]), 422) && denied(fn() => createSale($reception, ['client_id' => $clientA, 'lines' => []]), 422) && denied(fn() => createSale($reception, ['client_id' => $clientA, 'lines' => [['description' => 'Sin precio', 'quantity' => 1]]]), 422), 'cliente, detalle y precio obligatorios');
    $before = $count('sales');
    checkFinance(denied(fn() => createSale($reception, ['client_id' => $clientA, 'lines' => [['service_id' => $masaje, 'quantity' => 1], ['service_id' => $masaje, 'quantity' => 1, 'discount_type' => 'amount', 'discount_value' => '999']]]), 422) && $count('sales') === $before, 'una línea inválida no deja una venta a medias');

    // Seña: se cobra una vez, se aplica a la venta y no se cuenta dos veces
    [$deposit] = recordPayment($reception, ['appointment_id' => $appointment, 'amount' => '300', 'method' => 'cash', 'idempotency_key' => 'sena-cita-0001']);
    checkFinance($cashOf() === 150000 + 30000, 'la seña entra a caja al cobrarla');
    checkFinance(depositAvailableCents($deposit) === 30000, 'seña disponible 300');
    checkFinance(denied(fn() => applyDeposit($reception, $deposit, $s1, '400'), 422), 'no se aplica más de lo que tiene la seña');
    applyDeposit($reception, $deposit, $s1, '300');
    checkFinance(saleBalance($s1) === ['total' => 229900, 'paid' => 30000, 'balance' => 199900, 'status' => 'confirmed', 'collection' => 'partial'], 'la seña reduce el saldo (2.299 - 300)');
    checkFinance($cashOf() === 180000 && depositAvailableCents($deposit) === 0, 'aplicar la seña no vuelve a mover caja');
    checkFinance(denied(fn() => applyDeposit($reception, $deposit, $s1, '1'), 422), 'una seña aplicada no se reutiliza');
    [$partial] = recordPayment($reception, ['sale_id' => $s1, 'amount' => '999', 'method' => 'transfer', 'idempotency_key' => 'cobro-parcial-01']);
    checkFinance(saleBalance($s1)['balance'] === 100000 && saleBalance($s1)['collection'] === 'partial', 'cobro parcial deja saldo 1.000');
    checkFinance(denied(fn() => recordPayment($reception, ['sale_id' => $s1, 'amount' => '1000,01', 'method' => 'cash', 'idempotency_key' => 'sobrepago-0001']), 422), 'no se cobra más que el saldo');
    checkFinance(denied(fn() => recordPayment($reception, ['appointment_id' => $appointment, 'amount' => '10', 'method' => 'cash', 'idempotency_key' => 'ambos-0001', 'sale_id' => $s1]), 422), 'un cobro va a una venta o a una cita, no a ambas');

    // Reintentos e idempotencia
    $paymentsBefore = $count('payments'); $movesBefore = $count('cash_movements');
    $first = recordPayment($reception, ['sale_id' => $s1, 'amount' => '400', 'method' => 'cash', 'idempotency_key' => 'doble-clic-0001']);
    $again = recordPayment($reception, ['sale_id' => $s1, 'amount' => '400', 'method' => 'cash', 'idempotency_key' => 'doble-clic-0001']);
    checkFinance($first[1] === false && $again[1] === true && $first[0] === $again[0], 'el reintento devuelve el mismo cobro');
    checkFinance($count('payments') === $paymentsBefore + 1 && $count('cash_movements') === $movesBefore + 1 && saleBalance($s1)['balance'] === 60000, 'el reintento no duplica cobro ni movimiento');
    checkFinance(denied(fn() => recordPayment($reception, ['sale_id' => $s1, 'amount' => '401', 'method' => 'cash', 'idempotency_key' => 'doble-clic-0001']), 409), 'la misma clave con otros datos se rechaza');
    checkFinance($cashOf() === 180000 + 40000, 'el efectivo esperado refleja el cobro una sola vez');
    checkFinance(denied(fn() => recordPayment($reception, ['sale_id' => $s1, 'amount' => '10', 'method' => 'cash', 'idempotency_key' => 'corta']), 422) && denied(fn() => recordPayment($reception, ['sale_id' => $s1, 'amount' => '10', 'method' => 'bitcoin', 'idempotency_key' => 'metodo-malo-01']), 422), 'clave y medio de pago inválidos rechazados');
    checkFinance(denied(fn() => recordPayment($professional, ['sale_id' => $s1, 'amount' => '10', 'method' => 'cash', 'idempotency_key' => 'sin-permiso-01']), 403), 'un profesional no cobra');

    // Anulación: motivo, solo administración, reversión coherente y sin borrar historial
    checkFinance(denied(fn() => voidSale($reception, $s1, 'Error de carga'), 403), 'recepción no anula ventas');
    checkFinance(denied(fn() => voidSale($admin, $s1, 'abc'), 422), 'la anulación exige motivo');
    $cashBefore = $cashOf(); $movesBefore = $count('cash_movements');
    voidSale($admin, $s1, 'Cliente desistió del tratamiento');
    $voided = query('SELECT * FROM sales WHERE id=?', [$s1])->fetch();
    checkFinance($voided['status'] === 'voided' && (int)$voided['voided_by'] === $admin && $voided['void_reason'] === 'Cliente desistió del tratamiento' && $voided['voided_at'] !== null, 'la anulación registra motivo, usuario y fecha');
    checkFinance(saleBalance($s1)['collection'] === 'voided' && saleBalance($s1)['paid'] === 0, 'una venta anulada no tiene cobros vigentes');
    $refunds = query("SELECT * FROM cash_movements WHERE kind='refund' ORDER BY id")->fetchAll();
    checkFinance(count($refunds) === 2 && $count('cash_movements') === $movesBefore + 2, 'cada cobro directo se revierte con un movimiento inverso (999 transferencia y 400 efectivo)');
    checkFinance($cashOf() === $cashBefore - 40000, 'solo el efectivo reintegrado resta del efectivo esperado; la seña no se toca');
    checkFinance(query("SELECT COUNT(*) FROM cash_movements WHERE kind='refund' AND reverses_id IS NOT NULL")->fetchColumn() === 2, 'los reintegros referencian el movimiento original');
    checkFinance(query("SELECT status FROM payments WHERE id=?", [$partial])->fetchColumn() === 'refunded' && query("SELECT status FROM payments WHERE id=?", [$deposit])->fetchColumn() === 'paid', 'cobros reintegrados, seña conservada');
    checkFinance(depositAvailableCents($deposit) === 30000, 'la seña vuelve a estar disponible para otra venta');
    checkFinance(denied(fn() => voidSale($admin, $s1, 'Otro intento de anular'), 409), 'no se anula dos veces');
    checkFinance(denied(fn() => recordPayment($reception, ['sale_id' => $s1, 'amount' => '10', 'method' => 'cash', 'idempotency_key' => 'sobre-anulada-1']), 409), 'no se cobra una venta anulada');
    checkFinance(denied(fn() => applyDeposit($reception, $deposit, $s1, '10'), 409), 'no se aplica una seña a una venta anulada');
    $again = createSale($reception, ['client_id' => $clientA, 'appointment_id' => (string)$appointment, 'lines' => [['service_id' => $masaje, 'quantity' => 1]]]);
    checkFinance($again > $s1, 'tras anular, la cita admite una venta nueva');
    applyDeposit($reception, $deposit, $again, '300');
    checkFinance(saleBalance($again)['balance'] === 20000 && $cashOf() === $cashBefore - 40000, 'la seña reutilizada tampoco duplica ingresos');
    checkFinance($count('sales') >= 5 && $count('payments') >= 5, 'el historial se conserva');

    // Anular con cobros directos exige caja abierta
    $s3 = createSale($reception, ['client_id' => $clientB, 'lines' => [['service_id' => $masaje, 'quantity' => 1]]]);
    recordPayment($reception, ['sale_id' => $s3, 'amount' => '500', 'method' => 'cash', 'idempotency_key' => 'venta-tres-0001']);
    query("UPDATE cash_sessions SET status='closed', closed_by=?, closed_at=NOW() WHERE status='open'", [$admin]);
    checkFinance(denied(fn() => voidSale($admin, $s3, 'Anulación sin caja'), 409) && query('SELECT status FROM sales WHERE id=?', [$s3])->fetchColumn() === 'confirmed', 'sin caja abierta no se reintegra y la venta queda intacta');
    checkFinance(currentCashSession() === false && openCashSession($reception, '0') > $session, 'cerrada la caja anterior se puede abrir otra');

    // Dos envíos simultáneos con la misma clave producen un solo cobro
    $race = createSale($admin, ['client_id' => $clientA, 'lines' => [['description' => 'Carrera', 'quantity' => 1, 'unit_price' => '500']]]);
    $procs = [];
    for ($i = 0; $i < 2; $i++) {
        $procs[] = proc_open([PHP_BINARY, __FILE__, '--worker', (string)$reception, (string)$race], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i], null, ['DB_DATABASE' => $database, 'DB_USERNAME' => 'root', 'DB_PASSWORD' => $rootPassword, 'DB_HOST' => $host, 'DB_PORT' => $port, 'PATH' => getenv('PATH')]);
    }
    $outputs = [];
    foreach ($procs as $i => $proc) { $outputs[] = stream_get_contents($pipes[$i][1]); proc_close($proc); }
    checkFinance(count(array_filter($outputs, fn($o) => str_starts_with($o, 'created:'))) === 1 && count(array_filter($outputs, fn($o) => str_starts_with($o, 'replayed:'))) === 1, 'dos envíos simultáneos: uno crea y el otro recibe el mismo cobro');
    checkFinance((int)query("SELECT COUNT(*) FROM payments WHERE idempotency_key IS NOT NULL AND id IN (SELECT payment_id FROM payment_allocations WHERE sale_id=?)", [$race])->fetchColumn() === 1 && saleBalance($race)['paid'] === 10000, 'la carrera deja un solo cobro de 100');

    // Actualización desde el esquema anterior (payments y payment_allocations viejos, con datos)
    foreach (['cash_movements', 'cash_sessions', 'payment_allocations', 'payments', 'sale_items', 'sales'] as $table) db()->exec("DROP TABLE `$table`");
    db()->exec("CREATE TABLE payments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, client_id BIGINT UNSIGNED NOT NULL, amount DECIMAL(12,2) NOT NULL, payment_method ENUM('cash','debit_card','credit_card','transfer','other') NOT NULL DEFAULT 'cash', status ENUM('pending','paid','refunded','cancelled') NOT NULL DEFAULT 'pending', paid_at TIMESTAMP NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT payments_client FOREIGN KEY (client_id) REFERENCES clients(id), CONSTRAINT valid_payment_amount CHECK (amount > 0), INDEX payments_client (client_id)) ENGINE=InnoDB");
    db()->exec("CREATE TABLE payment_allocations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, payment_id BIGINT UNSIGNED NOT NULL, appointment_id BIGINT UNSIGNED NOT NULL, amount DECIMAL(12,2) NOT NULL, CONSTRAINT payment_allocations_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE, CONSTRAINT payment_allocations_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id), CONSTRAINT valid_allocation_amount CHECK (amount > 0), UNIQUE (payment_id, appointment_id)) ENGINE=InnoDB");
    query("INSERT INTO payments (client_id,amount,status,paid_at) VALUES (?,250.00,'paid',NOW())", [$clientA]);
    query('INSERT INTO payment_allocations (payment_id,appointment_id,amount) VALUES (?,?,250.00)', [(int)db()->lastInsertId(), $appointment]);
    migrateFinanceModel(); migrateFinanceModel();
    checkFinance(query('SELECT amount FROM payments')->fetchColumn() === '250.00' && query('SELECT kind FROM payments')->fetchColumn() === 'payment' && query('SELECT appointment_id FROM payment_allocations')->fetchColumn() == $appointment, 'la migración conserva los datos anteriores');
    openCashSession($reception, '0');
    [$migrated] = recordPayment($reception, ['appointment_id' => $appointment, 'amount' => '50', 'method' => 'cash', 'idempotency_key' => 'post-migracion-1']);
    checkFinance($migrated > 1 && sqlRejected(fn() => query('INSERT INTO payment_allocations (payment_id, amount) VALUES (?, 1)', [$migrated])), 'tras migrar funciona el modelo nuevo y exige destino de la asignación');

    // Base con el modelo de la tarjeta 14 pero sin categorías ni clave en el libro de caja (tarjeta 19)
    db()->exec('ALTER TABLE cash_movements DROP FOREIGN KEY cash_movements_category, DROP INDEX cash_movements_idempotency, DROP COLUMN category_id, DROP COLUMN idempotency_key');
    migrateFinanceModel(); migrateFinanceModel();
    checkFinance(count(query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cash_movements' AND COLUMN_NAME IN ('category_id','idempotency_key')")->fetchAll()) === 2 && (int)query("SELECT COUNT(*) FROM expense_categories WHERE name='Insumos'")->fetchColumn() === 1, 'la migración agrega categoría y clave al libro de caja y las categorías por defecto, sin duplicarlas');
    checkFinance(recordCashMovement($reception, 'income', 'cash', '5', 'tras migrar', '', 'caja-migrada-001') === recordCashMovement($reception, 'income', 'cash', '5', 'tras migrar', '', 'caja-migrada-001'), 'tras migrar el libro de caja deduplica por clave de operación');

    echo "\nTodas las verificaciones financieras pasaron: $checks.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n");
    $failed = true;
} finally {
    $root->exec("DROP DATABASE IF EXISTS `$database`");
}
exit(empty($failed) ? 0 : 1);
