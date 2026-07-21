-- =============================================================================
-- InnovaAuto — MySQL (Hostinger / phpMyAdmin)
-- База: u417315406_innovaauto
--
-- Чӣ тавр импорт кунед:
-- 1) hPanel → Databases → phpMyAdmin
-- 2) аз чап базаи u417315406_innovaauto-ро интихоб кунед
-- 3) вкладка Import → Choose File → ин файл → Go
--
-- Бехатар: CREATE TABLE IF NOT EXISTS (агар ҷадвал бошад, дубора сохта намешавад)
-- Маълумот (эълонҳо, корбарон): баъд setup-mysql.php ё импорти dump
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Админ-панел
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS admin_users (
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

CREATE TABLE IF NOT EXISTS admin_login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_remember_tokens (
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

CREATE TABLE IF NOT EXISTS admin_password_resets (
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

-- ---------------------------------------------------------------------------
-- Танзимоти сайт
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(64) NOT NULL,
    setting_value LONGTEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Корбарони платформа
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS platform_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL DEFAULT '',
    phone VARCHAR(32) NOT NULL DEFAULT '',
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NULL,
    account_type VARCHAR(30) NOT NULL DEFAULT 'private',
    avatar_path VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_platform_email (email),
    KEY idx_platform_status (status),
    KEY idx_platform_account_type (account_type),
    KEY idx_platform_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Каталог (бренд, модел, категория)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS car_brands (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_car_brand_name (name),
    KEY idx_car_brand_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicle_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vehicle_cat_name (name),
    KEY idx_vehicle_cat_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS car_models (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_car_model_brand_name (brand_id, name),
    KEY idx_car_model_brand (brand_id),
    KEY idx_car_model_sort (brand_id, sort_order),
    CONSTRAINT fk_car_model_brand FOREIGN KEY (brand_id) REFERENCES car_brands (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Тарифҳо
-- ---------------------------------------------------------------------------

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

-- ---------------------------------------------------------------------------
-- Эълонҳо ва медиа
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ad_listings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    photo_url VARCHAR(255) NULL,
    brand VARCHAR(120) NOT NULL DEFAULT '',
    model VARCHAR(120) NOT NULL DEFAULT '',
    description TEXT NULL,
    model_year SMALLINT UNSIGNED NULL,
    mileage_km INT UNSIGNED NULL,
    vin VARCHAR(17) NULL,
    price DECIMAL(12, 2) NOT NULL DEFAULT 0,
    seller_name VARCHAR(150) NOT NULL DEFAULT '',
    city VARCHAR(120) NOT NULL DEFAULT '',
    body_type VARCHAR(32) NOT NULL DEFAULT '',
    fuel_type VARCHAR(24) NOT NULL DEFAULT '',
    transmission VARCHAR(24) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    is_vip TINYINT(1) NOT NULL DEFAULT 0,
    is_top TINYINT(1) NOT NULL DEFAULT 0,
    rejection_reason TEXT NULL,
    availability VARCHAR(24) NOT NULL DEFAULT 'in_stock',
    views_count INT UNSIGNED NOT NULL DEFAULT 0,
    clicks_count INT UNSIGNED NOT NULL DEFAULT 0,
    color VARCHAR(40) NOT NULL DEFAULT '',
    drive_type VARCHAR(16) NOT NULL DEFAULT '',
    engine_volume VARCHAR(40) NOT NULL DEFAULT '',
    has_turbo TINYINT(1) NOT NULL DEFAULT 0,
    condition_state VARCHAR(16) NOT NULL DEFAULT '',
    customs_cleared TINYINT(1) NOT NULL DEFAULT 0,
    taxi_license TINYINT(1) NOT NULL DEFAULT 0,
    prepayment_amount DECIMAL(12, 2) NULL,
    currency VARCHAR(8) NOT NULL DEFAULT 'TJS',
    listing_geo_lat DECIMAL(10, 7) NULL,
    listing_geo_lng DECIMAL(10, 7) NULL,
    listing_geo_captured_at DATETIME NULL,
    listing_geo_accuracy_m FLOAT NULL,
    expires_at DATETIME NULL,
    sold_at DATETIME NULL,
    last_engagement_at DATETIME NULL,
    user_soft_deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_list_user (user_id),
    KEY idx_list_status (status),
    KEY idx_list_created (created_at),
    KEY idx_list_vip (is_vip),
    CONSTRAINT fk_ad_list_platform_user FOREIGN KEY (user_id) REFERENCES platform_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_listing_media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id INT UNSIGNED NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    media_kind ENUM('image','video') NOT NULL DEFAULT 'image',
    stored_path VARCHAR(512) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ia_idx_amedia_listing_sort (listing_id, sort_order),
    CONSTRAINT ia_fk_amedia_ad_listing FOREIGN KEY (listing_id) REFERENCES ad_listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Пардохтҳо
-- ---------------------------------------------------------------------------

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

-- ---------------------------------------------------------------------------
-- Чат
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS chat_threads (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id INT UNSIGNED NOT NULL DEFAULT 0,
    user_low_id INT UNSIGNED NOT NULL,
    user_high_id INT UNSIGNED NOT NULL,
    is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    last_seen_low_at DATETIME NULL,
    last_seen_high_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_chat_pair (listing_id, user_low_id, user_high_id),
    KEY idx_chat_blocked (is_blocked),
    CONSTRAINT fk_chat_low FOREIGN KEY (user_low_id) REFERENCES platform_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_high FOREIGN KEY (user_high_id) REFERENCES platform_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    msg_type VARCHAR(16) NOT NULL DEFAULT 'text',
    attachment_path VARCHAR(255) NULL,
    attachment_name VARCHAR(200) NULL,
    attachment_mime VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cm_thread (thread_id),
    KEY idx_cm_created (created_at),
    CONSTRAINT fk_cm_thread FOREIGN KEY (thread_id) REFERENCES chat_threads (id) ON DELETE CASCADE,
    CONSTRAINT fk_cm_sender FOREIGN KEY (sender_id) REFERENCES platform_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_complaints (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id BIGINT UNSIGNED NOT NULL,
    reporter_id INT UNSIGNED NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'reviewed', 'dismissed') NOT NULL DEFAULT 'pending',
    admin_note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cc_status (status),
    KEY idx_cc_msg (message_id),
    CONSTRAINT fk_cc_msg FOREIGN KEY (message_id) REFERENCES chat_messages (id) ON DELETE CASCADE,
    CONSTRAINT fk_cc_rep FOREIGN KEY (reporter_id) REFERENCES platform_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Баннерҳо, favorite, compare
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS site_banners (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slot ENUM('homepage', 'promo_slider', 'ads') NOT NULL,
    title VARCHAR(200) NOT NULL DEFAULT '',
    image_path VARCHAR(255) NOT NULL,
    link_url VARCHAR(500) NOT NULL DEFAULT '',
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bn_slot (slot, sort_order),
    KEY idx_bn_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_favorites (
    user_id INT UNSIGNED NOT NULL,
    listing_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, listing_id),
    KEY ia_idx_ufav_listing (listing_id),
    CONSTRAINT ia_fk_ufav_platform_user FOREIGN KEY (user_id) REFERENCES platform_users (id) ON DELETE CASCADE,
    CONSTRAINT ia_fk_ufav_ad_listing FOREIGN KEY (listing_id) REFERENCES ad_listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_compare (
    user_id INT UNSIGNED NOT NULL,
    listing_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, listing_id),
    KEY ia_idx_ucmp_listing (listing_id),
    CONSTRAINT ia_fk_ucmp_platform_user FOREIGN KEY (user_id) REFERENCES platform_users (id) ON DELETE CASCADE,
    CONSTRAINT ia_fk_ucmp_ad_listing FOREIGN KEY (listing_id) REFERENCES ad_listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Огоҳиномаҳо
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS notification_campaigns (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel ENUM('push', 'sms', 'email') NOT NULL,
    audience ENUM('all', 'group', 'single') NOT NULL,
    subject VARCHAR(255) NOT NULL DEFAULT '',
    body TEXT NOT NULL,
    target_user_id INT UNSIGNED NULL,
    group_key VARCHAR(64) NULL,
    admin_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_nc_created (created_at),
    CONSTRAINT fk_nc_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users (id) ON DELETE SET NULL,
    CONSTRAINT fk_nc_target FOREIGN KEY (target_user_id) REFERENCES platform_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id INT UNSIGNED NOT NULL,
    platform_user_id INT UNSIGNED NOT NULL,
    status ENUM('queued', 'sent', 'failed', 'skipped') NOT NULL DEFAULT 'queued',
    detail VARCHAR(255) NULL,
    sent_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_nd_campaign (campaign_id),
    KEY idx_nd_user (platform_user_id),
    CONSTRAINT fk_nd_campaign FOREIGN KEY (campaign_id) REFERENCES notification_campaigns (id) ON DELETE CASCADE,
    CONSTRAINT fk_nd_user FOREIGN KEY (platform_user_id) REFERENCES platform_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    kind VARCHAR(40) NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    link_url VARCHAR(500) NULL,
    listing_id INT UNSIGNED NULL,
    thread_id INT UNSIGNED NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pn_user_read (user_id, is_read, created_at),
    KEY idx_pn_listing (listing_id),
    KEY idx_pn_thread (thread_id),
    CONSTRAINT fk_pn_user FOREIGN KEY (user_id) REFERENCES platform_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Reset парол, шикоятҳо, тамос
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS platform_password_resets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ppr_user (user_id),
    KEY idx_ppr_expires (expires_at),
    CONSTRAINT fk_ppr_user FOREIGN KEY (user_id) REFERENCES platform_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    from_name VARCHAR(120) NOT NULL,
    from_email VARCHAR(255) NOT NULL DEFAULT '',
    message TEXT NOT NULL,
    status ENUM('new', 'reviewed', 'closed') NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cr_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS listing_complaints (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id INT UNSIGNED NOT NULL,
    reporter_id INT UNSIGNED NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'reviewed', 'dismissed') NOT NULL DEFAULT 'pending',
    admin_note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_lc_status (status),
    KEY idx_lc_listing (listing_id),
    CONSTRAINT fk_lc_listing FOREIGN KEY (listing_id) REFERENCES ad_listings (id) ON DELETE CASCADE,
    CONSTRAINT fk_lc_reporter FOREIGN KEY (reporter_id) REFERENCES platform_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- Админ барои аввалин ворид (танҳо агар admin_users холӣ бошад)
-- Логин: admin@innovaauto.local  |  Парол: Admin123!
-- ---------------------------------------------------------------------------

INSERT INTO admin_users (email, username, password_hash, role, is_active)
SELECT 'admin@innovaauto.local', 'admin', '$2y$10$fwRRc6m0Alky/99bTeIAaOaTDbzUDOo8nUKgMYlQte.3tUdRHTcl.', 'super_admin', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM admin_users LIMIT 1);

-- Танзимоти асосии сайт (агар холӣ бошад)
INSERT INTO site_settings (setting_key, setting_value)
SELECT 'site_name', 'InnovaAuto' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM site_settings WHERE setting_key = 'site_name');

INSERT INTO site_settings (setting_key, setting_value)
SELECT 'meta_title', 'InnovaAuto — продажа автомобилей' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM site_settings WHERE setting_key = 'meta_title');

INSERT INTO site_settings (setting_key, setting_value)
SELECT 'contact_phone', '' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM site_settings WHERE setting_key = 'contact_phone');

INSERT INTO site_settings (setting_key, setting_value)
SELECT 'contact_email', '' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM site_settings WHERE setting_key = 'contact_email');

INSERT INTO site_settings (setting_key, setting_value)
SELECT 'contact_address', '' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM site_settings WHERE setting_key = 'contact_address');
