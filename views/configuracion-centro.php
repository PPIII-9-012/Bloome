<section class="config-page">
    <header class="config-page-header">
        <p class="config-eyebrow">Configuración · Empresa</p>
        <h1>Datos del centro</h1>
        <p>Estos datos se guardan en la base de datos de Bloome.</p>
        <a class="config-action-link" href="<?= e(url('configuracion')) ?>">← Volver a Configuración</a>
    </header>

    <?php if (!empty($errors)): ?>
        <div class="config-form-errors" role="alert">
            <p>Revisá los siguientes campos:</p>
            <ul>
                <?php foreach ($errors as $message): ?>
                    <li><?= e($message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="config-form" method="post" action="<?= e(url('configuracion', ['seccion' => 'centro'])) ?>">
        <?= csrfField() ?>

        <div class="config-form-grid">
            <label>
                Nombre del centro *
                <input name="center_name" maxlength="120" required value="<?= e($center['center_name'] ?? '') ?>">
                <?php if (isset($errors['center_name'])): ?><small><?= e($errors['center_name']) ?></small><?php endif; ?>
            </label>

            <label>
                Razón social
                <input name="legal_name" maxlength="160" value="<?= e($center['legal_name'] ?? '') ?>">
                <?php if (isset($errors['legal_name'])): ?><small><?= e($errors['legal_name']) ?></small><?php endif; ?>
            </label>

            <label>
                Correo electrónico
                <input type="email" name="email" maxlength="190" value="<?= e($center['email'] ?? '') ?>">
                <?php if (isset($errors['email'])): ?><small><?= e($errors['email']) ?></small><?php endif; ?>
            </label>

            <label>
                País *
                <input name="country" maxlength="100" required value="<?= e($center['country'] ?? 'Argentina') ?>">
                <?php if (isset($errors['country'])): ?><small><?= e($errors['country']) ?></small><?php endif; ?>
            </label>

            <label>
                Provincia
                <input name="province" maxlength="100" value="<?= e($center['province'] ?? '') ?>">
                <?php if (isset($errors['province'])): ?><small><?= e($errors['province']) ?></small><?php endif; ?>
            </label>

            <label>
                Código postal
                <input name="postal_code" maxlength="20" value="<?= e($center['postal_code'] ?? '') ?>">
                <?php if (isset($errors['postal_code'])): ?><small><?= e($errors['postal_code']) ?></small><?php endif; ?>
            </label>

            <label class="config-form-wide">
                Dirección
                <input name="address" maxlength="190" value="<?= e($center['address'] ?? '') ?>">
                <?php if (isset($errors['address'])): ?><small><?= e($errors['address']) ?></small><?php endif; ?>
            </label>

            <label>
                Teléfono móvil
                <input type="tel" name="phone" maxlength="30" value="<?= e($center['phone'] ?? '') ?>">
                <?php if (isset($errors['phone'])): ?><small><?= e($errors['phone']) ?></small><?php endif; ?>
            </label>
        </div>

        <div class="config-form-actions">
            <button type="submit">Guardar cambios</button>
        </div>
    </form>
</section>
