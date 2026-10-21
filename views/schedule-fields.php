<p class="schedule-hint">Marcá los días de atención. Las horas de los días desmarcados no se guardan.</p>
<?php if($scheduleFlag==='is_working'): ?><p class="schedule-hint">Para jornada partida, indicá un descanso por día (por ejemplo, 13:00 a 15:00). Dejá ambos campos vacíos para horario continuo. Cambiar el horario no modifica ni cancela los turnos ya guardados: revisalos en la agenda.</p><?php endif; ?>
<div class="schedule-grid">
<?php foreach($schedule as $day=>$s): ?>
<div class="schedule-row">
<label class="schedule-day"><input type="checkbox" name="<?= e($schedulePrefix) ?>[<?= $day ?>][<?= e($scheduleFlag) ?>]" value="1" <?= $s[$scheduleFlag]?'checked':'' ?> <?= $scheduleEditable?'':'disabled' ?>> <?= e($s['name']) ?></label>
<div class="schedule-times">
<label>Inicio <?= e($s['name']) ?><input type="time" name="<?= e($schedulePrefix) ?>[<?= $day ?>][start_time]" value="<?= e(substr($s['start_time'],0,5)) ?>" <?= $scheduleEditable?'':'disabled' ?>></label>
<label>Fin <?= e($s['name']) ?><input type="time" name="<?= e($schedulePrefix) ?>[<?= $day ?>][end_time]" value="<?= e(substr($s['end_time'],0,5)) ?>" <?= $scheduleEditable?'':'disabled' ?>></label>
<?php if($scheduleFlag==='is_working'): ?>
<label>Inicio del descanso <?= e($s['name']) ?><input type="time" name="<?= e($schedulePrefix) ?>[<?= $day ?>][break_start]" value="<?= e(substr($s['break_start'],0,5)) ?>" <?= $scheduleEditable?'':'disabled' ?>></label>
<label>Fin del descanso <?= e($s['name']) ?><input type="time" name="<?= e($schedulePrefix) ?>[<?= $day ?>][break_end]" value="<?= e(substr($s['break_end'],0,5)) ?>" <?= $scheduleEditable?'':'disabled' ?>></label>
<?php endif; ?>
</div></div>
<?php endforeach; ?>
</div>
