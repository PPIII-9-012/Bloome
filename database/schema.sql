CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM(
        'admin',
        'reception',
        'professional'
    ) NOT NULL DEFAULT 'reception',
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identity_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX attempts_identity (identity_hash, attempted_at),
    INDEX attempts_ip (ip_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/*
*Employee: contiene informacion sobre un usuario como trabajador
*User: contiene informacion para iniciar sesion
*/
CREATE TABLE IF NOT EXISTS employees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    phone VARCHAR(30) NULL,
    appointment_color CHAR(7) NOT NULL DEFAULT '#0E3D3E',
    specialty VARCHAR(120) NULL,
    hire_date DATE NULL,
    commission_percentage DECIMAL(5,2) NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT employees_user
        FOREIGN KEY (user_id)
        REFERENCES users(id),

    CONSTRAINT valid_commission
        CHECK (
            commission_percentage IS NULL
            OR commission_percentage BETWEEN 0 AND 100
        )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(30) NULL,
    birth_date DATE NULL,
    gender ENUM('female','male','other') NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX clients_name (last_name, first_name),
    INDEX clients_email (email),
    INDEX clients_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    color VARCHAR(7) NOT NULL DEFAULT '#8B5CF6',
    sort_order INT NOT NULL DEFAULT 0,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_service_category_name (name)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(50) NULL,
    category_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    tax_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    active BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT valid_duration
        CHECK (duration_minutes > 0),

    CONSTRAINT valid_price
        CHECK (price >= 0),

    CONSTRAINT valid_service_tax
        CHECK (tax_percentage BETWEEN 0 AND 100),

    CONSTRAINT fk_services_category
        FOREIGN KEY (category_id)
        REFERENCES service_categories(id)
        ON DELETE RESTRICT,

    UNIQUE KEY unique_services_reference (reference),
    INDEX idx_services_category (category_id),
    INDEX idx_services_order (sort_order, name)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS appointments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    status ENUM(
        'pending',
        'confirmed',
        'completed',
        'cancelled',
        'missed'
    ) NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT appointments_client
        FOREIGN KEY (client_id)
        REFERENCES clients(id),
    CONSTRAINT appointments_employee
        FOREIGN KEY (employee_id)
        REFERENCES employees(id),
    CONSTRAINT valid_interval
        CHECK (ends_at > starts_at),
    INDEX appointments_schedule (
        employee_id,
        starts_at,
        ends_at
    ),
    INDEX appointments_date (starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Appointment_services: permite agregar mas de un servicio a un turno
CREATE TABLE IF NOT EXISTS appointment_services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    CONSTRAINT appointment_services_appointment
        FOREIGN KEY (appointment_id)
        REFERENCES appointments(id)
        ON DELETE CASCADE,
    CONSTRAINT appointment_services_service
        FOREIGN KEY (service_id)
        REFERENCES services(id),
    CONSTRAINT valid_service_price
        CHECK (price >= 0),
    CONSTRAINT valid_service_duration
        CHECK (duration_minutes > 0),
    UNIQUE (appointment_id, service_id),
    INDEX appointment_services_appointment (appointment_id),
    INDEX appointment_services_service (service_id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Agrego tabla para historial clinico
CREATE TABLE IF NOT EXISTS clinical_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    client_id BIGINT UNSIGNED NOT NULL,
    appointment_id BIGINT UNSIGNED NULL,
    employee_id BIGINT UNSIGNED NOT NULL,

    treatment_date DATE NOT NULL,
    description TEXT NOT NULL,
    observations TEXT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT clinical_records_client
        FOREIGN KEY (client_id)
        REFERENCES clients(id),

    CONSTRAINT clinical_records_appointment
        FOREIGN KEY (appointment_id)
        REFERENCES appointments(id),

    CONSTRAINT clinical_records_employee
        FOREIGN KEY (employee_id)
        REFERENCES employees(id),

    INDEX clinical_records_client (client_id),
    INDEX clinical_records_appointment (appointment_id),
    INDEX clinical_records_employee (employee_id),
    INDEX clinical_records_date (treatment_date)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seed_runs (
    name VARCHAR(100) PRIMARY KEY,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Analisis financiero, revisar.

CREATE TABLE IF NOT EXISTS expense_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS expenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    expense_date DATE NOT NULL,
    payment_method ENUM(
        'cash',
        'debit_card',
        'credit_card',
        'transfer',
        'other'
    ) NOT NULL DEFAULT 'cash',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT expenses_category
        FOREIGN KEY (category_id)
        REFERENCES expense_categories(id),
    CONSTRAINT valid_expense_amount
        CHECK (amount > 0),
    INDEX expenses_category (category_id),
    INDEX expenses_date (expense_date)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Modelo financiero (tarjeta #14). Importes DECIMAL(12,2), con cálculos hechos en centavos (app/money.php).

CREATE TABLE IF NOT EXISTS sales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NULL,
    appointment_id BIGINT UNSIGNED NULL,
    status ENUM('confirmed','voided') NOT NULL DEFAULT 'confirmed',
    subtotal DECIMAL(12,2) NOT NULL,
    discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL,
    notes VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    voided_at TIMESTAMP NULL,
    voided_by BIGINT UNSIGNED NULL,
    void_reason VARCHAR(255) NULL,
    active_appointment_key BIGINT UNSIGNED GENERATED ALWAYS AS (IF(status='confirmed', appointment_id, NULL)) STORED,
    CONSTRAINT sales_client FOREIGN KEY (client_id) REFERENCES clients(id),
    CONSTRAINT sales_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
    CONSTRAINT sales_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id),
    CONSTRAINT sales_creator FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT sales_voider FOREIGN KEY (voided_by) REFERENCES users(id),
    CONSTRAINT valid_sale_amounts CHECK (subtotal >= 0 AND discount_total >= 0 AND tax_total >= 0 AND total >= 0),
    CONSTRAINT valid_sale_void CHECK ((status = 'voided') = (voided_at IS NOT NULL AND voided_by IS NOT NULL AND void_reason IS NOT NULL)),
    UNIQUE KEY one_active_sale_per_appointment (active_appointment_key),
    INDEX sales_client_date (client_id, created_at),
    INDEX sales_date (created_at),
    INDEX sales_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sale_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NULL,
    description VARCHAR(190) NOT NULL,
    quantity SMALLINT UNSIGNED NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    tax_included BOOLEAN NOT NULL DEFAULT TRUE,
    net_amount DECIMAL(12,2) NOT NULL,
    tax_amount DECIMAL(12,2) NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    CONSTRAINT sale_items_sale FOREIGN KEY (sale_id) REFERENCES sales(id),
    CONSTRAINT sale_items_service FOREIGN KEY (service_id) REFERENCES services(id),
    CONSTRAINT valid_item_amounts CHECK (quantity >= 1 AND unit_price >= 0 AND discount >= 0 AND tax_rate BETWEEN 0 AND 100 AND net_amount >= 0 AND tax_amount >= 0 AND total_amount >= 0),
    INDEX sale_items_sale (sale_id),
    INDEX sale_items_service (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash','debit_card','credit_card','transfer','other') NOT NULL DEFAULT 'cash',
    status ENUM('pending','paid','refunded','cancelled') NOT NULL DEFAULT 'pending',
    kind ENUM('payment','deposit') NOT NULL DEFAULT 'payment',
    idempotency_key CHAR(64) NULL,
    created_by BIGINT UNSIGNED NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    refunded_at TIMESTAMP NULL,
    refunded_by BIGINT UNSIGNED NULL,
    CONSTRAINT payments_client FOREIGN KEY (client_id) REFERENCES clients(id),
    CONSTRAINT payments_creator FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT payments_refunder FOREIGN KEY (refunded_by) REFERENCES users(id),
    CONSTRAINT valid_payment_amount CHECK (amount > 0),
    UNIQUE KEY payments_idempotency (idempotency_key),
    INDEX payments_client (client_id),
    INDEX payments_date (paid_at),
    INDEX payments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un cobro se aplica a una venta o, si es una seña, a una cita. released_at conserva la historia al anular.
CREATE TABLE IF NOT EXISTS payment_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id BIGINT UNSIGNED NOT NULL,
    appointment_id BIGINT UNSIGNED NULL,
    sale_id BIGINT UNSIGNED NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    released_at TIMESTAMP NULL,
    CONSTRAINT payment_allocations_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    CONSTRAINT payment_allocations_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id),
    CONSTRAINT payment_allocations_sale FOREIGN KEY (sale_id) REFERENCES sales(id),
    CONSTRAINT valid_allocation_amount CHECK (amount > 0),
    CONSTRAINT valid_allocation_target CHECK ((appointment_id IS NULL) <> (sale_id IS NULL)),
    INDEX payment_allocations_payment (payment_id),
    INDEX payment_allocations_appointment (appointment_id),
    INDEX payment_allocations_sale (sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Una sola caja abierta por centro: open_key vale 1 mientras está abierta y NULL al cerrarse.
CREATE TABLE IF NOT EXISTS cash_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    status ENUM('open','closed') NOT NULL DEFAULT 'open',
    opening_cash DECIMAL(12,2) NOT NULL,
    opened_by BIGINT UNSIGNED NOT NULL,
    opened_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_by BIGINT UNSIGNED NULL,
    closed_at TIMESTAMP NULL,
    expected_cash DECIMAL(12,2) NULL,
    counted_cash DECIMAL(12,2) NULL,
    difference DECIMAL(12,2) NULL,
    closing_note VARCHAR(255) NULL,
    closing_summary JSON NULL,
    open_key TINYINT GENERATED ALWAYS AS (IF(status='open', 1, NULL)) STORED,
    CONSTRAINT cash_sessions_opener FOREIGN KEY (opened_by) REFERENCES users(id),
    CONSTRAINT cash_sessions_closer FOREIGN KEY (closed_by) REFERENCES users(id),
    CONSTRAINT valid_opening_cash CHECK (opening_cash >= 0),
    UNIQUE KEY one_open_cash_session (open_key),
    INDEX cash_sessions_date (opened_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Libro de caja de solo agregado: una corrección es un movimiento inverso (reverses_id), nunca un UPDATE.
CREATE TABLE IF NOT EXISTS cash_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cash_session_id BIGINT UNSIGNED NOT NULL,
    direction ENUM('in','out') NOT NULL,
    kind ENUM('payment','refund','income','expense','withdrawal') NOT NULL,
    method ENUM('cash','debit_card','credit_card','transfer','other') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_id BIGINT UNSIGNED NULL,
    reverses_id BIGINT UNSIGNED NULL,
    category_id BIGINT UNSIGNED NULL,
    idempotency_key CHAR(64) NULL,
    description VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT cash_movements_session FOREIGN KEY (cash_session_id) REFERENCES cash_sessions(id),
    CONSTRAINT cash_movements_payment FOREIGN KEY (payment_id) REFERENCES payments(id),
    CONSTRAINT cash_movements_reverses FOREIGN KEY (reverses_id) REFERENCES cash_movements(id),
    CONSTRAINT cash_movements_creator FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT cash_movements_category FOREIGN KEY (category_id) REFERENCES expense_categories(id),
    UNIQUE KEY cash_movements_idempotency (idempotency_key),
    CONSTRAINT valid_movement_amount CHECK (amount > 0),
    UNIQUE KEY one_reversal_per_movement (reverses_id),
    INDEX cash_movements_session (cash_session_id, created_at),
    INDEX cash_movements_payment (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO expense_categories (name) VALUES ('Insumos'), ('Servicios'), ('Alquiler'), ('Sueldos y comisiones'), ('Limpieza y mantenimiento'), ('Otros');

CREATE TABLE IF NOT EXISTS appointment_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id BIGINT UNSIGNED NOT NULL,
    actor_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(30) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX appointment_audit_history (appointment_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS business_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    center_name VARCHAR(120) NOT NULL DEFAULT 'Bloome',
    legal_name VARCHAR(160) NULL,
    email VARCHAR(190) NULL,
    country VARCHAR(100) NOT NULL DEFAULT 'Argentina',
    province VARCHAR(100) NULL,
    address VARCHAR(190) NULL,
    postal_code VARCHAR(20) NULL,
    phone VARCHAR(30) NULL,
    theme_color VARCHAR(7) NOT NULL DEFAULT '#6D5BD0',
    logo_path VARCHAR(255) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT business_settings_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(30) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX employee_audit_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS holidays (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL UNIQUE,
    description VARCHAR(190) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX holidays_date (holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_vacations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    description VARCHAR(190) NOT NULL,
    starts_at DATE NOT NULL,
    ends_at DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT vacations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT valid_vacation_dates CHECK (ends_at >= starts_at),
    INDEX vacations_interval (user_id, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    is_working BOOLEAN NOT NULL DEFAULT TRUE,
    break_start TIME NULL,
    break_end TIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT schedules_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY employee_day (user_id, day_of_week),
    CONSTRAINT valid_emp_day CHECK (day_of_week BETWEEN 1 AND 7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    day_of_week TINYINT UNSIGNED NOT NULL UNIQUE,
    start_time TIME NULL,
    end_time TIME NULL,
    is_open BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT valid_biz_day CHECK (day_of_week BETWEEN 1 AND 7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Catálogo de permisos por acción. No se agregan columnas a users:
-- cada usuario hereda los permisos de su rol.
CREATE TABLE IF NOT EXISTS permissions (
    code VARCHAR(80) PRIMARY KEY,
    description VARCHAR(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role ENUM('admin', 'reception', 'professional') NOT NULL,
    permission_code VARCHAR(80) NOT NULL,
    PRIMARY KEY (role, permission_code),
    CONSTRAINT fk_role_permission_permission
        FOREIGN KEY (permission_code) REFERENCES permissions(code)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id BIGINT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    entity VARCHAR(80) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    outcome ENUM('success', 'denied') NOT NULL,
    details JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user
        FOREIGN KEY (actor_user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_audit_user_date (actor_user_id, created_at),
    INDEX idx_audit_entity (entity, entity_id),
    INDEX idx_audit_outcome_date (outcome, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (code, description) VALUES
('clients.view', 'Ver clientes'),
('clients.create', 'Crear clientes'),
('clients.edit', 'Editar clientes'),
('audit.view', 'Consultar auditoría');

-- Admin tiene todos los permisos definidos.
INSERT IGNORE INTO role_permissions (role, permission_code)
SELECT 'admin', code FROM permissions;

-- Recepción puede consultar y mantener clientes.
INSERT IGNORE INTO role_permissions (role, permission_code)
SELECT 'reception', code FROM permissions
WHERE code IN ('clients.view', 'clients.create', 'clients.edit');

-- Los profesionales pueden consultar clientes, sin modificarlos.
INSERT IGNORE INTO role_permissions (role, permission_code)
VALUES ('professional', 'clients.view');


CREATE TABLE IF NOT EXISTS password_reset_codes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
