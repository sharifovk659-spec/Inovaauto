<?php

declare(strict_types=1);

/** @deprecated Используйте add-listing.php — редирект. */
define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';
ia_redirect(ia_public_url('add-listing.php'));
