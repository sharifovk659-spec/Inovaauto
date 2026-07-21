<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/env_loader.php';
ia_load_dotenv(IA_ROOT . '/.env');
require_once IA_ROOT . '/db.php';
require_once IA_ROOT . '/config/database.php';

$email = getenv('IA_TEST_LOGIN_EMAIL') ?: 'test@gmail.com';
$password = getenv('IA_TEST_LOGIN_PASSWORD') ?: '';
if ($password === '') {
    echo "Skip: set IA_TEST_LOGIN_PASSWORD to run full login test.\n";
    exit(0);
}

$base = 'http://localhost/Auto%201';
$cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ia_login_ok_cookies.txt';
@unlink($cookieFile);

function http(string $url, string $cookieFile, ?string $postBody = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_HEADER => true,
    ]);
    if ($postBody !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
    }
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $parts = preg_split("/\r\n\r\n/", $raw, 2);

    return ['code' => $code, 'headers' => $parts[0] ?? '', 'body' => $parts[1] ?? ''];
}

$r1 = http($base . '/login.php', $cookieFile);
preg_match('/name="_csrf" value="([^"]+)"/', $r1['body'], $m);
$csrf = $m[1] ?? '';
$post = http_build_query([
    'action' => 'login',
    'email' => $email,
    'password' => $password,
    '_csrf' => $csrf,
    'redirect' => 'profile.php',
]);
$r2 = http($base . '/login.php', $cookieFile, $post);
echo "Login POST HTTP {$r2['code']}\n";
if ($r2['code'] !== 302 && $r2['code'] !== 303) {
    echo "FAIL: expected redirect\n";
    if (str_contains($r2['body'], 'Сессия устарела')) {
        echo "CSRF failed\n";
    }
    exit(1);
}
preg_match('/Location:\s*(.+)/i', $r2['headers'], $loc);
echo 'Redirect: ' . trim($loc[1] ?? '') . "\n";

$r3 = http(trim($loc[1] ?? $base . '/profile.php'), $cookieFile);
echo "Profile GET HTTP {$r3['code']}\n";
if (str_contains($r3['body'], 'login.php') && str_contains($r3['body'], 'Вход')) {
    echo "FAIL: profile redirected to login page content\n";
    exit(1);
}
if (str_contains($r3['body'], 'profile.php') || str_contains($r3['body'], 'Кабинет') || str_contains($r3['body'], 'Профиль')) {
    echo "OK: profile page accessible after login\n";
}
if (preg_match('/href="([^"]*profile\.php[^"]*)"[^>]*aria-label="Профиль/', $r3['body'], $pm)) {
    echo 'Header profile link: ' . html_entity_decode($pm[1]) . "\n";
}
