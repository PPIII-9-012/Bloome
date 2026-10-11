<div class="employee-heading">
    <h1><?= e($title) ?></h1>
</div>

<div class="employee-profile-layout">
    <div class="employee-profile-sidebar">
        <aside class="employee-identity">
            <div class="employee-avatar" aria-hidden="true"><?= e(initials($employee['name'])) ?></div>
            <h2><?= e($employee['name']) ?></h2>
            <p class="employee-company">AIXA</p>
            <p class="employee-role-badge"><?= e(employeeRoles()[$employee['role']]) ?></p>
            <p class="employee-status"><?= $employee['active'] ? 'Activo' : 'Inactivo' ?></p>
            <a class="button-secondary" href="<?= e(url($self ? 'profile-edit' : 'employee-edit', $self ? [] : ['id'=>$id])) ?>">Editar</a>
        </aside>

        <div class="form-card">
            <h2>Datos personales</h2>
            <dl>
                <dt>Correo electrónico</dt>
                <dd><?= e($employee['email']) ?></dd>
                <dt>Teléfono</dt>
                <dd><?= e($employee['phone'] ?: 'Sin registrar') ?></dd>
                <dt>Color de citas</dt>
                <dd><?= employeeSwatch($employee['appointment_color']) ?> <?= e(employeeColor($employee['appointment_color'])) ?></dd>
            </dl>
            <?php if (!$self): ?>
                <a class="back-link" href="<?= e(url('employees')) ?>">← Volver a empleados</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="employee-profile-details">
        <!-- CARD: Vacaciones (Figma marco 10 y 32) -->
        <div class="form-card profile-card-block">
            <div class="profile-card-header">
                <h2>Vacaciones y ausencias</h2>
                <button type="button" class="btn-figma-primary" onclick="var m = document.getElementById('vacation-modal'); m.style.display = m.style.display === 'none' ? 'block' : 'none';">+ AÑADIR</button>
            </div>
            
            <div id="vacation-modal" class="vacation-form-modal" style="display: none; margin-bottom: 1.5rem; background: var(--bg-surface, #F9FAFB); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-subtle, #E5E7EB);">
                <form action="<?= e(url('employee-vacation-new')) ?>" method="post" class="vacation-inline-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="user_id" value="<?= e($id) ?>">
                    <h3 style="margin-top: 0; margin-bottom: 0.75rem; font-size: 1rem;">Añadir Vacaciones</h3>
                    <div class="form-group" style="margin-bottom: 0.75rem;">
                        <label for="vac_desc">Descripción</label>
                        <input type="text" id="vac_desc" name="description" placeholder="Ej. Vacaciones de verano" required maxlength="190">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                        <div class="form-group">
                            <label for="vac_start">Fecha Inicio</label>
                            <input type="date" id="vac_start" name="starts_at" required>
                        </div>
                        <div class="form-group">
                            <label for="vac_end">Fecha Fin</label>
                            <input type="date" id="vac_end" name="ends_at" required>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="submit" class="btn-figma-primary">GUARDAR</button>
                        <button type="button" class="button-secondary" onclick="document.getElementById('vacation-modal').style.display='none'">Cancelar</button>
                    </div>
                </form>
            </div>

            <?php if (empty($vacations)): ?>
                <p class="empty-state-text" style="color: var(--text-muted, #6B7280); font-size: 0.9rem;">No hay períodos de vacaciones registrados para este empleado.</p>
            <?php else: ?>
                <table class="figma-table-mini" style="width: 100%; border-collapse: collapse; margin-top: 0.5rem;">
                    <thead>
                        <tr style="background: var(--brand-dark, #0E3D3E); color: white; text-align: left;">
                            <th style="padding: 0.5rem 0.75rem; font-size: 0.85rem;">Descripción</th>
                            <th style="padding: 0.5rem 0.75rem; font-size: 0.85rem;">Fecha Inicio</th>
                            <th style="padding: 0.5rem 0.75rem; font-size: 0.85rem;">Fecha Fin</th>
                            <th style="padding: 0.5rem 0.75rem; text-align: right;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vacations as $v): ?>
                            <tr style="border-bottom: 1px solid var(--border-subtle, #E5E7EB);">
                                <td style="padding: 0.6rem 0.75rem;"><?= e($v['description']) ?></td>
                                <td style="padding: 0.6rem 0.75rem;"><?= e(date('d/m/Y', strtotime($v['starts_at']))) ?></td>
                                <td style="padding: 0.6rem 0.75rem;"><?= e(date('d/m/Y', strtotime($v['ends_at']))) ?></td>
                                <td style="padding: 0.6rem 0.75rem; text-align: right;">
                                    <form action="<?= e(url('employee-vacation-delete')) ?>" method="post" style="display: inline;" onsubmit="return confirm('¿Eliminar este período de vacaciones?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="vacation_id" value="<?= e($v['id']) ?>">
                                        <input type="hidden" name="user_id" value="<?= e($id) ?>">
                                        <button type="submit" class="button-delete-icon" style="background: none; border: none; cursor: pointer; color: #EF4444;" title="Eliminar vacación">🗑</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- CARD: Configuración del Horario (Figma marco 10) -->
        <div class="form-card profile-card-block" style="margin-top: 1.5rem;">
            <div class="profile-card-header">
                <h2>Configuración del Horario</h2>
            </div>
            <form action="<?= e(url('employee-schedule-save')) ?>" method="post" class="schedule-form" style="margin-top: 1rem;">
                <?= csrfField() ?>
                <input type="hidden" name="user_id" value="<?= e($id) ?>">
                <div class="schedule-grid" style="display: flex; flex-direction: column; gap: 0.6rem;">
                    <?php foreach ($schedule as $day => $s): ?>
                        <div class="schedule-row" style="display: flex; align-items: center; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px dashed var(--border-subtle, #E5E7EB);">
                            <div class="schedule-day-label" style="min-width: 120px;">
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" name="schedule[<?= $day ?>][is_working]" value="1" <?= $s['is_working'] ? 'checked' : '' ?> onchange="toggleDayInputs(this, <?= $day ?>)">
                                    <span><?= e($s['name']) ?></span>
                                </label>
                            </div>
                            <div class="schedule-day-inputs" id="day-inputs-<?= $day ?>" style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="time" name="schedule[<?= $day ?>][start_time]" value="<?= e(substr($s['start_time'], 0, 5)) ?>" <?= $s['is_working'] ? '' : 'disabled' ?> aria-label="Inicio <?= e($s['name']) ?>" style="padding: 0.35rem 0.5rem; border: 1px solid #D1D5DB; border-radius: 6px;">
                                <span>a</span>
                                <input type="time" name="schedule[<?= $day ?>][end_time]" value="<?= e(substr($s['end_time'], 0, 5)) ?>" <?= $s['is_working'] ? '' : 'disabled' ?> aria-label="Fin <?= e($s['name']) ?>" style="padding: 0.35rem 0.5rem; border: 1px solid #D1D5DB; border-radius: 6px;">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-actions" style="margin-top: 1.25rem;">
                    <button type="submit" class="btn-figma-primary">GUARDAR</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleDayInputs(chk, day) {
    var container = document.getElementById('day-inputs-' + day);
    if (!container) return;
    var inputs = container.querySelectorAll('input[type="time"]');
    inputs.forEach(function(inp) {
        inp.disabled = !chk.checked;
    });
}
</script>
