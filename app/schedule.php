<?php
declare(strict_types=1);
function daysOfWeek(): array { return [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo']; }
function employeeVacations(int $userId): array { return query('SELECT * FROM employee_vacations WHERE user_id=? ORDER BY starts_at DESC',[$userId])->fetchAll(); }
function scheduleDescription(string $value): string {
    $value=trim($value);
    if ($value==='' || textLength($value)>190) throw new DomainException('Ingresá una descripción de hasta 190 caracteres.',422);
    return $value;
}
function addEmployeeVacation(int $userId,string $description,string $startsAt,string $endsAt): void {
    if (!validDate($startsAt) || !validDate($endsAt) || $endsAt<$startsAt) throw new DomainException('Ingresá fechas válidas; el fin no puede ser anterior al inicio.',422);
    query('INSERT INTO employee_vacations (user_id,description,starts_at,ends_at) VALUES (?,?,?,?)',[$userId,scheduleDescription($description),$startsAt,$endsAt]);
}
function deleteEmployeeVacation(int $vacationId,int $targetId,bool $isAdmin): void {
    if (!$isAdmin) throw new DomainException('Solo un administrador puede gestionar ausencias.',403);
    query('DELETE FROM employee_vacations WHERE id=? AND user_id=?',[$vacationId,$targetId]);
}
function scheduleRows(?int $userId): array {
    $employee=$userId!==null; $flag=$employee?'is_working':'is_open';
    $rows=$employee?query('SELECT * FROM employee_schedules WHERE user_id=?',[$userId])->fetchAll():query('SELECT * FROM business_schedules')->fetchAll();
    $indexed=array_column($rows,null,'day_of_week'); $result=[];
    foreach(daysOfWeek() as $day=>$name) $result[$day]=['day_of_week'=>$day,'name'=>$name,'start_time'=>$indexed[$day]['start_time']??'09:00','end_time'=>$indexed[$day]['end_time']??'18:00','break_start'=>$indexed[$day]['break_start']??'','break_end'=>$indexed[$day]['break_end']??'',$flag=>(bool)($indexed[$day][$flag]??false)];
    return $result;
}
function employeeSchedule(int $userId): array { return scheduleRows($userId); }
function businessSchedule(): array { return scheduleRows(null); }
function validateSchedule(mixed $source,string $flag): array {
    if (!is_array($source)) throw new DomainException('El horario enviado no es válido.',422);
    $result=[];
    foreach(daysOfWeek() as $day=>$name) {
        $row=$source[$day]??[];
        if (!is_array($row)) throw new DomainException("Revisá el horario de $name.",422);
        $enabled=$row[$flag]??'0';
        if (!in_array($enabled,['0','1',0,1,false,true],true)) throw new DomainException("Revisá el estado de $name.",422);
        $enabled=in_array($enabled,['1',1,true],true);
        $start=input($row,'start_time'); $end=input($row,'end_time');
        if ($enabled && (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::00)?$/D',$start) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::00)?$/D',$end) || substr($end,0,5)<=substr($start,0,5))) throw new DomainException("Ingresá horas válidas y un fin posterior al inicio para $name.",422);
        $result[$day]=[$enabled?substr($start,0,5).':00':null,$enabled?substr($end,0,5).':00':null,$enabled?1:0];
        if ($flag==='is_working') {
            $breakStart=input($row,'break_start'); $breakEnd=input($row,'break_end');
            foreach(['break_start','break_end'] as $field) if(isset($row[$field]) && !is_string($row[$field])) throw new DomainException("Revisá el descanso de $name.",422);
            if ($enabled && ($breakStart!=='' || $breakEnd!=='')) {
                $pattern='/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::00)?$/D';
                if (!preg_match($pattern,$breakStart) || !preg_match($pattern,$breakEnd) || substr($breakStart,0,5)<=substr($start,0,5) || substr($breakEnd,0,5)>=substr($end,0,5) || substr($breakStart,0,5)>=substr($breakEnd,0,5)) throw new DomainException("El descanso de $name debe tener inicio y fin, estar dentro de la jornada y terminar después de comenzar.",422);
            }
            $result[$day][]=$enabled && $breakStart!==''?substr($breakStart,0,5).':00':null;
            $result[$day][]=$enabled && $breakEnd!==''?substr($breakEnd,0,5).':00':null;
        }
    }
    return $result;
}
function saveSchedule(?int $userId,mixed $source): void {
    $pdo=db(); $pdo->beginTransaction();
    try {
        if ($userId!==null && is_array($source)) {
            $existing=query('SELECT * FROM employee_schedules WHERE user_id=? ORDER BY day_of_week FOR UPDATE',[$userId])->fetchAll();
            foreach($existing as $dayRow) {
                $day=(int)$dayRow['day_of_week'];
                if (!isset($source[$day]) || !is_array($source[$day])) continue;
                $hasStart=array_key_exists('break_start',$source[$day]);
                $hasEnd=array_key_exists('break_end',$source[$day]);
                if ($hasStart!==$hasEnd) throw new DomainException('Reabrí el formulario: faltan campos del descanso.',422);
                if (!$hasStart) {
                    $source[$day]['break_start']=$dayRow['break_start']??'';
                    $source[$day]['break_end']=$dayRow['break_end']??'';
                }
            }
        }
        $rows=validateSchedule($source,$userId===null?'is_open':'is_working');
        foreach($rows as $day=>[$start,$end,$enabled]) {
            if ($userId===null) query('INSERT INTO business_schedules (day_of_week,start_time,end_time,is_open) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE start_time=VALUES(start_time),end_time=VALUES(end_time),is_open=VALUES(is_open)',[$day,$start,$end,$enabled]);
            else query('INSERT INTO employee_schedules (user_id,day_of_week,start_time,end_time,is_working,break_start,break_end) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE start_time=VALUES(start_time),end_time=VALUES(end_time),is_working=VALUES(is_working),break_start=VALUES(break_start),break_end=VALUES(break_end)',[$userId,$day,$start,$end,$enabled,$rows[$day][3],$rows[$day][4]]);
        }
        $pdo->commit();
    } catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
function saveEmployeeSchedule(int $userId,mixed $source): void { saveSchedule($userId,$source); }
function saveBusinessSchedule(mixed $source): void { saveSchedule(null,$source); }
function holidaysList(?string $search=null): array {
    if ($search!==null && trim($search)!=='') return query('SELECT * FROM holidays WHERE description LIKE ? OR holiday_date LIKE ? ORDER BY holiday_date',array_fill(0,2,'%'.trim($search).'%'))->fetchAll();
    return query('SELECT * FROM holidays ORDER BY holiday_date')->fetchAll();
}
function addHoliday(string $date,string $description): void {
    if(!validDate($date)) throw new DomainException('La fecha del feriado no es válida.',422);
    query('INSERT INTO holidays (holiday_date,description) VALUES (?,?)',[$date,scheduleDescription($description)]);
}
function deleteHoliday(int $id): void { query('DELETE FROM holidays WHERE id=?',[$id]); }
function checkAppointmentAvailability(int $employeeUserId,string $startsAt,string $endsAt): ?string {
    $dates=[];
    foreach([$startsAt,$endsAt] as $value) {
        $format=strlen($value)===16?'Y-m-d H:i':'Y-m-d H:i:s';
        $date=DateTimeImmutable::createFromFormat('!'.$format,$value);
        if(!$date || $date->format($format)!==$value) return 'El intervalo de la cita no es válido.';
        $dates[]=$date;
    }
    [$start,$end]=$dates;
    if($end<=$start || $start->format('Y-m-d')!==$end->format('Y-m-d')) return 'La cita debe terminar después del inicio, dentro del mismo día.';
    $active=query('SELECT u.active AND COALESCE(e.active,1) FROM users u LEFT JOIN employees e ON e.user_id=u.id WHERE u.id=?',[$employeeUserId])->fetchColumn();
    if(!$active) return 'El empleado está inactivo o no existe.';
    $day=$start->format('Y-m-d'); $weekday=(int)$start->format('N');
    if(query('SELECT id FROM holidays WHERE holiday_date=?',[$day])->fetchColumn()) return 'No se pueden agendar turnos en días feriados.';
    if(query('SELECT id FROM employee_vacations WHERE user_id=? AND starts_at<=? AND ends_at>=? LIMIT 1',[$employeeUserId,$day,$day])->fetchColumn()) return 'El profesional se encuentra ausente o de vacaciones.';
    $biz=businessSchedule()[$weekday]; $emp=employeeSchedule($employeeUserId)[$weekday];
    if(!$biz['is_open'] || $start->format('H:i:s')<$biz['start_time'] || $end->format('H:i:s')>$biz['end_time']) return 'El centro se encuentra cerrado o sin horario configurado.';
    if(!$emp['is_working'] || $start->format('H:i:s')<$emp['start_time'] || $end->format('H:i:s')>$emp['end_time']) return 'El profesional no atiende en el horario seleccionado o no tiene horario configurado.';
    if($emp['break_start']!=='' && $start->format('H:i:s')<$emp['break_end'] && $end->format('H:i:s')>$emp['break_start']) return 'El turno se superpone con el descanso del profesional. Elegí un horario antes o después del descanso.';
    return null;
}
