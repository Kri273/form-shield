-- =========================================================
-- FORM-SHIELD DATABASE
-- =========================================================

CREATE DATABASE IF NOT EXISTS form_shield
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE form_shield;


-- ---------------------------------------------------------
-- ADMINS
-- ---------------------------------------------------------


CREATE TABLE admins (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY unique_username (username)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- RATE LIMITS
-- ---------------------------------------------------------

CREATE TABLE rate_limits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_hash CHAR(64) NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 1,
    window_start DATETIME NOT NULL,
    last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY unique_ip_window (ip_hash, window_start),
    INDEX idx_ip_hash (ip_hash)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- SPAM LOGS
-- ---------------------------------------------------------

-- ---------------------------------------------------------
-- SPAM LOGS
-- ---------------------------------------------------------

CREATE TABLE spam_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Privacy-friendly IP storage
    ip_hash CHAR(64) NOT NULL,
    ip_encrypted TEXT DEFAULT NULL,

    -- ISO 3166-1 alpha-2 country code, e.g. EE, US, DE
    country CHAR(2) DEFAULT NULL,

    email VARCHAR(254) DEFAULT NULL,

    reason VARCHAR(100) NOT NULL,
    score INT NOT NULL DEFAULT 0,

    silent TINYINT(1) NOT NULL DEFAULT 0,

    message TEXT DEFAULT NULL,

    PRIMARY KEY (id),

    INDEX idx_created_at (created_at),
    INDEX idx_reason (reason),
    INDEX idx_country (country),
    INDEX idx_ip_hash (ip_hash)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- KNOWN TEMPLATES
-- ---------------------------------------------------------

CREATE TABLE known_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,

    name VARCHAR(150) NOT NULL,
    pattern TEXT NOT NULL,

    enabled TINYINT(1) NOT NULL DEFAULT 1,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    INDEX idx_enabled (enabled)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- LEGITIMATE SUBMISSIONS
-- ---------------------------------------------------------

-- ---------------------------------------------------------
-- SUBMISSIONS
-- ---------------------------------------------------------

CREATE TABLE submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,

    ip_encrypted TEXT DEFAULT NULL,

    -- ISO 3166-1 alpha-2 country code, e.g. EE, US, DE
    country CHAR(2) DEFAULT NULL,

    message TEXT NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    INDEX idx_created_at (created_at),
    INDEX idx_email (email),
    INDEX idx_country (country)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;