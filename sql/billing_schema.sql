-- Биллинг: тарифы и платежи (ТЗ п. 11–12). Создаётся автоматически через ia_ensure_billing_schema.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS billing_tariffs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(160) NOT NULL,
    price DECIMAL(12, 2) NOT NULL DEFAULT 0,
    duration_days INT UNSIGNED NULL,
    benefits TEXT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_billing_tariff_code (code),
    KEY idx_billing_tariff_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    platform_user_id INT UNSIGNED NOT NULL,
    tariff_id INT UNSIGNED NULL,
    amount DECIMAL(12, 2) NOT NULL,
    status ENUM('paid', 'pending', 'failed') NOT NULL DEFAULT 'pending',
    method VARCHAR(40) NOT NULL DEFAULT 'unknown',
    note VARCHAR(255) NULL,
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_btx_user (platform_user_id),
    KEY idx_btx_tariff (tariff_id),
    KEY idx_btx_status (status),
    KEY idx_btx_created (created_at),
    CONSTRAINT fk_btx_user FOREIGN KEY (platform_user_id) REFERENCES platform_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_btx_tariff FOREIGN KEY (tariff_id) REFERENCES billing_tariffs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
