<?php
declare(strict_types=1);
// Importación y exportación de clientes en CSV (tarjeta #22).

const CLIENT_CSV_HEADERS = ['nombre', 'apellido', 'email', 'telefono', 'fecha_nacimiento', 'genero', 'observaciones'];
const CLIENT_CSV_MAX_BYTES = 1048576;
const CLIENT_CSV_MAX_ROWS = 1000;

/** Etiqueta de género para el archivo (vacío si no hay dato). */
function clientCsvGender(?string $gender): string
{
    return $gender !== null && isset(CLIENT_GENDERS[$gender]) ? CLIENT_GENDERS[$gender] : '';
}

/** Plantilla fija: solo la fila de encabezados. UTF-8 con BOM y ';' como separador, igual que el resto de los informes. */
function clientsCsvTemplate(): string
{
    return reportCsv([CLIENT_CSV_HEADERS]);
}

/** Todos los clientes con las mismas columnas que la plantilla, de modo que un archivo exportado se puede volver a importar. Las celdas a salvo de fórmulas. */
function clientsCsv(): string
{
    $rows = [CLIENT_CSV_HEADERS];
    foreach (query('SELECT first_name, last_name, email, phone, birth_date, gender, notes FROM clients ORDER BY last_name, first_name, id')->fetchAll() as $c) {
        $rows[] = [$c['first_name'], $c['last_name'], (string)$c['email'], (string)$c['phone'], (string)$c['birth_date'], clientCsvGender($c['gender']), (string)$c['notes']];
    }
    return reportCsv($rows);
}

/** Quita la comilla que antepone la exportación a las celdas que empiezan con = + - @ (así un archivo exportado vuelve a importarse igual). */
function clientCsvUnescape(string $cell): string
{
    return preg_match('/^\'[=+\-@]/', $cell) === 1 ? substr($cell, 1) : $cell;
}

/** Fecha como AAAA-MM-DD o DD/MM/AAAA a AAAA-MM-DD. Si no se reconoce, se devuelve tal cual para que la validación la rechace. */
function clientCsvDate(string $value): string
{
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    return $value;
}

function clientCsvGenderKey(string $value): string
{
    $map = ['femenino' => 'female', 'masculino' => 'male', 'otro' => 'other', 'female' => 'female', 'male' => 'male', 'other' => 'other'];
    return $value === '' ? '' : ($map[strtolower($value)] ?? $value);
}

/** Últimos 8 dígitos del teléfono, para reconocer el mismo número escrito con o sin prefijo de país o "0" inicial. Vacío si tiene menos de 6 dígitos. */
function clientPhoneKey(string $phone): string
{
    $digits = preg_replace('/\D/', '', $phone) ?? '';
    return strlen($digits) >= 6 ? substr($digits, -8) : '';
}

/**
 * Lee y valida el archivo sin tocar la base. Devuelve ['fatal' => string|null, 'rows' => [[line, data, errors]]].
 * Un error fatal (archivo ilegible, encabezados distintos, demasiadas filas) impide importar cualquier fila.
 */
function parseClientCsv(string $path): array
{
    $size = @filesize($path);
    if ($size === false || $size === 0) return ['fatal' => 'El archivo está vacío.', 'rows' => []];
    if ($size > CLIENT_CSV_MAX_BYTES) return ['fatal' => 'El archivo supera el máximo de 1 MB.', 'rows' => []];
    $content = (string)file_get_contents($path);
    if (str_starts_with($content, "\xEF\xBB\xBF")) $content = substr($content, 3);
    if (preg_match('//u', $content) !== 1) return ['fatal' => 'El archivo debe estar guardado en UTF-8. Guardalo como "CSV UTF-8" desde la planilla.', 'rows' => []];
    if (str_contains($content, "\0")) return ['fatal' => 'El archivo no es un CSV de texto.', 'rows' => []];
    $first = strtok($content, "\r\n") ?: '';
    $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $content);
    rewind($stream);
    $header = fgetcsv($stream, 0, $delimiter, '"', '');
    $normalized = $header ? array_map(fn($h) => strtolower(trim((string)$h)), $header) : [];
    if ($normalized !== CLIENT_CSV_HEADERS) {
        fclose($stream);
        return ['fatal' => 'Los encabezados no coinciden con la plantilla. Deben ser, en este orden: ' . implode(', ', CLIENT_CSV_HEADERS) . '.', 'rows' => []];
    }
    $rows = []; $line = 1;
    while (($record = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
        $line++;
        if ($record === [null] || trim(implode('', array_map('strval', $record))) === '') continue;
        if (count($rows) >= CLIENT_CSV_MAX_ROWS) { fclose($stream); return ['fatal' => 'El archivo tiene más de ' . CLIENT_CSV_MAX_ROWS . ' filas. Dividilo en archivos más chicos.', 'rows' => []]; }
        if (count($record) !== count(CLIENT_CSV_HEADERS)) {
            $rows[] = ['line' => $line, 'data' => array_fill_keys(['first_name', 'last_name', 'email', 'phone', 'birth_date', 'gender', 'notes'], ''), 'errors' => ['row' => 'La fila tiene ' . count($record) . ' columnas y se esperan ' . count(CLIENT_CSV_HEADERS) . '.']];
            continue;
        }
        $cells = array_map(fn($c) => clientCsvUnescape(trim((string)$c)), $record);
        [$data, $errors] = validateClient([
            'first_name' => $cells[0], 'last_name' => $cells[1], 'email' => $cells[2], 'phone' => $cells[3],
            'birth_date' => clientCsvDate($cells[4]), 'gender' => clientCsvGenderKey($cells[5]), 'notes' => $cells[6],
        ]);
        $rows[] = ['line' => $line, 'data' => $data, 'errors' => $errors];
    }
    fclose($stream);
    if (!$rows) return ['fatal' => 'El archivo no tiene filas de clientes debajo de los encabezados.', 'rows' => []];
    return ['fatal' => null, 'rows' => $rows];
}

/** Claves de comparación (correo, teléfono, nombre + nacimiento) de los clientes que ya existen. */
function clientDuplicateIndex(): array
{
    $index = ['email' => [], 'phone' => [], 'name' => [], 'bare' => []];
    foreach (query('SELECT id, first_name, last_name, email, phone, birth_date FROM clients')->fetchAll() as $c) {
        if ($c['email'] !== null && $c['email'] !== '') $index['email'][strtolower($c['email'])] ??= (int)$c['id'];
        if ($key = clientPhoneKey((string)$c['phone'])) $index['phone'][$key] ??= (int)$c['id'];
        if ($c['birth_date'] !== null) $index['name'][clientNameKey($c['first_name'], $c['last_name'], $c['birth_date'])] ??= (int)$c['id'];
        if (($c['email'] ?? '') === '' && ($c['phone'] ?? '') === '' && $c['birth_date'] === null) $index['bare'][clientNameKey($c['first_name'], $c['last_name'], '')] ??= (int)$c['id'];
    }
    return $index;
}

function clientNameKey(string $first, string $last, string $birth): string
{
    return strtolower(trim($first)) . '|' . strtolower(trim($last)) . '|' . $birth;
}

/**
 * Importa las filas válidas que no repitan a un cliente existente ni a otra fila del archivo. Sin fusión automática:
 * un duplicado se informa y no se toca. Devuelve ['created', 'invalid' => [[line, name, messages]], 'duplicates' => [[line, name, message]]].
 */
function importClients(int $actorId, array $rows): array
{
    $index = clientDuplicateIndex();
    $created = 0; $invalid = []; $duplicates = [];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            $d = $row['data']; $name = trim($d['first_name'] . ' ' . $d['last_name']);
            if ($row['errors']) { $invalid[] = ['line' => $row['line'], 'name' => $name, 'messages' => array_values($row['errors'])]; continue; }
            $emailKey = strtolower($d['email']); $phoneKey = clientPhoneKey($d['phone']); $nameKey = $d['birth_date'] !== '' ? clientNameKey($d['first_name'], $d['last_name'], $d['birth_date']) : '';
            $bareKey = ($emailKey === '' && $phoneKey === '' && $nameKey === '') ? clientNameKey($d['first_name'], $d['last_name'], '') : '';
            $why = null;
            if ($emailKey !== '' && isset($index['email'][$emailKey])) $why = 'Ya hay un cliente con ese correo (en el sistema o en una fila anterior).';
            elseif ($phoneKey !== '' && isset($index['phone'][$phoneKey])) $why = 'Ya hay un cliente con ese teléfono (en el sistema o en una fila anterior).';
            elseif ($nameKey !== '' && isset($index['name'][$nameKey])) $why = 'Ya hay un cliente con ese nombre y fecha de nacimiento (en el sistema o en una fila anterior).';
            elseif ($bareKey !== '' && isset($index['bare'][$bareKey])) $why = 'Ya hay un cliente con ese nombre y sin otros datos para distinguirlo (en el sistema o en una fila anterior).';
            if ($why !== null) { $duplicates[] = ['line' => $row['line'], 'name' => $name, 'message' => $why]; continue; }
            query('INSERT INTO clients (first_name, last_name, email, phone, birth_date, gender, notes) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$d['first_name'], $d['last_name'], $d['email'] ?: null, $d['phone'] ?: null, $d['birth_date'] ?: null, $d['gender'] ?: null, $d['notes'] ?: null]);
            $id = (int)$pdo->lastInsertId();
            if ($emailKey !== '') $index['email'][$emailKey] = $id;
            if ($phoneKey !== '') $index['phone'][$phoneKey] = $id;
            if ($nameKey !== '') $index['name'][$nameKey] = $id;
            if ($bareKey !== '') $index['bare'][$bareKey] = $id;
            $created++;
        }
        recordAudit($actorId, 'clients.import', 'clients', null, 'success', ['created' => $created, 'invalid' => count($invalid), 'duplicates' => count($duplicates)]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return ['created' => $created, 'invalid' => $invalid, 'duplicates' => $duplicates];
}
