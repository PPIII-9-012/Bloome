<?php
declare(strict_types=1);

/**
 * Registra acciones relevantes y denegaciones de acceso.
 * No debe incluir contraseñas, tokens ni otros secretos en $details.
 */
function recordAudit(
    ?int $userId,
    string $action,
    string $entity,
    ?int $entityId,
    string $outcome,
    array $details = []
): void {
    query(
        'INSERT INTO audit_logs
            (actor_user_id, action, entity, entity_id, outcome, details)
         VALUES (?, ?, ?, ?, ?, ?)',
        [
            $userId,
            $action,
            $entity,
            $entityId,
            $outcome,
            json_encode($details, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
        ]
    );
}
