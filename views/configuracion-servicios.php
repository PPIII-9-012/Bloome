<?php
declare(strict_types=1);

$services = $services ?? [];
$categories = $categories ?? [];
$service = $service ?? [
    'id' => null,
    'reference' => '',
    'category_id' => '',
    'name' => '',
    'duration_minutes' => '',
    'price' => '',
    'tax_percentage' => '0',
    'sort_order' => '0',
    'active' => 1,
];
$errors = $errors ?? [];
$search = $search ?? '';
$editing = !empty($service['id']);
?>

<section class="config-page">
    <header class="config-page-header">
        <div>
            <p class="config-eyebrow">Configuración / Ventas</p>
            <h1>Servicios y tratamientos</h1>
            <p>
                Administrá las referencias, categorías, duraciones,
                precios e impuestos de tus servicios.
            </p>
        </div>

        <a class="config-action-link"
           href="<?= e(url('configuracion')) ?>">
            Volver a configuración
        </a>
    </header>

    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="category-notice">
            <?= e($_SESSION['flash']) ?>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="config-form-errors" role="alert">
            <p>Revisá los siguientes campos:</p>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <section class="category-panel">
        <h2><?= $editing ? 'Editar servicio' : 'Nuevo servicio' ?></h2>

        <form method="post"
              action="<?= e(url('configuracion', ['seccion' => 'servicios'])) ?>"
              class="config-form">

            <?= csrfField() ?>

            <input type="hidden" name="action" value="save">
            <?php if ($editing): ?>
                <input type="hidden" name="id"
                       value="<?= (int) $service['id'] ?>">
            <?php endif; ?>

            <div class="config-form-grid">
                <label>
                    Referencia
                    <input type="text" name="reference" maxlength="50"
                           value="<?= e((string) ($service['reference'] ?? '')) ?>">
                    <?php if (isset($errors['reference'])): ?>
                        <small class="field-error"><?= e($errors['reference']) ?></small>
                    <?php endif; ?>
                </label>

                <label>
                    Nombre del servicio *
                    <input type="text" name="name" maxlength="120" required
                           value="<?= e((string) ($service['name'] ?? '')) ?>">
                    <?php if (isset($errors['name'])): ?>
                        <small class="field-error"><?= e($errors['name']) ?></small>
                    <?php endif; ?>
                </label>

                <label>
                    Categoría
                    <select name="category_id">
                        <option value="">Sin categoría</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>"
                                <?= (string) ($service['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>>
                                <?= e($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['category_id'])): ?>
                        <small class="field-error"><?= e($errors['category_id']) ?></small>
                    <?php endif; ?>
                </label>

                <label>
                    Duración (minutos) *
                    <input type="number" name="duration_minutes"
                           min="1" max="65535" step="1" required
                           value="<?= e((string) ($service['duration_minutes'] ?? '')) ?>">
                    <?php if (isset($errors['duration_minutes'])): ?>
                        <small class="field-error"><?= e($errors['duration_minutes']) ?></small>
                    <?php endif; ?>
                </label>

                <label>
                    Precio *
                    <input type="number" name="price" min="0"
                           max="9999999999.99" step="0.01" required
                           value="<?= e((string) ($service['price'] ?? '')) ?>">
                    <?php if (isset($errors['price'])): ?>
                        <small class="field-error"><?= e($errors['price']) ?></small>
                    <?php endif; ?>
                </label>

                <label>
                    Impuesto (%)
                    <input type="number" name="tax_percentage"
                           min="0" max="100" step="0.01"
                           value="<?= e((string) ($service['tax_percentage'] ?? '0')) ?>">
                    <?php if (isset($errors['tax_percentage'])): ?>
                        <small class="field-error"><?= e($errors['tax_percentage']) ?></small>
                    <?php endif; ?>
                </label>

                <label>
                    Orden
                    <input type="number" name="sort_order"
                           min="0" max="2147483647" step="1"
                           value="<?= e((string) ($service['sort_order'] ?? '0')) ?>">
                    <?php if (isset($errors['sort_order'])): ?>
                        <small class="field-error"><?= e($errors['sort_order']) ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <div class="config-form-actions">
                <button type="submit">
                    <?= $editing ? 'Guardar cambios' : 'Crear servicio' ?>
                </button>

                <?php if ($editing): ?>
                    <a class="config-action-link"
                       href="<?= e(url('configuracion', ['seccion' => 'servicios'])) ?>">
                        Cancelar edición
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="category-panel">
        <div class="category-list-heading">
            <h2>Catálogo de servicios</h2>

            <form method="get"
                  action="<?= e(url('configuracion')) ?>"
                  class="category-search">
                <input type="hidden" name="seccion" value="servicios">
                <label>
                    Buscar
                    <input type="search" name="buscar"
                           placeholder="Nombre o referencia"
                           value="<?= e($search) ?>">
                </label>
                <button type="submit">Buscar</button>
            </form>
        </div>

        <?php if (!$services): ?>
            <p class="category-empty">
                No hay servicios para mostrar. Podés crear el primero
                usando el formulario.
            </p>
        <?php else: ?>
            <div class="category-table-wrapper">
                <table class="category-table">
                    <thead>
                        <tr>
                            <th>Referencia</th>
                            <th>Servicio</th>
                            <th>Categoría</th>
                            <th>Duración</th>
                            <th>Precio</th>
                            <th>Impuesto</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $item): ?>
                            <tr>
                                <td><?= e((string) ($item['reference'] ?? '—')) ?></td>
                                <td class="category-name">
                                    <?= e($item['name']) ?>
                                </td>
                                <td><?= e((string) ($item['category_name'] ?? 'Sin categoría')) ?></td>
                                <td><?= (int) $item['duration_minutes'] ?> min</td>
                                <td>$<?= number_format((float) $item['price'], 2, ',', '.') ?></td>
                                <td><?= number_format((float) $item['tax_percentage'], 2, ',', '.') ?>%</td>
                                <td>
                                    <span class="category-status <?= $item['active'] ? 'category-status-active' : 'category-status-inactive' ?>">
                                        <?= $item['active'] ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td class="category-actions">
                                    <a class="config-action-link"
                                       href="<?= e(url('configuracion', [
                                           'seccion' => 'servicios',
                                           'editar' => (int) $item['id'],
                                       ])) ?>">
                                        Editar
                                    </a>

                                    <form method="post"
                                          action="<?= e(url('configuracion', ['seccion' => 'servicios'])) ?>">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action"
                                               value="toggle">
                                        <input type="hidden" name="id"
                                               value="<?= (int) $item['id'] ?>">
                                        <input type="hidden" name="active"
                                               value="<?= $item['active'] ? '0' : '1' ?>">
                                        <button type="submit"
                                                class="category-status-button">
                                            <?= $item['active'] ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>
