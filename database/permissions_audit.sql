-- TABLA 1: PERMISOS DISPONIBLES

CREATE TABLE IF NOT EXISTS permissions (
    code VARCHAR(80) PRIMARY KEY,
    description VARCHAR(150) NOT NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- TABLA 2: PERMISOS ASIGNADOS A CADA ROL

CREATE TABLE IF NOT EXISTS role_permissions (
    role ENUM(
        'admin',
        'reception',
        'professional'
    ) NOT NULL,

    permission_code VARCHAR(80) NOT NULL,

    PRIMARY KEY (role, permission_code),

    CONSTRAINT fk_role_permission
        FOREIGN KEY (permission_code)
        REFERENCES permissions(code)
        ON DELETE RESTRICT

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- TABLA 3: HISTORIAL DE AUDITORÍA

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    actor_user_id BIGINT UNSIGNED NULL,

    action VARCHAR(80) NOT NULL,

    entity VARCHAR(80) NOT NULL,

    entity_id BIGINT UNSIGNED NULL,

    outcome ENUM('success', 'denied') NOT NULL,

    details JSON NULL,

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_audit_user
        FOREIGN KEY (actor_user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    INDEX idx_audit_user_date (
        actor_user_id,
        created_at
    ),

    INDEX idx_audit_entity (
        entity,
        entity_id
    )
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (code, description)
VALUES
('clients.view', 'Ver clientes'),
('clients.create', 'Crear clientes'),
('clients.edit', 'Editar clientes'),
('audit.view', 'Consultar auditoría');

-- El administrador tiene todos los permisos

INSERT IGNORE INTO role_permissions (role, permission_code)
SELECT 'admin', code
FROM permissions;


-- Recepción puede ver, crear y editar clientes

INSERT IGNORE INTO role_permissions (role, permission_code)
SELECT 'reception', code
FROM permissions
WHERE code IN (
    'clients.view',
    'clients.create',
    'clients.edit'
);
-- Permiso de lectura para profesionales
-- Permitido durante la etapa piloto de BLOOME

INSERT IGNORE INTO role_permissions
(role, permission_code)
VALUES ('professional', 'clients.view');