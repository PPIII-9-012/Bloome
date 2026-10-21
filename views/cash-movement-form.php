<?php $labels=['expense'=>'gasto','income'=>'ingreso','withdrawal'=>'retiro']; ?>
<p class="client-back"><a href="<?= e(url('caja')) ?>">← Volver a la caja</a></p>
<div class="employee-heading"><h1>Nuevo movimiento de caja</h1><p>Los cobros de ventas entran solos a la caja. Acá se cargan solo gastos, ingresos y retiros manuales.</p></div>
<div class="form-card employee-form">
<?php if ($errors): ?><div class="notice error" role="alert"><ul><?php foreach($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php require __DIR__.'/_cash-gate.php'; ?>
<?php if($session): ?>
<form method="post" action="<?= e(url('caja-movement')) ?>">
<?= csrfField() ?>
<input type="hidden" name="idempotency_key" value="<?= e($key) ?>">
<div class="form-grid">
<div class="field"><label for="kind">Tipo *</label><select id="kind" name="kind"><?php foreach($labels as $v=>$l): ?><option value="<?= e($v) ?>" <?= $form['kind']===$v?'selected':'' ?>><?= e(ucfirst($l)) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="method">Medio de pago *</label><select id="method" name="method"><?php foreach(PAYMENT_METHODS as $v=>$l): ?><option value="<?= e($v) ?>" <?= $form['method']===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="amount">Importe *</label><input id="amount" name="amount" type="text" inputmode="decimal" required value="<?= e($form['amount']) ?>"></div>
<div class="field"><label for="category_id">Categoría (solo gastos)</label><select id="category_id" name="category_id"><option value="">Sin categoría</option><?php foreach($categories as $c): ?><option value="<?= e($c['id']) ?>" <?= (string)$c['id']===$form['category_id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
</div>
<div class="field"><label for="description">Descripción</label><input id="description" name="description" maxlength="255" value="<?= e($form['description']) ?>"><small class="muted">Obligatoria en gastos y retiros.</small></div>
<div class="form-actions"><a class="button-secondary" href="<?= e(url('caja')) ?>">Cancelar</a><button class="button" type="submit">Registrar movimiento</button></div>
</form>
<?php endif; ?>
</div>
