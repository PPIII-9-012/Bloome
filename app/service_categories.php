<?php
declare(strict_types=1);

/**
 * Categorías de servicios y tratamientos.
 * No utiliza expense_categories.
 */

function listServiceCategories(string $search = ''): array
{
    $search = trim($search);

    if ($search !== '') {
        return query(
            'SELECT c.*,
                    (
                        SELECT COUNT(*)
                        FROM services s
                        WHERE s.category_id = c.id
                    ) AS service_count
             FROM service_categories c
             WHERE c.name LIKE ?
             ORDER BY c.sort_order, c.name',
            ['%' . $search . '%']
        )->fetchAll();
    }

    return query(
        'SELECT c.*,
                (
                    SELECT COUNT(*)
                    FROM services s
                    WHERE s.category_id = c.id
                ) AS service_count
         FROM service_categories c
         ORDER BY c.sort_order, c.name'
    )->fetchAll();
}

function findServiceCategory(int $id): ?array
{
    $category = query(
        'SELECT * FROM service_categories WHERE id = ?',
        [$id]
    )->fetch();

    return $category ?: null;
}

function validateServiceCategory(array $source, ?int $id = null): array
{
    $data = [
        'name' => input($source, 'name'),
        'color' => strtoupper(input($source, 'color', '#8B5CF6')),
        'sort_order' => input($source, 'sort_order', '0'),
    ];

    $errors = [];

    if ($data['name'] === '') {
        $errors['name'] = 'El nombre de la categoría es obligatorio.';
    } elseif (textLength($data['name']) > 120) {
        $errors['name'] = 'El nombre admite hasta 120 caracteres.';
    }

    if (!preg_match('/^#[0-9A-F]{6}$/D', $data['color'])) {
        $errors['color'] = 'Seleccioná un color hexadecimal válido.';
    }

    if (
        $data['sort_order'] === ''
        || !ctype_digit($data['sort_order'])
        || (int) $data['sort_order'] > 2147483647
    ) {
        $errors['sort_order'] = 'El orden debe ser un número entero igual o mayor que cero.';
    }

    if (!isset($errors['name'])) {
        $duplicate = query(
            'SELECT id
             FROM service_categories
             WHERE name = ?
               AND (? IS NULL OR id <> ?)
             LIMIT 1',
            [$data['name'], $id, $id]
        )->fetchColumn();

        if ($duplicate) {
            $errors['name'] = 'Ya existe una categoría con ese nombre.';
        }
    }

    $data['sort_order'] = (int) (
        ctype_digit($data['sort_order'])
            ? $data['sort_order']
            : '0'
    );

    return [$data, $errors];
}

function saveServiceCategory(array $data, ?int $id = null): void
{
    if ($id === null) {
        query(
            'INSERT INTO service_categories
                (name, color, sort_order, active)
             VALUES (?, ?, ?, 1)',
            [
                $data['name'],
                $data['color'],
                $data['sort_order'],
            ]
        );

        return;
    }

    query(
        'UPDATE service_categories
         SET name = ?, color = ?, sort_order = ?
         WHERE id = ?',
        [
            $data['name'],
            $data['color'],
            $data['sort_order'],
            $id,
        ]
    );
}

function setServiceCategoryActive(int $id, bool $active): void
{
    query(
        'UPDATE service_categories SET active = ? WHERE id = ?',
        [$active ? 1 : 0, $id]
    );
}
