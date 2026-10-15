<p class="schedule-hint">Marcá los días de atención. Las horas de los días desmarcados no se guardan.</p>
<div class="schedule-grid">
<?php foreach($schedule as $day=>$s): ?>
<div class="schedule-row">
<label class="schedule-day"><input type="checkbox" name="<?= e($schedulePrefix) ?>[<?= $day ?>][<?= e($scheduleFlag) ?>]" value="1" <?= $s[$scheduleFlag]?'checked':'' ?> <?= $scheduleEditable?'':'disabled' ?>> <?= e($s['name']) ?></label>
<div class="schedule-times">
<label>Inicio <?= e($s['name']) ?><input type="time" name="<?= e($schedulePrefix) ?>[<?= $day ?>][start_time]" value="<?= e(substr($s['start_time'],0,5)) ?>" <?= $scheduleEditable?'':'disabled' ?>></label>
<label>Fin <?= e($s['name']) ?><input type="time" name="<?= e($schedulePrefix) ?>[<?= $day ?>][end_time]" value="<?= e(substr($s['end_time'],0,5)) ?>" <?= $scheduleEditable?'':'disabled' ?>></label>
</div></div>
<?php endforeach; ?>
</div>
