<?php

declare(strict_types=1);

/**
 * Схема для публичного сайта: пароли пользователей, избранное, описание объявления.
 */
function ia_ensure_frontend_schema(IaPgConnection|IaPdoConnection $pdo): void
{
    ia_ensure_column(
        $pdo,
        'platform_users',
        'password_hash',
        'ALTER TABLE platform_users ADD COLUMN password_hash VARCHAR(255) NULL AFTER email'
    );

    ia_ensure_column(
        $pdo,
        'platform_users',
        'google_id',
        'ALTER TABLE platform_users ADD COLUMN google_id VARCHAR(64) NULL AFTER email'
    );

    ia_ensure_column(
        $pdo,
        'ad_listings',
        'description',
        'ALTER TABLE ad_listings ADD COLUMN description TEXT NULL AFTER model'
    );

    ia_ensure_user_favorites_table($pdo);
    ia_ensure_user_compare_table($pdo);

    ia_seed_frontend_settings_if_empty($pdo);
}

function ia_ensure_user_compare_table(IaPgConnection|IaPdoConnection $pdo): void
{
    $chk = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $chk->execute(['user_compare']);
    if ((int) $chk->fetchColumn() > 0) {
        return;
    }

    $pdo->exec(
        <<<'SQL'
CREATE TABLE user_compare (
    user_id INT UNSIGNED NOT NULL,
    listing_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, listing_id),
    KEY ia_idx_ucmp_listing (listing_id),
    CONSTRAINT ia_fk_ucmp_platform_user FOREIGN KEY (user_id) REFERENCES platform_users (id) ON DELETE CASCADE,
    CONSTRAINT ia_fk_ucmp_ad_listing FOREIGN KEY (listing_id) REFERENCES ad_listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );
}

/**
 * Имена FOREIGN KEY должны быть уникальны в пределах базы (иначе errno 121).
 */
function ia_ensure_user_favorites_table(IaPgConnection|IaPdoConnection $pdo): void
{
    $chk = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $chk->execute(['user_favorites']);
    if ((int) $chk->fetchColumn() > 0) {
        return;
    }

    $pdo->exec(
        <<<'SQL'
CREATE TABLE user_favorites (
    user_id INT UNSIGNED NOT NULL,
    listing_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, listing_id),
    KEY ia_idx_ufav_listing (listing_id),
    CONSTRAINT ia_fk_ufav_platform_user FOREIGN KEY (user_id) REFERENCES platform_users (id) ON DELETE CASCADE,
    CONSTRAINT ia_fk_ufav_ad_listing FOREIGN KEY (listing_id) REFERENCES ad_listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );
}

function ia_seed_frontend_settings_if_empty(IaPgConnection|IaPdoConnection $pdo): void
{
    $defaults = [
        'page_about_text' => '',
        'page_contact_intro' => 'Свяжитесь с нами через контакты из настроек сайта (админ-панель) или оставьте сообщение в форме.',
        'footer_company_text' => 'InnovaAuto — маркетплейс автомобилей в Таджикистане.',
        'contact_phone' => '+992 00 000 0000',
        'contact_email' => 'info@innovaauto.tj',
        'contact_address' => 'Душанбе, Таджикистан',
        'social_telegram' => 'https://t.me/',
        'social_instagram' => 'https://instagram.com/',
        'social_facebook' => 'https://facebook.com/',
        'social_youtube' => 'https://youtube.com/',
        'footer_brand_title' => 'Innovaauto.com',
        'listing_photo_qa_enabled' => '0',
    ];
    try {
        $ins = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)');
        $cntSt = $pdo->prepare('SELECT COUNT(*) FROM site_settings WHERE setting_key = ?');
        foreach ($defaults as $key => $val) {
            $cntSt->execute([$key]);
            if ((int) $cntSt->fetchColumn() === 0) {
                $ins->execute([$key, $val]);
            }
        }
    } catch (\PDOException) {
    }
}
