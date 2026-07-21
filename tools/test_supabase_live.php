<?php
declare(strict_types=1);
define('IA_ROOT', dirname(__DIR__));
require IA_ROOT . '/config/database.php';

try {
    $d = ia_db();
    echo 'Driver: ' . get_class($d) . PHP_EOL;
    $n = (int) $d->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'"
    )->fetchColumn();
    echo "OK — tables in Supabase: $n" . PHP_EOL;
    foreach (['platform_users', 'ad_listings', 'admin_users'] as $t) {
        try {
            $c = (int) $d->query("SELECT COUNT(*) FROM $t")->fetchColumn();
            echo "$t: $c rows" . PHP_EOL;
        } catch (Throwable $e) {
            echo "$t: error" . PHP_EOL;
        }
    }
} catch (Throwable $e) {
    echo 'FAIL: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
