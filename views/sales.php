<?php
$active=array_filter($filters,fn($v)=>$v!=='');
$pageUrl=fn(int $n)=>url('sales',$active+['p'=>$n]);
?>
<div class="figma-action-bar">
<a class="figma-action-btn" href="<?= e(url('sale-new')) ?>"><span>Nueva venta</span></a>
<a class="figma-action-btn" href="<?= e(url('sales-print',$active)) ?>"><span>Imprimir</span></a>
<a class="figma-action-btn" href="<?= e(url('sales-export',$active)) ?>"><span>Exportar CSV</span></a>
</div>
<form method="get" class="form-card sales-filters" role="search" aria-label="Buscar ventas">
<input type="hidden" name="page" value="sales">
<div class="field"><label for="q">Cliente o N.º de venta</label><input id="q" name="q" value="<?= e($filters['q']) ?>" maxlength="100" placeholder="Ej.: Ana o 12"></div>
<div class="field"><label for="status">Estado</label><select id="status" name="status"><option value="">Todos</option><?php foreach(COLLECTION_LABELS as $value=>$label): ?><option value="<?= e($value) ?>" <?= $filters['status']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="from">Desde</label><input id="from" type="date" name="from" value="<?= e($filters['from']) ?>"></div>
<div class="field"><label for="to">Hasta</label><input id="to" type="date" name="to" value="<?= e($filters['to']) ?>"></div>
<div class="field"><label for="employee">Empleado</label><select id="employee" name="employee"><option value="">Todos</option><?php foreach($employeeOptions as $opt): ?><option value="<?= e($opt['id']) ?>" <?= $filters['employee']===(string)$opt['id']?'selected':'' ?>><?= e($opt['name']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="method">Medio de pago</label><select id="method" name="method"><option value="">Todos</option><?php foreach(PAYMENT_METHODS as $value=>$label): ?><option value="<?= e($value) ?>" <?= $filters['method']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
<div class="sales-filter-actions"><button class="button-secondary" type="submit">Buscar</button><?php if($active): ?><a class="clear-filter-link" href="<?= e(url('sales')) ?>">✕ Limpiar</a><?php endif; ?></div>
</form>
<?php if($filterError): ?><div class="notice error" role="alert"><?= e($filterError) ?></div><?php endif; ?>
<dl class="sales-totals" aria-label="Totales del resultado">
<div><dt>Vendido</dt><dd><?= e(moneyFormat($totals['sold'])) ?></dd></div>
<div><dt>Cobrado</dt><dd><?= e(moneyFormat($totals['collected'])) ?></dd></div>
<div><dt>Pendiente</dt><dd><?= e(moneyFormat($totals['pending'])) ?></dd></div>
</dl>
<div class="figma-table-card">
<?php if(!$sales): ?>
<div class="empty-state" role="status"><h3><?= $active?'No encontramos ventas con esos filtros.':'Todavía no hay ventas registradas.' ?></h3><p><?= $active?'Probá con otro nombre, estado o rango de fechas.':'Registrá la primera desde la agenda, la ficha de un cliente o con "Nueva venta".' ?></p></div>
<?php else: ?>
<div class="employee-table-wrap"><table class="figma-table sales-table">
<thead><tr><th scope="col">N.º</th><th scope="col">Fecha</th><th scope="col">Cliente</th><th scope="col">Total</th><th scope="col">Saldo</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
<tbody>
<?php foreach($sales as $sale): $client=trim($sale['first_name'].' '.$sale['last_name']); ?>
<tr>
<td data-label="N.º" class="code-cell">#<?= e($sale['id']) ?></td>
<td data-label="Fecha"><?= e((new DateTimeImmutable($sale['created_at']))->format('d/m/Y H:i')) ?></td>
<td data-label="Cliente"><a href="<?= e(url('client',['id'=>$sale['client_id']])) ?>"><?= e($client) ?></a></td>
<td data-label="Total"><?= e(moneyFormat(decimalToCents($sale['total']))) ?></td>
<td data-label="Saldo"><?= e(moneyFormat($sale['balance'])) ?></td>
<td data-label="Estado"><span class="badge <?= e($sale['collection']==='voided'?'cancelled':($sale['collection']==='paid'?'completed':'pending')) ?>"><?= e(COLLECTION_LABELS[$sale['collection']]) ?></span></td>
<td><a class="table-action-edit" href="<?= e(url('sale',['id'=>$sale['id']])) ?>" aria-label="Ver venta <?= e($sale['id']) ?> de <?= e($client) ?>"><span>Ver venta</span></a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<div class="figma-table-pagination">
<span><?= e($count) ?> ventas · Página <?= e($number) ?> de <?= e($pages) ?></span>
<div class="pagination-links">
<?php if($number>1): ?><a class="button-secondary" href="<?= e($pageUrl($number-1)) ?>">Anterior</a><?php endif; ?>
<?php if($number<$pages): ?><a class="button-secondary" href="<?= e($pageUrl($number+1)) ?>">Siguiente</a><?php endif; ?>
</div></div>
<?php endif; ?>
</div>
