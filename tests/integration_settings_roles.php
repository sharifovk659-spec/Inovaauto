<?php

/**
 * Настройки сайта, роли админов, таймаут (ТЗ §17–19) — php tests/integration_settings_roles.php
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

$st = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
);
$st->execute(['site_settings']);
ok((int) $st->fetchColumn() === 1, 'Таблица site_settings существует');

$cfg = ia_config();
ok(array_key_exists('admin_idle_seconds', $cfg['security'] ?? []), 'config: security.admin_idle_seconds');
ok(ia_admin_can(['id' => 1, 'role' => 'super_admin', 'is_active' => 1], 'settings'), 'super_admin: доступ к настройкам');
ok(ia_admin_can(['id' => 1, 'role' => 'moderator', 'is_active' => 1], 'listings'), 'moderator: объявления');
ok(!ia_admin_can(['id' => 1, 'role' => 'moderator', 'is_active' => 1], 'users'), 'moderator: нет пользователей');
ok(ia_admin_can(['id' => 1, 'role' => 'finance', 'is_active' => 1], 'billing'), 'finance: биллинг');
ok(ia_admin_can(['id' => 1, 'role' => 'support', 'is_active' => 1], 'users'), 'support: пользователи');
ok(ia_admin_can(['id' => 1, 'role' => 'support', 'is_active' => 1], 'security'), 'support: страница безопасности');

$col = $pdo->query(
    "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users' AND COLUMN_NAME = 'role'"
)->fetchColumn();
ok($col !== false && str_contains((string) $col, 'moderator'), 'Колонка role содержит moderator');

echo "OK: настройки, роли и проверки безопасности согласованы с ТЗ.\n";
