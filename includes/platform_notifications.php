<?php

declare(strict_types=1);

const IA_LISTING_PUBLISH_DAYS = 30;
const IA_LISTING_EXPIRY_WARN_DAYS = 3;

/**
 * @return list<string>
 */
function ia_platform_notification_kinds(): array
{
    return [
        'message_new',
        'listing_approved',
        'listing_rejected',
        'listing_expiring',
        'listing_sold',
        'listing_reactivate',
    ];
}

function ia_platform_notification_kind_label_ru(string $kind): string
{
    return match ($kind) {
        'message_new' => 'Новое сообщение',
        'listing_approved' => 'Объявление одобрено',
        'listing_rejected' => 'Объявление отклонено',
        'listing_expiring' => 'Низкая активность',
        'listing_sold' => 'Авто продано',
        'listing_reactivate' => 'Скрыто из каталога',
        default => 'Уведомление',
    };
}

function ia_platform_notification_push(
    IaPgConnection|IaPdoConnection $pdo,
    int $userId,
    string $kind,
    string $title,
    string $body,
    ?string $linkUrl = null,
    ?int $listingId = null,
    ?int $threadId = null,
    bool $dedupeRecent = true
): bool {
    if ($userId <= 0 || !in_array($kind, ia_platform_notification_kinds(), true)) {
        return false;
    }
    $title = trim($title);
    $body = trim($body);
    if ($title === '' || $body === '') {
        return false;
    }
    if ($dedupeRecent) {
        $sql = 'SELECT id FROM platform_notifications
                WHERE user_id = ? AND kind = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)';
        $params = [$userId, $kind];
        if ($listingId !== null && $listingId > 0) {
            $sql .= ' AND listing_id = ?';
            $params[] = $listingId;
        } elseif ($threadId !== null && $threadId > 0) {
            $sql .= ' AND thread_id = ?';
            $params[] = $threadId;
        }
        $sql .= ' LIMIT 1';
        if (ia_db_is_pgsql($pdo)) {
            $sql = str_replace('DATE_SUB(NOW(), INTERVAL 1 DAY)', "NOW() - INTERVAL '1 day'", $sql);
        }
        $st = $pdo->prepare($sql);
        $st->execute($params);
        if ($st->fetchColumn()) {
            return false;
        }
    }

    $st = $pdo->prepare(
        'INSERT INTO platform_notifications (user_id, kind, title, body, link_url, listing_id, thread_id, is_read)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0)'
    );

    return $st->execute([
        $userId,
        $kind,
        mb_substr($title, 0, 200),
        $body,
        $linkUrl !== null && $linkUrl !== '' ? mb_substr($linkUrl, 0, 500) : null,
        $listingId !== null && $listingId > 0 ? $listingId : null,
        $threadId !== null && $threadId > 0 ? $threadId : null,
    ]);
}

function ia_platform_notifications_unread_count(IaPgConnection|IaPdoConnection $pdo, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM platform_notifications WHERE user_id = ? AND is_read = 0');
    $st->execute([$userId]);

    return (int) $st->fetchColumn();
}

/**
 * @return list<array<string,mixed>>
 */
function ia_platform_notifications_for_user(IaPgConnection|IaPdoConnection $pdo, int $userId, int $limit = 50): array
{
    if ($userId <= 0) {
        return [];
    }
    $limit = ia_db_sql_limit($limit, 1, 100);
    return ia_db_fetch_all_limit(
        $pdo,
        'SELECT * FROM platform_notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC',
        [$userId],
        $limit,
        null
    );
}

function ia_platform_notification_mark_read(IaPgConnection|IaPdoConnection $pdo, int $userId, int $notificationId): void
{
    if ($userId <= 0 || $notificationId <= 0) {
        return;
    }
    $st = $pdo->prepare('UPDATE platform_notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $st->execute([$notificationId, $userId]);
}

function ia_platform_notification_mark_all_read(IaPgConnection|IaPdoConnection $pdo, int $userId): void
{
    if ($userId <= 0) {
        return;
    }
    $st = $pdo->prepare('UPDATE platform_notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $st->execute([$userId]);
}

function ia_platform_listing_expires_at_value(): string
{
    return (new \DateTimeImmutable('+' . IA_LISTING_PUBLISH_DAYS . ' days'))->format('Y-m-d H:i:s');
}

function ia_platform_notify_listing_moderation(IaPgConnection|IaPdoConnection $pdo, int $listingId, string $result, ?string $reason = null): void
{
    $st = $pdo->prepare('SELECT id, user_id, brand, model FROM ad_listings WHERE id = ? LIMIT 1');
    $st->execute([$listingId]);
    $row = $st->fetch();
    if (!$row) {
        return;
    }
    $ownerId = (int) ($row['user_id'] ?? 0);
    if ($ownerId <= 0) {
        return;
    }
    $label = trim((string) ($row['brand'] ?? '') . ' ' . (string) ($row['model'] ?? ''));
    $link = ia_public_url('car.php?id=' . $listingId);
    if ($result === 'approved') {
        ia_platform_notification_push(
            $pdo,
            $ownerId,
            'listing_approved',
            'Объявление одобрено',
            $label !== '' ? '«' . $label . '» опубликовано в каталоге.' : 'Ваше объявление опубликовано в каталоге.',
            $link,
            $listingId,
            null,
            false
        );

        return;
    }
    if ($result === 'rejected') {
        $reasonText = trim((string) $reason);
        $body = $label !== '' ? '«' . $label . '» отклонено модератором.' : 'Объявление отклонено модератором.';
        if ($reasonText !== '') {
            $body .= ' Причина: ' . $reasonText;
        }
        ia_platform_notification_push(
            $pdo,
            $ownerId,
            'listing_rejected',
            'Объявление отклонено',
            $body,
            ia_public_url('profile.php?list=archive'),
            $listingId,
            null,
            false
        );
    }
}

function ia_platform_notify_chat_message(IaPgConnection|IaPdoConnection $pdo, int $threadId, int $senderId, string $body): void
{
    $st = $pdo->prepare('SELECT listing_id, user_low_id, user_high_id FROM chat_threads WHERE id = ? LIMIT 1');
    $st->execute([$threadId]);
    $thread = $st->fetch();
    if (!$thread) {
        return;
    }
    $low = (int) ($thread['user_low_id'] ?? 0);
    $high = (int) ($thread['user_high_id'] ?? 0);
    $recipientId = $senderId === $low ? $high : $low;
    if ($recipientId <= 0 || $recipientId === $senderId) {
        return;
    }
    $listingId = (int) ($thread['listing_id'] ?? 0);
    $listingLabel = '';
    if ($listingId > 0) {
        $lst = $pdo->prepare('SELECT brand, model FROM ad_listings WHERE id = ? LIMIT 1');
        $lst->execute([$listingId]);
        $lr = $lst->fetch();
        if ($lr) {
            $listingLabel = trim((string) ($lr['brand'] ?? '') . ' ' . (string) ($lr['model'] ?? ''));
        }
    }
    $preview = trim(preg_replace('/\s+/u', ' ', $body) ?? '');
    if (mb_strlen($preview) > 140) {
        $preview = mb_substr($preview, 0, 137) . '…';
    }
    $title = 'Новое сообщение';
    $text = $listingLabel !== '' ? 'По объявлению «' . $listingLabel . '»: ' . $preview : $preview;
    ia_platform_notification_push(
        $pdo,
        $recipientId,
        'message_new',
        $title,
        $text !== '' ? $text : 'Вам пришло новое сообщение.',
        ia_public_url('messages.php?thread=' . $threadId),
        $listingId > 0 ? $listingId : null,
        $threadId,
        true
    );
}

function ia_platform_notify_listing_sold(IaPgConnection|IaPdoConnection $pdo, int $listingId, int $ownerId): void
{
    $st = $pdo->prepare('SELECT brand, model FROM ad_listings WHERE id = ? AND user_id = ? LIMIT 1');
    $st->execute([$listingId, $ownerId]);
    $row = $st->fetch();
    if (!$row) {
        return;
    }
    $label = trim((string) ($row['brand'] ?? '') . ' ' . (string) ($row['model'] ?? ''));
    ia_platform_notification_push(
        $pdo,
        $ownerId,
        'listing_sold',
        'Авто продано',
        $label !== '' ? 'Объявление «' . $label . '» отмечено как проданное и скрыто из каталога.' : 'Объявление отмечено как проданное и скрыто из каталога.',
        ia_public_url('profile.php?list=archive'),
        $listingId,
        null,
        false
    );
}

/**
 * Один раз на объявление: предложение вернуть карточку после автоархива по неактивности.
 */
function ia_platform_notify_listing_reactivate_prompt(IaPgConnection|IaPdoConnection $pdo, int $listingId, int $ownerId, string $brand, string $model): void
{
    if ($listingId <= 0 || $ownerId <= 0) {
        return;
    }
    $dup = $pdo->prepare(
        "SELECT id FROM platform_notifications WHERE user_id = ? AND kind = 'listing_reactivate' AND listing_id = ? LIMIT 1"
    );
    $dup->execute([$ownerId, $listingId]);
    if ($dup->fetchColumn()) {
        return;
    }
    $label = trim($brand . ' ' . $model);
    $body = $label !== ''
        ? 'Объявление «' . $label . '» скрыто из каталога из‑за отсутствия активности. Верните его на проверку — одна кнопка в разделе «Архив» в профиле.'
        : 'Объявление скрыто из каталога из‑за отсутствия активности. Его можно снова отправить на модерацию в профиле.';
    ia_platform_notification_push(
        $pdo,
        $ownerId,
        'listing_reactivate',
        'Вернуть объявление в каталог?',
        $body,
        ia_public_url('profile.php?list=archive'),
        $listingId,
        null,
        false
    );
}

function ia_platform_sync_seller_listing_notifications(IaPgConnection|IaPdoConnection $pdo, int $userId): void
{
    if ($userId <= 0) {
        return;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        $syncKey = 'ia_notif_sync_at';
        $lastSync = (int) ($_SESSION[$syncKey] ?? 0);
        if ($lastSync > 0 && (time() - $lastSync) < 300) {
            return;
        }
        $_SESSION[$syncKey] = time();
    }

    require_once IA_ROOT . '/includes/listing_lifecycle.php';
    require_once IA_ROOT . '/includes/db_compat.php';

    if (!ia_db_column_exists($pdo, 'ad_listings', 'last_engagement_at')) {
        $warnUntil = (new \DateTimeImmutable('+' . IA_LISTING_EXPIRY_WARN_DAYS . ' days'))->format('Y-m-d H:i:s');
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $st = $pdo->prepare(
            "SELECT id, brand, model, expires_at
             FROM ad_listings
             WHERE user_id = ? AND status = 'approved' AND expires_at IS NOT NULL
               AND expires_at > ? AND expires_at <= ?
             ORDER BY expires_at ASC"
        );
        $st->execute([$userId, $now, $warnUntil]);
        foreach ($st->fetchAll() ?: [] as $row) {
            $listingId = (int) ($row['id'] ?? 0);
            if ($listingId <= 0) {
                continue;
            }
            $label = trim((string) ($row['brand'] ?? '') . ' ' . (string) ($row['model'] ?? ''));
            $expiresAt = trim((string) ($row['expires_at'] ?? ''));
            $expiresLabel = $expiresAt;
            try {
                $expiresLabel = (new \DateTimeImmutable($expiresAt))->format('d.m.Y');
            } catch (\Throwable) {
            }
            $body = $label !== ''
                ? 'Срок публикации «' . $label . '» истекает ' . $expiresLabel . '.'
                : 'Срок публикации объявления истекает ' . $expiresLabel . '.';
            ia_platform_notification_push(
                $pdo,
                $userId,
                'listing_expiring',
                'Истекает срок объявления',
                $body,
                ia_public_url('profile.php?list=active'),
                $listingId,
                null,
                true
            );
        }

        return;
    }

    $expr = ia_listing_activity_timestamp_sql();
    $archDays = IA_LISTING_INACTIVITY_ARCHIVE_DAYS;
    $warnDays = max(1, $archDays - IA_LISTING_INACTIVITY_WARN_DAYS_BEFORE);

    if (ia_db_is_pgsql($pdo)) {
        $st = $pdo->prepare(
            "SELECT id, brand, model, {$expr} AS activity_at
             FROM ad_listings
             WHERE user_id = ? AND status = 'approved'
               AND {$expr} <= (NOW() - INTERVAL '{$warnDays} days')
               AND {$expr} > (NOW() - INTERVAL '{$archDays} days')
             ORDER BY activity_at ASC"
        );
    } else {
        $st = $pdo->prepare(
            "SELECT id, brand, model, {$expr} AS activity_at
             FROM ad_listings
             WHERE user_id = ? AND status = 'approved'
               AND {$expr} <= DATE_SUB(NOW(), INTERVAL {$warnDays} DAY)
               AND {$expr} > DATE_SUB(NOW(), INTERVAL {$archDays} DAY)
             ORDER BY activity_at ASC"
        );
    }
    $st->execute([$userId]);
    foreach ($st->fetchAll() ?: [] as $row) {
        $listingId = (int) ($row['id'] ?? 0);
        if ($listingId <= 0) {
            continue;
        }
        $label = trim((string) ($row['brand'] ?? '') . ' ' . (string) ($row['model'] ?? ''));
        $act = trim((string) ($row['activity_at'] ?? ''));
        $actLabel = $act;
        try {
            $actLabel = (new \DateTimeImmutable($act))->format('d.m.Y H:i');
        } catch (\Throwable) {
        }
        $body = $label !== ''
            ? 'У объявления «' . $label . '» давно не было просмотров, сообщений и правок (последняя активность: ' . $actLabel . '). Без активности оно скроется из каталога через несколько дней.'
            : 'У объявления давно не было активности (последняя: ' . $actLabel . '). Оно скроется из каталога без просмотров и сообщений.';
        ia_platform_notification_push(
            $pdo,
            $userId,
            'listing_expiring',
            'Низкая активность объявления',
            $body,
            ia_public_url('profile.php?list=active'),
            $listingId,
            null,
            true
        );
    }
}
