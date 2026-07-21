<?php

declare(strict_types=1);

function ia_ensure_billing_schema(IaPgConnection|IaPdoConnection $pdo): void
{
    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS billing_tariffs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(160) NOT NULL,
    price DECIMAL(12, 2) NOT NULL DEFAULT 0,
    duration_days INT UNSIGNED NULL,
    benefits TEXT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_billing_tariff_code (code),
    KEY idx_billing_tariff_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS billing_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    platform_user_id INT UNSIGNED NOT NULL,
    tariff_id INT UNSIGNED NULL,
    amount DECIMAL(12, 2) NOT NULL,
    status ENUM('paid', 'pending', 'failed') NOT NULL DEFAULT 'pending',
    method VARCHAR(40) NOT NULL DEFAULT 'unknown',
    note VARCHAR(255) NULL,
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_btx_user (platform_user_id),
    KEY idx_btx_tariff (tariff_id),
    KEY idx_btx_status (status),
    KEY idx_btx_created (created_at),
    CONSTRAINT fk_btx_user FOREIGN KEY (platform_user_id) REFERENCES platform_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_btx_tariff FOREIGN KEY (tariff_id) REFERENCES billing_tariffs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    ia_billing_ensure_listing_id_column($pdo);
}

function ia_billing_ensure_listing_id_column(IaPgConnection|IaPdoConnection $pdo): void
{
    if (ia_db_column_exists($pdo, 'billing_transactions', 'listing_id')) {
        return;
    }
    if (ia_db_is_pgsql($pdo)) {
        $pdo->exec('ALTER TABLE billing_transactions ADD COLUMN listing_id INTEGER NULL');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_btx_listing ON billing_transactions (listing_id)');
    } else {
        $pdo->exec('ALTER TABLE billing_transactions ADD COLUMN listing_id BIGINT UNSIGNED NULL AFTER platform_user_id');
        $pdo->exec('ALTER TABLE billing_transactions ADD KEY idx_btx_listing (listing_id)');
    }
}

/**
 * Тарифы по умолчанию + демо-платежи (если таблицы пусты).
 */
function ia_seed_billing_if_empty(IaPgConnection|IaPdoConnection $pdo): void
{
    try {
        $tc = (int) $pdo->query('SELECT COUNT(*) FROM billing_tariffs')->fetchColumn();
    } catch (\PDOException) {
        return;
    }

    if ($tc === 0) {
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO billing_tariffs (code, name, price, duration_days, benefits, sort_order) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $rows = [
                ['standard', 'Standard', 990.00, 30, 'Базовое размещение, стандартная видимость в поиске.', 10],
                ['free', 'Бесплатный (Ройгон)', 0.00, 14, 'Краткий срок публикации, ограниченные возможности.', 20],
                ['premium', 'Premium', 2490.00, 30, 'Расширенная видимость, приоритет в списках.', 30],
                ['vip', 'VIP', 4990.00, 30, 'VIP-оформление карточки, повышенное доверие покупателей.', 40],
                ['top', 'Top', 1490.00, 7, 'Закрепление в топе выдачи на неделю.', 50],
            ];
            foreach ($rows as $r) {
                $ins->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $r[5]]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    try {
        $pc = (int) $pdo->query('SELECT COUNT(*) FROM billing_transactions')->fetchColumn();
        $uc = (int) $pdo->query('SELECT COUNT(*) FROM platform_users')->fetchColumn();
    } catch (\PDOException) {
        return;
    }

    if ($pc > 0 || $uc === 0) {
        return;
    }

    $pdo->beginTransaction();
    try {
        $tariffIds = $pdo->query('SELECT code, id FROM billing_tariffs')->fetchAll(PDO::FETCH_KEY_PAIR);
        $users = $pdo->query('SELECT id FROM platform_users ORDER BY id ASC LIMIT 12')->fetchAll(PDO::FETCH_COLUMN);
        $methods = ['card', 'sbp', 'bank_transfer', 'cash'];
        $statuses = ['paid', 'paid', 'pending', 'failed'];
        $ins = $pdo->prepare(
            'INSERT INTO billing_transactions (platform_user_id, tariff_id, amount, status, method, note, paid_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $i = 0;
        foreach ($users as $uid) {
            $code = ['standard', 'free', 'premium', 'vip', 'top'][$i % 5];
            $tid = isset($tariffIds[$code]) ? (int) $tariffIds[$code] : 0;
            $amount = [990, 0, 2490, 4990, 1490][$i % 5];
            $st = $statuses[$i % 4];
            $method = $methods[$i % 4];
            $daysAgo = $i % 10;
            $created = (new DateTimeImmutable())->modify(sprintf('-%d days', $daysAgo))->format('Y-m-d H:i:s');
            $paid = $st === 'paid' ? $created : null;
            $note = 'Демо-платёж #' . ($i + 1);
            $ins->execute([(int) $uid, $tid ?: null, (float) $amount, $st, $method, $note, $paid, $created]);
            $i++;
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
