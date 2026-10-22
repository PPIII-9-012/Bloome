<div class="ph-bar">
    <div>
        <h1 class="ph-title">Tu centro, en un vistazo</h1>
        <p class="ph-sub">Resumen operativo para una atención ordenada y cercana.</p>
    </div>
    <a class="btn-pill-action btn-compact" href="<?= e(url('agenda')) ?>">
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

<?php if ($money !== null): ?>
<div class="dashboard-kpi-grid">
    <a class="kpi-card kpi-link" href="<?= e(url('sales', ['from' => date('Y-m-d'), 'to' => date('Y-m-d')])) ?>">
        <span class="kpi-title">Vendido hoy</span>
        <span class="kpi-value"><?= e(moneyFormat($money['sold'])) ?></span>
        <span class="kpi-hint">Cobrado <?= e(moneyFormat($money['collected'])) ?> · Pendiente <?= e(moneyFormat($money['pending'])) ?></span>
    </a>
    <a class="kpi-card kpi-link" href="<?= e(url('caja')) ?>">
        <span class="kpi-title">Caja</span>
        <?php if ($money['cashOpen']): ?>
            <span class="kpi-value"><?= e(moneyFormat($money['cashExpected'])) ?></span>
            <span class="kpi-hint">Efectivo esperado · caja abierta</span>
        <?php else: ?>
            <span class="kpi-value">Cerrada</span>
            <span class="kpi-hint">Abrí la caja para registrar cobros</span>
        <?php endif; ?>
    </a>
</div>
<?php endif; ?>

<!-- TODAY AGENDA PREVIEW -->
<div class="section-bar">
    <h2 class="section-title">En la agenda de hoy</h2>
    <a href="<?= e(url('agenda')) ?>" class="link-small">Ir a agenda de hoy →</a>
</div>

<div class="figma-table-card">
    <?php require ROOT . '/views/appointment-list.php'; ?>
</div>
