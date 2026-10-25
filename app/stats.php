<?php
declare(strict_types=1);

/**
 * Estadísticas (tarjeta #27). Todo en centavos enteros. Las cifras salen de las mismas fuentes que Ventas y Caja:
 *  - Vendido: total de las ventas vigentes (las anuladas no suman) con fecha de venta dentro del período.
 *  - Cobrado: lo cobrado de esas ventas (asignaciones vigentes de cobros pagados, sin pasar del total de cada venta),
 *    igual que el listado de ventas. Pendiente = vendido - cobrado.
 *  - Gastos: movimientos de caja de tipo gasto, netos de sus correcciones.
 *  - Horas: duración de las citas completadas por empleado (no es un fichaje).
 */
const STATS_NO_DATA = 'Sin dato';
const STATS_MAX_DAYS = 3660;

const STATS_SALES_VIEWS = ['general' => 'General', 'categoria' => 'Por categoría', 'servicio' => 'Por servicios', 'genero' => 'Por género'];
const STATS_EXPENSE_VIEWS = ['general' => 'General', 'categoria' => 'Por categoría'];

/** Primer y último día del mes en curso, para el período por defecto. */
function statsDefaultRange(?DateTimeImmutable $today = null): array
{
    $today ??= new DateTimeImmutable('today');
    return [$today->format('Y-m-01'), $today->format('Y-m-d')];
}

/**
 * Filtros desde la URL. Devuelve [filtros, error]. Fechas inclusivas; sin fechas se usa el mes en curso.
 * category/service: id o 'none' (sin categoría / sin servicio). gender: female|male|other|none.
 */
function statsFilterInput(array $get): array
{
    [$from, $to] = statsDefaultRange();
    $f = ['from' => input($get, 'from'), 'to' => input($get, 'to'), 'category' => input($get, 'category'), 'service' => input($get, 'service'), 'gender' => input($get, 'gender'), 'employee' => input($get, 'employee')];
    $error = '';
    foreach (['from', 'to'] as $k) {
        if ($f[$k] !== '' && !validDate($f[$k])) { $error = 'Revisá las fechas: usá el selector de fecha.'; $f[$k] = ''; }
    }
    if ($error === '' && $f['from'] !== '' && $f['to'] !== '' && $f['from'] > $f['to']) { $error = 'La fecha "desde" no puede ser posterior a "hasta".'; $f['from'] = $f['to'] = ''; }
    if ($error === '' && $f['from'] !== '' && $f['to'] !== '' && (new DateTimeImmutable($f['from']))->diff(new DateTimeImmutable($f['to']))->days > STATS_MAX_DAYS) { $error = 'El período no puede superar los 10 años.'; $f['from'] = $f['to'] = ''; }
    if ($f['from'] === '') $f['from'] = $from;
    if ($f['to'] === '') $f['to'] = $to;
    if ($f['from'] > $f['to']) $f['from'] = $f['to'];
    foreach (['category', 'service', 'employee'] as $k) {
        if ($f[$k] !== 'none' && !ctype_digit($f[$k])) $f[$k] = '';
        if ($k === 'employee' && $f[$k] === 'none') $f[$k] = '';
    }
    if ($f['gender'] !== 'none' && !isset(CLIENT_GENDERS[$f['gender']])) $f['gender'] = '';
    return [$f, $error];
}

/** Reparte $total en partes proporcionales a $weights con el método del mayor resto: la suma da siempre exactamente $total. */
function statsProrate(int $total, array $weights): array
{
    $sum = array_sum($weights);
    $out = array_fill_keys(array_keys($weights), 0);
    if ($total <= 0 || $sum <= 0) return $out;
    $remainders = [];
    $given = 0;
    foreach ($weights as $key => $w) {
        $share = intdiv($total * $w, $sum);
        $out[$key] = $share;
        $given += $share;
        $remainders[$key] = ($total * $w) % $sum;
    }
    arsort($remainders);
    foreach (array_keys($remainders) as $key) {
        if ($given >= $total) break;
        $out[$key]++;
        $given++;
    }
    return $out;
}

/**
 * Ítems de las ventas vigentes del período con su parte proporcional de lo cobrado. Cada fila:
 * sale_id, date (Y-m-d), total, collected (centavos), category_id|null, category, service_id|null, service, gender|null.
 * La parte cobrada de cada venta se reparte entre sus ítems según su importe, y las partes suman exactamente lo cobrado.
 */
function statsSaleItems(string $from, string $to): array
{
    $rows = query("SELECT s.id AS sale_id, DATE(s.created_at) AS sale_date, s.total AS sale_total, i.id AS item_id, i.total_amount, i.description,
            i.service_id, sv.name AS service_name, sv.category_id, sc.name AS category_name, c.gender,
            COALESCE((SELECT SUM(pa.amount) FROM payment_allocations pa JOIN payments p ON p.id=pa.payment_id WHERE pa.sale_id=s.id AND pa.released_at IS NULL AND p.status='paid'),0) AS paid
        FROM sales s JOIN sale_items i ON i.sale_id=s.id JOIN clients c ON c.id=s.client_id
        LEFT JOIN services sv ON sv.id=i.service_id LEFT JOIN service_categories sc ON sc.id=sv.category_id
        WHERE s.status='confirmed' AND DATE(s.created_at) BETWEEN ? AND ? ORDER BY s.id, i.id", [$from, $to])->fetchAll();
    $bySale = [];
    foreach ($rows as $r) $bySale[$r['sale_id']][] = $r;
    $items = [];
    foreach ($bySale as $saleId => $lines) {
        $saleTotal = decimalToCents($lines[0]['sale_total']);
        $collected = min(decimalToCents($lines[0]['paid']), $saleTotal);
        $weights = [];
        foreach ($lines as $l) $weights[$l['item_id']] = decimalToCents($l['total_amount']);
        $parts = statsProrate($collected, $weights);
        foreach ($lines as $l) {
            $items[] = [
                'sale_id' => (int)$saleId, 'date' => $l['sale_date'], 'total' => $weights[$l['item_id']], 'collected' => $parts[$l['item_id']],
                'category_id' => $l['category_id'] === null ? null : (int)$l['category_id'], 'category' => $l['category_name'] ?? STATS_NO_DATA,
                'service_id' => $l['service_id'] === null ? null : (int)$l['service_id'], 'service' => $l['service_name'] ?? ($l['service_id'] === null ? 'Ítem libre' : STATS_NO_DATA),
                'gender' => $l['gender'],
            ];
        }
    }
    return $items;
}

/** Filtra ítems por categoría, servicio y género ('none' = sin dato). */
function statsFilterItems(array $items, array $f): array
{
    return array_values(array_filter($items, static function (array $i) use ($f): bool {
        if ($f['category'] !== '' && ($f['category'] === 'none' ? $i['category_id'] !== null : (string)$i['category_id'] !== $f['category'])) return false;
        if ($f['service'] !== '' && ($f['service'] === 'none' ? $i['service_id'] !== null : (string)$i['service_id'] !== $f['service'])) return false;
        if ($f['gender'] !== '' && ($f['gender'] === 'none' ? $i['gender'] !== null : $i['gender'] !== $f['gender'])) return false;
        return true;
    }));
}

/** Agrupa filas [clave, etiqueta, vendido, cobrado] sumando; ordena por vendido descendente (y por etiqueta). */
function statsGroup(array $entries): array
{
    $groups = [];
    foreach ($entries as [$key, $label, $sold, $collected, $sale]) {
        $g = &$groups[$key];
        $g ??= ['label' => $label, 'sold' => 0, 'collected' => 0, 'sales' => []];
        $g['sold'] += $sold; $g['collected'] += $collected; $g['sales'][$sale] = true;
        unset($g);
    }
    $out = [];
    foreach ($groups as $g) $out[] = ['label' => $g['label'], 'sold' => $g['sold'], 'collected' => $g['collected'], 'pending' => $g['sold'] - $g['collected'], 'sales' => count($g['sales'])];
    usort($out, static fn($a, $b) => [$b['sold'], $a['label']] <=> [$a['sold'], $b['label']]);
    return $out;
}

/** Granularidad de las series: por día hasta 31 días, por mes después. */
function statsBucket(string $from, string $to): string
{
    return (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days <= 31 ? 'day' : 'month';
}

function statsBucketKey(string $date, string $bucket): string
{
    return $bucket === 'day' ? $date : substr($date, 0, 7);
}

/** Informe de ventas. $view: general|categoria|servicio|genero. Devuelve ['rows' => filas, 'totals' => totales, 'sales' => nº de ventas]. */
function statsSales(string $view, array $f): array
{
    $items = statsFilterItems(statsSaleItems($f['from'], $f['to']), $f);
    $bucket = statsBucket($f['from'], $f['to']);
    $entries = [];
    foreach ($items as $i) {
        [$key, $label] = match ($view) {
            'categoria' => [$i['category_id'] ?? 0, $i['category']],
            'servicio' => [$i['service_id'] ?? 0, $i['service']],
            'genero' => [$i['gender'] ?? 'none', CLIENT_GENDERS[$i['gender'] ?? ''] ?? STATS_NO_DATA],
            default => [statsBucketKey($i['date'], $bucket), statsBucketKey($i['date'], $bucket)],
        };
        $entries[] = [$key, $label, $i['total'], $i['collected'], $i['sale_id']];
    }
    $rows = statsGroup($entries);
    if ($view === 'general') usort($rows, static fn($a, $b) => $a['label'] <=> $b['label']);
    $sold = array_sum(array_column($items, 'total'));
    $collected = array_sum(array_column($items, 'collected'));
    return ['rows' => $rows, 'totals' => ['sold' => $sold, 'collected' => $collected, 'pending' => $sold - $collected], 'sales' => count(array_unique(array_column($items, 'sale_id'))), 'bucket' => $bucket];
}

/** Informe de gastos: movimientos de caja de tipo gasto del período, netos de correcciones. $view: general|categoria. */
function statsExpenses(string $view, array $f): array
{
    $where = "m.kind='expense' AND DATE(m.created_at) BETWEEN ? AND ?"; $params = [$f['from'], $f['to']];
    if ($f['category'] === 'none') $where .= ' AND m.category_id IS NULL';
    elseif ($f['category'] !== '') { $where .= ' AND m.category_id = ?'; $params[] = (int)$f['category']; }
    $rows = query("SELECT DATE(m.created_at) AS d, m.category_id, ec.name AS category, m.direction, m.amount FROM cash_movements m LEFT JOIN expense_categories ec ON ec.id=m.category_id WHERE $where", $params)->fetchAll();
    $bucket = statsBucket($f['from'], $f['to']);
    $groups = []; $total = 0; $count = 0;
    foreach ($rows as $r) {
        $cents = decimalToCents($r['amount']) * ($r['direction'] === 'out' ? 1 : -1);
        if ($view === 'categoria') { $key = $r['category_id'] ?? 0; $label = $r['category'] ?? 'Sin categoría'; }
        else { $key = $label = statsBucketKey($r['d'], $bucket); }
        $g = &$groups[$key];
        $g ??= ['label' => $label, 'amount' => 0, 'count' => 0];
        $g['amount'] += $cents; $g['count'] += $r['direction'] === 'out' ? 1 : -1;
        unset($g);
        $total += $cents; $count += $r['direction'] === 'out' ? 1 : -1;
    }
    // Un grupo que quedó en cero por una corrección no aporta información.
    $out = array_values(array_filter($groups, static fn($g) => $g['amount'] !== 0 || $g['count'] !== 0));
    if ($view === 'categoria') usort($out, static fn($a, $b) => [$b['amount'], $a['label']] <=> [$a['amount'], $b['label']]);
    else usort($out, static fn($a, $b) => $a['label'] <=> $b['label']);
    return ['rows' => $out, 'total' => $total, 'count' => $count, 'bucket' => $bucket];
}

/** Horas por empleado: citas completadas del período (por fecha de inicio) y su duración en minutos. */
function statsHours(array $f): array
{
    $where = "a.status='completed' AND DATE(a.starts_at) BETWEEN ? AND ?"; $params = [$f['from'], $f['to']];
    if (ctype_digit($f['employee'])) { $where .= ' AND a.employee_id = ?'; $params[] = (int)$f['employee']; }
    $rows = query("SELECT a.employee_id, u.name, COUNT(*) AS appointments, COALESCE(SUM(TIMESTAMPDIFF(MINUTE, a.starts_at, a.ends_at)),0) AS minutes
        FROM appointments a JOIN employees e ON e.id=a.employee_id JOIN users u ON u.id=e.user_id WHERE $where GROUP BY a.employee_id, u.name ORDER BY minutes DESC, u.name", $params)->fetchAll();
    $out = []; $minutes = 0; $count = 0;
    foreach ($rows as $r) {
        $out[] = ['label' => $r['name'], 'appointments' => (int)$r['appointments'], 'minutes' => (int)$r['minutes']];
        $minutes += (int)$r['minutes']; $count += (int)$r['appointments'];
    }
    return ['rows' => $out, 'minutes' => $minutes, 'count' => $count];
}

/** 150 -> "2 h 30 min". */
function statsDuration(int $minutes): string
{
    $h = intdiv($minutes, 60); $m = $minutes % 60;
    return $h > 0 ? ($m > 0 ? "$h h $m min" : "$h h") : "$m min";
}
