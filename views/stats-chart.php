<?php
$rows=array_slice($chart['rows'],0,12); $type=$chart['type']??'bar';
if($type==='line' && count($rows)>0):
  $max=1; foreach($rows as $r) $max=max($max,(int)$r[1],(int)($r[2]??0));
  $w=760; $h=280; $left=62; $right=24; $top=24; $bottom=48; $plotW=$w-$left-$right; $plotH=$h-$top-$bottom; $n=count($rows); $xAt=fn($i)=>$left+($n===1?$plotW/2:$i*$plotW/($n-1)); $yAt=fn($v)=>$top+$plotH-($v/$max)*$plotH;
  $pathA=$pathB=''; foreach($rows as $i=>$r){$pathA.=($i?' L ':'M ').round($xAt($i),1).' '.round($yAt($r[1]),1);$pathB.=($i?' L ':'M ').round($xAt($i),1).' '.round($yAt((int)($r[2]??0)),1);}
?>
<figure class="stats-chart stats-chart-line"><div class="stats-chart-heading"><strong>Evolución de ingresos</strong><span>Compará ventas generadas con dinero cobrado</span></div><svg role="img" aria-label="<?= e($chart['alt']) ?>" viewBox="0 0 <?= $w ?> <?= $h ?>" width="100%" preserveAspectRatio="xMidYMid meet">
<?php for($g=0;$g<=4;$g++):$gy=$top+$plotH*$g/4; ?><line x1="<?= $left ?>" y1="<?= $gy ?>" x2="<?= $w-$right ?>" y2="<?= $gy ?>" class="stats-gridline"/><text x="<?= $left-10 ?>" y="<?= $gy+4 ?>" text-anchor="end" class="stats-axis-label"><?= e(number_format($max*(4-$g)/4/100,0,',','.')) ?></text><?php endfor; ?>
<path d="<?= e($pathA) ?>" class="stats-line-a"/><path d="<?= e($pathB) ?>" class="stats-line-b"/>
<?php foreach($rows as $i=>$r): ?><circle cx="<?= $xAt($i) ?>" cy="<?= $yAt($r[1]) ?>" r="4" class="stats-dot-a"/><circle cx="<?= $xAt($i) ?>" cy="<?= $yAt((int)($r[2]??0)) ?>" r="4" class="stats-dot-b"/><text x="<?= $xAt($i) ?>" y="<?= $h-18 ?>" text-anchor="middle" class="stats-axis-label"><?= e(textLength($r[0])>12?preg_replace('/^(.{11}).*$/us','$1…',$r[0]):$r[0]) ?></text><?php endforeach; ?>
</svg><figcaption class="stats-legend"><span class="stats-key stats-key-a"></span> Vendido <span class="stats-key stats-key-b"></span> Cobrado</figcaption></figure>
<?php elseif($type==='donut' && count($rows)>0):
$total=array_sum(array_map(fn($r)=>max(0,(int)$r[1]),$rows)); $total=max(1,$total); $colors=['#256b55','#7ba994','#b4cfc2','#d6a85f','#718096','#a5b4c4','#c47f68','#596b61']; $circ=2*pi()*64; $offset=0;
?>
<figure class="stats-chart stats-chart-donut"><div class="stats-chart-heading"><strong>Distribución de gastos</strong><span>Qué categorías concentran el gasto del período</span></div><div class="stats-donut-layout"><svg role="img" aria-label="<?= e($chart['alt']) ?>" viewBox="0 0 180 180" width="180" height="180"><circle cx="90" cy="90" r="64" fill="none" stroke="#edf1ef" stroke-width="25"/>
<?php foreach($rows as $i=>$r):$v=max(0,(int)$r[1]);$len=$circ*$v/$total; ?><circle cx="90" cy="90" r="64" fill="none" stroke="<?= $colors[$i%count($colors)] ?>" stroke-width="25" stroke-dasharray="<?= $len ?> <?= $circ-$len ?>" stroke-dashoffset="<?= -$offset ?>" transform="rotate(-90 90 90)"/><?php $offset+=$len; endforeach; ?><text x="90" y="85" text-anchor="middle" class="stats-donut-total"><?= e(count($rows)) ?></text><text x="90" y="103" text-anchor="middle" class="stats-donut-caption">categorías</text></svg><div class="stats-donut-legend">
<?php foreach($rows as $i=>$r): ?><div class="stats-donut-row"><span class="stats-donut-swatch" style="background:<?= $colors[$i%count($colors)] ?>"></span><span><?= e($r[0]) ?></span><strong><?= e($r[3]) ?></strong><small><?= e(number_format((int)$r[1]/$total*100,1,',','.')) ?>%</small></div><?php endforeach; ?></div></div></figure>
<?php else:
$max=1; foreach($rows as $r) $max=max($max,(int)$r[1]); $rowH=36;$labelW=190;$barMax=420;$h=count($rows)*$rowH+12;
?>
<figure class="stats-chart"><div class="stats-chart-heading"><strong><?= e($chart['series'][0]??'Resultados') ?></strong><span>Comparación por <?= e(($chart['alt']??'resultados')) ?></span></div><svg role="img" aria-label="<?= e($chart['alt']) ?>" viewBox="0 0 <?= $labelW+$barMax+130 ?> <?= $h ?>" width="100%" preserveAspectRatio="xMinYMin meet">
<?php foreach($rows as $n=>$r):$y=$n*$rowH+5;$wa=(int)round((int)$r[1]/$max*$barMax); ?><text x="<?= $labelW-10 ?>" y="<?= $y+15 ?>" text-anchor="end" class="stats-label"><?= e(textLength($r[0])>27?preg_replace('/^(.{26}).*$/us','$1…',$r[0]):$r[0]) ?></text><rect x="<?= $labelW ?>" y="<?= $y ?>" width="<?= $barMax ?>" height="18" rx="5" class="stats-bar-track"/><rect x="<?= $labelW ?>" y="<?= $y ?>" width="<?= $wa ?>" height="18" rx="5" class="stats-bar-a"/><text x="<?= $labelW+$wa+8 ?>" y="<?= $y+14 ?>" class="stats-value"><?= e($r[3]) ?></text><?php endforeach; ?></svg><?php if(count($chart['rows'])>12): ?><figcaption class="stats-legend">Se muestran los primeros 12 resultados. La tabla incluye todos.</figcaption><?php endif; ?></figure>
<?php endif; ?>
