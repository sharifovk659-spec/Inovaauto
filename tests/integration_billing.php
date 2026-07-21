<?php

/**
 * Интеграционная проверка биллинга с реальной БД (запуск: php tests/integration_billing.php).
 * Требуется корректный config/local.php / переменные окружения для MySQL.
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

$nTar = (int) $pdo->query('SELECT COUNT(*) FROM billing_tariffs')->fetchColumn();
ok($nTar >= 5, 'В базе должно быть не менее 5 тарифов после сида');

$codes = $pdo->query('SELECT code FROM billing_tariffs')->fetchAll(PDO::FETCH_COLUMN);
foreach (['standard', 'free', 'premium', 'vip', 'top'] as $req) {
    ok(in_array($req, $codes, true), 'Обязательный код тарифа присутствует: ' . $req);
}

$rows = $pdo->query(
    'SELECT t.id, t.status, u.email FROM billing_transactions t JOIN platform_users u ON u.id = t.platform_user_id LIMIT 1'
)->fetchAll();
if (count($rows) > 0) {
    ok(in_array($rows[0]['status'], ['paid', 'pending', 'failed'], true), 'Статус платежа из ENUM');
}

echo "OK: биллинг в базе согласован с ТЗ (тарифы + при необходимости платежи).\n";
