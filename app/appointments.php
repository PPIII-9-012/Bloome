<?php
declare(strict_types=1);

function appointmentStatuses(): array {
    return ['pending'=>'Pendiente','confirmed'=>'Confirmada','completed'=>'Completada','cancelled'=>'Cancelada','missed'=>'Ausente'];
}
function appointmentRecord(int $id, bool $lock = false): array|false {
    $suffix=$lock?' FOR UPDATE':'';
    $row = query('SELECT * FROM appointments WHERE id=?'.$suffix, [$id])->fetch();
    if ($row) $row['services'] = query('SELECT service_id,price,duration_minutes FROM appointment_services WHERE appointment_id=? ORDER BY service_id'.$suffix, [$id])->fetchAll();
    return $row;
}
function appointmentVersion(array $row): string { return hash('sha256', json_encode($row, JSON_THROW_ON_ERROR)); }
function saveAppointment(int $actorId, ?int $id, array $source, bool $cancel = false): int {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Same lock order as employee administration. Coarse locking is intentional
        // for this single-centre pilot: bookings and account changes serialize.
        $accounts = query('SELECT u.id,u.role,u.active,COALESCE(e.active,1) AS employee_active FROM users u LEFT JOIN employees e ON e.user_id=u.id ORDER BY u.id FOR UPDATE')->fetchAll();
        $actor = null;
        foreach ($accounts as $account) if ((int)$account['id'] === $actorId) $actor=$account;
        if (!$actor || !$actor['active'] || !$actor['employee_active'] || !canEdit($actor)) throw new DomainException('No tenés permiso para modificar turnos.',403);
        $old = $id === null ? false : appointmentRecord($id,true);
        if ($id !== null && !$old) throw new DomainException('Turno no encontrado.',404);
        if ($old && !hash_equals(appointmentVersion($old), input($source,'version'))) throw new DomainException('El turno cambió desde que abriste el formulario. Volvé a abrirlo antes de guardar.',409);
        if ($cancel) {
            if (!$old) throw new DomainException('Turno no encontrado.',404);
            query("UPDATE appointments SET status='cancelled' WHERE id=?",[$id]);
        } else {
            $client = input($source,'client_id'); $employee = input($source,'employee_id');
            $startText = str_replace('T',' ',input($source,'starts_at'));
            $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i',$startText);
            $status = input($source,'status'); $notes = input($source,'notes');
            $serviceIds = $source['services'] ?? [];
            if (!ctype_digit($client) || !ctype_digit($employee) || !$start || $start->format('Y-m-d H:i') !== $startText || !isset(appointmentStatuses()[$status]) || textLength($notes)>5000 || !is_array($serviceIds) || !$serviceIds || count($serviceIds)>30) throw new DomainException('Revisá cliente, empleado, fecha, estado y servicios. Notas: máximo 5000 caracteres.',422);
            foreach ($serviceIds as $serviceId) if (!is_string($serviceId) || !ctype_digit($serviceId) || (int)$serviceId<1) throw new DomainException('Seleccioná servicios válidos.',422);
            $serviceIds = array_map('intval',$serviceIds);
            if (count(array_unique($serviceIds)) !== count($serviceIds)) throw new DomainException('No repitas servicios en el turno.',422);
            if (!query('SELECT id FROM clients WHERE id=? FOR SHARE',[$client])->fetchColumn()) throw new DomainException('El cliente no existe.',422);
            $worker = query('SELECT e.id,e.user_id,e.active,u.active AS user_active FROM employees e JOIN users u ON u.id=e.user_id WHERE e.id=? FOR UPDATE',[$employee])->fetch();
            if (!$worker) throw new DomainException('El empleado no existe.',422);
            $previous = $old ? array_column($old['services'],null,'service_id') : [];
            $details=[]; $duration=0;
            sort($serviceIds);
            foreach ($serviceIds as $serviceId) {
                $service = query('SELECT * FROM services WHERE id=? FOR SHARE',[$serviceId])->fetch();
                if (!$service || (!$service['active'] && !isset($previous[$serviceId]))) throw new DomainException('Hay un servicio inexistente o inactivo.',422);
                $detail=$previous[$serviceId] ?? ['service_id'=>$serviceId,'price'=>$service['price'],'duration_minutes'=>$service['duration_minutes']];
                $duration+=(int)$detail['duration_minutes']; $details[]=$detail;
            }
            if ($duration<1 || $duration>1440) throw new DomainException('La duración total debe ser de 1 a 1440 minutos.',422);
            $end=$start->modify("+$duration minutes");
            // Legacy appointments may have a recorded interval different from the
            // catalog snapshot. Non-scheduling edits must preserve that interval.
            if ($old && $start->format('Y-m-d H:i:s')===$old['starts_at'] && $serviceIds===array_map('intval',array_column($old['services'],'service_id'))) {
                $end=new DateTimeImmutable($old['ends_at']);
            }
            if ($end->format('Y-m-d')!==$start->format('Y-m-d')) throw new DomainException('El turno debe finalizar dentro del mismo día.',422);
            $startsAt=$start->format('Y-m-d H:i:s'); $endsAt=$end->format('Y-m-d H:i:s');
            $changed = !$old || (int)$old['employee_id'] !== (int)$employee || $old['starts_at'] !== $startsAt || $old['ends_at'] !== $endsAt || ($old['status']==='cancelled' && $status!=='cancelled');
            if ($changed && (!$worker['active'] || !$worker['user_active'])) throw new DomainException('El empleado está inactivo.',422);
            if ($changed && $status!=='cancelled') {
                // Lock configuration and absence ranges through commit, including empty ranges.
                query('SELECT id FROM holidays WHERE holiday_date=? FOR SHARE',[$start->format('Y-m-d')])->fetchAll();
                query('SELECT id FROM employee_vacations WHERE user_id=? FOR SHARE',[$worker['user_id']])->fetchAll();
                query('SELECT id FROM business_schedules WHERE day_of_week=? FOR SHARE',[$start->format('N')])->fetchAll();
                query('SELECT id FROM employee_schedules WHERE user_id=? FOR SHARE',[$worker['user_id']])->fetchAll();
                $reason=checkAppointmentAvailability((int)$worker['user_id'],$startsAt,$endsAt);
                if ($reason) throw new DomainException($reason,422);
                $conflict=query("SELECT id FROM appointments WHERE employee_id=? AND status<>'cancelled' AND starts_at<? AND ends_at>? AND id<>? LIMIT 1 FOR UPDATE",[$employee,$endsAt,$startsAt,$id??0])->fetchColumn();
                if ($conflict) throw new DomainException('El empleado ya tiene un turno en ese horario. Elegí otro horario.',409);
            }
            if ($id===null) {
                if ($status==='cancelled') throw new DomainException('Creá el turno como pendiente o confirmado antes de cancelarlo.',422);
                query('INSERT INTO appointments (client_id,employee_id,starts_at,ends_at,status,notes) VALUES (?,?,?,?,?,?)',[$client,$employee,$startsAt,$endsAt,$status,$notes]);
                $id=(int)$pdo->lastInsertId();
            } else query('UPDATE appointments SET client_id=?,employee_id=?,starts_at=?,ends_at=?,status=?,notes=? WHERE id=?',[$client,$employee,$startsAt,$endsAt,$status,$notes,$id]);
            query('DELETE FROM appointment_services WHERE appointment_id=?',[$id]);
            foreach($details as $detail) query('INSERT INTO appointment_services (appointment_id,service_id,price,duration_minutes) VALUES (?,?,?,?)',[$id,$detail['service_id'],$detail['price'],$detail['duration_minutes']]);
        }
        query('INSERT INTO appointment_audit (appointment_id,actor_id,action) VALUES (?,?,?)',[$id,$actorId,$cancel?'cancelled':($old?'updated':'created')]);
        $pdo->commit();
        return $id;
    } catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function appointmentsBetween(string $from, string $until, ?int $limit = null, ?int $employeeId = null, ?string $status = null): array
{
    $sql = 'SELECT a.*, c.first_name, c.last_name, u.name AS employee_name, e.appointment_color
        FROM appointments a JOIN clients c ON c.id=a.client_id
        JOIN employees e ON e.id=a.employee_id JOIN users u ON u.id=e.user_id
        WHERE a.starts_at >= ? AND a.starts_at < ?';
    $params = [$from, $until];
    if ($employeeId !== null) { $sql .= ' AND a.employee_id=?'; $params[]=$employeeId; }
    if ($status !== null) { $sql .= ' AND a.status=?'; $params[]=$status; }
    $sql .= ' ORDER BY a.starts_at, a.id';
    if ($limit !== null) $sql .= ' LIMIT ' . max(0, $limit);
    $appointments = query($sql, $params)->fetchAll();
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
