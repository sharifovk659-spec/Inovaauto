<?php

declare(strict_types=1);

require_once IA_ROOT . '/includes/schema_public_moderation.php';

function ia_pub_moderation_ensure_schema(IaPgConnection|IaPdoConnection $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    ia_ensure_public_moderation_schema($pdo);
    $done = true;
}

function ia_pub_submit_listing_complaint(IaPgConnection|IaPdoConnection $pdo, int $listingId, int $reporterId, string $reason): bool
{
    ia_pub_moderation_ensure_schema($pdo);
    $reason = ia_input_long_text($reason, 2000);
    if ($listingId <= 0 || $reporterId <= 0 || $reason === '' || mb_strlen($reason) > 2000) {
        return false;
    }

    $st = $pdo->prepare('SELECT id, user_id, status FROM ad_listings WHERE id = ? LIMIT 1');
    $st->execute([$listingId]);
    $row = $st->fetch();
    if (!$row || (int) ($row['user_id'] ?? 0) === $reporterId) {
        return false;
    }
    if (!in_array((string) ($row['status'] ?? ''), ['approved'], true)) {
        return false;
    }

    $pdo->prepare(
        'INSERT INTO listing_complaints (listing_id, reporter_id, reason, status) VALUES (?, ?, ?, ?)'
    )->execute([$listingId, $reporterId, $reason, 'pending']);

    return true;
}

function ia_pub_submit_chat_complaint(IaPgConnection|IaPdoConnection $pdo, int $messageId, int $reporterId, string $reason): bool
{
    ia_pub_moderation_ensure_schema($pdo);
    $reason = ia_input_long_text($reason, 2000);
    if ($messageId <= 0 || $reporterId <= 0 || $reason === '' || mb_strlen($reason) > 2000) {
        return false;
    }

    $st = $pdo->prepare(
        'SELECT m.id, m.sender_id, t.user_low_id, t.user_high_id
         FROM chat_messages m
         INNER JOIN chat_threads t ON t.id = m.thread_id
         WHERE m.id = ? LIMIT 1'
    );
    $st->execute([$messageId]);
    $row = $st->fetch();
    if (!$row) {
        return false;
    }
    $low = (int) ($row['user_low_id'] ?? 0);
    $high = (int) ($row['user_high_id'] ?? 0);
    if ($reporterId !== $low && $reporterId !== $high) {
        return false;
    }
    if ((int) ($row['sender_id'] ?? 0) === $reporterId) {
        return false;
    }

    $dup = $pdo->prepare(
        'SELECT 1 FROM chat_complaints WHERE message_id = ? AND reporter_id = ? AND status = ? LIMIT 1'
    );
    $dup->execute([$messageId, $reporterId, 'pending']);
    if ($dup->fetchColumn()) {
        return false;
    }

    $pdo->prepare(
        'INSERT INTO chat_complaints (message_id, reporter_id, reason, status) VALUES (?, ?, ?, ?)'
    )->execute([$messageId, $reporterId, $reason, 'pending']);

    return true;
}

function ia_pub_delete_owned_listing(IaPgConnection|IaPdoConnection $pdo, int $listingId, int $ownerId): bool
{
    if ($listingId <= 0 || $ownerId <= 0) {
        return false;
    }
    $st = $pdo->prepare('SELECT id, status FROM ad_listings WHERE id = ? AND user_id = ? LIMIT 1');
    $st->execute([$listingId, $ownerId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }
    if (in_array((string) ($row['status'] ?? ''), ['sold'], true)) {
        return false;
    }

    require_once IA_ROOT . '/includes/db_compat.php';
    require_once IA_ROOT . '/includes/listing_lifecycle.php';

    if (ia_db_column_exists($pdo, 'ad_listings', 'user_soft_deleted_at')) {
        $pdo->prepare(
            "UPDATE ad_listings SET status = 'archived', user_soft_deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?"
        )->execute([$listingId, $ownerId]);
    } else {
        $pdo->prepare("UPDATE ad_listings SET status = 'archived' WHERE id = ? AND user_id = ?")->execute([$listingId, $ownerId]);
    }
    ia_listing_block_chat_threads_for_listing($pdo, $listingId);
    require_once IA_ROOT . '/includes/ia_cache.php';
    ia_cache_forget('pub_body_type_counts');

    return true;
}

function ia_pub_save_contact_request(IaPgConnection|IaPdoConnection $pdo, string $name, string $email, string $message): bool
{
    ia_pub_moderation_ensure_schema($pdo);
    $name = ia_input_text($name, 120);
    $email = ia_input_email($email);
    $message = ia_input_long_text($message, 8000);
    if ($name === '' || $message === '') {
        return false;
    }

    $pdo->prepare(
        'INSERT INTO contact_requests (from_name, from_email, message, status) VALUES (?, ?, ?, ?)'
    )->execute([$name, $email, $message, 'new']);

    return true;
}
