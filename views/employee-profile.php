<div class="employee-heading"><h1><?= e($title) ?></h1></div>
<div class="employee-profile">
<aside class="employee-identity"><div class="employee-avatar" aria-hidden="true"><?= e(initials($employee['name'])) ?></div><h2><?= e($employee['name']) ?></h2><p><?= e(employeeRoles()[$employee['role']]) ?></p><p><?= $employee['active'] ? 'Activo' : 'Inactivo' ?></p><a class="button-secondary" href="<?= e(url($self ? 'profile-edit' : 'employee-edit', $self ? [] : ['id'=>$id])) ?>">Editar</a></aside>
<div class="form-card"><h2>Datos personales</h2><dl><dt>Correo electrónico</dt><dd><?= e($employee['email']) ?></dd><dt>Teléfono</dt><dd><?= e($employee['phone'] ?: 'Sin registrar') ?></dd><dt>Color de citas</dt><dd><?= employeeSwatch($employee['appointment_color']) ?> <?= e(employeeColor($employee['appointment_color'])) ?></dd></dl><?php if (!$self): ?><a href="<?= e(url('employees')) ?>">← Volver a empleados</a><?php endif; ?></div>
</div>
