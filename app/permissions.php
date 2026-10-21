<?php
declare(strict_types=1);

/**
 * Comprueba permisos asignados al rol del usuario activo.
 * Los permisos se almacenan en role_permissions, no en la tabla users.
 */
function hasPermission(?array $user, string $permission): bool
{
    if (empty($user['id'])) {
        return false;
    }

    $allowed = query(
        'SELECT 1
         FROM users u
         INNER JOIN role_permissions rp ON rp.role = u.role
         INNER JOIN permissions p ON p.code = rp.permission_code
         WHERE u.id = ? AND u.active = 1 AND p.code = ?
         LIMIT 1',
        [(int) $user['id'], $permission]
    )->fetchColumn();

    return $allowed !== false;
}
