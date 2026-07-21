-- InnovaAuto Admin — схема БД (MySQL 8+)
-- Импорт: mysql -u root innovaauto < sql/schema.sql
-- Предварительно: CREATE DATABASE innovaauto CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS admin_password_resets;
DROP TABLE IF EXISTS admin_remember_tokens;
DROP TABLE IF EXISTS admin_login_attempts;
DROP TABLE IF EXISTS admin_users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE admin_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    username VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin','moderator','finance','support','manager') NOT NULL DEFAULT 'manager',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_email (email),
    UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_remember_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    selector CHAR(32) NOT NULL,
    validator_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_selector (selector),
    KEY idx_user (user_id),
    CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES admin_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_password_resets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    KEY idx_expires (expires_at),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES admin_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Тестовый супер-администратор (логин: admin@innovaauto.local или username: admin, пароль: Admin123!)
INSERT INTO admin_users (email, username, password_hash, role, is_active)
VALUES (
    'admin@innovaauto.local',
    'admin',
    '$2y$10$fwRRc6m0Alky/99bTeIAaOaTDbzUDOo8nUKgMYlQte.3tUdRHTcl.',
    'super_admin',
    1
);
