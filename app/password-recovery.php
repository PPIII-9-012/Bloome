<?php
declare(strict_types=1);

/** Crea la tabla si la aplicación ya estaba instalada antes de agregar la recuperación. */
function ensurePasswordRecoveryTable(): void
{
    static $ready = false;
    if ($ready) return;
    db()->exec("CREATE TABLE IF NOT EXISTS password_reset_codes (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        email_hash CHAR(64) NOT NULL,
        code_hash VARCHAR(255) NOT NULL,
        ip_hash CHAR(64) NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        INDEX reset_email_created (email_hash, created_at),
        INDEX reset_ip_created (ip_hash, created_at),
        INDEX reset_user_active (user_id, used_at, expires_at),
        CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready = true;
}

function smtpRead($socket): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    if ($response === '') throw new RuntimeException('El servidor SMTP no respondió.');
    return $response;
}

function smtpCommand($socket, string $command, array $expected): string
{
    if (fwrite($socket, $command . "\r\n") === false) throw new RuntimeException('No se pudo escribir en SMTP.');
    $response = smtpRead($socket);
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expected, true)) throw new RuntimeException('Respuesta SMTP inesperada: ' . trim($response));
    return $response;
}

/** Envío SMTP sin dependencias externas. Configuración por variables MAIL_*. */
function sendRecoveryCodeEmail(string $recipient, string $code): void
{
    $host = trim((string) getenv('MAIL_HOST'));
    $port = (int) (getenv('MAIL_PORT') ?: 587);
    $username = (string) getenv('MAIL_USERNAME');
    $password = (string) getenv('MAIL_PASSWORD');
    $from = trim((string) getenv('MAIL_FROM'));
    $fromName = trim((string) (getenv('MAIL_FROM_NAME') ?: 'Bloome'));
    $encryption = strtolower(trim((string) (getenv('MAIL_ENCRYPTION') ?: 'tls')));

    if ($host === '' || !filter_var($from, FILTER_VALIDATE_EMAIL) || $username === '' || $password === '') {
        throw new RuntimeException('Falta configurar MAIL_HOST, MAIL_FROM, MAIL_USERNAME o MAIL_PASSWORD.');
    }
    if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) throw new RuntimeException('MAIL_ENCRYPTION debe ser tls, ssl o none.');

    $target = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]]);
    $socket = @stream_socket_client($target, $errno, $error, 12, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) throw new RuntimeException('No se pudo conectar al servidor SMTP: ' . $error);
    stream_set_timeout($socket, 12);

    try {
        $greeting = smtpRead($socket);
        if ((int) substr($greeting, 0, 3) !== 220) throw new RuntimeException('Saludo SMTP inválido.');
        $hostname = gethostname() ?: 'bloome.local';
        smtpCommand($socket, 'EHLO ' . preg_replace('/[^A-Za-z0-9.-]/', '', $hostname), [250]);
        if ($encryption === 'tls') {
            smtpCommand($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('No se pudo activar TLS para SMTP.');
            smtpCommand($socket, 'EHLO ' . preg_replace('/[^A-Za-z0-9.-]/', '', $hostname), [250]);
        }
        smtpCommand($socket, 'AUTH LOGIN', [334]);
        smtpCommand($socket, base64_encode($username), [334]);
        smtpCommand($socket, base64_encode($password), [235]);
        smtpCommand($socket, 'MAIL FROM:<' . $from . '>', [250]);
        smtpCommand($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
        smtpCommand($socket, 'DATA', [354]);

        $subject = '=?UTF-8?B?' . base64_encode('Tu código para restablecer la contraseña de Bloome') . '?=';
        $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $body = "Hola,\r\n\r\nRecibimos una solicitud para restablecer la contraseña de tu cuenta de Bloome.\r\n\r\nTu código de verificación es: {$code}\r\n\r\nEl código vence en 15 minutos y solo puede utilizarse una vez. Si no solicitaste este cambio, podés ignorar este correo.\r\n\r\nEquipo de Bloome";
        $message = 'From: ' . $encodedName . ' <' . $from . ">\r\n" .
            'To: <' . $recipient . ">\r\n" .
            'Subject: ' . $subject . "\r\n" .
            "Date: " . date(DATE_RFC2822) . "\r\n" .
            "Message-ID: <" . bin2hex(random_bytes(12)) . '@' . preg_replace('/[^A-Za-z0-9.-]/', '', $hostname) . ">\r\n" .
            "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $body;
        $message = preg_replace('/(^|\r\n)\./', '$1..', $message) ?? $message;
        if (fwrite($socket, $message . "\r\n.\r\n") === false) throw new RuntimeException('No se pudo enviar el mensaje SMTP.');
        $response = smtpRead($socket);
        if ((int) substr($response, 0, 3) !== 250) throw new RuntimeException('El servidor SMTP rechazó el mensaje: ' . trim($response));
        smtpCommand($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}

function recoveryIpHash(): string
{
    return hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}
