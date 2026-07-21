<?php

declare(strict_types=1);

/**
 * Hostinger MySQL check — delete after debugging.
 */
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/env_loader.php';
ia_load_dotenv(IA_ROOT . DIRECTORY_SEPARATOR . '.env');

echo "PHP: " . PHP_VERSION . "\n";
echo 'pdo_mysql: ' . (extension_loaded('pdo_mysql') ? 'yes' : 'no') . "\n";
echo 'IA_DB_DRIVER: ' . (string) (ia_env('IA_DB_DRIVER') ?: '(not set)') . "\n";
echo 'IA_DB_HOST: ' . (string) (ia_env('IA_DB_HOST') ?: '(not set)') . "\n";
echo 'IA_DB_NAME: ' . (string) (ia_env('IA_DB_NAME') ?: '(not set)') . "\n";
echo 'IA_DB_USER: ' . (string) (ia_env('IA_DB_USER') ?: '(not set)') . "\n";

try {
    require_once IA_ROOT . '/config/database.php';
    $db = ia_db();
    $info = ia_db_connection_info();
    echo 'DB label: ' . ($info['label'] ?? '') . "\n";
    $one = $db->query('SELECT 1 AS ok')->fetchColumn();
    echo 'DB query: ' . ($one === false ? 'fail' : 'ok') . "\n";
    echo "STATUS: OK\n";
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    echo "STATUS: FAIL\n";
}
