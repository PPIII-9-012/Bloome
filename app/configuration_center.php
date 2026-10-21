<?php
declare(strict_types=1);

/**
 * Configuración general del centro.
 * Requiere las funciones db(), query(), input() y textLength() del proyecto.
 */

function centerSettings(): array
{
    $row = query('SELECT id, center_name, legal_name, email, country, province, address, postal_code, phone FROM business_settings WHERE id = 1')->fetch();

    if ($row) {
        return $row;
    }

    // Permite abrir la pantalla incluso si aún no se ejecutó la migración.
    return [
        'id' => 1,
        'center_name' => 'Bloome',
        'legal_name' => '',
        'email' => '',
        'country' => 'Argentina',
        'province' => '',
        'address' => '',
        'postal_code' => '',
        'phone' => '',
    ];
}

function validateCenterSettings(array $source): array
{
    $fields = [
        'center_name', 'legal_name', 'email', 'country',
        'province', 'address', 'postal_code', 'phone',
    ];
    $data = [];
    foreach ($fields as $field) {
        $data[$field] = input($source, $field);
    }

    $errors = [];
    $limits = [
        'center_name' => 120,
        'legal_name' => 160,
        'country' => 100,
        'province' => 100,
        'address' => 190,
        'postal_code' => 20,
    ];

    foreach ($limits as $field => $max) {
        if (textLength($data[$field]) > $max) {
            $errors[$field] = "Este campo admite hasta {$max} caracteres.";
        }
    }

    if ($data['center_name'] === '') {
        $errors['center_name'] = 'El nombre del centro es obligatorio.';
    }

    if ($data['country'] === '') {
        $errors['country'] = 'El país es obligatorio.';
    }

    if ($data['email'] !== '' &&
        (strlen($data['email']) > 190 || !filter_var($data['email'], FILTER_VALIDATE_EMAIL))) {
        $errors['email'] = 'Ingresá un correo electrónico válido.';
    }

    if ($data['phone'] !== '' && !preg_match('/^[0-9+() .\-]{6,30}$/D', $data['phone'])) {
        $errors['phone'] = 'Ingresá un teléfono válido (entre 6 y 30 caracteres).';
    }

    return [$data, $errors];
}

function saveCenterSettings(array $data): void
{
    query(
        'INSERT INTO business_settings
            (id, center_name, legal_name, email, country, province, address, postal_code, phone)
         VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            center_name = VALUES(center_name),
            legal_name = VALUES(legal_name),
            email = VALUES(email),
            country = VALUES(country),
            province = VALUES(province),
            address = VALUES(address),
            postal_code = VALUES(postal_code),
            phone = VALUES(phone)',
        [
            $data['center_name'],
            $data['legal_name'] !== '' ? $data['legal_name'] : null,
            $data['email'] !== '' ? $data['email'] : null,
            $data['country'],
            $data['province'] !== '' ? $data['province'] : null,
            $data['address'] !== '' ? $data['address'] : null,
            $data['postal_code'] !== '' ? $data['postal_code'] : null,
            $data['phone'] !== '' ? $data['phone'] : null,
        ]
    );
}
