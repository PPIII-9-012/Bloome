<?php
declare(strict_types=1);

function hasPermission(array $user, string $permission): bool
{
    $sql = "
        SELECT 1
        FROM users u
        INNER JOIN role_permissions rp
            ON rp.role = u.role
        WHERE u.id = ?
          AND u.active = 1
          AND rp.permission_code = ?
        LIMIT 1
    ";

    $result = query($sql, [
        $user['id'],
        $permission
    ]);

    return $result->fetchColumn() !== false;
}