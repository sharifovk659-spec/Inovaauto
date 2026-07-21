<?php

declare(strict_types=1);

require_once IA_ROOT . '/includes/db_compat.php';

/** Дней без активности — объявление скрывается из каталога (архив). */
const IA_LISTING_INACTIVITY_ARCHIVE_DAYS = 30;

/** За сколько дней до скрытия отправляем предупреждение продавцу. */
const IA_LISTING_INACTIVITY_WARN_DAYS_BEFORE = 3;

/** Минимум секунд между глобальным проходом архивации (нагрузка на БД). */
const IA_LISTING_IDLE_MAINTENANCE_MIN_INTERVAL_SEC = 21600;

function ia_listing_activity_timestamp_sql(): string
{
    return 'COALESCE(last_engagement_at, updated_at, created_at)';
}

function ia_listing_touch_engagement(IaPgConnection|IaPdoConnection $pdo, int $listingId): void
{
    if ($listingId <= 0) {
        return;
    }
    if (!ia_db_column_exists($pdo, 'ad_listings', 'last_engagement_at')) {
        return;
    }
    try {
        $pdo->prepare(
            "UPDATE ad_listings SET last_engagement_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'approved'"
        )->execute([$listingId]);
    } catch (\PDOException) {
    }
}

function ia_listing_block_chat_threads_for_listing(IaPgConnection|IaPdoConnection $pdo, int $listingId): void
{
    if ($listingId <= 0) {
        return;
    }
    try {
        $pdo->prepare('UPDATE chat_threads SET is_blocked = 1 WHERE listing_id = ?')->execute([$listingId]);
    } catch (\PDOException) {
    }
}

function ia_listing_unblock_chat_threads_for_listing(IaPgConnection|IaPdoConnection $pdo, int $listingId): void
{
    if ($listingId <= 0) {
        return;
    }
    try {
        $pdo->prepare('UPDATE chat_threads SET is_blocked = 0 WHERE listing_id = ?')->execute([$listingId]);
    } catch (\PDOException) {
    }
}

/**
 * Блокирует переписки по объявлениям, которые уже не активны в каталоге.
 */
function ia_listing_block_threads_for_non_catalog_listings(IaPgConnection|IaPdoConnection $pdo): void
{
    try {
        if (ia_db_is_pgsql($pdo)) {
            $pdo->exec(
                "UPDATE chat_threads t SET is_blocked = 1
                 FROM ad_listings l
                 WHERE t.listing_id = l.id AND t.listing_id > 0 AND t.is_blocked = 0
                   AND l.status IN ('archived','sold','rejected')"
            );
        } else {
            $pdo->exec(
                "UPDATE chat_threads t
                 INNER JOIN ad_listings l ON l.id = t.listing_id
                 SET t.is_blocked = 1
                 WHERE t.listing_id > 0 AND t.is_blocked = 0
                   AND l.status IN ('archived','sold','rejected')"
            );
        }
    } catch (\PDOException) {
    }
}

/**
 * Скрывает из каталога объявления без просмотров/кликов/сообщений/правок дольше порога.
 *
 * @return int примерное число затронутых строк (PostgreSQL может вернуть -1)
 */
function ia_platform_archive_idle_approved_listings(IaPgConnection|IaPdoConnection $pdo): int
{
    if (!ia_db_column_exists($pdo, 'ad_listings', 'last_engagement_at')) {
        return 0;
    }
    $days = max(7, min(120, IA_LISTING_INACTIVITY_ARCHIVE_DAYS));
    $expr = ia_listing_activity_timestamp_sql();
    if (ia_db_is_pgsql($pdo)) {
        $where = "status = 'approved' AND {$expr} < (NOW() - INTERVAL '{$days} days')";
    } else {
        $where = "status = 'approved' AND {$expr} < DATE_SUB(NOW(), INTERVAL {$days} DAY)";
    }
    try {
        $list = $pdo->prepare("SELECT id, user_id, brand, model FROM ad_listings WHERE {$where}");
        $list->execute();
        $victims = $list->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($victims !== []) {
            require_once IA_ROOT . '/includes/platform_notifications.php';
            foreach ($victims as $row) {
                $lid = (int) ($row['id'] ?? 0);
                $uid = (int) ($row['user_id'] ?? 0);
                if ($lid > 0 && $uid > 0) {
                    ia_platform_notify_listing_reactivate_prompt(
                        $pdo,
                        $lid,
                        $uid,
                        (string) ($row['brand'] ?? ''),
                        (string) ($row['model'] ?? '')
                    );
                }
            }
        }
        $n = $pdo->prepare("UPDATE ad_listings SET status = 'archived' WHERE {$where}");
        $n->execute();

        return $n->rowCount();
    } catch (\PDOException) {
        return 0;
    }
}

function ia_platform_maybe_run_listing_idle_maintenance(IaPgConnection|IaPdoConnection $pdo): void
{
    if (!ia_db_column_exists($pdo, 'ad_listings', 'last_engagement_at')) {
        return;
    }
    require_once IA_ROOT . '/includes/site_settings.php';
    $last = trim(ia_site_setting_get($pdo, 'listing_idle_maintenance_at', ''));
    if ($last !== '') {
        $ts = strtotime($last) ?: 0;
        if ($ts > 0 && (time() - $ts) < IA_LISTING_IDLE_MAINTENANCE_MIN_INTERVAL_SEC) {
            return;
        }
    }
    ia_platform_archive_idle_approved_listings($pdo);
    ia_listing_block_threads_for_non_catalog_listings($pdo);
    try {
        ia_site_setting_set($pdo, 'listing_idle_maintenance_at', gmdate('c'));
    } catch (\Throwable) {
    }
}
