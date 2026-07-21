<?php

declare(strict_types=1);

require_once IA_ROOT . '/includes/search_fuzzy.php';
require_once IA_ROOT . '/includes/ia_cache.php';
require_once IA_ROOT . '/includes/db_compat.php';

/**
 * Публичный каталог и объявления — только одобренные, если не указано иное.
 *
 * @return list<array<string,mixed>>
 */
/** Сброс кэша брендов/моделей после правок в админке (фильтры, главная, каталог). */
function ia_pub_invalidate_catalog_cache(): void
{
    ia_cache_forget('pub_brands_ordered');
    ia_cache_forget('pub_models_grouped');
    ia_cache_forget_prefix('pub_popular_brands');
}

function ia_pub_brands_ordered(IaPgConnection|IaPdoConnection $pdo): array
{
    $ttl = ia_cache_ttl('brands', 600);

    return ia_cache_remember('pub_brands_ordered', $ttl, static function () use ($pdo): array {
        return $pdo->query('SELECT id, name, sort_order FROM car_brands ORDER BY sort_order ASC, name ASC')->fetchAll() ?: [];
    });
}

/**
 * @return list<array<string,mixed>>
 */
function ia_pub_models_for_brand(IaPgConnection|IaPdoConnection $pdo, int $brandId): array
{
    $st = $pdo->prepare('SELECT id, name, sort_order FROM car_models WHERE brand_id = ? ORDER BY sort_order ASC, name ASC');
    $st->execute([$brandId]);

    return $st->fetchAll() ?: [];
}

/**
 * @return array<string, list<array{id:int,name:string}>>
 */
function ia_pub_models_grouped_json(IaPgConnection|IaPdoConnection $pdo): array
{
    $ttl = ia_cache_ttl('models', 600);

    return ia_cache_remember('pub_models_grouped', $ttl, static function () use ($pdo): array {
        $sql = 'SELECT m.id, m.name, m.brand_id, b.name AS brand_name FROM car_models m INNER JOIN car_brands b ON b.id = m.brand_id ORDER BY b.sort_order, m.sort_order';
        $rows = $pdo->query($sql)->fetchAll() ?: [];
        $out = [];
        foreach ($rows as $r) {
            $bid = (string) (int) $r['brand_id'];
            if (!isset($out[$bid])) {
                $out[$bid] = [];
            }
            $out[$bid][] = ['id' => (int) $r['id'], 'name' => (string) $r['name']];
        }

        return $out;
    });
}

/**
 * @param array{q?:string,brand?:string,model?:string,price_min?:float,price_max?:float,year?:string,mileage_max?:string,fuel_type?:string,transmission?:string,city?:string,body_type?:string,availability?:string,sort?:string,page?:int,per?:int} $f
 * @return array{rows:list<array<string,mixed>>,total:int,fuzzy:bool,fuzzy_query:string}
 */
function ia_pub_listings_catalog(IaPgConnection|IaPdoConnection $pdo, array $f): array
{
    $page = max(1, (int) ($f['page'] ?? 1));
    $per = max(6, min(48, (int) ($f['per'] ?? 12)));
    $offset = ($page - 1) * $per;
    $sort = (string) ($f['sort'] ?? 'new');
    $order = match ($sort) {
        'price_asc' => 'l.price ASC, l.id DESC',
        'price_desc' => 'l.price DESC, l.id DESC',
        'mileage_asc' => 'CASE WHEN l.mileage_km IS NULL OR l.mileage_km <= 0 THEN 1 ELSE 0 END ASC, l.mileage_km ASC, l.id DESC',
        default => 'l.created_at DESC, l.id DESC',
    };

    $where = ["l.status = 'approved'"];
    $params = [];

    $brand = trim((string) ($f['brand'] ?? ''));
    if ($brand !== '') {
        $where[] = 'l.brand = :brand';
        $params['brand'] = $brand;
    }
    $model = trim((string) ($f['model'] ?? ''));
    if ($model !== '') {
        $where[] = 'l.model = :model';
        $params['model'] = $model;
    }
    $pmin = $f['price_min'] ?? null;
    if ($pmin !== null && $pmin !== '') {
        $where[] = 'l.price >= :pmin';
        $params['pmin'] = (float) $pmin;
    }
    $pmax = $f['price_max'] ?? null;
    if ($pmax !== null && $pmax !== '') {
        $where[] = 'l.price <= :pmax';
        $params['pmax'] = (float) $pmax;
    }
    $year = trim((string) ($f['year'] ?? ''));
    if ($year !== '' && ctype_digit($year)) {
        $y = (int) $year;
        if ($y >= 1950 && $y <= 2100) {
            $where[] = 'l.model_year = :model_year';
            $params['model_year'] = $y;
        }
    }
    $bodyType = trim((string) ($f['body_type'] ?? ''));
    $allowedBody = ['sedan', 'suv', 'hatchback', 'pickup', 'ev', 'sport'];
    if ($bodyType !== '' && in_array($bodyType, $allowedBody, true)) {
        $where[] = 'l.body_type = :body_type';
        $params['body_type'] = $bodyType;
    }
    $mileageMax = trim((string) ($f['mileage_max'] ?? ''));
    if ($mileageMax !== '' && ctype_digit($mileageMax)) {
        $where[] = 'l.mileage_km <= :mileage_max';
        $params['mileage_max'] = (int) $mileageMax;
    }
    $fuelType = trim((string) ($f['fuel_type'] ?? ''));
    $allowedFuel = ['petrol', 'diesel', 'gas', 'hybrid', 'electric'];
    if ($fuelType !== '' && in_array($fuelType, $allowedFuel, true)) {
        $where[] = 'l.fuel_type = :fuel_type';
        $params['fuel_type'] = $fuelType;
    }
    $transmission = trim((string) ($f['transmission'] ?? ''));
    $allowedTrans = ['auto', 'manual', 'robot', 'cvt'];
    if ($transmission !== '' && in_array($transmission, $allowedTrans, true)) {
        $where[] = 'l.transmission = :transmission';
        $params['transmission'] = $transmission;
    }
    $city = trim((string) ($f['city'] ?? ''));
    if ($city !== '') {
        if (!function_exists('ia_tj_city_normalize')) {
            require_once IA_ROOT . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'tj_cities.php';
        }
        $cityNorm = ia_tj_city_normalize($city);
        if ($cityNorm !== '' && ia_tj_city_is_allowed($cityNorm)) {
            $where[] = 'l.city = :city';
            $params['city'] = $cityNorm;
        } else {
            $where[] = 'l.city LIKE :city';
            $params['city'] = '%' . $city . '%';
        }
    }
    $availabilityFilter = trim((string) ($f['availability'] ?? ''));
    if ($availabilityFilter === 'on_order' || $availabilityFilter === 'in_stock') {
        $where[] = 'l.availability = :availability_filter';
        $params['availability_filter'] = $availabilityFilter;
    }

    $q = trim((string) ($f['q'] ?? ''));
    $fuzzy = false;
    $fuzzyQuery = '';

    $runCatalog = static function (array $w, array $p) use ($pdo, $order, $per, $offset): array {
        $sqlWhere = implode(' AND ', $w);
        $countSt = $pdo->prepare("SELECT COUNT(*) FROM ad_listings l WHERE {$sqlWhere}");
        $countSt->execute($p);
        $total = (int) $countSt->fetchColumn();
        $sql = "SELECT l.* FROM ad_listings l WHERE {$sqlWhere} ORDER BY {$order} LIMIT " . (int) $per . ' OFFSET ' . (int) $offset;
        $st = $pdo->prepare($sql);
        $st->execute($p);

        return ['rows' => $st->fetchAll() ?: [], 'total' => $total];
    };

    $searchWhere = $where;
    $searchParams = $params;
    if ($q !== '') {
        $searchWhere[] = ia_db_like_or(
            ['l.brand', 'l.model', 'l.seller_name', 'l.description'],
            'q',
            '%' . $q . '%',
            $searchParams
        );
    }

    $result = $runCatalog($searchWhere, $searchParams);

    if ($result['total'] === 0 && $q !== '') {
        $terms = ia_pub_fuzzy_resolve_terms($pdo, $q, 60.0);
        if ($terms === []) {
            foreach (ia_search_resolve_needles($q) as $needle) {
                if (mb_strlen($needle) >= 3) {
                    $terms[] = $needle;
                }
            }
            $terms = array_values(array_unique($terms));
        }
        if ($terms !== []) {
            $fuzzyWhere = $where;
            $fuzzyParams = $params;
            $parts = [];
            foreach ($terms as $i => $term) {
                $parts[] = ia_db_like_or(['l.brand', 'l.model'], 'fz' . $i, '%' . $term . '%', $fuzzyParams);
            }
            $fuzzyWhere[] = '(' . implode(' OR ', $parts) . ')';
            $fuzzyResult = $runCatalog($fuzzyWhere, $fuzzyParams);
            if ($fuzzyResult['total'] > 0) {
                $result = $fuzzyResult;
                $fuzzy = true;
                $fuzzyQuery = $q;
            }
        }
    }

    return [
        'rows' => $result['rows'],
        'total' => $result['total'],
        'fuzzy' => $fuzzy,
        'fuzzy_query' => $fuzzyQuery,
    ];
}

/**
 * Suggested listings when catalog search returns nothing.
 *
 * @return list<array<string,mixed>>
 */
function ia_pub_listings_search_suggestions(IaPgConnection|IaPdoConnection $pdo, string $q, int $limit = 8): array
{
    $limit = max(4, min(12, $limit));
    $terms = ia_pub_fuzzy_resolve_terms($pdo, $q, 45.0);
    if ($terms === []) {
        foreach (ia_search_resolve_needles($q) as $needle) {
            if (mb_strlen($needle) >= 3) {
                $terms[] = $needle;
            }
        }
        $terms = array_values(array_unique($terms));
    }

    if ($terms !== []) {
        $parts = [];
        $params = [];
        foreach ($terms as $i => $term) {
            $parts[] = ia_db_like_or(['l.brand', 'l.model'], 'sg' . $i, '%' . $term . '%', $params);
        }
        $sql = "SELECT l.* FROM ad_listings l
                WHERE l.status = 'approved' AND (" . implode(' OR ', $parts) . ")
                ORDER BY l.created_at DESC, l.id DESC
                LIMIT " . (int) $limit;
        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($rows !== []) {
                return $rows;
            }
        } catch (Throwable) {
            // fall through
        }
    }

    return ia_pub_listings_latest($pdo, $limit);
}

/**
 * Returns an associative map [listing_id => array<string>] of image URLs (max 6),
 * including the listing's main photo as the first item, so cards can preview
 * additional photos on hover without an extra request per card.
 *
 * @param array<int> $listingIds
 * @return array<int, array<int, string>>
 */
function ia_pub_listing_thumbs_for_ids(IaPgConnection|IaPdoConnection $pdo, array $listingIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $listingIds), static fn (int $v): bool => $v > 0)));
    if (empty($ids)) {
        return [];
    }
    $place = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT listing_id, media_kind, stored_path, sort_order
            FROM ad_listing_media
            WHERE listing_id IN ($place) AND media_kind = 'image'
            ORDER BY listing_id ASC, sort_order ASC, id ASC";
    try {
        $st = $pdo->prepare($sql);
        $st->execute($ids);
        $rows = $st->fetchAll() ?: [];
    } catch (\PDOException) {
        return [];
    }
    $map = [];
    foreach ($rows as $row) {
        $lid = (int) ($row['listing_id'] ?? 0);
        if ($lid <= 0) {
            continue;
        }
        $src = ia_listing_photo_src((string) ($row['stored_path'] ?? ''));
        if ($src === '') {
            continue;
        }
        if (!isset($map[$lid])) {
            $map[$lid] = [];
        }
        if (count($map[$lid]) >= 6) {
            continue;
        }
        $map[$lid][] = $src;
    }

    return $map;
}

/**
 * Listings with a full photo set for the 360° badge on cards.
 *
 * @param array<int> $listingIds
 * @return array<int, true>
 */
function ia_pub_listing_panorama_flags_for_ids(IaPgConnection|IaPdoConnection $pdo, array $listingIds): array
{
    if (!defined('IA_LISTING_PHOTO_SLOT_COUNT')) {
        require_once __DIR__ . '/listing_media.php';
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $listingIds), static fn (int $v): bool => $v > 0)));
    if (empty($ids)) {
        return [];
    }

    $place = implode(',', array_fill(0, count($ids), '?'));
    $minPhotos = IA_LISTING_PHOTO_SLOT_COUNT;
    $sql = "SELECT listing_id
            FROM ad_listing_media
            WHERE listing_id IN ($place) AND media_kind = 'image'
            GROUP BY listing_id
            HAVING COUNT(*) >= {$minPhotos}";
    try {
        $st = $pdo->prepare($sql);
        $st->execute($ids);
        $rows = $st->fetchAll() ?: [];
    } catch (\PDOException) {
        return [];
    }

    $flags = [];
    foreach ($rows as $row) {
        $lid = (int) ($row['listing_id'] ?? 0);
        if ($lid > 0) {
            $flags[$lid] = true;
        }
    }

    return $flags;
}

function ia_pub_listing_by_id(IaPgConnection|IaPdoConnection $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT l.*, u.email AS owner_email, u.phone AS owner_phone FROM ad_listings l INNER JOIN platform_users u ON u.id = l.user_id WHERE l.id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();

    return $row ?: null;
}

/**
 * Объявление только если владелец.
 *
 * @return array<string, mixed>|null
 */
function ia_pub_listing_owned_by(IaPgConnection|IaPdoConnection $pdo, int $listingId, int $userId): ?array
{
    $st = $pdo->prepare('SELECT * FROM ad_listings WHERE id = ? AND user_id = ? LIMIT 1');
    $st->execute([$listingId, $userId]);
    $row = $st->fetch();

    return $row ?: null;
}

/**
 * Одобренные объявления с координатами в радиусе {@see ia_listing_geo_nearby_radius_m()} от точки (кроме $excludeListingId).
 *
 * @return list<array{id:int, brand:string, model:string, price:float, currency:string, lat:float, lng:float, city:string, distance_m:float}>
 */
function ia_pub_listings_geo_nearby(IaPgConnection|IaPdoConnection $pdo, float $lat, float $lng, int $excludeListingId, int $limit = 14): array
{
    require_once IA_ROOT . '/includes/listing_geo.php';
    $limit = max(1, min(40, $limit));
    $radiusM = ia_listing_geo_nearby_radius_m();
    $padDeg = ($radiusM / 111000) * 1.2;
    $latMin = $lat - $padDeg;
    $latMax = $lat + $padDeg;
    $cos = cos(deg2rad($lat));
    $cos = $cos > 0.01 ? $cos : 0.01;
    $lngPad = $padDeg / $cos;
    $lngMin = $lng - $lngPad;
    $lngMax = $lng + $lngPad;
    try {
        $st = $pdo->prepare(
            'SELECT id, brand, model, price, currency, listing_geo_lat, listing_geo_lng, city
             FROM ad_listings
             WHERE status = ?
               AND id <> ?
               AND listing_geo_lat IS NOT NULL AND listing_geo_lng IS NOT NULL
               AND listing_geo_lat BETWEEN ? AND ?
               AND listing_geo_lng BETWEEN ? AND ?'
        );
        $st->execute(['approved', $excludeListingId, $latMin, $latMax, $lngMin, $lngMax]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\PDOException) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $la = filter_var($r['listing_geo_lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $ln = filter_var($r['listing_geo_lng'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($la === false || $ln === false) {
            continue;
        }
        $d = ia_listing_geo_haversine_meters($lat, $lng, (float) $la, (float) $ln);
        if ($d > $radiusM) {
            continue;
        }
        $out[] = [
            'id' => (int) ($r['id'] ?? 0),
            'brand' => (string) ($r['brand'] ?? ''),
            'model' => (string) ($r['model'] ?? ''),
            'price' => (float) ($r['price'] ?? 0),
            'currency' => (string) ($r['currency'] ?? 'TJS'),
            'lat' => (float) $la,
            'lng' => (float) $ln,
            'city' => (string) ($r['city'] ?? ''),
            'distance_m' => $d,
        ];
    }
    usort($out, static fn (array $a, array $b): int => $a['distance_m'] <=> $b['distance_m']);

    return array_slice($out, 0, $limit);
}

/**
 * Владелец возвращает объявление из архива на повторную модерацию.
 */
function ia_pub_reactivate_archived_listing(IaPgConnection|IaPdoConnection $pdo, int $listingId, int $ownerId): bool
{
    if ($listingId <= 0 || $ownerId <= 0) {
        return false;
    }
    $st = $pdo->prepare("SELECT id, status FROM ad_listings WHERE id = ? AND user_id = ? LIMIT 1");
    $st->execute([$listingId, $ownerId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || (string) ($row['status'] ?? '') !== 'archived') {
        return false;
    }
    require_once IA_ROOT . '/includes/platform_notifications.php';
    require_once IA_ROOT . '/includes/db_compat.php';
    $expiresAt = ia_platform_listing_expires_at_value();
    if (ia_db_column_exists($pdo, 'ad_listings', 'user_soft_deleted_at')) {
        $u = $pdo->prepare(
            "UPDATE ad_listings SET status = 'pending', rejection_reason = NULL, expires_at = ?, user_soft_deleted_at = NULL
             WHERE id = ? AND user_id = ? AND status = 'archived'"
        );
        $u->execute([$expiresAt, $listingId, $ownerId]);
    } else {
        $u = $pdo->prepare(
            "UPDATE ad_listings SET status = 'pending', rejection_reason = NULL, expires_at = ?
             WHERE id = ? AND user_id = ? AND status = 'archived'"
        );
        $u->execute([$expiresAt, $listingId, $ownerId]);
    }

    return $u->rowCount() > 0;
}

/**
 * Подбор id бренда и модели по именам из объявления (для формы редактирования).
 *
 * @return array{brand_id:int, model_id:int}
 */
function ia_pub_resolve_brand_model_ids(IaPgConnection|IaPdoConnection $pdo, string $brandName, string $modelName): array
{
    $st = $pdo->prepare('SELECT id FROM car_brands WHERE name = ? LIMIT 1');
    $st->execute([$brandName]);
    $bid = (int) $st->fetchColumn();
    $mid = 0;
    if ($bid > 0 && $modelName !== '') {
        $st = $pdo->prepare('SELECT id FROM car_models WHERE brand_id = ? AND name = ? LIMIT 1');
        $st->execute([$bid, $modelName]);
        $mid = (int) $st->fetchColumn();
    }

    return ['brand_id' => $bid, 'model_id' => $mid];
}

/**
 * @return list<array<string,mixed>>
 */
function ia_pub_listings_home(IaPgConnection|IaPdoConnection $pdo, int $limit = 12): array
{
    $limit = max(1, min(48, $limit));
    $sql = "SELECT * FROM ad_listings WHERE status = 'approved' AND is_vip = 0 ORDER BY created_at DESC LIMIT {$limit}";

    return $pdo->query($sql)->fetchAll() ?: [];
}

/**
 * Последние объявления (latest first), только approved.
 *
 * @return list<array<string,mixed>>
 */
function ia_pub_listings_latest(IaPgConnection|IaPdoConnection $pdo, int $limit = 12): array
{
    $limit = max(1, min(48, $limit));
    $sql = "SELECT * FROM ad_listings WHERE status = 'approved' ORDER BY created_at DESC LIMIT {$limit}";

    return $pdo->query($sql)->fetchAll() ?: [];
}

/**
 * VIP-объявления для главной: только одобренные, is_vip = 1.
 *
 * @return list<array<string,mixed>>
 */
function ia_pub_listings_vip(IaPgConnection|IaPdoConnection $pdo, int $limit = 12): array
{
    $limit = max(1, min(48, $limit));
    $sql = "SELECT * FROM ad_listings WHERE status = 'approved' AND is_vip = 1 ORDER BY created_at DESC LIMIT {$limit}";

    return $pdo->query($sql)->fetchAll() ?: [];
}

/**
 * @return array{homepage: list<array<string,mixed>>, promo_slider: list<array<string,mixed>>}
 */
function ia_pub_banners_home(IaPgConnection|IaPdoConnection $pdo): array
{
    $ttl = ia_cache_ttl('banners', 120);

    return ia_cache_remember('pub_banners_home', $ttl, static function () use ($pdo): array {
        $rows = $pdo->query(
            "SELECT * FROM site_banners
             WHERE slot IN ('homepage', 'promo_slider')
               AND is_active = 1
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at IS NULL OR ends_at > NOW())
             ORDER BY slot ASC, sort_order ASC, id ASC"
        )->fetchAll() ?: [];
        $out = ['homepage' => [], 'promo_slider' => []];
        foreach ($rows as $row) {
            $slot = (string) ($row['slot'] ?? '');
            if (isset($out[$slot])) {
                $out[$slot][] = $row;
            }
        }

        return $out;
    });
}

/**
 * @return list<array<string,mixed>>
 */
function ia_pub_banners_slot(IaPgConnection|IaPdoConnection $pdo, string $slot): array
{
    if (!in_array($slot, ['homepage', 'promo_slider', 'ads'], true)) {
        return [];
    }
    if ($slot === 'homepage' || $slot === 'promo_slider') {
        $all = ia_pub_banners_home($pdo);

        return $all[$slot] ?? [];
    }
    $st = $pdo->prepare(
        'SELECT * FROM site_banners
         WHERE slot = ?
           AND is_active = 1
           AND (starts_at IS NULL OR starts_at <= NOW())
           AND (ends_at IS NULL OR ends_at > NOW())
         ORDER BY sort_order ASC, id ASC'
    );
    $st->execute([$slot]);

    return $st->fetchAll() ?: [];
}

/**
 * Популярные бренды по количеству одобренных объявлений.
 *
 * @return list<array<string,mixed>>
 */
function ia_pub_popular_brands(IaPgConnection|IaPdoConnection $pdo, int $limit = 12): array
{
    $limit = max(1, min(24, $limit));
    $ttl = ia_cache_ttl('popular_brands', 300);

    return ia_cache_remember('pub_popular_brands_' . $limit, $ttl, static function () use ($pdo, $limit): array {
        $sql = "SELECT b.id, b.name, COUNT(l.id) AS listings_count
            FROM car_brands b
            LEFT JOIN ad_listings l ON l.brand = b.name AND l.status = 'approved'
            GROUP BY b.id, b.name
            ORDER BY listings_count DESC, b.sort_order ASC, b.name ASC
            LIMIT {$limit}";

        return $pdo->query($sql)->fetchAll() ?: [];
    });
}

/**
 * Количество одобренных объявлений по типу кузова.
 *
 * @return array<string, int>
 */
function ia_pub_listing_counts_by_body_type(IaPgConnection|IaPdoConnection $pdo): array
{
    $ttl = ia_cache_ttl('body_type_counts', 300);

    return ia_cache_remember('pub_body_type_counts', $ttl, static function () use ($pdo): array {
        $rows = $pdo->query(
            "SELECT body_type, COUNT(*) AS listings_count
             FROM ad_listings
             WHERE status = 'approved' AND body_type <> ''
             GROUP BY body_type"
        )->fetchAll() ?: [];

        $map = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row['body_type'] ?? ''));
            if ($key === '') {
                continue;
            }
            $map[$key] = (int) ($row['listings_count'] ?? 0);
        }

        return $map;
    });
}

/**
 * @return list<int>
 */
function ia_pub_favorite_ids(IaPgConnection|IaPdoConnection $pdo, int $userId): array
{
    $st = $pdo->prepare('SELECT listing_id FROM user_favorites WHERE user_id = ? ORDER BY created_at DESC');
    $st->execute([$userId]);

    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function ia_pub_favorite_visible_count(IaPgConnection|IaPdoConnection $pdo, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM user_favorites f
         INNER JOIN ad_listings l ON l.id = f.listing_id
         WHERE f.user_id = ? AND l.status = 'approved'"
    );
    $st->execute([$userId]);

    return (int) $st->fetchColumn();
}

/**
 * @return array{visible:list<array<string,mixed>>,hidden:list<array<string,mixed>>}
 */
function ia_pub_favorites_for_user(IaPgConnection|IaPdoConnection $pdo, int $userId): array
{
    if ($userId <= 0) {
        return ['visible' => [], 'hidden' => []];
    }

    $visibleSt = $pdo->prepare(
        "SELECT l.* FROM user_favorites f
         INNER JOIN ad_listings l ON l.id = f.listing_id
         WHERE f.user_id = ? AND l.status = 'approved'
         ORDER BY f.created_at DESC"
    );
    $visibleSt->execute([$userId]);
    $visible = $visibleSt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $hiddenSt = $pdo->prepare(
        "SELECT f.listing_id, l.brand, l.model, l.status, l.photo_url
         FROM user_favorites f
         INNER JOIN ad_listings l ON l.id = f.listing_id
         WHERE f.user_id = ? AND l.status <> 'approved'
         ORDER BY f.created_at DESC"
    );
    $hiddenSt->execute([$userId]);
    $hidden = $hiddenSt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return ['visible' => $visible, 'hidden' => $hidden];
}

function ia_pub_prune_orphan_favorites(IaPgConnection|IaPdoConnection $pdo, int $userId): void
{
    if ($userId <= 0) {
        return;
    }
    try {
        $pdo->prepare(
            'DELETE FROM user_favorites f
             WHERE f.user_id = ?
               AND NOT EXISTS (SELECT 1 FROM ad_listings l WHERE l.id = f.listing_id)'
        )->execute([$userId]);
    } catch (\PDOException) {
    }
}

function ia_pub_is_favorite(IaPgConnection|IaPdoConnection $pdo, int $userId, int $listingId): bool
{
    $st = $pdo->prepare('SELECT 1 FROM user_favorites WHERE user_id = ? AND listing_id = ? LIMIT 1');
    $st->execute([$userId, $listingId]);

    return (bool) $st->fetchColumn();
}

const IA_COMPARE_MAX = 4;

function ia_pub_compare_ids(IaPgConnection|IaPdoConnection $pdo, int $userId): array
{
    if ($userId <= 0) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $g = $_SESSION['compare_ids'] ?? [];

            return is_array($g) ? array_values(array_unique(array_map('intval', $g))) : [];
        }

        return [];
    }
    try {
        $st = $pdo->prepare('SELECT listing_id FROM user_compare WHERE user_id = ? ORDER BY created_at DESC');
        $st->execute([$userId]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    } catch (\PDOException) {
        return [];
    }
}

function ia_pub_is_in_compare(IaPgConnection|IaPdoConnection $pdo, int $userId, int $listingId): bool
{
    if ($userId <= 0) {
        $g = is_array($_SESSION['compare_ids'] ?? null) ? $_SESSION['compare_ids'] : [];

        return in_array($listingId, array_map('intval', $g), true);
    }
    try {
        $st = $pdo->prepare('SELECT 1 FROM user_compare WHERE user_id = ? AND listing_id = ? LIMIT 1');
        $st->execute([$userId, $listingId]);

        return (bool) $st->fetchColumn();
    } catch (\PDOException) {
        return false;
    }
}

/**
 * Toggles a listing in the compare list.
 * Returns array{added: bool, count: int, full: bool}.
 */
function ia_pub_toggle_compare(IaPgConnection|IaPdoConnection $pdo, int $userId, int $listingId): array
{
    $current = ia_pub_compare_ids($pdo, $userId);
    $isIn = in_array($listingId, $current, true);

    if ($isIn) {
        if ($userId > 0) {
            try {
                $pdo->prepare('DELETE FROM user_compare WHERE user_id = ? AND listing_id = ?')
                    ->execute([$userId, $listingId]);
            } catch (\PDOException) {
            }
        } else {
            $_SESSION['compare_ids'] = array_values(array_filter($current, static fn (int $v): bool => $v !== $listingId));
        }

        ia_pub_layout_state_bump();

        return ['added' => false, 'count' => count($current) - 1, 'full' => false];
    }

    if (count($current) >= IA_COMPARE_MAX) {
        return ['added' => false, 'count' => count($current), 'full' => true];
    }

    if ($userId > 0) {
        try {
            $pdo->prepare('INSERT INTO user_compare (user_id, listing_id) VALUES (?, ?)')
                ->execute([$userId, $listingId]);
        } catch (\PDOException) {
        }
    } else {
        $_SESSION['compare_ids'] = array_values(array_unique(array_merge($current, [$listingId])));
    }

    ia_pub_layout_state_bump();

    return ['added' => true, 'count' => count($current) + 1, 'full' => false];
}

function ia_pub_compare_clear(IaPgConnection|IaPdoConnection $pdo, int $userId): void
{
    if ($userId > 0) {
        try {
            $pdo->prepare('DELETE FROM user_compare WHERE user_id = ?')->execute([$userId]);
        } catch (\PDOException) {
        }
    } else {
        $_SESSION['compare_ids'] = [];
    }
    ia_pub_layout_state_bump();
}

/**
 * Loads listings for the compare list (only approved).
 *
 * @return array<int, array<string, mixed>>
 */
function ia_pub_compare_listings(IaPgConnection|IaPdoConnection $pdo, array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $ids = array_filter($ids, static fn (int $v): bool => $v > 0);
    if (empty($ids)) {
        return [];
    }
    $place = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id, brand, model, price, photo_url, model_year, mileage_km, fuel_type, transmission,
                   body_type, city, availability, is_vip, is_top, status,
                   color, drive_type, engine_volume, has_turbo, condition_state, customs_cleared, taxi_license, prepayment_amount, currency
            FROM ad_listings
            WHERE id IN ($place) AND status = 'approved'";
    $st = $pdo->prepare($sql);
    $st->execute($ids);
    $rows = $st->fetchAll() ?: [];
    $byId = [];
    foreach ($rows as $r) {
        $byId[(int) $r['id']] = $r;
    }
    $ordered = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $ordered[] = $byId[$id];
        }
    }

    return $ordered;
}

function ia_pub_can_view_listing(?array $viewer, array $listing): bool
{
    if (($listing['status'] ?? '') === 'approved') {
        return true;
    }
    if ($viewer !== null && (int) $viewer['id'] === (int) ($listing['user_id'] ?? 0)) {
        return true;
    }

    return false;
}

/**
 * Increments the listing's view counter once per session per listing
 * (does not count owner viewing their own listing).
 */
function ia_pub_listing_track_view(IaPgConnection|IaPdoConnection $pdo, int $listingId, int $viewerUserId, int $ownerUserId): void
{
    if ($listingId <= 0 || ($viewerUserId > 0 && $viewerUserId === $ownerUserId)) {
        return;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $key = (string) $listingId;
    $seen = isset($_SESSION['listing_views_seen']) && is_array($_SESSION['listing_views_seen'])
        ? $_SESSION['listing_views_seen']
        : [];
    $now = time();
    $last = (int) ($seen[$key] ?? 0);
    if ($now - $last < 1800) {
        return;
    }
    try {
        $pdo->prepare('UPDATE ad_listings SET views_count = COALESCE(views_count, 0) + 1 WHERE id = ?')
            ->execute([$listingId]);
        $seen[$key] = $now;
        $_SESSION['listing_views_seen'] = $seen;
        require_once IA_ROOT . '/includes/listing_lifecycle.php';
        ia_listing_touch_engagement($pdo, $listingId);
    } catch (\PDOException) {
    }
}

/**
 * Increments the listing's click counter (e.g. user clicked "Написать продавцу"
 * or "Показать телефон"). Owner clicks are skipped.
 */
function ia_pub_listing_track_click(IaPgConnection|IaPdoConnection $pdo, int $listingId, int $viewerUserId): void
{
    if ($listingId <= 0) {
        return;
    }
    try {
        $st = $pdo->prepare('SELECT user_id FROM ad_listings WHERE id = ?');
        $st->execute([$listingId]);
        $ownerId = (int) ($st->fetchColumn() ?: 0);
        if ($viewerUserId > 0 && $viewerUserId === $ownerId) {
            return;
        }
        $pdo->prepare('UPDATE ad_listings SET clicks_count = COALESCE(clicks_count, 0) + 1 WHERE id = ?')
            ->execute([$listingId]);
        require_once IA_ROOT . '/includes/listing_lifecycle.php';
        ia_listing_touch_engagement($pdo, $listingId);
    } catch (\PDOException) {
    }
}

function ia_pub_toggle_favorite(IaPgConnection|IaPdoConnection $pdo, int $userId, int $listingId): bool
{
    if (ia_pub_is_favorite($pdo, $userId, $listingId)) {
        $pdo->prepare('DELETE FROM user_favorites WHERE user_id = ? AND listing_id = ?')->execute([$userId, $listingId]);
        ia_pub_layout_state_bump();

        return false;
    }
    try {
        $pdo->prepare('INSERT INTO user_favorites (user_id, listing_id) VALUES (?, ?)')->execute([$userId, $listingId]);
        ia_pub_layout_state_bump();

        return true;
    } catch (\PDOException) {
        return true;
    }
}

/**
 * @return list<array<string,mixed>>
 */
function ia_pub_threads_for_user(IaPgConnection|IaPdoConnection $pdo, int $platformUserId): array
{
    $sql = 'SELECT t.*,
            CASE WHEN t.user_low_id = ? THEN t.last_seen_low_at ELSE t.last_seen_high_at END AS my_last_seen_at,
            CASE WHEN t.user_low_id = ? THEN t.last_seen_high_at ELSE t.last_seen_low_at END AS peer_last_seen_at,
            l.brand AS listing_brand,
            l.model AS listing_model,
            l.seller_name AS listing_seller_name,
            l.user_id AS listing_owner_id,
            (SELECT u.name FROM platform_users u WHERE u.id = CASE WHEN t.user_low_id = ? THEN t.user_high_id ELSE t.user_low_id END LIMIT 1) AS peer_name,
            (SELECT u.email FROM platform_users u WHERE u.id = CASE WHEN t.user_low_id = ? THEN t.user_high_id ELSE t.user_low_id END LIMIT 1) AS peer_email,
            (SELECT u.avatar_path FROM platform_users u WHERE u.id = CASE WHEN t.user_low_id = ? THEN t.user_high_id ELSE t.user_low_id END LIMIT 1) AS peer_avatar_path,
            (SELECT COALESCE(NULLIF(TRIM(m.body), \'\'), CASE LOWER(COALESCE(m.msg_type, \'text\'))
                WHEN \'image\' THEN \'Фото\' WHEN \'voice\' THEN \'Голосовое\' WHEN \'file\' THEN \'Файл\' ELSE \'\' END)
             FROM chat_messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_body,
            (SELECT created_at FROM chat_messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_at,
            (SELECT sender_id FROM chat_messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS last_sender_id
            FROM chat_threads t
            LEFT JOIN ad_listings l ON l.id = t.listing_id
            WHERE t.user_low_id = ? OR t.user_high_id = ?
            ORDER BY t.updated_at DESC';
    $st = $pdo->prepare($sql);
    $st->execute([
        $platformUserId,
        $platformUserId,
        $platformUserId,
        $platformUserId,
        $platformUserId,
        $platformUserId,
        $platformUserId,
    ]);

    return $st->fetchAll() ?: [];
}

/**
 * Количество диалогов с непрочитанными сообщениями для пользователя.
 *
 * @param list<array<string,mixed>> $threads
 */
function ia_pub_unread_threads_count(array $threads, int $userId, array $seenMap): int
{
    $count = 0;
    foreach ($threads as $t) {
        $tid = (string) (int) ($t['id'] ?? 0);
        $lastSender = (int) ($t['last_sender_id'] ?? 0);
        if ($lastSender <= 0 || $lastSender === $userId) {
            continue;
        }
        $lastAt = strtotime((string) ($t['last_at'] ?? '')) ?: 0;
        $seenAtDb = strtotime((string) ($t['my_last_seen_at'] ?? '')) ?: 0;
        $seenAt = max($seenAtDb, isset($seenMap[$tid]) ? (int) $seenMap[$tid] : 0);
        if ($lastAt > $seenAt) {
            $count++;
        }
    }

    return $count;
}

/**
 * @return list<array<string,mixed>>
 */
function ia_pub_listings_for_owner(IaPgConnection|IaPdoConnection $pdo, int $userId): array
{
    $st = $pdo->prepare(
        'SELECT al.id, al.brand, al.model, al.status, al.price, al.photo_url, al.created_at,
                al.model_year, al.mileage_km, al.fuel_type, al.transmission, al.city,
                al.is_vip, al.is_top, al.availability,
                COALESCE(al.views_count, 0) AS views_count,
                COALESCE(al.clicks_count, 0) AS clicks_count,
                (SELECT COUNT(*) FROM user_favorites uf WHERE uf.listing_id = al.id) AS favorites_count,
                (SELECT COUNT(*) FROM chat_messages cm
                    JOIN chat_threads ct ON ct.id = cm.thread_id
                    WHERE ct.listing_id = al.id) AS messages_count
         FROM ad_listings al
         WHERE al.user_id = ?
         ORDER BY al.created_at DESC'
    );
    $st->execute([$userId]);

    return $st->fetchAll() ?: [];
}

/**
 * Покупатель ($buyerId) пишет продавцу по объявлению. Возвращает id темы или null.
 */
function ia_pub_get_or_create_thread(IaPgConnection|IaPdoConnection $pdo, int $listingId, int $buyerId): ?int
{
    $listing = ia_pub_listing_by_id($pdo, $listingId);
    if ($listing === null) {
        return null;
    }
    $sellerId = (int) ($listing['user_id'] ?? 0);
    if ($sellerId <= 0 || $buyerId === $sellerId) {
        return null;
    }
    if (($listing['status'] ?? '') !== 'approved') {
        return null;
    }

    $low = min($buyerId, $sellerId);
    $high = max($buyerId, $sellerId);
    $st = $pdo->prepare('SELECT id FROM chat_threads WHERE listing_id = ? AND user_low_id = ? AND user_high_id = ? LIMIT 1');
    $st->execute([$listingId, $low, $high]);
    $ex = $st->fetchColumn();
    if ($ex) {
        return (int) $ex;
    }
    $pdo->prepare('INSERT INTO chat_threads (listing_id, user_low_id, user_high_id) VALUES (?, ?, ?)')->execute([$listingId, $low, $high]);

    return (int) $pdo->lastInsertId();
}

/**
 * @return array<string,mixed>|null
 */
function ia_pub_thread_for_participant(IaPgConnection|IaPdoConnection $pdo, int $threadId, int $userId): ?array
{
    $st = $pdo->prepare(
        'SELECT t.*,
            CASE WHEN t.user_low_id = ? THEN t.last_seen_high_at ELSE t.last_seen_low_at END AS peer_last_seen_at,
            l.brand AS listing_brand,
            l.model AS listing_model,
            l.user_id AS listing_owner_id,
            l.seller_name AS listing_seller_name,
            (SELECT u.name FROM platform_users u
                WHERE u.id = CASE WHEN t.user_low_id = ? THEN t.user_high_id ELSE t.user_low_id END LIMIT 1) AS peer_name,
            (SELECT u.email FROM platform_users u
                WHERE u.id = CASE WHEN t.user_low_id = ? THEN t.user_high_id ELSE t.user_low_id END LIMIT 1) AS peer_email,
            (SELECT u.avatar_path FROM platform_users u
                WHERE u.id = CASE WHEN t.user_low_id = ? THEN t.user_high_id ELSE t.user_low_id END LIMIT 1) AS peer_avatar_path
         FROM chat_threads t
         LEFT JOIN ad_listings l ON l.id = t.listing_id
         WHERE t.id = ? AND (t.user_low_id = ? OR t.user_high_id = ?)
         LIMIT 1'
    );
    $st->execute([$userId, $userId, $userId, $userId, $threadId, $userId, $userId]);
    $row = $st->fetch();

    return $row ?: null;
}

/**
 * @return list<array<string,mixed>>
 */
function ia_pub_messages_for_thread(IaPgConnection|IaPdoConnection $pdo, int $threadId, int $limit = 200): array
{
    $limit = max(1, min(500, $limit));
    $st = $pdo->prepare(
        "SELECT m.*, u.name AS sender_name, u.avatar_path AS sender_avatar_path
         FROM chat_messages m
         INNER JOIN platform_users u ON u.id = m.sender_id
         WHERE m.thread_id = ?
         ORDER BY m.id ASC
         LIMIT {$limit}"
    );
    $st->execute([$threadId]);

    return $st->fetchAll() ?: [];
}

function ia_pub_thread_last_message_id(IaPgConnection|IaPdoConnection $pdo, int $threadId): int
{
    $st = $pdo->prepare('SELECT id FROM chat_messages WHERE thread_id = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$threadId]);

    return (int) $st->fetchColumn();
}

/**
 * null = можно писать; иначе текст ошибки для пользователя.
 */
function ia_pub_chat_thread_send_error(IaPgConnection|IaPdoConnection $pdo, int $threadId, int $userId): ?string
{
    if ($threadId <= 0) {
        return 'Некорректный диалог.';
    }
    $st = $pdo->prepare('SELECT is_blocked FROM chat_threads WHERE id = ? AND (user_low_id = ? OR user_high_id = ?) LIMIT 1');
    $st->execute([$threadId, $userId, $userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return 'Диалог не найден.';
    }
    if (!empty($row['is_blocked'])) {
        return 'Этот диалог закрыт: объявление снято с продажи или продано.';
    }

    return null;
}

function ia_pub_send_chat_message(IaPgConnection|IaPdoConnection $pdo, int $threadId, int $senderId, string $body): bool
{
    $body = trim($body);
    if ($body === '' || mb_strlen($body) > 8000) {
        return false;
    }
    if (ia_pub_chat_thread_send_error($pdo, $threadId, $senderId) !== null) {
        return false;
    }
    $pdo->prepare('INSERT INTO chat_messages (thread_id, sender_id, body) VALUES (?, ?, ?)')->execute([$threadId, $senderId, $body]);
    ia_pub_mark_thread_seen($pdo, $threadId, $senderId);
    $pdo->prepare('UPDATE chat_threads SET updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$threadId]);
    $tList = $pdo->prepare('SELECT listing_id FROM chat_threads WHERE id = ? LIMIT 1');
    $tList->execute([$threadId]);
    $listingForEng = (int) ($tList->fetchColumn() ?: 0);
    if ($listingForEng > 0) {
        require_once IA_ROOT . '/includes/listing_lifecycle.php';
        ia_listing_touch_engagement($pdo, $listingForEng);
    }
    require_once IA_ROOT . '/includes/platform_notifications.php';
    ia_platform_notify_chat_message($pdo, $threadId, $senderId, $body);

    return true;
}

/**
 * @param array<string,mixed> $thread
 * @return array{name:string,avatar_url:?string,peer_id:int}
 */
function ia_pub_chat_peer_profile(array $thread, int $viewerId): array
{
    $low = (int) ($thread['user_low_id'] ?? 0);
    $high = (int) ($thread['user_high_id'] ?? 0);
    $peerId = 0;
    if ($viewerId === $low) {
        $peerId = $high;
    } elseif ($viewerId === $high) {
        $peerId = $low;
    }

    $name = trim((string) ($thread['peer_name'] ?? ''));
    $ownerId = (int) ($thread['listing_owner_id'] ?? 0);
    if ($name === '' && $peerId > 0 && $peerId === $ownerId) {
        $seller = trim((string) ($thread['listing_seller_name'] ?? ''));
        if ($seller !== '') {
            $name = $seller;
        }
    }
    if ($name === '') {
        $email = trim((string) ($thread['peer_email'] ?? ''));
        $name = $email !== '' ? $email : 'Пользователь';
    }

    require_once IA_ROOT . '/includes/helpers.php';
    $avatarPath = trim((string) ($thread['peer_avatar_path'] ?? ''));

    return [
        'name' => $name,
        'avatar_url' => ia_user_avatar_src($avatarPath !== '' ? $avatarPath : null),
        'peer_id' => $peerId,
    ];
}

function ia_pub_send_chat_media(
    IaPgConnection|IaPdoConnection $pdo,
    int $threadId,
    int $senderId,
    string $msgType,
    string $attachmentPath,
    string $attachmentName,
    string $attachmentMime,
    string $caption = ''
): bool {
    $msgType = strtolower(trim($msgType));
    if (!in_array($msgType, ['image', 'file', 'voice'], true)) {
        return false;
    }
    if ($attachmentPath === '') {
        return false;
    }
    $caption = trim($caption);
    if (mb_strlen($caption) > 8000) {
        return false;
    }

    if (ia_pub_chat_thread_send_error($pdo, $threadId, $senderId) !== null) {
        return false;
    }

    try {
        $pdo->prepare(
            'INSERT INTO chat_messages (thread_id, sender_id, body, msg_type, attachment_path, attachment_name, attachment_mime)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
        $threadId,
        $senderId,
        $caption,
        $msgType,
        $attachmentPath,
        $attachmentName,
        $attachmentMime,
        ]);
    } catch (\PDOException) {
        return false;
    }
    ia_pub_mark_thread_seen($pdo, $threadId, $senderId);
    $pdo->prepare('UPDATE chat_threads SET updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$threadId]);

    $notifyBody = $caption;
    if ($notifyBody === '') {
        $notifyBody = match ($msgType) {
            'image' => 'Фото',
            'voice' => 'Голосовое сообщение',
            default => 'Файл',
        };
    }
    $tList = $pdo->prepare('SELECT listing_id FROM chat_threads WHERE id = ? LIMIT 1');
    $tList->execute([$threadId]);
    $listingForEng = (int) ($tList->fetchColumn() ?: 0);
    if ($listingForEng > 0) {
        require_once IA_ROOT . '/includes/listing_lifecycle.php';
        ia_listing_touch_engagement($pdo, $listingForEng);
    }
    require_once IA_ROOT . '/includes/platform_notifications.php';
    ia_platform_notify_chat_message($pdo, $threadId, $senderId, $notifyBody);

    return true;
}

function ia_pub_mark_thread_seen(IaPgConnection|IaPdoConnection $pdo, int $threadId, int $userId): void
{
    $st = $pdo->prepare('SELECT user_low_id, user_high_id FROM chat_threads WHERE id = ? LIMIT 1');
    $st->execute([$threadId]);
    $row = $st->fetch();
    if (!$row) {
        return;
    }
    if ((int) $row['user_low_id'] === $userId) {
        $pdo->prepare('UPDATE chat_threads SET last_seen_low_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$threadId]);
    } elseif ((int) $row['user_high_id'] === $userId) {
        $pdo->prepare('UPDATE chat_threads SET last_seen_high_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$threadId]);
    }
}

function ia_pub_mark_all_threads_seen(IaPgConnection|IaPdoConnection $pdo, int $userId): void
{
    $pdo->prepare('UPDATE chat_threads SET last_seen_low_at = CURRENT_TIMESTAMP WHERE user_low_id = ?')->execute([$userId]);
    $pdo->prepare('UPDATE chat_threads SET last_seen_high_at = CURRENT_TIMESTAMP WHERE user_high_id = ?')->execute([$userId]);
}

/** Загрузка категорий главной / формы объявления (без Fatal, если файл не залит на сервер). */
function ia_require_home_quick_categories(): void
{
    if (function_exists('ia_home_quick_categories_definitions')) {
        return;
    }

    $path = IA_ROOT . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'home_quick_categories.php';
    if (is_file($path)) {
        require_once $path;

        return;
    }

    if (!function_exists('ia_home_quick_categories_definitions')) {
        function ia_home_quick_categories_definitions(): array
        {
            return [
                ['label' => 'Седан', 'body_type' => 'sedan', 'img' => 'Imeg/categories/sedan.png'],
                ['label' => 'Внедорожник', 'body_type' => 'suv', 'img' => 'Imeg/categories/suv.png'],
                ['label' => 'Электромобиль', 'body_type' => 'ev', 'img' => 'Imeg/categories/ev.png'],
                ['label' => 'Спорт', 'body_type' => 'sport', 'img' => 'Imeg/categories/sport.png'],
                ['label' => 'Кроссовер премиум', 'body_type' => 'crossover', 'img' => 'Imeg/categories/crossover-premium.png'],
                ['label' => 'Премиум-седан', 'body_type' => 'sedan', 'img' => 'Imeg/categories/sedan-premium.png', 'q' => 'Mercedes'],
                ['label' => 'Хэтчбек', 'body_type' => 'hatchback', 'img' => 'Imeg/categories/hatchback.png'],
                ['label' => 'Пикап', 'body_type' => 'pickup', 'img' => 'Imeg/categories/pickup.png'],
                ['label' => 'Минивэн', 'body_type' => 'van', 'img' => 'Imeg/categories/van.png'],
                [
                    'label' => 'Мотосикл',
                    'body_type' => 'motorcycle',
                    'img' => 'Imeg/categories/motorcycle.jpg',
                    'img_alts' => ['Imeg/categories/motorcycle..jpg', 'Imeg/categories/motorcycle.svg'],
                ],
                ['label' => 'Коммерческий', 'body_type' => 'truck', 'img' => 'Imeg/categories/commercial.png'],
            ];
        }
    }

    if (!function_exists('ia_home_category_image_rel')) {
        function ia_home_category_image_rel(string $primary, array $alternates = []): string
        {
            foreach (array_merge([$primary], $alternates) as $rel) {
                $rel = ltrim(str_replace('\\', '/', $rel), '/');
                if ($rel === '') {
                    continue;
                }
                $abs = IA_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                if (is_file($abs)) {
                    return $rel;
                }
            }

            return ltrim(str_replace('\\', '/', $primary), '/');
        }
    }

    if (!function_exists('ia_listing_form_body_type_options')) {
        function ia_listing_form_body_type_options(): array
        {
            $options = [];
            foreach (ia_home_quick_categories_definitions() as $item) {
                $code = trim((string) ($item['body_type'] ?? ''));
                $label = trim((string) ($item['label'] ?? ''));
                if ($code === '' || $label === '') {
                    continue;
                }
                if (!isset($options[$code])) {
                    $options[$code] = $label;
                }
            }

            return $options;
        }
    }
}
