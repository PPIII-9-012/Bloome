<div class="login-viewport">
    <div class="login-hero-pane" aria-hidden="true"></div>
    <div class="login-form-pane">
        <div class="login-box">
            <div class="login-logo-header">
                <img src="/assets/bloome-logo.png" alt="BLOOME Estética Integral" class="login-logo-img">
            </div>

            <?php if ($errors): ?>
                <div class="notice error" role="alert">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span><?= e($errors['login']) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('login')) ?>">
                <?= csrfField() ?>
                
                <div class="figma-input-group">
                    <span class="figma-input-icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </span>
                    <input id="email" name="email" type="email" autocomplete="username" value="<?= e($email) ?>" required maxlength="190" placeholder="Correo Electrónico" class="figma-text-input">
                </div>

                <div class="figma-input-group">
                    <span class="figma-input-icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="1024" placeholder="Contraseña" class="figma-text-input">
                </div>

                <button class="figma-btn-acceder" type="submit">ACCEDER</button>

                <div class="login-forgot-wrap">
                    <a href="<?= e(url('forgot-password')) ?>" class="login-forgot-link">
                        ¿Has olvidado tu contraseña?
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
/*
!!! Agregar a la base de datos .
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,

    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT password_reset_tokens_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    INDEX password_reset_tokens_user (user_id),
    INDEX password_reset_tokens_expires (expires_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
*/