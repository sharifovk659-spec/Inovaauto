-- =============================================================================
-- Innova Auto — схемаи пурраи Supabase (PostgreSQL)
-- =============================================================================
-- Иҷро: Supabase Dashboard → SQL Editor → New query → Run
--   ё: psql "postgresql://postgres:[PASSWORD]@db.xenelqfppvjyuxnoamme.supabase.co:5432/postgres" -f sql/supabase_schema_full.sql
--
-- Ключҳо барои Vite (.env.local) — дар ин файл НАГУЗОРЕД:
--   VITE_SUPABASE_URL=https://xenelqfppvjyuxnoamme.supabase.co
--   VITE_SUPABASE_PUBLISHABLE_KEY=sb_publishable_...
--
-- Алоқоти ҷадвалҳо (FK):
--   admin_users ← admin_remember_tokens, admin_password_resets, notification_campaigns
--   platform_users ← ad_listings, billing_transactions, chat_*, favorites, compare, ...
--   ad_listings ← ad_listing_media, user_favorites, user_compare, listing_complaints
--   car_brands ← car_models
--   billing_tariffs ← billing_transactions
--   chat_threads ← chat_messages ← chat_complaints
--   notification_campaigns ← notification_deliveries
-- =============================================================================

-- Extensions (ихтиёрӣ, барои оянда)
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- ---------------------------------------------------------------------------
-- Админ
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.admin_users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'manager',
    is_active SMALLINT NOT NULL DEFAULT 1,
    last_login_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS public.admin_login_attempts (
    id BIGSERIAL PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_admin_login_ip_time
    ON public.admin_login_attempts (ip_address, attempted_at);

CREATE TABLE IF NOT EXISTS public.admin_remember_tokens (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES public.admin_users(id) ON DELETE CASCADE,
    selector CHAR(32) NOT NULL UNIQUE,
    validator_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_admin_remember_user
    ON public.admin_remember_tokens (user_id);

CREATE TABLE IF NOT EXISTS public.admin_password_resets (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES public.admin_users(id) ON DELETE CASCADE,
    token_hash CHAR(64) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_admin_reset_user ON public.admin_password_resets (user_id);
CREATE INDEX IF NOT EXISTS idx_admin_reset_expires ON public.admin_password_resets (expires_at);

CREATE TABLE IF NOT EXISTS public.site_settings (
    setting_key VARCHAR(64) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ---------------------------------------------------------------------------
-- Корбарони платформа
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.platform_users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL DEFAULT '',
    phone VARCHAR(32) NOT NULL DEFAULT '',
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NULL,
    account_type VARCHAR(30) NOT NULL DEFAULT 'private',
    avatar_path VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ---------------------------------------------------------------------------
-- Эълонҳо ва медиа
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.ad_listings (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES public.platform_users(id) ON DELETE CASCADE,
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
    price NUMERIC(12, 2) NOT NULL DEFAULT 0,
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
    prepayment_amount NUMERIC(12, 2) NULL,
    currency VARCHAR(8) NOT NULL DEFAULT 'TJS',
    listing_geo_lat NUMERIC(10, 7) NULL,
    listing_geo_lng NUMERIC(10, 7) NULL,
    listing_geo_captured_at TIMESTAMPTZ NULL,
    listing_geo_accuracy_m DOUBLE PRECISION NULL,
    vin VARCHAR(17) NULL,
    expires_at TIMESTAMPTZ NULL,
    sold_at TIMESTAMPTZ NULL,
    last_engagement_at TIMESTAMPTZ NULL,
    user_soft_deleted_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_list_user ON public.ad_listings (user_id);
CREATE INDEX IF NOT EXISTS idx_list_status ON public.ad_listings (status);
CREATE INDEX IF NOT EXISTS idx_list_created ON public.ad_listings (created_at);
CREATE INDEX IF NOT EXISTS idx_list_vip ON public.ad_listings (is_vip);

CREATE TABLE IF NOT EXISTS public.ad_listing_media (
    id BIGSERIAL PRIMARY KEY,
    listing_id INTEGER NOT NULL REFERENCES public.ad_listings(id) ON DELETE CASCADE,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    media_kind VARCHAR(10) NOT NULL DEFAULT 'image',
    stored_path VARCHAR(512) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_listing_media_listing
    ON public.ad_listing_media (listing_id, sort_order);

-- ---------------------------------------------------------------------------
-- Пардохтҳо ва тарифҳо
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.site_payments (
    id BIGSERIAL PRIMARY KEY,
    amount NUMERIC(12, 2) NOT NULL,
    kind VARCHAR(20) NOT NULL DEFAULT 'general',
    note VARCHAR(255) NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS public.billing_tariffs (
    id SERIAL PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    price NUMERIC(12, 2) NOT NULL DEFAULT 0,
    duration_days INTEGER NULL,
    benefits TEXT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS public.billing_transactions (
    id BIGSERIAL PRIMARY KEY,
    platform_user_id INTEGER NOT NULL REFERENCES public.platform_users(id) ON DELETE CASCADE,
    tariff_id INTEGER NULL REFERENCES public.billing_tariffs(id) ON DELETE SET NULL,
    amount NUMERIC(12, 2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    method VARCHAR(40) NOT NULL DEFAULT 'unknown',
    note VARCHAR(255) NULL,
    paid_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_billing_tx_user ON public.billing_transactions (platform_user_id);

-- ---------------------------------------------------------------------------
-- Каталог (бренд / модел)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.car_brands (
    id SERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS public.car_models (
    id SERIAL PRIMARY KEY,
    brand_id INTEGER NOT NULL REFERENCES public.car_brands(id) ON DELETE CASCADE,
    name VARCHAR(120) NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (brand_id, name)
);
CREATE INDEX IF NOT EXISTS idx_car_models_brand ON public.car_models (brand_id);

CREATE TABLE IF NOT EXISTS public.vehicle_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ---------------------------------------------------------------------------
-- Чат
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.chat_threads (
    id SERIAL PRIMARY KEY,
    listing_id INTEGER NOT NULL DEFAULT 0,
    user_low_id INTEGER NOT NULL REFERENCES public.platform_users(id) ON DELETE CASCADE,
    user_high_id INTEGER NOT NULL REFERENCES public.platform_users(id) ON DELETE CASCADE,
    is_blocked SMALLINT NOT NULL DEFAULT 0,
    last_seen_low_at TIMESTAMPTZ NULL,
    last_seen_high_at TIMESTAMPTZ NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (listing_id, user_low_id, user_high_id)
);
CREATE INDEX IF NOT EXISTS idx_chat_threads_users
    ON public.chat_threads (user_low_id, user_high_id);

CREATE TABLE IF NOT EXISTS public.chat_messages (
    id BIGSERIAL PRIMARY KEY,
    thread_id INTEGER NOT NULL REFERENCES public.chat_threads(id) ON DELETE CASCADE,
    sender_id INTEGER NOT NULL REFERENCES public.platform_users(id) ON DELETE CASCADE,
    body TEXT NOT NULL,
    msg_type VARCHAR(16) NOT NULL DEFAULT 'text',
    attachment_path VARCHAR(255) NULL,
    attachment_name VARCHAR(200) NULL,
    attachment_mime VARCHAR(100) NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_chat_messages_thread ON public.chat_messages (thread_id, created_at);

CREATE TABLE IF NOT EXISTS public.chat_complaints (
    id SERIAL PRIMARY KEY,
    message_id BIGINT NOT NULL REFERENCES public.chat_messages(id) ON DELETE CASCADE,
    reporter_id INTEGER NOT NULL REFERENCES public.platform_users(id) ON DELETE CASCADE,
    reason TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    admin_note TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    reviewed_at TIMESTAMPTZ NULL
);

-- ---------------------------------------------------------------------------
-- Баннерҳо, favorite, compare
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.site_banners (
    id SERIAL PRIMARY KEY,
    slot VARCHAR(30) NOT NULL,
    title VARCHAR(200) NOT NULL DEFAULT '',
    image_path VARCHAR(255) NOT NULL,
    link_url VARCHAR(500) NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active SMALLINT NOT NULL DEFAULT 1,
    starts_at TIMESTAMPTZ NULL,
    ends_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS public.user_favorites (
    user_id INTEGER NOT NULL REFERENCES public.platform_users(id) ON DELETE CASCADE,
    listing_id INTEGER NOT NULL REFERENCES public.ad_listings(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (user_id, listing_id)
);

CREATE TABLE IF NOT EXISTS public.user_compare (
    user_id INTEGER NOT NULL REFERENCES public.platform_users(id) ON DELETE CASCADE,
    listing_id INTEGER NOT NULL REFERENCES public.ad_listings(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (user_id, listing_id)
);

-- ---------------------------------------------------------------------------
-- Огоҳиномаҳо
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.notification_campaigns (
    id SERIAL PRIMARY KEY,
    channel VARCHAR(16) NOT NULL,
    audience VARCHAR(16) NOT NULL,
    subject VARCHAR(255) NOT NULL DEFAULT '',
    body TEXT NOT NULL,
    target_user_id INTEGER NULL REFERENCES public.platform_users(id) ON DELETE SET NULL,
    group_key VARCHAR(64) NULL,
    admin_user_id INTEGER NULL REFERENCES public.admin_users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_nc_created ON public.notification_campaigns (created_at);

CREATE TABLE IF NOT EXISTS public.notification_deliveries (
    id BIGSERIAL PRIMARY KEY,
    campaign_id INTEGER NOT NULL REFERENCES public.notification_campaigns(id) ON DELETE CASCADE,
    platform_user_id INTEGER NOT NULL REFERENCES public.platform_users(id) ON DELETE CASCADE,
    status VARCHAR(16) NOT NULL DEFAULT 'queued',
    detail VARCHAR(255) NULL,
    sent_at TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS idx_nd_campaign ON public.notification_deliveries (campaign_id);
CREATE INDEX IF NOT EXISTS idx_nd_user ON public.notification_deliveries (platform_user_id);

CREATE TABLE IF NOT EXISTS public.platform_notifications (
    id BIGSERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES public.platform_users(id) ON DELETE CASCADE,
    kind VARCHAR(40) NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    link_url VARCHAR(500) NULL,
    listing_id INTEGER NULL REFERENCES public.ad_listings(id) ON DELETE SET NULL,
    thread_id INTEGER NULL REFERENCES public.chat_threads(id) ON DELETE SET NULL,
    is_read SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_pn_user_read
    ON public.platform_notifications (user_id, is_read, created_at);

-- ---------------------------------------------------------------------------
-- Reset парол, шикоятҳо, тамос
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS public.platform_password_resets (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES public.platform_users(id) ON DELETE CASCADE,
    token_hash CHAR(64) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_ppr_user ON public.platform_password_resets (user_id);
CREATE INDEX IF NOT EXISTS idx_ppr_expires ON public.platform_password_resets (expires_at);

CREATE TABLE IF NOT EXISTS public.contact_requests (
    id BIGSERIAL PRIMARY KEY,
    from_name VARCHAR(120) NOT NULL,
    from_email VARCHAR(255) NOT NULL DEFAULT '',
    message TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_cr_status ON public.contact_requests (status, created_at);

CREATE TABLE IF NOT EXISTS public.listing_complaints (
    id SERIAL PRIMARY KEY,
    listing_id INTEGER NOT NULL REFERENCES public.ad_listings(id) ON DELETE CASCADE,
    reporter_id INTEGER NOT NULL REFERENCES public.platform_users(id) ON DELETE CASCADE,
    reason TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    admin_note TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    reviewed_at TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS idx_lc_status ON public.listing_complaints (status);
CREATE INDEX IF NOT EXISTS idx_lc_listing ON public.listing_complaints (listing_id);

-- =============================================================================
-- RLS: барои PHP backend хомӯш нигоҳ доред (INSERT/UPDATE бе сиёсат).
-- Агар пеш ENABLE карда бошед: sql/supabase_disable_rls.sql
-- =============================================================================
-- ALTER TABLE public.admin_users ENABLE ROW LEVEL SECURITY;
-- (RLS хомӯш — барои Vite/anon баъдтар сиёсатҳо илова кунед)
