<?php

declare(strict_types=1);

/**
 * Hostinger: одобрить объявления + очистить кэш + проверка БД.
 * Удалите после использования.
 *
 * https://inovaauto.com/fix-site.php?token=YOUR_IA_MIGRATE_TOKEN
 */
define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/env_loader.php';
ia_load_dotenv(IA_ROOT . DIRECTORY_SEPARATOR . '.env');

$token = trim((string) ($_GET['token'] ?? ''));
$expected = trim((string) (ia_env('IA_MIGRATE_TOKEN') ?: 'innova2026migrate'));

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');

if ($token === '' || !hash_equals($expected, $token)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>403</title></head><body><h1>403</h1><p>token?</p></body></html>';
    exit;
}

require_once IA_ROOT . '/config/database.php';
require_once IA_ROOT . '/includes/data_migration.php';
require_once IA_ROOT . '/includes/db_compat.php';
require_once IA_ROOT . '/includes/schema_platform.php';

$lines = [];
$stats = ['listings_total' => 0, 'listings_approved' => 0, 'brands' => 0, 'users' => 0, 'media' => 0, 'listings_vip' => 0, 'listings_top' => 0];

$requiredFiles = [
    'includes/helpers.php',
    'includes/partials/listing-card-badges.php',
    'includes/partials/top-badge.php',
    'assets/site.css',
    'catalog.php',
    'index.php',
];

try {
    $db = ia_db();
    if (!$db instanceof IaPdoConnection) {
        throw new RuntimeException('MySQL required (IA_DB_DRIVER=mysql)');
    }

    $lines[] = 'DB: ' . ia_db_connection_info()['label'];
    $lines[] = 'Base URL: ' . (ia_config()['app']['base_url'] ?? '');
    $lines[] = '';

    $lines[] = '--- Файлы для TOP/VIP (public_html) ---';
    foreach ($requiredFiles as $rel) {
        $abs = IA_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $lines[] = (is_file($abs) ? 'OK' : 'MISSING') . '  ' . $rel;
    }
    $lines[] = '';

    ia_ensure_platform_schema($db);
    $lines[] = 'Схема platform: проверена (is_vip, is_top и др.)';

    $hasTopCol = ia_db_column_exists($db, 'ad_listings', 'is_top');
    $hasVipCol = ia_db_column_exists($db, 'ad_listings', 'is_vip');
    $lines[] = 'Колонка is_top: ' . ($hasTopCol ? 'есть' : 'НЕТ — загрузите sql/hostinger_full_install.sql или включите IA_DB_PLATFORM_SCHEMA=true');
    $lines[] = 'Колонка is_vip: ' . ($hasVipCol ? 'есть' : 'НЕТ');

    $post = ia_migrate_post_process_mysql($db);
    $lines[] = 'Статусов объявлений обновлено: ' . $post['listings_approved'];
    $lines[] = 'Кэш удалён (файлов): ' . $post['cache_files_removed'];
    $lines[] = '';

    $stats['listings_total'] = (int) $db->query('SELECT COUNT(*) FROM ad_listings')->fetchColumn();
    $stats['listings_approved'] = (int) $db->query("SELECT COUNT(*) FROM ad_listings WHERE status = 'approved'")->fetchColumn();
    if ($hasVipCol) {
        $stats['listings_vip'] = (int) $db->query("SELECT COUNT(*) FROM ad_listings WHERE status = 'approved' AND is_vip = 1")->fetchColumn();
    }
    if ($hasTopCol) {
        $stats['listings_top'] = (int) $db->query("SELECT COUNT(*) FROM ad_listings WHERE status = 'approved' AND is_top = 1")->fetchColumn();
    }
    $stats['brands'] = (int) $db->query('SELECT COUNT(*) FROM car_brands')->fetchColumn();
    $stats['users'] = (int) $db->query('SELECT COUNT(*) FROM platform_users')->fetchColumn();
    $stats['media'] = (int) $db->query('SELECT COUNT(*) FROM ad_listing_media')->fetchColumn();

    $lines[] = 'Объявления: ' . $stats['listings_approved'] . ' approved / ' . $stats['listings_total'] . ' total';
    $lines[] = 'С VIP (золотой значок): ' . $stats['listings_vip'];
    $lines[] = 'С TOP (фиолетовый значок): ' . $stats['listings_top'];
    if ($stats['listings_top'] === 0) {
        $lines[] = 'Подсказка: TOP не показывается, если is_top=0. В админке: Объявления → редактировать → «Закрепить в топе».';
    }
    $lines[] = 'Бренды: ' . $stats['brands'];
    $lines[] = 'Пользователи: ' . $stats['users'];
    $lines[] = 'Фото (media): ' . $stats['media'];
    $lines[] = '';
    $lines[] = 'STATUS: OK';
} catch (Throwable $e) {
    $lines[] = 'ERROR: ' . $e->getMessage();
}

$baseUrl = rtrim((string) (ia_config()['app']['base_url'] ?? 'https://inovaauto.com'), '/');
if ($baseUrl === '') {
    $baseUrl = 'https://inovaauto.com';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>InnovaAuto — Fix</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 640px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        pre { background: #f1f5f9; padding: 1rem; border-radius: 8px; }
        a { display: inline-block; margin: 0.25rem 0.5rem 0.25rem 0; color: #2563eb; font-weight: 600; }
    </style>
</head>
<body>
    <h1>InnovaAuto — исправление сайта</h1>
    <pre><?= htmlspecialchars(implode("\n", $lines)) ?></pre>
    <p>
        <a href="<?= htmlspecialchars($baseUrl) ?>/">Главная</a>
        <a href="<?= htmlspecialchars($baseUrl) ?>/catalog.php">Каталог</a>
        <a href="<?= htmlspecialchars($baseUrl) ?>/admin/">Админ</a>
    </p>
    <p style="color:#64748b;font-size:0.85rem">Удалите <code>fix-site.php</code> после проверки.</p>
</body>
</html>
