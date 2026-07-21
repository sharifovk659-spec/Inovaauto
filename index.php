<?php

declare(strict_types=1);

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/public_queries.php';

use InnovaAuto\Security\Csrf;

$pdo = ia_db();
$cu = ia_platform_current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = (string) ($_POST['action'] ?? '');
    if (Csrf::validate($_POST['_csrf'] ?? null)) {
        $lid = (int) ($_POST['listing_id'] ?? 0);
        if ($postAction === 'toggle_fav' && $lid > 0 && $cu !== null) {
            ia_pub_toggle_favorite($pdo, (int) $cu['id'], $lid);
            ia_flash('pub_ok', 'Избранное обновлено.');
        } elseif ($postAction === 'toggle_compare' && $lid > 0) {
            $r = ia_pub_toggle_compare($pdo, $cu ? (int) $cu['id'] : 0, $lid);
            ia_flash('pub_ok', ($r['action'] ?? '') === 'added' ? 'Добавлено к сравнению.' : 'Убрано из сравнения.');
        }
    }
    ia_redirect(ia_public_url('index.php'));
}

$pageTitle = 'Главная';
$brands = ia_pub_brands_ordered($pdo);
$modelsJson = json_encode(ia_pub_models_grouped_json($pdo), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$vipListings = ia_pub_listings_vip($pdo, 12);
$latestListings = ia_pub_listings_latest($pdo, 12);
$favMap = $cu !== null ? array_fill_keys(ia_pub_favorite_ids($pdo, (int) $cu['id']), true) : [];
$homeListingIds = array_merge(
    array_map(static fn ($r): int => (int) ($r['id'] ?? 0), $vipListings),
    array_map(static fn ($r): int => (int) ($r['id'] ?? 0), $latestListings)
);
$listingThumbs = ia_pub_listing_thumbs_for_ids($pdo, $homeListingIds);
$bannerSlots = ia_pub_banners_home($pdo);
$banners = $bannerSlots['homepage'];
$promo = $bannerSlots['promo_slider'];

$catalogUrl = ia_public_url('catalog.php');
$bodyTypeCounts = function_exists('ia_pub_listing_counts_by_body_type')
    ? ia_pub_listing_counts_by_body_type($pdo)
    : [];
if ($bodyTypeCounts === [] || array_sum(array_map('intval', $bodyTypeCounts)) === 0) {
    try {
        // Fallback for hosting: bypass cache/helpers and read fresh counts directly.
        $rows = $pdo->query(
            "SELECT body_type, COUNT(*) AS listings_count
             FROM ad_listings
             WHERE status = 'approved' AND body_type <> ''
             GROUP BY body_type"
        )->fetchAll() ?: [];
        $bodyTypeCounts = [];
        foreach ($rows as $row) {
            $k = trim((string) ($row['body_type'] ?? ''));
            if ($k === '') {
                continue;
            }
            $bodyTypeCounts[$k] = (int) ($row['listings_count'] ?? 0);
        }
    } catch (Throwable $e) {
        // Keep graceful behavior even if DB/schema differs on hosting.
    }
}
$normalizeBodyType = static function (string $rawType): string {
    $value = trim($rawType);
    if ($value === '') {
        return '';
    }

    if (function_exists('ia_listing_body_type_code')) {
        return ia_listing_body_type_code($value);
    }

    $lower = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    $fallbackMap = [
        'седан' => 'sedan',
        'внедорожник' => 'suv',
        'suv' => 'suv',
        'кроссовер' => 'crossover',
        'хэтчбек' => 'hatchback',
        'пикап' => 'pickup',
        'минивэн' => 'van',
        'электромобиль' => 'ev',
        'электрокар' => 'ev',
        'спорт' => 'sport',
        'спорткар' => 'sport',
        'грузовой' => 'truck',
    ];
    if (isset($fallbackMap[$lower])) {
        return $fallbackMap[$lower];
    }

    return $lower;
};
$bodyTypeCountsNormalized = [];
foreach ($bodyTypeCounts as $rawType => $rawCount) {
    $normalizedType = $normalizeBodyType((string) $rawType);
    if ($normalizedType === '') {
        continue;
    }
    $bodyTypeCountsNormalized[$normalizedType] = (int) ($bodyTypeCountsNormalized[$normalizedType] ?? 0) + max(0, (int) $rawCount);
}

require_once IA_ROOT . '/includes/partials/home-brands-data.php';
$iaHomeImagesHelper = IA_ROOT . '/includes/home_responsive_images.php';
if (is_file($iaHomeImagesHelper)) {
    require_once $iaHomeImagesHelper;
} elseif (!function_exists('ia_responsive_webp_set')) {
    /**
     * Fallback if helper was not uploaded to hosting (prevents HTTP 500 on index only deploy).
     */
    function ia_responsive_webp_set(string $sourceRelative, int $fallbackWidth = 640, int $fallbackHeight = 480): array
    {
        $rel = ltrim(str_replace('\\', '/', $sourceRelative), '/');
        $abs = IA_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $fallback = is_file($abs) ? ia_public_asset_version($rel) : '';

        return [
            'has_webp' => false,
            'fallback' => $fallback,
            'width' => $fallbackWidth,
            'height' => $fallbackHeight,
            'sources' => [],
            'srcset' => '',
            'preload' => $fallback,
        ];
    }
    function ia_home_hero_mobile_set(): ?array
    {
        return null;
    }
    function ia_home_hero_desktop_bg_url(): string
    {
        return '';
    }
    function ia_banner_responsive_set(string $imagePath): array
    {
        $fallback = ia_uploads_banners_public_url($imagePath);

        return [
            'has_webp' => false,
            'fallback' => $fallback,
            'width' => 1920,
            'height' => 823,
            'sources' => [],
            'srcset' => '',
            'preload' => $fallback,
        ];
    }
    function ia_render_responsive_picture(array $set, array $opts = []): void
    {
        $fallback = (string) ($set['fallback'] ?? '');
        if ($fallback === '') {
            return;
        }
        $class = (string) ($opts['class'] ?? '');
        $alt = (string) ($opts['alt'] ?? '');
        $loading = (string) ($opts['loading'] ?? 'lazy');
        $decoding = (string) ($opts['decoding'] ?? 'async');
        $width = max(1, (int) ($set['width'] ?? 640));
        $height = max(1, (int) ($set['height'] ?? 480));
        $fetchpriority = (string) ($opts['fetchpriority'] ?? '');
        $perf = function_exists('ia_img_perf_attrs')
            ? ia_img_perf_attrs(['loading' => $loading, 'fetchpriority' => $fetchpriority, 'width' => $width, 'height' => $height, 'decoding' => $decoding])
            : 'width="' . $width . '" height="' . $height . '" loading="' . htmlspecialchars($loading, ENT_QUOTES, 'UTF-8') . '" decoding="' . htmlspecialchars($decoding, ENT_QUOTES, 'UTF-8') . '"';
        echo '<img class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" src="' . htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" ' . $perf . '>';
    }
}
$popularBrandsList = ia_home_popular_brands_list($pdo);

ia_require_home_quick_categories();
$quickCatsSource = ia_home_quick_categories_definitions();
$assetUrl = static function (string $relativePath): string {
    if (function_exists('ia_public_asset_version')) {
        return ia_public_asset_version($relativePath);
    }
    return ia_public_asset($relativePath);
};
$quickCats = [];
foreach ($quickCatsSource as $item) {
    $imgRel = ia_home_category_image_rel(
        (string) ($item['img'] ?? ''),
        is_array($item['img_alts'] ?? null) ? $item['img_alts'] : []
    );
    if ($imgRel === '') {
        continue;
    }
    $imgAbs = IA_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $imgRel);
    $stem = pathinfo($imgRel, PATHINFO_FILENAME);
    $webpMobileAbs = IA_ROOT . DIRECTORY_SEPARATOR . 'Imeg' . DIRECTORY_SEPARATOR . 'categories'
        . DIRECTORY_SEPARATOR . 'webp' . DIRECTORY_SEPARATOR . $stem . '-768.webp';
    if (!is_file($imgAbs) && !is_file($webpMobileAbs)) {
        continue;
    }
    $query = 'catalog.php?body_type=' . rawurlencode((string) ($item['body_type'] ?? ''));
    if (!empty($item['q'])) {
        $query .= '&q=' . rawurlencode((string) $item['q']);
    }
    $quickCats[] = [
        'label' => (string) ($item['label'] ?? ''),
        'url' => ia_public_url($query),
        'img' => $assetUrl($imgRel),
        'picture' => ia_responsive_webp_set($imgRel, 160, 160),
        'count' => max(0, (int) ($bodyTypeCountsNormalized[(string) ($item['body_type'] ?? '')] ?? 0)),
    ];
}

$iaBodyExtraClass = 'ia-page-home';

$heroMobileSet = ia_home_hero_mobile_set();
$heroMobileBannerUrl = $heroMobileSet !== null ? (string) ($heroMobileSet['fallback'] ?? '') : '';
$heroMobileBannerPreload = $heroMobileSet !== null ? (string) ($heroMobileSet['preload'] ?? $heroMobileBannerUrl) : '';
$heroDesktopBgUrl = ia_home_hero_desktop_bg_url();

$preloadHeadParts = [];
if ($heroMobileBannerPreload !== '') {
    $preloadLower = strtolower($heroMobileBannerPreload);
    $preloadType = (strlen($preloadLower) >= 5 && substr($preloadLower, -5) === '.webp') ? ' type="image/webp"' : '';
    $preloadHeadParts[] = '<link rel="preload" as="image"' . $preloadType . ' href="'
        . htmlspecialchars($heroMobileBannerPreload, ENT_QUOTES, 'UTF-8')
        . '" fetchpriority="high" media="(max-width: 991.98px)">';
}
if ($heroDesktopBgUrl !== '') {
    $preloadHeadParts[] = '<link rel="preload" as="image" href="'
        . htmlspecialchars($heroDesktopBgUrl, ENT_QUOTES, 'UTF-8')
        . '" fetchpriority="high" media="(min-width: 992px)">';
}
if ($preloadHeadParts !== []) {
    $GLOBALS['ia_extra_head_html'] = implode("\n", $preloadHeadParts);
}

require IA_ROOT . '/includes/partials/site-header.php';
?>
<?php if ($heroDesktopBgUrl !== ''): ?>
<style>body.ia-page-home{--ia-hero-desktop-img:url('<?= str_replace("'", '%27', $heroDesktopBgUrl) ?>');}</style>
<?php endif; ?>

<?php
$iaHeroDesktopPartial = IA_ROOT . '/includes/partials/home-hero-desktop.php';
if (is_file($iaHeroDesktopPartial)) {
    require $iaHeroDesktopPartial;
}
?>

<?php require IA_ROOT . '/includes/partials/home-hero-mobile.php'; ?>

<?php require IA_ROOT . '/includes/partials/home-brands-section.php'; ?>

<section class="py-4 ia-page-section ia-quick-cats ia-quick-cats--bleed">
    <div class="container ia-container">
        <div class="ia-pop-cats-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Популярные категории</h2>
                <a class="ia-pop-cats-more" href="<?= ia_h(ia_public_url('catalog.php')) ?>">Все категории →</a>
            </div>
            <div class="ia-pop-cats-slider" tabindex="0" aria-label="Популярные категории — листайте вбок">
            <div class="ia-pop-cats-row">
            <?php foreach ($quickCats as $ci => $c): ?>
                <div class="ia-pop-cats-col">
                    <a href="<?= ia_h((string) $c['url']) ?>" class="ia-pop-cat-item">
                        <span class="ia-pop-cat-head">
                            <span class="ia-pop-cat-label"><?= ia_h($c['label']) ?></span>
                        </span>
                        <span class="ia-pop-cat-thumb">
                            <?php
                            $catPicture = is_array($c['picture'] ?? null) ? $c['picture'] : ia_responsive_webp_set((string) ($c['img'] ?? ''), 160, 160);
                            ia_render_responsive_picture($catPicture, [
                                'class' => 'ia-pop-cat-img',
                                'alt' => '',
                                'sizes' => '(max-width: 575.98px) 120px, 160px',
                                'loading' => $ci < 2 ? 'eager' : 'lazy',
                                'decoding' => 'async',
                            ]);
                            ?>
                        </span>
                    </a>
                </div>
            <?php endforeach; ?>
            </div>
            </div>
        </div>
    </div>
</section>

<?php if (count($vipListings) > 0): ?>
<section class="ia-vip-section py-4 ia-page-section">
    <div class="container ia-container">
        <div class="d-flex justify-content-between align-items-end mb-3 flex-wrap gap-2">
            <div>
                <h2 class="h5 text-secondary text-uppercase letter-spacing mb-1">VIP</h2>
                <p class="mb-0 text-secondary small">Специальные предложения из базы</p>
            </div>
            <a class="small" href="<?= ia_h(ia_public_url('catalog.php')) ?>">Все объявления →</a>
        </div>
        <div class="row g-4">
            <?php foreach ($vipListings as $row): ?>
                <div class="col-12 col-sm-6 col-lg-3">
                    <article class="ia-listing-card ia-listing-card-vip">
                        <?php
                        $ph = ia_listing_photo_src($row['photo_url'] ?? null);
                        $cardThumbs = $listingThumbs[(int) $row['id']] ?? [];
                        $hoverThumbs = $cardThumbs;
                        if (!empty($hoverThumbs) && (!isset($hoverThumbs[0]) || $hoverThumbs[0] !== $ph) && $ph !== '') {
                            array_unshift($hoverThumbs, $ph);
                            $hoverThumbs = array_values(array_unique($hoverThumbs));
                        }
                        $thumbsAttr = (count($hoverThumbs) > 1)
                            ? json_encode(array_slice($hoverThumbs, 0, 6), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                            : null;
                        ?>
                        <a href="<?= ia_h(ia_public_url('car.php?id=' . (int) $row['id'])) ?>" class="ia-listing-card-img-wrap ia-card-hover<?= $thumbsAttr ? ' has-hover-thumbs' : '' ?>"<?= $thumbsAttr ? ' data-thumbs=' . "'" . htmlspecialchars($thumbsAttr, ENT_QUOTES, 'UTF-8') . "'" : '' ?>>
                            <img class="ia-listing-card-img" src="<?= ia_h($ph) ?>" alt="" <?= ia_img_perf_attrs(['width' => 640, 'height' => 480]) ?>>
                            <?php ia_render_listing_card_badges($row); ?>
                            <?php if ($thumbsAttr): ?>
                                <span class="ia-card-hover-dots" aria-hidden="true">
                                    <?php foreach ($hoverThumbs as $hi => $_): ?><span class="ia-card-hover-dot<?= $hi === 0 ? ' is-active' : '' ?>"></span><?php endforeach; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <?php if ($cu !== null): ?>
                            <?php $isFavRow = isset($favMap[(int) $row['id']]); ?>
                            <div class="ia-card-actions">
                                <form method="post" class="ia-card-action-form">
                                    <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                    <input type="hidden" name="action" value="toggle_fav">
                                    <input type="hidden" name="listing_id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="ia-card-icon-btn ia-card-icon-btn--fav<?= $isFavRow ? ' is-active' : '' ?>" aria-label="<?= $isFavRow ? 'Убрать из избранного' : 'Добавить в избранное' ?>" title="<?= $isFavRow ? 'Убрать из избранного' : 'Добавить в избранное' ?>">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 21.2l-1.45-1.32C5.4 15.25 2 12.16 2 8.5 2 5.41 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.41 22 8.5c0 3.66-3.4 6.75-8.55 11.39z"/></svg>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                        <div class="p-3 flex-grow-1 d-flex flex-column ia-vip-card-body ia-listing-card-body">
                            <a class="text-decoration-none ia-listing-title-link fw-semibold d-block mb-1 ia-card-title-clamp" href="<?= ia_h(ia_public_url('car.php?id=' . (int) $row['id'])) ?>"><?= ia_h((string) $row['brand'] . ' ' . (string) $row['model']) ?></a>
                            <?php $vipAvail = ia_listing_availability_normalize((string) ($row['availability'] ?? '')); ?>
                            <div class="ia-listing-card-meta mb-2">
                                <span class="ia-badge-availability <?= $vipAvail === 'on_order' ? 'ia-badge-availability--on-order' : 'ia-badge-availability--in-stock' ?>"><?= ia_h(ia_listing_availability_label_ru($vipAvail)) ?></span>
                                <span class="ia-price ia-price-card"><?= ia_h(ia_listing_format_price((float) $row['price'], (string) ($row['currency'] ?? 'TJS'))) ?></span>
                            </div>
                            <?php
                            $vy = isset($row['model_year']) ? (int) $row['model_year'] : 0;
                            $vyOk = $vy >= 1950;
                            $vc = trim((string) ($row['city'] ?? ''));
                            $vTr = ia_listing_transmission_label_ru((string) ($row['transmission'] ?? ''));
                            ?>
                            <dl class="row ia-vip-dl ia-listing-specs-dl small mb-0 mt-auto gx-0 gy-1">
                                <dt class="col-5 text-secondary">Год</dt>
                                <dd class="col-7 mb-0"><?= ia_h($vyOk ? (string) $vy : '—') ?></dd>
                                <dt class="col-5 text-secondary">Пробег</dt>
                                <dd class="col-7 mb-0"><?= ia_h(ia_listing_mileage_label_ru($row['mileage_km'] ?? 0)) ?></dd>
                                <dt class="col-5 text-secondary">Коробка</dt>
                                <dd class="col-7 mb-0"><?= ia_h($vTr !== '' ? $vTr : '—') ?></dd>
                                <dt class="col-5 text-secondary">Город</dt>
                                <dd class="col-7 mb-0 ia-listing-city-dd">
                                    <span class="ia-listing-city-name"><?= ia_h($vc !== '' ? $vc : '—') ?></span>
                                    <?php ia_render_listing_views_inline($row); ?>
                                </dd>
                                <dt class="col-5 text-secondary">Дата</dt>
                                <dd class="col-7 mb-0"><?= ia_h(ia_listing_pub_date_label((string) ($row['created_at'] ?? ''))) ?></dd>
                            </dl>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php
$hasPromo = count($promo) > 0;
$hasHomeBanners = count($banners) > 0;
?>
<?php if ($hasPromo || $hasHomeBanners): ?>
<section class="ia-home-media py-3 py-lg-4 border-top border-secondary border-opacity-25 ia-page-section">
    <div class="container ia-container">
        <?php if ($hasPromo): ?>
            <div id="carouselPromo" class="carousel slide ia-promo-carousel" data-bs-ride="carousel">
                <div class="carousel-inner">
                    <?php foreach ($promo as $i => $b): ?>
                        <?php
                        $bannerPath = (string) ($b['image_path'] ?? '');
                        $promoPicture = ia_banner_responsive_set($bannerPath);
                        $href = (string) ($b['link_url'] ?: '#');
                        ?>
                        <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                            <a href="<?= ia_h($href) ?>" class="ia-promo-slide">
                                <?php
                                ia_render_responsive_picture($promoPicture, [
                                    'class' => 'ia-promo-slide-img',
                                    'alt' => (string) ($b['title'] ?? ''),
                                    'sizes' => '(max-width: 767.98px) 100vw, (max-width: 991.98px) 100vw, 1200px',
                                    'loading' => 'lazy',
                                    'decoding' => 'async',
                                ]);
                                ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($promo) > 1): ?>
                    <button class="carousel-control-prev ia-promo-control" type="button" data-bs-target="#carouselPromo" data-bs-slide="prev" aria-label="Предыдущий слайд">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    </button>
                    <button class="carousel-control-next ia-promo-control" type="button" data-bs-target="#carouselPromo" data-bs-slide="next" aria-label="Следующий слайд">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($hasHomeBanners): ?>
            <div class="row g-3 g-lg-4 <?= $hasPromo ? 'mt-3 mt-lg-4' : '' ?> ia-home-banner-grid">
                <?php foreach ($banners as $b): ?>
                    <?php
                    $bannerPath = (string) ($b['image_path'] ?? '');
                    $homeBannerPicture = ia_banner_responsive_set($bannerPath);
                    $href = (string) ($b['link_url'] ?: '#');
                    ?>
                    <div class="col-md-6">
                        <a href="<?= ia_h($href) ?>" class="ia-home-banner">
                            <?php
                            ia_render_responsive_picture($homeBannerPicture, [
                                'class' => 'ia-home-banner-img',
                                'alt' => (string) ($b['title'] ?? ''),
                                'sizes' => '(max-width: 767.98px) 100vw, 50vw',
                                'loading' => 'lazy',
                                'decoding' => 'async',
                            ]);
                            ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<section class="py-4 ia-page-section">
    <div class="container ia-container">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <h2 class="h4 mb-0">Последние объявления</h2>
            <a class="small" href="<?= ia_h(ia_public_url('catalog.php')) ?>">Все →</a>
        </div>
        <?php if (count($latestListings) === 0): ?>
            <p class="text-secondary">Пока нет одобренных объявлений. Добавьте первое объявление.</p>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($latestListings as $li => $row): ?>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <article class="ia-listing-card">
                            <?php
                            $ph = ia_listing_photo_src($row['photo_url'] ?? null);
                            $cardThumbs = $listingThumbs[(int) $row['id']] ?? [];
                            $hoverThumbs = $cardThumbs;
                            if (!empty($hoverThumbs) && (!isset($hoverThumbs[0]) || $hoverThumbs[0] !== $ph) && $ph !== '') {
                                array_unshift($hoverThumbs, $ph);
                                $hoverThumbs = array_values(array_unique($hoverThumbs));
                            }
                            $thumbsAttr = (count($hoverThumbs) > 1)
                                ? json_encode(array_slice($hoverThumbs, 0, 6), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                                : null;
                            ?>
                            <a href="<?= ia_h(ia_public_url('car.php?id=' . (int) $row['id'])) ?>" class="ia-listing-card-img-wrap ia-card-hover<?= $thumbsAttr ? ' has-hover-thumbs' : '' ?>"<?= $thumbsAttr ? ' data-thumbs=' . "'" . htmlspecialchars($thumbsAttr, ENT_QUOTES, 'UTF-8') . "'" : '' ?>>
                                <img class="ia-listing-card-img" src="<?= ia_h($ph) ?>" alt="" <?= ia_img_perf_attrs([
                                    'loading' => $li < 4 ? 'eager' : 'lazy',
                                    'width' => 640,
                                    'height' => 480,
                                ]) ?>>
                                <?php ia_render_listing_card_badges($row); ?>
                                <?php if ($thumbsAttr): ?>
                                    <span class="ia-card-hover-dots" aria-hidden="true">
                                        <?php foreach ($hoverThumbs as $hi => $_): ?><span class="ia-card-hover-dot<?= $hi === 0 ? ' is-active' : '' ?>"></span><?php endforeach; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <?php if ($cu !== null): ?>
                                <?php $isFavRow = isset($favMap[(int) $row['id']]); ?>
                                <div class="ia-card-actions">
                                    <form method="post" class="ia-card-action-form">
                                        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                        <input type="hidden" name="action" value="toggle_fav">
                                        <input type="hidden" name="listing_id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="ia-card-icon-btn ia-card-icon-btn--fav<?= $isFavRow ? ' is-active' : '' ?>" aria-label="<?= $isFavRow ? 'Убрать из избранного' : 'Добавить в избранное' ?>" title="<?= $isFavRow ? 'Убрать из избранного' : 'Добавить в избранное' ?>">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 21.2l-1.45-1.32C5.4 15.25 2 12.16 2 8.5 2 5.41 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.41 22 8.5c0 3.66-3.4 6.75-8.55 11.39z"/></svg>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                            <div class="p-3 flex-grow-1 d-flex flex-column ia-listing-card-body">
                                <a class="text-decoration-none ia-listing-title-link fw-semibold d-block mb-1 ia-card-title-clamp" href="<?= ia_h(ia_public_url('car.php?id=' . (int) $row['id'])) ?>"><?= ia_h((string) $row['brand'] . ' ' . (string) $row['model']) ?></a>
                                <?php $latAvail = ia_listing_availability_normalize((string) ($row['availability'] ?? '')); ?>
                                <div class="ia-listing-card-meta mb-2">
                                    <span class="ia-badge-availability <?= $latAvail === 'on_order' ? 'ia-badge-availability--on-order' : 'ia-badge-availability--in-stock' ?>"><?= ia_h(ia_listing_availability_label_ru($latAvail)) ?></span>
                                    <span class="ia-price ia-price-card"><?= ia_h(ia_listing_format_price((float) $row['price'], (string) ($row['currency'] ?? 'TJS'))) ?></span>
                                </div>
                                <?php
                                $ly = isset($row['model_year']) ? (int) $row['model_year'] : 0;
                                $lyOk = $ly >= 1950;
                                $lc = trim((string) ($row['city'] ?? ''));
                                $latTr = ia_listing_transmission_label_ru((string) ($row['transmission'] ?? ''));
                                ?>
                                <dl class="row ia-listing-specs-dl small mb-0 mt-auto gx-0 gy-1">
                                    <dt class="col-5 text-secondary">Год</dt>
                                    <dd class="col-7 mb-0"><?= ia_h($lyOk ? (string) $ly : '—') ?></dd>
                                    <dt class="col-5 text-secondary">Пробег</dt>
                                    <dd class="col-7 mb-0"><?= ia_h(ia_listing_mileage_label_ru($row['mileage_km'] ?? 0)) ?></dd>
                                    <dt class="col-5 text-secondary">Коробка</dt>
                                    <dd class="col-7 mb-0"><?= ia_h($latTr !== '' ? $latTr : '—') ?></dd>
                                    <dt class="col-5 text-secondary">Город</dt>
                                    <dd class="col-7 mb-0 ia-listing-city-dd">
                                        <span class="ia-listing-city-name"><?= ia_h($lc !== '' ? $lc : '—') ?></span>
                                        <?php ia_render_listing_views_inline($row); ?>
                                    </dd>
                                    <dt class="col-5 text-secondary">Дата</dt>
                                    <dd class="col-7 mb-0"><?= ia_h(ia_listing_pub_date_label((string) ($row['created_at'] ?? ''))) ?></dd>
                                </dl>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
  var models = <?= $modelsJson ?>;
  function wireBrandModel(brandId, modelId, emptyLabel) {
    var b = document.getElementById(brandId);
    var m = document.getElementById(modelId);
    if (!b || !m) return;
    function fillModels() {
      var bid = String(b.value || '0');
      m.innerHTML = '<option value="0">' + emptyLabel + '</option>';
      if (bid === '0' || !models[bid]) {
        m.disabled = true;
        return;
      }
      m.disabled = false;
      models[bid].forEach(function (x) {
        var o = document.createElement('option');
        o.value = String(x.id);
        o.textContent = x.name;
        m.appendChild(o);
      });
    }
    b.addEventListener('change', fillModels);
    fillModels();
  }
  wireBrandModel('heroBrand', 'heroModel', 'Все модели');
  wireBrandModel('heroMobileBrand', 'heroMobileModel', 'Модель');
})();

(function () {
  var toggle = document.getElementById('iaHomeFilterToggle');
  var panel = document.getElementById('iaHomeMobileFilters');
  if (!toggle || !panel) return;
  var icon = toggle.querySelector('i');

  function setOpen(open) {
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? 'Скрыть фильтры' : 'Показать фильтры');
    toggle.classList.toggle('is-active', open);
    if (icon) {
      icon.className = open ? 'bi bi-x-lg' : 'bi bi-sliders';
    }
  }

  setOpen(false);

  toggle.addEventListener('click', function () {
    var open = toggle.getAttribute('aria-expanded') === 'true';
    setOpen(!open);
    if (!open) return;
    var first = panel.querySelector('select:not([disabled]), input');
    if (first) first.focus();
  });
})();

</script>

<?php require IA_ROOT . '/includes/partials/site-footer.php'; ?>
