<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require IA_ROOT . '/includes/env_loader.php';
ia_load_dotenv(IA_ROOT . '/.env');
require IA_ROOT . '/config/config.php';
require IA_ROOT . '/includes/supabase_storage.php';

$cfg = ia_supabase_storage_config();
echo 'enabled=' . ($cfg['enabled'] ? 'yes' : 'no') . PHP_EOL;
echo 'bucket=' . $cfg['bucket'] . PHP_EOL;
echo 'url=' . $cfg['url'] . PHP_EOL;

$local = IA_ROOT . '/uploads/listings/d59da86a81c93b93f85ad1d7bbb2318e.webp';
if (!is_file($local)) {
    fwrite(STDERR, "local test file missing: $local\n");
    exit(1);
}

$objectPath = 'listings/d59da86a81c93b93f85ad1d7bbb2318e.webp';
$ok = ia_supabase_storage_upload_file($local, $objectPath, 'image/webp');
echo 'upload=' . ($ok ? 'OK' : 'FAIL') . PHP_EOL;
echo 'public=' . ia_supabase_storage_public_url($objectPath) . PHP_EOL;
