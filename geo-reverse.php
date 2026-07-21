<?php

declare(strict_types=1);

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/listing_geo.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$lat = filter_var($_GET['lat'] ?? '', FILTER_VALIDATE_FLOAT);
$lng = filter_var($_GET['lng'] ?? '', FILTER_VALIDATE_FLOAT);

if ($lat === false || $lng === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_coords'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = ia_listing_geo_reverse_geocode((float) $lat, (float) $lng);
if ($result === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'city' => $result['city'],
    'place' => $result['place'],
], JSON_UNESCAPED_UNICODE);
