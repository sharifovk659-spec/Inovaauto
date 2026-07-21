<?php

declare(strict_types=1);

function ia_flash(string $key, ?string $message = null): mixed
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;

        return null;
    }

    $out = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);

    return $out;
}

function ia_redirect(string $url): never
{
    header('Location: ' . $url, true, 302);
    exit;
}

function ia_h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ia_pub_layout_state_bump(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['ia_layout_state']);
    }
}

function ia_is_local_request(): bool
{
    if (PHP_SAPI === 'cli') {
        return false;
    }
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return false;
    }
    $host = preg_replace('/:\d+$/', '', $host);

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
        || str_ends_with($host, '.local')
        || str_ends_with($host, '.test');
}

/**
 * Web-path to project root (e.g. "Auto%201"), not the current /admin/ script folder.
 */
function ia_site_web_path(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $docRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
    $docReal = $docRoot !== '' ? realpath($docRoot) : false;
    $rootReal = realpath(IA_ROOT) ?: IA_ROOT;
    if ($docReal !== false) {
        $docNorm = rtrim(str_replace('\\', '/', $docReal), '/');
        $rootNorm = rtrim(str_replace('\\', '/', (string) $rootReal), '/');
        if ($rootNorm !== '' && str_starts_with($rootNorm, $docNorm)) {
            $rel = trim(substr($rootNorm, strlen($docNorm)), '/');
            if ($rel === '') {
                $cached = '';

                return $cached;
            }
            $parts = explode('/', $rel);
            $encoded = [];
            foreach ($parts as $segment) {
                if ($segment === '' || $segment === '.') {
                    continue;
                }
                $encoded[] = rawurlencode(rawurldecode($segment));
            }
            $cached = implode('/', $encoded);

            return $cached;
        }
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = trim(dirname($script), '/');
    $parts = $dir === '' ? [] : explode('/', $dir);
    if (($parts[count($parts) - 1] ?? '') === 'admin') {
        array_pop($parts);
    }
    $encoded = [];
    foreach ($parts as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        $encoded[] = rawurlencode(rawurldecode($segment));
    }
    $cached = implode('/', $encoded);

    return $cached;
}

function ia_request_base_url(): string
{
    if (PHP_SAPI === 'cli') {
        return '';
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return '';
    }

    $https = function_exists('ia_is_https_request') ? ia_is_https_request() : (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );
    $scheme = $https ? 'https' : 'http';
    $webPath = ia_site_web_path();

    return $scheme . '://' . $host . ($webPath === '' ? '' : '/' . $webPath);
}

function ia_site_base_url(): string
{
    static $cached;
    if ($cached !== null) {
        return $cached;
    }

    $configured = rtrim((string) (ia_config()['app']['base_url'] ?? ''), '/');
    if ($configured !== '') {
        $cached = $configured;

        return $cached;
    }

    $detect = filter_var(ia_env('IA_DETECT_BASE_URL', 'true'), FILTER_VALIDATE_BOOLEAN);
    if ($detect) {
        $detected = ia_request_base_url();
        if ($detected !== '') {
            $cached = $detected;

            return $cached;
        }
    }

    $cached = 'http://localhost';

    return $cached;
}

/**
 * Преобразует внутренний путь script.php в «красивый» URL без расширения.
 * index.php → пусто; login.php?redirect=profile.php → login?redirect=profile
 */
function ia_pretty_public_path(string $path): string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    if ($path === '' || strcasecmp($path, 'index.php') === 0) {
        return '';
    }

    $qPos = strpos($path, '?');
    $scriptPart = $qPos !== false ? substr($path, 0, $qPos) : $path;
    $queryPart = $qPos !== false ? substr($path, $qPos + 1) : '';

    if (preg_match('/\.php$/i', $scriptPart)) {
        $base = substr($scriptPart, 0, -4);
        $scriptPart = strcasecmp($base, 'index') === 0 ? '' : $base;
    }

    if ($queryPart !== '') {
        parse_str($queryPart, $params);
        if (isset($params['redirect']) && is_string($params['redirect'])) {
            $params['redirect'] = ia_pretty_public_path($params['redirect']);
        }
        $queryPart = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    if ($scriptPart === '') {
        return $queryPart !== '' ? '?' . $queryPart : '';
    }

    return $queryPart !== '' ? $scriptPart . '?' . $queryPart : $scriptPart;
}

function ia_public_url(string $path = ''): string
{
    $path = ia_pretty_public_path(ltrim($path, '/'));
    $base = rtrim(ia_site_base_url(), '/');

    if ($path === '') {
        return $base . '/';
    }
    if (str_starts_with($path, '?')) {
        return $base . '/' . $path;
    }

    return $base . '/' . $path;
}

/**
 * URL to a static file under the site root (кириллица и пробелы кодируются для хостинга).
 */
function ia_root_asset(string $relativePath): string
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    $segments = explode('/', $relativePath);
    $encoded = [];
    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        $encoded[] = rawurlencode(rawurldecode($segment));
    }
    $encodedPath = implode('/', $encoded);
    $webPath = ia_site_web_path();
    if ($webPath === '') {
        return '/' . $encodedPath;
    }

    return '/' . $webPath . '/' . $encodedPath;
}

/** Абсолютный путь к PNG-фону welcome-экрана входа (папка IMG). */
function ia_login_welcome_background_disk_path(): ?string
{
    static $resolved = null;
    static $done = false;
    if ($done) {
        return $resolved;
    }
    $done = true;

    $dir = IA_ROOT . DIRECTORY_SEPARATOR . 'IMG';
    if (!is_dir($dir)) {
        return null;
    }

    $explicit = [
        'login-welcome.png',
        'login-bg.png',
        'vhod.png',
        'вход.png',
        'фони .png',
        'фони.png',
        'фон.png',
    ];
    foreach ($explicit as $name) {
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (is_file($path)) {
            $resolved = $path;

            return $resolved;
        }
    }

    $entries = scandir($dir);
    if ($entries === false) {
        return null;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (!preg_match('/\.png$/i', $entry) || preg_match('/^\d+\.png$/', $entry)) {
            continue;
        }
        $lower = function_exists('mb_strtolower') ? mb_strtolower($entry, 'UTF-8') : strtolower($entry);
        if (
            str_contains($lower, 'вход')
            || str_contains($lower, 'vhod')
            || str_contains($lower, 'фон')
            || str_contains($lower, 'login')
        ) {
            $resolved = $dir . DIRECTORY_SEPARATOR . $entry;

            return $resolved;
        }
    }

    return null;
}

/** Публичный URL фона welcome-экрана входа с ?v=filemtime. */
function ia_login_welcome_background_url(): string
{
    $path = ia_login_welcome_background_disk_path();
    if ($path === null) {
        return '';
    }

    return ia_public_asset_version('IMG/' . basename($path));
}

/** Абсолютный путь к PNG-логотипу welcome-экрана (IMG/логотип.png). */
function ia_auth_logo_disk_path(): ?string
{
    static $resolved = null;
    static $done = false;
    if ($done) {
        return $resolved;
    }
    $done = true;

    $dir = IA_ROOT . DIRECTORY_SEPARATOR . 'IMG';
    if (!is_dir($dir)) {
        return null;
    }

    $explicit = [
        'логотип.png',
        'logotip.png',
        'logo-auth.png',
        'logo.png',
    ];
    foreach ($explicit as $name) {
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (is_file($path)) {
            $resolved = $path;

            return $resolved;
        }
    }

    $entries = scandir($dir);
    if ($entries === false) {
        return null;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (!preg_match('/\.png$/i', $entry) || preg_match('/^\d+\.png$/', $entry)) {
            continue;
        }
        $lower = function_exists('mb_strtolower') ? mb_strtolower($entry, 'UTF-8') : strtolower($entry);
        if (
            str_contains($lower, 'логотип')
            || str_contains($lower, 'logotip')
            || str_contains($lower, 'logo')
        ) {
            $resolved = $dir . DIRECTORY_SEPARATOR . $entry;

            return $resolved;
        }
    }

    return null;
}

/** Публичный URL логотипа welcome-экрана с ?v=filemtime. */
function ia_auth_logo_url(): string
{
    $path = ia_auth_logo_disk_path();
    if ($path === null) {
        return '';
    }

    return ia_public_asset_version('IMG/' . basename($path));
}

function ia_public_asset(string $relativePath): string
{
    return ia_root_asset($relativePath);
}

/** URL к статике с ?v=filemtime — после загрузки на хостинг кэш обновляется сам. */
function ia_public_asset_version(string $relativePath): string
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    $abs = IA_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $url = ia_public_asset($relativePath);

    return is_file($abs) ? $url . '?v=' . (string) filemtime($abs) : $url;
}

/**
 * true when unminified source assets should load (local debugging).
 */
function ia_assets_dev_mode(): bool
{
    return filter_var(ia_env('IA_CSS_DEV', 'false'), FILTER_VALIDATE_BOOLEAN)
        || filter_var(ia_env('IA_JS_DEV', 'false'), FILTER_VALIDATE_BOOLEAN);
}

/**
 * Stylesheet href with optional minified build (IA_CSS_DEV / IA_JS_DEV → source file).
 */
function ia_stylesheet_href(string $sourceRel, ?string $minRel = null): string
{
    $dev = ia_assets_dev_mode();
    $rel = $sourceRel;
    if (!$dev && $minRel !== null) {
        $minAbs = IA_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $minRel);
        if (is_file($minAbs)) {
            $rel = $minRel;
        }
    }

    return ia_public_asset_version($rel);
}

/**
 * Public stylesheet URL: site.min.css in production, site.css when IA_CSS_DEV=1.
 */
function ia_public_site_css_href(): string
{
    return ia_stylesheet_href('assets/site.css', 'assets/site.min.css');
}

/**
 * Script src with optional .min.js build (IA_JS_DEV=1 → source).
 */
function ia_script_href(string $sourceRel, ?string $minRel = null): string
{
    $dev = ia_assets_dev_mode();
    $rel = $sourceRel;
    if (!$dev && $minRel !== null) {
        $minAbs = IA_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $minRel);
        if (is_file($minAbs)) {
            $rel = $minRel;
        }
    }

    return ia_public_asset_version($rel);
}

/**
 * Path to critical above-the-fold CSS (inlined in head).
 */
function ia_critical_above_fold_css_path(): ?string
{
    $path = IA_ROOT . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'critical' . DIRECTORY_SEPARATOR . 'above-fold.css';

    return is_file($path) ? $path : null;
}

/**
 * Атрибутҳои <img> барои LCP/CLS: lazy барои офф-скрин, eager + high барои hero/logo.
 *
 * @param array{loading?:string,fetchpriority?:string,width?:int,height?:int,decoding?:string} $opts
 */
function ia_img_perf_attrs(array $opts = []): string
{
    $loading = strtolower((string) ($opts['loading'] ?? 'lazy'));
    if ($loading !== 'eager') {
        $loading = 'lazy';
    }
    $decoding = strtolower((string) ($opts['decoding'] ?? 'async'));
    if ($decoding !== 'auto' && $decoding !== 'sync') {
        $decoding = 'async';
    }
    $parts = ['loading="' . $loading . '"', 'decoding="' . $decoding . '"'];
    $w = (int) ($opts['width'] ?? 0);
    $h = (int) ($opts['height'] ?? 0);
    if ($w > 0) {
        $parts[] = 'width="' . $w . '"';
    }
    if ($h > 0) {
        $parts[] = 'height="' . $h . '"';
    }
    $fp = strtolower((string) ($opts['fetchpriority'] ?? ''));
    if ($fp === 'high' || $fp === 'low') {
        $parts[] = 'fetchpriority="' . $fp . '"';
    }

    return implode(' ', $parts);
}

/**
 * ASCII-ключ для поиска slug логотипа (ë, é, кириллица не ломают slug).
 */
function ia_brand_ascii_key(string $value): string
{
    $v = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    $v = strtr($v, [
        'ë' => 'e', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'á' => 'a', 'à' => 'a',
        'ü' => 'u', 'ö' => 'o', 'ä' => 'a', 'ß' => 'ss', 'ç' => 'c', 'ñ' => 'n',
        'ё' => 'e', 'й' => 'i', '—' => '-', '_' => ' ',
    ]);

    return (string) preg_replace('/[^a-z0-9]+/', '', $v);
}

/**
 * @return array<string, string>
 */
function ia_brand_icon_slug_map(): array
{
    return [
        'audi' => 'audi', 'bmw' => 'bmw', 'byd' => 'byd', 'chevrolet' => 'chevrolet',
        'ford' => 'ford', 'honda' => 'honda', 'hyundai' => 'hyundai', 'kia' => 'kia',
        'lexus' => 'lexus', 'mazda' => 'mazda', 'mercedesbenz' => 'mercedes',
        'mercedes' => 'mercedes', 'mitsubishi' => 'mitsubishi', 'nissan' => 'nissan',
        'opel' => 'opel', 'porsche' => 'porsche', 'renault' => 'renault', 'subaru' => 'subaru',
        'suzuki' => 'suzuki', 'tesla' => 'tesla', 'toyota' => 'toyota', 'volkswagen' => 'volkswagen',
        'volvo' => 'volvo', 'lada' => 'lada', 'citroen' => 'citroen', 'fiat' => 'fiat',
        'mini' => 'mini', 'genesis' => 'genesis', 'bentley' => 'bentley', 'ferrari' => 'ferrari',
        'jeep' => 'jeep', 'skoda' => 'skoda', 'peugeot' => 'peugeot', 'landrover' => 'landrover',
        'zeekr' => 'zeekr', 'liauto' => 'liauto',
        'jaguar' => 'jaguar', 'infiniti' => 'infiniti', 'acura' => 'acura', 'cadillac' => 'cadillac',
        'chrysler' => 'chrysler', 'dodge' => 'dodge', 'gmc' => 'gmc', 'ram' => 'ram',
        'seat' => 'seat', 'cupra' => 'cupra', 'dsautomobiles' => 'dsautomobiles',
    ];
}

/** Slug для Simple Icons по названию бренда. */
function ia_brand_icon_slug(string $brandName, ?string $hintSlug = null): string
{
    $map = ia_brand_icon_slug_map();

    if ($hintSlug !== null && $hintSlug !== '') {
        $hintKey = ia_brand_ascii_key($hintSlug);
        if ($hintKey !== '') {
            return $map[$hintKey] ?? $hintKey;
        }
    }

    $key = ia_brand_ascii_key($brandName);
    if ($key === '') {
        return '';
    }

    if (isset($map[$key])) {
        return $map[$key];
    }

    return $key;
}

/**
 * Read valid local/builtin brand SVG markup.
 */
function ia_brand_logo_svg_content(string $slug): ?string
{
    if ($slug === '') {
        return null;
    }

    $rel = 'assets/brands/' . $slug . '.svg';
    $abs = IA_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (is_file($abs) && is_readable($abs)) {
        $content = (string) file_get_contents($abs);
        if ($content !== '' && preg_match('/<(svg|xml)/i', $content) && stripos($content, 'File not found') === false) {
            return $content;
        }
    }

    if (!function_exists('ia_brand_builtin_logo_svg')) {
        $defaults = IA_ROOT . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'brand_logo_defaults.php';
        if (is_file($defaults)) {
            require_once $defaults;
        }
    }

    return function_exists('ia_brand_builtin_logo_svg') ? ia_brand_builtin_logo_svg($slug) : null;
}

function ia_brand_logo_data_uri(string $slug): ?string
{
    $svg = ia_brand_logo_svg_content($slug);
    if ($svg === null || trim($svg) === '') {
        return null;
    }

    return 'data:image/svg+xml,' . rawurlencode($svg);
}

/**
 * @return array{src: string, fallback: string, slug: string}
 */
function ia_brand_local_logo_relative(string $slug): ?string
{
    if ($slug === '') {
        return null;
    }

    $rel = 'assets/brands/' . $slug . '.svg';
    $abs = IA_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($abs) || !is_readable($abs)) {
        return null;
    }

    $head = (string) file_get_contents($abs, false, null, 0, 256);
    if ($head === '' || !preg_match('/<(svg|xml)/i', $head) || stripos($head, 'File not found') !== false) {
        return null;
    }

    return $rel;
}

/**
 * @return array{src: string, fallback: string, slug: string}
 */
function ia_brand_logo_urls(string $brandName, ?string $hintSlug = null): array
{
    $slug = ia_brand_icon_slug($brandName, $hintSlug);
    if ($slug === '') {
        return ['src' => '', 'fallback' => '', 'slug' => ''];
    }

    $dataUri = ia_brand_logo_data_uri($slug);
    if ($dataUri !== null) {
        return [
            'src' => $dataUri,
            'fallback' => '',
            'slug' => $slug,
        ];
    }

    if (!function_exists('ia_brand_cdn_unsupported_slugs')) {
        $defaults = IA_ROOT . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'brand_logo_defaults.php';
        if (is_file($defaults)) {
            require_once $defaults;
        }
    }
    $noCdn = function_exists('ia_brand_cdn_unsupported_slugs') ? ia_brand_cdn_unsupported_slugs() : [];

    if (in_array($slug, $noCdn, true)) {
        return ['src' => '', 'fallback' => '', 'slug' => $slug];
    }

    $encoded = rawurlencode($slug);

    return [
        'src' => 'https://cdn.simpleicons.org/' . $encoded,
        'fallback' => 'https://cdn.jsdelivr.net/npm/simple-icons@11.14.0/icons/' . $encoded . '.svg',
        'slug' => $slug,
    ];
}

/** Ссылка на вход в админ-панель (без загрузки auth.php админки). */
function ia_public_admin_url(): string
{
    return ia_site_base_url() . '/admin/';
}

/**
 * URL к статике админки относительно каталога текущего PHP-скрипта в /admin/.
 * Не зависит от точности app.base_url — исправляет «голую» страницу без CSS.
 */
function ia_admin_asset_url(string $relativePath): string
{
    $rel = str_replace('\\', '/', ltrim($relativePath, '/'));
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script === '') {
        return '/admin/' . $rel;
    }
    $dir = dirname($script);
    $dir = $dir === '.' || $dir === '\\' ? '/admin' : str_replace('\\', '/', $dir);
    if ($dir !== '/' && $dir !== '' && !str_starts_with($dir, '/')) {
        $dir = '/' . $dir;
    }
    if ($dir === '/' || $dir === '') {
        return '/' . $rel;
    }

    return rtrim($dir, '/') . '/' . $rel;
}

/** Admin CSS: admin.min.css in production unless IA_CSS_DEV=1. */
function ia_admin_css_href(): string
{
    $dev = ia_assets_dev_mode();
    $rel = 'assets/admin.css';
    if (!$dev) {
        $minRel = 'assets/admin.min.css';
        $minAbs = IA_ROOT . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $minRel);
        if (is_file($minAbs)) {
            $rel = $minRel;
        }
    }
    $abs = IA_ROOT . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $url = ia_admin_asset_url($rel);

    return is_file($abs) ? $url . '?v=' . (string) filemtime($abs) : $url . '?v=18';
}

/** Относительный путь внутри uploads/banners/ (например demo/home.svg). */
function ia_uploads_banners_public_url(string $relativePath): string
{
    $rel = str_replace('\\', '/', $relativePath);

    return ia_site_base_url() . '/uploads/banners/' . ltrim($rel, '/');
}

/**
 * URL для тега <img>: загрузка (имя файла в uploads/listings/) или внешний https.
 */
function ia_listing_photo_src(?string $stored): string
{
    $s = trim((string) $stored);
    if ($s === '') {
        return ia_listing_photo_placeholder();
    }
    if (preg_match('#\Ahttps?://#i', $s)) {
        return $s;
    }
    require_once IA_ROOT . '/includes/supabase_storage.php';
    $cloudUrl = ia_supabase_storage_resolve_photo_url($s);
    if ($cloudUrl !== null && $cloudUrl !== '') {
        return $cloudUrl;
    }
    $s = ltrim(str_replace('\\', '/', $s), '/');
    $basename = basename($s);
    $rel = 'uploads/listings/' . $basename;
    $localPath = IA_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (is_file($localPath)) {
        return ia_root_asset($rel);
    }

    return ia_root_asset($rel);
}

/**
 * Inline SVG placeholder (no external network, sharp on every screen).
 */
function ia_listing_photo_placeholder(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $svg = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 400" width="640" height="400">'
        . '<defs>'
        . '<linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0%" stop-color="#eef2f8"/>'
        . '<stop offset="100%" stop-color="#dde4ef"/>'
        . '</linearGradient>'
        . '</defs>'
        . '<rect width="640" height="400" fill="url(#g)"/>'
        . '<g fill="#94a3b8" opacity="0.6">'
        . '<path d="M120 270c-12 0-22-10-22-22v-22c0-9 5-16 13-19l40-12 26-46c5-9 14-15 25-15h236c11 0 20 6 25 15l26 46 40 12c8 3 13 10 13 19v22c0 12-10 22-22 22H120zm70-30a30 30 0 1 0-60 0 30 30 0 0 0 60 0zm320 0a30 30 0 1 0-60 0 30 30 0 0 0 60 0z"/>'
        . '</g>'
        . '<text x="320" y="350" text-anchor="middle" fill="#94a3b8" font-family="Inter, Arial, sans-serif" font-size="18" font-weight="600">Нет фото</text>'
        . '</svg>';
    $cached = 'data:image/svg+xml;utf8,' . rawurlencode($svg);

    return $cached;
}

/** Убирает пояснения в скобках из подписей формы (только отображение). */
function ia_listing_form_plain_label(string $text): string
{
    $plain = preg_replace('/\s*\([^)]*\)\s*/u', ' ', $text);
    if ($plain === null) {
        return trim($text);
    }
    $plain = preg_replace('/\s+/u', ' ', $plain);

    return trim($plain ?? $text);
}

/**
 * Builds the public URL for the user's avatar (or returns null when none stored).
 */
function ia_user_avatar_src(?string $stored): ?string
{
    $s = trim((string) $stored);
    if ($s === '') {
        return null;
    }
    if (preg_match('#\Ahttps?://#i', $s)) {
        return $s;
    }
    $s = ltrim(str_replace('\\', '/', $s), '/');
    $rel = str_starts_with($s, 'uploads/avatars/') ? $s : 'uploads/avatars/' . $s;

    return ia_root_asset($rel);
}

/** @return 'in_stock'|'on_order' */
function ia_listing_availability_normalize(?string $raw): string
{
    return trim((string) $raw) === 'on_order' ? 'on_order' : 'in_stock';
}

function ia_listing_availability_label_ru(string $availability): string
{
    return $availability === 'on_order' ? 'На заказ' : 'В наличии';
}

function ia_listing_drive_type_options(): array
{
    return [
        '' => '— не указан —',
        'front' => 'Передний',
        'rear' => 'Задний',
        'awd' => 'Полный (AWD)',
        '4wd' => 'Полный (4WD)',
    ];
}

function ia_listing_drive_type_label_ru(string $code): string
{
    $map = ia_listing_drive_type_options();
    return $map[$code] ?? '—';
}

function ia_listing_condition_options(): array
{
    return [
        '' => '— не указано —',
        'new' => 'Новая',
        'used' => 'Б/у',
    ];
}

function ia_listing_condition_label_ru(string $code): string
{
    $map = ia_listing_condition_options();
    return $map[$code] ?? '—';
}

function ia_listing_fuel_label_ru(string $code): string
{
    return match ($code) {
        'petrol' => 'Бензин',
        'diesel' => 'Дизель',
        'gas' => 'Газ',
        'hybrid' => 'Гибрид',
        'electric' => 'Электричество',
        default => '',
    };
}

function ia_listing_transmission_label_ru(string $code): string
{
    return match ($code) {
        'auto', 'automatic' => 'Автомат',
        'manual' => 'Механика',
        'robot' => 'Робот',
        'cvt' => 'Вариатор (CVT)',
        default => '',
    };
}

function ia_listing_pub_date_label(?string $createdAt): string
{
    $ts = strtotime(trim((string) $createdAt));
    if ($ts === false || $ts <= 0) {
        return '—';
    }

    return date('d.m.Y', $ts);
}

function ia_listing_mileage_label_ru(mixed $mileageKm): string
{
    $km = (int) $mileageKm;
    if ($km <= 0) {
        return '—';
    }

    return number_format($km, 0, '.', ' ') . ' км';
}

function ia_listing_views_count(array $row): int
{
    return max(0, (int) ($row['views_count'] ?? 0));
}

/** VIP / TOP / другие TINYINT-флаги из MySQL или PostgreSQL. */
function ia_listing_flag_on(mixed $value): bool
{
    if ($value === true || $value === 1) {
        return true;
    }
    if ($value === false || $value === null || $value === '') {
        return false;
    }
    if (is_string($value)) {
        $v = strtolower(trim($value));
        if (in_array($v, ['0', 'false', 'f', 'no', 'off', ''], true)) {
            return false;
        }
        if (in_array($v, ['1', 'true', 't', 'yes', 'on'], true)) {
            return true;
        }
    }

    return (int) $value === 1;
}

/**
 * Текст предупреждения при «Поделиться» (модалка и системное меню).
 *
 * @return array{heading: string, lines: list<string>}
 */
function ia_listing_share_disclaimer_parts(string $siteName = 'InnovaAuto'): array
{
    $site = trim($siteName) !== '' ? trim($siteName) : 'InnovaAuto';

    $warning = 'Для вашей безопасности общайтесь только через сайт ' . $site . '. '
        . 'При общении вне сайта (WhatsApp, Telegram и другие мессенджеры) '
        . $site . ' не несёт ответственности.';

    return [
        'heading' => 'Внимание',
        'lines' => [$warning],
        'native' => $warning,
    ];
}

function ia_listing_share_disclaimer(string $siteName = 'InnovaAuto'): string
{
    $parts = ia_listing_share_disclaimer_parts($siteName);

    return (string) ($parts['native'] ?? implode(' ', $parts['lines']));
}

/** Абсолютный URL для Open Graph и «Поделиться». */
function ia_absolute_url(string $pathOrUrl): string
{
    $pathOrUrl = trim($pathOrUrl);
    if ($pathOrUrl === '') {
        return '';
    }
    if (preg_match('#\Ahttps?://#i', $pathOrUrl)) {
        return $pathOrUrl;
    }
    if (str_starts_with($pathOrUrl, '//')) {
        $https = function_exists('ia_is_https_request') ? ia_is_https_request() : (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
        );

        return ($https ? 'https' : 'http') . ':' . $pathOrUrl;
    }

    $base = rtrim(ia_site_base_url(), '/');
    if (str_starts_with($pathOrUrl, '/')) {
        $parts = parse_url($base);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $base . $pathOrUrl;
        }
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin . $pathOrUrl;
    }

    return $base . '/' . ltrim(str_replace('\\', '/', $pathOrUrl), '/');
}

/**
 * Мета для «Поделиться» и SEO страницы объявления.
 *
 * @param array<string, mixed> $listing
 * @return array{
 *   url: string,
 *   title: string,
 *   page_title: string,
 *   description: string,
 *   image: string,
 *   share_line: string,
 *   share_text: string
 * }
 */
function ia_listing_share_meta(array $listing, int $listingId, string $siteName = 'InnovaAuto'): array
{
    $brand = trim((string) ($listing['brand'] ?? ''));
    $model = trim((string) ($listing['model'] ?? ''));
    $carTitle = trim($brand . ' ' . $model);
    if ($carTitle === '') {
        $carTitle = 'Автомобиль';
    }

    $year = (int) ($listing['model_year'] ?? 0);
    $cur = (string) ($listing['currency'] ?? 'TJS');
    $priceLabel = ia_listing_format_price((float) ($listing['price'] ?? 0), $cur);

    $shareTitle = $carTitle;
    if ($year >= 1950) {
        $shareTitle .= ', ' . $year;
    }
    $shareTitle .= ' — ' . $priceLabel;

    $url = function_exists('ia_absolute_url')
        ? ia_absolute_url(ia_public_url('car.php?id=' . max(1, $listingId)))
        : ia_public_url('car.php?id=' . max(1, $listingId));

    $descBits = [];
    if ($year >= 1950) {
        $descBits[] = (string) $year;
    }
    if (isset($listing['mileage_km']) && $listing['mileage_km'] !== null && $listing['mileage_km'] !== '') {
        $descBits[] = ia_listing_mileage_label_ru($listing['mileage_km']);
    }
    $city = trim((string) ($listing['city'] ?? ''));
    if ($city !== '') {
        $descBits[] = $city;
    }
    $description = $shareTitle;
    if ($descBits !== []) {
        $description .= ' · ' . implode(' · ', $descBits);
    }

    $image = '';
    $photo = trim((string) ($listing['photo_url'] ?? ''));
    if ($photo !== '') {
        $src = ia_listing_photo_src($photo);
        if ($src !== '' && !str_starts_with($src, 'data:image')) {
            $image = ia_absolute_url($src);
        }
    }

    return [
        'url' => $url,
        'title' => $shareTitle,
        'page_title' => $carTitle,
        'description' => $description,
        'image' => $image,
        'share_line' => $shareTitle,
        'share_text' => $shareTitle . "\n" . $url,
    ];
}

/**
 * @param array{title?: string, description?: string, url?: string, image?: string, site_name?: string, type?: string} $meta
 */
function ia_open_graph_meta_html(array $meta): string
{
    $title = trim((string) ($meta['title'] ?? ''));
    $description = trim((string) ($meta['description'] ?? ''));
    $url = trim((string) ($meta['url'] ?? ''));
    $image = trim((string) ($meta['image'] ?? ''));
    $siteName = trim((string) ($meta['site_name'] ?? ''));
    $type = trim((string) ($meta['type'] ?? 'website'));
    if ($type === '') {
        $type = 'website';
    }

    $lines = [];
    if ($title !== '') {
        $lines[] = '<meta property="og:title" content="' . ia_h($title) . '">';
        $lines[] = '<meta name="twitter:title" content="' . ia_h($title) . '">';
    }
    if ($description !== '') {
        $lines[] = '<meta property="og:description" content="' . ia_h($description) . '">';
        $lines[] = '<meta name="description" content="' . ia_h($description) . '">';
        $lines[] = '<meta name="twitter:description" content="' . ia_h($description) . '">';
    }
    if ($url !== '') {
        $lines[] = '<meta property="og:url" content="' . ia_h($url) . '">';
    }
    if ($image !== '') {
        $lines[] = '<meta property="og:image" content="' . ia_h($image) . '">';
        $lines[] = '<meta name="twitter:image" content="' . ia_h($image) . '">';
    }
    if ($siteName !== '') {
        $lines[] = '<meta property="og:site_name" content="' . ia_h($siteName) . '">';
    }
    $lines[] = '<meta property="og:type" content="' . ia_h($type) . '">';
    $lines[] = '<meta name="twitter:card" content="' . ($image !== '' ? 'summary_large_image' : 'summary') . '">';

    return implode("\n", $lines);
}

/** @param array<string, mixed> $row */
function ia_listing_is_vip(array $row): bool
{
    return ia_listing_flag_on($row['is_vip'] ?? 0);
}

/** @param array<string, mixed> $row */
function ia_listing_is_top(array $row): bool
{
    return ia_listing_flag_on($row['is_top'] ?? 0);
}

/** @param array{name?:string,id?:string,class?:string,selected?:string,empty_label?:string|null,min?:int,max?:int,aria_label?:string,required?:bool} $opts */
function ia_render_year_select(array $opts = []): void
{
    $name = (string) ($opts['name'] ?? 'year');
    $id = (string) ($opts['id'] ?? 'fldYear');
    $class = (string) ($opts['class'] ?? 'form-select');
    $emptyLabel = array_key_exists('empty_label', $opts) ? $opts['empty_label'] : 'Все';
    $selected = trim((string) ($opts['selected'] ?? ''));
    $ariaLabel = (string) ($opts['aria_label'] ?? 'Год');
    $required = !empty($opts['required']);
    $minYear = max(1900, min(2100, (int) ($opts['min'] ?? 1950)));
    $maxYear = min(2100, (int) ($opts['max'] ?? ((int) date('Y') + 1)));

    if ($maxYear < $minYear) {
        $maxYear = $minYear;
    }

    echo '<select name="' . ia_h($name) . '" id="' . ia_h($id) . '" class="' . ia_h($class) . '"'
        . ($required ? ' required' : '')
        . ' aria-label="' . ia_h($ariaLabel) . '">';

    if ($emptyLabel !== null) {
        $sel = $selected === '' ? ' selected' : '';
        echo '<option value=""' . $sel . '>' . ia_h((string) $emptyLabel) . '</option>';
    }

    $selectedYear = ($selected !== '' && ctype_digit($selected)) ? (int) $selected : null;
    $hasSelectedInRange = $selectedYear !== null && $selectedYear >= $minYear && $selectedYear <= $maxYear;

    for ($y = $maxYear; $y >= $minYear; $y--) {
        $sel = $hasSelectedInRange && $selectedYear === $y ? ' selected' : '';
        echo '<option value="' . $y . '"' . $sel . '>' . $y . '</option>';
    }

    if ($selectedYear !== null && !$hasSelectedInRange) {
        echo '<option value="' . $selectedYear . '" selected>' . $selectedYear . '</option>';
    }

    echo '</select>';
}

/** VIP / TOP — левый верх карточки объявления. @param array<string, mixed> $row */
function ia_render_listing_card_badges(array $row): void
{
    $showVip = ia_listing_is_vip($row);
    $showTop = ia_listing_is_top($row);
    if (!$showVip && !$showTop) {
        return;
    }
    echo '<div class="ia-listing-badges-overlay">';
    if ($showVip) {
        echo '<span class="ia-badge-vip">VIP</span>';
    }
    if ($showTop) {
        echo '<span class="ia-badge-top" aria-label="TOP">TOP</span>';
    }
    echo '</div>';
}

/** @param array<string, mixed> $row */
function ia_render_listing_views_inline(array $row): void
{
    $viewsN = ia_listing_views_count($row);
    $viewsLabel = ia_listing_views_label_ru($viewsN);
    echo '<span class="ia-listing-views-inline" title="', ia_h($viewsLabel), '" aria-label="', ia_h($viewsLabel), '">';
    echo '<i class="bi bi-eye-fill" aria-hidden="true"></i>';
    echo '<span class="ia-listing-views-inline-num">', ia_h(number_format($viewsN, 0, '.', ' ')), '</span>';
    echo '</span>';
}

/** @param array<string, mixed> $row */
function ia_render_listing_views_badge(array $row): void
{
    $viewsN = ia_listing_views_count($row);
    $viewsLabel = ia_listing_views_label_ru($viewsN);
    echo '<span class="ia-listing-views-badge" title="', ia_h($viewsLabel), '" aria-label="', ia_h($viewsLabel), '">';
    echo '<i class="bi bi-eye-fill" aria-hidden="true"></i>';
    echo '<span class="ia-listing-views-badge-num">', ia_h(number_format($viewsN, 0, '.', ' ')), '</span>';
    echo '</span>';
}

function ia_listing_views_word_ru(int $count): string
{
    $n = abs($count) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) {
        return 'просмотров';
    }
    if ($n1 === 1) {
        return 'просмотр';
    }
    if ($n1 >= 2 && $n1 <= 4) {
        return 'просмотра';
    }

    return 'просмотров';
}

function ia_listing_views_label_ru(int $count): string
{
    $n = max(0, $count);

    return number_format($n, 0, '.', ' ') . ' ' . ia_listing_views_word_ru($n);
}

function ia_listing_catalog_count_word_ru(int $count): string
{
    $n = abs($count) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) {
        return 'автомобилей';
    }
    if ($n1 === 1) {
        return 'автомобиль';
    }
    if ($n1 >= 2 && $n1 <= 4) {
        return 'автомобиля';
    }

    return 'автомобилей';
}

/**
 * Allowed currencies for listings.
 *
 * @return array<string, array{symbol: string, label_ru: string}>
 */
function ia_listing_currencies(): array
{
    return [
        'TJS' => ['symbol' => 'с.', 'label_ru' => 'Сомонӣ (с.)'],
        'RUB' => ['symbol' => '₽',  'label_ru' => 'Рубл (₽)'],
        'USD' => ['symbol' => '$',  'label_ru' => 'Доллар ($)'],
    ];
}

function ia_listing_currency_normalize(?string $code): string
{
    $c = strtoupper(trim((string) $code));
    $list = ia_listing_currencies();

    return isset($list[$c]) ? $c : 'TJS';
}

function ia_listing_currency_symbol(?string $code): string
{
    $c = ia_listing_currency_normalize($code);
    $list = ia_listing_currencies();

    return $list[$c]['symbol'];
}

function ia_listing_currency_label_ru(?string $code): string
{
    $c = ia_listing_currency_normalize($code);
    $list = ia_listing_currencies();

    return $list[$c]['label_ru'];
}

function ia_listing_format_price(float $value, ?string $currencyCode): string
{
    $code = ia_listing_currency_normalize($currencyCode);
    $decimals = ($code === 'USD' || $code === 'RUB') ? 2 : 0;
    $rounded = round($value, $decimals);

    return number_format($rounded, $decimals, '.', ' ') . ' ' . ia_listing_currency_symbol($code);
}

/**
 * Build a stable internal code for body type names.
 */
function ia_listing_body_type_code(string $name): string
{
    $raw = trim($name);
    if ($raw === '') {
        return '';
    }

    $lower = function_exists('mb_strtolower') ? mb_strtolower($raw) : strtolower($raw);
    $aliases = [
        'sedan' => 'sedan',
        'седан' => 'sedan',
        'hatchback' => 'hatchback',
        'хэтчбек' => 'hatchback',
        'suv' => 'suv',
        'внедорожник' => 'suv',
        'crossover' => 'crossover',
        'кроссовер' => 'crossover',
        'кроссовер премиум' => 'crossover',
        'wagon' => 'wagon',
        'универсал' => 'wagon',
        'coupe' => 'coupe',
        'купе' => 'coupe',
        'cabrio' => 'cabrio',
        'кабриолет' => 'cabrio',
        'pickup' => 'pickup',
        'пикап' => 'pickup',
        'van' => 'van',
        'минивэн' => 'van',
        'truck' => 'truck',
        'грузовой' => 'truck',
        'коммерческий' => 'truck',
        'bus' => 'bus',
        'автобус' => 'bus',
        'ev' => 'ev',
        'электрокар' => 'ev',
        'электромобиль' => 'ev',
        'sport' => 'sport',
        'спорткар' => 'sport',
        'спорт' => 'sport',
        'motorcycle' => 'motorcycle',
        'мотоцикл' => 'motorcycle',
        'мотосикл' => 'motorcycle',
        'other' => 'other',
        'другое' => 'other',
    ];
    if (isset($aliases[$lower])) {
        return $aliases[$lower];
    }

    $latin = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $raw);
    if (!is_string($latin)) {
        $latin = $raw;
    }
    $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $latin), '-'));
    if ($slug !== '') {
        return $slug;
    }

    return 'cat_' . substr(sha1($raw), 0, 12);
}

/**
 * Dynamic map of supported listing body types.
 *
 * @return array<string,string>
 */
function ia_listing_body_types_map(): array
{
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }

    $base = [
        'sedan' => 'Седан',
        'hatchback' => 'Хэтчбек',
        'suv' => 'Внедорожник',
        'crossover' => 'Кроссовер премиум',
        'wagon' => 'Универсал',
        'coupe' => 'Купе',
        'cabrio' => 'Кабриолет',
        'pickup' => 'Пикап',
        'van' => 'Минивэн',
        'truck' => 'Коммерческий',
        'bus' => 'Автобус',
        'ev' => 'Электромобиль',
        'sport' => 'Спорт',
        'motorcycle' => 'Мотосикл',
        'other' => 'Другое',
    ];

    if (is_file(IA_ROOT . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'home_quick_categories.php')) {
        require_once IA_ROOT . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'home_quick_categories.php';
        if (function_exists('ia_listing_form_body_type_options')) {
            foreach (ia_listing_form_body_type_options() as $code => $label) {
                $base[$code] = $label;
            }
        }
    }

    if (!function_exists('ia_db')) {
        $cached = $base;
        return $cached;
    }

    try {
        $pdo = ia_db();
        $rows = $pdo->query('SELECT name FROM vehicle_categories ORDER BY sort_order ASC, id ASC')->fetchAll() ?: [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $code = ia_listing_body_type_code($name);
            if ($code === '' || isset($base[$code])) {
                continue;
            }
            $base[$code] = $name;
        }
    } catch (Throwable $e) {
        // Keep base map when table is unavailable during setup/migrations.
    }

    $cached = $base;
    return $cached;
}

function ia_listing_body_label_ru_pub(string $code): string
{
    $map = ia_listing_body_types_map();
    return $map[$code] ?? '';
}

/** Русская подпись статуса объявления для личного кабинета и карточек. */
/** Bootstrap Icons class for car spec label on listing page. */
function ia_car_spec_icon(string $label): string
{
    return match ($label) {
        'Кузов' => 'bi-truck',
        'Год выпуска' => 'bi-calendar3',
        'Цвет' => 'bi-palette',
        'Привод' => 'bi-diagram-3',
        'Объём двигателя' => 'bi-speedometer2',
        'Пробег' => 'bi-signpost-2',
        'Турбина' => 'bi-lightning-charge',
        'Состояние' => 'bi-stars',
        'Вид топлива' => 'bi-fuel-pump',
        'Растаможен в РТ' => 'bi-shield-check',
        'Коробка передач' => 'bi-gear-wide-connected',
        'Лицензия на такси' => 'bi-taxi-front',
        'Город' => 'bi-geo-alt',
        'Местоположение' => 'bi-pin-map-fill',
        'VIN' => 'bi-upc-scan',
        default => 'bi-info-circle',
    };
}

function ia_pub_listing_status_ru(string $status): string
{
    return match ($status) {
        'approved' => 'Активный',
        'pending' => 'На проверке',
        'sold' => 'Продано',
        'archived' => 'Скрыто из каталога',
        'rejected' => 'Заблокировано',
        default => $status !== '' ? $status : '—',
    };
}

/**
 * @return list<string>
 */
function ia_listing_status_codes(): array
{
    return ['pending', 'approved', 'sold', 'archived', 'rejected'];
}

function ia_pub_listing_status_css_class(string $status): string
{
    return match ($status) {
        'approved' => 'is-active',
        'pending' => 'is-pending',
        'sold' => 'is-sold',
        'archived' => 'is-expired',
        'rejected' => 'is-blocked',
        default => 'is-expired',
    };
}

function ia_listing_status_admin_badge_class(string $status): string
{
    return 'ia-badge-status--' . match ($status) {
        'approved' => 'approved',
        'pending' => 'pending',
        'sold' => 'sold',
        'archived' => 'archived',
        'rejected' => 'rejected',
        default => 'archived',
    };
}

function ia_client_ip(): string
{
    if (!empty($_SERVER['IA_CLIENT_IP'])) {
        return (string) $_SERVER['IA_CLIENT_IP'];
    }

    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * @param array<string, mixed> $config
 */
function ia_recaptcha_enabled(array $config): bool
{
    return \InnovaAuto\Security\Recaptcha::isEnabled($config);
}
