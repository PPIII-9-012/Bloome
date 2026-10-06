<div style="margin-bottom: 20px;">
    <a href="<?= e(url('clients')) ?>" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: var(--teal-dark); margin-bottom: 12px;">
        <span aria-hidden="true">←</span> Volver a Clientes
    </a>
    <h1 style="margin: 0; font-size: 24px; font-weight: 700; color: var(--text-primary);"><?= e($title) ?></h1>
    <p style="margin: 4px 0 0; color: var(--text-muted); font-size: 13px;">Completá los datos del cliente para su historial y atención en el centro.</p>
</div>

<div class="form-card">
    <?php if ($errors): ?>
        <div class="notice error" role="alert">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span>Revisá los campos indicados a continuación. Los datos válidos se conservaron.</span>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url($page, $page === 'client-edit' ? ['id' => $id] : [])) ?>">
        <?= csrfField() ?>
        
        <div class="form-grid">
            <?php
            $fields = [
                'first_name' => ['label' => 'Nombre *', 'type' => 'text', 'max' => 100, 'required' => true],
                'last_name' => ['label' => 'Apellido *', 'type' => 'text', 'max' => 100, 'required' => true],
                'email' => ['label' => 'Correo electrónico', 'type' => 'email', 'max' => 190, 'required' => false],
                'phone' => ['label' => 'Teléfono', 'type' => 'tel', 'max' => 30, 'required' => false],
                'birth_date' => ['label' => 'Fecha de nacimiento', 'type' => 'date', 'max' => 10, 'required' => false],
            ];
            foreach ($fields as $field => $cfg):
            ?>
            <div class="field">
                <label for="<?= $field ?>"><?= e($cfg['label']) ?></label>
                <input id="<?= $field ?>"
                       name="<?= $field ?>"
                       type="<?= $cfg['type'] ?>"
                       value="<?= e($client[$field]) ?>"
                       maxlength="<?= $cfg['max'] ?>"
                       <?= $cfg['required'] ? 'required' : '' ?>
                       <?= $field === 'birth_date' ? 'min="1900-01-01" max="' . date('Y-m-d') . '"' : '' ?>
                       <?= isset($errors[$field]) ? 'aria-invalid="true" aria-describedby="error-' . $field . '"' : '' ?>>
                <?php if (isset($errors[$field])): ?>
                    <small class="field-error" id="error-<?= $field ?>"><?= e($errors[$field]) ?></small>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div class="field full-width">
                <label for="notes">Observaciones</label>
                <textarea id="notes"
                          name="notes"
                          rows="4"
                          maxlength="2000"
                          placeholder="Preferencias, condiciones particulares o notas útiles para su atención."
                          <?= isset($errors['notes']) ? 'aria-invalid="true" aria-describedby="error-notes"' : '' ?>><?= e($client['notes']) ?></textarea>
                <?php if (isset($errors['notes'])): ?>
                    <small class="field-error" id="error-notes"><?= e($errors['notes']) ?></small>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <a class="button-secondary" href="<?= e(url('clients')) ?>">Cancelar</a>
            <button class="button-orange" type="submit">
                <span><?= $page === 'client-new' ? 'GUARDAR CLIENTE' : 'GUARDAR CAMBIOS' ?></span>
                <span aria-hidden="true">✓</span>
            </button>
        </div>
    </form>
</div>
