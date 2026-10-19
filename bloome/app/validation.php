<?php
declare(strict_types=1);

function validDate(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function validateClient(array $source): array
{
    $fields = ['first_name', 'last_name', 'email', 'phone', 'birth_date', 'notes'];
    $data = [];
    foreach ($fields as $field) $data[$field] = input($source, $field);
    $errors = [];
    foreach (['first_name' => 'nombre', 'last_name' => 'apellido'] as $field => $label) {
        if ($data[$field] === '' || textLength($data[$field]) > 100) $errors[$field] = "Ingresá un $label de hasta 100 caracteres.";
    }
    if ($data['email'] !== '' && (strlen($data['email']) > 190 || !filter_var($data['email'], FILTER_VALIDATE_EMAIL))) $errors['email'] = 'Ingresá un correo válido.';
    if ($data['phone'] !== '' && !preg_match('/^[0-9+() .\-]{6,30}$/D', $data['phone'])) $errors['phone'] = 'Revisá el teléfono (entre 6 y 30 caracteres).';
    if ($data['birth_date'] !== '' && (!validDate($data['birth_date']) || $data['birth_date'] > date('Y-m-d') || $data['birth_date'] < '1900-01-01')) $errors['birth_date'] = 'Ingresá una fecha válida entre 1900 y hoy.';
    if (textLength($data['notes']) > 2000) $errors['notes'] = 'Las observaciones son demasiado extensas (máximo 2000 caracteres).';
    return [$data, $errors];
}
