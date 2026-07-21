<?php

declare(strict_types=1);

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/google_auth.php';

if (ia_platform_current_user() !== null) {
    ia_redirect(ia_public_url('index.php'));
}

$redirect = ia_public_safe_redirect_full((string) ($_GET['redirect'] ?? 'index.php'));
ia_google_oauth_start($redirect);
