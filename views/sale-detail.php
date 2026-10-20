<?php
$balance=$sale['balance']; $client=trim($sale['first_name'].' '.$sale['last_name']);
$label=COLLECTION_LABELS[$balance['collection']]??$balance['collection'];
?>
<p class="client-back"><a href="<?= e(url('client',['id'=>$sale['client_id']])) ?>">← Volver a la ficha de <?= e($client) ?></a></p>
<div class="employee-heading"><h1>Venta #<?= e($sale['id']) ?></h1><p><?= e((new DateTimeImmutable($sale['created_at']))->format('d/m/Y H:i')) ?> · registrada por <?= e($sale['created_by_name']) ?></p></div>
<div class="figma-action-bar client-actions">
<span class="figma-action-btn disabled" aria-disabled="true"><span>Cobrar</span> <small class="nav-tag-pending">Próx.</small></span>
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
<?php if($sale['status']==='confirmed' && $balance['paid']===0): ?><p class="muted" role="status">Todavía no hay cobros registrados. El cobro de ventas llega con la tarjeta 16.</p><?php endif; ?>
</section>
</div>
