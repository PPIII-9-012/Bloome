<!-- FIGMA ACTION BAR (05-clientes.png) -->
<div class="figma-action-bar">
    <?php if (canEdit($user)): ?>
    <a class="figma-action-btn" href="<?= e(url('client-new')) ?>" id="btn-nuevo-cliente">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
        <span>Nuevo Cliente</span>
    </a>
    <?php endif; ?>
</div>

<!-- FIGMA SEARCH ROW -->
<div class="figma-search-row">
    <form method="get" class="figma-search-input-wrap">
        <input type="hidden" name="page" value="clients">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input id="q" name="q" value="<?= e($search) ?>" placeholder="Buscar" maxlength="100" aria-label="Buscar clientes">
        <?php if ($search !== ''): ?>
            <a class="clear-filter-link" href="<?= e(url('clients')) ?>" title="Limpiar búsqueda">✕ Limpiar</a>
        <?php endif; ?>
        <button type="submit" class="button-secondary btn-xs">Buscar</button>
    </form>
</div>

<!-- FIGMA TABLE (05-clientes.png) -->
<div class="figma-table-card">
    <?php if (!$clients): ?>
        <div class="empty-block">
            <svg class="empty-icon" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <h3 class="empty-title"><?= $search !== '' ? 'No encontramos coincidencias.' : 'No hay clientes registrados todavía.' ?></h3>
            <p class="empty-text"><?= $search !== '' ? 'Probá con otro nombre, teléfono o correo.' : 'Agregá el primer cliente usando el botón superior.' ?></p>
        </div>
    <?php else: ?>
        <table class="figma-table">
            <thead>
                <tr>
                    <th class="col-100">Código</th>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>Teléfono</th>
                    <th>Ingresos</th>
                    <th class="col-80r"><span class="sr-only">Acciones</span>☰</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $client): ?>
                <tr>
                    <td class="code-cell">#<?= e(str_pad((string)$client['id'], 4, '0', STR_PAD_LEFT)) ?></td>
                    <td><strong><a href="<?= e(url('client', ['id' => $client['id']])) ?>"><?= e($client['first_name']) ?></a></strong></td>
                    <td><?= e($client['last_name']) ?></td>
                    <td><?= e($client['phone'] ?: ($client['email'] ?: 'Sin teléfono')) ?></td>
                    <td>—</td>
                    <td class="text-right">
                        <a class="table-action-edit" href="<?= e(url('client', ['id' => $client['id']])) ?>" aria-label="Ver ficha de <?= e($client['first_name'] . ' ' . $client['last_name']) ?>"><span>Ver ficha</span></a>
                        <?php if (canEdit($user)): ?>
                        <a class="table-action-edit" href="<?= e(url('client-edit', ['id' => $client['id']])) ?>" aria-label="Editar a <?= e($client['first_name'] . ' ' . $client['last_name']) ?>">
                            <span>Editar</span>
                            <span aria-hidden="true">↗</span>
                        </a>
                        <?php else: ?>
                        <span class="cell-note">Solo lectura</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="figma-table-pagination">
            <span><?= e($count) ?> clientes · Página <?= e($number) ?> de <?= e($pages) ?></span>
            <div class="flex-gap-8">
                <?php if ($number > 1): ?>
                    <a class="button-secondary btn-xs2" href="<?= e(url('clients', ['q' => $search, 'p' => $number - 1])) ?>">Anterior</a>
                <?php endif; ?>
                <?php if ($number < $pages): ?>
                    <a class="button-secondary btn-xs2" href="<?= e(url('clients', ['q' => $search, 'p' => $number + 1])) ?>">Siguiente</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
