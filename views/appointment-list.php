<?php $statuses = ['pending' => 'Pendiente', 'confirmed' => 'Confirmada', 'completed' => 'Completada', 'cancelled' => 'Cancelada']; ?>
<?php if (!$appointments): ?>
    <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 12px; color: var(--teal-dark);"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <h3 style="margin: 0 0 6px; font-size: 16px; font-weight: 600; color: var(--text-primary);">Un espacio libre en tu agenda</h3>
        <p style="margin: 0; font-size: 13px;">No hay citas registradas para la fecha seleccionada.</p>
    </div>
<?php else: ?>
    <table class="figma-table">
        <thead>
            <tr>
                <th style="width: 140px;">Horario</th>
                <th>Cliente / Servicio</th>
                <th>Profesional</th>
                <th style="width: 130px; text-align: right;">Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($appointments as $appointment): ?>
            <tr>
                <td style="font-weight: 600; color: var(--teal-dark);">
                    <?= e(date('H:i', strtotime($appointment['starts_at']))) ?>
                    <small style="display: block; color: var(--text-muted); font-weight: normal; font-size: 11px;">hasta <?= e(date('H:i', strtotime($appointment['ends_at']))) ?></small>
                </td>
                <td>
                    <strong style="display: block; font-size: 14px; color: var(--text-primary);"><?= e($appointment['first_name'] . ' ' . $appointment['last_name']) ?></strong>
                    <small style="color: var(--text-muted); font-size: 12px;"><?= e($appointment['service_name']) ?></small>
                </td>
                <td style="color: var(--text-secondary);"><?= e($appointment['employee_name']) ?></td>
                <td style="text-align: right;">
                    <span class="badge <?= e($appointment['status']) ?>"><?= e($statuses[$appointment['status']]) ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
