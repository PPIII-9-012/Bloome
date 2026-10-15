<?php
declare(strict_types=1);

function appointmentsBetween(string $from, string $until, ?int $limit = null): array
{
    $sql = 'SELECT a.*, c.first_name, c.last_name, u.name AS employee_name, e.appointment_color
        FROM appointments a JOIN clients c ON c.id=a.client_id
        JOIN employees e ON e.id=a.employee_id JOIN users u ON u.id=e.user_id
        WHERE a.starts_at >= ? AND a.starts_at < ? ORDER BY a.starts_at, a.id';
    if ($limit !== null) $sql .= ' LIMIT ' . max(0, $limit);
    $appointments = query($sql, [$from, $until])->fetchAll();
    if (!$appointments) return [];
    $ids = array_column($appointments, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $services = query("SELECT aps.appointment_id, s.name FROM appointment_services aps
        JOIN services s ON s.id=aps.service_id WHERE aps.appointment_id IN ($placeholders)
        ORDER BY aps.id", $ids)->fetchAll();
    $names = [];
    foreach ($services as $service) $names[$service['appointment_id']][] = $service['name'];
    foreach ($appointments as &$appointment) {
        $appointment['service_name'] = implode(' · ', $names[$appointment['id']] ?? []) ?: 'Sin servicios';
    }
    return $appointments;
}
