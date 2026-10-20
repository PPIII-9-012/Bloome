<div class="employee-heading"><h1>Agenda diaria</h1><p>Consultá los turnos por fecha. Las vistas semanal y mensual y los filtros adicionales todavía no están disponibles.</p></div>
<?php if (canEdit($user)): ?><div class="figma-action-bar"><a class="figma-action-btn" href="<?= e(url('appointment-new')) ?>">Nuevo turno</a></div><?php endif; ?>
<div class="figma-filters-card">
<form method="get" class="agenda-date-controls">
<input type="hidden" name="page" value="agenda">
<label for="date">Fecha</label><input id="date" type="date" name="date" value="<?= e($date) ?>" required class="agenda-date-input">
<button type="submit" class="btn-pill-action">Ver fecha</button>
<a href="<?= e(url('agenda')) ?>" class="btn-pill-action">Hoy</a>
</form></div>
<div class="figma-table-card"><?php require ROOT . '/views/appointment-list.php'; ?></div>
