-- После миграции: объявления на главной + каталоге (status = approved)
-- phpMyAdmin → u417315406_innovaauto → SQL → Go

UPDATE ad_listings SET status = 'approved' WHERE status IN ('active', 'pending');

UPDATE ad_listings
SET last_engagement_at = COALESCE(updated_at, created_at)
WHERE last_engagement_at IS NULL AND status = 'approved';

-- Колонки VIP/TOP (если база старая)
-- ALTER TABLE ad_listings ADD COLUMN is_vip TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
-- ALTER TABLE ad_listings ADD COLUMN is_top TINYINT(1) NOT NULL DEFAULT 0 AFTER is_vip;

-- Пример: включить TOP для объявления (замените id)
-- UPDATE ad_listings SET is_top = 1 WHERE id = 7;

SELECT status, COUNT(*) AS cnt FROM ad_listings GROUP BY status;
SELECT COUNT(*) AS approved_listings FROM ad_listings WHERE status = 'approved';
SELECT id, brand, model, is_vip, is_top FROM ad_listings WHERE status = 'approved' ORDER BY id;
