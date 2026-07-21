-- Справочники: бренды, модели, категории кузова (разделы ТЗ 8–10).
-- При включённом IA_DB_PLATFORM_SCHEMA таблицы создаются автоматически из PHP.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS car_brands (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_car_brand_name (name),
    KEY idx_car_brand_sort (sort_order)
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

CREATE TABLE IF NOT EXISTS vehicle_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vehicle_cat_name (name),
    KEY idx_vehicle_cat_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
