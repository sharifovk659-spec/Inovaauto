<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

/**
 * @return list<array<string,mixed>>
 */
function ia_moderation_threads_list(IaPgConnection|IaPdoConnection $pdo): array
{
    $sql = 'SELECT t.*, u1.name AS n1, u1.email AS e1, u2.name AS n2, u2.email AS e2,
            (SELECT COUNT(*) FROM chat_messages m WHERE m.thread_id = t.id) AS msg_count,
            (SELECT COUNT(*) FROM chat_complaints c
              INNER JOIN chat_messages m2 ON m2.id = c.message_id
              WHERE m2.thread_id = t.id AND c.status = \'pending\') AS open_reports
            FROM chat_threads t
            INNER JOIN platform_users u1 ON u1.id = t.user_low_id
            INNER JOIN platform_users u2 ON u2.id = t.user_high_id
            ORDER BY t.updated_at DESC';
    return $pdo->query($sql)->fetchAll() ?: [];
}

/**
 * @return list<array<string,mixed>>
 */
function ia_moderation_messages_for_thread(IaPgConnection|IaPdoConnection $pdo, int $threadId): array
{
    $sql = 'SELECT m.*, u.name AS sender_name, u.email AS sender_email
            FROM chat_messages m
            INNER JOIN platform_users u ON u.id = m.sender_id
            WHERE m.thread_id = ?
            ORDER BY m.id ASC';
    $st = $pdo->prepare($sql);
    $st->execute([$threadId]);
    return $st->fetchAll() ?: [];
}

function ia_moderation_thread_by_id(IaPgConnection|IaPdoConnection $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM chat_threads WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

/**
 * @return list<array<string,mixed>>
 */
function ia_moderation_complaints_list(IaPgConnection|IaPdoConnection $pdo): array
{
    $sql = 'SELECT c.*, m.body AS msg_body, m.thread_id,
            ru.name AS reporter_name, ru.email AS reporter_email,
            su.name AS sender_name
            FROM chat_complaints c
            INNER JOIN chat_messages m ON m.id = c.message_id
            INNER JOIN platform_users ru ON ru.id = c.reporter_id
            INNER JOIN platform_users su ON su.id = m.sender_id
            ORDER BY CASE WHEN c.status = \'pending\' THEN 0 ELSE 1 END, c.id DESC';
    return $pdo->query($sql)->fetchAll() ?: [];
}

function ia_moderation_threads_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('mod_error', 'Сессия устарела.');
        ia_redirect(ia_admin_url('chat-threads.php'));
    }
    $pdo = ia_db();
    $action = (string) ($_POST['action'] ?? '');
    $tid = ia_post_int('thread_id');
    if ($tid <= 0) {
        ia_redirect(ia_admin_url('chat-threads.php'));
    }
    if ($action === 'toggle_block') {
        $pdo->prepare('UPDATE chat_threads SET is_blocked = CASE WHEN is_blocked=1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$tid]);
        ia_flash('mod_ok', 'Статус блокировки чата изменён.');
        ia_redirect(ia_admin_url('chat-thread.php?id=' . $tid));
    }
    ia_redirect(ia_admin_url('chat-threads.php'));
}

function ia_moderation_complaints_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('mod_error', 'Сессия устарела.');
        ia_redirect(ia_admin_url('chat-reports.php'));
    }
    $pdo = ia_db();
    $action = (string) ($_POST['action'] ?? '');
    $cid = ia_post_int('complaint_id');
    $note = ia_input_long_text($_POST['admin_note'] ?? '', 2000);
    if ($cid <= 0) {
        ia_redirect(ia_admin_url('chat-reports.php'));
    }
    if ($action === 'review') {
        $pdo->prepare(
            'UPDATE chat_complaints SET status = \'reviewed\', admin_note = ?, reviewed_at = NOW() WHERE id = ?'
        )->execute([$note === '' ? null : $note, $cid]);
        ia_flash('mod_ok', 'Жалоба помечена как рассмотренная.');
    } elseif ($action === 'dismiss') {
        $pdo->prepare(
            'UPDATE chat_complaints SET status = \'dismissed\', admin_note = ?, reviewed_at = NOW() WHERE id = ?'
        )->execute([$note === '' ? null : $note, $cid]);
        ia_flash('mod_ok', 'Жалоба отклонена.');
    }
    ia_redirect(ia_admin_url('chat-reports.php'));
}

/**
 * @return list<array<string,mixed>>
 */
function ia_moderation_listing_complaints_list(IaPgConnection|IaPdoConnection $pdo): array
{
    $sql = 'SELECT c.*, l.brand, l.model, l.status AS listing_status,
            ru.name AS reporter_name, ru.email AS reporter_email
            FROM listing_complaints c
            INNER JOIN ad_listings l ON l.id = c.listing_id
            INNER JOIN platform_users ru ON ru.id = c.reporter_id
            ORDER BY CASE WHEN c.status = \'pending\' THEN 0 ELSE 1 END, c.id DESC';

    return $pdo->query($sql)->fetchAll() ?: [];
}

function ia_moderation_listing_complaints_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('mod_error', 'Сессия устарела.');
        ia_redirect(ia_admin_url('listing-reports.php'));
    }
    $pdo = ia_db();
    $action = (string) ($_POST['action'] ?? '');
    $cid = ia_post_int('complaint_id');
    $note = ia_input_long_text($_POST['admin_note'] ?? '', 2000);
    if ($cid <= 0) {
        ia_redirect(ia_admin_url('listing-reports.php'));
    }
    if ($action === 'review') {
        $pdo->prepare(
            'UPDATE listing_complaints SET status = \'reviewed\', admin_note = ?, reviewed_at = NOW() WHERE id = ?'
        )->execute([$note === '' ? null : $note, $cid]);
        ia_flash('mod_ok', 'Жалоба на объявление рассмотрена.');
    } elseif ($action === 'dismiss') {
        $pdo->prepare(
            'UPDATE listing_complaints SET status = \'dismissed\', admin_note = ?, reviewed_at = NOW() WHERE id = ?'
        )->execute([$note === '' ? null : $note, $cid]);
        ia_flash('mod_ok', 'Жалоба на объявление отклонена.');
    }
    ia_redirect(ia_admin_url('listing-reports.php'));
}
