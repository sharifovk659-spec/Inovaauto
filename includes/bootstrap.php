<?php

declare(strict_types=1);

/**
 * Админ-панель: отдельная сессия от публичного сайта.
 */
if (!defined('IA_ROOT')) {
    define('IA_ROOT', dirname(__DIR__));
}

require_once IA_ROOT . '/includes/env_loader.php';
ia_load_dotenv(IA_ROOT . DIRECTORY_SEPARATOR . '.env');

$iaEdgeHttp = IA_ROOT . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'edge_http.php';
if (is_file($iaEdgeHttp)) {
    require_once $iaEdgeHttp;
}

$autoload = IA_ROOT . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
if (!class_exists(\InnovaAuto\Security\Csrf::class, false)) {
    require_once IA_ROOT . '/src/Security/Csrf.php';
    require_once IA_ROOT . '/src/Security/Recaptcha.php';
}

require_once IA_ROOT . '/db.php';
require_once IA_ROOT . '/config/database.php';

$config = ia_config();

require_once IA_ROOT . '/includes/helpers.php';

if (function_exists('ia_edge_http_bootstrap')) {
    ia_edge_http_bootstrap();
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $session = array_merge(
        [
            'name' => 'IA_ADMIN_SESSID',
            'lifetime' => 3600,
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ],
        $config['session'] ?? []
    );

    session_name((string) $session['name']);
    session_set_cookie_params([
        'lifetime' => (int) $session['lifetime'],
        'path' => '/',
        'domain' => '',
        'secure' => (bool) $session['secure'],
        'httponly' => (bool) $session['httponly'],
        'samesite' => (string) $session['samesite'],
    ]);
    session_start();
}

require_once IA_ROOT . '/includes/site_settings.php';
require_once IA_ROOT . '/includes/promotion_monetization.php';
require_once IA_ROOT . '/includes/promotion_billing.php';
require_once IA_ROOT . '/includes/auth.php';

ia_try_restore_session_from_cookie();

if (function_exists('ia_edge_send_http_headers')) {
    ia_edge_send_http_headers();
} elseif (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

if (PHP_SAPI !== 'cli') {
    require_once IA_ROOT . '/includes/listing_lifecycle.php';
    ia_platform_maybe_run_listing_idle_maintenance(ia_db());
}
