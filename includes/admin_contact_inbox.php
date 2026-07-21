<?php

declare(strict_types=1);

require_once IA_ROOT . '/includes/schema_public_moderation.php';

use InnovaAuto\Security\Csrf;

function ia_admin_contact_inbox_ensure_schema(IaPgConnection|IaPdoConnection $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    ia_ensure_public_moderation_schema($pdo);
    $done = true;
}

function ia_admin_contact_inbox_new_count(IaPgConnection|IaPdoConnection $pdo): int
{
    ia_admin_contact_inbox_ensure_schema($pdo);
    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM contact_requests WHERE status = 'new'")->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

/**
 * @return list<array<string, mixed>>
 */
function ia_admin_contact_inbox_recent(IaPgConnection|IaPdoConnection $pdo, int $limit = 8, ?string $status = 'new'): array
{
    ia_admin_contact_inbox_ensure_schema($pdo);
    $limit = ia_db_sql_limit($limit, 1, 50);
    $sql = 'SELECT id, from_name, from_email, message, status, created_at
            FROM contact_requests';
    $params = [];
    if ($status !== null && $status !== '' && in_array($status, ['new', 'reviewed', 'closed'], true)) {
        $sql .= ' WHERE status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY id DESC LIMIT ?';
    $params[] = $limit;
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return list<array<string, mixed>>
 */
function ia_admin_contact_inbox_list(IaPgConnection|IaPdoConnection $pdo, ?string $status, int $limit, int $offset): array
{
    ia_admin_contact_inbox_ensure_schema($pdo);
    $limit = ia_db_sql_limit($limit, 1, 200);
    $offset = ia_db_sql_offset($offset);
    $sql = 'SELECT id, from_name, from_email, message, status, created_at FROM contact_requests';
    $params = [];
    if ($status !== null && $status !== '' && in_array($status, ['new', 'reviewed', 'closed'], true)) {
        $sql .= ' WHERE status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY id DESC LIMIT ? OFFSET ?';
    $params[] = $limit;
    $params[] = $offset;
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function ia_admin_contact_inbox_mark_status(IaPgConnection|IaPdoConnection $pdo, int $id, string $status): bool
{
    ia_admin_contact_inbox_ensure_schema($pdo);
    if ($id <= 0 || !in_array($status, ['new', 'reviewed', 'closed'], true)) {
        return false;
    }
    $st = $pdo->prepare('UPDATE contact_requests SET status = ? WHERE id = ?');
    $st->execute([$status, $id]);

    return $st->rowCount() > 0;
}

function ia_admin_contact_inbox_status_label_ru(string $status): string
{
    return match ($status) {
        'new' => 'Новое',
        'reviewed' => 'Просмотрено',
        'closed' => 'Закрыто',
        default => $status,
    };
}

function ia_admin_contact_inbox_handle_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('contact_inbox_error', 'Сессия устарела.');
        ia_redirect(ia_admin_url('contact-messages.php'));
    }

    $pdo = ia_db();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $redirect = trim((string) ($_POST['redirect'] ?? ''));
    $back = $redirect !== '' && str_starts_with($redirect, ia_admin_url(''))
        ? $redirect
        : ia_admin_url('contact-messages.php');

    if ($action === 'mark_reviewed' && ia_admin_contact_inbox_mark_status($pdo, $id, 'reviewed')) {
        ia_flash('contact_inbox_ok', 'Обращение отмечено как просмотренное.');
    } elseif ($action === 'mark_closed' && ia_admin_contact_inbox_mark_status($pdo, $id, 'closed')) {
        ia_flash('contact_inbox_ok', 'Обращение закрыто.');
    } elseif ($action === 'mark_new' && ia_admin_contact_inbox_mark_status($pdo, $id, 'new')) {
        ia_flash('contact_inbox_ok', 'Статус: новое.');
    } else {
        ia_flash('contact_inbox_error', 'Не удалось обновить обращение.');
    }

    ia_redirect($back);
}
