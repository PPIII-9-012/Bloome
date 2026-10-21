<?php
// Gráfico de barras horizontales en SVG (sin scripts ni estilos en línea). $chart: ['rows'=>[[etiqueta, valor, valor2|null, texto]], 'series'=>[nombre1, nombre2|null]].
$rows=$chart['rows']; $max=1; foreach($rows as $r) $max=max($max,$r[1],(int)($r[2]??0));
$rows=array_slice($rows,0,12); $two=($chart['series'][1]??null)!==null;
$rowH=$two?34:22; $labelW=170; $barMax=420; $h=count($rows)*$rowH+8;
?>
<figure class="stats-chart">
<svg role="img" aria-label="<?= e($chart['alt']) ?>" viewBox="0 0 <?= $labelW+$barMax+110 ?> <?= $h ?>" width="100%" preserveAspectRatio="xMinYMin meet">
<?php foreach($rows as $n=>[$label,$a,$b,$text]): $y=$n*$rowH+4; $wa=max(1,(int)round($a/$max*$barMax)); ?>
<text x="<?= $labelW-8 ?>" y="<?= $y+13 ?>" text-anchor="end" class="stats-label"><?= e(textLength($label)>26 ? preg_replace('/^(.{25}).*$/us','$1…',$label) : $label) ?></text>
<rect x="<?= $labelW ?>" y="<?= $y ?>" width="<?= $a>0?$wa:0 ?>" height="14" rx="2" class="stats-bar-a"/>
<?php if($two): $wb=max(1,(int)round($b/$max*$barMax)); ?><rect x="<?= $labelW ?>" y="<?= $y+16 ?>" width="<?= $b>0?$wb:0 ?>" height="14" rx="2" class="stats-bar-b"/><?php endif; ?>
<text x="<?= $labelW+max($a>0?$wa:0,$b>0&&$two?$wb:0)+6 ?>" y="<?= $y+13 ?>" class="stats-value"><?= e($text) ?></text>
<?php endforeach; ?>
</svg>
<?php if($two): ?><figcaption class="stats-legend"><span class="stats-key stats-key-a" aria-hidden="true"></span> <?= e($chart['series'][0]) ?> <span class="stats-key stats-key-b" aria-hidden="true"></span> <?= e($chart['series'][1]) ?></figcaption><?php endif; ?>
<?php if(count($chart['rows'])>12): ?><figcaption class="stats-legend">El gráfico muestra los 12 primeros. La tabla trae todos.</figcaption><?php endif; ?>
</figure>
