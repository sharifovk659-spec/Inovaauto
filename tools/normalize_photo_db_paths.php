<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require IA_ROOT . '/includes/env_loader.php';
ia_load_dotenv(IA_ROOT . '/.env');
require IA_ROOT . '/config/database.php';
require IA_ROOT . '/includes/supabase_storage.php';

$p = ia_db();
foreach ($p->query('SELECT id, photo_url FROM ad_listings')->fetchAll() as $r) {
    $key = ia_supabase_storage_file_key((string) $r['photo_url']);
    if ($key !== '' && $key !== $r['photo_url']) {
        $p->prepare('UPDATE ad_listings SET photo_url = ? WHERE id = ?')->execute([$key, $r['id']]);
        echo 'listing ' . $r['id'] . ' -> ' . $key . PHP_EOL;
    }
}
foreach ($p->query('SELECT id, stored_path FROM ad_listing_media')->fetchAll() as $r) {
    $key = ia_supabase_storage_file_key((string) $r['stored_path']);
    if ($key !== '' && $key !== $r['stored_path']) {
        $p->prepare('UPDATE ad_listing_media SET stored_path = ? WHERE id = ?')->execute([$key, $r['id']]);
        echo 'media ' . $r['id'] . ' -> ' . $key . PHP_EOL;
    }
}
