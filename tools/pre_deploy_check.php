<?php

declare(strict_types=1);

/**
 * Pre-deploy gate (Step 9) — run locally before push/deploy.
 *
 * Usage:
 *   php tools/pre_deploy_check.php
 *   php tools/pre_deploy_check.php --base-url=http://localhost/Test%20innovaauto
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('IA_ROOT', dirname(__DIR__));

/** @var list<array{ok: bool, name: string, detail: string}> */
$results = [];

function check(string $name, bool $ok, string $detail = ''): void
{
    global $results;
    $results[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
    $flag = $ok ? 'PASS' : 'FAIL';
    $line = "[{$flag}] {$name}";
    if ($detail !== '') {
        $line .= ' — ' . $detail;
    }
    echo $line . PHP_EOL;
}

function read_arg(string $prefix): ?string
{
    global $argv;
    foreach ($argv ?? [] as $arg) {
        if (str_starts_with($arg, $prefix)) {
            return substr($arg, strlen($prefix));
        }
    }

    return null;
}

function ia_pre_shell_exec_available(): bool
{
    if (!function_exists('exec')) {
        return false;
    }
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

    return !in_array('exec', $disabled, true);
}

// --- 1. PHP syntax ---
$syntaxErrors = [];
if (ia_pre_shell_exec_available()) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(IA_ROOT, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
            continue;
        }
        $out = [];
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
        if ($code !== 0) {
            $syntaxErrors[] = str_replace(IA_ROOT . DIRECTORY_SEPARATOR, '', $path) . ': ' . implode(' ', $out);
        }
    }
    check('PHP syntax (all .php)', $syntaxErrors === [], $syntaxErrors === [] ? 'exec scan OK' : implode('; ', array_slice($syntaxErrors, 0, 3)));
} else {
    check('PHP syntax (all .php)', true, 'skipped (exec disabled on host — use deploy.sh find -exec)');
}

// --- 2. .htaccess ---
check('.htaccess exists', is_file(IA_ROOT . '/.htaccess'));

// --- 3. Database connection ---
$dbOk = false;
$dbDetail = '';
try {
    require_once IA_ROOT . '/includes/env_loader.php';
    ia_load_dotenv();
    require_once IA_ROOT . '/db.php';
    require_once IA_ROOT . '/config/database.php';
    $db = ia_db();
    $row = $db->query('SELECT 1 AS ok')->fetch();
    $dbOk = (int) ($row['ok'] ?? 0) === 1;
    $info = ia_db_connection_info();
    $dbDetail = ($info['label'] ?? '') . ' @ ' . ($info['host'] ?? '');
} catch (Throwable $e) {
    $dbDetail = $e->getMessage();
}
check('Database connection (SELECT 1)', $dbOk, $dbDetail);

// --- 4. Listings from DB ---
$listingsOk = false;
$listingsDetail = '';
if ($dbOk) {
    try {
        require_once IA_ROOT . '/includes/public_queries.php';
        $latest = ia_pub_listings_latest($db, 3);
        $listingsOk = is_array($latest);
        $listingsDetail = 'rows=' . count($latest);
    } catch (Throwable $e) {
        $listingsDetail = $e->getMessage();
    }
}
check('Listings query from database', $listingsOk, $listingsDetail);

// --- 5. Uploads directory writable ---
$uploadsDir = IA_ROOT . '/uploads/listings';
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0775, true);
}
check('uploads/listings writable', is_dir($uploadsDir) && is_writable($uploadsDir));

// --- 6. No hardcoded absolute local paths in production code ---
$badPathPatterns = [
    '#C:\\\\xampp#i',
    '#C:/xampp#i',
    '#htdocs[\\\\/]Test innovaauto#i',
    '#localhost/Auto%201#i',
    '#localhost/Auto 1#i',
];
$badPathSkip = [
    'config/local.example.php',
    'tools/test_http_login.php',
    'tools/test_login_profile_flow.php',
];
$badPathFiles = [];
$scanDirs = ['admin', 'includes', 'config', 'assets', 'src'];
foreach ($scanDirs as $dir) {
    $base = IA_ROOT . '/' . $dir;
    if (!is_dir($base)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) {
            continue;
        }
        $ext = $f->getExtension();
        if (!in_array($ext, ['php', 'js', 'css'], true)) {
            continue;
        }
        $content = file_get_contents($f->getPathname());
        if ($content === false) {
            continue;
        }
        $rel = str_replace(IA_ROOT . DIRECTORY_SEPARATOR, '', $f->getPathname());
        $relNorm = str_replace('\\', '/', $rel);
        $baseName = basename($relNorm);
        if (in_array($relNorm, $badPathSkip, true) || in_array($baseName, ['local.example.php'], true)) {
            continue;
        }
        foreach ($badPathPatterns as $pat) {
            if (preg_match($pat, $content)) {
                $badPathFiles[] = str_replace(IA_ROOT . DIRECTORY_SEPARATOR, '', $f->getPathname());
                break;
            }
        }
    }
}
foreach (glob(IA_ROOT . '/*.php') ?: [] as $rootPhp) {
    $content = file_get_contents($rootPhp);
    if ($content === false) {
        continue;
    }
    foreach ($badPathPatterns as $pat) {
        if (preg_match($pat, $content)) {
            $badPathFiles[] = basename($rootPhp);
            break;
        }
    }
}
check('No absolute local paths in production code', $badPathFiles === [], $badPathFiles === [] ? 'clean' : implode(', ', $badPathFiles));

// --- 7. APP_DEBUG / production debug flags in .env.hostinger.example ---
$hostingerExample = file_get_contents(IA_ROOT . '/.env.hostinger.example') ?: '';
check('.env.hostinger.example has no APP_DEBUG=true', !preg_match('/^APP_DEBUG\s*=\s*true/mi', $hostingerExample));

// --- 8. HTTP checks (optional base URL) ---
$baseUrl = read_arg('--base-url=') ?: read_arg('--base=');
if ($baseUrl === null) {
    // Auto-detect common XAMPP paths
    foreach ([
        'http://localhost/Test%20innovaauto',
        'http://127.0.0.1/Test%20innovaauto',
        'http://localhost/innovaauto',
    ] as $candidate) {
        $code = ia_pre_http_status($candidate . '/');
        if ($code >= 200 && $code < 500) {
            $baseUrl = rtrim($candidate, '/');
            break;
        }
    }
}

if ($baseUrl !== null && $baseUrl !== '') {
    $baseUrl = rtrim($baseUrl, '/');
    echo PHP_EOL . "HTTP checks against: {$baseUrl}" . PHP_EOL;

    $pages = [
        'homepage' => '/',
        'catalog' => '/catalog.php',
        'login' => '/login.php',
        'register' => '/register.php',
        'admin_login' => '/admin/login.php',
        'favorites' => '/favorites.php',
        'compare' => '/compare.php',
        'add_listing' => '/add-listing.php',
    ];
    foreach ($pages as $label => $path) {
        $code = ia_pre_http_status($baseUrl . $path);
        check("HTTP {$label}", $code >= 200 && $code < 500, "status={$code}");
    }

    // CSS/JS load
    $homeBody = ia_pre_http_get($baseUrl . '/');
    $cssOk = str_contains($homeBody, 'site.css') || str_contains($homeBody, 'site.min.css');
    check('Homepage references CSS', $cssOk);

    // Search/filter page
    $catalogCode = ia_pre_http_status($baseUrl . '/catalog.php?body_type=sedan');
    check('Catalog filter URL', $catalogCode >= 200 && $catalogCode < 500, "status={$catalogCode}");
} else {
    check('HTTP checks (local)', false, 'pass --base-url=http://localhost/YourProject or start Apache');
}

// Summary
echo PHP_EOL . '=== SUMMARY ===' . PHP_EOL;
$passed = count(array_filter($results, static fn ($r) => $r['ok']));
$total = count($results);
$failed = $total - $passed;
echo "Passed: {$passed}/{$total}, Failed: {$failed}" . PHP_EOL;

exit($failed > 0 ? 1 : 0);

function ia_pre_http_status(string $url): int
{
    $ctx = stream_context_create([
        'http' => ['timeout' => 15, 'ignore_errors' => true],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $headers = @get_headers($url, true, $ctx);
    if ($headers === false) {
        return 0;
    }
    $statusLine = is_array($headers[0] ?? null) ? ($headers[0][0] ?? '') : (string) ($headers[0] ?? '');
    if (preg_match('/\s(\d{3})\s/', $statusLine, $m)) {
        return (int) $m[1];
    }

    return 0;
}

function ia_pre_http_get(string $url): string
{
    $ctx = stream_context_create([
        'http' => ['timeout' => 15, 'ignore_errors' => true],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    return (string) @file_get_contents($url, false, $ctx);
}
