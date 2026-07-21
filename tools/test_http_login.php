<?php

declare(strict_types=1);

/**
 * HTTP login smoke test (CLI).
 */
$base = 'http://localhost/Auto%201';
$cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ia_login_test_cookies.txt';
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
        CURLOPT_HTTPHEADER => $postBody !== null ? ['Content-Type: application/x-www-form-urlencoded'] : [],
    ]);
    if ($postBody !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
    }
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $parts = preg_split("/\r\n\r\n/", $raw, 2);
    $headers = $parts[0] ?? '';
    $body = $parts[1] ?? '';

    return ['code' => $code, 'headers' => $headers, 'body' => $body];
}

echo "1) GET login.php\n";
$r1 = http($base . '/login.php', $cookieFile);
echo 'HTTP ' . $r1['code'] . "\n";
if (!preg_match('/name="_csrf" value="([^"]+)"/', $r1['body'], $m)) {
    echo "FAIL: no CSRF token\n";
    exit(1);
}
$csrf = $m[1];
echo 'CSRF OK: ' . substr($csrf, 0, 12) . "...\n";

echo "\n2) POST login (wrong password)\n";
$post = http_build_query([
    'action' => 'login',
    'email' => 'test@gmail.com',
    'password' => 'wrong-password-xyz',
    '_csrf' => $csrf,
    'redirect' => 'index.php',
]);
$r2 = http($base . '/login.php', $cookieFile, $post);
echo 'HTTP ' . $r2['code'] . "\n";
if (str_contains($r2['body'], 'Сессия устарела')) {
    echo "FAIL: CSRF rejected on login\n";
    exit(1);
}
if (str_contains($r2['body'], 'Неверный email или пароль')) {
    echo "OK: login form processed (wrong password message)\n";
} else {
    echo "WARN: unexpected login response\n";
}

echo "\n3) GET login.php again (same cookie jar) — CSRF must persist\n";
$r3 = http($base . '/login.php', $cookieFile);
if (!preg_match('/name="_csrf" value="([^"]+)"/', $r3['body'], $m2)) {
    echo "FAIL: no CSRF on second GET\n";
    exit(1);
}
echo ($m2[1] === $csrf ? 'OK: CSRF stable across requests' : 'WARN: CSRF rotated') . "\n";

echo "\n4) GET index.php profile link when guest\n";
$r4 = http($base . '/index.php', $cookieFile);
if (preg_match('/href="([^"]*login\.php[^"]*)"[^>]*aria-label="Профиль/', $r4['body'], $pm)) {
    echo 'OK: guest profile -> ' . html_entity_decode($pm[1]) . "\n";
} else {
    echo "WARN: profile login link not found in HTML\n";
}

echo "\nDone.\n";
