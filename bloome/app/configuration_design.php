<?php

declare(strict_types=1);

function designSettings(): array
{
    $stmt = db()->query(
        'SELECT theme_color, logo_path
         FROM business_settings
         WHERE id = 1
         LIMIT 1'
    );

    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    return $settings ?: [
        'theme_color' => '#6D5BD0',
        'logo_path' => null,
    ];
}

function validateDesignSettings(array $input): array
{
    $errors = [];
    $color = trim((string) ($input['theme_color'] ?? ''));

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $errors[] = 'Ingresá un color hexadecimal válido.';
    }

    return [
        ['theme_color' => strtoupper($color)],
        $errors,
    ];
}

function saveDesignSettings(array $settings): void
{
    $stmt = db()->prepare(
        'UPDATE business_settings
         SET theme_color = :theme_color
         WHERE id = 1'
    );

    $stmt->execute([
        'theme_color' => $settings['theme_color'],
    ]);
}

function saveDesignLogoPath(string $path): void
{
    $stmt = db()->prepare(
        'UPDATE business_settings
         SET logo_path = :logo_path
         WHERE id = 1'
    );

    $stmt->execute([
        'logo_path' => $path,
    ]);
}

function storeBrandingLogo(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            'No se pudo recibir la imagen. Intentá nuevamente.'
        );
    }

    if (
        ($file['size'] ?? 0) < 1 ||
        $file['size'] > 2 * 1024 * 1024
    ) {
        throw new RuntimeException(
            'El logo debe pesar como máximo 2 MiB.'
        );
    }

    $tmp = (string) ($file['tmp_name'] ?? '');

    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('El archivo recibido no es válido.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);

    $extensions = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime])) {
        throw new RuntimeException(
            'Formato no admitido. Usá PNG, JPG o WebP.'
        );
    }

    $directory = dirname(__DIR__) . '/public/uploads/branding';

    if (
        !is_dir($directory) &&
        !mkdir($directory, 0750, true) &&
        !is_dir($directory)
    ) {
        throw new RuntimeException(
            'No se pudo crear el directorio para guardar el logo.'
        );
    }

    $filename = bin2hex(random_bytes(16))
        . '.'
        . $extensions[$mime];

    $destination = $directory . '/' . $filename;

    if (!move_uploaded_file($tmp, $destination)) {
        throw new RuntimeException('No se pudo guardar el logo.');
    }

    return '/uploads/branding/' . $filename;
}