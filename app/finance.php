<?php
declare(strict_types=1);

require_once __DIR__ . '/money.php';

/*
 * Operaciones financieras (tarjeta #14): ventas, cobros, señas, anulación y libro de caja.
 * Reglas: importes calculados en el servidor, todo en una transacción, permisos verificados
 * contra la base y no contra la sesión, nada se borra: se anula con motivo y se revierte con
 * un movimiento inverso. Orden de bloqueo: caja abierta -> venta -> cobro.
 */

const PAYMENT_METHODS = ['cash' => 'Efectivo', 'debit_card' => 'Tarjeta de débito', 'credit_card' => 'Tarjeta de crédito', 'transfer' => 'Transferencia', 'other' => 'Otro'];

/** Usuario activo con uno de los roles indicados; si no, 403. Bloquea la fila hasta el final de la transacción. */
function financeActor(int $actorId, array $roles): array
{
    $actor = query('SELECT u.id,u.role FROM users u LEFT JOIN employees e ON e.user_id=u.id WHERE u.id=? AND u.active=1 AND COALESCE(e.active,1)=1 FOR SHARE', [$actorId])->fetch();
    if (!$actor || !in_array($actor['role'], $roles, true)) throw new DomainException('No tenés permiso para esta operación financiera.', 403);
    return $actor;
}

/** Corre $work en una transacción; la deshace ante cualquier error. */
function financeTransaction(callable $work): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $work();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function currentCashSession(bool $lock = false): array|false
{
    return query("SELECT * FROM cash_sessions WHERE status='open'" . ($lock ? ' FOR UPDATE' : ''))->fetch();
}

/** Efectivo que debería haber en caja: apertura + entradas en efectivo - salidas en efectivo. Otros medios no cuentan. */
function cashExpectedCents(int $sessionId): int
{
    $session = query('SELECT opening_cash FROM cash_sessions WHERE id=?', [$sessionId])->fetch();
    if (!$session) throw new DomainException('Caja no encontrada.', 404);
    $moves = query("SELECT direction, SUM(amount) AS total FROM cash_movements WHERE cash_session_id=? AND method='cash' GROUP BY direction", [$sessionId])->fetchAll();
    $cents = decimalToCents($session['opening_cash']);
    foreach ($moves as $move) $cents += ($move['direction'] === 'in' ? 1 : -1) * decimalToCents($move['total']);
    return $cents;
}

/** Total por medio de pago de la caja (entradas menos salidas), en centavos. */
function cashTotalsByMethod(int $sessionId): array
{
    $totals = array_fill_keys(array_keys(PAYMENT_METHODS), 0);
    foreach (query('SELECT method, direction, SUM(amount) AS total FROM cash_movements WHERE cash_session_id=? GROUP BY method, direction', [$sessionId])->fetchAll() as $row) {
        $totals[$row['method']] += ($row['direction'] === 'in' ? 1 : -1) * decimalToCents($row['total']);
    }
    return $totals;
}

function openCashSession(int $actorId, string $openingCash): int
{
    $opening = moneyToCents($openingCash);
    if ($opening === null) throw new DomainException('El efectivo inicial debe ser un importe válido, con hasta 2 decimales.', 422);
    return financeTransaction(function () use ($actorId, $opening) {
        financeActor($actorId, ['admin', 'reception']);
        if (currentCashSession(true)) throw new DomainException('Ya hay una caja abierta. Cerrala antes de abrir otra.', 409);
        try {
            query('INSERT INTO cash_sessions (opening_cash, opened_by) VALUES (?, ?)', [centsToDecimal($opening), $actorId]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') throw new DomainException('Ya hay una caja abierta. Cerrala antes de abrir otra.', 409);
            throw $e;
        }
        return (int)db()->lastInsertId();
    });
}

/** Ingreso, gasto o retiro manual en la caja abierta. */
function recordCashMovement(int $actorId, string $kind, string $method, string $amount, string $description): int
{
    $cents = moneyToCents($amount);
    $description = trim($description);
    if (!in_array($kind, ['income', 'expense', 'withdrawal'], true) || !isset(PAYMENT_METHODS[$method]) || $cents === null || $cents < 1 || textLength($description) > 255) {
        throw new DomainException('Revisá tipo, medio de pago e importe (mayor que cero). La descripción admite hasta 255 caracteres.', 422);
    }
    return financeTransaction(function () use ($actorId, $kind, $method, $cents, $description) {
        financeActor($actorId, ['admin', 'reception']);
        $session = currentCashSession(true);
        if (!$session) throw new DomainException('No hay una caja abierta. Abrí la caja para registrar movimientos.', 409);
        if ($kind !== 'income') {
            $available = $method === 'cash' ? cashExpectedCents((int)$session['id']) : cashTotalsByMethod((int)$session['id'])[$method];
            if ($cents > $available) throw new DomainException('La salida supera lo disponible en ese medio de pago.', 422);
        }
        query('INSERT INTO cash_movements (cash_session_id, direction, kind, method, amount, description, created_by) VALUES (?,?,?,?,?,?,?)',
            [$session['id'], $kind === 'income' ? 'in' : 'out', $kind, $method, centsToDecimal($cents), $description ?: null, $actorId]);
        return (int)db()->lastInsertId();
    });
}

/**
 * Valida y calcula las líneas de una venta (sin guardar nada). Con $appointmentId, un servicio que ya estaba en esa cita
 * conserva el precio acordado en la cita. Devuelve items (para guardar) y sum (totales en centavos).
 */
function prepareSaleLines(array $lines, string $role, string $appointmentId = ''): array
{
    if (!$lines || count($lines) > 30) throw new DomainException('El detalle debe tener de 1 a 30 líneas.', 422);
    $defaultRate = rateToBasisPoints(getenv('SALES_DEFAULT_TAX_RATE') ?: '0') ?? 0;
    $defaultIncluded = (getenv('SALES_TAX_INCLUDED') ?: '1') !== '0';
    $computed = []; $items = [];
    foreach ($lines as $line) {
        if (!is_array($line)) throw new DomainException('Detalle de venta inválido.', 422);
        $serviceId = (string)($line['service_id'] ?? '');
        $quantity = (string)($line['quantity'] ?? '1');
        if (!ctype_digit($quantity) || ($serviceId !== '' && !ctype_digit($serviceId))) throw new DomainException('Cantidad o servicio inválidos.', 422);
        $service = $serviceId === '' ? false : query('SELECT id,name,price,active FROM services WHERE id=? FOR SHARE', [$serviceId])->fetch();
        if ($serviceId !== '' && (!$service || !$service['active'])) throw new DomainException('Hay un servicio inexistente o inactivo.', 422);
        $description = trim((string)($line['description'] ?? '')) ?: ($service['name'] ?? '');
        if ($description === '' || textLength($description) > 190) throw new DomainException('Cada línea necesita una descripción de hasta 190 caracteres.', 422);
        $unit = $service ? decimalToCents($service['price']) : null;
        if ($service && $appointmentId !== '') {
            $agreed = query('SELECT price FROM appointment_services WHERE appointment_id=? AND service_id=?', [$appointmentId, $serviceId])->fetchColumn();
            if ($agreed !== false) $unit = decimalToCents($agreed);
        }
        if (isset($line['unit_price']) && trim((string)$line['unit_price']) !== '') {
            if ($role !== 'admin') throw new DomainException('Solo administración puede modificar precios.', 403);
            $unit = moneyToCents((string)$line['unit_price']);
        }
        if ($unit === null) throw new DomainException('Indicá un precio válido para cada línea.', 422);
        $type = (string)($line['discount_type'] ?? 'none');
        $rawDiscount = trim((string)($line['discount_value'] ?? ''));
        $discountValue = match ($type) { 'amount' => moneyToCents($rawDiscount), 'percent' => rateToBasisPoints($rawDiscount), default => 0 };
        if ($discountValue === null) throw new DomainException('El descuento es inválido.', 422);
        $rate = isset($line['tax_rate']) && trim((string)$line['tax_rate']) !== '' ? rateToBasisPoints((string)$line['tax_rate']) : $defaultRate;
        if ($rate === null) throw new DomainException('El impuesto debe estar entre 0 y 100 %.', 422);
        $included = isset($line['tax_included']) ? (string)$line['tax_included'] !== '0' : $defaultIncluded;
        $result = calcSaleLine($unit, (int)$quantity, ['type' => $type, 'value' => $discountValue], $rate, $included);
        $computed[] = $result;
        $items[] = [$service['id'] ?? null, $description, (int)$quantity, $unit, $result, $rate, $included];
    }
    return ['items' => $items, 'computed' => $computed, 'sum' => sumSaleLines($computed)];
}

/** Calcula una venta sin guardarla (vista previa del formulario). Lanza DomainException si hay datos inválidos. */
function previewSale(int $actorId, array $lines, string $appointmentId = ''): array
{
    $actor = financeActor($actorId, ['admin', 'reception']);
    return prepareSaleLines($lines, $actor['role'], ctype_digit($appointmentId) ? $appointmentId : '');
}

/**
 * Crea una venta confirmada. $data: client_id, employee_id?, appointment_id?, lines[].
 * Cada línea: service_id? , description?, quantity, unit_price? (solo admin), discount_type (none|amount|percent),
 * discount_value, tax_rate (% ; por defecto SALES_DEFAULT_TAX_RATE), tax_included? (por defecto SALES_TAX_INCLUDED, 1).
 * El total lo calcula el servidor: ningún importe del formulario se guarda sin recalcular.
 */
function createSale(int $actorId, array $data): int
{
    $client = (string)($data['client_id'] ?? ''); $employee = (string)($data['employee_id'] ?? ''); $appointment = (string)($data['appointment_id'] ?? '');
    $lines = $data['lines'] ?? null;
    $notes = trim((string)($data['notes'] ?? ''));
    if (textLength($notes) > 500) throw new DomainException('Las observaciones admiten hasta 500 caracteres.', 422);
    if (!ctype_digit($client) || ($employee !== '' && !ctype_digit($employee)) || ($appointment !== '' && !ctype_digit($appointment)) || !is_array($lines) || !$lines || count($lines) > 30) {
        throw new DomainException('Revisá cliente, empleado y detalle de la venta (1 a 30 líneas).', 422);
    }
    return financeTransaction(function () use ($actorId, $client, $employee, $appointment, $lines, $notes) {
        $actor = financeActor($actorId, ['admin', 'reception']);
        if (!query('SELECT id FROM clients WHERE id=? FOR SHARE', [$client])->fetchColumn()) throw new DomainException('El cliente no existe.', 422);
        if ($employee !== '' && !query('SELECT id FROM employees WHERE id=? FOR SHARE', [$employee])->fetchColumn()) throw new DomainException('El empleado no existe.', 422);
        if ($appointment !== '') {
            $owner = query('SELECT client_id FROM appointments WHERE id=? FOR SHARE', [$appointment])->fetchColumn();
            if ($owner === false || (string)$owner !== $client) throw new DomainException('La cita no existe o no pertenece a este cliente.', 422);
        }
        ['items' => $items, 'sum' => $sum] = prepareSaleLines($lines, $actor['role'], $appointment);
        try {
            query('INSERT INTO sales (client_id, employee_id, appointment_id, subtotal, discount_total, tax_total, total, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?)',
                [$client, $employee ?: null, $appointment ?: null, centsToDecimal($sum['subtotal']), centsToDecimal($sum['discount']), centsToDecimal($sum['tax']), centsToDecimal($sum['total']), $notes ?: null, $actorId]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'one_active_sale_per_appointment')) throw new DomainException('Esta cita ya tiene una venta vigente. Anulala antes de crear otra.', 409);
            throw $e;
        }
        $saleId = (int)db()->lastInsertId();
        foreach ($items as [$serviceId, $description, $quantity, $unit, $r, $rate, $included]) {
            query('INSERT INTO sale_items (sale_id, service_id, description, quantity, unit_price, discount, tax_rate, tax_included, net_amount, tax_amount, total_amount) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$saleId, $serviceId, $description, $quantity, centsToDecimal($unit), centsToDecimal($r['discount']), sprintf('%d.%02d', intdiv($rate, 100), $rate % 100), (int)$included, centsToDecimal($r['net']), centsToDecimal($r['tax']), centsToDecimal($r['total'])]);
        }
        return $saleId;
    });
}

/** Total, cobrado (asignaciones vigentes de cobros pagados), saldo y estado de cobro de una venta. */
function saleBalance(int $saleId): array
{
    $sale = query('SELECT total, status FROM sales WHERE id=?', [$saleId])->fetch();
    if (!$sale) throw new DomainException('Venta no encontrada.', 404);
    $paid = decimalToCents(query("SELECT COALESCE(SUM(pa.amount),0) FROM payment_allocations pa JOIN payments p ON p.id=pa.payment_id WHERE pa.sale_id=? AND pa.released_at IS NULL AND p.status='paid'", [$saleId])->fetchColumn());
    $total = decimalToCents($sale['total']);
    return ['total' => $total, 'paid' => $paid, 'balance' => $total - $paid, 'status' => $sale['status'], 'collection' => $sale['status'] === 'voided' ? 'voided' : collectionStatus($total, $paid)];
}

/** Parte de una seña todavía no aplicada a ninguna venta. */
function depositAvailableCents(int $paymentId): int
{
    $payment = query("SELECT amount FROM payments WHERE id=? AND kind='deposit' AND status='paid'", [$paymentId])->fetch();
    if (!$payment) return 0;
    $used = decimalToCents(query('SELECT COALESCE(SUM(amount),0) FROM payment_allocations WHERE payment_id=? AND sale_id IS NOT NULL AND released_at IS NULL', [$paymentId])->fetchColumn());
    return decimalToCents($payment['amount']) - $used;
}

/**
 * Registra un cobro de una venta (sale_id) o una seña de una cita (appointment_id), con el dinero entrando
 * a la caja abierta. idempotency_key (8 a 100 caracteres, generada al abrir el formulario) hace que un reintento
 * o doble clic devuelva el mismo cobro sin duplicarlo. Devuelve [payment_id, replayed].
 */
function recordPayment(int $actorId, array $data): array
{
    $key = (string)($data['idempotency_key'] ?? '');
    $method = (string)($data['method'] ?? '');
    $cents = moneyToCents((string)($data['amount'] ?? ''));
    $saleId = (string)($data['sale_id'] ?? ''); $appointmentId = (string)($data['appointment_id'] ?? ''); $clientId = (string)($data['client_id'] ?? '');
    if (strlen($key) < 8 || strlen($key) > 100 || !isset(PAYMENT_METHODS[$method]) || $cents === null || $cents < 1
        || ($saleId === '') === ($appointmentId === '') || ($saleId !== '' && !ctype_digit($saleId)) || ($appointmentId !== '' && !ctype_digit($appointmentId))) {
        throw new DomainException('Revisá el importe (mayor que cero), el medio de pago y a qué venta o cita se aplica.', 422);
    }
    $hash = hash('sha256', $actorId . '|' . $key);
    $attempt = static function () use ($actorId, $hash, $method, $cents, $saleId, $appointmentId, $clientId): array {
        return financeTransaction(function () use ($actorId, $hash, $method, $cents, $saleId, $appointmentId, $clientId) {
            financeActor($actorId, ['admin', 'reception']);
            $session = currentCashSession(true);
            if (!$session) throw new DomainException('No hay una caja abierta. Abrí la caja antes de cobrar.', 409);
            $same = query('SELECT p.id,p.amount,p.payment_method,p.client_id,pa.sale_id,pa.appointment_id FROM payments p JOIN payment_allocations pa ON pa.payment_id=p.id WHERE p.idempotency_key=? ORDER BY pa.id LIMIT 1', [$hash])->fetch();
            if ($same) {
                if (decimalToCents($same['amount']) !== $cents || $same['payment_method'] !== $method || (string)($same['sale_id'] ?? '') !== $saleId || (string)($same['appointment_id'] ?? '') !== $appointmentId) {
                    throw new DomainException('Esa clave de operación ya se usó con otros datos. Abrí el formulario de nuevo.', 409);
                }
                return [(int)$same['id'], true];
            }
            if ($saleId !== '') {
                $sale = query('SELECT id,client_id,status FROM sales WHERE id=? FOR UPDATE', [$saleId])->fetch();
                if (!$sale) throw new DomainException('Venta no encontrada.', 404);
                if ($sale['status'] !== 'confirmed') throw new DomainException('No se puede cobrar una venta anulada.', 409);
                if ($cents > saleBalance((int)$saleId)['balance']) throw new DomainException('El cobro supera el saldo pendiente de la venta.', 422);
                $kind = 'payment'; $client = (int)$sale['client_id'];
            } else {
                $appointment = query('SELECT id,client_id,status FROM appointments WHERE id=? FOR SHARE', [$appointmentId])->fetch();
                if (!$appointment) throw new DomainException('Cita no encontrada.', 404);
                if ($appointment['status'] === 'cancelled') throw new DomainException('No se puede registrar una seña para una cita cancelada.', 409);
                $kind = 'deposit'; $client = (int)$appointment['client_id'];
            }
            if ($clientId !== '' && (string)$client !== $clientId) throw new DomainException('El cliente no coincide con la venta o la cita.', 422);
            query("INSERT INTO payments (client_id, amount, payment_method, status, kind, idempotency_key, created_by, paid_at) VALUES (?,?,?,'paid',?,?,?,NOW())",
                [$client, centsToDecimal($cents), $method, $kind, $hash, $actorId]);
            $paymentId = (int)db()->lastInsertId();
            query('INSERT INTO payment_allocations (payment_id, appointment_id, sale_id, amount) VALUES (?,?,?,?)', [$paymentId, $appointmentId ?: null, $saleId ?: null, centsToDecimal($cents)]);
            query("INSERT INTO cash_movements (cash_session_id, direction, kind, method, amount, payment_id, created_by) VALUES (?,'in','payment',?,?,?,?)",
                [$session['id'], $method, centsToDecimal($cents), $paymentId, $actorId]);
            return [$paymentId, false];
        });
    };
    try {
        return $attempt();
    } catch (PDOException $e) {
        // Dos envíos simultáneos con la misma clave: el segundo choca con el índice único y relee el cobro del primero.
        if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'payments_idempotency')) return $attempt();
        throw $e;
    }
}

/** Aplica (todo o parte de) una seña a una venta del mismo cliente. No mueve caja: el dinero ya entró al cobrar la seña. */
function applyDeposit(int $actorId, int $paymentId, int $saleId, string $amount): int
{
    $cents = moneyToCents($amount);
    if ($cents === null || $cents < 1) throw new DomainException('El importe a aplicar debe ser mayor que cero.', 422);
    return financeTransaction(function () use ($actorId, $paymentId, $saleId, $cents) {
        financeActor($actorId, ['admin', 'reception']);
        $sale = query('SELECT id,client_id,status FROM sales WHERE id=? FOR UPDATE', [$saleId])->fetch();
        $payment = query("SELECT id,client_id FROM payments WHERE id=? AND kind='deposit' AND status='paid' FOR UPDATE", [$paymentId])->fetch();
        if (!$sale || !$payment) throw new DomainException('Venta o seña no encontrada.', 404);
        if ($sale['status'] !== 'confirmed') throw new DomainException('No se puede aplicar una seña a una venta anulada.', 409);
        if ($sale['client_id'] !== $payment['client_id']) throw new DomainException('La seña pertenece a otro cliente.', 422);
        if ($cents > depositAvailableCents($paymentId)) throw new DomainException('La seña no tiene saldo suficiente sin aplicar.', 422);
        if ($cents > saleBalance($saleId)['balance']) throw new DomainException('El importe supera el saldo pendiente de la venta.', 422);
        query('INSERT INTO payment_allocations (payment_id, sale_id, amount) VALUES (?,?,?)', [$paymentId, $saleId, centsToDecimal($cents)]);
        return (int)db()->lastInsertId();
    });
}

/**
 * Anula una venta (solo administración, con motivo). Los cobros directos se reintegran con un movimiento inverso
 * en la caja abierta; las señas aplicadas vuelven a quedar disponibles sin mover dinero. Nada se borra.
 */
function voidSale(int $actorId, int $saleId, string $reason): void
{
    $reason = trim($reason);
    if (textLength($reason) < 5 || textLength($reason) > 255) throw new DomainException('Indicá el motivo de la anulación (5 a 255 caracteres).', 422);
    financeTransaction(function () use ($actorId, $saleId, $reason) {
        financeActor($actorId, ['admin']);
        $session = currentCashSession(true);
        $sale = query('SELECT id,status FROM sales WHERE id=? FOR UPDATE', [$saleId])->fetch();
        if (!$sale) throw new DomainException('Venta no encontrada.', 404);
        if ($sale['status'] === 'voided') throw new DomainException('La venta ya está anulada.', 409);
        $allocations = query("SELECT pa.id AS allocation_id, pa.amount, p.id AS payment_id, p.kind, p.payment_method FROM payment_allocations pa JOIN payments p ON p.id=pa.payment_id WHERE pa.sale_id=? AND pa.released_at IS NULL AND p.status='paid' ORDER BY pa.id FOR UPDATE", [$saleId])->fetchAll();
        $refunds = array_filter($allocations, fn($a) => $a['kind'] === 'payment');
        if ($refunds && !$session) throw new DomainException('Hay cobros para reintegrar y no hay una caja abierta. Abrí la caja y repetí la anulación.', 409);
        foreach ($allocations as $allocation) {
            query('UPDATE payment_allocations SET released_at=NOW() WHERE id=?', [$allocation['allocation_id']]);
            if ($allocation['kind'] !== 'payment') continue; // seña: vuelve a quedar disponible
            query("UPDATE payments SET status='refunded', refunded_at=NOW(), refunded_by=? WHERE id=?", [$actorId, $allocation['payment_id']]);
            $original = query("SELECT id FROM cash_movements WHERE payment_id=? AND kind='payment' AND reverses_id IS NULL ORDER BY id LIMIT 1", [$allocation['payment_id']])->fetchColumn();
            query("INSERT INTO cash_movements (cash_session_id, direction, kind, method, amount, payment_id, reverses_id, description, created_by) VALUES (?,'out','refund',?,?,?,?,?,?)",
                [$session['id'], $allocation['payment_method'], $allocation['amount'], $allocation['payment_id'], $original ?: null, 'Anulación de venta #' . $saleId, $actorId]);
        }
        query("UPDATE sales SET status='voided', voided_at=NOW(), voided_by=?, void_reason=? WHERE id=?", [$actorId, $reason, $saleId]);
    });
}

/** Venta con cliente, empleado, cita, detalle y saldo; false si no existe. */
function saleRecord(int $saleId): array|false
{
    $sale = query('SELECT s.*, c.first_name, c.last_name, u.name AS employee_name, creator.name AS created_by_name
        FROM sales s JOIN clients c ON c.id=s.client_id
        LEFT JOIN employees e ON e.id=s.employee_id LEFT JOIN users u ON u.id=e.user_id
        JOIN users creator ON creator.id=s.created_by WHERE s.id=?', [$saleId])->fetch();
    if (!$sale) return false;
    $sale['items'] = query('SELECT * FROM sale_items WHERE sale_id=? ORDER BY id', [$saleId])->fetchAll();
    $sale['balance'] = saleBalance($saleId);
    return $sale;
}

/** Ventas de un cliente, más recientes primero, con total, cobrado y saldo en centavos. */
function clientSales(int $clientId, int $limit = 50): array
{
    $rows = query('SELECT s.id, s.status, s.total, s.created_at, s.appointment_id,
            COALESCE((SELECT SUM(pa.amount) FROM payment_allocations pa JOIN payments p ON p.id=pa.payment_id
                WHERE pa.sale_id=s.id AND pa.released_at IS NULL AND p.status=\'paid\'),0) AS paid
        FROM sales s WHERE s.client_id=? ORDER BY s.created_at DESC, s.id DESC LIMIT ' . max(1, $limit), [$clientId])->fetchAll();
    foreach ($rows as &$row) {
        $row['total_cents'] = decimalToCents($row['total']); $row['paid_cents'] = decimalToCents($row['paid']);
        $row['balance_cents'] = $row['total_cents'] - $row['paid_cents'];
        $row['collection'] = $row['status'] === 'voided' ? 'voided' : collectionStatus($row['total_cents'], $row['paid_cents']);
    }
    return $rows;
}

/** [appointment_id => sale_id] de las ventas vigentes de esas citas. */
function activeSalesByAppointment(array $appointmentIds): array
{
    $appointmentIds = array_values(array_unique(array_map('intval', $appointmentIds)));
    if (!$appointmentIds) return [];
    $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
    $map = [];
    foreach (query("SELECT appointment_id, id FROM sales WHERE status='confirmed' AND appointment_id IN ($placeholders)", $appointmentIds)->fetchAll() as $row) $map[(int)$row['appointment_id']] = (int)$row['id'];
    return $map;
}

const COLLECTION_LABELS = ['unpaid' => 'Sin cobrar', 'partial' => 'Cobro parcial', 'paid' => 'Cobrada', 'voided' => 'Anulada'];

/** Cobros y señas aplicados a una venta (vigentes y liberados), del más antiguo al más nuevo. */
function salePayments(int $saleId): array
{
    return query('SELECT pa.id, pa.amount, pa.released_at, p.id AS payment_id, p.kind, p.payment_method, p.status, p.paid_at, u.name AS created_by_name
        FROM payment_allocations pa JOIN payments p ON p.id=pa.payment_id LEFT JOIN users u ON u.id=p.created_by
        WHERE pa.sale_id=? ORDER BY pa.id', [$saleId])->fetchAll();
}

/** Señas pagadas de un cliente que todavía tienen saldo sin aplicar: [payment_id, amount, available (centavos), appointment_id, paid_at]. */
function clientDeposits(int $clientId): array
{
    $rows = query("SELECT p.id AS payment_id, p.amount, p.paid_at, pa.appointment_id FROM payments p
        JOIN payment_allocations pa ON pa.payment_id=p.id AND pa.appointment_id IS NOT NULL
        WHERE p.client_id=? AND p.kind='deposit' AND p.status='paid' ORDER BY p.id", [$clientId])->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $available = depositAvailableCents((int)$row['payment_id']);
        if ($available > 0) $out[] = $row + ['available' => $available];
    }
    return $out;
}

const SALES_PER_PAGE = 12;

/**
 * Listado de ventas con búsqueda y filtros. $f: q (número de venta o nombre del cliente), status (unpaid|partial|paid|voided),
 * from y to (Y-m-d, inclusivos). Devuelve [filas, total de resultados]. El estado de cobro se calcula igual que saleBalance.
 */
function searchSales(array $f, int $page): array
{
    $where = []; $params = [];
    $q = trim((string)($f['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . addcslashes($q, '\\%_') . '%';
        $clause = "(x.first_name LIKE ? OR x.last_name LIKE ? OR CONCAT_WS(' ', x.first_name, x.last_name) LIKE ? OR CONCAT_WS(' ', x.last_name, x.first_name) LIKE ?";
        $params = [$like, $like, $like, $like];
        if (ctype_digit(ltrim($q, '#')) && ltrim($q, '#') !== '') { $clause .= ' OR x.id = ?'; $params[] = (int)ltrim($q, '#'); }
        $where[] = $clause . ')';
    }
    $status = (string)($f['status'] ?? '');
    $where[] = match ($status) {
        'voided' => "x.status='voided'",
        'unpaid' => "x.status='confirmed' AND x.paid=0 AND x.total>0",
        'partial' => "x.status='confirmed' AND x.paid>0 AND x.paid<x.total",
        'paid' => "x.status='confirmed' AND x.paid>=x.total AND (x.paid>0 OR x.total=0)",
        default => '1=1',
    };
    if (($f['from'] ?? '') !== '') { $where[] = 'DATE(x.created_at) >= ?'; $params[] = $f['from']; }
    if (($f['to'] ?? '') !== '') { $where[] = 'DATE(x.created_at) <= ?'; $params[] = $f['to']; }
    $base = "FROM (SELECT s.id, s.created_at, s.total, s.status, s.appointment_id, c.id AS client_id, c.first_name, c.last_name,
        COALESCE((SELECT SUM(pa.amount) FROM payment_allocations pa JOIN payments p ON p.id=pa.payment_id WHERE pa.sale_id=s.id AND pa.released_at IS NULL AND p.status='paid'),0) AS paid
        FROM sales s JOIN clients c ON c.id=s.client_id) x WHERE " . implode(' AND ', $where);
    $count = (int)query("SELECT COUNT(*) $base", $params)->fetchColumn();
    $offset = (max(1, $page) - 1) * SALES_PER_PAGE;
    $rows = query("SELECT x.* $base ORDER BY x.created_at DESC, x.id DESC LIMIT " . SALES_PER_PAGE . " OFFSET $offset", $params)->fetchAll();
    foreach ($rows as &$row) {
        $total = decimalToCents($row['total']); $paid = decimalToCents($row['paid']);
        $row['collection'] = $row['status'] === 'voided' ? 'voided' : collectionStatus($total, $paid);
        $row['balance'] = $row['status'] === 'voided' ? 0 : $total - $paid;
    }
    return [$rows, $count];
}
