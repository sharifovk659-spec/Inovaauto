<?php

declare(strict_types=1);

require_once IA_ROOT . '/includes/db_compat.php';

function ia_ensure_public_moderation_schema(IaPgConnection|IaPdoConnection $pdo): void
{
    if (ia_db_is_pgsql($pdo)) {
        $pdo->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS platform_password_resets (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES platform_users(id) ON DELETE CASCADE,
    token_hash CHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ppr_user ON platform_password_resets (user_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ppr_expires ON platform_password_resets (expires_at)');

        $pdo->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS contact_requests (
    id BIGSERIAL PRIMARY KEY,
    from_name VARCHAR(120) NOT NULL,
    from_email VARCHAR(255) NOT NULL DEFAULT '',
    message TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cr_status ON contact_requests (status, created_at)');

        $pdo->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS listing_complaints (
    id SERIAL PRIMARY KEY,
    listing_id INTEGER NOT NULL REFERENCES ad_listings(id) ON DELETE CASCADE,
    reporter_id INTEGER NOT NULL REFERENCES platform_users(id) ON DELETE CASCADE,
    reason TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    admin_note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL
)
SQL
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_lc_status ON listing_complaints (status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_lc_listing ON listing_complaints (listing_id)');

        return;
    }

    $pdo->exec(
        <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS contact_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    from_name VARCHAR(120) NOT NULL,
    from_email VARCHAR(255) NOT NULL DEFAULT '',
    message TEXT NOT NULL,
    status ENUM('new', 'reviewed', 'closed') NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cr_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    $pdo->exec(
        <<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );
}
