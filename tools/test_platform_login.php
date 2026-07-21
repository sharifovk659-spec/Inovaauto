<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/env_loader.php';
ia_load_dotenv(IA_ROOT . '/.env');
require_once IA_ROOT . '/db.php';
require_once IA_ROOT . '/config/database.php';
require_once IA_ROOT . '/includes/helpers.php';
require_once IA_ROOT . '/includes/public_auth.php';

$config = ia_config();
session_name($config['platform_session']['name']);
session_start();

echo "=== platform_users sample ===\n";
$st = ia_db()->query('SELECT id, email, status, password_hash IS NOT NULL AS has_pw FROM platform_users ORDER BY id LIMIT 5');
foreach ($st->fetchAll() as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== CSRF token persistence ===\n";
require_once IA_ROOT . '/src/Security/Csrf.php';
$t1 = InnovaAuto\Security\Csrf::token();
$t2 = InnovaAuto\Security\Csrf::token();
echo ($t1 === $t2 ? 'OK same token in session' : 'FAIL tokens differ') . "\n";
echo 'Token: ' . substr($t1, 0, 16) . "...\n";

echo "\n=== Login simulation ===\n";
$email = '';
$st = ia_db()->query("SELECT email FROM platform_users WHERE status = 'active' AND password_hash IS NOT NULL ORDER BY id LIMIT 1");
$email = (string) ($st->fetchColumn() ?: '');
if ($email === '') {
    echo "No active user with password found.\n";
    exit(0);
}
echo "Testing user: {$email}\n";
$u = ia_platform_find_by_email($email);
if ($u === null) {
    echo "User not found.\n";
    exit(1);
}
ia_platform_login_user($u);
$cu = ia_platform_current_user();
echo ($cu !== null ? 'OK logged in as id=' . $cu['id'] : 'FAIL login') . "\n";
echo 'Session platform_user_id=' . (int) ($_SESSION['platform_user_id'] ?? 0) . "\n";
