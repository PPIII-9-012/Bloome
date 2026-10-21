<?php
$groups=[
    'Ventas'=>[['estadisticas-ventas','general','General'],['estadisticas-ventas','categoria','Por categoría'],['estadisticas-ventas','servicio','Por servicios'],['estadisticas-ventas','genero','Por género']],
    'Gastos'=>[['estadisticas-gastos','general','General'],['estadisticas-gastos','categoria','Por categoría']],
    'Otros'=>[['estadisticas-horas','general','Horas totales empleadas']],
];
?>
<div class="employee-heading"><h1>Estadísticas</h1><p>Elegí un informe. Por defecto muestra el mes en curso y se puede cambiar el período.</p></div>
<?php foreach($groups as $title=>$links): ?>
<section class="form-card stats-group" aria-labelledby="stats-<?= e(strtolower($title)) ?>">
<h2 id="stats-<?= e(strtolower($title)) ?>"><?= e($title) ?></h2>
<ul class="stats-links">
<?php foreach($links as [$p,$v,$label]): ?><li><a class="stats-link" href="<?= e(url($p,['v'=>$v])) ?>"><?= e($label) ?></a></li><?php endforeach; ?>
</ul>
</section>
<?php endforeach; ?>
