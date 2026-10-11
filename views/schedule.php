<div class="schedule-header" style="margin-bottom: 1.5rem;">
    <h1 style="margin: 0; font-size: 1.5rem; color: var(--brand-dark, #0E3D3E);"><?= e($title) ?></h1>
    <p style="margin: 0.25rem 0 0; color: var(--text-muted, #6B7280); font-size: 0.9rem;">Define los días y rangos de atención general del centro para la agenda.</p>
</div>

<div class="form-card" style="max-width: 640px; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #E5E7EB;">
    <form action="<?= e(url('schedule')) ?>" method="post">
        <?= csrfField() ?>
        
        <div class="schedule-grid" style="display: flex; flex-direction: column; gap: 1rem;">
            <?php foreach ($schedule as $day => $s): ?>
                <div class="schedule-row" style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px dashed #E5E7EB;">
                    <div style="min-width: 140px;">
                        <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-size: 0.95rem;">
                            <input type="checkbox" name="days[<?= $day ?>][is_open]" value="1" <?= $s['is_open'] ? 'checked' : '' ?> onchange="toggleBizInputs(this, <?= $day ?>)">
                            <strong style="color: var(--text-dark, #111827);"><?= e($s['name']) ?></strong>
                        </label>
                    </div>

                    <div id="biz-inputs-<?= $day ?>" style="display: flex; align-items: center; gap: 0.6rem;">
                        <div>
                            <input type="time" name="days[<?= $day ?>][start_time]" value="<?= e(substr($s['start_time'] ?? '08:00', 0, 5)) ?>" <?= $s['is_open'] ? '' : 'disabled' ?> style="padding: 0.45rem 0.6rem; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 0.95rem;" aria-label="Apertura <?= e($s['name']) ?>">
                        </div>
                        <span style="color: #6B7280; font-size: 0.9rem;">a</span>
                        <div>
                            <input type="time" name="days[<?= $day ?>][end_time]" value="<?= e(substr($s['end_time'] ?? '20:00', 0, 5)) ?>" <?= $s['is_open'] ? '' : 'disabled' ?> style="padding: 0.45rem 0.6rem; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 0.95rem;" aria-label="Cierre <?= e($s['name']) ?>">
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top: 2rem;">
            <button type="submit" class="btn-figma-primary" style="padding: 0.65rem 2rem; font-size: 1rem;">GUARDAR</button>
        </div>
    </form>
</div>

<script>
function toggleBizInputs(chk, day) {
    var container = document.getElementById('biz-inputs-' + day);
    if (!container) return;
    var inputs = container.querySelectorAll('input[type="time"]');
    inputs.forEach(function(inp) {
        inp.disabled = !chk.checked;
    });
}
</script>
