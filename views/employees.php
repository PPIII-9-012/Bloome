<div class="employee-heading"><h1>Empleados</h1><p>Administrá el equipo, sus permisos y el color de sus citas.</p></div>
<div class="employee-toolbar"><a href="<?= e(url('employee-new')) ?>">＋ Nuevo empleado</a></div>
<form method="get" class="employee-filters">
    <input type="hidden" name="page" value="employees">
    <label for="employee-search">Buscar<input id="employee-search" type="search" name="q" value="<?= e($search) ?>" placeholder="Nombre o correo" maxlength="100"></label>
    <label for="employee-state">Estado<select id="employee-state" name="state"><?php foreach (['all'=>'Todos','1'=>'Activos','0'=>'Inactivos'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= (string)$value === $filter ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <button class="button-secondary" type="submit">Buscar</button>
</form>
<div class="employee-table-wrap">
<table class="figma-table"><thead><tr><th>Nombre</th><th>Correo electrónico</th><th>Rol</th><th>Color citas</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php foreach ($employees as $employee): ?>
<tr><td><?= e($employee['name']) ?></td><td><?= e($employee['email']) ?></td><td><?= e(employeeRoles()[$employee['role']]) ?></td><td><?= employeeSwatch($employee['appointment_color']) ?> <?= e(employeeColor($employee['appointment_color'])) ?></td><td><?= $employee['active'] ? 'Activo' : 'Inactivo' ?></td><td><a href="<?= e(url('employee', ['id'=>$employee['id']])) ?>">Ver perfil</a> · <a href="<?= e(url('employee-edit', ['id'=>$employee['id']])) ?>">Editar</a></td></tr>
<?php endforeach; ?>
<?php if (!$employees): ?><tr><td colspan="6">No se encontraron empleados con estos filtros.</td></tr><?php endif; ?>
</tbody></table>
</div>
<nav class="employee-pagination" aria-label="Páginas de empleados">
<span><?= e($count) ?> empleados · Página <?= e($number) ?> de <?= e($pages) ?></span>
<?php if ($number > 1): ?><a href="<?= e(url('employees', ['q'=>$search,'state'=>$filter,'p'=>$number-1])) ?>">Anterior</a><?php endif; ?>
<?php if ($number < $pages): ?><a href="<?= e(url('employees', ['q'=>$search,'state'=>$filter,'p'=>$number+1])) ?>">Siguiente</a><?php endif; ?>
</nav>
