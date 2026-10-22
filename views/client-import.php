<div class="csv-head">
    <a class="csv-back" href="<?= e(url('clients')) ?>"><span aria-hidden="true">←</span> Volver a Clientes</a>
    <h1 class="csv-title">Importar clientes</h1>
    <p class="csv-sub">Cargá varios clientes a la vez desde una planilla guardada como CSV UTF-8.</p>
</div>

<?php if ($fatal !== null): ?>
    <div class="notice error" role="alert"><span><?= e($fatal) ?> No se importó ningún cliente.</span></div>
<?php endif; ?>

<?php if ($result !== null): ?>
    <section class="form-card csv-result" aria-labelledby="csv-result-title" role="status">
        <h2 id="csv-result-title" class="csv-h2">Resultado de la importación</h2>
        <ul class="csv-summary">
            <li><strong><?= e($result['created']) ?></strong> <?= $result['created'] === 1 ? 'cliente importado' : 'clientes importados' ?></li>
            <li><strong><?= e(count($result['duplicates'])) ?></strong> omitidos por estar repetidos</li>
            <li><strong><?= e(count($result['invalid'])) ?></strong> con errores, no importados</li>
        </ul>
        <?php if ($result['invalid']): ?>
            <h3 class="csv-h3">Filas con errores</h3>
            <p class="csv-sub">Corregilas en la planilla y volvé a subir el archivo: las filas ya importadas se omitirán como repetidas.</p>
            <table class="figma-table csv-table">
                <thead><tr><th>Fila</th><th>Cliente</th><th>Qué corregir</th></tr></thead>
                <tbody><?php foreach ($result['invalid'] as $row): ?>
                    <tr><td><?= e($row['line']) ?></td><td><?= e($row['name'] !== '' ? $row['name'] : '—') ?></td><td><?= e(implode(' ', $row['messages'])) ?></td></tr>
                <?php endforeach; ?></tbody>
            </table>
        <?php endif; ?>
        <?php if ($result['duplicates']): ?>
            <h3 class="csv-h3">Filas omitidas por repetidas</h3>
            <p class="csv-sub">No se fusionan ni se modifican clientes existentes. Si hay que actualizarlos, editalos desde su ficha.</p>
            <table class="figma-table csv-table">
                <thead><tr><th>Fila</th><th>Cliente</th><th>Motivo</th></tr></thead>
                <tbody><?php foreach ($result['duplicates'] as $row): ?>
                    <tr><td><?= e($row['line']) ?></td><td><?= e($row['name']) ?></td><td><?= e($row['message']) ?></td></tr>
                <?php endforeach; ?></tbody>
            </table>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="form-card">
    <h2 class="csv-h2">Subir archivo</h2>
    <form method="post" action="<?= e(url('clients-import')) ?>" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div class="field full-width">
            <label for="file">Archivo CSV (hasta 1 MB y 1.000 filas)</label>
            <input id="file" name="file" type="file" accept=".csv,text/csv" required>
        </div>
        <div class="form-actions">
            <a class="button-secondary" href="<?= e(url('clients-template')) ?>">Descargar plantilla</a>
            <button class="button-orange" type="submit">Importar</button>
        </div>
    </form>
</section>

<section class="form-card">
    <h2 class="csv-h2">Cómo preparar el archivo</h2>
    <ul class="csv-help">
        <li>Usá la plantilla: las columnas deben ser <code>nombre, apellido, email, telefono, fecha_nacimiento, genero, observaciones</code>, en ese orden.</li>
        <li>Obligatorios: nombre y apellido. El resto puede ir vacío.</li>
        <li>Fecha como <code>AAAA-MM-DD</code> o <code>DD/MM/AAAA</code>. Género: <code>Femenino</code>, <code>Masculino</code> u <code>Otro</code>.</li>
        <li>Se considera repetido a quien comparta correo, teléfono (aunque esté escrito distinto) o nombre y fecha de nacimiento con un cliente existente o con una fila anterior.</li>
        <li>Las filas válidas se importan aunque otras tengan errores; las inválidas se listan con el motivo.</li>
    </ul>
</section>
