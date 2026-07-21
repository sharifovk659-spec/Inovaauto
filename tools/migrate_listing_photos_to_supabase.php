<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require IA_ROOT . '/includes/env_loader.php';
ia_load_dotenv(IA_ROOT . '/.env');
require IA_ROOT . '/config/database.php';
require IA_ROOT . '/includes/supabase_storage.php';

if (!ia_supabase_storage_enabled()) {
    fwrite(STDERR, "Storage disabled\n");
    exit(1);
}

$result = ia_supabase_storage_sync_all_listing_photos(ia_db());
echo 'uploaded=' . $result['uploaded'] . ' updated=' . $result['updated'] . ' errors=' . $result['errors'] . PHP_EOL;
