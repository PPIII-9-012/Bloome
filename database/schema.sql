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
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX clients_name (last_name, first_name),
    INDEX clients_email (email),
    INDEX clients_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT valid_duration CHECK (duration_minutes > 0),
    CONSTRAINT valid_price CHECK (price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Saque service_id 
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

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM(
        'cash',
        'debit_card',
        'credit_card',
        'transfer',
        'other'
    ) NOT NULL DEFAULT 'cash',
    status ENUM(
        'pending',
        'paid',
        'refunded',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT payments_client
        FOREIGN KEY (client_id)
        REFERENCES clients(id),

    CONSTRAINT valid_payment_amount
        CHECK (amount > 0),

    INDEX payments_client (client_id),
    INDEX payments_date (paid_at),
    INDEX payments_status (status)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

  CREATE TABLE IF NOT EXISTS payment_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    payment_id BIGINT UNSIGNED NOT NULL,
    appointment_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,

    CONSTRAINT payment_allocations_payment
        FOREIGN KEY (payment_id)
        REFERENCES payments(id)
        ON DELETE CASCADE,

    CONSTRAINT payment_allocations_appointment
        FOREIGN KEY (appointment_id)
        REFERENCES appointments(id),

    CONSTRAINT valid_allocation_amount
        CHECK (amount > 0),

    UNIQUE (payment_id, appointment_id),

    INDEX payment_allocations_payment (payment_id),
    INDEX payment_allocations_appointment (appointment_id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;