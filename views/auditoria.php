<?php
declare(strict_types=1);
$logs = $logs ?? [];
$actionLabels = [
    'employee_created' => 'Empleado creado',
    'employee_updated' => 'Empleado actualizado',
    'employee_activated' => 'Empleado activado',
    'employee_deactivated' => 'Empleado desactivado',
    'audit_view' => 'Consulta de auditoría',
];
?>
<section class="page-section audit-page">
    <div class="section-header">
        <div>
            <h1>Registro de acciones</h1>
            <p>Últimos 250 eventos registrados, incluidos cambios de roles y accesos denegados.</p>
        </div>
        <a class="button" href="<?= e(url('configuracion')) ?>">Volver a configuración</a>
    </div>
    <?php if (!$logs): ?>
        <div class="empty-state"><h2>Todavía no hay acciones registradas</h2><p>Las acciones aparecerán aquí cuando se realicen operaciones auditadas.</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>Resultado</th><th>Detalles</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $log):
                    $details = [];
                    if (!empty($log['details'])) {
                        $decoded = json_decode((string)$log['details'], true);
                        if (is_array($decoded)) $details = $decoded;
                    }
                    $roleChange = !empty($details['role_changed']);
                ?>
                    <tr>
                        <td><?= e(date('d/m/Y H:i:s', strtotime((string)$log['created_at']))) ?></td>
                        <td><?= e($log['actor_name'] ?? 'Usuario eliminado / sistema') ?></td>
                        <td><?= e($actionLabels[$log['action']] ?? ucwords(str_replace('_', ' ', (string)$log['action']))) ?>
                            <?php if ($roleChange): ?><br><strong>Rol:</strong> <?= e((string)$details['role_before']) ?> → <?= e((string)$details['role_after']) ?><?php endif; ?>
                        </td>
                        <td><?= e((string)$log['entity']) ?><?= $log['entity_id'] !== null ? ' #' . e((string)$log['entity_id']) : '' ?></td>
                        <td><span class="status-badge <?= $log['outcome'] === 'denied' ? 'audit-denied' : 'audit-success' ?>"><?= $log['outcome'] === 'denied' ? 'Denegado' : 'Correcto' ?></span></td>
                        <td><?= $details ? e(json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
