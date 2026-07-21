<?php

declare(strict_types=1);

/**
 * Примеры pg_connect + pg_query_params (Supabase PostgreSQL).
 * php tools/pg_example.php
 */
define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/db.php';
$conn = ia_pg_connect();

echo "=== SELECT (platform_users) ===\n";
$users = ia_pg_example_select_users($conn, 5);
foreach ($users as $row) {
    echo sprintf("#%s %s <%s>\n", $row['id'] ?? '', $row['name'] ?? '', $row['email'] ?? '');
}

echo "\n=== INSERT + DELETE (test) ===\n";
$email = 'pg_example_' . bin2hex(random_bytes(3)) . '@test.local';
$id = ia_pg_example_insert($conn, 'PG Example', $email);
echo "Inserted id={$id}\n";
ia_pg_query_params($conn, 'DELETE FROM platform_users WHERE id = $1', [$id]);
echo "Deleted test row.\n";

echo "\nOK — pg_connect + pg_query_params работают.\n";
