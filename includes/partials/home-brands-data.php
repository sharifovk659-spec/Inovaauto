<?php

declare(strict_types=1);

if (!function_exists('ia_pub_popular_brands')) {
    require_once IA_ROOT . '/includes/public_queries.php';
}

/**
 * @return list<array{id:int,name:string,slug:?string,count:int}>
 */
function ia_home_brands_str_lower(string $value): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value);
    }

    return strtolower($value);
}

/**
 * Бренды для блока «Популярные бренды» на главной (порядок отображения).
 *
 * @return list<string>
 */
function ia_home_popular_brand_names(): array
{
    return [
        'Toyota', 'Mercedes-Benz', 'BMW', 'Lexus', 'Audi', 'Porsche', 'Tesla',
        'BYD', 'Zeekr', 'Li Auto', 'Hyundai', 'Land Rover', 'Opel',
    ];
}

/**
 * @return list<array{id:int,name:string,slug:?string,count:int}>
 */
function ia_home_popular_brands_fallback(): array
{
    $names = ia_home_popular_brand_names();
    $slugs = [
        'toyota', 'mercedes', 'bmw', 'lexus', 'audi', 'porsche', 'tesla',
        'byd', 'zeekr', 'liauto', 'hyundai', 'landrover', 'opel',
    ];
    $list = [];
    foreach ($names as $i => $name) {
        $list[] = [
            'id' => 0,
            'name' => $name,
            'slug' => $slugs[$i] ?? null,
            'count' => 0,
        ];
    }

    return $list;
}

/**
 * Список популярных брендов для главной (логотип + количество объявлений).
 *
 * @return list<array{id:int,name:string,slug:?string,count:int}>
 */
function ia_home_popular_brands_list(IaPgConnection|IaPdoConnection $pdo): array
{
    try {
        $popularBrands = ia_pub_popular_brands($pdo, 24);
    } catch (Throwable $e) {
        $popularBrands = [];
    }

    $brandLogoSlugMap = [
        'audi' => 'audi', 'bmw' => 'bmw', 'byd' => 'byd', 'chevrolet' => 'chevrolet',
        'ford' => 'ford', 'honda' => 'honda', 'hyundai' => 'hyundai', 'kia' => 'kia',
        'lexus' => 'lexus', 'mazda' => 'mazda', 'mercedes-benz' => 'mercedes',
        'mercedes benz' => 'mercedes', 'mercedes' => 'mercedes', 'mitsubishi' => 'mitsubishi',
        'nissan' => 'nissan', 'opel' => 'opel', 'porsche' => 'porsche', 'renault' => 'renault',
        'subaru' => 'subaru', 'suzuki' => 'suzuki', 'tesla' => 'tesla', 'toyota' => 'toyota',
        'volkswagen' => 'volkswagen', 'volvo' => 'volvo', 'lada' => 'lada', 'ваз' => 'lada',
        'citroen' => 'citroen', 'fiat' => 'fiat', 'mini' => 'mini', 'genesis' => 'genesis',
        'bentley' => 'bentley', 'ferrari' => 'ferrari', 'jeep' => 'jeep', 'skoda' => 'skoda',
        'peugeot' => 'peugeot', 'zeekr' => 'zeekr', 'li auto' => 'liauto', 'land rover' => 'landrover',
    ];

    $brandEnMap = [
        'тойота' => 'Toyota', 'бмв' => 'BMW', 'мерседес' => 'Mercedes-Benz',
        'мерседес-бенз' => 'Mercedes-Benz', 'мерседес бенс' => 'Mercedes-Benz',
        'мерседесбенз' => 'Mercedes-Benz', 'мерседес' => 'Mercedes-Benz',
        'mersedes' => 'Mercedes-Benz', 'mersedes-benz' => 'Mercedes-Benz',
        'mercedesbenz' => 'Mercedes-Benz', 'ауди' => 'Audi', 'фольксваген' => 'Volkswagen',
        'хёндэ' => 'Hyundai', 'хендай' => 'Hyundai', 'киа' => 'KIA', 'лексус' => 'Lexus',
        'ниссан' => 'Nissan', 'хонда' => 'Honda', 'шевроле' => 'Chevrolet', 'форд' => 'Ford',
        'мазда' => 'Mazda', 'субару' => 'Subaru', 'мицубиси' => 'Mitsubishi',
        'тесла' => 'Tesla', 'вольво' => 'Volvo', 'порше' => 'Porsche',
    ];

    $allBrands = ia_pub_brands_ordered($pdo);

    $brandsByName = [];
    foreach ($popularBrands as $pb) {
        $bn = trim((string) ($pb['name'] ?? ''));
        if ($bn === '') {
            continue;
        }
        $brandsByName[ia_home_brands_str_lower($bn)] = $pb;
    }
    foreach ($allBrands as $ab) {
        $bn = trim((string) ($ab['name'] ?? ''));
        if ($bn === '') {
            continue;
        }
        $key = ia_home_brands_str_lower($bn);
        if (!isset($brandsByName[$key])) {
            $brandsByName[$key] = [
                'id' => (int) ($ab['id'] ?? 0),
                'name' => $bn,
                'listings_count' => 0,
            ];
        }
    }

    $list = [];
    $seenBrandKeys = [];
    foreach (ia_home_popular_brand_names() as $bnRaw) {
        $bn = trim((string) $bnRaw);
        if ($bn === '') {
            continue;
        }
        $normalized = ia_home_brands_str_lower(str_replace(['_', '—'], [' ', '-'], $bn));
        $displayName = $brandEnMap[$normalized] ?? $bn;
        $key = ia_home_brands_str_lower($displayName);
        if (isset($seenBrandKeys[$key])) {
            continue;
        }
        $seenBrandKeys[$key] = true;

        $row = $brandsByName[ia_home_brands_str_lower($bn)] ?? $brandsByName[ia_home_brands_str_lower($displayName)] ?? null;
        $brandId = (int) ($row['id'] ?? 0);
        if ($brandId <= 0) {
            foreach ($allBrands as $ab) {
                if (ia_home_brands_str_lower((string) ($ab['name'] ?? '')) === ia_home_brands_str_lower($displayName)) {
                    $brandId = (int) ($ab['id'] ?? 0);
                    break;
                }
            }
        }
        $slugHint = $brandLogoSlugMap[$normalized]
            ?? ($brandLogoSlugMap[ia_home_brands_str_lower($displayName)] ?? null);
        $slug = function_exists('ia_brand_icon_slug')
            ? ia_brand_icon_slug($displayName, $slugHint !== null ? (string) $slugHint : null)
            : (string) ($slugHint ?? '');
        if ($slug === '') {
            $slug = null;
        }

        $list[] = [
            'id' => $brandId,
            'name' => $displayName,
            'slug' => $slug,
            'count' => (int) ($row['listings_count'] ?? 0),
        ];
    }

    if ($list === []) {
        return ia_home_popular_brands_fallback();
    }

    return $list;
}
