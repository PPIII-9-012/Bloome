<?php
declare(strict_types=1);

function listServices(string $search = ''): array
{
    $search = trim($search);

    $sql = 'SELECT s.*, c.name AS category_name, c.color AS category_color
            FROM services s
            LEFT JOIN service_categories c ON c.id = s.category_id';

    if ($search !== '') {
        $sql .= ' WHERE s.name LIKE ? OR s.reference LIKE ?';
        $params = ['%' . $search . '%', '%' . $search . '%'];
    } else {
        $params = [];
    }

    $sql .= ' ORDER BY s.sort_order, s.name';

    return query($sql, $params)->fetchAll();
}

function findService(int $id): ?array
{
    $service = query(
        'SELECT * FROM services WHERE id = ?',
        [$id]
    )->fetch();

    return $service ?: null;
}

function validateService(array $source, ?int $id = null): array
{
    $data = [
        'reference' => trim(input($source, 'reference')),
        'category_id' => trim(input($source, 'category_id')),
        'name' => trim(input($source, 'name')),
        'duration_minutes' => trim(input($source, 'duration_minutes')),
        'price' => trim(input($source, 'price')),
        'tax_percentage' => trim(input($source, 'tax_percentage', '0')),
        'sort_order' => trim(input($source, 'sort_order', '0')),
    ];

    $errors = [];

    if ($data['reference'] === '') {
        $data['reference'] = null;
    } elseif (textLength($data['reference']) > 50) {
        $errors['reference'] = 'La referencia admite hasta 50 caracteres.';
    }

    if ($data['name'] === '') {
        $errors['name'] = 'El nombre es obligatorio.';
    } elseif (textLength($data['name']) > 120) {
        $errors['name'] = 'El nombre admite hasta 120 caracteres.';
    }

    if (
        $data['category_id'] !== ''
        && (
            !ctype_digit($data['category_id'])
            || (int) $data['category_id'] < 1
        )
    ) {
        $errors['category_id'] = 'Seleccioná una categoría válida.';
    } elseif ($data['category_id'] !== '') {
        $category = query(
            'SELECT id FROM service_categories WHERE id = ?',
            [(int) $data['category_id']]
        )->fetchColumn();

        if (!$category) {
            $errors['category_id'] = 'La categoría seleccionada no existe.';
        }
    }

    if (
        $data['duration_minutes'] === ''
        || !ctype_digit($data['duration_minutes'])
        || (int) $data['duration_minutes'] < 1
        || (int) $data['duration_minutes'] > 65535
    ) {
        $errors['duration_minutes'] =
            'La duración debe ser un entero entre 1 y 65535 minutos.';
    }

    if (
        !preg_match('/^\d{1,10}(?:[.,]\d{1,2})?$/D', $data['price'])
        || (float) str_replace(',', '.', $data['price']) > 9999999999.99
    ) {
        $errors['price'] = 'Ingresá un precio válido, igual o mayor que cero.';
    }

    if (
        !preg_match('/^(?:\d{1,3})(?:[.,]\d{1,2})?$/D', $data['tax_percentage'])
        || (float) str_replace(',', '.', $data['tax_percentage']) > 100
    ) {
        $errors['tax_percentage'] = 'El impuesto debe estar entre 0 y 100%.';
    }

    if (
        $data['sort_order'] === ''
        || !ctype_digit($data['sort_order'])
        || (int) $data['sort_order'] > 2147483647
    ) {
        $errors['sort_order'] =
            'El orden debe ser un entero igual o mayor que cero.';
    }

    if ($data['reference'] !== null && !isset($errors['reference'])) {
        $duplicate = query(
            'SELECT id FROM services
             WHERE reference = ? AND (? IS NULL OR id <> ?)
             LIMIT 1',
            [$data['reference'], $id, $id]
        )->fetchColumn();

        if ($duplicate) {
            $errors['reference'] = 'Ya existe un servicio con esa referencia.';
        }
    }

    $data['category_id'] = $data['category_id'] === ''
        ? null
        : (int) $data['category_id'];

    $data['duration_minutes'] = ctype_digit($data['duration_minutes'])
        ? (int) $data['duration_minutes']
        : 0;

    $data['price'] = str_replace(',', '.', $data['price']);
    $data['tax_percentage'] = str_replace(',', '.', $data['tax_percentage']);
    $data['sort_order'] = ctype_digit($data['sort_order'])
        ? (int) $data['sort_order']
        : 0;

    return [$data, $errors];
}

function saveService(array $data, ?int $id = null): void
{
    $values = [
        $data['reference'] ?: null,
        $data['category_id'] ?: null,
        $data['name'],
        $data['duration_minutes'],
        $data['price'],
        $data['tax_percentage'],
        $data['sort_order'],
    ];

    if ($id === null) {
        query(
            'INSERT INTO services
                (reference, category_id, name, duration_minutes,
                 price, tax_percentage, sort_order, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)',
            $values
        );

        return;
    }

    query(
        'UPDATE services
         SET reference = ?, category_id = ?, name = ?,
             duration_minutes = ?, price = ?, tax_percentage = ?,
             sort_order = ?
         WHERE id = ?',
        [...$values, $id]
    );
}

function setServiceActive(int $id, bool $active): void
{
    query(
        'UPDATE services SET active = ? WHERE id = ?',
        [$active ? 1 : 0, $id]
    );
}
