<div class="employee-heading"><h1><?= e($title) ?></h1><p>Días no laborables del centro.</p></div>
<details class="form-card schedule-add"><summary>＋ Nuevo feriado</summary>
<form method="post" action="<?= e(url('holiday-new')) ?>">
<?= csrfField() ?><div class="schedule-times">
<label>Fecha<input type="date" name="date" required></label><label>Descripción<input type="text" name="description" maxlength="190" required></label></div>
<div class="form-actions"><button type="submit" class="button-orange">GUARDAR</button></div></form></details>
<form class="employee-filters" method="get"><input type="hidden" name="page" value="holidays"><label>Buscar feriado<input type="search" name="q" value="<?= e($search) ?>" maxlength="100"></label><button class="button-secondary">Buscar</button></form>
<div class="employee-table-wrap"><table class="figma-table"><thead><tr><th>Fecha</th><th>Descripción</th><th>Acciones</th></tr></thead><tbody>
<?php foreach($holidays as $h): ?><tr><td><?= e(date('d/m/Y',strtotime($h['holiday_date']))) ?></td><td><?= e($h['description']) ?></td><td><form method="post" action="<?= e(url('holiday-delete')) ?>"><?= csrfField() ?><input type="hidden" name="id" value="<?= e($h['id']) ?>"><button class="button-secondary">Eliminar feriado</button></form></td></tr><?php endforeach; ?>
<?php if(!$holidays): ?><tr><td colspan="3">No se encontraron feriados.</td></tr><?php endif; ?>
</tbody></table></div>
