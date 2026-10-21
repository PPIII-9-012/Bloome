<?php
declare(strict_types=1);

$employees = $employees ?? [];
$employee = $employee ?? null;
$errors = $errors ?? [];
$search = $search ?? '';
$status = $status ?? 'all';
$editing = is_array($employee);
?>

<section class="page-section">
    <div class="section-header">
        <div>
            <h1>Empleados</h1>
            <p>Administrá las cuentas y los perfiles del equipo.</p>
        </div>

        <a class="button button-primary"
           href="<?= e(url('configuracion', ['seccion' => 'empleados', 'action' => 'crear'])) ?>">
            Nuevo empleado
        </a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-info">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <form method="get" action="<?= e(url('configuracion')) ?>" class="filter-form">
        <input type="hidden" name="seccion" value="empleados">

        <label for="buscar">Buscar empleado</label>
        <input
            id="buscar"
            type="search"
            name="buscar"
            value="<?= e($search) ?>"
            placeholder="Nombre, correo, teléfono o especialidad"
        >

        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Todos</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Activos</option>
            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactivos</option>
        </select>

        <button type="submit" class="button">Buscar</button>
        <a class="button" href="<?= e(url('configuracion', ['seccion' => 'empleados'])) ?>">
            Limpiar
        </a>
    </form>

    <?php if (!$employees): ?>
        <div class="empty-state">
            <h2>No hay empleados para mostrar</h2>
            <p>Probá con otra búsqueda o registrá un nuevo empleado.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Teléfono</th>
                        <th>Especialidad</th>
                        <th>Comisión</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $item): ?>
                        <?php
                        $active = (bool) $item['employee_active']
                            && (bool) $item['user_active'];
                        ?>
                        <tr>
                            <td><?= e($item['name']) ?></td>
                            <td><?= e($item['email']) ?></td>
                            <td>
                                <?= match ($item['role']) {
                                    'admin' => 'Administrador',
                                    'reception' => 'Recepción',
                                    'professional' => 'Profesional',
                                    default => $item['role'],
                                } ?>
                            </td>
                            <td><?= e($item['phone'] ?? '—') ?></td>
                            <td><?= e($item['specialty'] ?? '—') ?></td>
                            <td>
                                <?= $item['commission_percentage'] !== null
                                    ? e((string) $item['commission_percentage']) . ' %'
                                    : '—' ?>
                            </td>
                            <td>
                                <span class="status-badge">
                                    <?= $active ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td>
                                <a
                                    href="<?= e(url('configuracion', [
                                        'seccion' => 'empleados',
                                        'action' => 'editar',
                                        'id' => $item['id'],
                                    ])) ?>"
                                >Editar</a>

                                <form
                                    method="post"
                                    action="<?= e(url('configuracion', ['seccion' => 'empleados'])) ?>"
                                    class="inline-form"
                                >
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                                    <input type="hidden" name="active" value="<?= $active ? '0' : '1' ?>">

                                    <button type="submit" class="button-link">
                                        <?= $active ? 'Desactivar' : 'Activar' ?>
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

<?php if (($_GET['action'] ?? '') === 'crear' || $editing): ?>
    <section class="page-section">
        <h2><?= $editing ? 'Editar empleado' : 'Nuevo empleado' ?></h2>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <p>Revisá los siguientes campos:</p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form
            method="post"
            action="<?= e(url('configuracion', ['seccion' => 'empleados'])) ?>"
            class="form-grid"
        >
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <?php if ($editing): ?>
                <input type="hidden" name="id" value="<?= e((string) $employee['id']) ?>">
            <?php endif; ?>

            <label for="name">Nombre completo *</label>
            <input
                id="name"
                name="name"
                maxlength="100"
                required
                value="<?= e((string) ($employee['name'] ?? '')) ?>"
            >

            <label for="email">Correo electrónico *</label>
            <input
                id="email"
                type="email"
                name="email"
                maxlength="190"
                required
                value="<?= e((string) ($employee['email'] ?? '')) ?>"
            >

            <label for="role">Rol *</label>
            <select id="role" name="role" required>
                <option value="professional" <?= ($employee['role'] ?? 'professional') === 'professional' ? 'selected' : '' ?>>Profesional</option>
                <option value="reception" <?= ($employee['role'] ?? '') === 'reception' ? 'selected' : '' ?>>Recepción</option>
            </select>

            <label for="phone">Teléfono</label>
            <input
                id="phone"
                name="phone"
                maxlength="30"
                value="<?= e((string) ($employee['phone'] ?? '')) ?>"
            >

            <label for="specialty">Especialidad</label>
            <input
                id="specialty"
                name="specialty"
                maxlength="120"
                value="<?= e((string) ($employee['specialty'] ?? '')) ?>"
            >

            <label for="hire_date">Fecha de ingreso</label>
            <input
                id="hire_date"
                type="date"
                name="hire_date"
                value="<?= e((string) ($employee['hire_date'] ?? '')) ?>"
            >

            <label for="commission_percentage">Comisión (%)</label>
            <input
                id="commission_percentage"
                type="number"
                name="commission_percentage"
                min="0"
                max="100"
                step="0.01"
                value="<?= e((string) ($employee['commission_percentage'] ?? '')) ?>"
            >

            <label for="password">
                Contraseña <?= $editing ? '(dejala vacía para conservar la actual)' : '*' ?>
            </label>
            <input
                id="password"
                type="password"
                name="password"
                minlength="8"
                maxlength="1024"
                <?= $editing ? '' : 'required' ?>
                autocomplete="new-password"
            >

            <div class="form-actions">
                <button type="submit" class="button button-primary">
                    <?= $editing ? 'Guardar cambios' : 'Crear empleado' ?>
                </button>

                <a
                    class="button"
                    href="<?= e(url('configuracion', ['seccion' => 'empleados'])) ?>"
                >Cancelar</a>
            </div>
        </form>
    </section>
<?php endif; ?>
