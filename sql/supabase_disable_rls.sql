-- PHP backend (postgres user) — RLS-ро хомӯш кунед, то INSERT/UPDATE бе сиёсат кор кунанд.
-- Supabase SQL Editor → Run

ALTER TABLE public.admin_users DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.admin_login_attempts DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.admin_remember_tokens DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.admin_password_resets DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.site_settings DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.platform_users DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.ad_listings DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.ad_listing_media DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.site_payments DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.billing_tariffs DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.billing_transactions DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.car_brands DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.car_models DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.vehicle_categories DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.chat_threads DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.chat_messages DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.chat_complaints DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.site_banners DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.user_favorites DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.user_compare DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.notification_campaigns DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.notification_deliveries DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.platform_notifications DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.platform_password_resets DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.contact_requests DISABLE ROW LEVEL SECURITY;
ALTER TABLE public.listing_complaints DISABLE ROW LEVEL SECURITY;
