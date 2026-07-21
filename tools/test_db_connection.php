<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/db.php';
require_once IA_ROOT . '/includes/pg_helpers.php';
require_once IA_ROOT . '/config/database.php';

try {
    $info = ia_db_connection_info();
    $db = ia_db();
    $native = ia_pg();
    $v = ia_pg_fetch_one($native, 'SELECT version() AS v');
    $n = (int) ia_pg_fetch_one($native, "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'")['c'];
    echo "OK\n";
    echo 'Driver: pg_connect (pgsql extension)' . "\n";
    echo 'Target: ' . $info['host'] . '/' . $info['name'] . "\n";
    echo 'PostgreSQL: ' . ($v['v'] ?? '?') . "\n";
    echo 'Tables: ' . $n . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}
