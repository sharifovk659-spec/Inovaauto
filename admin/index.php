<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';

if (ia_current_user() !== null) {
    ia_redirect(ia_admin_url('dashboard.php'));
}

ia_redirect(ia_admin_url('login.php'));
