<?php

declare(strict_types=1);

function ia_ensure_pgsql_schema(IaPgConnection|IaPdoConnection $pdo): void
{
    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS admin_users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'manager',
    is_active SMALLINT NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
    );
    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS admin_login_attempts (
    id BIGSERIAL PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ip_time ON admin_login_attempts (ip_address, attempted_at)');
    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS admin_remember_tokens (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
    selector CHAR(32) NOT NULL UNIQUE,
    validator_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
    );
    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS admin_password_resets (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
    token_hash CHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reset_user ON admin_password_resets (user_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reset_expires ON admin_password_resets (expires_at)');

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(64) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
    );

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS platform_users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL DEFAULT '',
    phone VARCHAR(32) NOT NULL DEFAULT '',
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NULL,
    google_id VARCHAR(64) NULL,
    account_type VARCHAR(30) NOT NULL DEFAULT 'private',
    avatar_path VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
    );

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS ad_listings (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES platform_users(id) ON DELETE CASCADE,
    photo_url VARCHAR(255) NULL,
    brand VARCHAR(120) NOT NULL DEFAULT '',
    model VARCHAR(120) NOT NULL DEFAULT '',
    description TEXT NULL,
    model_year SMALLINT NULL,
    mileage_km INTEGER NULL,
    city VARCHAR(120) NOT NULL DEFAULT '',
    body_type VARCHAR(32) NOT NULL DEFAULT '',
    fuel_type VARCHAR(24) NOT NULL DEFAULT '',
    transmission VARCHAR(24) NOT NULL DEFAULT '',
    price NUMERIC(12,2) NOT NULL DEFAULT 0,
    seller_name VARCHAR(150) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    is_vip SMALLINT NOT NULL DEFAULT 0,
    is_top SMALLINT NOT NULL DEFAULT 0,
    rejection_reason TEXT NULL,
    availability VARCHAR(24) NOT NULL DEFAULT 'in_stock',
    views_count INTEGER NOT NULL DEFAULT 0,
    clicks_count INTEGER NOT NULL DEFAULT 0,
    color VARCHAR(40) NOT NULL DEFAULT '',
    drive_type VARCHAR(16) NOT NULL DEFAULT '',
    engine_volume VARCHAR(40) NOT NULL DEFAULT '',
    has_turbo SMALLINT NOT NULL DEFAULT 0,
    condition_state VARCHAR(16) NOT NULL DEFAULT '',
    customs_cleared SMALLINT NOT NULL DEFAULT 0,
    taxi_license SMALLINT NOT NULL DEFAULT 0,
    prepayment_amount NUMERIC(12,2) NULL,
    currency VARCHAR(8) NOT NULL DEFAULT 'TJS',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_list_user ON ad_listings (user_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_list_status ON ad_listings (status)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_list_created ON ad_listings (created_at)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_list_vip ON ad_listings (is_vip)');

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS site_payments (
    id BIGSERIAL PRIMARY KEY,
    amount NUMERIC(12,2) NOT NULL,
    kind VARCHAR(20) NOT NULL DEFAULT 'general',
    note VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
    );

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS car_brands (
    id SERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
    );
    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS car_models (
    id SERIAL PRIMARY KEY,
    brand_id INTEGER NOT NULL REFERENCES car_brands(id) ON DELETE CASCADE,
    name VARCHAR(120) NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (brand_id, name)
)
SQL
    );
    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS vehicle_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
    );

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS billing_tariffs (
    id SERIAL PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    price NUMERIC(12,2) NOT NULL DEFAULT 0,
    duration_days INTEGER NULL,
    benefits TEXT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
    );
    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS billing_transactions (
    id BIGSERIAL PRIMARY KEY,
    platform_user_id INTEGER NOT NULL REFERENCES platform_users(id) ON DELETE CASCADE,
    tariff_id INTEGER NULL REFERENCES billing_tariffs(id) ON DELETE SET NULL,
    amount NUMERIC(12,2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    method VARCHAR(40) NOT NULL DEFAULT 'unknown',
    note VARCHAR(255) NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
    );

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS chat_threads (
    id SERIAL PRIMARY KEY,
    listing_id INTEGER NOT NULL DEFAULT 0,
    user_low_id INTEGER NOT NULL REFERENCES platform_users(id) ON DELETE CASCADE,
    user_high_id INTEGER NOT NULL REFERENCES platform_users(id) ON DELETE CASCADE,
    is_blocked SMALLINT NOT NULL DEFAULT 0,
    last_seen_low_at TIMESTAMP NULL,
    last_seen_high_at TIMESTAMP NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (listing_id, user_low_id, user_high_id)
)
SQL
    );
    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS chat_messages (
    id BIGSERIAL PRIMARY KEY,
    thread_id INTEGER NOT NULL REFERENCES chat_threads(id) ON DELETE CASCADE,
    sender_id INTEGER NOT NULL REFERENCES platform_users(id) ON DELETE CASCADE,
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
    );
    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS chat_complaints (
    id SERIAL PRIMARY KEY,
    message_id BIGINT NOT NULL REFERENCES chat_messages(id) ON DELETE CASCADE,
    reporter_id INTEGER NOT NULL REFERENCES platform_users(id) ON DELETE CASCADE,
    reason TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    admin_note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL
)
SQL
    );

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS site_banners (
    id SERIAL PRIMARY KEY,
    slot VARCHAR(30) NOT NULL,
    title VARCHAR(200) NOT NULL DEFAULT '',
    image_path VARCHAR(255) NOT NULL,
    link_url VARCHAR(500) NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active SMALLINT NOT NULL DEFAULT 1,
    starts_at TIMESTAMP NULL,
    ends_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
    );

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS user_favorites (
    user_id INTEGER NOT NULL REFERENCES platform_users(id) ON DELETE CASCADE,
    listing_id INTEGER NOT NULL REFERENCES ad_listings(id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, listing_id)
)
SQL
    );

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS ad_listing_media (
    id BIGSERIAL PRIMARY KEY,
    listing_id INTEGER NOT NULL REFERENCES ad_listings(id) ON DELETE CASCADE,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    media_kind VARCHAR(10) NOT NULL DEFAULT 'image',
    stored_path VARCHAR(512) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
    );

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS notification_campaigns (
    id SERIAL PRIMARY KEY,
    channel VARCHAR(16) NOT NULL,
    audience VARCHAR(16) NOT NULL,
    subject VARCHAR(255) NOT NULL DEFAULT '',
    body TEXT NOT NULL,
    target_user_id INTEGER NULL REFERENCES platform_users(id) ON DELETE SET NULL,
    group_key VARCHAR(64) NULL,
    admin_user_id INTEGER NULL REFERENCES admin_users(id) ON DELETE SET NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_nc_created ON notification_campaigns (created_at)');

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS notification_deliveries (
    id BIGSERIAL PRIMARY KEY,
    campaign_id INTEGER NOT NULL REFERENCES notification_campaigns(id) ON DELETE CASCADE,
    platform_user_id INTEGER NOT NULL REFERENCES platform_users(id) ON DELETE CASCADE,
    status VARCHAR(16) NOT NULL DEFAULT 'queued',
    detail VARCHAR(255) NULL,
    sent_at TIMESTAMP NULL
)
SQL
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_nd_campaign ON notification_deliveries (campaign_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_nd_user ON notification_deliveries (platform_user_id)');

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS platform_notifications (
    id BIGSERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES platform_users(id) ON DELETE CASCADE,
    kind VARCHAR(40) NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    link_url VARCHAR(500) NULL,
    listing_id INTEGER NULL,
    thread_id INTEGER NULL,
    is_read SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pn_user_read ON platform_notifications (user_id, is_read, created_at)');

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS user_compare (
    user_id INTEGER NOT NULL REFERENCES platform_users(id) ON DELETE CASCADE,
    listing_id INTEGER NOT NULL REFERENCES ad_listings(id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, listing_id)
)
SQL
    );

    require_once IA_ROOT . '/includes/db_compat.php';
    ia_ensure_pgsql_ad_listings_columns($pdo);
    if (ia_db_column_exists($pdo, 'ad_listings', 'last_engagement_at')) {
        $pdo->exec(
            "UPDATE ad_listings SET last_engagement_at = COALESCE(updated_at, created_at)
             WHERE last_engagement_at IS NULL AND status = 'approved'"
        );
    }
    if (!ia_db_column_exists($pdo, 'platform_users', 'avatar_path')) {
        $pdo->exec('ALTER TABLE platform_users ADD COLUMN avatar_path VARCHAR(255) NULL');
    }
    if (!ia_db_column_exists($pdo, 'platform_users', 'google_id')) {
        $pdo->exec('ALTER TABLE platform_users ADD COLUMN google_id VARCHAR(64) NULL');
        try {
            $pdo->exec(
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_platform_users_google_id ON platform_users (google_id) WHERE google_id IS NOT NULL'
            );
        } catch (\Throwable) {
        }
    }
    if (!ia_db_column_exists($pdo, 'chat_messages', 'msg_type')) {
        $pdo->exec("ALTER TABLE chat_messages ADD COLUMN msg_type VARCHAR(16) NOT NULL DEFAULT 'text'");
    }
    if (!ia_db_column_exists($pdo, 'chat_messages', 'attachment_path')) {
        $pdo->exec('ALTER TABLE chat_messages ADD COLUMN attachment_path VARCHAR(255) NULL');
    }
    if (!ia_db_column_exists($pdo, 'chat_messages', 'attachment_name')) {
        $pdo->exec('ALTER TABLE chat_messages ADD COLUMN attachment_name VARCHAR(200) NULL');
    }
    if (!ia_db_column_exists($pdo, 'chat_messages', 'attachment_mime')) {
        $pdo->exec('ALTER TABLE chat_messages ADD COLUMN attachment_mime VARCHAR(100) NULL');
    }

    require_once IA_ROOT . '/includes/schema_public_moderation.php';
    ia_ensure_public_moderation_schema($pdo);
}

function ia_ensure_pgsql_ad_listings_columns(IaPgConnection|IaPdoConnection $pdo): void
{
    require_once IA_ROOT . '/includes/db_compat.php';
    if (!ia_db_column_exists($pdo, 'ad_listings', 'availability')) {
        $pdo->exec("ALTER TABLE ad_listings ADD COLUMN availability VARCHAR(24) NOT NULL DEFAULT 'in_stock'");
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'views_count')) {
        $pdo->exec('ALTER TABLE ad_listings ADD COLUMN views_count INTEGER NOT NULL DEFAULT 0');
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'clicks_count')) {
        $pdo->exec('ALTER TABLE ad_listings ADD COLUMN clicks_count INTEGER NOT NULL DEFAULT 0');
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'color')) {
        $pdo->exec("ALTER TABLE ad_listings ADD COLUMN color VARCHAR(40) NOT NULL DEFAULT ''");
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'drive_type')) {
        $pdo->exec("ALTER TABLE ad_listings ADD COLUMN drive_type VARCHAR(16) NOT NULL DEFAULT ''");
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'engine_volume')) {
        $pdo->exec("ALTER TABLE ad_listings ADD COLUMN engine_volume VARCHAR(40) NOT NULL DEFAULT ''");
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'has_turbo')) {
        $pdo->exec('ALTER TABLE ad_listings ADD COLUMN has_turbo SMALLINT NOT NULL DEFAULT 0');
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'condition_state')) {
        $pdo->exec("ALTER TABLE ad_listings ADD COLUMN condition_state VARCHAR(16) NOT NULL DEFAULT ''");
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'customs_cleared')) {
        $pdo->exec('ALTER TABLE ad_listings ADD COLUMN customs_cleared SMALLINT NOT NULL DEFAULT 0');
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'taxi_license')) {
        $pdo->exec('ALTER TABLE ad_listings ADD COLUMN taxi_license SMALLINT NOT NULL DEFAULT 0');
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'prepayment_amount')) {
        $pdo->exec('ALTER TABLE ad_listings ADD COLUMN prepayment_amount NUMERIC(12,2) NULL');
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'currency')) {
        $pdo->exec("ALTER TABLE ad_listings ADD COLUMN currency VARCHAR(8) NOT NULL DEFAULT 'TJS'");
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'listing_geo_lat')) {
        $pdo->exec('ALTER TABLE ad_listings ADD COLUMN listing_geo_lat NUMERIC(10, 7) NULL');
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'listing_geo_lng')) {
        $pdo->exec('ALTER TABLE ad_listings ADD COLUMN listing_geo_lng NUMERIC(10, 7) NULL');
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'listing_geo_captured_at')) {
        $pdo->exec('ALTER TABLE ad_listings ADD COLUMN listing_geo_captured_at TIMESTAMP NULL');
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'listing_geo_accuracy_m')) {
        $pdo->exec('ALTER TABLE ad_listings ADD COLUMN listing_geo_accuracy_m DOUBLE PRECISION NULL');
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'listing_geo_place')) {
        $pdo->exec("ALTER TABLE ad_listings ADD COLUMN listing_geo_place VARCHAR(120) NOT NULL DEFAULT ''");
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'vin')) {
        $pdo->exec('ALTER TABLE ad_listings ADD COLUMN vin VARCHAR(17) NULL');
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'expires_at')) {
        $pdo->exec('ALTER TABLE ad_listings ADD COLUMN expires_at TIMESTAMP NULL');
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'sold_at')) {
        $pdo->exec('ALTER TABLE ad_listings ADD COLUMN sold_at TIMESTAMP NULL');
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'last_engagement_at')) {
        $pdo->exec('ALTER TABLE ad_listings ADD COLUMN last_engagement_at TIMESTAMP NULL');
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'user_soft_deleted_at')) {
        $pdo->exec('ALTER TABLE ad_listings ADD COLUMN user_soft_deleted_at TIMESTAMP NULL');
    }
}
