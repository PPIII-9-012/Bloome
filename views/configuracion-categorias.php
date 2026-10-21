
<section class="config-page">
    <header class="config-page-header">
        <p class="config-eyebrow">Configuración · Ventas</p>
        <h1>Categorías de servicios</h1>
        <p>Organizá los servicios y tratamientos que ofrece tu centro.</p>
        <a class="config-action-link" href="<?= e(url('configuracion')) ?>">
            ← Volver a Configuración
        </a>
    </header>

    <?php if (!empty($_SESSION['flash'])): ?>
        <p class="category-notice" role="status">
            <?= e($_SESSION['flash']) ?>
        </p>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="config-form-errors" role="alert">
            <p>Revisá los siguientes errores:</p>
            <ul>
                <?php foreach ($errors as $message): ?>
                    <li><?= e($message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <section class="category-panel">
        <h2><?= $editingCategory ? 'Editar categoría' : 'Nueva categoría' ?></h2>

        <form
            class="config-form"
            method="post"
            action="<?= e(url('configuracion', ['seccion' => 'categorias'])) ?>"
        >
            <?= csrfField() ?>

            <input
                type="hidden"
                name="action"
                value="<?= $editingCategory ? 'update' : 'create' ?>"
            >

            <?php if ($editingCategory): ?>
                <input
                    type="hidden"
                    name="category_id"
                    value="<?= (int) $editingCategory['id'] ?>"
                >
            <?php endif; ?>

            <div class="config-form-grid">
                <label>
                    Nombre *
                    <input
                        type="text"
                        name="name"
                        maxlength="120"
                        required
                        value="<?= e($form['name'] ?? '') ?>"
                        placeholder="Ej.: Tratamientos faciales"
                    >
                    <?php if (isset($errors['name'])): ?>
                        <small class="field-error"><?= e($errors['name']) ?></small>
                    <?php endif; ?>
                </label>

                <label>
                    Color identificador *
                    <input
                        type="color"
                        name="color"
                        required
                        value="<?= e($form['color'] ?? '#8B5CF6') ?>"
                    >
                    <?php if (isset($errors['color'])): ?>
                        <small class="field-error"><?= e($errors['color']) ?></small>
                    <?php endif; ?>
                </label>

                <label>
                    Orden de aparición *
                    <input
                        type="number"
                        name="sort_order"
                        min="0"
                        max="2147483647"
                        required
                        value="<?= e((string) ($form['sort_order'] ?? '0')) ?>"
                    >
                    <?php if (isset($errors['sort_order'])): ?>
                        <small class="field-error"><?= e($errors['sort_order']) ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <div class="config-form-actions">
                <button type="submit">
                    <?= $editingCategory ? 'Guardar cambios' : 'Crear categoría' ?>
                </button>

                <?php if ($editingCategory): ?>
                    <a
                        class="config-action-link"
                        href="<?= e(url('configuracion', ['seccion' => 'categorias'])) ?>"
                    >
                        Cancelar
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="category-panel">
        <div class="category-list-heading">
            <div>
                <h2>Categorías existentes</h2>
                <p>Las categorías desactivadas se conservan y pueden reactivarse.</p>
            </div>
        </div>

        <form
            class="category-search"
            method="get"
            action="/"
        >
            <input type="hidden" name="page" value="configuracion">
            <input type="hidden" name="seccion" value="categorias">

            <label>
                Buscar por nombre
                <input
                    type="search"
                    name="buscar"
                    maxlength="120"
                    value="<?= e($search) ?>"
                    placeholder="Ej.: Manicuría"
                >
            </label>

            <button type="submit">Buscar</button>

            <?php if ($search !== ''): ?>
                <a
                    class="config-action-link"
                    href="<?= e(url('configuracion', ['seccion' => 'categorias'])) ?>"
                >
                    Limpiar
                </a>
            <?php endif; ?>
        </form>

        <?php if (!$categories): ?>
            <p class="category-empty">
                <?= $search !== ''
                    ? 'No se encontraron categorías con ese nombre.'
                    : 'Todavía no hay categorías. Podés crear la primera con el formulario anterior.' ?>
            </p>
        <?php else: ?>
            <div class="category-table-wrapper">
                <table class="category-table">
                    <thead>
                        <tr>
                            <th>Categoría</th>
                            <th>Orden</th>
                            <th>Servicios</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td>
                                    <span class="category-name">
                                        <svg
                                            class="category-color"
                                            viewBox="0 0 18 18"
                                            role="img"
                                            aria-label="Color <?= e($category['color']) ?>"
                                        >
                                            <circle
                                                cx="9"
                                                cy="9"
                                                r="7"
                                                fill="<?= e($category['color']) ?>"
                                                stroke="#d1d5db"
                                            />
                                        </svg>
                                        <?= e($category['name']) ?>
                                    </span>
                                </td>
                                <td><?= (int) $category['sort_order'] ?></td>
                                <td><?= (int) $category['service_count'] ?></td>
                                <td>
                                    <span class="<?= $category['active']
                                        ? 'category-status category-status-active'
                                        : 'category-status category-status-inactive' ?>">
                                        <?= $category['active'] ? 'Activa' : 'Inactiva' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="category-actions">
                                        <a
                                            class="config-action-link"
                                            href="<?= e(url('configuracion', [
                                                'seccion' => 'categorias',
                                                'editar' => $category['id'],
                                            ])) ?>"
                                        >
                                            Editar
                                        </a>

                                        <form
                                            method="post"
                                            action="<?= e(url('configuracion', ['seccion' => 'categorias'])) ?>"
                                        >
                                            <?= csrfField() ?>
                                            <input
                                                type="hidden"
                                                name="category_id"
                                                value="<?= (int) $category['id'] ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="action"
                                                value="<?= $category['active'] ? 'deactivate' : 'activate' ?>"
                                            >
                                            <button
                                                type="submit"
                                                class="category-status-button"
                                            >
                                                <?= $category['active'] ? 'Desactivar' : 'Reactivar' ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>
