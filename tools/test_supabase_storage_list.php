<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require IA_ROOT . '/includes/env_loader.php';
ia_load_dotenv(IA_ROOT . '/.env');
require IA_ROOT . '/config/database.php';
require IA_ROOT . '/includes/supabase_storage.php';

$cfg = ia_supabase_storage_config();
$url = $cfg['url'] . '/storage/v1/object/list/' . $cfg['bucket'];
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $cfg['secret_key'],
        'apikey: ' . $cfg['secret_key'],
        'Content-Type: application/json',
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['prefix' => '', 'limit' => 1000]),
]);
$items = json_decode((string) curl_exec($ch), true) ?: [];
curl_close($ch);

$files = 0;
$dirs = 0;
foreach ($items as $item) {
    if (is_array($item['metadata'] ?? null)) {
        $files++;
    } else {
        $dirs++;
    }
}
echo "bucket root: files=$files dirs=$dirs\n";

$p = ia_db();
echo "\nDB listings:\n";
foreach ($p->query('SELECT id, photo_url FROM ad_listings ORDER BY id')->fetchAll() as $r) {
    $url = ia_supabase_storage_resolve_photo_url((string) $r['photo_url']);
    $ok = $url && ia_supabase_storage_object_exists(ia_supabase_storage_object_path((string) $r['photo_url']));
    echo $r['id'] . ': ' . $r['photo_url'] . ' => ' . ($ok ? 'OK' : 'MISSING') . "\n";
}
