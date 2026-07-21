<?php

declare(strict_types=1);

/**
 * Hostinger: создать MySQL-схему + перенести данные из Supabase PostgreSQL.
 * Удалите этот файл после успешной миграции.
 *
 * URL: https://inovaauto.com/setup-mysql.php?token=YOUR_IA_MIGRATE_TOKEN&run=1
 */
define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/env_loader.php';
ia_load_dotenv(IA_ROOT . DIRECTORY_SEPARATOR . '.env');

$token = trim((string) ($_GET['token'] ?? ''));
$expected = trim((string) (ia_env('IA_MIGRATE_TOKEN') ?: 'innova2026migrate'));
$run = isset($_GET['run']);

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');

if ($token === '' || !hash_equals($expected, $token)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>403</title></head><body style="font-family:system-ui;padding:2rem">';
    echo '<h1>403 Forbidden</h1><p>Неверный token. Добавьте в <code>.env</code>: <code>IA_MIGRATE_TOKEN=ваш_секрет</code></p>';
    echo '<p>URL: <code>setup-mysql.php?token=ваш_секрет&amp;run=1</code></p></body></html>';
    exit;
}

require_once IA_ROOT . '/config/database.php';
require_once IA_ROOT . '/includes/data_migration.php';

$driver = strtolower((string) (ia_config()['db']['driver'] ?? ''));
if ($driver !== 'mysql') {
    echo '<p style="color:#b91c1c">Ошибка: в .env должно быть <code>IA_DB_DRIVER=mysql</code> (сейчас: ' . htmlspecialchars($driver) . ')</p>';
    exit;
}

$dbInfo = ia_config()['db'];
$lines = [];

function setup_log(array &$lines, string $msg): void
{
    $lines[] = $msg;
}

try {
    setup_log($lines, '1. Подключение к MySQL...');
    $mysql = ia_db();
    if (!$mysql instanceof IaPdoConnection) {
        throw new RuntimeException('Expected MySQL PDO connection');
    }
    setup_log($lines, '   OK — ' . ia_db_connection_info()['label']);

    setup_log($lines, '2. Создание таблиц (schema)...');
    ia_db_bootstrap_mysql($mysql);
    setup_log($lines, '   OK');

    if (!$run) {
        setup_log($lines, '3. Готов к миграции. Нажмите ссылку «Запустить миграцию» ниже.');
    } else {
        setup_log($lines, '3. Чтение данных из Supabase PostgreSQL (REST API)...');
        if (!ia_supabase_rest_configured()) {
            throw new RuntimeException('IA_SUPABASE_URL и IA_SUPABASE_SECRET_KEY нужны для чтения из Supabase');
        }
        setup_log($lines, '4. Копирование в MySQL...');
        $report = ia_migrate_supabase_to_mysql($mysql, true);
        setup_log($lines, '   Таблиц: ' . count($report['tables']) . ', строк: ' . $report['total']);
        foreach ($report['tables'] as $t) {
            $msg = '   • ' . $t['table'] . ': ' . $t['inserted'] . '/' . $t['source'];
            if ($t['error'] !== '') {
                $msg .= ' — ' . $t['error'];
            }
            setup_log($lines, $msg);
        }
        if (!$report['ok']) {
            setup_log($lines, '   ОШИБКА: ' . $report['error']);
        } else {
            setup_log($lines, '5. Объявления одобрены, кэш очищен (авто).');
            setup_log($lines, '6. Миграция завершена успешно.');
        }
    }
} catch (Throwable $e) {
    setup_log($lines, 'ОШИБКА: ' . $e->getMessage());
}

$baseUrl = rtrim((string) (ia_config()['app']['base_url'] ?? ''), '/');
if ($baseUrl === '') {
    $baseUrl = 'https://inovaauto.com';
}
$selfToken = rawurlencode($token);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>InnovaAuto — MySQL Setup</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 720px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; color: #111; }
        pre { background: #f1f5f9; padding: 1rem; border-radius: 8px; overflow-x: auto; font-size: 0.88rem; }
        .box { background: #eff6ff; border: 1px solid #bfdbfe; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
        a.btn { display: inline-block; background: #2563eb; color: #fff; padding: 0.55rem 1rem; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 0.25rem 0.25rem 0.25rem 0; }
        a.btn--green { background: #15803d; }
        code { background: #e2e8f0; padding: 0.1rem 0.35rem; border-radius: 4px; }
        h1 { font-size: 1.35rem; }
    </style>
</head>
<body>
    <h1>InnovaAuto — MySQL Hostinger</h1>

    <div class="box">
        <strong>MySQL (hPanel):</strong><br>
        Host: <code><?= htmlspecialchars((string) ($dbInfo['host'] ?? 'localhost')) ?></code><br>
        Database: <code><?= htmlspecialchars((string) ($dbInfo['name'] ?? '')) ?></code><br>
        User: <code><?= htmlspecialchars((string) ($dbInfo['user'] ?? '')) ?></code><br>
        Password: <code>(из .env IA_DB_PASS)</code>
    </div>

    <pre><?= htmlspecialchars(implode("\n", $lines)) ?></pre>

    <p>
        <?php if (!$run): ?>
            <a class="btn btn--green" href="setup-mysql.php?token=<?= $selfToken ?>&amp;run=1">▶ Запустить миграцию Supabase → MySQL</a>
        <?php endif; ?>
        <a class="btn" href="<?= htmlspecialchars($baseUrl) ?>/">Открыть сайт</a>
        <a class="btn" href="<?= htmlspecialchars($baseUrl) ?>/health.php">Проверка БД</a>
        <a class="btn" href="<?= htmlspecialchars($baseUrl) ?>/admin/">Админ-панель</a>
    </p>

    <p style="color:#64748b;font-size:0.85rem">После успешной миграции удалите <code>setup-mysql.php</code> с сервера.</p>
</body>
</html>
