<?php
$active=array_filter($filters,fn($v)=>$v!=='');
$sessionParam=$cashView&&!$isCurrent?['session'=>$cashView['id']]:[];
$pageUrl=fn(int $n)=>url('caja',$sessionParam+$active+['p'=>$n]);
$fmt=fn(string $d)=>(new DateTimeImmutable($d))->format('d/m/Y H:i');
?>
<h1 class="ph-title mb-20">Caja</h1>
<div class="figma-action-bar">
<?php if($open): ?>
<a class="figma-action-btn" href="<?= e(url('caja-movement',['kind'=>'expense'])) ?>"><span>Registrar gasto</span></a>
<a class="figma-action-btn" href="<?= e(url('caja-movement',['kind'=>'income'])) ?>"><span>Registrar ingreso</span></a>
<a class="figma-action-btn" href="<?= e(url('caja-movement',['kind'=>'withdrawal'])) ?>"><span>Registrar retiro</span></a>
<a class="figma-action-btn" href="<?= e(url('caja-close')) ?>"><span>Cerrar caja</span></a>
<?php endif; ?>
<?php if($cashView): ?>
<a class="figma-action-btn" href="<?= e(url('caja-print',['session'=>$cashView['id']]+$active)) ?>"><span>Imprimir</span></a>
<a class="figma-action-btn" href="<?= e(url('caja-export',['session'=>$cashView['id']]+$active)) ?>"><span>Exportar CSV</span></a>
<?php endif; ?>
</div>
<?php if ($errors): ?><div class="notice error" role="alert"><ul><?php foreach($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if(!$open): ?>
<?php $session=false; require __DIR__.'/_cash-gate.php'; ?>
<?php endif; ?>
<?php if(!$cashView): ?>
<div class="figma-table-card"><div class="empty-state" role="status"><h3>Todavía no hay cajas registradas.</h3><p>Abrí la primera caja con el efectivo inicial para empezar a cobrar.</p></div></div>
<?php else: ?>
<section class="form-card" aria-labelledby="cash-title">
<h2 id="cash-title">Caja #<?= e($cashView['id']) ?> · <span class="badge <?= $cashView['status']==='open'?'completed':'pending' ?>"><?= $cashView['status']==='open'?'Abierta':'Cerrada' ?></span></h2>
<dl class="sale-meta">
<dt>Apertura</dt><dd><?= e($fmt($cashView['opened_at'])) ?> por <?= e($cashView['opened_by_name']) ?></dd>
<dt>Efectivo inicial</dt><dd><?= e(moneyFormat(decimalToCents($cashView['opening_cash']))) ?></dd>
<?php if($cashView['status']==='closed'): ?><dt>Cierre</dt><dd><?= e($cashView['closed_at']?$fmt($cashView['closed_at']):'—') ?><?= $cashView['closed_by_name']?' por '.e($cashView['closed_by_name']):'' ?></dd><?php endif; ?>
<?php if($cashView['status']==='closed' && $cashView['counted_cash']!==null): $diff=decimalToCents($cashView['difference']); ?>
<dt>Efectivo esperado</dt><dd><?= e(moneyFormat(decimalToCents($cashView['expected_cash']))) ?> <small class="muted">(inicial + entradas − salidas en efectivo, al cierre)</small></dd>
<dt>Efectivo contado</dt><dd><strong><?= e(moneyFormat(decimalToCents($cashView['counted_cash']))) ?></strong></dd>
<dt>Diferencia</dt><dd><strong class="<?= $diff===0?'':($diff<0?'amount-out':'amount-in') ?>"><?= e($diff===0?'Sin diferencias':moneyFormat($diff)) ?></strong></dd>
<?php if($cashView['closing_note']): ?><dt>Observación</dt><dd><?= e($cashView['closing_note']) ?></dd><?php endif; ?>
<?php else: ?>
<dt>Efectivo esperado</dt><dd><strong><?= e(moneyFormat($expected)) ?></strong> <small class="muted">(inicial + entradas − salidas en efectivo)</small></dd>
<?php endif; ?>
</dl>
<h3>Resumen por medio de pago</h3>
<div class="employee-table-wrap"><table class="figma-table cash-summary">
<thead><tr><th scope="col">Medio</th><th scope="col">Entradas</th><th scope="col">Salidas</th><th scope="col">Neto</th></tr></thead>
<tbody>
<?php $tin=$tout=0; foreach(PAYMENT_METHODS as $m=>$label): $row=$summary[$m]; $tin+=$row['in']; $tout+=$row['out']; ?>
<tr><td data-label="Medio"><?= e($label) ?></td><td data-label="Entradas"><?= e(moneyFormat($row['in'])) ?></td><td data-label="Salidas"><?= e(moneyFormat($row['out'])) ?></td><td data-label="Neto"><?= e(moneyFormat($row['net'])) ?></td></tr>
<?php endforeach; ?>
<tr class="cash-total"><th scope="row">Total</th><td><?= e(moneyFormat($tin)) ?></td><td><?= e(moneyFormat($tout)) ?></td><td><strong><?= e(moneyFormat($tin-$tout)) ?></strong></td></tr>
</tbody></table></div>
<p class="muted">El efectivo inicial no se cuenta como entrada. Las señas aplicadas a una venta no suman otra vez: entraron cuando se cobraron.</p>
</section>

<form method="get" class="form-card sales-filters cash-filters" role="search" aria-label="Filtrar movimientos">
<input type="hidden" name="page" value="caja">
<?php if($sessionParam): ?><input type="hidden" name="session" value="<?= e($cashView['id']) ?>"><?php endif; ?>
<div class="field"><label for="method">Medio de pago</label><select id="method" name="method"><option value="">Todos</option><?php foreach(PAYMENT_METHODS as $v=>$l): ?><option value="<?= e($v) ?>" <?= $filters['method']===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="kind">Tipo</label><select id="kind" name="kind"><option value="">Todos</option><?php foreach(CASH_KIND_LABELS as $v=>$l): ?><option value="<?= e($v) ?>" <?= $filters['kind']===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
<div class="sales-filter-actions"><button class="button-secondary" type="submit">Filtrar</button><?php if($active): ?><a class="clear-filter-link" href="<?= e(url('caja',$sessionParam)) ?>">✕ Limpiar</a><?php endif; ?></div>
</form>

<div class="figma-table-card">
<?php if(!$movements): ?>
<div class="empty-state" role="status"><h3><?= $active?'No hay movimientos con esos filtros.':'Esta caja todavía no tiene movimientos.' ?></h3></div>
<?php else: ?>
<div class="employee-table-wrap"><table class="figma-table cash-table">
<thead><tr><th scope="col">Fecha</th><th scope="col">Tipo</th><th scope="col">Origen / detalle</th><th scope="col">Medio</th><th scope="col">Importe</th><th scope="col">Usuario</th><?php if($isAdmin && $isCurrent): ?><th scope="col"><span class="sr-only">Acciones</span></th><?php endif; ?></tr></thead>
<tbody>
<?php foreach($movements as $mv): $out=$mv['direction']==='out'; ?>
<tr>
<td data-label="Fecha"><?= e($fmt($mv['created_at'])) ?></td>
<td data-label="Tipo"><?= e(CASH_KIND_LABELS[$mv['kind']]) ?><?= $mv['reverses_id']?' <small class="muted">(corrección de #'.e($mv['reverses_id']).')</small>':'' ?><?= $mv['reversed_by_id']?' <small class="muted">(corregido)</small>':'' ?></td>
<td data-label="Origen">
<?php if($mv['sale_id']): ?><a href="<?= e(url('sale',['id'=>$mv['sale_id']])) ?>">Venta #<?= e($mv['sale_id']) ?></a><?= $mv['kind']==='refund'?' (anulada)':'' ?>
<?php elseif($mv['appointment_id']): ?>Seña · <a href="<?= e(url('appointment-edit',['id'=>$mv['appointment_id']])) ?>">cita #<?= e($mv['appointment_id']) ?></a>
<?php else: ?>Manual<?= $mv['category_name']?' · '.e($mv['category_name']):'' ?><?php endif; ?>
<?php if($mv['description']): ?><br><small class="muted"><?= e($mv['description']) ?></small><?php endif; ?>
</td>
<td data-label="Medio"><?= e(PAYMENT_METHODS[$mv['method']]) ?></td>
<td data-label="Importe" class="<?= $out?'amount-out':'amount-in' ?>"><?= $out?'− ':'+ ' ?><?= e(moneyFormat(decimalToCents($mv['amount']))) ?></td>
<td data-label="Usuario"><?= e($mv['user_name']) ?></td>
<?php if($isAdmin && $isCurrent): ?><td>
<?php if(in_array($mv['kind'],['income','expense','withdrawal'],true) && !$mv['reverses_id'] && !$mv['reversed_by_id']): ?>
<form method="post" action="<?= e(url('caja')) ?>" class="inline-form"><?= csrfField() ?><input type="hidden" name="intent" value="reverse"><input type="hidden" name="movement_id" value="<?= e($mv['id']) ?>">
<label class="sr-only" for="reason-<?= e($mv['id']) ?>">Motivo de la corrección del movimiento <?= e($mv['id']) ?></label><input id="reason-<?= e($mv['id']) ?>" name="reason" placeholder="Motivo" minlength="5" maxlength="180" required>
<button class="button-secondary" type="submit">Corregir</button></form>
<?php endif; ?></td><?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<div class="figma-table-pagination"><span><?= e($count) ?> movimientos · Página <?= e($number) ?> de <?= e($pages) ?></span>
<div class="pagination-links">
<?php if($number>1): ?><a class="button-secondary" href="<?= e($pageUrl($number-1)) ?>">Anterior</a><?php endif; ?>
<?php if($number<$pages): ?><a class="button-secondary" href="<?= e($pageUrl($number+1)) ?>">Siguiente</a><?php endif; ?>
</div></div>
<?php endif; ?>
</div>

<?php if(count($sessions)>1 || ($sessions && !$isCurrent)): ?>
<section class="form-card" aria-labelledby="cash-history-title"><h2 id="cash-history-title">Cajas anteriores</h2>
<ul class="cash-history"><?php foreach($sessions as $s): ?><li><?php if((int)$s['id']===(int)$cashView['id']): ?><strong>Caja #<?= e($s['id']) ?></strong><?php else: ?><a href="<?= e(url('caja',['session'=>$s['id']])) ?>">Caja #<?= e($s['id']) ?></a><?php endif; ?> · <?= e($fmt($s['opened_at'])) ?> · <?= $s['status']==='open'?'abierta':'cerrada' ?></li><?php endforeach; ?></ul></section>
<?php endif; ?>
<?php endif; ?>

<?php if($isAdmin): ?>
<section class="form-card" aria-labelledby="cats-title"><h2 id="cats-title">Categorías de gasto</h2>
<p><?= $categories?e(implode(' · ',array_column($categories,'name'))):'Sin categorías.' ?></p>
<form method="post" action="<?= e(url('caja')) ?>" class="inline-form"><?= csrfField() ?><input type="hidden" name="intent" value="add-category">
<div class="field"><label for="cat-name">Nueva categoría</label><input id="cat-name" name="name" minlength="2" maxlength="100" required></div>
<button class="button-secondary" type="submit">Agregar categoría</button></form></section>
<?php endif; ?>
