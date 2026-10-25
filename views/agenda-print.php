<?php
$viewNames=['day'=>'Día','week'=>'Semana','month'=>'Mes'];
$filters=['mode'=>$mode,'employee'=>$employeeFilter,'status'=>$statusFilter,'date'=>$date];
$first=new DateTimeImmutable($period['from']);
$last=(new DateTimeImmutable($period['until']))->modify('-1 day');
$employeeName='Todos';
foreach($employees as $employee) if ((string)$employee['id']===$employeeFilter) $employeeName=$employee['name'];
$statusName=$statusFilter!=='' ? (appointmentStatuses()[$statusFilter]??'Todos') : 'Todos';
$byDay=[];
foreach ($appointments as $appointment) $byDay[substr($appointment['starts_at'],0,10)][]=$appointment;
$weekdays=['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
?>
<div class="agenda-print-actions no-print">
<a href="<?= e(url('agenda',$filters)) ?>">← Volver a la agenda</a>
<?php if(!$filterError): ?><button type="button" class="btn-pill-action" data-print>Imprimir</button><a class="btn-pill-action" href="<?= e(url('agenda-export',$filters)) ?>">Exportar CSV</a><?php endif; ?>
</div>
<section class="agenda-print">
<h1>Agenda · <?= e($viewNames[$mode]) ?></h1>
<p>Período: <?= e($first->format('d/m/Y')) ?> al <?= e($last->format('d/m/Y')) ?> · Empleado: <?= e($employeeName) ?> · Estado: <?= e($statusName) ?> · <?= count($appointments) ?> turnos</p>
<?php if($filterError): ?>
<p role="alert">Los filtros no son válidos. Volvé a la agenda y elegí un empleado y un estado de la lista.</p>
<?php elseif(!$appointments): ?>
<p role="status">No hay turnos para este período y estos filtros.</p>
<?php else: ?>
<div class="agenda-print-scroll"><table class="agenda-print-table">
<thead><tr><th scope="col">Hora</th><th scope="col">Cliente</th><th scope="col">Servicios</th><th scope="col">Empleado</th><th scope="col">Estado</th></tr></thead>
<?php foreach($byDay as $key=>$items): $day=new DateTimeImmutable($key); ?>
<tbody>
<tr class="agenda-print-day"><th colspan="5" scope="colgroup"><?= e($weekdays[(int)$day->format('N')-1].' '.$day->format('d/m/Y')) ?></th></tr>
<?php foreach($items as $appointment): ?>
<tr>
<td><?= e(substr($appointment['starts_at'],11,5).'–'.substr($appointment['ends_at'],11,5)) ?></td>
<td><?= e($appointment['first_name'].' '.$appointment['last_name']) ?></td>
<td><?= e($appointment['service_name']) ?></td>
<td><?= e($appointment['employee_name']) ?></td>
<td><?= e(appointmentStatuses()[$appointment['status']]??$appointment['status']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
<?php endforeach; ?>
</table></div>
<?php endif; ?>
</section>
<script src="/assets/print.js" defer></script>
