<?php
$active=array_filter($filters,fn($v)=>$v!=='');
$param=['session'=>$cashView['id']]+$active;
$fmt=fn(string $d)=>(new DateTimeImmutable($d))->format('d/m/Y H:i');
$closed=$cashView['status']==='closed';
$methodName=$filters['method']!==''?(PAYMENT_METHODS[$filters['method']]??'Todos'):'Todos';
$kindName=$filters['kind']!==''?(CASH_KIND_LABELS[$filters['kind']]??'Todos'):'Todos';
?>
<div class="agenda-print-actions no-print">
<a href="<?= e(url('caja',$param)) ?>">← Volver a la caja</a>
<button type="button" class="btn-pill-action" data-print>Imprimir</button>
<a class="btn-pill-action" href="<?= e(url('caja-export',$param)) ?>">Exportar CSV</a>
</div>
<section class="agenda-print report-print">
<h1>Caja #<?= e($cashView['id']) ?> · <?= $closed?'Cerrada':'Abierta' ?></h1>
<p class="muted">Emitido el <?= e(date('d/m/Y H:i')) ?> por <?= e($user['name']) ?>.<?= $count>count($movements)?' Se muestran los primeros '.e(count($movements)).' de '.e($count).' movimientos: acotá los filtros para ver el resto.':'' ?></p>
<dl class="sale-meta">
<dt>Apertura</dt><dd><?= e($fmt($cashView['opened_at'])) ?> por <?= e($cashView['opened_by_name']) ?></dd>
<dt>Efectivo inicial</dt><dd><?= e(moneyFormat(decimalToCents($cashView['opening_cash']))) ?></dd>
<?php if($closed): ?>
<dt>Cierre</dt><dd><?= e($cashView['closed_at']?$fmt($cashView['closed_at']):'—') ?><?= $cashView['closed_by_name']?' por '.e($cashView['closed_by_name']):'' ?></dd>
<?php if($cashView['counted_cash']!==null): $diff=decimalToCents($cashView['difference']); ?>
<dt>Efectivo esperado</dt><dd><?= e(moneyFormat(decimalToCents($cashView['expected_cash']))) ?></dd>
<dt>Efectivo contado</dt><dd><?= e(moneyFormat(decimalToCents($cashView['counted_cash']))) ?></dd>
<dt>Diferencia</dt><dd><?= e($diff===0?'Sin diferencias':moneyFormat($diff)) ?></dd>
<?php if($cashView['closing_note']): ?><dt>Observación</dt><dd><?= e($cashView['closing_note']) ?></dd><?php endif; ?>
<?php endif; ?>
<?php else: ?>
<dt>Efectivo esperado</dt><dd><?= e(moneyFormat($expected)) ?></dd>
<?php endif; ?>
</dl>
<h2>Resumen por medio de pago</h2>
<div class="agenda-print-scroll"><table class="agenda-print-table">
<thead><tr><th scope="col">Medio</th><th scope="col">Entradas</th><th scope="col">Salidas</th><th scope="col">Neto</th></tr></thead>
<tbody>
<?php $tin=$tout=0; foreach(PAYMENT_METHODS as $m=>$label): $row=$summary[$m]; $tin+=$row['in']; $tout+=$row['out']; ?>
<tr><td><?= e($label) ?></td><td><?= e(moneyFormat($row['in'])) ?></td><td><?= e(moneyFormat($row['out'])) ?></td><td><?= e(moneyFormat($row['net'])) ?></td></tr>
<?php endforeach; ?>
<tr class="agenda-print-day"><th scope="row">Total</th><td><?= e(moneyFormat($tin)) ?></td><td><?= e(moneyFormat($tout)) ?></td><td><strong><?= e(moneyFormat($tin-$tout)) ?></strong></td></tr>
</tbody></table></div>
<h2>Movimientos</h2>
<p>Medio de pago: <?= e($methodName) ?> · Tipo: <?= e($kindName) ?> · <?= e($count) ?> movimientos, del más antiguo al más reciente.</p>
<?php if(!$movements): ?>
<p role="status">Esta caja no tiene movimientos con estos filtros.</p>
<?php else: ?>
<div class="agenda-print-scroll"><table class="agenda-print-table">
<thead><tr><th scope="col">Fecha</th><th scope="col">Tipo</th><th scope="col">Origen / detalle</th><th scope="col">Medio</th><th scope="col">Importe</th><th scope="col">Usuario</th></tr></thead>
<tbody>
<?php foreach($movements as $mv): $out=$mv['direction']==='out'; ?>
<tr>
<td><?= e($fmt($mv['created_at'])) ?></td>
<td><?= e(CASH_KIND_LABELS[$mv['kind']]) ?><?= $mv['reverses_id']?' (corrección de #'.e($mv['reverses_id']).')':'' ?><?= $mv['reversed_by_id']?' (corregido)':'' ?></td>
<td><?= e(cashMovementOrigin($mv)) ?><?= $mv['description']?' · '.e($mv['description']):'' ?></td>
<td><?= e(PAYMENT_METHODS[$mv['method']]) ?></td>
<td><?= $out?'− ':'+ ' ?><?= e(moneyFormat(decimalToCents($mv['amount']))) ?></td>
<td><?= e($mv['user_name']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php endif; ?>
</section>
<script src="/assets/print.js" defer></script>
