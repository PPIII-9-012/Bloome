<?php
$active=array_filter($filters,fn($v)=>$v!=='');
$dateLabel=fn(string $d)=>(new DateTimeImmutable($d))->format('d/m/Y');
$bucketName=($report['bucket']??'')==='day'?'Día':'Mes';
$fmtBucket=function(string $l) use ($report){ return ($report['bucket']??'')==='day' ? (new DateTimeImmutable($l))->format('d/m/Y') : (new DateTimeImmutable($l.'-01'))->format('m/Y'); };
?>
<div class="employee-heading"><h1><?= e($kind==='ventas'?'Ventas':($kind==='gastos'?'Gastos':'Otros')) ?> · <?= e($views[$mode]) ?></h1>
<p>Del <?= e($dateLabel($filters['from'])) ?> al <?= e($dateLabel($filters['to'])) ?>. <a href="<?= e(url('estadisticas')) ?>">← Volver a estadísticas</a></p></div>
<?php if($filterError): ?><div class="notice error" role="alert"><?= e($filterError) ?> Se muestra el mes en curso.</div><?php endif; ?>
<form method="get" class="form-card sales-filters" role="search" aria-label="Filtros del informe">
<input type="hidden" name="page" value="<?= e($page) ?>"><input type="hidden" name="v" value="<?= e($mode) ?>">
<div class="field"><label for="from">Desde</label><input id="from" type="date" name="from" value="<?= e($filters['from']) ?>"></div>
<div class="field"><label for="to">Hasta</label><input id="to" type="date" name="to" value="<?= e($filters['to']) ?>"></div>
<?php if($kind==='ventas'): ?>
<div class="field"><label for="category">Categoría</label><select id="category" name="category"><option value="">Todas</option><option value="none" <?= $filters['category']==='none'?'selected':'' ?>>Sin dato</option><?php foreach($categories as $c): ?><option value="<?= e($c['id']) ?>" <?= $filters['category']===(string)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="service">Servicio</label><select id="service" name="service"><option value="">Todos</option><option value="none" <?= $filters['service']==='none'?'selected':'' ?>>Sin dato (ítem libre)</option><?php foreach($services as $s): ?><option value="<?= e($s['id']) ?>" <?= $filters['service']===(string)$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="gender">Género del cliente</label><select id="gender" name="gender"><option value="">Todos</option><?php foreach(CLIENT_GENDERS as $v=>$l): ?><option value="<?= e($v) ?>" <?= $filters['gender']===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?><option value="none" <?= $filters['gender']==='none'?'selected':'' ?>>Sin dato</option></select></div>
<?php elseif($kind==='gastos'): ?>
<div class="field"><label for="category">Categoría</label><select id="category" name="category"><option value="">Todas</option><option value="none" <?= $filters['category']==='none'?'selected':'' ?>>Sin categoría</option><?php foreach($expenseCats as $c): ?><option value="<?= e($c['id']) ?>" <?= $filters['category']===(string)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
<?php else: ?>
<div class="field"><label for="employee">Empleado</label><select id="employee" name="employee"><option value="">Todos</option><?php foreach($employeeOptions as $o): ?><option value="<?= e($o['id']) ?>" <?= $filters['employee']===(string)$o['id']?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
<?php endif; ?>
<div class="sales-filter-actions"><button class="button-secondary" type="submit">Ver informe</button><a class="clear-filter-link" href="<?= e(url($page,['v'=>$mode])) ?>">✕ Mes en curso</a></div>
</form>

<?php if($kind==='ventas'): $t=$report['totals']; ?>
<dl class="sales-totals" aria-label="Totales del informe">
<div><dt>Vendido</dt><dd><?= e(moneyFormat($t['sold'])) ?></dd></div>
<div><dt>Cobrado</dt><dd><?= e(moneyFormat($t['collected'])) ?></dd></div>
<div><dt>Pendiente</dt><dd><?= e(moneyFormat($t['pending'])) ?></dd></div>
<div><dt>Ventas</dt><dd><?= e($report['sales']) ?></dd></div>
</dl>
<p class="stats-note">Vendido es el total de las ventas vigentes del período (las anuladas no suman). Cobrado es lo cobrado de esas ventas, igual que en el listado de Ventas. Si una venta tiene varios servicios, lo cobrado se reparte entre ellos según su importe.</p>
<?php if(!$report['rows']): ?>
<div class="figma-table-card"><div class="empty-state" role="status"><h3>No hay ventas con esos filtros.</h3><p>Probá con otro período o quitá algún filtro.</p></div></div>
<?php else:
    $chart=['rows'=>array_map(fn($r)=>[$mode==='general'?$fmtBucket($r['label']):$r['label'],$r['sold'],$r['collected'],moneyFormat($r['sold'])],$report['rows']),'series'=>['Vendido','Cobrado'],'alt'=>'Vendido y cobrado: '.$views[$mode]];
    require ROOT.'/views/stats-chart.php'; ?>
<div class="figma-table-card"><div class="employee-table-wrap"><table class="figma-table">
<thead><tr><th scope="col"><?= e($mode==='general'?$bucketName:'Detalle') ?></th><th scope="col">Ventas</th><th scope="col">Vendido</th><th scope="col">Cobrado</th><th scope="col">Pendiente</th></tr></thead><tbody>
<?php foreach($report['rows'] as $r): ?><tr><td><?= e($mode==='general'?$fmtBucket($r['label']):$r['label']) ?></td><td><?= e($r['sales']) ?></td><td><?= e(moneyFormat($r['sold'])) ?></td><td><?= e(moneyFormat($r['collected'])) ?></td><td><?= e(moneyFormat($r['pending'])) ?></td></tr><?php endforeach; ?>
</tbody><tfoot><tr><th scope="row">Total</th><td><?= e($mode==='general'?$report['sales']:'—') ?></td><td><?= e(moneyFormat($t['sold'])) ?></td><td><?= e(moneyFormat($t['collected'])) ?></td><td><?= e(moneyFormat($t['pending'])) ?></td></tr></tfoot></table></div></div>
<?php if($mode!=='general'): ?><p class="stats-note">La cantidad de ventas no se suma en el total porque una misma venta puede aparecer en más de una fila.</p><?php endif; ?>
<?php endif; ?>

<?php elseif($kind==='gastos'): ?>
<dl class="sales-totals" aria-label="Totales del informe">
<div><dt>Total de gastos</dt><dd><?= e(moneyFormat($report['total'])) ?></dd></div>
<div><dt>Movimientos</dt><dd><?= e($report['count']) ?></dd></div>
</dl>
<p class="stats-note">Suma los gastos registrados en la caja, descontando los que se corrigieron. No incluye retiros ni reintegros a clientes.</p>
<?php if(!$report['rows']): ?>
<div class="figma-table-card"><div class="empty-state" role="status"><h3>No hay gastos con esos filtros.</h3><p>Probá con otro período o quitá la categoría.</p></div></div>
<?php else:
    $chart=['rows'=>array_map(fn($r)=>[$mode==='general'?$fmtBucket($r['label']):$r['label'],max(0,$r['amount']),null,moneyFormat($r['amount'])],$report['rows']),'series'=>['Gastos',null],'alt'=>'Gastos: '.$views[$mode]];
    require ROOT.'/views/stats-chart.php'; ?>
<div class="figma-table-card"><div class="employee-table-wrap"><table class="figma-table">
<thead><tr><th scope="col"><?= e($mode==='general'?$bucketName:'Categoría') ?></th><th scope="col">Movimientos</th><th scope="col">Importe</th></tr></thead><tbody>
<?php foreach($report['rows'] as $r): ?><tr><td><?= e($mode==='general'?$fmtBucket($r['label']):$r['label']) ?></td><td><?= e($r['count']) ?></td><td><?= e(moneyFormat($r['amount'])) ?></td></tr><?php endforeach; ?>
</tbody><tfoot><tr><th scope="row">Total</th><td><?= e($report['count']) ?></td><td><?= e(moneyFormat($report['total'])) ?></td></tr></tfoot></table></div></div>
<?php endif; ?>

<?php else: ?>
<dl class="sales-totals" aria-label="Totales del informe">
<div><dt>Horas totales</dt><dd><?= e(statsDuration($report['minutes'])) ?></dd></div>
<div><dt>Citas completadas</dt><dd><?= e($report['count']) ?></dd></div>
</dl>
<p class="stats-note">Es la duración de las citas completadas de cada empleado. No es un fichaje de entrada y salida.</p>
<?php if(!$report['rows']): ?>
<div class="figma-table-card"><div class="empty-state" role="status"><h3>No hay citas completadas en ese período.</h3><p>Probá con otro rango de fechas o con otro empleado.</p></div></div>
<?php else:
    $chart=['rows'=>array_map(fn($r)=>[$r['label'],$r['minutes'],null,statsDuration($r['minutes'])],$report['rows']),'series'=>['Horas',null],'alt'=>'Horas por empleado'];
    require ROOT.'/views/stats-chart.php'; ?>
<div class="figma-table-card"><div class="employee-table-wrap"><table class="figma-table">
<thead><tr><th scope="col">Empleado</th><th scope="col">Citas completadas</th><th scope="col">Horas</th></tr></thead><tbody>
<?php foreach($report['rows'] as $r): ?><tr><td><?= e($r['label']) ?></td><td><?= e($r['appointments']) ?></td><td><?= e(statsDuration($r['minutes'])) ?></td></tr><?php endforeach; ?>
</tbody><tfoot><tr><th scope="row">Total</th><td><?= e($report['count']) ?></td><td><?= e(statsDuration($report['minutes'])) ?></td></tr></tfoot></table></div></div>
<?php endif; ?>
<?php endif; ?>
