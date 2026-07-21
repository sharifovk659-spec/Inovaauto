<?php

declare(strict_types=1);

/**
 * Cloudflare / edge: HTTPS, canonical host, real client IP, security & cache headers.
 * Called from public_bootstrap.php and bootstrap.php immediately after .env load.
 */

/** @var list<string> Cloudflare egress (https://www.cloudflare.com/ips-v4/) */
const IA_CLOUDFLARE_IPV4_CIDRS = [
    '173.245.48.0/20',
    '103.21.244.0/22',
    '103.22.200.0/22',
    '103.31.4.0/22',
    '141.101.64.0/18',
    '108.162.192.0/18',
    '190.93.240.0/20',
    '188.114.96.0/20',
    '197.234.240.0/22',
    '198.41.128.0/17',
    '162.158.0.0/15',
    '104.16.0.0/13',
    '104.24.0.0/14',
    '172.64.0.0/13',
    '131.0.72.0/22',
];

function ia_edge_is_local_request(): bool
{
    if (function_exists('ia_is_local_request')) {
        return ia_is_local_request();
    }
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return false;
    }
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
        || str_ends_with($host, '.local')
        || str_ends_with($host, '.test');
}

function ia_edge_http_bootstrap(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    ia_edge_apply_cloudflare_real_ip();
    ia_edge_redirect_canonical_host();
    ia_edge_redirect_https();
}

function ia_is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    $proto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($proto === 'https') {
        return true;
    }
    $cfVisitor = (string) ($_SERVER['HTTP_CF_VISITOR'] ?? '');
    if ($cfVisitor !== '' && str_contains($cfVisitor, '"https"')) {
        return true;
    }

    return false;
}

function ia_edge_trust_cloudflare_headers(): bool
{
    $mode = strtolower(trim((string) (ia_env('IA_TRUST_CLOUDFLARE', 'auto'))));
    if ($mode === 'false' || $mode === '0' || $mode === 'off') {
        return false;
    }
    if ($mode === 'true' || $mode === '1' || $mode === 'on') {
        return true;
    }

    return ia_edge_request_via_cloudflare();
}

function ia_edge_request_via_cloudflare(): bool
{
    if (trim((string) ($_SERVER['HTTP_CF_RAY'] ?? '')) === '') {
        return false;
    }
    $peer = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    return $peer !== '' && ia_edge_ip_in_cloudflare_ranges($peer);
}

function ia_edge_ip_in_cloudflare_ranges(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        return false;
    }
    foreach (IA_CLOUDFLARE_IPV4_CIDRS as $cidr) {
        if (ia_edge_ipv4_in_cidr($ip, $cidr)) {
            return true;
        }
    }

    return false;
}

function ia_edge_ipv4_in_cidr(string $ip, string $cidr): bool
{
    if (!str_contains($cidr, '/')) {
        return $ip === $cidr;
    }
    [$subnet, $bitsStr] = explode('/', $cidr, 2);
    $bits = (int) $bitsStr;
    if ($bits < 0 || $bits > 32) {
        return false;
    }
    $ipLong = ip2long($ip);
    $subnetLong = ip2long($subnet);
    if ($ipLong === false || $subnetLong === false) {
        return false;
    }
    if ($bits === 0) {
        return true;
    }
    $mask = $bits === 32 ? -1 : (~((1 << (32 - $bits)) - 1));

    return ($ipLong & $mask) === ($subnetLong & $mask);
}

function ia_edge_apply_cloudflare_real_ip(): void
{
    if (!ia_edge_trust_cloudflare_headers()) {
        return;
    }

    $clientIp = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($clientIp === '' || filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
        return;
    }

    if (!isset($_SERVER['IA_REMOTE_ADDR_ORIGINAL'])) {
        $_SERVER['IA_REMOTE_ADDR_ORIGINAL'] = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
    $_SERVER['IA_CLIENT_IP'] = $clientIp;
    $_SERVER['REMOTE_ADDR'] = $clientIp;

    $proto = strtolower(trim((string) ($_SERVER['HTTP_CF_VISITOR'] ?? '')));
    if ($proto === '' && isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        return;
    }
    if (str_contains((string) ($_SERVER['HTTP_CF_VISITOR'] ?? ''), '"https"')) {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_PORT'] = '443';
    }
}

function ia_edge_canonical_host(): string
{
    $host = strtolower(trim((string) (ia_env('IA_CANONICAL_HOST', ''))));
    if ($host !== '') {
        return $host;
    }
    $base = rtrim((string) (ia_env('IA_BASE_URL', '')), '/');
    if ($base !== '' && preg_match('#^https?://([^/]+)#i', $base, $m)) {
        return strtolower($m[1]);
    }

    return '';
}

function ia_edge_redirect_canonical_host(): void
{
    if (ia_edge_is_local_request()) {
        return;
    }

    $canonical = ia_edge_canonical_host();
    if ($canonical === '') {
        return;
    }

    $requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $requestHost = preg_replace('/:\d+$/', '', $requestHost) ?? $requestHost;
    if ($requestHost === '' || $requestHost === $canonical) {
        return;
    }

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $target = (ia_is_https_request() ? 'https' : 'http') . '://' . $canonical . $uri;
    header('Location: ' . $target, true, 301);
    exit;
}

function ia_edge_redirect_https(): void
{
    if (ia_edge_is_local_request() || ia_is_https_request()) {
        return;
    }

    $force = strtolower(trim((string) (ia_env('IA_FORCE_HTTPS', 'auto'))));
    if ($force === 'false' || $force === '0' || $force === 'off') {
        return;
    }
    if ($force === 'auto' && ia_edge_canonical_host() === '' && rtrim((string) ia_env('IA_BASE_URL', ''), '/') === '') {
        return;
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

function ia_edge_is_sensitive_route(): bool
{
    $script = strtolower(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')));
    if (str_contains($script, '/admin/')) {
        return true;
    }

    $base = basename($script);

    return in_array($base, [
        'login.php',
        'register.php',
        'forgot-password.php',
        'reset-password.php',
        'google-auth-callback.php',
        'logout.php',
        'profile.php',
        'messages.php',
        'chat-poll.php',
        'edit-listing.php',
        'add-listing.php',
        'pay-promotion.php',
        'fix-site.php',
        'setup-mysql.php',
        'migrate.php',
    ], true);
}

function ia_edge_send_http_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header(
        'Permissions-Policy: geolocation=(self), camera=(self), microphone=(), payment=(), usb=(), interest-cohort=()'
    );

    if (ia_edge_is_sensitive_route()) {
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('CDN-Cache-Control: no-store');
    }

    if (!ia_edge_is_local_request() && ia_is_https_request() && ia_edge_hsts_enabled()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function ia_edge_hsts_enabled(): bool
{
    return filter_var(ia_env('IA_HSTS', 'true'), FILTER_VALIDATE_BOOLEAN);
}

/**
 * Canonical URL for <link rel="canonical"> (public pages; car.php sets its own).
 */
function ia_page_canonical_url(): string
{
    if (ia_edge_is_local_request() || !function_exists('ia_public_url')) {
        return '';
    }

    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script === '' || $script === 'car.php') {
        return '';
    }

    if ($script === 'index.php') {
        return rtrim(ia_site_base_url(), '/') . '/';
    }

    return ia_public_url($script);
}
