<?php
declare(strict_types=1);

function recordAudit(
    ?int $userId,
    string $action,
    string $entity,
    ?int $entityId,
    string $outcome,
    array $details = []
): void {

    $sql = "
        INSERT INTO audit_logs (
            actor_user_id,
            action,
            entity,
            entity_id,
            outcome,
            details
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ";

    query($sql, [
        $userId,
        $action,
        $entity,
        $entityId,
        $outcome,
        json_encode($details, JSON_THROW_ON_ERROR)
    ]);
}