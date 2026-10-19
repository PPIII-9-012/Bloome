<!-- FIGMA ACTION BAR (05-clientes.png) -->
<div class="figma-action-bar">
    <?php if (canEdit($user)): ?>
    <a class="figma-action-btn" href="<?= e(url('client-new')) ?>" id="btn-nuevo-cliente">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
        <span>Nuevo Cliente</span>
    </a>
    <?php endif; ?>
    <button type="button" class="figma-action-btn" onclick="alert('Exportación en formato CSV planificada para la próxima entrega.');">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <span>Exportar</span>
    </button>
    <button type="button" class="figma-action-btn" onclick="alert('Importación de clientes planificada para la próxima entrega.');">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <span>Importar</span>
    </button>
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
        <button type="submit" class="button-secondary" style="padding: 6px 14px; font-size: 12px;">Buscar</button>
    </form>
</div>

<!-- FIGMA TABLE (05-clientes.png) -->
<div class="figma-table-card">
    <?php if (!$clients): ?>
        <div style="text-align: center; padding: 48px 24px; color: var(--text-muted);">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 12px; color: var(--teal-dark);"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <h3 style="margin: 0 0 6px; font-size: 16px; font-weight: 600; color: var(--text-primary);"><?= $search !== '' ? 'No encontramos coincidencias.' : 'No hay clientes registrados todavía.' ?></h3>
            <p style="margin: 0; font-size: 13px;"><?= $search !== '' ? 'Probá con otro nombre, teléfono o correo.' : 'Agregá el primer cliente usando el botón superior.' ?></p>
        </div>
    <?php else: ?>
        <table class="figma-table">
            <thead>
                <tr>
                    <th style="width: 100px;">Código</th>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>Teléfono</th>
                    <th>Ingresos</th>
                    <th style="width: 80px; text-align: right;"><span class="sr-only">Acciones</span>☰</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $client): ?>
                <tr>
                    <td class="code-cell">#<?= e(str_pad((string)$client['id'], 4, '0', STR_PAD_LEFT)) ?></td>
                    <td><strong><?= e($client['first_name']) ?></strong></td>
                    <td><?= e($client['last_name']) ?></td>
                    <td><?= e($client['phone'] ?: ($client['email'] ?: 'Sin teléfono')) ?></td>
                    <td>—</td>
                    <td style="text-align: right;">
                        <?php if (canEdit($user)): ?>
                        <a class="table-action-edit" href="<?= e(url('client-edit', ['id' => $client['id']])) ?>" aria-label="Editar a <?= e($client['first_name'] . ' ' . $client['last_name']) ?>">
                            <span>Editar</span>
                            <span aria-hidden="true">↗</span>
                        </a>
                        <?php else: ?>
                        <span style="color: var(--text-muted); font-size: 12px;">Solo lectura</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="figma-table-pagination">
            <span><?= e($count) ?> clientes · Página <?= e($number) ?> de <?= e($pages) ?></span>
            <div style="display: flex; gap: 8px;">
                <?php if ($number > 1): ?>
                    <a class="button-secondary" style="padding: 6px 12px; font-size: 12px;" href="<?= e(url('clients', ['q' => $search, 'p' => $number - 1])) ?>">Anterior</a>
                <?php endif; ?>
                <?php if ($number < $pages): ?>
                    <a class="button-secondary" style="padding: 6px 12px; font-size: 12px;" href="<?= e(url('clients', ['q' => $search, 'p' => $number + 1])) ?>">Siguiente</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
