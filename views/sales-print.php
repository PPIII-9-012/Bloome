<?php
$active=array_filter($filters,fn($v)=>$v!=='');
$back=url('sales',$active);
$statusName=$filters['status']!==''?(COLLECTION_LABELS[$filters['status']]??'Todos'):'Todos';
$methodName=$filters['method']!==''?(PAYMENT_METHODS[$filters['method']]??'Todos'):'Todos';
$periodText=($filters['from']!==''||$filters['to']!=='')
    ? ($filters['from']!==''?(new DateTimeImmutable($filters['from']))->format('d/m/Y'):'el inicio').' al '.($filters['to']!==''?(new DateTimeImmutable($filters['to']))->format('d/m/Y'):'hoy')
    : 'Todas las fechas';
?>
<div class="agenda-print-actions no-print">
<a href="<?= e($back) ?>">← Volver a ventas</a>
<button type="button" class="btn-pill-action" data-print>Imprimir</button>
<a class="btn-pill-action" href="<?= e(url('sales-export',$active)) ?>">Exportar CSV</a>
</div>
<section class="agenda-print report-print">
<h1>Ventas</h1>
<p>Período: <?= e($periodText) ?> · Estado: <?= e($statusName) ?> · Empleado: <?= e($employeeName) ?> · Medio de pago: <?= e($methodName) ?><?= $filters['q']!==''?' · Búsqueda: '.e($filters['q']):'' ?> · <?= e($count) ?> ventas</p>
<p class="muted">Emitido el <?= e(date('d/m/Y H:i')) ?> por <?= e($user['name']) ?>.<?= $count>count($sales)?' Se muestran las primeras '.e(count($sales)).' de '.e($count).' ventas: acotá los filtros para ver el resto.':'' ?></p>
<dl class="report-totals">
<div><dt>Vendido</dt><dd><?= e(moneyFormat($totals['sold'])) ?></dd></div>
<div><dt>Cobrado</dt><dd><?= e(moneyFormat($totals['collected'])) ?></dd></div>
<div><dt>Pendiente</dt><dd><?= e(moneyFormat($totals['pending'])) ?></dd></div>
</dl>
<?php if(!$sales): ?>
<p role="status">No hay ventas con estos filtros.</p>
<?php else: ?>
<div class="agenda-print-scroll"><table class="agenda-print-table">
<thead><tr><th scope="col">N.º</th><th scope="col">Fecha</th><th scope="col">Cliente</th><th scope="col">Empleado</th><th scope="col">Total</th><th scope="col">Cobrado</th><th scope="col">Saldo</th><th scope="col">Estado</th></tr></thead>
<tbody>
<?php foreach($sales as $sale): ?>
<tr>
<td>#<?= e($sale['id']) ?></td>
<td><?= e((new DateTimeImmutable($sale['created_at']))->format('d/m/Y H:i')) ?></td>
<td><?= e(trim($sale['first_name'].' '.$sale['last_name'])) ?></td>
<td><?= e($sale['employee_name']??'—') ?></td>
<td><?= e(moneyFormat(decimalToCents($sale['total']))) ?></td>
<td><?= e(moneyFormat(decimalToCents($sale['paid']))) ?></td>
<td><?= e(moneyFormat($sale['balance'])) ?></td>
<td><?= e(COLLECTION_LABELS[$sale['collection']]??$sale['collection']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php endif; ?>
</section>
<script src="/assets/print.js" defer></script>
