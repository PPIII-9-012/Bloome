<?php $client=trim($sale['first_name'].' '.$sale['last_name']); ?>
<p class="client-back"><a href="<?= e($backUrl) ?>">← Volver a la venta #<?= e($sale['id']) ?></a></p>
<div class="employee-heading"><h1>Anular venta #<?= e($sale['id']) ?></h1><p><?= e($client) ?> · total <?= e(moneyFormat($sale['balance']['total'])) ?></p></div>
<div class="form-card employee-form">
<?php if ($errors): ?><div class="notice error" role="alert"><ul><?php foreach($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="notice" role="status" aria-label="Qué pasa al anular">
<p>La venta no se borra: queda marcada como anulada con el motivo y quién la anuló.</p>
<ul>
<li>Cobros a reintegrar: <strong><?= e(moneyFormat($refundCents)) ?></strong><?= $refundCents>0?' (salen de la caja abierta, con un movimiento inverso).':'.' ?></li>
<li>Señas que vuelven a quedar disponibles: <strong><?= e(moneyFormat($depositCents)) ?></strong> (no mueven la caja).</li>
</ul>
</section>
<?php if($refundCents>0) require __DIR__.'/_cash-gate.php'; ?>
<form method="post" action="<?= e(url('sale-void',['id'=>$sale['id']])) ?>">
<?= csrfField() ?>
<div class="field"><label for="reason">Motivo de la anulación *</label><textarea id="reason" name="reason" rows="3" minlength="5" maxlength="255" required><?= e($reason) ?></textarea><small class="muted">Entre 5 y 255 caracteres. Queda registrado.</small></div>
<div class="form-actions"><a class="button-secondary" href="<?= e($backUrl) ?>">Cancelar</a><button class="button-orange" type="submit">ANULAR VENTA</button></div>
</form>
</div>
