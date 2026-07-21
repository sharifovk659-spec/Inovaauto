<?php

declare(strict_types=1);

/**
 * Validates listing geolocation POST fields (browser Geolocation API).
 * Rejects out-of-range coordinates, forged timestamps, and junk accuracy values.
 *
 * @return array{lat: float, lng: float, captured_at: string, accuracy: float|null}|null
 */
function ia_listing_geo_normalize_coord(mixed $raw): ?float
{
    if ($raw === null || $raw === '') {
        return null;
    }
    if (is_int($raw) || is_float($raw)) {
        $v = (float) $raw;
    } else {
        $s = trim(str_replace(',', '.', (string) $raw));
        if ($s === '' || !is_numeric($s)) {
            return null;
        }
        $v = (float) $s;
    }
    if (!is_finite($v)) {
        return null;
    }

    return $v;
}

function ia_listing_geo_parse_captured_at(string $tsRaw): ?\DateTimeImmutable
{
    $tsRaw = trim($tsRaw);
    if ($tsRaw === '') {
        return null;
    }
    try {
        return (new \DateTimeImmutable($tsRaw))->setTimezone(new \DateTimeZone('UTC'));
    } catch (\Throwable $e) {
    }
    $normalized = preg_replace('/\.\d+(?=[Z+-])/', '', $tsRaw);
    if (is_string($normalized) && $normalized !== '' && $normalized !== $tsRaw) {
        try {
            return (new \DateTimeImmutable($normalized))->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Throwable $e) {
        }
    }
    foreach (['Y-m-d\TH:i:s\Z', 'Y-m-d\TH:i:s.u\Z', 'Y-m-d H:i:s', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP'] as $fmt) {
        $dt = \DateTimeImmutable::createFromFormat($fmt, $tsRaw, new \DateTimeZone('UTC'));
        if ($dt instanceof \DateTimeImmutable) {
            return $dt;
        }
    }
    $unix = strtotime($tsRaw);
    if ($unix !== false) {
        return (new \DateTimeImmutable('@' . $unix))->setTimezone(new \DateTimeZone('UTC'));
    }

    return null;
}

function ia_listing_geo_unpack_payload(?string $raw): ?array
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }
    $decodedB64 = base64_decode($raw, true);
    if ($decodedB64 !== false && $decodedB64 !== '') {
        $try = json_decode($decodedB64, true);
        if (is_array($try)) {
            return $try;
        }
    }
    $try = json_decode($raw, true);
    if (!is_array($try)) {
        return null;
    }

    return $try;
}

function ia_listing_geo_normalize_post(array $post): array
{
    $lat = ia_listing_geo_normalize_coord($post['listing_geo_lat'] ?? null);
    $lng = ia_listing_geo_normalize_coord($post['listing_geo_lng'] ?? null);
    if ($lat !== null && $lng !== null) {
        return $post;
    }
    $payload = ia_listing_geo_unpack_payload($post['listing_geo_payload'] ?? null);
    if ($payload === null) {
        return $post;
    }
    foreach ([
        'lat' => 'listing_geo_lat',
        'lng' => 'listing_geo_lng',
        'ts' => 'listing_geo_captured_at',
        'captured_at' => 'listing_geo_captured_at',
        'acc' => 'listing_geo_accuracy_m',
        'accuracy' => 'listing_geo_accuracy_m',
        'place' => 'listing_geo_place',
    ] as $from => $to) {
        if (!array_key_exists($from, $payload)) {
            continue;
        }
        $val = trim((string) $payload[$from]);
        if ($val === '' || trim((string) ($post[$to] ?? '')) !== '') {
            continue;
        }
        $post[$to] = $val;
    }

    return $post;
}

function ia_listing_geo_validate_post(array $post): ?array
{
    $post = ia_listing_geo_normalize_post($post);
    $lat = ia_listing_geo_normalize_coord($post['listing_geo_lat'] ?? null);
    $lng = ia_listing_geo_normalize_coord($post['listing_geo_lng'] ?? null);
    if ($lat === null || $lng === null) {
        return null;
    }
    if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
        return null;
    }
    $tsRaw = trim((string) ($post['listing_geo_captured_at'] ?? ''));
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $cap = $tsRaw !== '' ? ia_listing_geo_parse_captured_at($tsRaw) : null;
    if ($cap !== null) {
        $cap = $cap->setTimezone(new \DateTimeZone('UTC'));
        $skewPast = 604800;
        $skewFuture = 3600;
        if ($cap->getTimestamp() > $now->getTimestamp() + $skewFuture
            || $cap->getTimestamp() < $now->getTimestamp() - $skewPast) {
            $cap = $now;
        }
    } else {
        $cap = $now;
    }
    $accuracy = null;
    $accRaw = $post['listing_geo_accuracy_m'] ?? '';
    if ($accRaw !== '' && $accRaw !== null) {
        $a = filter_var($accRaw, FILTER_VALIDATE_FLOAT);
        if ($a !== false && $a >= 0.0 && $a <= 50000.0) {
            $accuracy = round($a, 2);
        }
    }

    return [
        'lat' => round((float) $lat, 7),
        'lng' => round((float) $lng, 7),
        'captured_at' => $cap->format('Y-m-d H:i:s'),
        'accuracy' => $accuracy,
    ];
}

/**
 * Геолокация при размещении объявления всегда обязательна (координаты + время из POST).
 *
 * @return array{lat: float, lng: float, captured_at: string, accuracy: float|null}|null null — данные не прошли проверку или отсутствуют
 */
function ia_listing_geo_for_insert_from_post(array $post): ?array
{
    $v = ia_listing_geo_validate_post($post);
    if ($v === null) {
        return null;
    }

    return [
        'lat' => $v['lat'],
        'lng' => $v['lng'],
        'captured_at' => $v['captured_at'],
        'accuracy' => $v['accuracy'],
    ];
}

/**
 * Нужна ли геолокация при публикации (форма «Разместить объявление»): всегда да.
 * @deprecated Параметр listing_geo_required в БД больше не используется для публикации; оставлено для совместимости вызовов.
 */
function ia_listing_geo_requirement_enabled(?PDO $_pdo = null): bool
{
    return true;
}

/**
 * @return array{lat: float, lng: float, captured_at: ?string, accuracy_m: ?float}|null
 */
function ia_listing_geo_from_row(array $row): ?array
{
    if (!array_key_exists('listing_geo_lat', $row) || !array_key_exists('listing_geo_lng', $row)) {
        return null;
    }
    $lat = filter_var($row['listing_geo_lat'], FILTER_VALIDATE_FLOAT);
    $lng = filter_var($row['listing_geo_lng'], FILTER_VALIDATE_FLOAT);
    if ($lat === false || $lng === false) {
        return null;
    }
    if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
        return null;
    }

    $accuracy = null;
    if (array_key_exists('listing_geo_accuracy_m', $row) && $row['listing_geo_accuracy_m'] !== null && $row['listing_geo_accuracy_m'] !== '') {
        $a = filter_var($row['listing_geo_accuracy_m'], FILTER_VALIDATE_FLOAT);
        if ($a !== false && $a >= 0.0) {
            $accuracy = round((float) $a, 2);
        }
    }

    $capturedAt = trim((string) ($row['listing_geo_captured_at'] ?? ''));

    return [
        'lat' => (float) $lat,
        'lng' => (float) $lng,
        'captured_at' => $capturedAt !== '' ? $capturedAt : null,
        'accuracy_m' => $accuracy,
    ];
}

function ia_listing_geo_coords_text(float $lat, float $lng): string
{
    return number_format($lat, 5, '.', '') . ', ' . number_format($lng, 5, '.', '');
}

function ia_listing_geo_captured_text(?string $capturedAt): string
{
    if ($capturedAt === null || trim($capturedAt) === '') {
        return '';
    }
    try {
        return (new \DateTimeImmutable($capturedAt))->format('d.m.Y, H:i');
    } catch (\Throwable $e) {
        return trim($capturedAt);
    }
}

function ia_listing_geo_accuracy_text(?float $accuracyM): string
{
    if ($accuracyM === null) {
        return '';
    }

    return 'точность ±' . number_format($accuracyM, 0, '.', ' ') . ' м';
}

function ia_listing_geo_maps_url(float $lat, float $lng): string
{
    $latStr = number_format($lat, 7, '.', '');
    $lngStr = number_format($lng, 7, '.', '');

    return 'https://www.openstreetmap.org/?mlat=' . rawurlencode($latStr)
        . '&mlon=' . rawurlencode($lngStr)
        . '#map=16/' . rawurlencode($latStr) . '/' . rawurlencode($lngStr);
}

function ia_listing_geo_nearby_radius_m(): float
{
    return 1000.0;
}

function ia_listing_geo_haversine_meters(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earthRadius = 6371000.0;
    $phi1 = deg2rad($lat1);
    $phi2 = deg2rad($lat2);
    $dPhi = deg2rad($lat2 - $lat1);
    $dLambda = deg2rad($lng2 - $lng1);
    $a = sin($dPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;

    return 2 * $earthRadius * asin(min(1.0, sqrt($a)));
}

/**
 * @param array<int, array{id: int, lat: float, lng: float, label: string}> $points
 * @return array<int, array{id: int, lat: float, lng: float, label: string}>
 */
function ia_listing_geo_points_from_rows(array $rows): array
{
    $points = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $geo = ia_listing_geo_from_row($row);
        if ($geo === null) {
            continue;
        }
        $points[] = [
            'id' => (int) ($row['id'] ?? 0),
            'lat' => $geo['lat'],
            'lng' => $geo['lng'],
            'label' => trim((string) (($row['brand'] ?? '') . ' ' . ($row['model'] ?? ''))),
        ];
    }

    return $points;
}

/**
 * @param array<int, array{id: int, lat: float, lng: float, label: string}> $points
 */
function ia_listing_geo_nearby_count(array $points, float $lat, float $lng, float $radiusM, ?int $excludeId = null): int
{
    $count = 0;
    foreach ($points as $point) {
        if ($excludeId !== null && (int) ($point['id'] ?? 0) === $excludeId) {
            continue;
        }
        if (ia_listing_geo_haversine_meters($lat, $lng, (float) $point['lat'], (float) $point['lng']) <= $radiusM) {
            $count++;
        }
    }

    return $count;
}

function ia_listing_geo_area_count(array $points, float $lat, float $lng, float $radiusM): int
{
    $count = 0;
    foreach ($points as $point) {
        if (ia_listing_geo_haversine_meters($lat, $lng, (float) $point['lat'], (float) $point['lng']) <= $radiusM) {
            $count++;
        }
    }

    return $count;
}

function ia_listing_geo_density_tone(int $areaCount): string
{
    if ($areaCount <= 2) {
        return 'sparse';
    }
    if ($areaCount <= 5) {
        return 'medium';
    }

    return 'dense';
}

function ia_listing_geo_density_label_ru(int $areaCount, float $radiusM): string
{
    $radiusKm = $radiusM / 1000.0;
    $radiusLabel = abs($radiusKm - 1.0) < 0.01
        ? '1 км'
        : number_format($radiusKm, 1, '.', ' ') . ' км';

    return 'В радиусе ' . $radiusLabel . ': ' . $areaCount . ' объявл.';
}

function ia_listing_geo_embed_url(float $lat, float $lng): string
{
    $pad = 0.012;
    $west = $lng - $pad;
    $south = $lat - $pad;
    $east = $lng + $pad;
    $north = $lat + $pad;
    $bbox = number_format($west, 6, '.', '') . ','
        . number_format($south, 6, '.', '') . ','
        . number_format($east, 6, '.', '') . ','
        . number_format($north, 6, '.', '');
    $marker = number_format($lat, 7, '.', '') . ',' . number_format($lng, 7, '.', '');

    return 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode($bbox)
        . '&layer=mapnik&marker=' . rawurlencode($marker);
}

/**
 * @return array{city: string, place: string}
 */
function ia_listing_geo_parse_nominatim_address(array $data): array
{
    $addr = is_array($data['address'] ?? null) ? $data['address'] : [];
    $cityKeys = ['city', 'town', 'municipality', 'county', 'state', 'region'];
    $placeKeys = ['suburb', 'neighbourhood', 'quarter', 'city_district', 'district', 'village', 'hamlet', 'residential'];

    $city = '';
    foreach ($cityKeys as $key) {
        $val = trim((string) ($addr[$key] ?? ''));
        if ($val !== '') {
            $city = $val;
            break;
        }
    }

    $place = '';
    foreach ($placeKeys as $key) {
        $val = trim((string) ($addr[$key] ?? ''));
        if ($val !== '') {
            $place = $val;
            break;
        }
    }

    if ($place === '' && $city !== '') {
        $display = trim((string) ($data['display_name'] ?? ''));
        if ($display !== '' && str_contains($display, ',')) {
            $parts = array_map('trim', explode(',', $display));
            if (count($parts) >= 2 && mb_stripos($parts[0], $city) === false) {
                $place = $parts[0];
            }
        }
    }

    return [
        'city' => mb_substr($city, 0, 120),
        'place' => mb_substr($place, 0, 120),
    ];
}

/**
 * Reverse geocode via OpenStreetMap Nominatim (server-side).
 *
 * @return array{city: string, place: string}|null
 */
function ia_listing_geo_reverse_geocode(float $lat, float $lng): ?array
{
    if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
        return null;
    }

    $query = http_build_query([
        'format' => 'jsonv2',
        'lat' => number_format($lat, 7, '.', ''),
        'lon' => number_format($lng, 7, '.', ''),
        'zoom' => '16',
        'accept-language' => 'ru',
    ]);
    $url = 'https://nominatim.openstreetmap.org/reverse?' . $query;
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: InnovaAuto/1.0 (+https://inovaauto.com)\r\nAccept: application/json\r\n",
            'timeout' => 6,
        ],
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || $raw === '') {
        return null;
    }

    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (\Throwable $e) {
        return null;
    }

    if (!is_array($data)) {
        return null;
    }

    $parsed = ia_listing_geo_parse_nominatim_address($data);
    if ($parsed['city'] === '' && $parsed['place'] === '') {
        return null;
    }

    return $parsed;
}

function ia_listing_geo_place_sanitize(?string $place): string
{
    $place = trim((string) $place);
    if ($place === '') {
        return '';
    }
    if (mb_strlen($place) > 120) {
        $place = mb_substr($place, 0, 120);
    }

    return $place;
}

/**
 * Resolve place label for listing card: stored value or reverse geocode fallback.
 */
function ia_listing_geo_place_label(array $row): string
{
    $stored = ia_listing_geo_place_sanitize($row['listing_geo_place'] ?? null);
    if ($stored !== '') {
        return $stored;
    }

    $geo = ia_listing_geo_from_row($row);
    if ($geo === null) {
        return '';
    }

    $rev = ia_listing_geo_reverse_geocode($geo['lat'], $geo['lng']);
    if ($rev === null) {
        return '';
    }

    return ia_listing_geo_place_sanitize($rev['place']);
}
