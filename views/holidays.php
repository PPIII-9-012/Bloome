<div class="holidays-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h1 style="margin: 0; font-size: 1.5rem; color: var(--brand-dark, #0E3D3E);"><?= e($title) ?></h1>
        <p style="margin: 0.25rem 0 0; color: var(--text-muted, #6B7280); font-size: 0.9rem;">Días no laborables y festivos del centro que bloquean el agendamiento de turnos.</p>
    </div>
    <div>
        <button type="button" class="btn-figma-primary" onclick="var m = document.getElementById('holiday-modal'); m.style.display = m.style.display === 'none' ? 'block' : 'none';">+ Nuevo</button>
    </div>
</div>

<!-- Modal / Formulario Nuevo Feriado (Figma 29-configuracion-agenda-feriado-nuevo.png) -->
<div id="holiday-modal" class="form-card" style="display: none; margin-bottom: 1.5rem; border-left: 4px solid var(--accent-orange, #F27A38);">
    <h2 style="font-size: 1.15rem; margin-top: 0; margin-bottom: 1rem;">Nuevo feriado</h2>
    <form action="<?= e(url('holiday-new')) ?>" method="post">
        <?= csrfField() ?>
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem; margin-bottom: 1rem;">
            <div class="form-group">
                <label for="holiday_date">Fecha</label>
                <input type="date" id="holiday_date" name="date" required style="width: 100%; padding: 0.5rem; border: 1px solid #D1D5DB; border-radius: 6px;">
            </div>
            <div class="form-group">
                <label for="holiday_desc">Descripción</label>
                <input type="text" id="holiday_desc" name="description" placeholder="Ej. Feriado Nacional / Navidad" required maxlength="190" style="width: 100%; padding: 0.5rem; border: 1px solid #D1D5DB; border-radius: 6px;">
            </div>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn-figma-primary">GUARDAR</button>
            <button type="button" class="button-secondary" onclick="document.getElementById('holiday-modal').style.display='none'">Cancelar</button>
        </div>
    </form>
</div>

<!-- Buscador (Figma 08-configuracion-agenda-feriado.png) -->
<div class="filters-card" style="margin-bottom: 1rem;">
    <form method="get" action="/" class="search-form" style="display: flex; gap: 0.5rem;">
        <input type="hidden" name="page" value="holidays">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Buscar feriado por descripción o fecha..." style="flex: 1; padding: 0.5rem 0.75rem; border: 1px solid #D1D5DB; border-radius: 6px;">
        <button type="submit" class="button-secondary">Buscar</button>
        <?php if ($search !== ''): ?>
            <a href="<?= e(url('holidays')) ?>" class="button-secondary" style="text-decoration: none;">Limpiar</a>
        <?php endif; ?>
    </form>
</div>

<!-- Tabla de Festivos (Figma 08-configuracion-agenda-feriado.png) -->
<div class="table-container" style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #E5E7EB;">
    <table class="figma-table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: var(--brand-dark, #0E3D3E); color: white; text-align: left;">
                <th style="padding: 0.75rem 1rem; width: 200px;">Fecha</th>
                <th style="padding: 0.75rem 1rem;">Descripción</th>
                <th style="padding: 0.75rem 1rem; text-align: right; width: 80px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($holidays)): ?>
                <tr>
                    <td colspan="3" style="padding: 2rem; text-align: center; color: #6B7280;">
                        <?= $search !== '' ? 'No se encontraron feriados para la búsqueda realizada.' : 'No hay feriados registrados en el sistema.' ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($holidays as $h): ?>
                    <tr style="border-bottom: 1px solid #E5E7EB;">
                        <td style="padding: 0.75rem 1rem; font-weight: 500;">
                            <?= e(date('d/m/Y', strtotime($h['holiday_date']))) ?>
                        </td>
                        <td style="padding: 0.75rem 1rem;">
                            <?= e($h['description']) ?>
                        </td>
                        <td style="padding: 0.75rem 1rem; text-align: right;">
                            <form action="<?= e(url('holiday-delete')) ?>" method="post" style="display: inline;" onsubmit="return confirm('¿Eliminar este feriado?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= e($h['id']) ?>">
                                <button type="submit" style="background: none; border: none; cursor: pointer; color: #EF4444; font-size: 1.1rem;" title="Eliminar feriado">🗑</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
