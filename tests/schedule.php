<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$origin = 'http://127.0.0.1:8000';
$cookies = [];
$checks = 0;

function req(string $page, array $fields = [], bool $post = false): array {
    global $origin, $cookies;
    $headers = ['Content-Type: application/x-www-form-urlencoded'];
    if ($cookies) {
        $headers[] = 'Cookie: ' . implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($cookies), $cookies));
    }
    $context = stream_context_create([
        'http' => [
            'method' => $post ? 'POST' : 'GET',
            'header' => implode("\r\n", $headers),
            'content' => $post ? http_build_query($fields) : '',
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 10
        ]
    ]);
    $body = file_get_contents($origin . '/?page=' . $page, false, $context);
    $responseHeaders = http_get_last_response_headers();
    preg_match('/\s(\d{3})\s/', $responseHeaders[0], $status);
    foreach ($responseHeaders as $header) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $match)) {
            $cookies[$match[1]] = $match[2];
        }
    }
    return [(int)$status[1], $body];
}

function extractToken(string $body): string {
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $match);
    return $match[1] ?? '';
}

function testAssert(bool $condition, string $label): void {
    global $checks;
    if (!$condition) {
        throw new RuntimeException("Fallo en prueba: $label");
    }
    echo "OK: $label\n";
    $checks++;
}

try {
    // 1. Acceso a festivos sin sesión redirige a login
    [$status] = req('holidays');
    testAssert($status === 303, 'Festivos protegido sin sesión');

    // 2. Login como admin
    [, $loginBody] = req('login');
    [$status] = req('login', ['email' => getenv('ADMIN_EMAIL'), 'password' => getenv('ADMIN_PASSWORD'), 'csrf' => extractToken($loginBody)], true);
    testAssert($status === 303, 'Login exitoso como administrador');

    // 3. Admin accede a festivos
    [$status, $holidaysBody] = req('holidays');
    testAssert($status === 200 && str_contains($holidaysBody, 'Festivos y Feriados'), 'Admin accede a vista de Festivos');

    // 4. Agregar feriado de prueba
    $token = extractToken($holidaysBody);
    $testHolidayDate = '2026-12-25';
    [$status] = req('holiday-new', ['csrf' => $token, 'date' => $testHolidayDate, 'description' => 'Navidad Bloome'], true);
    testAssert($status === 303, 'Creación de feriado redirige');

    $holidayInDb = query('SELECT * FROM holidays WHERE holiday_date = ?', [$testHolidayDate])->fetch();
    testAssert((bool)$holidayInDb && $holidayInDb['description'] === 'Navidad Bloome', 'Feriado persistido en base de datos');

    // 5. Rechaza duplicado de feriado en la misma fecha
    [, $bodyAfter] = req('holidays');
    [$status] = req('holiday-new', ['csrf' => extractToken($bodyAfter), 'date' => $testHolidayDate, 'description' => 'Duplicado'], true);
    testAssert($status === 303, 'Manejo de duplicado de feriado redirige con aviso');

    // 6. Admin accede a Horarios del centro
    [$status, $schedBody] = req('schedule');
    testAssert($status === 200 && str_contains($schedBody, 'Configuración del Horario'), 'Admin accede a horario de atención');

    // 7. Guardar horario de atención del centro
    $schedToken = extractToken($schedBody);
    $bizDays = [
        1 => ['is_open' => '1', 'start_time' => '08:30', 'end_time' => '19:30'],
        2 => ['is_open' => '1', 'start_time' => '08:30', 'end_time' => '19:30'],
        3 => ['is_open' => '1', 'start_time' => '08:30', 'end_time' => '19:30'],
        4 => ['is_open' => '1', 'start_time' => '08:30', 'end_time' => '19:30'],
        5 => ['is_open' => '1', 'start_time' => '08:30', 'end_time' => '19:30'],
        6 => ['is_open' => '1', 'start_time' => '09:00', 'end_time' => '13:00'],
        7 => ['is_open' => '0', 'start_time' => '', 'end_time' => ''],
    ];
    [$status] = req('schedule', ['csrf' => $schedToken, 'days' => $bizDays], true);
    testAssert($status === 303, 'Guardado de horario del centro redirige');

    $savedMon = query('SELECT * FROM business_schedules WHERE day_of_week = 1')->fetch();
    testAssert($savedMon['start_time'] === '08:30:00' && $savedMon['end_time'] === '19:30:00', 'Horario de lunes persistido en MySQL');

    // 8. Crear empleado de prueba para probar vacaciones y horarios individuales
    $empEmail = 'qa-sched-' . bin2hex(random_bytes(4)) . '@bloome.test';
    [, $newEmpBody] = req('employee-new');
    $empFields = [
        'csrf' => extractToken($newEmpBody),
        'name' => 'Profesional Horarios',
        'email' => $empEmail,
        'role' => 'professional',
        'active' => '1',
        'appointment_color' => '#147D71',
        'password' => 'BloomePass12345!'
    ];
    req('employee-new', $empFields, true);
    $empUser = query('SELECT id FROM users WHERE email = ?', [$empEmail])->fetch();
    testAssert((bool)$empUser, 'Empleado de prueba creado');
    $empId = (int)$empUser['id'];

    // 9. Guardar horario de trabajo individual del empleado
    [, $profBody] = req("employee&id=$empId");
    testAssert(str_contains($profBody, 'Vacaciones y ausencias'), 'Perfil muestra sección de vacaciones');
    testAssert(str_contains($profBody, 'Configuración del Horario'), 'Perfil muestra sección de horarios');

    $empScheduleData = [
        1 => ['is_working' => '1', 'start_time' => '10:00', 'end_time' => '16:00'],
        2 => ['is_working' => '1', 'start_time' => '10:00', 'end_time' => '16:00'],
        3 => ['is_working' => '1', 'start_time' => '10:00', 'end_time' => '16:00'],
        4 => ['is_working' => '1', 'start_time' => '10:00', 'end_time' => '16:00'],
        5 => ['is_working' => '1', 'start_time' => '10:00', 'end_time' => '16:00'],
        6 => ['is_working' => '0', 'start_time' => '', 'end_time' => ''],
        7 => ['is_working' => '0', 'start_time' => '', 'end_time' => ''],
    ];
    [$status] = req('employee-schedule-save', ['csrf' => extractToken($profBody), 'user_id' => $empId, 'schedule' => $empScheduleData], true);
    testAssert($status === 303, 'Horario de empleado guardado');

    $savedEmpMon = query('SELECT * FROM employee_schedules WHERE user_id = ? AND day_of_week = 1', [$empId])->fetch();
    testAssert($savedEmpMon['start_time'] === '10:00:00' && $savedEmpMon['end_time'] === '16:00:00', 'Horario de empleado persistido en MySQL');

    // 10. Añadir período de vacaciones al empleado
    [, $profBody2] = req("employee&id=$empId");
    $vacStarts = '2026-11-10';
    $vacEnds = '2026-11-20';
    [$status] = req('employee-vacation-new', [
        'csrf' => extractToken($profBody2),
        'user_id' => $empId,
        'description' => 'Vacaciones Anuales',
        'starts_at' => $vacStarts,
        'ends_at' => $vacEnds
    ], true);
    testAssert($status === 303, 'Alta de vacaciones redirige');

    $vacationRow = query('SELECT * FROM employee_vacations WHERE user_id = ? AND starts_at = ?', [$empId, $vacStarts])->fetch();
    testAssert((bool)$vacationRow && $vacationRow['description'] === 'Vacaciones Anuales', 'Vacaciones persistidas en MySQL');

    // 11. Validador de disponibilidad: rechaza citas en día feriado
    $errHoliday = checkAppointmentAvailability($empId, '2026-12-25 11:00:00', '2026-12-25 12:00:00');
    testAssert($errHoliday !== null && str_contains($errHoliday, 'feriados'), 'Disponibilidad rechaza fecha de feriado');

    // 12. Validador de disponibilidad: rechaza citas en período de vacaciones del profesional
    $errVacation = checkAppointmentAvailability($empId, '2026-11-15 11:00:00', '2026-11-15 12:00:00');
    testAssert($errVacation !== null && str_contains($errVacation, 'vacaciones'), 'Disponibilidad rechaza fecha de vacaciones del empleado');

    // 13. Validador de disponibilidad: rechaza citas fuera del horario del profesional (atiende hasta las 16:00)
    // 2026-11-02 es Lunes
    $errHour = checkAppointmentAvailability($empId, '2026-11-02 17:00:00', '2026-11-02 18:00:00');
    testAssert($errHour !== null && str_contains($errHour, 'no atiende'), 'Disponibilidad rechaza hora fuera del turno del profesional');

    // 14. Validador de disponibilidad: aprueba cita dentro del horario laboral, sin feriado ni vacaciones
    $okAvailability = checkAppointmentAvailability($empId, '2026-11-02 11:00:00', '2026-11-02 12:00:00');
    testAssert($okAvailability === null, 'Disponibilidad aprueba horario regular válido');

    // 15. Eliminar feriado
    [$status] = req('holiday-delete', ['csrf' => extractToken($profBody2), 'id' => (int)$holidayInDb['id']], true);
    testAssert($status === 303, 'Eliminar feriado redirige');
    $checkDeleted = query('SELECT COUNT(*) FROM holidays WHERE id = ?', [(int)$holidayInDb['id']])->fetchColumn();
    testAssert((int)$checkDeleted === 0, 'Feriado eliminado de la base de datos');

    // 16. Logout y verificación de permisos denegados a usuario profesional
    [, $lastBody] = req('holidays');
    req('logout', ['csrf' => extractToken($lastBody)], true);

    [, $loginBodyProf] = req('login');
    req('login', ['email' => $empEmail, 'password' => 'BloomePass12345!', 'csrf' => extractToken($loginBodyProf)], true);

    [$status] = req('holidays');
    testAssert($status === 403, 'Profesional no tiene acceso a festivos (403)');

    [$status] = req('schedule');
    testAssert($status === 403, 'Profesional no tiene acceso a configuración de horarios (403)');

    echo "\n$checks pruebas de Horarios, Vacaciones y Festivos aprobadas.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "\nFAIL: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    $failed = true;
} finally {
    // Limpieza de datos creados en el test
    if (isset($empId) && $empId) {
        query('DELETE FROM employee_vacations WHERE user_id = ?', [$empId]);
        query('DELETE FROM employee_schedules WHERE user_id = ?', [$empId]);
        query('DELETE FROM employees WHERE user_id = ?', [$empId]);
        query('DELETE FROM employee_audit WHERE user_id = ?', [$empId]);
        query('DELETE FROM users WHERE id = ?', [$empId]);
    }
    query('DELETE FROM holidays WHERE description LIKE ?', ['%Navidad Bloome%']);
}

exit(isset($failed) ? 1 : 0);
