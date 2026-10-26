<div class="login-viewport recovery-viewport">
    <div class="login-hero-pane" aria-hidden="true"></div>
    <div class="login-form-pane">
        <section class="login-box recovery-box" aria-labelledby="recovery-title">
            <div class="login-logo-header">
                <img src="/assets/bloome-logo.png" alt="BLOOME Estética Integral" class="login-logo-img">
            </div>
            <h1 id="recovery-title" class="recovery-title">Recuperar contraseña</h1>
            <p class="recovery-description">Ingresá tu correo, verificá el código y creá una nueva contraseña sin salir de esta pantalla.</p>

            <?php if (!empty($errors['general'])): ?>
                <div class="notice error" role="alert"><?= e($errors['general']) ?></div>
            <?php endif; ?>
            <?php if (!empty($errors['email'])): ?>
                <div class="notice error" role="alert"><?= e($errors['email']) ?></div>
            <?php endif; ?>
            <?php if (!empty($errors['password'])): ?>
                <div class="notice error" role="alert"><?= e($errors['password']) ?></div>
            <?php endif; ?>
            <?php if (!empty($errors['password_confirm'])): ?>
                <div class="notice error" role="alert"><?= e($errors['password_confirm']) ?></div>
            <?php endif; ?>
            <?php if ($notice !== ''): ?>
                <div class="notice info" role="status"><?= e($notice) ?></div>
            <?php endif; ?>

            <?php if ($recoveryStep === 'email'): ?>
                <form method="post" action="<?= e(url('forgot-password')) ?>" id="recovery-email-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="recovery_action" value="send_code">
                    <label class="recovery-label" for="recovery-email">Correo electrónico</label>
                    <div class="recovery-email-row">
                        <input id="recovery-email" name="email" type="email" autocomplete="email" value="<?= e($email) ?>" required maxlength="190" placeholder="correo@ejemplo.com" class="figma-text-input" aria-describedby="recovery-email-status">
                        <button id="recovery-send-button" class="figma-btn-acceder" type="submit" disabled>ENVIAR CÓDIGO</button>
                    </div>
                    <p id="recovery-email-status" class="recovery-field-status" aria-live="polite">Ingresá un correo válido para habilitar el botón.</p>
                </form>
            <?php elseif ($recoveryStep === 'code'): ?>
                <div class="recovery-current-email">Código enviado a <strong><?= e($email) ?></strong></div>
                <form method="post" action="<?= e(url('forgot-password')) ?>" id="recovery-code-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="recovery_action" value="verify_code">
                    <label class="recovery-label" for="recovery-code">Código de verificación</label>
                    <input id="recovery-code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" value="<?= e($code) ?>" required minlength="6" maxlength="6" placeholder="123456" class="figma-text-input">
                    <button class="figma-btn-acceder" type="submit">VERIFICAR CÓDIGO</button>
                </form>
                <form method="post" action="<?= e(url('forgot-password')) ?>" id="recovery-resend-form" class="recovery-resend-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="recovery_action" value="send_code">
                    <input type="hidden" name="email" value="<?= e($email) ?>">
                    <button id="recovery-resend-button" class="recovery-resend-button" type="submit" disabled>Podés solicitar otro código en <span id="recovery-countdown" data-until="<?= (int) $cooldownUntil ?>">--:--</span></button>
                </form>
            <?php else: ?>
                <div class="recovery-current-email">Correo verificado: <strong><?= e($email) ?></strong></div>
                <form method="post" action="<?= e(url('forgot-password')) ?>" id="recovery-password-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="recovery_action" value="reset_password">
                    <label class="recovery-label" for="recovery-password">Nueva contraseña</label>
                    <input id="recovery-password" name="password" type="password" autocomplete="new-password" required minlength="12" maxlength="72" placeholder="Mínimo 12 caracteres" class="figma-text-input">
                    <label class="recovery-label" for="recovery-password-confirm">Repetir contraseña</label>
                    <input id="recovery-password-confirm" name="password_confirm" type="password" autocomplete="new-password" required minlength="12" maxlength="72" placeholder="Repetí la contraseña" class="figma-text-input">
                    <p id="recovery-password-match" class="recovery-field-status" aria-live="polite"></p>
                    <button class="figma-btn-acceder" type="submit">RESTABLECER CONTRASEÑA</button>
                </form>
            <?php endif; ?>
            <p class="recovery-back"><a href="<?= e(url('login')) ?>">Volver a iniciar sesión</a></p>
        </section>
    </div>
</div>

<script src="/assets/password-recovery.js" defer></script>
