<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

/**
 * @return list<int>
 */
function ia_notification_recipient_ids(IaPgConnection|IaPdoConnection $pdo, string $audience, ?string $groupKey, ?int $targetUserId): array
{
    if ($audience === 'all') {
        $st = $pdo->prepare('SELECT id FROM platform_users');
        $st->execute();

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
    if ($audience === 'single' && $targetUserId !== null && $targetUserId > 0) {
        $st = $pdo->prepare('SELECT id FROM platform_users WHERE id = ? LIMIT 1');
        $st->execute([$targetUserId]);
        $id = $st->fetchColumn();

        return $id ? [(int) $id] : [];
    }
    if ($audience === 'group' && $groupKey !== null && $groupKey !== '') {
        if ($groupKey === 'dealer') {
            $st = $pdo->prepare("SELECT id FROM platform_users WHERE account_type = 'dealer'");
            $st->execute();

            return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        }
        if ($groupKey === 'private') {
            $st = $pdo->prepare("SELECT id FROM platform_users WHERE account_type = 'private'");
            $st->execute();

            return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        }
        if ($groupKey === 'active') {
            $st = $pdo->prepare("SELECT id FROM platform_users WHERE status = 'active'");
            $st->execute();

            return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        }
    }

    return [];
}

function ia_notification_send_deliveries(IaPgConnection|IaPdoConnection $pdo, int $campaignId): void
{
    $st = $pdo->prepare('SELECT * FROM notification_campaigns WHERE id = ?');
    $st->execute([$campaignId]);
    $camp = $st->fetch();
    if (!$camp) {
        return;
    }

    $delSt = $pdo->prepare(
        'SELECT id, platform_user_id FROM notification_deliveries WHERE campaign_id = ? AND status = \'queued\' ORDER BY id ASC'
    );
    $delSt->execute([$campaignId]);
    $delRows = $delSt->fetchAll() ?: [];
    $from = (string) ia_config()['mail']['from'];
    $channel = (string) $camp['channel'];

    $upd = $pdo->prepare(
        'UPDATE notification_deliveries SET status = ?, detail = ?, sent_at = NOW() WHERE id = ?'
    );

    foreach ($delRows as $dr) {
        $did = (int) $dr['id'];
        $platformUserId = (int) $dr['platform_user_id'];

        $uSt = $pdo->prepare('SELECT email, name FROM platform_users WHERE id = ?');
        $uSt->execute([$platformUserId]);
        $u = $uSt->fetch();
        if (!$u) {
            $upd->execute(['skipped', 'Пользователь не найден', $did]);

            continue;
        }

        if ($channel === 'email') {
            $to = (string) $u['email'];
            $subj = (string) $camp['subject'];
            $body = (string) $camp['body'];
            $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nFrom: {$from}\r\n";
            $ok = $to !== '' && @mail(
                $to,
                '=?UTF-8?B?' . base64_encode($subj) . '?=',
                $body,
                $headers
            );
            $upd->execute([$ok ? 'sent' : 'failed', $ok ? null : 'mail() вернул false', $did]);
        } elseif ($channel === 'push') {
            $upd->execute(['sent', 'Push: запись в журнале (подключите FCM/APNs)', $did]);
        } else {
            $upd->execute(['sent', 'SMS: запись в журнале (подключите SMS-провайдера)', $did]);
        }
    }
}

function ia_admin_notifications_handle_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('notif_error', 'Сессия устарела.');
        ia_redirect(ia_admin_url('notifications.php'));
    }

    $pdo = ia_db();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'send') {
        $channel = ia_input_enum($_POST['channel'] ?? '', ['push', 'sms', 'email']);
        $audience = ia_input_enum($_POST['audience'] ?? '', ['all', 'group', 'single']);
        $groupKey = ia_post_text('group_key', 64);
        $targetUserId = ia_post_int('target_user_id');
        $subject = ia_post_text('subject', 200);
        $body = ia_post_text('body', 8000, true);
        $adminId = (int) (ia_current_user()['id'] ?? 0);

        if (!in_array($channel, ['push', 'sms', 'email'], true) || !in_array($audience, ['all', 'group', 'single'], true)) {
            ia_flash('notif_error', 'Некорректные параметры рассылки.');
            ia_redirect(ia_admin_url('notifications.php'));
        }
        if ($channel === 'email' && $subject === '') {
            ia_flash('notif_error', 'Укажите тему письма.');
            ia_redirect(ia_admin_url('notifications.php'));
        }
        if ($body === '') {
            ia_flash('notif_error', 'Введите текст сообщения.');
            ia_redirect(ia_admin_url('notifications.php'));
        }

        $gk = $groupKey === '' ? null : $groupKey;
        $tid = $audience === 'single' && $targetUserId > 0 ? $targetUserId : null;

        $recipients = ia_notification_recipient_ids($pdo, $audience, $gk, $tid);
        if (count($recipients) === 0) {
            ia_flash('notif_error', 'Нет получателей по выбранным условиям.');
            ia_redirect(ia_admin_url('notifications.php'));
        }

        $insC = $pdo->prepare(
            'INSERT INTO notification_campaigns (channel, audience, subject, body, target_user_id, group_key, admin_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $insC->execute([
            $channel,
            $audience,
            $subject,
            $body,
            $tid,
            $audience === 'group' ? $gk : null,
            $adminId > 0 ? $adminId : null,
        ]);
        $campaignId = (int) $pdo->lastInsertId();

        $insD = $pdo->prepare(
            'INSERT INTO notification_deliveries (campaign_id, platform_user_id, status) VALUES (?, ?, \'queued\')'
        );
        foreach ($recipients as $rid) {
            $insD->execute([$campaignId, $rid]);
        }

        ia_notification_send_deliveries($pdo, $campaignId);

        ia_flash('notif_ok', 'Рассылка создана и обработана для ' . count($recipients) . ' получателей.');
        ia_redirect(ia_admin_url('notifications.php'));
    }
}

/**
 * @return list<array<string,mixed>>
 */
function ia_admin_notifications_recent_campaigns(IaPgConnection|IaPdoConnection $pdo, int $limit = 30): array
{
    return ia_db_fetch_all_limit(
        $pdo,
        'SELECT c.*, u.username AS admin_username
            FROM notification_campaigns c
            LEFT JOIN admin_users u ON u.id = c.admin_user_id
            ORDER BY c.id DESC',
        [],
        $limit,
        null
    );
}
