<?php

declare(strict_types=1);

/**
 * Публичный сайт: отдельная сессия от админ-панели.
 *
 * Требует на сервере (вместе с этим файлом):
 *   includes/edge_http.php  — Cloudflare / HTTPS (если нет, сайт всё равно работает)
 *   includes/helpers.php
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
    $s = $config['platform_session'];
    session_name($s['name']);
    session_set_cookie_params([
        'lifetime' => (int) $s['lifetime'],
        'path' => '/',
        'domain' => '',
        'secure' => (bool) $s['secure'],
        'httponly' => (bool) $s['httponly'],
        'samesite' => (string) $s['samesite'],
    ]);
    session_start();
}

require_once IA_ROOT . '/includes/site_settings.php';
require_once IA_ROOT . '/includes/promotion_monetization.php';
require_once IA_ROOT . '/includes/promotion_billing.php';
require_once IA_ROOT . '/includes/public_auth.php';
require_once IA_ROOT . '/includes/public_layout.php';

if (function_exists('ia_edge_send_http_headers')) {
    ia_edge_send_http_headers();
} elseif (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
