<?php $client=trim($appointment['first_name'].' '.$appointment['last_name']); ?>
<p class="client-back"><a href="<?= e($backUrl) ?>">← Volver a la agenda</a></p>
<div class="employee-heading"><h1>Registrar seña</h1><p><?= e($client) ?> · cita del <?= e((new DateTimeImmutable($appointment['starts_at']))->format('d/m/Y H:i')) ?><?= $deposited>0?' · señas ya cobradas: '.e(moneyFormat($deposited)):'' ?></p></div>
<div class="form-card employee-form">
<?php if ($errors): ?><div class="notice error" role="alert"><ul><?php foreach($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php require __DIR__.'/_cash-gate.php'; ?>
<?php if($session): ?>
<form method="post" action="<?= e(url('deposit-new',['appointment'=>$appointment['id']])) ?>">
<?= csrfField() ?>
<input type="hidden" name="idempotency_key" value="<?= e($key) ?>">
<div class="form-grid">
<div class="field"><label for="amount">Importe de la seña *</label><input id="amount" name="amount" type="text" inputmode="decimal" required value="<?= e($form['amount']) ?>"></div>
<div class="field"><label for="method">Medio de pago *</label><select id="method" name="method"><?php foreach(PAYMENT_METHODS as $value=>$label): ?><option value="<?= e($value) ?>" <?= $form['method']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
</div>
<p class="muted">La seña entra a la caja ahora y se descuenta del saldo cuando se aplica a la venta de la cita.</p>
<div class="form-actions"><button class="button" type="submit">Registrar seña</button><a class="button-secondary" href="<?= e($backUrl) ?>">Cancelar</a></div>
</form>
<?php endif; ?>
</div>
