<?php
$groups = [
    'Ventas' => [
        ['estadisticas-ventas', 'general', 'General', 'Resumen de ventas e ingresos durante el período seleccionado.', 'chart'],
        ['estadisticas-ventas', 'categoria', 'Por categoría', 'Compará los ingresos según la categoría de servicio.', 'grid'],
        ['estadisticas-ventas', 'servicio', 'Por servicios', 'Identificá qué servicios generan más actividad e ingresos.', 'service'],
        ['estadisticas-ventas', 'genero', 'Por género', 'Consultá la distribución de clientes por género.', 'users'],
    ],
    'Gastos' => [
        ['estadisticas-gastos', 'general', 'General', 'Revisá los gastos registrados y su evolución en el tiempo.', 'wallet'],
        ['estadisticas-gastos', 'categoria', 'Por categoría', 'Analizá cómo se distribuyen los gastos por categoría.', 'grid'],
    ],
    'Otros' => [
        ['estadisticas-horas', 'general', 'Horas totales empleadas', 'Consultá las horas de trabajo registradas en el período.', 'clock'],
    ],
];
$icons = [
    'chart' => '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-5 5"/>',
    'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'service' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v10M7 12h10"/>',
    'users' => '<circle cx="9" cy="8" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M16 4a4 4 0 0 1 0 8M19 14a6 6 0 0 1 3 5v2"/>',
    'wallet' => '<rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 9h18M16 14h2"/><path d="M6 5V3h12v2"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
];
?>
<div class="page-heading employee-heading"><h1>Estadísticas</h1><p>Elegí un informe. Por defecto muestra el mes en curso y se puede cambiar el período.</p></div>
<?php foreach ($groups as $title => $links): ?>
<section class="form-card stats-group stats-group-full" aria-labelledby="stats-<?= e(strtolower($title)) ?>">
    <h2 id="stats-<?= e(strtolower($title)) ?>"><?= e($title) ?></h2>
    <div class="stats-report-list">
    <?php foreach ($links as [$page, $view, $label, $description, $icon]): ?>
        <a class="stats-report-card" href="<?= e(url($page, ['v' => $view])) ?>">
            <span class="stats-report-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?= $icons[$icon] ?></svg></span>
            <span class="stats-report-info"><strong><?= e($label) ?></strong><span><?= e($description) ?></span></span>
        </a>
    <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>
