-- Таблицы платформы InnovaAuto (клиенты, объявления, платежи) — дашборд админки.
-- При включённом IA_DB_PLATFORM_SCHEMA таблицы создаются автоматически.
-- Ручной импорт: выберите БД innovaauto и выполните этот файл.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS platform_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL DEFAULT '',
    phone VARCHAR(32) NOT NULL DEFAULT '',
    email VARCHAR(255) NOT NULL,
    account_type VARCHAR(30) NOT NULL DEFAULT 'private',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_platform_email (email),
    KEY idx_platform_status (status),
    KEY idx_platform_account_type (account_type),
    KEY idx_platform_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_listings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    photo_url VARCHAR(255) NULL,
    brand VARCHAR(120) NOT NULL DEFAULT '',
    model VARCHAR(120) NOT NULL DEFAULT '',
    price DECIMAL(12, 2) NOT NULL DEFAULT 0,
    seller_name VARCHAR(150) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    is_vip TINYINT(1) NOT NULL DEFAULT 0,
    is_top TINYINT(1) NOT NULL DEFAULT 0,
    rejection_reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_list_user (user_id),
    KEY idx_list_status (status),
    KEY idx_list_created (created_at),
    KEY idx_list_vip (is_vip),
    CONSTRAINT fk_ad_list_platform_user FOREIGN KEY (user_id) REFERENCES platform_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    amount DECIMAL(12, 2) NOT NULL,
    kind ENUM('general', 'vip') NOT NULL DEFAULT 'general',
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pay_created (created_at),
    KEY idx_pay_kind (kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
