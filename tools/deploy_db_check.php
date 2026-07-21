<?php

declare(strict_types=1);

/**
 * Deploy gate: PHP syntax + DB SELECT 1 (no secrets in output).
 * Exit 0 = OK, 1 = fail.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/public_bootstrap.php';

try {
    $db = ia_db();
    $row = $db->query('SELECT 1 AS ok')->fetch();
    if ((int) ($row['ok'] ?? 0) !== 1) {
        fwrite(STDERR, "DB check failed: unexpected result\n");
        exit(1);
    }
    echo "DB_OK\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'DB check failed: ' . $e->getMessage() . "\n");
    exit(1);
}
