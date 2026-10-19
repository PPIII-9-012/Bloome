<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <h1 style="margin: 0; font-size: 24px; font-weight: 700; color: var(--text-primary);">Tu centro, en un vistazo</h1>
        <p style="margin: 4px 0 0; color: var(--text-muted); font-size: 13px;">Resumen operativo para una atención ordenada y cercana.</p>
    </div>
    <a class="btn-pill-action" href="<?= e(url('agenda')) ?>" style="padding: 8px 16px; display: inline-flex; align-items: center; gap: 6px;">
        <span>Ver agenda completa</span>
        <span aria-hidden="true">→</span>
    </a>
</div>

<!-- KPI STATS CARDS -->
<div class="dashboard-kpi-grid">
    <div class="kpi-card">
        <span class="kpi-title">Clientes registrados</span>
        <span class="kpi-value"><?= e($stats['clients']) ?></span>
        <span class="kpi-hint">Comunidad activa en el sistema</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-title">Citas de hoy</span>
        <span class="kpi-value"><?= e($stats['appointments']) ?></span>
        <span class="kpi-hint"><?= e(date('d/m/Y')) ?> · Sin incluir canceladas</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-title">Servicios activos</span>
        <span class="kpi-value"><?= e($stats['services']) ?></span>
        <span class="kpi-hint">Disponibles en el catálogo</span>
    </div>
</div>

<!-- TODAY AGENDA PREVIEW -->
<div style="margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
    <h2 style="font-size: 17px; font-weight: 600; margin: 0; color: var(--text-primary);">En la agenda de hoy</h2>
    <a href="<?= e(url('agenda')) ?>" style="font-size: 12px; font-weight: 600; color: var(--teal-dark);">Ir a agenda de hoy →</a>
</div>

<div class="figma-table-card">
    <?php require ROOT . '/views/appointment-list.php'; ?>
</div>
