<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/env_loader.php';
ia_load_dotenv();

/**
 * Настройки приложения.
 *
 * MySQL (XAMPP): IA_DB_DRIVER=mysql, IA_DB_HOST, IA_DB_PORT, IA_DB_NAME, IA_DB_USER, IA_DB_PASS
 * Supabase: IA_SUPABASE_URL + IA_SUPABASE_DB_PASSWORD  (ё IA_DATABASE_URL)
 *           ё config/local.php — см. config/local.example.php
 *
 * Файл .env в корне (скопируйте из .env.example). Переопределение: config/local.php
 */
$supabaseUrl = (string) (ia_env('IA_SUPABASE_URL') ?: ia_env('VITE_SUPABASE_URL') ?: '');
$supabaseRef = (string) (ia_env('IA_SUPABASE_PROJECT_REF') ?: '');
if ($supabaseRef === '' && preg_match('#https?://([a-z0-9]+)\.supabase\.co#i', $supabaseUrl, $m)) {
    $supabaseRef = $m[1];
}

$configuredDriver = strtolower((string) (ia_env('IA_DB_DRIVER') ?: ''));
$dbHostEnv = (string) (ia_env('IA_DB_HOST') ?: '');

$dbFromUrl = null;
$usePgsqlDriver = in_array($configuredDriver, ['pgsql', 'postgres', 'postgresql'], true);
if ($configuredDriver === '') {
    $usePgsqlDriver = null;
} elseif ($configuredDriver === 'mysql') {
    $usePgsqlDriver = false;
} elseif (in_array($configuredDriver, ['supabase', 'supabase_rest', 'rest'], true)) {
    $usePgsqlDriver = false;
}

if ($usePgsqlDriver !== false) {
    $dbFromUrl = ia_parse_postgres_url((string) (ia_env('IA_DATABASE_URL') ?: ia_env('DATABASE_URL') ?: ''));
    if ($configuredDriver === '') {
        $usePgsqlDriver = $dbFromUrl !== null
            || str_contains($dbHostEnv, 'supabase.co')
            || str_contains($dbHostEnv, 'pooler.supabase.com');
    }
}

$isSupabase = $supabaseRef !== ''
    || str_contains($dbHostEnv, 'supabase.co')
    || ($dbFromUrl !== null && str_contains($dbFromUrl['host'], 'supabase.co'));

$useMysqlDriver = $configuredDriver === 'mysql'
    || ($configuredDriver === '' && !$isSupabase);

$defaultDriver = $useMysqlDriver ? 'mysql' : ($isSupabase ? 'pgsql' : 'mysql');
if ($configuredDriver !== '') {
    $defaultDriver = $configuredDriver;
}
$usePgsqlDriver = in_array($defaultDriver, ['pgsql', 'postgres', 'postgresql'], true);
$useMysqlDriver = $defaultDriver === 'mysql';

$defaultPort = $useMysqlDriver ? '3306' : ($isSupabase ? '5432' : '5432');
$defaultHost = $useMysqlDriver
    ? '127.0.0.1'
    : ($isSupabase && $supabaseRef !== '' ? 'db.' . $supabaseRef . '.supabase.co' : '127.0.0.1');
$defaultName = $useMysqlDriver ? 'innovaauto' : ($isSupabase ? 'postgres' : 'innovaauto');
$defaultUser = $useMysqlDriver ? 'root' : ($isSupabase ? 'postgres' : 'root');

$dbPass = '';
if (ia_env('IA_DB_PASS') !== null && ia_env('IA_DB_PASS') !== '') {
    $dbPass = (string) ia_env('IA_DB_PASS');
} elseif ($usePgsqlDriver && ia_env('IA_SUPABASE_DB_PASSWORD') !== null && ia_env('IA_SUPABASE_DB_PASSWORD') !== '') {
    $dbPass = (string) ia_env('IA_SUPABASE_DB_PASSWORD');
} elseif ($dbFromUrl !== null) {
    $dbPass = $dbFromUrl['pass'];
}

$dbSslmode = (string) (ia_env('IA_DB_SSLMODE') ?: '');
if ($dbSslmode === '' && $dbFromUrl !== null && $dbFromUrl['sslmode'] !== '') {
    $dbSslmode = $dbFromUrl['sslmode'];
} elseif ($dbSslmode === '' && $isSupabase) {
    // prefer: XAMPP/Windows libpq зиёда вақт бо require хато медиҳад
    $dbSslmode = 'prefer';
}

$autoEnsureDefault = $useMysqlDriver ? 'true' : ($isSupabase ? 'false' : 'true');

$cfg = [
    'db' => [
        'driver' => $defaultDriver,
        'host' => $usePgsqlDriver && $dbFromUrl !== null
            ? $dbFromUrl['host']
            : (ia_env('IA_DB_HOST') ?: $defaultHost),
        'port' => (string) ($usePgsqlDriver && $dbFromUrl !== null
            ? $dbFromUrl['port']
            : (ia_env('IA_DB_PORT') ?: $defaultPort)),
        'name' => $usePgsqlDriver && $dbFromUrl !== null
            ? $dbFromUrl['name']
            : (ia_env('IA_DB_NAME') ?: $defaultName),
        'user' => $usePgsqlDriver && $dbFromUrl !== null
            ? $dbFromUrl['user']
            : (ia_env('IA_DB_USER') ?: $defaultUser),
        'pass' => $dbPass,
        'sslmode' => $dbSslmode,
        'charset' => 'utf8mb4',
        'auto_ensure_schema' => filter_var(
            ia_env('IA_DB_AUTO_ENSURE') ?: $autoEnsureDefault,
            FILTER_VALIDATE_BOOLEAN
        ),
        'seed_default_admin_if_empty' => filter_var(
            ia_env('IA_DB_SEED_ADMIN') ?: ($useMysqlDriver ? 'true' : ($isSupabase ? 'false' : 'true')),
            FILTER_VALIDATE_BOOLEAN
        ),
        'auto_ensure_platform_schema' => filter_var(
            ia_env('IA_DB_PLATFORM_SCHEMA') ?: $autoEnsureDefault,
            FILTER_VALIDATE_BOOLEAN
        ),
        'seed_platform_demo_if_empty' => filter_var(
            ia_env('IA_SEED_PLATFORM_DEMO') ?: 'false',
            FILTER_VALIDATE_BOOLEAN
        ),
    ],
    'supabase' => [
        'url' => $supabaseUrl,
        'anon_key' => (string) (ia_env('IA_SUPABASE_ANON_KEY')
            ?: ia_env('VITE_SUPABASE_PUBLISHABLE_KEY')
            ?: ''),
        'service_key' => (string) (ia_env('IA_SUPABASE_SERVICE_KEY') ?: ''),
        'secret_key' => (string) (ia_env('IA_SUPABASE_SECRET_KEY') ?: ia_env('IA_SUPABASE_SERVICE_KEY') ?: ''),
        'storage_bucket' => strtolower((string) (ia_env('IA_SUPABASE_STORAGE_BUCKET') ?: 'photos')),
        'storage_enabled' => filter_var(
            ia_env('IA_SUPABASE_STORAGE_ENABLED') ?: 'true',
            FILTER_VALIDATE_BOOLEAN
        ),
        'project_ref' => $supabaseRef,
    ],
    'app' => [
        'base_url' => rtrim((string) (ia_env('IA_BASE_URL') ?: ''), '/'),
        'admin_path' => '/admin',
        'show_dev_login_hint' => filter_var(
            getenv('IA_SHOW_DEV_LOGIN_HINT') ?: 'false',
            FILTER_VALIDATE_BOOLEAN
        ),
        'show_listing_save_errors' => filter_var(
            getenv('IA_SHOW_LISTING_SAVE_ERRORS') ?: 'false',
            FILTER_VALIDATE_BOOLEAN
        ),
        'dashboard_chart_days' => max(7, min(90, (int) (getenv('IA_DASHBOARD_CHART_DAYS') ?: '14'))),
    ],
    'session' => [
        'name' => 'IA_ADMIN_SESSID',
        'lifetime' => 3600,
        'secure' => filter_var(getenv('IA_SESSION_SECURE') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'httponly' => true,
        'samesite' => 'Lax',
    ],
    'platform_session' => [
        'name' => (string) (getenv('IA_PLATFORM_SESSION_NAME') ?: 'IA_PLATFORM_SID'),
        'lifetime' => max(60, (int) (getenv('IA_PLATFORM_SESSION_LIFETIME') ?: '1209600')),
        'secure' => filter_var(getenv('IA_SESSION_SECURE') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'httponly' => true,
        'samesite' => 'Lax',
    ],
    'recaptcha' => [
        'site_key' => (string) (getenv('IA_RECAPTCHA_SITE_KEY') ?: ''),
        'secret' => (string) (getenv('IA_RECAPTCHA_SECRET') ?: ''),
    ],
    'mail' => [
        'from' => (string) (getenv('IA_MAIL_FROM') ?: 'noreply@innovaauto.local'),
    ],
    'security' => [
        'max_login_attempts' => 5,
        'lockout_minutes' => 15,
        'remember_days' => 30,
        'admin_idle_seconds' => max(0, (int) (getenv('IA_ADMIN_IDLE_SECONDS') ?: '1800')),
        'reset_token_minutes' => 60,
        'log_reset_link_instead_of_mail' => filter_var(
            getenv('IA_DEBUG_RESET_LOG') ?: 'true',
            FILTER_VALIDATE_BOOLEAN
        ),
    ],
];

$envDb = $cfg['db'];

$localPath = __DIR__ . DIRECTORY_SEPARATOR . 'local.php';
if (is_file($localPath)) {
    /** @var array<string, mixed> $local */
    $local = require $localPath;
    $cfg = array_replace_recursive($cfg, $local);
    // Supabase аз .env — local.php набояд host/user/pass-ро ба PostgreSQL локалӣ иваз кунад
    if ($isSupabase) {
        $connectionKeys = ['driver', 'host', 'port', 'name', 'user', 'pass', 'sslmode'];
        foreach ($connectionKeys as $key) {
            if (array_key_exists($key, $envDb)) {
                $cfg['db'][$key] = $envDb[$key];
            }
        }
    }
}

$driverFinal = strtolower((string) ($cfg['db']['driver'] ?? ''));
$hostFinal = (string) ($cfg['db']['host'] ?? '');
$isPgDriverFinal = in_array($driverFinal, ['pgsql', 'postgres', 'postgresql'], true);
if ($isSupabase && $isPgDriverFinal) {
    $isPgHost = str_contains($hostFinal, 'supabase.co') || str_contains($hostFinal, 'pooler.supabase.com');
    if (!$isPgHost) {
        throw new RuntimeException(
            'Supabase танзим шудааст (IA_SUPABASE_URL), аммо host = ' . $hostFinal
            . '. Барои PostgreSQL pooler/host Supabase истифода баред, ё IA_DB_DRIVER=mysql барои Hostinger.'
        );
    }
}

return $cfg;
