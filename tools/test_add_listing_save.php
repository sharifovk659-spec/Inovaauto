<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/listing_media.php';
require_once IA_ROOT . '/includes/listing_geo.php';
require_once IA_ROOT . '/includes/db_compat.php';

$pdo = ia_db();
$uid = (int) $pdo->query('SELECT id FROM platform_users ORDER BY id ASC LIMIT 1')->fetchColumn();
if ($uid < 1) {
    fwrite(STDERR, "no platform user\n");
    exit(1);
}

$post = [
    'listing_geo_lat' => '38.58734',
    'listing_geo_lng' => '68.72904',
    'listing_geo_captured_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c'),
    'listing_geo_accuracy_m' => '124',
];

$geo = ia_listing_geo_for_insert_from_post($post);
echo 'geo=' . json_encode($geo, JSON_UNESCAPED_UNICODE) . PHP_EOL;
if ($geo === null) {
    echo "GEO FAILED\n";
    exit(1);
}

$pdo->beginTransaction();
try {
    $insertSql = "INSERT INTO ad_listings (user_id, photo_url, brand, model, vin, description, model_year, mileage_km, city, body_type, fuel_type, transmission, price, seller_name, availability, status, is_vip, is_top,
            color, drive_type, engine_volume, has_turbo, condition_state, customs_cleared, taxi_license, prepayment_amount, currency,
            listing_geo_lat, listing_geo_lng, listing_geo_captured_at, listing_geo_accuracy_m)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id";
    $ins = $pdo->prepare($insertSql);
    $ins->execute([
        $uid,
        null,
        'Toyota',
        'Corolla',
        null,
        null,
        2026,
        5463,
        'Душанбе',
        '',
        '',
        '',
        4563.0,
        'Test Seller',
        'in_stock',
        1,
        0,
        '',
        '',
        '',
        0,
        '',
        0,
        0,
        null,
        'TJS',
        $geo['lat'],
        $geo['lng'],
        $geo['captured_at'],
        $geo['accuracy'],
    ]);
    $newId = (int) $ins->fetchColumn();
    echo "insert id=$newId\n";
    ia_listing_media_insert($pdo, $newId, 'image', 'test_fake.png', 0);
    $pdo->rollBack();
    echo "OK rolled back\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo 'ERR: ' . $e->getMessage() . PHP_EOL;
    if ($e instanceof PDOException) {
        print_r($e->errorInfo);
    }
    exit(1);
}
