<div class="login-viewport">
    <div class="login-hero-pane" aria-hidden="true"></div>

    <div class="login-form-pane">
        <div class="login-box">

            <div class="login-logo-header">
                <img
                    src="/assets/bloome-logo.png"
                    alt="BLOOME Estética Integral"
                    class="login-logo-img"
                >
            </div>

            <h1>Recuperar contraseña</h1>

            <p>
                Ingresá tu correo electrónico y te enviaremos
                un enlace para restablecer tu contraseña.
            </p>

            <?php if ($errors): ?>
                <div class="notice error" role="alert">
                    <span><?= e($errors['email'] ?? '') ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="notice success" role="status">
                    <span><?= e($success) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('forgot-password')) ?>">
                <?= csrfField() ?>

                <div class="figma-input-group">
                    <span class="figma-input-icon" aria-hidden="true">
                        <!-- icono correo -->
                    </span>

                    <input
                        id="email"
                        name="email"
                        type="email"
                        autocomplete="email"
                        value="<?= e($email) ?>"
                        required
                        maxlength="190"
                        placeholder="Correo Electrónico"
                        class="figma-text-input"
                    >
                </div>

                <button class="figma-btn-acceder" type="submit">
                    ENVIAR ENLACE
                </button>
            </form>

            <div class="login-forgot-wrap">
                <a
                    href="<?= e(url('login')) ?>"
                    class="login-forgot-link"
                >
                    Volver al inicio de sesión
                </a>
            </div>

        </div>
    </div>
</div>