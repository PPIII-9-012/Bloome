<?php $diff=$preview['difference']??null; ?>
<p class="client-back"><a href="<?= e(url('caja')) ?>">← Volver a la caja</a></p>
<div class="employee-heading"><h1>Cerrar caja #<?= e($session['id']) ?></h1><p>Contá el efectivo que hay en la caja y compará con lo esperado. Al cerrar, la caja no se puede reabrir ni modificar.</p></div>
<?php if ($errors): ?><div class="notice error" role="alert"><ul><?php foreach($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="form-card" aria-labelledby="close-summary-title">
<h2 id="close-summary-title">Resumen por medio de pago</h2>
<div class="employee-table-wrap"><table class="figma-table cash-summary">
<thead><tr><th scope="col">Medio</th><th scope="col">Entradas</th><th scope="col">Salidas</th><th scope="col">Neto</th></tr></thead>
<tbody>
<?php foreach(PAYMENT_METHODS as $m=>$label): $row=$summary[$m]; ?>
<tr><td data-label="Medio"><?= e($label) ?></td><td data-label="Entradas"><?= e(moneyFormat($row['in'])) ?></td><td data-label="Salidas"><?= e(moneyFormat($row['out'])) ?></td><td data-label="Neto"><?= e(moneyFormat($row['net'])) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<dl class="sale-meta">
<dt>Efectivo inicial</dt><dd><?= e(moneyFormat(decimalToCents($session['opening_cash']))) ?></dd>
<dt>Efectivo esperado</dt><dd><strong><?= e(moneyFormat($expected)) ?></strong> <small class="muted">(inicial + entradas − salidas en efectivo)</small></dd>
</dl>
<p class="muted">Tarjetas, transferencias y otros medios no entran en el arqueo de efectivo: se concilian aparte contra el posnet o el banco con los netos de arriba.</p>
</section>
<form method="post" action="<?= e(url('caja-close')) ?>" class="form-card employee-form">
<?= csrfField() ?>
<div class="form-grid">
<div class="field"><label for="counted_cash">Efectivo contado *</label><input id="counted_cash" name="counted_cash" type="text" inputmode="decimal" required value="<?= e($form['counted_cash']) ?>"></div>
</div>
<?php if($preview): ?>
<div class="notice <?= $diff===0?'':'error' ?>" role="status"><p>Esperado <strong><?= e(moneyFormat($preview['expected'])) ?></strong> · contado <strong><?= e(moneyFormat($preview['counted'])) ?></strong> ·
<?= $diff===0?'<strong>sin diferencias</strong>.':'diferencia <strong>'.e(moneyFormat($diff)).'</strong> ('.($diff<0?'falta efectivo':'sobra efectivo').'). Explicala en la observación.' ?></p></div>
<?php endif; ?>
<div class="field"><label for="note">Observación<?= $diff?' *':'' ?></label><textarea id="note" name="note" rows="3" maxlength="255"><?= e($form['note']) ?></textarea><small class="muted">Obligatoria si hay diferencia.</small></div>
<div class="form-actions"><a class="button-secondary" href="<?= e(url('caja')) ?>">Cancelar</a><button class="button-secondary" type="submit" name="intent" value="preview">Ver diferencia</button><button class="button-orange" type="submit" name="intent" value="close">CERRAR CAJA</button></div>
</form>
