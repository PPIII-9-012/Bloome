<?php
$viewNames=['day'=>'Día','week'=>'Semana','month'=>'Mes'];
$filters=['mode'=>$mode,'employee'=>$employeeFilter,'status'=>$statusFilter];
$byDay=[];
foreach ($appointments as $appointment) $byDay[substr($appointment['starts_at'],0,10)][]=$appointment;
$first=new DateTimeImmutable($period['from']);
$until=new DateTimeImmutable($period['until']);
$weekdays=['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
?>
<div class="page-heading employee-heading"><h1>Agenda</h1><p>Turnos del <?= e($first->format('d/m/Y')) ?> al <?= e($until->modify('-1 day')->format('d/m/Y')) ?> · <?= count($appointments) ?> resultados</p></div>
<?php if(!$filterError): ?><p class="agenda-output-links"><a href="<?= e(url('agenda-print',$filters+['date'=>$date])) ?>">Vista para imprimir</a> · <a href="<?= e(url('agenda-export',$filters+['date'=>$date])) ?>">Exportar CSV</a></p><?php endif; ?>
<div class="agenda-toolbar">
<nav class="agenda-view-switch" aria-label="Tipo de vista del calendario">
<?php foreach($viewNames as $value=>$label): ?><a class="agenda-view-option<?= $mode===$value?' is-active':'' ?>" href="<?= e(url('agenda',array_replace($filters,['date'=>$date,'mode'=>$value]))) ?>" <?= $mode===$value?'aria-current="page"':'' ?>><?= e($label) ?></a><?php endforeach; ?>
</nav>
<div class="figma-filters-card agenda-toolbar-filters">
<form method="get" class="agenda-controls">
<input type="hidden" name="page" value="agenda"><input type="hidden" name="mode" value="<?= e($mode) ?>">
<label class="agenda-date-filter">Fecha<input type="date" name="date" value="<?= e($date) ?>" required></label>
<label>Profesional<select name="employee"><option value="">Todos</option><?php foreach($employees as $employee): ?><option value="<?= (int)$employee['id'] ?>" <?= $employeeFilter===(string)$employee['id']?'selected':'' ?>><?= e($employee['name']) ?><?= !$employee['active'] || !$employee['user_active']?' (inactivo)':'' ?></option><?php endforeach; ?></select></label>
<label>Estado<select name="status"><option value="">Todos</option><?php foreach(appointmentStatuses() as $value=>$label): ?><option value="<?= e($value) ?>" <?= $statusFilter===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
<button type="submit" class="btn-pill-action">Aplicar</button><a class="agenda-clear-filters" href="<?= e(url('agenda',['date'=>$date,'mode'=>$mode])) ?>">Limpiar</a>
</form>
</div>
<?php if (canEdit($user)): ?><a class="figma-action-btn agenda-new-appointment" href="<?= e(url('appointment-new',$filters+['date'=>$date])) ?>">+ Nuevo turno</a><?php endif; ?>
</div>
<nav class="agenda-navigation" aria-label="Navegación del calendario">
<a class="btn-pill-action" href="<?= e(url('agenda',$filters+['date'=>$period['previous']])) ?>">Anterior</a>
<a class="btn-pill-action" href="<?= e(url('agenda',$filters+['date'=>date('Y-m-d')])) ?>">Hoy</a>
<a class="btn-pill-action" href="<?= e(url('agenda',$filters+['date'=>$period['next']])) ?>">Siguiente</a>
</nav>
<?php if($filterError): ?>
<p role="alert">Los filtros no son válidos. Seleccioná un empleado y un estado de la lista y aplicalos nuevamente.</p>
<?php else: ?>
<?php if(!$appointments): ?><p role="status">No hay turnos para este período y estos filtros.</p><?php endif; ?>
<div class="agenda-calendar agenda-<?= e($mode) ?>">
<?php if($mode==='month'): ?>
<?php foreach($weekdays as $weekday): ?><div class="agenda-weekday" aria-hidden="true"><?= e($weekday) ?></div><?php endforeach; ?>
<?php for($i=1;$i<(int)$first->format('N');$i++): ?><div class="agenda-padding" aria-hidden="true"></div><?php endfor; ?>
<?php endif; ?>
<?php for($day=$first;$day<$until;$day=$day->modify('+1 day')): $key=$day->format('Y-m-d'); ?>
<section class="agenda-day-card<?= $key===date('Y-m-d')?' agenda-today':'' ?>" aria-label="<?= e($day->format('d/m/Y')) ?>">
<h2><a href="<?= e(url('agenda',array_replace($filters,['date'=>$key,'mode'=>'day']))) ?>"<?= $key===date('Y-m-d')?' aria-current="date"':'' ?>><?= e($day->format('j')) ?></a></h2>
<?php if(empty($byDay[$key])): ?><p class="schedule-hint">Sin turnos<?= $employeeFilter!=='' || $statusFilter!==''?' con estos filtros':'' ?></p><?php endif; ?>
<?php foreach($byDay[$key]??[] as $appointment): ?>
<article class="agenda-appointment" data-appointment-color="<?= e(employeeColor($appointment['appointment_color']??null)) ?>">
<strong><?= e(substr($appointment['starts_at'],11,5).'–'.substr($appointment['ends_at'],11,5)) ?></strong>
<p><a href="<?= e(url('client',['id'=>$appointment['client_id']])) ?>"><?= e($appointment['first_name'].' '.$appointment['last_name']) ?></a></p>
<p><?= e($appointment['service_name']) ?></p>
<p><?= employeeSwatch($appointment['appointment_color']??null) ?> <?= e($appointment['employee_name']) ?></p>
<span class="badge <?= e($appointment['status']) ?>"><?= e(appointmentStatuses()[$appointment['status']]) ?></span>
<?php if(canEdit($user)): ?><a class="agenda-edit" href="<?= e(url('appointment-edit',['id'=>$appointment['id'],'date'=>$date]+$filters)) ?>">Editar turno</a><?php endif; ?>
<?php if(canEdit($user)): if(isset($saleByAppointment[(int)$appointment['id']])): ?><a class="agenda-edit" href="<?= e(url('sale',['id'=>$saleByAppointment[(int)$appointment['id']]])) ?>">Ver venta</a><?php elseif(!in_array($appointment['status'],['cancelled','missed'],true)): ?><a class="agenda-edit" href="<?= e(url('sale-new',['appointment'=>$appointment['id']])) ?>">Vender</a><?php endif; endif; ?>
</article>
<?php endforeach; ?>
</section>
<?php endfor; ?>
</div>
<?php endif; ?>
