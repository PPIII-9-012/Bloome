<?php
declare(strict_types=1);

$deny = static function (int $status, string $message) use ($user, $page): never {
    http_response_code($status);
    render('message', compact('user', 'page', 'message') + ['title' => $status === 403 ? 'Acceso restringido' : 'Página no encontrada']);
    exit;
};

if (!canManageEmployees($user)) {
    $deny(403, 'Solo un administrador puede configurar horarios y feriados del centro.');
}

if ($page === 'holidays') {
    $search = substr(input($_GET, 'q'), 0, 100);
    $holidays = holidaysList($search !== '' ? $search : null);
    $title = 'Festivos y Feriados';
    render('holidays', compact('user', 'page', 'holidays', 'search', 'title'));
    exit;
}

if ($page === 'holiday-new' && $method === 'POST') {
    $date = input($_POST, 'date');
    $description = input($_POST, 'description');
    try {
        addHoliday($date, $description);
        flash('Feriado agregado correctamente.');
        redirect('holidays');
    } catch (DomainException $e) {
        flash($e->getMessage());
        redirect('holidays');
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1062) {
            flash('Ya existe un feriado registrado para esa fecha.');
        } else {
            flash('Error al guardar el feriado.');
        }
        redirect('holidays');
    }
}

if ($page === 'holiday-delete' && $method === 'POST') {
    $id = (int)input($_POST, 'id');
    deleteHoliday($id);
    flash('Feriado eliminado.');
    redirect('holidays');
}

if ($page === 'schedule') {
    if ($method === 'POST') {
        $daysInput = $_POST['days'] ?? [];
        $scheduleData = [];
        foreach (daysOfWeek() as $day => $name) {
            $scheduleData[$day] = [
                'is_open' => !empty($daysInput[$day]['is_open']),
                'start_time' => $daysInput[$day]['start_time'] ?? null,
                'end_time' => $daysInput[$day]['end_time'] ?? null,
            ];
        }
        try {
            saveBusinessSchedule($scheduleData);
            flash('Horario de atención del centro guardado correctamente.');
            redirect('schedule');
        } catch (DomainException $e) {
            flash($e->getMessage());
            redirect('schedule');
        }
    }
    $schedule = businessSchedule();
    $title = 'Configuración del Horario';
    render('schedule', compact('user', 'page', 'schedule', 'title'));
    exit;
}

$deny(404, 'Ruta de horarios o feriados no encontrada.');
