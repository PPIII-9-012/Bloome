<?php
declare(strict_types=1);
// Importar y exportar clientes en CSV (tarjeta #22). Autenticación y CSRF las comprueba el front controller.
require_once ROOT . '/app/client-import.php';
$deny = static function (int $status, string $message) use ($user, $page): never {
    http_response_code($status);
    render('message', compact('user', 'page', 'message') + ['title' => 'Clientes']);
    exit;
};
$csvDownload = static function (string $name, string $body): never {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    echo $body;
    exit;
};

if ($page === 'clients-export') {
    if (!canEdit($user) || !hasPermission($user, 'clients.view')) {
        recordAudit((int)$user['id'], 'clients.export', 'clients', null, 'denied');
        $deny(403, 'Solo administración y recepción pueden exportar clientes.');
    }
    if ($method !== 'GET') $deny(405, 'Método no permitido.');
    recordAudit((int)$user['id'], 'clients.export', 'clients', null, 'success');
    $csvDownload('clientes-' . date('Y-m-d') . '.csv', clientsCsv());
}

// Importar y descargar la plantilla requieren poder crear clientes.
if (!canEdit($user) || !hasPermission($user, 'clients.create')) {
    recordAudit((int)$user['id'], 'clients.import', 'clients', null, 'denied');
    $deny(403, 'Solo administración y recepción pueden importar clientes.');
}
if ($page === 'clients-template') {
    if ($method !== 'GET') $deny(405, 'Método no permitido.');
    $csvDownload('plantilla-clientes.csv', clientsCsvTemplate());
}

$result = null; $fatal = null;
if ($method === 'POST') {
    $file = $_FILES['file'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $fatal = 'Elegí un archivo CSV para importar.';
    } elseif ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $fatal = 'El archivo supera el máximo de 1 MB.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name'])) {
        $fatal = 'No se pudo recibir el archivo. Intentá de nuevo.';
    } elseif (!preg_match('/\.(csv|txt)$/i', (string)$file['name'])) {
        $fatal = 'Subí un archivo con extensión .csv.';
    } else {
        $parsed = parseClientCsv((string)$file['tmp_name']);
        if ($parsed['fatal'] !== null) $fatal = $parsed['fatal'];
        else $result = importClients((int)$user['id'], $parsed['rows']);
    }
    if ($fatal !== null) http_response_code(422);
}
render('client-import', compact('user', 'page', 'result', 'fatal') + ['title' => 'Importar clientes']);
exit;
