<?php

declare(strict_types=1);

function ia_ensure_platform_schema(IaPgConnection|IaPdoConnection $pdo): void
{
    $pdo->exec(
        <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    $pdo->exec(
        <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS site_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    amount DECIMAL(12, 2) NOT NULL,
    kind ENUM('general', 'vip') NOT NULL DEFAULT 'general',
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pay_created (created_at),
    KEY idx_pay_kind (kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    ia_ensure_catalog_schema($pdo);
    require_once IA_ROOT . '/includes/schema_billing.php';
    ia_ensure_billing_schema($pdo);
    require_once IA_ROOT . '/includes/schema_extended.php';
    ia_ensure_extended_schema($pdo);
    ia_ensure_platform_columns($pdo);
    require_once IA_ROOT . '/includes/schema_frontend.php';
    ia_ensure_frontend_schema($pdo);
    require_once IA_ROOT . '/includes/listing_media.php';
    ia_ensure_listing_media_table($pdo);
}

function ia_ensure_catalog_schema(IaPgConnection|IaPdoConnection $pdo): void
{
    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS car_brands (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_car_brand_name (name),
    KEY idx_car_brand_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    $pdo->exec(
        <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS vehicle_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vehicle_cat_name (name),
    KEY idx_vehicle_cat_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );
}

function ia_ensure_platform_columns(IaPgConnection|IaPdoConnection $pdo): void
{
    ia_ensure_column($pdo, 'platform_users', 'name', "ALTER TABLE platform_users ADD COLUMN name VARCHAR(150) NOT NULL DEFAULT '' AFTER id");
    ia_ensure_column($pdo, 'platform_users', 'phone', "ALTER TABLE platform_users ADD COLUMN phone VARCHAR(32) NOT NULL DEFAULT '' AFTER name");
    ia_ensure_column($pdo, 'platform_users', 'account_type', "ALTER TABLE platform_users ADD COLUMN account_type VARCHAR(30) NOT NULL DEFAULT 'private' AFTER email");
    ia_ensure_column($pdo, 'platform_users', 'avatar_path', 'ALTER TABLE platform_users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER account_type');
    ia_ensure_column($pdo, 'platform_users', 'updated_at', "ALTER TABLE platform_users ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");

    ia_ensure_column($pdo, 'ad_listings', 'photo_url', 'ALTER TABLE ad_listings ADD COLUMN photo_url VARCHAR(255) NULL AFTER user_id');
    ia_ensure_column($pdo, 'ad_listings', 'brand', "ALTER TABLE ad_listings ADD COLUMN brand VARCHAR(120) NOT NULL DEFAULT '' AFTER photo_url");
    ia_ensure_column($pdo, 'ad_listings', 'model', "ALTER TABLE ad_listings ADD COLUMN model VARCHAR(120) NOT NULL DEFAULT '' AFTER brand");
    ia_ensure_column($pdo, 'ad_listings', 'price', 'ALTER TABLE ad_listings ADD COLUMN price DECIMAL(12, 2) NOT NULL DEFAULT 0 AFTER model');
    ia_ensure_column($pdo, 'ad_listings', 'model_year', 'ALTER TABLE ad_listings ADD COLUMN model_year SMALLINT UNSIGNED NULL AFTER price');
    ia_ensure_column($pdo, 'ad_listings', 'mileage_km', 'ALTER TABLE ad_listings ADD COLUMN mileage_km INT UNSIGNED NULL AFTER model_year');
    ia_ensure_column($pdo, 'ad_listings', 'seller_name', "ALTER TABLE ad_listings ADD COLUMN seller_name VARCHAR(150) NOT NULL DEFAULT '' AFTER model_year");
    ia_ensure_column($pdo, 'ad_listings', 'city', "ALTER TABLE ad_listings ADD COLUMN city VARCHAR(120) NOT NULL DEFAULT '' AFTER seller_name");
    ia_ensure_column($pdo, 'ad_listings', 'body_type', "ALTER TABLE ad_listings ADD COLUMN body_type VARCHAR(32) NOT NULL DEFAULT '' AFTER city");
    ia_ensure_column($pdo, 'ad_listings', 'fuel_type', "ALTER TABLE ad_listings ADD COLUMN fuel_type VARCHAR(24) NOT NULL DEFAULT '' AFTER body_type");
    ia_ensure_column($pdo, 'ad_listings', 'transmission', "ALTER TABLE ad_listings ADD COLUMN transmission VARCHAR(24) NOT NULL DEFAULT '' AFTER fuel_type");
    ia_ensure_column($pdo, 'ad_listings', 'is_vip', 'ALTER TABLE ad_listings ADD COLUMN is_vip TINYINT(1) NOT NULL DEFAULT 0 AFTER status');
    ia_ensure_column($pdo, 'ad_listings', 'is_top', 'ALTER TABLE ad_listings ADD COLUMN is_top TINYINT(1) NOT NULL DEFAULT 0 AFTER is_vip');
    ia_ensure_column($pdo, 'ad_listings', 'rejection_reason', 'ALTER TABLE ad_listings ADD COLUMN rejection_reason TEXT NULL AFTER is_top');
    ia_ensure_column($pdo, 'ad_listings', 'availability', "ALTER TABLE ad_listings ADD COLUMN availability VARCHAR(24) NOT NULL DEFAULT 'in_stock' AFTER rejection_reason");
    ia_ensure_column($pdo, 'ad_listings', 'views_count', 'ALTER TABLE ad_listings ADD COLUMN views_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER availability');
    ia_ensure_column($pdo, 'ad_listings', 'clicks_count', 'ALTER TABLE ad_listings ADD COLUMN clicks_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER views_count');
    ia_ensure_column($pdo, 'ad_listings', 'color', "ALTER TABLE ad_listings ADD COLUMN color VARCHAR(40) NOT NULL DEFAULT '' AFTER clicks_count");
    ia_ensure_column($pdo, 'ad_listings', 'drive_type', "ALTER TABLE ad_listings ADD COLUMN drive_type VARCHAR(16) NOT NULL DEFAULT '' AFTER color");
    ia_ensure_column($pdo, 'ad_listings', 'engine_volume', "ALTER TABLE ad_listings ADD COLUMN engine_volume VARCHAR(40) NOT NULL DEFAULT '' AFTER drive_type");
    ia_ensure_column($pdo, 'ad_listings', 'has_turbo', 'ALTER TABLE ad_listings ADD COLUMN has_turbo TINYINT(1) NOT NULL DEFAULT 0 AFTER engine_volume');
    ia_ensure_column($pdo, 'ad_listings', 'condition_state', "ALTER TABLE ad_listings ADD COLUMN condition_state VARCHAR(16) NOT NULL DEFAULT '' AFTER has_turbo");
    ia_ensure_column($pdo, 'ad_listings', 'customs_cleared', 'ALTER TABLE ad_listings ADD COLUMN customs_cleared TINYINT(1) NOT NULL DEFAULT 0 AFTER condition_state');
    ia_ensure_column($pdo, 'ad_listings', 'taxi_license', 'ALTER TABLE ad_listings ADD COLUMN taxi_license TINYINT(1) NOT NULL DEFAULT 0 AFTER customs_cleared');
    ia_ensure_column($pdo, 'ad_listings', 'prepayment_amount', 'ALTER TABLE ad_listings ADD COLUMN prepayment_amount DECIMAL(12, 2) NULL AFTER taxi_license');
    ia_ensure_column($pdo, 'ad_listings', 'currency', "ALTER TABLE ad_listings ADD COLUMN currency VARCHAR(8) NOT NULL DEFAULT 'TJS' AFTER prepayment_amount");
    ia_ensure_column($pdo, 'ad_listings', 'listing_geo_lat', 'ALTER TABLE ad_listings ADD COLUMN listing_geo_lat DECIMAL(10, 7) NULL AFTER currency');
    ia_ensure_column($pdo, 'ad_listings', 'listing_geo_lng', 'ALTER TABLE ad_listings ADD COLUMN listing_geo_lng DECIMAL(10, 7) NULL AFTER listing_geo_lat');
    ia_ensure_column($pdo, 'ad_listings', 'listing_geo_captured_at', 'ALTER TABLE ad_listings ADD COLUMN listing_geo_captured_at DATETIME NULL AFTER listing_geo_lng');
    ia_ensure_column($pdo, 'ad_listings', 'listing_geo_accuracy_m', 'ALTER TABLE ad_listings ADD COLUMN listing_geo_accuracy_m FLOAT NULL AFTER listing_geo_captured_at');
    ia_ensure_column($pdo, 'ad_listings', 'listing_geo_place', "ALTER TABLE ad_listings ADD COLUMN listing_geo_place VARCHAR(120) NOT NULL DEFAULT '' AFTER listing_geo_accuracy_m");
    ia_ensure_column($pdo, 'ad_listings', 'vin', "ALTER TABLE ad_listings ADD COLUMN vin VARCHAR(17) NULL AFTER model");
    ia_ensure_column($pdo, 'ad_listings', 'expires_at', 'ALTER TABLE ad_listings ADD COLUMN expires_at DATETIME NULL AFTER listing_geo_accuracy_m');
    ia_ensure_column($pdo, 'ad_listings', 'sold_at', 'ALTER TABLE ad_listings ADD COLUMN sold_at DATETIME NULL AFTER expires_at');
    ia_ensure_column($pdo, 'ad_listings', 'last_engagement_at', 'ALTER TABLE ad_listings ADD COLUMN last_engagement_at DATETIME NULL AFTER sold_at');
    ia_ensure_column($pdo, 'ad_listings', 'user_soft_deleted_at', 'ALTER TABLE ad_listings ADD COLUMN user_soft_deleted_at DATETIME NULL AFTER last_engagement_at');
    ia_ensure_column($pdo, 'ad_listings', 'updated_at', 'ALTER TABLE ad_listings ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');

    if (ia_db_column_exists($pdo, 'ad_listings', 'last_engagement_at')) {
        $pdo->exec(
            "UPDATE ad_listings SET last_engagement_at = COALESCE(updated_at, created_at)
             WHERE last_engagement_at IS NULL AND status = 'approved'"
        );
    }
    $pdo->exec("UPDATE ad_listings SET status = 'approved' WHERE status = 'active'");
    $pdo->exec("UPDATE ad_listings SET status = 'pending' WHERE status NOT IN ('pending','approved','rejected','archived')");
}

function ia_ensure_column(IaPgConnection|IaPdoConnection $pdo, string $table, string $column, string $alterSql): void
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$table, $column]);
    if ((int) $st->fetchColumn() === 0) {
        $pdo->exec($alterSql);
    }
}

function ia_seed_platform_demo_if_empty(IaPgConnection|IaPdoConnection $pdo): void
{
    try {
        $users = (int) $pdo->query('SELECT COUNT(*) FROM platform_users')->fetchColumn();
        $payments = (int) $pdo->query('SELECT COUNT(*) FROM site_payments')->fetchColumn();
    } catch (\PDOException) {
        return;
    }

    if ($users === 0) {
        ia_seed_platform_demo_full($pdo);
    } elseif ($payments === 0) {
        ia_seed_site_payments_demo($pdo);
    }
    ia_seed_catalog_demo_if_empty($pdo);
    require_once IA_ROOT . '/includes/schema_billing.php';
    ia_seed_billing_if_empty($pdo);
    require_once IA_ROOT . '/includes/schema_extended.php';
    ia_seed_extended_demo_if_empty($pdo);
}

function ia_seed_catalog_demo_if_empty(IaPgConnection|IaPdoConnection $pdo): void
{
    try {
        $n = (int) $pdo->query('SELECT COUNT(*) FROM car_brands')->fetchColumn();
    } catch (\PDOException) {
        return;
    }
    if ($n > 0) {
        return;
    }
    $pdo->beginTransaction();
    try {
        $insB = $pdo->prepare('INSERT INTO car_brands (name, sort_order) VALUES (?, ?)');
        $insM = $pdo->prepare('INSERT INTO car_models (brand_id, name, sort_order) VALUES (?, ?, ?)');
        $insC = $pdo->prepare('INSERT INTO vehicle_categories (name, sort_order) VALUES (?, ?)');

        $insB->execute(['Toyota', 10]);
        $toyotaId = ia_db_last_insert_id($pdo, 'car_brands_id_seq');
        $insB->execute(['BMW', 20]);
        $bmwId = ia_db_last_insert_id($pdo, 'car_brands_id_seq');
        $insB->execute(['Mercedes', 30]);
        $mbId = ia_db_last_insert_id($pdo, 'car_brands_id_seq');
        if ($toyotaId < 1 || $bmwId < 1 || $mbId < 1) {
            $toyotaId = (int) $pdo->query("SELECT id FROM car_brands WHERE name = 'Toyota' LIMIT 1")->fetchColumn();
            $bmwId = (int) $pdo->query("SELECT id FROM car_brands WHERE name = 'BMW' LIMIT 1")->fetchColumn();
            $mbId = (int) $pdo->query("SELECT id FROM car_brands WHERE name = 'Mercedes' LIMIT 1")->fetchColumn();
        }

        foreach ([['Camry', 10], ['Corolla', 20]] as [$nm, $so]) {
            $insM->execute([$toyotaId, $nm, $so]);
        }
        $insM->execute([$bmwId, 'X5', 10]);
        $insM->execute([$mbId, 'E-Class', 10]);

        foreach ([['Sedan', 10], ['SUV', 20], ['Hatchback', 30], ['EV', 40], ['Pickup', 50]] as [$nm, $so]) {
            $insC->execute([$nm, $so]);
        }

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function ia_seed_site_payments_demo(IaPgConnection|IaPdoConnection $pdo): void
{
    $pdo->beginTransaction();
    try {
        $insPay = $pdo->prepare('INSERT INTO site_payments (amount, kind, note, created_at) VALUES (?, ?, ?, ?)');
        for ($p = 0; $p < 36; $p++) {
            $kind = $p % 5 === 0 ? 'vip' : 'general';
            $amount = $kind === 'vip'
                ? round(500 + (($p * 37) % 4500) / 10, 2)
                : round(100 + (($p * 91) % 8000) / 10, 2);
            $day = $p % 14;
            $created = (new DateTimeImmutable())->modify(sprintf('-%d days', $day))
                ->setTime(14, ($p * 13) % 60, 0)
                ->format('Y-m-d H:i:s');
            $note = $kind === 'vip' ? 'VIP-размещение' : 'Услуги сайта';
            $insPay->execute([$amount, $kind, $note, $created]);
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function ia_seed_platform_demo_full(IaPgConnection|IaPdoConnection $pdo): void
{
    $pdo->beginTransaction();
    try {
        $insUser = $pdo->prepare(
            'INSERT INTO platform_users (name, phone, email, account_type, status, created_at) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insListing = $pdo->prepare(
            'INSERT INTO ad_listings (user_id, photo_url, brand, model, price, seller_name, status, is_vip, is_top, rejection_reason, availability, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insPay = $pdo->prepare('INSERT INTO site_payments (amount, kind, note, created_at) VALUES (?, ?, ?, ?)');

        $brands = ['Toyota', 'Lada', 'Kia', 'Volkswagen', 'Hyundai', 'BMW', 'Mercedes'];
        $models = ['Camry', 'Vesta', 'Rio', 'Polo', 'Solaris', 'X5', 'E200'];
        $userIds = [];
        for ($u = 1; $u <= 24; $u++) {
            $email = sprintf('user%d@demo.innovaauto.local', $u);
            $status = $u % 11 === 0 ? 'blocked' : 'active';
            $accountType = $u % 4 === 0 ? 'dealer' : 'private';
            $daysAgo = min(13, ($u * 3) % 14);
            $created = (new DateTimeImmutable())->modify(sprintf('-%d days', $daysAgo))->format('Y-m-d H:i:s');
            $name = 'Пользователь ' . $u;
            $phone = '+99290000' . str_pad((string) $u, 3, '0', STR_PAD_LEFT);
            $insUser->execute([$name, $phone, $email, $accountType, $status, $created]);
            $userIds[] = (int) $pdo->lastInsertId();
        }

        $statuses = ['pending', 'approved', 'rejected', 'archived'];
        for ($i = 0; $i < 47; $i++) {
            $uid = $userIds[$i % count($userIds)];
            $st = $statuses[$i % count($statuses)];
            $day = $i % 14;
            $created = (new DateTimeImmutable())->modify(sprintf('-%d days', $day))
                ->setTime(10 + ($i % 8), ($i * 7) % 60, 0)
                ->format('Y-m-d H:i:s');
            $brand = $brands[$i % count($brands)];
            $model = $models[$i % count($models)];
            $price = (float) (25000 + (($i * 1111) % 250000));
            $isVip = $i % 6 === 0 ? 1 : 0;
            $isTop = $i % 9 === 0 ? 1 : 0;
            $reason = $st === 'rejected' ? 'Неполные данные по автомобилю' : null;
            $photo = 'https://via.placeholder.com/80x60?text=Auto+' . ($i + 1);
            $insListing->execute([$uid, $photo, $brand, $model, $price, 'Продавец ' . ($i + 1), $st, $isVip, $isTop, $reason, 'in_stock', $created]);
        }

        for ($p = 0; $p < 36; $p++) {
            $kind = $p % 5 === 0 ? 'vip' : 'general';
            $amount = $kind === 'vip'
                ? round(500 + (($p * 37) % 4500) / 10, 2)
                : round(100 + (($p * 91) % 8000) / 10, 2);
            $day = $p % 14;
            $created = (new DateTimeImmutable())->modify(sprintf('-%d days', $day))
                ->setTime(14, ($p * 13) % 60, 0)
                ->format('Y-m-d H:i:s');
            $note = $kind === 'vip' ? 'VIP-размещение' : 'Услуги сайта';
            $insPay->execute([$amount, $kind, $note, $created]);
        }

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
