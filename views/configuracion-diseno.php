<?php
$themeColor = $design['theme_color'] ?? '#6D5BD0';
$logoPath = $design['logo_path'] ?? null;
?>

<section class="configuration-form">

    <header class="configuration-form__header">
        <div>
            <p class="configuration-form__eyebrow">
                Configuración
            </p>

            <h1>Diseño</h1>

            <p>
                Personalizá el color principal y el logo de Bloome.
            </p>
        </div>

        <a href="<?= e(url('configuracion')) ?>">
            Volver a Configuración
        </a>
    </header>

    <?php if (!empty($errors)): ?>
        <div class="configuration-form__errors" role="alert">
            <p>Revisá los siguientes puntos:</p>

            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <section class="configuration-panel">
        <h2>Color principal</h2>

        <p>
            Elegí el color que querés utilizar en Bloome.
        </p>

        <form
            method="post"
            action="<?= e(url('configuracion', ['seccion' => 'diseno'])) ?>"
        >
            <?= csrfField() ?>

            <label class="configuration-field" for="theme_color">
                <span>Color del tema</span>

                <div class="configuration-color-control">
                    <input
                        id="theme_color"
                        name="theme_color"
                        type="color"
                        value="<?= e($themeColor) ?>"
                        required
                    >

                    <code><?= e($themeColor) ?></code>
                </div>
            </label>

            <button class="configuration-submit" type="submit">
                Guardar color
            </button>
        </form>
    </section>

    <section class="configuration-panel">
        <h2>Logo del centro</h2>

        <p>
            Formatos permitidos: PNG, JPG o WebP.
            Tamaño máximo: 2 MiB.
        </p>

        <?php if ($logoPath): ?>
            <div class="configuration-logo-preview">
                <img
                    src="<?= e($logoPath) ?>"
                    alt="Logo actual del centro"
                >
            </div>
        <?php else: ?>
            <p class="configuration-muted">
                Todavía no hay un logo cargado.
            </p>
        <?php endif; ?>

        <form
            method="post"
            enctype="multipart/form-data"
            action="<?= e(url('configuracion', ['seccion' => 'diseno'])) ?>"
        >
            <?= csrfField() ?>

            <input
                type="hidden"
                name="action"
                value="upload_logo"
            >

            <label class="configuration-field" for="logo">
                <span>Seleccionar imagen</span>

                <input
                    id="logo"
                    name="logo"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    required
                >
            </label>

            <button class="configuration-submit" type="submit">
                Subir logo
            </button>
        </form>
    </section>

</section>