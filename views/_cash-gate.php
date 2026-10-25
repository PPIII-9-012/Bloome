<?php /* Aviso de caja: sin caja abierta no se puede cobrar. Espera $session, $page. */ ?>
<?php if(!$session): ?>
<section class="notice" role="status" aria-labelledby="cash-closed-title">
<h2 id="cash-closed-title">No hay una caja abierta</h2>
<p>Para registrar cobros, señas o reintegros primero hay que abrir la caja del día.</p>
<form method="post" class="inline-form">
<?= csrfField() ?>
<input type="hidden" name="intent" value="open-cash">
<div class="field"><label for="opening_cash">Efectivo inicial</label><input id="opening_cash" name="opening_cash" type="text" inputmode="decimal" value="0" required></div>
<button class="button-secondary" type="submit">Abrir caja</button>
</form>
</section>
<?php endif; ?>
