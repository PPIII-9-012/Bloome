<div class="employee-heading"><h1><?= e($title) ?></h1></div>
<div class="employee-profile-layout">
<aside class="employee-identity"><div class="employee-avatar" aria-hidden="true"><?= e(initials($employee['name'])) ?></div><h2><?= e($employee['name']) ?></h2><p><?= e(employeeRoles()[$employee['role']]) ?></p><p><?= $employee['active']?'Activo':'Inactivo' ?></p><a class="button-secondary" href="<?= e(url($self?'profile-edit':'employee-edit',$self?[]:['id'=>$id])) ?>">Editar</a></aside>
<div class="employee-profile-details">
<div class="form-card"><h2>Datos personales</h2><dl><dt>Correo electrónico</dt><dd><?= e($employee['email']) ?></dd><dt>Teléfono</dt><dd><?= e($employee['phone']?:'Sin registrar') ?></dd><dt>Especialidad</dt><dd><?= e($employee['specialty']?:'Sin registrar') ?></dd><dt>Fecha de ingreso</dt><dd><?= e($employee['hire_date']?:'Sin registrar') ?></dd><?php if(canManageEmployees($user)||$self): ?><dt>Comisión</dt><dd><?= $employee['commission_percentage']!==null ? e(rtrim(rtrim((string)$employee['commission_percentage'],'0'),'.')).' %' : 'Sin registrar' ?></dd><?php endif; ?><dt>Color de citas</dt><dd><?= employeeSwatch($employee['appointment_color']) ?> <?= e(employeeColor($employee['appointment_color'])) ?></dd></dl></div>
<div class="form-card"><h2>Vacaciones y ausencias</h2>
<?php if(canManageEmployees($user)): ?>
<details class="schedule-add"><summary>＋ Añadir ausencia</summary><form method="post" action="<?= e(url('employee-vacation-new')) ?>"><?= csrfField() ?><input type="hidden" name="user_id" value="<?= e($id) ?>">
<label class="field">Descripción<input name="description" maxlength="190" required></label><div class="schedule-times"><label>Fecha inicio<input type="date" name="starts_at" required></label><label>Fecha fin<input type="date" name="ends_at" required></label></div><div class="form-actions"><button class="button-orange">GUARDAR</button></div></form></details>
<?php endif; ?>
<?php if(!$vacations): ?><p>No hay períodos de vacaciones registrados.</p><?php else: ?>
<div class="employee-table-wrap"><table class="figma-table"><thead><tr><th>Descripción</th><th>Inicio</th><th>Fin</th><?php if(canManageEmployees($user)): ?><th>Acciones</th><?php endif; ?></tr></thead><tbody>
<?php foreach($vacations as $v): ?><tr><td><?= e($v['description']) ?></td><td><?= e($v['starts_at']) ?></td><td><?= e($v['ends_at']) ?></td><?php if(canManageEmployees($user)): ?><td><form method="post" action="<?= e(url('employee-vacation-delete')) ?>"><?= csrfField() ?><input type="hidden" name="user_id" value="<?= e($id) ?>"><input type="hidden" name="vacation_id" value="<?= e($v['id']) ?>"><button class="button-secondary">Eliminar ausencia</button></form></td><?php endif; ?></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></div>
<div class="form-card schedule-card"><h2>Configuración del Horario</h2>
<?php $scheduleEditable=canManageEmployees($user); ?>
<?php if($scheduleEditable): ?><form action="<?= e(url('employee-schedule-save')) ?>" method="post"><?= csrfField() ?><input type="hidden" name="user_id" value="<?= e($id) ?>"><?php else: ?><p>Consultá con administración para modificar tu disponibilidad.</p><?php endif; ?>
<?php $schedulePrefix='schedule'; $scheduleFlag='is_working'; require ROOT.'/views/schedule-fields.php'; ?>
<?php if($scheduleEditable): ?><div class="form-actions"><button class="button-orange">GUARDAR</button></div></form><?php endif; ?>
</div></div></div>
