<?php

declare(strict_types=1);

function ia_ensure_extended_schema(IaPgConnection|IaPdoConnection $pdo): void
{
    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS chat_threads (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id INT UNSIGNED NOT NULL DEFAULT 0,
    user_low_id INT UNSIGNED NOT NULL,
    user_high_id INT UNSIGNED NOT NULL,
    is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_chat_pair (listing_id, user_low_id, user_high_id),
    KEY idx_chat_blocked (is_blocked),
    CONSTRAINT fk_chat_low FOREIGN KEY (user_low_id) REFERENCES platform_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_high FOREIGN KEY (user_high_id) REFERENCES platform_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );
    ia_ensure_extended_column(
        $pdo,
        'chat_threads',
        'last_seen_low_at',
        'ALTER TABLE chat_threads ADD COLUMN last_seen_low_at DATETIME NULL AFTER is_blocked'
    );
    ia_ensure_extended_column(
        $pdo,
        'chat_threads',
        'last_seen_high_at',
        'ALTER TABLE chat_threads ADD COLUMN last_seen_high_at DATETIME NULL AFTER last_seen_low_at'
    );

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS chat_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cm_thread (thread_id),
    KEY idx_cm_created (created_at),
    CONSTRAINT fk_cm_thread FOREIGN KEY (thread_id) REFERENCES chat_threads (id) ON DELETE CASCADE,
    CONSTRAINT fk_cm_sender FOREIGN KEY (sender_id) REFERENCES platform_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );
    ia_ensure_extended_column(
        $pdo,
        'chat_messages',
        'msg_type',
        "ALTER TABLE chat_messages ADD COLUMN msg_type VARCHAR(16) NOT NULL DEFAULT 'text' AFTER body"
    );
    ia_ensure_extended_column(
        $pdo,
        'chat_messages',
        'attachment_path',
        'ALTER TABLE chat_messages ADD COLUMN attachment_path VARCHAR(255) NULL AFTER msg_type'
    );
    ia_ensure_extended_column(
        $pdo,
        'chat_messages',
        'attachment_name',
        'ALTER TABLE chat_messages ADD COLUMN attachment_name VARCHAR(200) NULL AFTER attachment_path'
    );
    ia_ensure_extended_column(
        $pdo,
        'chat_messages',
        'attachment_mime',
        'ALTER TABLE chat_messages ADD COLUMN attachment_mime VARCHAR(100) NULL AFTER attachment_name'
    );

    $pdo->exec(
        <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS site_banners (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slot ENUM('homepage', 'promo_slider', 'ads') NOT NULL,
    title VARCHAR(200) NOT NULL DEFAULT '',
    image_path VARCHAR(255) NOT NULL,
    link_url VARCHAR(500) NOT NULL DEFAULT '',
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bn_slot (slot, sort_order),
    KEY idx_bn_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );
    ia_ensure_extended_column(
        $pdo,
        'site_banners',
        'starts_at',
        'ALTER TABLE site_banners ADD COLUMN starts_at DATETIME NULL AFTER is_active'
    );
    ia_ensure_extended_column(
        $pdo,
        'site_banners',
        'ends_at',
        'ALTER TABLE site_banners ADD COLUMN ends_at DATETIME NULL AFTER starts_at'
    );

    $pdo->exec(
        <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    $pdo->exec(
        <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    $pdo->exec(
        <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    require_once IA_ROOT . '/includes/schema_public_moderation.php';
    ia_ensure_public_moderation_schema($pdo);
}

function ia_ensure_extended_column(IaPgConnection|IaPdoConnection $pdo, string $table, string $column, string $alterSql): void
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$table, $column]);
    if ((int) $st->fetchColumn() === 0) {
        $pdo->exec($alterSql);
    }
}

function ia_seed_extended_demo_if_empty(IaPgConnection|IaPdoConnection $pdo): void
{
    try {
        $tc = (int) $pdo->query('SELECT COUNT(*) FROM chat_threads')->fetchColumn();
        $uc = (int) $pdo->query('SELECT COUNT(*) FROM platform_users')->fetchColumn();
    } catch (\PDOException) {
        return;
    }

    if ($tc > 0 || $uc < 2) {
        return;
    }

    $uids = $pdo->query('SELECT id FROM platform_users ORDER BY id ASC LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
    if (count($uids) < 2) {
        return;
    }
    $a = (int) $uids[0];
    $b = (int) $uids[1];
    $low = min($a, $b);
    $high = max($a, $b);

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO chat_threads (listing_id, user_low_id, user_high_id, is_blocked) VALUES (0, ?, ?, 0)'
        )->execute([$low, $high]);
        $tid = (int) $pdo->lastInsertId();
        $insM = $pdo->prepare('INSERT INTO chat_messages (thread_id, sender_id, body) VALUES (?, ?, ?)');
        $insM->execute([$tid, $low, 'Здравствуйте! Интересует автомобиль.']);
        $insM->execute([$tid, $high, 'Добрый день! Могу показать вечером.']);
        $lastMsgId = (int) $pdo->lastInsertId();
        $pdo->prepare(
            'INSERT INTO chat_complaints (message_id, reporter_id, reason, status) VALUES (?, ?, ?, ?)'
        )->execute([$lastMsgId, $low, 'Жалоба на сообщение (демо)', 'pending']);

        $bn = $pdo->prepare(
            'INSERT INTO site_banners (slot, title, image_path, link_url, sort_order, is_active) VALUES (?, ?, ?, ?, ?, 1)'
        );
        $bn->execute(['homepage', 'Главный баннер', 'demo/home.svg', '/', 10]);
        $bn->execute(['promo_slider', 'Акция', 'demo/promo.svg', '/', 20]);
        $bn->execute(['ads', 'Реклама', 'demo/ads.svg', '/', 30]);

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
