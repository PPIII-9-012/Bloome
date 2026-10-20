<?php
$fullName=$client['first_name'].' '.$client['last_name'];
$filters=array_filter(['from'=>$from,'to'=>$to],static fn($value)=>$value!=='');
$statuses=appointmentStatuses();
?>
<p class="client-back"><a href="<?= e(url('clients')) ?>">← Volver a clientes</a></p>
<div class="figma-action-bar client-actions">
<?php if(canEdit($user)): ?><a class="figma-action-btn" href="<?= e(url('appointment-new',['client'=>$client['id']])) ?>"><span>Nueva cita</span></a><?php endif; ?>
<?php if(canEdit($user)): ?><a class="figma-action-btn" href="<?= e(url('sale-new',['client'=>$client['id']])) ?>"><span>Nueva venta</span></a><?php endif; ?>
<span class="figma-action-btn disabled" aria-disabled="true"><span>Enviar un SMS</span> <small class="nav-tag-pending">Próx.</small></span>
<span class="figma-action-btn disabled" aria-disabled="true"><span>Enviar un Email</span> <small class="nav-tag-pending">Próx.</small></span>
</div>
<div class="client-profile">
<aside class="client-identity">
<div class="employee-avatar" aria-hidden="true"><?= e(initials($client['first_name'],$client['last_name'])) ?></div>
<h1><?= e($fullName) ?></h1>
<p class="muted">Código #<?= e(str_pad((string)$client['id'],4,'0',STR_PAD_LEFT)) ?></p>
<dl>
<dt>Teléfono</dt><dd><?= e($client['phone']?:'Sin registrar') ?></dd>
<dt>Correo electrónico</dt><dd><?= e($client['email']?:'Sin registrar') ?></dd>
<dt>Fecha de nacimiento</dt><dd><?= $client['birth_date']?e((new DateTimeImmutable($client['birth_date']))->format('d/m/Y')):'Sin registrar' ?></dd>
<dt>Cliente desde</dt><dd><?= e((new DateTimeImmutable($client['created_at']))->format('d/m/Y')) ?></dd>
</dl>
<?php if(canEdit($user)): ?><a class="button-secondary" href="<?= e(url('client-edit',['id'=>$client['id']])) ?>">Editar</a><?php endif; ?>
</aside>
<div class="client-details">
<?php if($client['notes']): ?><section class="form-card"><h2>Observaciones</h2><p class="client-notes"><?= e($client['notes']) ?></p></section><?php endif; ?>
<section class="form-card" aria-labelledby="client-appointments-title">
<h2 id="client-appointments-title">Citas</h2>
<form method="get" class="agenda-controls client-filters">
<input type="hidden" name="page" value="client"><input type="hidden" name="id" value="<?= e($client['id']) ?>">
<label>Inicio<input type="date" name="from" value="<?= e($from) ?>"></label>
<label>Fin<input type="date" name="to" value="<?= e($to) ?>"></label>
<button type="submit" class="btn-pill-action">Aplicar</button>
<?php if($filters): ?><a href="<?= e(url('client',['id'=>$client['id']])) ?>">Limpiar filtros</a><?php endif; ?>
</form>
<?php foreach($errors as $error): ?><p role="alert"><?= e($error) ?></p><?php endforeach; ?>
<?php if(!$errors): ?>
<p class="client-summary" role="status"><?= e($history['total']) ?> <?= $history['total']===1?'cita':'citas' ?><?= $filters?' en el período elegido':'' ?><?php foreach($statuses as $value=>$label): if($history['byStatus'][$value]>0): ?> · <?= e($label) ?>: <?= e($history['byStatus'][$value]) ?><?php endif; endforeach; ?></p>
<?php if(!$history['rows']): ?>
<p role="status"><?= $filters?'Este cliente no tiene citas en el período elegido.':'Este cliente todavía no tiene citas registradas.' ?></p>
<?php else: ?>
<div class="employee-table-wrap"><table class="figma-table">
<thead><tr><th scope="col">Fecha y horario</th><th scope="col">Servicios</th><th scope="col">Empleado</th><th scope="col">Estado</th><th scope="col">Seña · Abonado</th><?php if(canEdit($user)): ?><th scope="col"><span class="sr-only">Acciones</span></th><?php endif; ?></tr></thead>
<tbody>
<?php foreach($history['rows'] as $appointment): ?>
<tr>
<td><?= e(substr($appointment['starts_at'],8,2).'/'.substr($appointment['starts_at'],5,2).'/'.substr($appointment['starts_at'],0,4)) ?> · <?= e(substr($appointment['starts_at'],11,5).'–'.substr($appointment['ends_at'],11,5)) ?></td>
<td><?= e($appointment['service_name']) ?></td>
<td><?= employeeSwatch($appointment['appointment_color']??null) ?> <?= e($appointment['employee_name']) ?></td>
<td><span class="badge <?= e($appointment['status']) ?>"><?= e($statuses[$appointment['status']]??$appointment['status']) ?></span></td>
<td><?php if(isset($saleByAppointment[(int)$appointment['id']])): ?><a href="<?= e(url('sale',['id'=>$saleByAppointment[(int)$appointment['id']]])) ?>">Venta #<?= e($saleByAppointment[(int)$appointment['id']]) ?></a><?php elseif(canEdit($user) && !in_array($appointment['status'],['cancelled','missed'],true)): ?><a href="<?= e(url('sale-new',['appointment'=>$appointment['id']])) ?>">Vender</a><?php else: ?><span class="muted">—</span><?php endif; ?></td>
<?php if(canEdit($user)): ?><td><a href="<?= e(url('appointment-edit',['id'=>$appointment['id'],'date'=>substr($appointment['starts_at'],0,10)])) ?>">Editar turno</a></td><?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if($pages>1): ?>
<div class="employee-pagination"><span>Página <?= e($number) ?> de <?= e($pages) ?></span>
<?php if($number>1): ?><a class="button-secondary" href="<?= e(url('client',['id'=>$client['id'],'p'=>$number-1]+$filters)) ?>">Anterior</a><?php endif; ?>
<?php if($number<$pages): ?><a class="button-secondary" href="<?= e(url('client',['id'=>$client['id'],'p'=>$number+1]+$filters)) ?>">Siguiente</a><?php endif; ?></div>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
</section>
<?php if(canEdit($user)): $pending=0; foreach($sales as $sale) if($sale['status']==='confirmed') $pending+=$sale['balance_cents']; ?>
<section class="form-card" aria-labelledby="client-sales-title">
<h2 id="client-sales-title">Ventas y saldo</h2>
<?php if(!$sales): ?><p role="status">Este cliente todavía no tiene ventas registradas.</p>
<?php else: ?>
<p class="client-summary" role="status"><?= e(count($sales)) ?> <?= count($sales)===1?'venta':'ventas' ?> · Saldo pendiente: <?= e(moneyFormat($pending)) ?></p>
<div class="employee-table-wrap"><table class="figma-table">
<thead><tr><th scope="col">Venta</th><th scope="col">Fecha</th><th scope="col">Total</th><th scope="col">Cobrado</th><th scope="col">Saldo</th><th scope="col">Estado</th></tr></thead>
<tbody>
<?php foreach($sales as $sale): ?>
<tr><td><a href="<?= e(url('sale',['id'=>$sale['id']])) ?>">#<?= e($sale['id']) ?></a></td><td><?= e((new DateTimeImmutable($sale['created_at']))->format('d/m/Y')) ?></td><td><?= e(moneyFormat($sale['total_cents'])) ?></td><td><?= e(moneyFormat($sale['paid_cents'])) ?></td><td><?= e($sale['collection']==='voided'?'—':moneyFormat($sale['balance_cents'])) ?></td><td><?= e(COLLECTION_LABELS[$sale['collection']]??$sale['collection']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>
<?php endif; ?>
</div>
</div>
