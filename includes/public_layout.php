<?php

declare(strict_types=1);

require_once IA_ROOT . '/includes/public_queries.php';

/**
 * @return array{fav_count:int,compare_count:int,notification_unread:int,chat_unread:int}
 */
function ia_pub_user_badge_counts(IaPgConnection|IaPdoConnection $pdo, int $userId): array
{
    if ($userId <= 0) {
        return ['fav_count' => 0, 'compare_count' => 0, 'notification_unread' => 0];
    }

    try {
        $st = $pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM user_favorites f
                 INNER JOIN ad_listings l ON l.id = f.listing_id
                 WHERE f.user_id = ? AND l.status = 'approved') AS fav_count,
                (SELECT COUNT(*) FROM user_compare WHERE user_id = ?) AS compare_count,
                (SELECT COUNT(*) FROM platform_notifications WHERE user_id = ? AND is_read = 0) AS notification_unread"
        );
        $st->execute([$userId, $userId, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (\PDOException) {
        return ['fav_count' => 0, 'compare_count' => 0, 'notification_unread' => 0];
    }

    return [
        'fav_count' => (int) ($row['fav_count'] ?? 0),
        'compare_count' => (int) ($row['compare_count'] ?? 0),
        'notification_unread' => (int) ($row['notification_unread'] ?? 0),
    ];
}

function ia_pub_chat_unread_count(IaPgConnection|IaPdoConnection $pdo, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    $sql = 'SELECT t.id,
            CASE WHEN t.user_low_id = ? THEN t.last_seen_low_at ELSE t.last_seen_high_at END AS my_last_seen_at,
            (SELECT m.sender_id FROM chat_messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_sender_id,
            (SELECT m.created_at FROM chat_messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_at
            FROM chat_threads t
            WHERE t.user_low_id = ? OR t.user_high_id = ?';

    try {
        $st = $pdo->prepare($sql);
        $st->execute([$userId, $userId, $userId]);
        $threads = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\PDOException) {
        return 0;
    }

    $seenMap = isset($_SESSION['chat_seen']) && is_array($_SESSION['chat_seen']) ? $_SESSION['chat_seen'] : [];

    return ia_pub_unread_threads_count($threads, $userId, $seenMap);
}

/**
 * Shared header/footer/tabbar counts — one batch per page (short session cache).
 *
 * @return array{fav_count:int,compare_count:int,notification_unread:int,chat_unread:int}
 */
function ia_pub_layout_state(IaPgConnection|IaPdoConnection $pdo, ?array $cu): array
{
    static $requestCache = null;
    if ($requestCache !== null) {
        return $requestCache;
    }

    $userId = $cu !== null ? (int) ($cu['id'] ?? 0) : 0;
    $sessionKey = 'ia_layout_state';
    $ttl = max(5, ia_cache_ttl('layout_badges', 20));

    if ($userId > 0 && session_status() === PHP_SESSION_ACTIVE) {
        $cached = $_SESSION[$sessionKey] ?? null;
        if (
            is_array($cached)
            && (int) ($cached['uid'] ?? 0) === $userId
            && (time() - (int) ($cached['ts'] ?? 0)) < $ttl
            && is_array($cached['data'] ?? null)
        ) {
            $requestCache = $cached['data'];

            return $requestCache;
        }
    }

    $state = [
        'fav_count' => 0,
        'compare_count' => count(ia_pub_compare_ids($pdo, $userId)),
        'notification_unread' => 0,
        'chat_unread' => 0,
    ];

    if ($userId > 0) {
        $badges = ia_pub_user_badge_counts($pdo, $userId);
        $state['fav_count'] = $badges['fav_count'];
        $state['compare_count'] = $badges['compare_count'];
        $state['notification_unread'] = $badges['notification_unread'];
        $state['chat_unread'] = ia_pub_chat_unread_count($pdo, $userId);
    }

    if ($userId > 0 && session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION[$sessionKey] = [
            'uid' => $userId,
            'ts' => time(),
            'data' => $state,
        ];
    }

    $requestCache = $state;

    return $requestCache;
}
