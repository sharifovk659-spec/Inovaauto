<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/pg_adapter.php';

[$s, $p] = ia_pg_prepare_query(
    'SELECT * FROM admin_users WHERE (email = :l OR username = :l)',
    ['l' => 'admin@test']
);
echo $s . PHP_EOL;
var_export($p);
echo PHP_EOL;

define('IA_ROOT', dirname(__DIR__));
require IA_ROOT . '/config/database.php';
require IA_ROOT . '/includes/auth.php';
$u = ia_find_user_by_login('admin@innovaauto.local');
echo $u !== null ? 'login query OK: ' . $u['email'] . PHP_EOL : "login query: no user\n";
