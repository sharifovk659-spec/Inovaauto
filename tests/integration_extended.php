<?php

/**
 * Интеграционная проверка расширенной схемы (чат, баннеры, рассылки) — php tests/integration_extended.php
 */

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));

require_once IA_ROOT . '/includes/bootstrap.php';

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

try {
    $pdo = ia_db();
} catch (Throwable $e) {
    fwrite(STDERR, 'SKIP: нет подключения к БД: ' . $e->getMessage() . "\n");
    exit(0);
}

$stTable = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
);
foreach (
    [
        'chat_threads',
        'chat_messages',
        'chat_complaints',
        'site_banners',
        'notification_campaigns',
        'notification_deliveries',
    ] as $t
) {
    $stTable->execute([$t]);
    $n = (int) $stTable->fetchColumn();
    ok($n === 1, 'Таблица существует: ' . $t);
}

$tc = (int) $pdo->query('SELECT COUNT(*) FROM chat_threads')->fetchColumn();
$uc = (int) $pdo->query('SELECT COUNT(*) FROM platform_users')->fetchColumn();
if ($tc > 0 && $uc >= 2) {
    $row = $pdo->query(
        'SELECT t.user_low_id, t.user_high_id FROM chat_threads t LIMIT 1'
    )->fetch();
    ok($row !== false, 'Выборка chat_threads');
    if ($row) {
        ok(
            (int) $row['user_low_id'] !== (int) $row['user_high_id'],
            'В треде два разных участника'
        );
    }
}

$bn = (int) $pdo->query('SELECT COUNT(*) FROM site_banners')->fetchColumn();
ok($bn >= 0, 'site_banners доступна');

echo "OK: расширенная схема (§13–16) присутствует в БД.\n";
