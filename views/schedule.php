<div class="employee-heading"><h1><?= e($title) ?></h1><p>Definí los días y horas de atención del centro. Los días sin configurar permanecen cerrados.</p></div>
<div class="form-card schedule-card"><form action="<?= e(url('schedule')) ?>" method="post">
<?= csrfField() ?>
<?php $schedulePrefix='days'; $scheduleFlag='is_open'; $scheduleEditable=true; require ROOT.'/views/schedule-fields.php'; ?>
<div class="form-actions"><button class="button-orange" type="submit">GUARDAR</button></div>
</form></div>
