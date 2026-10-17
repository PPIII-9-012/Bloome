<div class="employee-heading"><h1><?= e($title) ?></h1><p>La duración se calcula sumando los servicios. Se comprueba la disponibilidad al guardar.</p></div>
<div class="form-card employee-form">
<?php if ($errors): ?><div class="notice error" role="alert"><ul><?php foreach($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (!$clients || !$employees || !$services): ?><div class="notice" role="status">Necesitás clientes, empleados activos y servicios para cargar un turno.</div><?php endif; ?>
<form method="post" action="<?= e(url($new?'appointment-new':'appointment-edit',$new?[]:['id'=>$id])) ?>">
<?= csrfField() ?><input type="hidden" name="version" value="<?= e($version) ?>">
<div class="form-grid">
<div class="field"><label for="client_id">Cliente *</label><select id="client_id" name="client_id" required><option value="">Seleccionar cliente</option><?php foreach($clients as $client): ?><option value="<?= e($client['id']) ?>" <?= (string)$client['id']===$formData['client_id']?'selected':'' ?>><?= e($client['last_name'].', '.$client['first_name']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="employee_id">Empleado *</label><select id="employee_id" name="employee_id" required><option value="">Seleccionar empleado</option><?php foreach($employees as $employee): ?><option value="<?= e($employee['id']) ?>" <?= (string)$employee['id']===$formData['employee_id']?'selected':'' ?>><?= e($employee['name'].($employee['active']?'':' (inactivo)')) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="starts_at">Fecha y hora *</label><input type="datetime-local" id="starts_at" name="starts_at" value="<?= e($formData['starts_at']) ?>" required></div>
<div class="field"><label for="status">Estado *</label><select id="status" name="status"><?php foreach(appointmentStatuses() as $key=>$label): if($new && $key==='cancelled') continue; ?><option value="<?= e($key) ?>" <?= $formData['status']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
</div>
<fieldset class="appointment-services"><legend>Servicios *</legend><p>Los servicios que ya tenía el turno conservan su precio y duración.</p>
<?php foreach($services as $service): if(!$service['active'] && !isset($previous[$service['id']])) continue; $detail=$previous[$service['id']]??$service; ?>
<label class="appointment-service"><input type="checkbox" name="services[]" value="<?= e($service['id']) ?>" <?= in_array((string)$service['id'],$formData['services'],true)?'checked':'' ?>><span><?= e($service['name']) ?><?= $service['active']?'':' (inactivo, conservado)' ?> — <?= e($detail['duration_minutes']) ?> min · $ <?= e(number_format((float)$detail['price'],2,',','.')) ?></span></label>
<?php endforeach; ?></fieldset>
<?php if($record): ?><p>Horario guardado: <?= e(substr($record['starts_at'],11,5)) ?> a <?= e(substr($record['ends_at'],11,5)) ?>.</p><?php endif; ?>
<div class="field"><label for="notes">Notas</label><textarea id="notes" name="notes" rows="4" maxlength="5000"><?= e($formData['notes']) ?></textarea></div>
<div class="form-actions"><a class="button-secondary" href="<?= e(url('agenda')) ?>">Volver a agenda</a><?php if(!$new): ?><a class="button-secondary" href="<?= e(url('appointment-edit',['id'=>$id])) ?>">Recargar turno</a><?php endif; ?><button class="button-orange" type="submit">GUARDAR TURNO</button></div>
</form>
<?php if($record && $record['status']!=='cancelled'): ?><details class="appointment-cancel"><summary>Cancelar este turno</summary><p>Se libera el horario y se conservan sus datos y servicios.</p><form method="post" action="<?= e(url('appointment-cancel',['id'=>$id])) ?>"><?= csrfField() ?><input type="hidden" name="version" value="<?= e($version) ?>"><button class="button-secondary" type="submit">Confirmar cancelación</button></form></details><?php endif; ?>
</div>
