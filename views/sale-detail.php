<?php
$balance=$sale['balance']; $client=trim($sale['first_name'].' '.$sale['last_name']);
$label=COLLECTION_LABELS[$balance['collection']]??$balance['collection'];
?>
<p class="client-back"><a href="<?= e(url('client',['id'=>$sale['client_id']])) ?>">← Volver a la ficha de <?= e($client) ?></a></p>
<div class="employee-heading"><h1>Venta #<?= e($sale['id']) ?></h1><p><?= e((new DateTimeImmutable($sale['created_at']))->format('d/m/Y H:i')) ?> · registrada por <?= e($sale['created_by_name']) ?></p></div>
<div class="figma-action-bar client-actions">
<?php if($sale['status']==='confirmed' && $balance['balance']>0): ?><a class="figma-action-btn" href="<?= e(url('sale-pay',['id'=>$sale['id']])) ?>"><span>Cobrar</span></a><?php else: ?><span class="figma-action-btn disabled" aria-disabled="true"><span>Cobrar</span></span><?php endif; ?>
<span class="figma-action-btn disabled" aria-disabled="true"><span>Anular</span> <small class="nav-tag-pending">Próx.</small></span>
</div>
<div class="client-details">
<section class="form-card">
<h2>Datos</h2>
<dl class="sale-meta">
<dt>Cliente</dt><dd><a href="<?= e(url('client',['id'=>$sale['client_id']])) ?>"><?= e($client) ?></a></dd>
<dt>Empleado</dt><dd><?= e($sale['employee_name']?:'Sin asignar') ?></dd>
<dt>Cita</dt><dd><?php if($sale['appointment_id']): ?><a href="<?= e(url('appointment-edit',['id'=>$sale['appointment_id']])) ?>">Ver cita #<?= e($sale['appointment_id']) ?></a><?php else: ?>Venta sin cita<?php endif; ?></dd>
<dt>Estado</dt><dd><span class="badge <?= e($sale['status']==='voided'?'cancelled':($balance['collection']==='paid'?'completed':'pending')) ?>"><?= e($label) ?></span></dd>
<?php if($sale['status']==='voided'): ?><dt>Motivo de anulación</dt><dd><?= e($sale['void_reason']) ?></dd><?php endif; ?>
<?php if($sale['notes']): ?><dt>Observaciones</dt><dd><?= e($sale['notes']) ?></dd><?php endif; ?>
</dl>
</section>
<section class="form-card" aria-labelledby="sale-items-title">
<h2 id="sale-items-title">Detalle</h2>
<div class="employee-table-wrap"><table class="figma-table">
<thead><tr><th scope="col">Descripción</th><th scope="col">Cantidad</th><th scope="col">Precio</th><th scope="col">Descuento</th><th scope="col">Impuesto</th><th scope="col">Total</th></tr></thead>
<tbody>
<?php foreach($sale['items'] as $item): ?>
<tr><td><?= e($item['description']) ?></td><td><?= e($item['quantity']) ?></td><td><?= e(moneyFormat(decimalToCents($item['unit_price']))) ?></td><td><?= e(moneyFormat(decimalToCents($item['discount']))) ?></td><td><?= e(moneyFormat(decimalToCents($item['tax_amount']))) ?> <small class="muted">(<?= e(rtrim(rtrim($item['tax_rate'],'0'),'.')?:'0') ?> %<?= $item['tax_included']?' incl.':'' ?>)</small></td><td><?= e(moneyFormat(decimalToCents($item['total_amount']))) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<dl class="sale-totals-list">
<dt>Subtotal</dt><dd><?= e(moneyFormat(decimalToCents($sale['subtotal']))) ?></dd>
<dt>Descuentos</dt><dd>- <?= e(moneyFormat(decimalToCents($sale['discount_total']))) ?></dd>
<dt>Impuestos</dt><dd><?= e(moneyFormat(decimalToCents($sale['tax_total']))) ?></dd>
<dt>Total</dt><dd><strong><?= e(moneyFormat($balance['total'])) ?></strong></dd>
<dt>Cobrado</dt><dd><?= e(moneyFormat($balance['paid'])) ?></dd>
<dt>Saldo</dt><dd><strong><?= e(moneyFormat($balance['balance'])) ?></strong></dd>
</dl>
<h3>Cobros</h3>
<?php if(!$payments): ?><p class="muted" role="status">Todavía no hay cobros registrados.</p><?php else: ?>
<div class="employee-table-wrap"><table class="figma-table">
<thead><tr><th scope="col">Fecha</th><th scope="col">Tipo</th><th scope="col">Medio</th><th scope="col">Importe</th><th scope="col">Estado</th></tr></thead>
<tbody>
<?php foreach($payments as $pay): ?>
<tr><td><?= e((new DateTimeImmutable($pay['paid_at']))->format('d/m/Y H:i')) ?></td><td><?= $pay['kind']==='deposit'?'Seña aplicada':'Cobro' ?></td><td><?= e(PAYMENT_METHODS[$pay['payment_method']]??$pay['payment_method']) ?></td><td><?= e(moneyFormat(decimalToCents($pay['amount']))) ?></td><td><?= $pay['released_at']?($pay['kind']==='deposit'?'Liberada':'Reintegrado'):'Vigente' ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>
</div>
