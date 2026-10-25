<?php
$balance=$sale['balance']; $client=trim($sale['first_name'].' '.$sale['last_name']);
?>
<p class="client-back"><a href="<?= e($backUrl) ?>">← Volver a la venta #<?= e($sale['id']) ?></a></p>
<div class="employee-heading"><h1>Cobrar venta #<?= e($sale['id']) ?></h1><p><?= e($client) ?> · total <?= e(moneyFormat($balance['total'])) ?> · cobrado <?= e(moneyFormat($balance['paid'])) ?> · saldo <strong><?= e(moneyFormat($balance['balance'])) ?></strong></p></div>
<div class="form-card employee-form">
<?php if ($errors): ?><div class="notice error" role="alert"><ul><?php foreach($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php require __DIR__.'/_cash-gate.php'; ?>
<?php if($balance['balance']<=0): ?>
<div class="notice" role="status">Esta venta no tiene saldo pendiente.</div>
<?php elseif($session): ?>
<form method="post" action="<?= e(url('sale-pay',['id'=>$sale['id']])) ?>">
<?= csrfField() ?>
<input type="hidden" name="idempotency_key" value="<?= e($key) ?>">
<div class="form-grid">
<div class="field"><label for="amount">Importe a cobrar *</label><input id="amount" name="amount" type="text" inputmode="decimal" required value="<?= e($form['amount']) ?>" aria-describedby="amount-help"><small id="amount-help" class="muted">Puede ser un cobro parcial. Máximo: <?= e(moneyFormat($balance['balance'])) ?>.</small></div>
<div class="field"><label for="method">Medio de pago *</label><select id="method" name="method"><?php foreach(PAYMENT_METHODS as $value=>$label): ?><option value="<?= e($value) ?>" <?= $form['method']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
</div>
<div class="form-actions"><button class="button" type="submit">Registrar cobro</button><a class="button-secondary" href="<?= e($backUrl) ?>">Cancelar</a></div>
</form>
<?php endif; ?>
</div>
<?php if($deposits && $balance['balance']>0): ?>
<section class="form-card" aria-labelledby="deposits-title">
<h2 id="deposits-title">Señas disponibles de <?= e($client) ?></h2>
<p class="muted">Aplicar una seña no mueve la caja: el dinero ya entró cuando se cobró.</p>
<?php foreach($deposits as $deposit): $suggest=min($deposit['available'],$balance['balance']); ?>
<form method="post" action="<?= e(url('sale-pay',['id'=>$sale['id']])) ?>" class="inline-form">
<?= csrfField() ?>
<input type="hidden" name="intent" value="apply-deposit"><input type="hidden" name="payment_id" value="<?= e($deposit['payment_id']) ?>">
<p>Seña #<?= e($deposit['payment_id']) ?> (<?= e((new DateTimeImmutable($deposit['paid_at']))->format('d/m/Y')) ?>) · disponible <?= e(moneyFormat($deposit['available'])) ?></p>
<div class="field"><label for="dep-<?= e($deposit['payment_id']) ?>">Importe a aplicar</label><input id="dep-<?= e($deposit['payment_id']) ?>" name="amount" type="text" inputmode="decimal" value="<?= e(centsToDecimal($suggest)) ?>"></div>
<button class="button-secondary" type="submit">Aplicar seña</button>
</form>
<?php endforeach; ?>
</section>
<?php endif; ?>
