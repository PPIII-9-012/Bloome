<!-- FIGMA ACTION BAR (14-agenda.png) -->
<div class="figma-action-bar">
    <button type="button" class="figma-action-btn" onclick="alert('La creación de citas se implementará en la próxima etapa.');">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span>Nueva Cita</span>
    </button>
    <button type="button" class="figma-action-btn" onclick="alert('El registro de ventas se implementará en la próxima etapa.');">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polygon points="12 6 13.8 9.8 18 10.4 15 13.3 15.7 17.5 12 15.5 8.3 17.5 9 13.3 6 10.4 10.2 9.8 12 6"/></svg>
        <span>Nueva Venta</span>
    </button>
    <button type="button" class="figma-action-btn" onclick="window.print();">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        <span>Imprimir</span>
    </button>
    <button type="button" class="figma-action-btn" onclick="alert('Exportación de agenda planificada para la próxima entrega.');">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <span>Exportar</span>
    </button>
</div>

<!-- FIGMA INCOME PILLS (14-agenda.png) -->
<div class="figma-income-grid">
    <div class="income-pill-card green">
        <span class="income-pill-title">Ingresos Realizados</span>
        <span class="income-pill-value">$ 0,00</span>
    </div>
    <div class="income-pill-card orange">
        <span class="income-pill-title">Ingresos Pendientes</span>
        <span class="income-pill-value">$ 0,00</span>
    </div>
</div>

<!-- FIGMA FILTERS CARD (14-agenda.png) -->
<div class="figma-filters-card">
    <div class="filter-pills-row">
        <button type="button" class="filter-pill" onclick="alert('Filtro por categorías en desarrollo.');">Categorías ▼</button>
        <button type="button" class="filter-pill" onclick="alert('Filtro por empleados en desarrollo.');">Empleados ▼</button>
        <button type="button" class="filter-pill" onclick="alert('Filtro por estados en desarrollo.');">Estados ▼</button>
    </div>

    <form method="get" class="agenda-date-controls">
        <input type="hidden" name="page" value="agenda">
        <input id="date" type="date" name="date" value="<?= e($date) ?>" required class="agenda-date-input" aria-label="Fecha de agenda">
        <button type="submit" class="btn-pill-action">Ver fecha</button>
        <a href="<?= e(url('agenda')) ?>" class="btn-pill-action">Hoy</a>
    </form>
</div>

<!-- VIEW SEGMENT CONTROL: DÍA / SEMANA / MES -->
<div class="view-segment-control">
    <button type="button" class="view-segment-btn active">Día</button>
    <button type="button" class="view-segment-btn" onclick="alert('Vista semanal en desarrollo.');">Semana</button>
    <button type="button" class="view-segment-btn" onclick="alert('Vista mensual en desarrollo.');">Mes</button>
</div>

<!-- APPOINTMENTS TABLE -->
<div class="figma-table-card">
    <?php require ROOT . '/views/appointment-list.php'; ?>
</div>
