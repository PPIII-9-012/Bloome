<div class="login-viewport recovery-viewport">
    <div class="login-hero-pane" aria-hidden="true"></div>
    <div class="login-form-pane">
        <section class="login-box recovery-box" aria-labelledby="reset-title">
            <div class="login-logo-header">
                <img src="/assets/bloome-logo.png" alt="BLOOME Estética Integral" class="login-logo-img">
            </div>
            <h1 id="reset-title" class="recovery-title">Restablecer contraseña</h1>
            <p class="recovery-description">Ingresá el correo, el código de seis dígitos que recibiste y elegí una contraseña nueva. El código vence a los 15 minutos.</p>

            <?php if (!empty($errors['general'])): ?>
                <div class="notice error" role="alert"><?= e($errors['general']) ?></div>
            <?php endif; ?>
            <?php if (!empty($errors['password'])): ?>
                <div class="notice error" role="alert"><?= e($errors['password']) ?></div>
            <?php endif; ?>
            <?php if (!empty($errors['password_confirm'])): ?>
                <div class="notice error" role="alert"><?= e($errors['password_confirm']) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('reset-password')) ?>">
                <?= csrfField() ?>
                <label class="recovery-label" for="reset-email">Correo electrónico</label>
                <input id="reset-email" name="email" type="email" autocomplete="email" value="<?= e($email) ?>" required maxlength="190" placeholder="correo@ejemplo.com" class="figma-text-input">

                <label class="recovery-label" for="reset-code">Código de verificación</label>
                <input id="reset-code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" value="<?= e($code) ?>" required minlength="6" maxlength="6" placeholder="123456" class="figma-text-input">

                <label class="recovery-label" for="reset-password">Nueva contraseña</label>
                <input id="reset-password" name="password" type="password" autocomplete="new-password" required minlength="12" maxlength="72" placeholder="Mínimo 12 caracteres" class="figma-text-input">

                <label class="recovery-label" for="reset-password-confirm">Repetir contraseña</label>
                <input id="reset-password-confirm" name="password_confirm" type="password" autocomplete="new-password" required minlength="12" maxlength="72" placeholder="Repetí la contraseña" class="figma-text-input">

                <button class="figma-btn-acceder" type="submit">RESTABLECER CONTRASEÑA</button>
            </form>
            <p class="recovery-back"><a href="<?= e(url('forgot-password')) ?>">Solicitar otro código</a></p>
            <p class="recovery-back"><a href="<?= e(url('login')) ?>">Volver a iniciar sesión</a></p>
        </section>
    </div>
</div>
