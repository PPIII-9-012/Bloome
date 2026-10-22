<?php $statuses = ['pending' => 'Pendiente', 'confirmed' => 'Confirmada', 'completed' => 'Completada', 'cancelled' => 'Cancelada', 'missed' => 'Ausente']; ?>
<?php if (!$appointments): ?>
    <div class="empty-block">
        <svg class="empty-icon" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <h3 class="empty-title">Un espacio libre en tu agenda</h3>
        <p class="empty-text">No hay citas registradas para la fecha seleccionada.</p>
    </div>
<?php else: ?>
    <table class="figma-table">
        <thead>
            <tr>
                <th class="col-140">Horario</th>
                <th>Cliente / Servicio</th>
                <th>Profesional</th>
                <?php if (isset($user) && canEdit($user)): ?><th>Acciones</th><?php endif; ?>
                <th class="col-130r">Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($appointments as $appointment): ?>
            <tr>
                <td class="cell-time">
                    <?= e(date('H:i', strtotime($appointment['starts_at']))) ?>
                    <small class="cell-sub">hasta <?= e(date('H:i', strtotime($appointment['ends_at']))) ?></small>
                </td>
                <td>
                    <strong class="cell-name"><?= e($appointment['first_name'] . ' ' . $appointment['last_name']) ?></strong>
                    <small class="cell-note"><?= e($appointment['service_name']) ?></small>
                </td>
                <td class="cell-plain"><?= employeeSwatch($appointment['appointment_color'] ?? null) ?> <?= e($appointment['employee_name']) ?></td>
                <?php if (isset($user) && canEdit($user)): ?><td><a href="<?= e(url('appointment-edit',['id'=>$appointment['id']])) ?>">Editar turno</a></td><?php endif; ?>
                <td class="text-right">
                    <span class="badge <?= e($appointment['status']) ?>"><?= e($statuses[$appointment['status']]) ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
