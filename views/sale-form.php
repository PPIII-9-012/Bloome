<?php
$discountTypes=['none'=>'Sin descuento','amount'=>'Importe ($)','percent'=>'Porcentaje (%)'];
$formUrl=url('sale-new',$appointment?['appointment'=>$appointment['id']]:[]);
?>
<p class="client-back"><a href="<?= e($backUrl) ?>">← Volver</a></p>
<div class="employee-heading"><h1>Nueva venta</h1><p><?= $appointment?'Venta de la cita del '.e((new DateTimeImmutable($appointment['starts_at']))->format('d/m/Y H:i')).'. Los servicios conservan el precio acordado en la cita.':'El total, los descuentos y los impuestos los calcula el servidor al guardar.' ?></p></div>
<div class="form-card employee-form">
<?php if ($errors): ?><div class="notice error" role="alert"><ul><?php foreach($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (!$clients || !$services): ?><div class="notice" role="status">Necesitás al menos un cliente y un servicio activo para registrar una venta.</div><?php endif; ?>
<form method="post" action="<?= e($formUrl) ?>">
<?= csrfField() ?>
<div class="form-grid">
<div class="field"><label for="client_id">Cliente *</label><select id="client_id" name="client_id" required <?= $appointment?'data-locked="1"':'' ?>><option value="">Seleccionar cliente</option><?php foreach($clients as $client): if($appointment && (string)$client['id']!==$formData['client_id']) continue; ?><option value="<?= e($client['id']) ?>" <?= (string)$client['id']===$formData['client_id']?'selected':'' ?>><?= e($client['last_name'].', '.$client['first_name']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="employee_id">Empleado</label><select id="employee_id" name="employee_id"><option value="">Sin asignar</option><?php foreach($employees as $employee): ?><option value="<?= e($employee['id']) ?>" <?= (string)$employee['id']===$formData['employee_id']?'selected':'' ?>><?= e($employee['name']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="sale_date">Fecha</label><input type="text" id="sale_date" value="<?= e(date('d/m/Y')) ?>" readonly aria-describedby="sale-date-help"><small id="sale-date-help" class="muted">Se registra con la fecha de hoy.</small></div>
</div>
<fieldset class="appointment-services"><legend>Servicios *</legend>
<div class="employee-table-wrap"><table class="figma-table sale-lines">
<thead><tr><th scope="col">Servicio</th><th scope="col">Cantidad</th><th scope="col">Descuento</th><th scope="col">Valor del descuento</th></tr></thead>
<tbody>
<?php foreach($formData['lines'] as $i=>$line): ?>
<tr>
<td data-label="Servicio"><label class="sr-only" for="line-service-<?= e($i) ?>">Servicio <?= e($i+1) ?></label><select id="line-service-<?= e($i) ?>" name="lines[<?= e($i) ?>][service_id]"><option value="">Elegir servicio</option><?php foreach($services as $service): ?><option value="<?= e($service['id']) ?>" <?= (string)$service['id']===$line['service_id']?'selected':'' ?>><?= e($service['name']) ?> — <?= e(moneyFormat(decimalToCents($service['price']))) ?></option><?php endforeach; ?></select></td>
<td data-label="Cantidad"><label class="sr-only" for="line-qty-<?= e($i) ?>">Cantidad <?= e($i+1) ?></label><input id="line-qty-<?= e($i) ?>" type="number" name="lines[<?= e($i) ?>][quantity]" min="1" max="999" value="<?= e($line['quantity']) ?>"></td>
<td data-label="Descuento"><label class="sr-only" for="line-dtype-<?= e($i) ?>">Tipo de descuento <?= e($i+1) ?></label><select id="line-dtype-<?= e($i) ?>" name="lines[<?= e($i) ?>][discount_type]"><?php foreach($discountTypes as $value=>$label): ?><option value="<?= e($value) ?>" <?= $line['discount_type']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></td>
<td data-label="Valor del descuento"><label class="sr-only" for="line-dvalue-<?= e($i) ?>">Valor del descuento <?= e($i+1) ?></label><input id="line-dvalue-<?= e($i) ?>" type="text" inputmode="decimal" name="lines[<?= e($i) ?>][discount_value]" value="<?= e($line['discount_value']) ?>" placeholder="0"></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<p><button class="button-secondary" type="submit" name="intent" value="add-line" formnovalidate>Agregar servicio</button></p>
</fieldset>
<div class="field"><label for="notes">Observaciones</label><textarea id="notes" name="notes" rows="3" maxlength="500"><?= e($formData['notes']) ?></textarea></div>
<?php if($preview): $sum=$preview['sum']; ?>
<section class="sale-totals" role="status" aria-label="Total calculado"><dl>
<dt>Subtotal</dt><dd><?= e(moneyFormat($sum['subtotal'])) ?></dd>
<dt>Descuentos</dt><dd>- <?= e(moneyFormat($sum['discount'])) ?></dd>
<dt>Impuestos</dt><dd><?= e(moneyFormat($sum['tax'])) ?></dd>
<dt>Total</dt><dd><strong><?= e(moneyFormat($sum['total'])) ?></strong></dd>
</dl><p class="muted">Cálculo de vista previa. El total definitivo se vuelve a calcular al guardar.</p></section>
<?php endif; ?>
<div class="form-actions"><a class="button-secondary" href="<?= e($backUrl) ?>">Cancelar</a><button class="button-secondary" type="submit" name="intent" value="preview" formnovalidate>Calcular total</button><button class="button-orange" type="submit" name="intent" value="save">GUARDAR VENTA</button></div>
</form>
</div>
