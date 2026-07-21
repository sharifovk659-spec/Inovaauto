<?php

declare(strict_types=1);

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';

ia_platform_logout();
ia_redirect(ia_public_url('index.php'));
