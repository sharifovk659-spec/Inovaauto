<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';

$bid = (int) ($_GET['brand_id'] ?? 0);
$dest = 'catalog.php?view=models' . ($bid > 0 ? '&brand_id=' . $bid : '');
ia_redirect(ia_admin_url($dest));
