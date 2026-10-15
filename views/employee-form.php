<div class="employee-heading"><h1><?= e($title) ?></h1><p><?= $self ? 'Podés actualizar tu nombre, correo y teléfono.' : 'Los cambios de estado conservan las citas y el historial del empleado.' ?></p></div>
<div class="form-card employee-form">
<?php if ($errors): ?><div class="notice error" role="alert"><div><strong>No se guardaron los cambios.</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div></div><?php endif; ?>
<form method="post" action="<?= e(url($page, $self || $new ? [] : ['id'=>$id])) ?>">
<?= csrfField() ?>
<div class="form-grid">
<?php foreach (['name'=>['Nombre y apellido','text',100], 'email'=>['Correo electrónico','email',190], 'phone'=>['Teléfono','tel',30]] as $field=>$cfg): ?>
<div class="field"><label for="<?= e($field) ?>"><?= e($cfg[0]) ?><?= $field !== 'phone' ? ' *' : '' ?></label><input id="<?= e($field) ?>" name="<?= e($field) ?>" type="<?= e($cfg[1]) ?>" maxlength="<?= e($cfg[2]) ?>" value="<?= e($employee[$field]) ?>" <?= $field !== 'phone' ? 'required' : '' ?> <?= isset($errors[$field]) ? 'aria-invalid="true" aria-describedby="error-'.e($field).'"' : '' ?>><?php if (isset($errors[$field])): ?><small class="field-error" id="error-<?= e($field) ?>"><?= e($errors[$field]) ?></small><?php endif; ?></div>
<?php endforeach; ?>
<?php if (!$self): ?>
<div class="field"><label for="role">Rol *</label><select id="role" name="role" required><?php foreach (employeeRoles() as $value=>$label): ?><option value="<?= e($value) ?>" <?= $employee['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="appointment_color">Color de citas *</label><input id="appointment_color" name="appointment_color" type="color" value="<?= e(employeeColor($employee['appointment_color'])) ?>" required></div>
<div class="field"><label for="active">Estado *</label><select id="active" name="active"><option value="1" <?= (string)$employee['active'] === '1' ? 'selected' : '' ?>>Activo</option><option value="0" <?= (string)$employee['active'] === '0' ? 'selected' : '' ?>>Inactivo</option></select><small>Un empleado inactivo no puede acceder ni recibir nuevas citas. Sus citas existentes se conservan.</small></div>
<?php if ($new): ?><div class="field"><label for="password">Contraseña inicial *</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required><small>Entre 12 y 72 bytes. Entregala al empleado por un canal privado.</small></div><?php endif; ?>
<?php endif; ?>
</div>
<div class="form-actions"><a class="button-secondary" href="<?= e(url($self ? 'profile' : 'employees')) ?>">Cancelar</a><button class="button-orange" type="submit">GUARDAR CAMBIOS</button></div>
</form>
</div>
