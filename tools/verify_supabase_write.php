<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/db.php';
require_once IA_ROOT . '/config/database.php';
$conn = ia_pg_connect();

$email = 'verify_' . bin2hex(random_bytes(4)) . '@test.local';

try {
    $info = ia_db_connection_info();
    echo 'Host: ' . $info['host'] . "\n";

    $id = ia_pg_example_insert($conn, 'Verify', $email);
    ia_pg_query_params($conn, 'DELETE FROM platform_users WHERE id = $1', [$id]);

    echo "OK: wrote and deleted test user id={$id}\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}
