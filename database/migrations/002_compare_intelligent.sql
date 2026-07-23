-- Intelligent comparison: maintenance profiles + extended listing fields

ALTER TABLE ad_listings ADD COLUMN IF NOT EXISTS generation VARCHAR(80) NOT NULL DEFAULT '' AFTER model;
ALTER TABLE ad_listings ADD COLUMN IF NOT EXISTS engine_power SMALLINT UNSIGNED NULL AFTER engine_volume;
ALTER TABLE ad_listings ADD COLUMN IF NOT EXISTS fuel_consumption DECIMAL(4, 1) NULL AFTER fuel_type;
ALTER TABLE ad_listings ADD COLUMN IF NOT EXISTS seat_count TINYINT UNSIGNED NULL AFTER body_type;
ALTER TABLE ad_listings ADD COLUMN IF NOT EXISTS ground_clearance_mm SMALLINT UNSIGNED NULL AFTER seat_count;
ALTER TABLE ad_listings ADD COLUMN IF NOT EXISTS listing_options JSON NULL AFTER ground_clearance_mm;
ALTER TABLE ad_listings ADD COLUMN IF NOT EXISTS seller_type VARCHAR(32) NOT NULL DEFAULT '' AFTER seller_name;
ALTER TABLE ad_listings ADD COLUMN IF NOT EXISTS credit_available TINYINT(1) NOT NULL DEFAULT 0 AFTER prepayment_amount;

CREATE TABLE IF NOT EXISTS car_maintenance_profiles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand VARCHAR(120) NOT NULL DEFAULT '',
    model VARCHAR(120) NOT NULL DEFAULT '',
    generation VARCHAR(80) NOT NULL DEFAULT '',
    year_from SMALLINT UNSIGNED NULL,
    year_to SMALLINT UNSIGNED NULL,
    maintenance_level ENUM('low', 'medium', 'high', 'premium') NOT NULL DEFAULT 'medium',
    parts_availability ENUM('easy', 'medium', 'difficult') NOT NULL DEFAULT 'medium',
    service_cost_level ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    fuel_cost_level ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    reliability_score TINYINT UNSIGNED NULL,
    source_note VARCHAR(255) NOT NULL DEFAULT '',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cmp_brand_model (brand, model),
    KEY idx_cmp_generation (generation),
    KEY idx_cmp_years (year_from, year_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
