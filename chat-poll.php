<?php

declare(strict_types=1);

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/public_queries.php';

header('Content-Type: application/json; charset=utf-8');

$cu = ia_platform_current_user();
if ($cu === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = ia_db();
$uid = (int) $cu['id'];
$unread = ia_pub_chat_unread_count($pdo, $uid);

$threadId = ia_get_int('thread_id');
$lastMessageId = 0;
if ($threadId > 0) {
    $thread = ia_pub_thread_for_participant($pdo, $threadId, $uid);
    if ($thread !== null) {
        ia_pub_mark_thread_seen($pdo, $threadId, $uid);
        $lastMessageId = ia_pub_thread_last_message_id($pdo, $threadId);
    }
}

echo json_encode([
    'ok' => true,
    'unread_count' => $unread,
    'thread_id' => $threadId,
    'last_message_id' => $lastMessageId,
], JSON_UNESCAPED_UNICODE);

