<?php
declare(strict_types=1);

function daysOfWeek(): array {
    return [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];
}

function employeeVacations(int $userId): array {
    return query(
        'SELECT * FROM employee_vacations WHERE user_id = ? ORDER BY starts_at DESC',
        [$userId]
    )->fetchAll();
}

function addEmployeeVacation(int $userId, string $description, string $startsAt, string $endsAt): void {
    if (!validDate($startsAt) || !validDate($endsAt)) {
        throw new DomainException('Las fechas de inicio y fin deben ser válidas.');
    }
    if ($endsAt < $startsAt) {
        throw new DomainException('La fecha de fin no puede ser anterior a la de inicio.');
    }
    $description = trim(substr($description, 0, 190));
    if ($description === '') {
        throw new DomainException('La descripción no puede estar vacía.');
    }
    query(
        'INSERT INTO employee_vacations (user_id, description, starts_at, ends_at) VALUES (?, ?, ?, ?)',
        [$userId, $description, $startsAt, $endsAt]
    );
}

function deleteEmployeeVacation(int $vacationId, int $currentUserId, bool $isAdmin): void {
    if ($isAdmin) {
        query('DELETE FROM employee_vacations WHERE id = ?', [$vacationId]);
    } else {
        query('DELETE FROM employee_vacations WHERE id = ? AND user_id = ?', [$vacationId, $currentUserId]);
    }
}

function employeeSchedule(int $userId): array {
    $rows = query('SELECT * FROM employee_schedules WHERE user_id = ?', [$userId])->fetchAll();
    $byDay = [];
    foreach ($rows as $r) {
        $byDay[(int)$r['day_of_week']] = $r;
    }
    $result = [];
    foreach (daysOfWeek() as $day => $name) {
        $result[$day] = [
            'day_of_week' => $day,
            'name' => $name,
            'start_time' => $byDay[$day]['start_time'] ?? '09:00',
            'end_time' => $byDay[$day]['end_time'] ?? '18:00',
            'is_working' => isset($byDay[$day]) ? (bool)$byDay[$day]['is_working'] : ($day <= 5),
        ];
    }
    return $result;
}

function saveEmployeeSchedule(int $userId, array $scheduleData): void {
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO employee_schedules (user_id, day_of_week, start_time, end_time, is_working)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE start_time = VALUES(start_time), end_time = VALUES(end_time), is_working = VALUES(is_working)'
    );
    foreach (daysOfWeek() as $day => $name) {
        $isWorking = !empty($scheduleData[$day]['is_working']);
        $start = !empty($scheduleData[$day]['start_time']) ? substr(trim($scheduleData[$day]['start_time']), 0, 8) : null;
        $end = !empty($scheduleData[$day]['end_time']) ? substr(trim($scheduleData[$day]['end_time']), 0, 8) : null;
        if ($start && strlen($start) === 5) $start .= ':00';
        if ($end && strlen($end) === 5) $end .= ':00';
        if ($isWorking) {
            if (!$start || !$end) {
                throw new DomainException("El día $name requiere horario de inicio y fin.");
            }
            if ($end <= $start) {
                throw new DomainException("El horario de fin debe ser posterior al de inicio para el día $name.");
            }
        }
        $stmt->execute([$userId, $day, $start, $end, $isWorking ? 1 : 0]);
    }
}

function businessSchedule(): array {
    $rows = query('SELECT * FROM business_schedules ORDER BY day_of_week')->fetchAll();
    $byDay = [];
    foreach ($rows as $r) {
        $byDay[(int)$r['day_of_week']] = $r;
    }
    $result = [];
    foreach (daysOfWeek() as $day => $name) {
        $result[$day] = [
            'day_of_week' => $day,
            'name' => $name,
            'start_time' => $byDay[$day]['start_time'] ?? ($day <= 5 ? '08:00' : ($day === 6 ? '09:00' : '00:00')),
            'end_time' => $byDay[$day]['end_time'] ?? ($day <= 5 ? '20:00' : ($day === 6 ? '14:00' : '00:00')),
            'is_open' => isset($byDay[$day]) ? (bool)$byDay[$day]['is_open'] : ($day <= 6),
        ];
    }
    return $result;
}

function saveBusinessSchedule(array $scheduleData): void {
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO business_schedules (day_of_week, start_time, end_time, is_open)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE start_time = VALUES(start_time), end_time = VALUES(end_time), is_open = VALUES(is_open)'
    );
    foreach (daysOfWeek() as $day => $name) {
        $isOpen = !empty($scheduleData[$day]['is_open']);
        $start = !empty($scheduleData[$day]['start_time']) ? substr(trim($scheduleData[$day]['start_time']), 0, 8) : null;
        $end = !empty($scheduleData[$day]['end_time']) ? substr(trim($scheduleData[$day]['end_time']), 0, 8) : null;
        if ($start && strlen($start) === 5) $start .= ':00';
        if ($end && strlen($end) === 5) $end .= ':00';
        if ($isOpen) {
            if (!$start || !$end) {
                throw new DomainException("El día $name requiere horario de apertura y cierre.");
            }
            if ($end <= $start) {
                throw new DomainException("La hora de cierre debe ser posterior a la de apertura para el día $name.");
            }
        }
        $stmt->execute([$day, $start, $end, $isOpen ? 1 : 0]);
    }
}

function holidaysList(?string $search = null): array {
    if ($search !== null && trim($search) !== '') {
        $term = '%' . trim($search) . '%';
        return query('SELECT * FROM holidays WHERE description LIKE ? OR holiday_date LIKE ? ORDER BY holiday_date ASC', [$term, $term])->fetchAll();
    }
    return query('SELECT * FROM holidays ORDER BY holiday_date ASC')->fetchAll();
}

function addHoliday(string $date, string $description): void {
    if (!validDate($date)) {
        throw new DomainException('La fecha del feriado no es válida.');
    }
    $description = trim(substr($description, 0, 190));
    if ($description === '') {
        throw new DomainException('La descripción del feriado es obligatoria.');
    }
    query('INSERT INTO holidays (holiday_date, description) VALUES (?, ?)', [$date, $description]);
}

function deleteHoliday(int $id): void {
    query('DELETE FROM holidays WHERE id = ?', [$id]);
}

function checkAppointmentAvailability(int $employeeUserId, string $startsAt, string $endsAt): ?string {
    $startDate = substr($startsAt, 0, 10);
    $startTime = substr($startsAt, 11, 8);
    $endTime = substr($endsAt, 11, 8);
    if (strlen($startTime) === 5) $startTime .= ':00';
    if (strlen($endTime) === 5) $endTime .= ':00';

    // 1. Verificar si es feriado en el centro
    $holiday = query('SELECT description FROM holidays WHERE holiday_date = ?', [$startDate])->fetch();
    if ($holiday) {
        return 'No se pueden agendar turnos en días feriados (' . $holiday['description'] . ').';
    }

    // 2. Verificar vacaciones o ausencias del profesional
    $vacation = query(
        'SELECT description FROM employee_vacations WHERE user_id = ? AND starts_at <= ? AND ends_at >= ? LIMIT 1',
        [$employeeUserId, $startDate, $startDate]
    )->fetch();
    if ($vacation) {
        return 'El profesional se encuentra ausente o de vacaciones (' . $vacation['description'] . ').';
    }

    // 3. Día de la semana (1=Lunes, 7=Domingo)
    $dayOfWeek = (int)(new DateTimeImmutable($startDate))->format('N');

    // 4. Verificar horario del centro
    $biz = query('SELECT * FROM business_schedules WHERE day_of_week = ?', [$dayOfWeek])->fetch();
    if ($biz && (!$biz['is_open'] || $startTime < $biz['start_time'] || $endTime > $biz['end_time'])) {
        return 'El centro se encuentra cerrado en el horario seleccionado.';
    }

    // 5. Verificar horario del profesional
    $empSched = query('SELECT * FROM employee_schedules WHERE user_id = ? AND day_of_week = ?', [$employeeUserId, $dayOfWeek])->fetch();
    if ($empSched && (!$empSched['is_working'] || $startTime < $empSched['start_time'] || $endTime > $empSched['end_time'])) {
        return 'El profesional no atiende en el horario seleccionado para ese día.';
    }

    return null;
}
