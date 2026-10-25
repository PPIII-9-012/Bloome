<?php
declare(strict_types=1);

function agendaContext(array $source): array {
    $context=[];
    $date=input($source,'date');
    if (validDate($date)) $context['date']=$date;
    $mode=input($source,'mode');
    if (in_array($mode,['day','week','month'],true)) $context['mode']=$mode;
    $employee=input($source,'employee');
    if (ctype_digit($employee) && strlen($employee)<11 && (int)$employee>0) $context['employee']=$employee;
    $status=input($source,'status');
    if (isset(appointmentStatuses()[$status])) $context['status']=$status;
    return $context;
}

function appointmentDraft(string $token): ?array {
    $draft=$_SESSION['appointment_drafts'][$token]??null;
    if (!is_array($draft) || $draft['created']<time()-3600) return null;
    return $draft;
}

function appointmentDraftTarget(array $draft, string $token): array {
    return ['page'=>$draft['id']===null?'appointment-new':'appointment-edit', 'params'=>['draft'=>$token]+($draft['id']===null?[]:['id'=>$draft['id']])];
}

function agendaPeriod(string $date, string $mode): array {
    $anchor = new DateTimeImmutable($date);
    $start = match ($mode) {
        'week' => $anchor->modify('-'.((int)$anchor->format('N')-1).' days'),
        'month' => $anchor->modify('first day of this month'),
        default => $anchor,
    };
    $step = match ($mode) { 'week' => 'week', 'month' => 'month', default => 'day' };
    return ['from'=>$start->format('Y-m-d'), 'until'=>$start->modify('+1 '.$step)->format('Y-m-d'),
        'previous'=>$start->modify('-1 '.$step)->format('Y-m-d'), 'next'=>$start->modify('+1 '.$step)->format('Y-m-d')];
}

/** Resolves the date/period/filters shared by the agenda screen, its print view and its CSV export. */
function agendaView(array $source): array {
    $date = input($source, 'date', date('Y-m-d'));
    if (!validDate($date)) $date = date('Y-m-d');
    $mode = input($source, 'mode', 'day');
    if (!in_array($mode, ['day','week','month'], true)) $mode = 'day';
    $employeeFilter = input($source, 'employee');
    $statusFilter = input($source, 'status');
    $employees = query('SELECT e.id,u.name,e.active,u.active AS user_active FROM employees e JOIN users u ON u.id=e.user_id ORDER BY u.name,e.id')->fetchAll();
    $filterError = ($employeeFilter !== '' && (!ctype_digit($employeeFilter) || !in_array($employeeFilter, array_map('strval', array_column($employees, 'id')), true)))
        || ($statusFilter !== '' && !isset(appointmentStatuses()[$statusFilter]));
    $period = agendaPeriod($date, $mode);
    $appointments = $filterError ? [] : appointmentsBetween($period['from'], $period['until'], null,
        $employeeFilter === '' ? null : (int)$employeeFilter, $statusFilter === '' ? null : $statusFilter);
    return compact('date', 'mode', 'employees', 'employeeFilter', 'statusFilter', 'filterError', 'period', 'appointments');
}

/** Neutralises spreadsheet formulas: cells starting with = + - @ tab or CR are prefixed with an apostrophe. */
function csvSafeCell(string $value): string {
    $value = str_replace("\0", '', $value);
    return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'" . $value : $value;
}

/** Builds the agenda CSV (UTF-8 with BOM, ';' separator for Spanish-locale spreadsheets). */
function agendaCsv(array $appointments): string {
    $out = fopen('php://temp', 'r+');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Fecha','Inicio','Fin','Cliente','Servicios','Empleado','Estado'], ';', '"', '');
    foreach ($appointments as $appointment) {
        fputcsv($out, array_map('csvSafeCell', [
            substr($appointment['starts_at'], 8, 2).'/'.substr($appointment['starts_at'], 5, 2).'/'.substr($appointment['starts_at'], 0, 4),
            substr($appointment['starts_at'], 11, 5), substr($appointment['ends_at'], 11, 5),
            trim($appointment['first_name'].' '.$appointment['last_name']), (string)$appointment['service_name'],
            (string)$appointment['employee_name'], appointmentStatuses()[$appointment['status']] ?? (string)$appointment['status'],
        ]), ';', '"', '');
    }
    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);
    return str_replace("\n", "\r\n", $csv);
}
