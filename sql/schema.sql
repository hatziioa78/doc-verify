CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    last_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    department VARCHAR(150) NOT NULL DEFAULT '',
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('manager', 'user') NOT NULL DEFAULT 'user',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_id INT UNSIGNED NOT NULL,
    subject VARCHAR(255) NOT NULL,
    protocol_number VARCHAR(120) NOT NULL,
    issuing_authority VARCHAR(255) NOT NULL,
    info TEXT NOT NULL,
    registered_at DATETIME NOT NULL,
    valid_until DATE NOT NULL,
    token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status ENUM('active', 'cancelled') NOT NULL DEFAULT 'active',
    cancellation_reason VARCHAR(500) NULL,
    cancelled_at DATETIME NULL,
    cancelled_by INT UNSIGNED NULL,
    original_name VARCHAR(255) NOT NULL,
    original_path VARCHAR(255) NOT NULL,
    certified_path VARCHAR(255) NOT NULL,
    sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    deleted_at DATETIME NULL,
    deleted_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_documents_token (token),
    KEY idx_documents_owner (owner_id),
    KEY idx_documents_protocol (protocol_number),
    KEY idx_documents_registered (registered_at),
    KEY idx_documents_valid (valid_until),
    KEY idx_documents_visible (deleted_at, status),
    CONSTRAINT fk_documents_owner FOREIGN KEY (owner_id) REFERENCES users (id),
    CONSTRAINT fk_documents_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users (id),
    CONSTRAINT fk_documents_deleted_by FOREIGN KEY (deleted_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    document_id INT UNSIGNED NULL,
    ip VARCHAR(45) NOT NULL,
    action VARCHAR(40) NOT NULL,
    details VARCHAR(1000) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_log_created (created_at),
    KEY idx_log_user (user_id, created_at),
    KEY idx_log_action (action, created_at),
    KEY idx_log_ip (ip, created_at),
    KEY idx_log_document (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    skey VARCHAR(64) NOT NULL,
    svalue TEXT NOT NULL,
    PRIMARY KEY (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS allowed_networks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cidr VARCHAR(64) NOT NULL,
    label VARCHAR(150) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_networks_cidr (cidr)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip VARCHAR(45) NOT NULL,
    email VARCHAR(190) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_attempts_ip (ip, created_at),
    KEY idx_attempts_email (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
