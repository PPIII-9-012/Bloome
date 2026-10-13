<?php
declare(strict_types=1);

$deny = static function (int $status, string $message) use ($user, $page): never {
    http_response_code($status);
    render('message', compact('user', 'page', 'message') + ['title' => $status === 422 ? 'Revisá los datos' : ($status === 403 ? 'Acceso restringido' : 'Página no encontrada')]);
    exit;
};

if (!canManageEmployees($user)) {
    $deny(403, 'Solo un administrador puede configurar horarios y feriados del centro.');
}

if (!in_array($method, ['GET','POST'], true) || ($page === 'holidays' && $method !== 'GET')) $deny(405, 'Método no permitido.');

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
        $deny(422, $e->getMessage());
        redirect('holidays');
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1062) {
            $deny(422, 'Ya existe un feriado registrado para esa fecha.');
        } else {
            throw $e;
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
        $scheduleData = $_POST['days'] ?? [];
        try {
            saveBusinessSchedule($scheduleData);
            flash('Horario de atención del centro guardado correctamente.');
            redirect('schedule');
        } catch (DomainException $e) {
            $deny(422, $e->getMessage());
            redirect('schedule');
        }
    }
    $schedule = businessSchedule();
    $title = 'Configuración del Horario';
    render('schedule', compact('user', 'page', 'schedule', 'title'));
    exit;
}

$deny(404, 'Ruta de horarios o feriados no encontrada.');
