<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/public_queries.php';
require_once IA_ROOT . '/includes/listing_media.php';
require_once IA_ROOT . '/includes/listing_geo.php';
require_once IA_ROOT . '/includes/public_moderation.php';

$pdo = ia_db();
$id = ia_get_int('id');
$cu = ia_platform_current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start_chat' && $cu) {
    if (Csrf::validate($_POST['_csrf'] ?? null)) {
        $tid = ia_pub_get_or_create_thread($pdo, $id, (int) $cu['id']);
        if ($tid !== null) {
            ia_pub_listing_track_click($pdo, $id, (int) $cu['id']);
            ia_redirect(ia_public_url('messages.php?thread=' . $tid));
        }
        ia_flash('pub_error', 'Нельзя открыть чат: это ваше объявление, либо оно ещё на модерации.');
    } else {
        ia_flash('pub_error', 'Сессия устарела.');
    }
    ia_redirect(ia_public_url('car.php?id=' . $id));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'report_listing' && $cu) {
    $reportLid = ia_post_int('listing_id', $id);
    if (Csrf::validate($_POST['_csrf'] ?? null)) {
        $reason = ia_input_long_text($_POST['reason'] ?? '', 2000);
        if (ia_pub_submit_listing_complaint($pdo, $reportLid, (int) $cu['id'], $reason)) {
            ia_flash('pub_ok', 'Жалоба отправлена. Модераторы рассмотрят её в ближайшее время.');
        } else {
            ia_flash('pub_error', 'Не удалось отправить жалобу. Проверьте текст и попробуйте снова.');
        }
    } else {
        ia_flash('pub_error', 'Сессия устарела.');
    }
    ia_redirect(ia_public_url('car.php?id=' . $reportLid));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_fav' && $cu) {
    if (Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_pub_toggle_favorite($pdo, (int) $cu['id'], ia_post_int('listing_id'));
        ia_flash('pub_ok', 'Избранное обновлено.');
    }
    ia_redirect(ia_public_url('car.php?id=' . $id));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_compare') {
    if (Csrf::validate($_POST['_csrf'] ?? null)) {
        $res = ia_pub_toggle_compare($pdo, $cu ? (int) $cu['id'] : 0, ia_post_int('listing_id'));
        if (!empty($res['full'])) {
            ia_flash('pub_error', sprintf('Можно сравнить не более %d автомобилей. Сначала уберите один из списка.', IA_COMPARE_MAX));
        } else {
            ia_flash('pub_ok', $res['added'] ? 'Добавлено в сравнение.' : 'Убрано из сравнения.');
        }
    }
    ia_redirect(ia_public_url('car.php?id=' . $id));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'show_phone') {
    if (Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_pub_listing_track_click($pdo, $id, $cu ? (int) $cu['id'] : 0);
        if (!isset($_SESSION['pub_phone_revealed']) || !is_array($_SESSION['pub_phone_revealed'])) {
            $_SESSION['pub_phone_revealed'] = [];
        }
        $_SESSION['pub_phone_revealed'][$id] = true;
    }
    ia_redirect(ia_public_url('car.php?id=' . $id . '#phone'));
}

if ($id <= 0) {
    ia_redirect(ia_public_url('catalog.php'));
}

$listing = ia_pub_listing_by_id($pdo, $id);
if ($listing === null || !ia_pub_can_view_listing($cu, $listing)) {
    http_response_code(404);
    $pageTitle = 'Не найдено';
    require IA_ROOT . '/includes/partials/site-header.php';
    echo '<section class="py-5"><div class="container ia-container"><p class="text-secondary">Объявление недоступно или на модерации.</p><a href="' . ia_h(ia_public_url('catalog.php')) . '">В каталог</a></div></section>';
    require IA_ROOT . '/includes/partials/site-footer.php';
    exit;
}

if (($listing['status'] ?? '') === 'approved' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    ia_pub_listing_track_view(
        $pdo,
        $id,
        $cu ? (int) $cu['id'] : 0,
        (int) ($listing['user_id'] ?? 0)
    );
    $listingFresh = ia_pub_listing_by_id($pdo, $id);
    if ($listingFresh !== null) {
        $listing['views_count'] = $listingFresh['views_count'];
    }
}

$fav = $cu ? ia_pub_is_favorite($pdo, (int) $cu['id'], $id) : false;
$pageTitle = (string) $listing['brand'] . ' ' . (string) $listing['model'];
$mediaResolved = ia_listing_media_resolved($pdo, $id, $listing['photo_url'] ?? null);
$images = array_values(array_filter(
    $mediaResolved,
    static fn (array $m): bool => ($m['media_kind'] ?? '') === 'image'
));
$videos = array_values(array_filter(
    $mediaResolved,
    static fn (array $m): bool => ($m['media_kind'] ?? '') === 'video'
));
$ph = count($images) > 0
    ? ia_listing_photo_src((string) $images[0]['stored_path'])
    : ia_listing_photo_src($listing['photo_url'] ?? null);
$statusRu = ia_pub_listing_status_ru((string) ($listing['status'] ?? ''));
$isOwner = $cu && (int) ($listing['user_id'] ?? 0) === (int) $cu['id'];
$desc = trim((string) ($listing['description'] ?? ''));
$listingGeo = ia_listing_geo_from_row($listing);
$nearbyForMap = [];
if ($listingGeo !== null) {
    $nearbyForMap = ia_pub_listings_geo_nearby($pdo, (float) $listingGeo['lat'], (float) $listingGeo['lng'], $id, 14);
}
$ownerPhone = preg_replace('/\s+/', '', trim((string) ($listing['owner_phone'] ?? '')));
$phoneRevealed = !empty($_SESSION['pub_phone_revealed'][$id]);
$canCall = !$isOwner
    && ($listing['status'] ?? '') === 'approved'
    && $ownerPhone !== '';
$iaCarMapJson = 'null';
if ($listingGeo !== null) {
    $homeTitle = trim((string) $listing['brand'] . ' ' . (string) $listing['model']);
    $homeCur = (string) ($listing['currency'] ?? 'TJS');
    $pins = [[
        'id' => $id,
        'lat' => (float) $listingGeo['lat'],
        'lng' => (float) $listingGeo['lng'],
        'title' => $homeTitle,
        'price' => ia_listing_format_price((float) $listing['price'], $homeCur),
        'url' => '',
        'home' => true,
        'popupHtml' => '<b>' . ia_h($homeTitle) . '</b><br>' . ia_h(ia_listing_format_price((float) $listing['price'], $homeCur)) . '<br><span class="text-secondary">Текущее объявление</span>',
    ]];
    foreach ($nearbyForMap as $n) {
        $t = trim((string) $n['brand'] . ' ' . (string) $n['model']);
        $pr = ia_listing_format_price((float) $n['price'], (string) ($n['currency'] ?? 'TJS'));
        $url = ia_public_url('car.php?id=' . (int) $n['id']);
        $pins[] = [
            'id' => (int) $n['id'],
            'lat' => (float) $n['lat'],
            'lng' => (float) $n['lng'],
            'title' => $t,
            'price' => $pr,
            'url' => $url,
            'home' => false,
            'popupHtml' => '<b>' . ia_h($t) . '</b><br>' . ia_h($pr) . '<br><a href="' . ia_h($url) . '">Открыть объявление</a>',
        ];
    }
    $iaCarMapJson = json_encode([
        'city' => trim((string) ($listing['city'] ?? '')),
        'place' => ia_listing_geo_place_label($listing),
        'coords' => ia_listing_geo_coords_text((float) $listingGeo['lat'], (float) $listingGeo['lng']),
        'radiusM' => ia_listing_geo_nearby_radius_m(),
        'pins' => $pins,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'null';
    $GLOBALS['ia_extra_head_html'] = '<link rel="stylesheet" href="'
        . ia_h(ia_public_url('assets/vendor/leaflet/leaflet.css?v=1')) . '">';
}

$iaBodyExtraClass = 'ia-page-car';
$listingCur = (string) ($listing['currency'] ?? 'TJS');
$siteNameShare = (string) (ia_site_settings_cached($pdo)['site_name'] ?? 'InnovaAuto');

if (!function_exists('ia_listing_share_meta')) {
    $iaShareHelper = IA_ROOT . '/includes/listing_share.php';
    if (is_file($iaShareHelper)) {
        require_once $iaShareHelper;
    }
}

if (!function_exists('ia_listing_share_meta')) {
    $brand = trim((string) ($listing['brand'] ?? ''));
    $model = trim((string) ($listing['model'] ?? ''));
    $carTitle = trim($brand . ' ' . $model) !== '' ? trim($brand . ' ' . $model) : 'Автомобиль';
    $year = (int) ($listing['model_year'] ?? 0);
    $priceLabel = ia_listing_format_price((float) ($listing['price'] ?? 0), $listingCur);
    $shareTitle = $carTitle . ($year >= 1950 ? ', ' . $year : '') . ' — ' . $priceLabel;
    $carShareUrl = function_exists('ia_absolute_url')
        ? ia_absolute_url(ia_public_url('car.php?id=' . max(1, $id)))
        : ia_public_url('car.php?id=' . max(1, $id));
    $shareMeta = [
        'url' => $carShareUrl,
        'title' => $shareTitle,
        'page_title' => $carTitle,
        'description' => $shareTitle,
        'image' => '',
        'share_line' => $shareTitle,
        'share_text' => $shareTitle . "\n" . $carShareUrl,
    ];
    $carShareDisclaimerParts = [
        'heading' => 'Внимание',
        'lines' => [
            'Для вашей безопасности общайтесь только через сайт ' . $siteNameShare . '. '
            . 'При общении вне сайта (WhatsApp, Telegram и другие мессенджеры) '
            . $siteNameShare . ' не несёт ответственности.',
        ],
        'native' => 'Для вашей безопасности общайтесь только через сайт ' . $siteNameShare . '. '
            . 'При общении вне сайта (WhatsApp, Telegram и другие мессенджеры) '
            . $siteNameShare . ' не несёт ответственности.',
    ];
    $carShareDisclaimer = (string) $carShareDisclaimerParts['native'];
} else {
    $shareMeta = ia_listing_share_meta($listing, $id, $siteNameShare);
    $carShareDisclaimerParts = ia_listing_share_disclaimer_parts($siteNameShare);
    $carShareDisclaimer = ia_listing_share_disclaimer($siteNameShare);
}
if ($ph !== '' && !str_starts_with($ph, 'data:image')) {
    $shareMeta['image'] = function_exists('ia_absolute_url')
        ? ia_absolute_url($ph)
        : (preg_match('#\Ahttps?://#i', $ph) ? $ph : ia_public_url(ltrim($ph, '/')));
}
$carShareUrl = $shareMeta['url'];
$carShareTitle = $shareMeta['title'] . ' | ' . $siteNameShare;
$carShareText = $shareMeta['share_line'];
$carShareCopy = $shareMeta['share_text'];
$pageTitle = $shareMeta['page_title'];
$metaDesc = $shareMeta['description'];
$GLOBALS['ia_open_graph_html'] = function_exists('ia_open_graph_meta_html') ? ia_open_graph_meta_html([
    'title' => $shareMeta['title'] . ' | ' . $siteNameShare,
    'description' => $shareMeta['description'],
    'url' => $shareMeta['url'],
    'image' => $shareMeta['image'],
    'site_name' => $siteNameShare,
    'type' => 'article',
]) : '';
if (!empty($GLOBALS['ia_extra_head_html'])) {
    $GLOBALS['ia_extra_head_html'] .= "\n";
}
$GLOBALS['ia_extra_head_html'] = ($GLOBALS['ia_extra_head_html'] ?? '')
    . '<link rel="canonical" href="' . ia_h($carShareUrl) . '">';

require IA_ROOT . '/includes/partials/site-header.php';
?>

<section class="py-4 py-lg-5 ia-page-section ia-car-page-section">
    <div class="container ia-container">
        <?php if ($msg = ia_flash('pub_ok')): ?><div class="alert alert-success"><?= ia_h((string) $msg) ?></div><?php endif; ?>
        <?php if ($msg = ia_flash('pub_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="ia-listing-card-img-wrap rounded-3 mb-2 ia-car-hero" id="iaCarHeroWrap" style="border: 1px solid var(--ia-border)">
                    <img class="ia-listing-card-img" id="iaCarHeroImg" src="<?= ia_h($ph) ?>" alt="" <?= ia_img_perf_attrs(['loading' => 'eager', 'fetchpriority' => 'high', 'width' => 960, 'height' => 600]) ?>>
                    <?php if (!empty($listing['is_vip'])): ?>
                        <span class="ia-vip-hero-badge" aria-label="VIP объявление">
                            <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M5 16l-2-9 5.5 4L12 4l3.5 7 5.5-4-2 9H5zm0 2h14v2H5v-2z"/></svg>
                            <span>VIP</span>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($listing['is_top'])): ?>
                        <span class="ia-vip-hero-badge ia-vip-hero-badge--top" aria-label="TOP объявление">
                            <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M12 2l2.4 7.4H22l-6 4.6 2.3 7-6.3-4.6L5.7 21l2.3-7-6-4.6h7.6z"/></svg>
                            <span>TOP</span>
                        </span>
                    <?php endif; ?>
                    <button type="button" class="ia-car-hero-zoom" id="iaCarHeroZoom" aria-label="Открыть фото на весь экран" title="Открыть фото на весь экран">
                        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M3 3h7v2H5v5H3V3zm11 0h7v7h-2V5h-5V3zM3 14h2v5h5v2H3v-7zm16 0h2v7h-7v-2h5v-5z"/></svg>
                    </button>
                </div>
                <?php if (count($images) > 1): ?>
                    <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Галерея">
                        <?php foreach ($images as $idx => $im): ?>
                            <?php $thumbSrc = ia_listing_photo_src((string) $im['stored_path']); ?>
                            <button type="button" class="btn p-0 border rounded overflow-hidden ia-car-thumb<?= $idx === 0 ? ' border-primary' : '' ?>" style="width:72px;height:48px" data-full-src="<?= ia_h($thumbSrc) ?>">
                                <img src="<?= ia_h($thumbSrc) ?>" alt="" class="w-100 h-100" style="object-fit:cover" width="72" height="48" <?= ia_img_perf_attrs() ?>>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php foreach ($videos as $vi): ?>
                    <?php $vsrc = ia_listing_photo_src((string) $vi['stored_path']); ?>
                    <div class="mb-3 rounded overflow-hidden border border-secondary" style="border-color: var(--ia-border)">
                        <video src="<?= ia_h($vsrc) ?>" class="w-100" controls playsinline preload="metadata" style="max-height:420px"></video>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="col-lg-5 ia-car-detail-panel">
                <?php $carAvail = ia_listing_availability_normalize((string) ($listing['availability'] ?? '')); ?>
                <div class="ia-car-detail-head mb-2">
                    <h1 class="h3 mb-0 ia-car-title"><?= ia_h((string) $listing['brand'] . ' ' . (string) $listing['model']) ?></h1>
                    <div class="ia-car-detail-head-actions">
                        <span class="ia-badge-availability <?= $carAvail === 'on_order' ? 'ia-badge-availability--on-order' : 'ia-badge-availability--in-stock' ?>"><?= ia_h(ia_listing_availability_label_ru($carAvail)) ?></span>
                        <div class="ia-car-share-wrap">
                            <button
                                type="button"
                                class="ia-car-share-btn"
                                id="iaCarShareBtn"
                                aria-label="Поделиться ссылкой на объявление InnovaAuto"
                                aria-haspopup="dialog"
                                aria-controls="iaCarShareFlyout"
                                aria-expanded="false"
                                title="Поделиться объявлением"
                            >
                                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                                    <path fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" d="M12 4v9M8.5 8.5 12 4.5 15.5 8.5"/>
                                    <path fill="none" stroke="currentColor" stroke-width="1.85" stroke-linejoin="round" d="M7 11H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-1"/>
                                </svg>
                            </button>
                            <div class="ia-car-share-flyout" id="iaCarShareFlyout" role="dialog" aria-labelledby="iaCarShareFlyoutLabel" hidden>
                                <div class="ia-car-share-flyout__head">
                                    <h2 class="ia-car-share-flyout__title" id="iaCarShareFlyoutLabel">
                                        <i class="bi bi-share" aria-hidden="true"></i>
                                        Поделиться ссылкой
                                    </h2>
                                    <button type="button" class="ia-car-share-flyout__close" id="iaCarShareCloseBtn" aria-label="Закрыть">
                                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div class="ia-car-share-preview">
                                    <div class="ia-car-share-preview-icon" aria-hidden="true"><i class="bi bi-link-45deg"></i></div>
                                    <div class="ia-car-share-preview-text">
                                        <div class="ia-car-share-preview-title" id="iaCarShareTitlePreview"><?= ia_h($carShareTitle) ?></div>
                                        <div class="ia-car-share-preview-url text-break"><?= ia_h($carShareUrl) ?></div>
                                    </div>
                                    <button type="button" class="ia-car-share-preview-copy" id="iaCarShareCopyBtn" title="Копировать ссылку" aria-label="Копировать ссылку">
                                        <i class="bi bi-copy" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div class="ia-car-share-notice" role="alert" id="iaCarShareNotice">
                                    <span class="ia-car-share-notice-icon" aria-hidden="true"><i class="bi bi-exclamation-triangle-fill"></i></span>
                                    <div class="ia-car-share-notice-body">
                                        <p class="ia-car-share-notice-heading mb-1"><?= ia_h($carShareDisclaimerParts['heading']) ?></p>
                                        <?php foreach ($carShareDisclaimerParts['lines'] as $line): ?>
                                            <p class="ia-car-share-notice-line mb-0"><?= ia_h($line) ?></p>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <button type="button" class="btn ia-btn-accent w-100 mb-2 ia-car-share-native-cta" id="iaCarShareNativeBtn">
                                    <i class="bi bi-share me-1" aria-hidden="true"></i>
                                    Поделиться ссылкой
                                </button>
                                <p class="ia-car-share-apps-title mb-2">Поделиться с помощью</p>
                                <div class="ia-car-share-apps" id="iaCarShareApps">
                                    <a class="ia-car-share-app" data-share-app="native" href="#" role="button">
                                        <span class="ia-car-share-app-icon ia-car-share-app-icon--system"><i class="bi bi-share"></i></span>
                                        <span class="ia-car-share-app-label">Системное меню</span>
                                    </a>
                                    <a class="ia-car-share-app" data-share-app="whatsapp" href="#" target="_blank" rel="noopener noreferrer">
                                        <span class="ia-car-share-app-icon ia-car-share-app-icon--wa">WA</span>
                                        <span class="ia-car-share-app-label">WhatsApp</span>
                                    </a>
                                    <a class="ia-car-share-app" data-share-app="telegram" href="#" target="_blank" rel="noopener noreferrer">
                                        <span class="ia-car-share-app-icon ia-car-share-app-icon--tg"><i class="bi bi-telegram"></i></span>
                                        <span class="ia-car-share-app-label">Telegram</span>
                                    </a>
                                    <a class="ia-car-share-app" data-share-app="copy" href="#" role="button">
                                        <span class="ia-car-share-app-icon ia-car-share-app-icon--copy"><i class="bi bi-clipboard"></i></span>
                                        <span class="ia-car-share-app-label">Копировать</span>
                                    </a>
                                </div>
                                <input type="text" class="visually-hidden" id="iaCarShareUrlField" value="<?= ia_h($carShareUrl) ?>" readonly tabindex="-1" aria-hidden="true">
                            </div>
                        </div>
                    </div>
                </div>
                <p class="ia-price ia-car-price mb-1 mb-md-2"><?= ia_h(ia_listing_format_price((float) $listing['price'], $listingCur)) ?></p>
                <p class="ia-car-views-stat mb-2 mb-md-3">
                    <i class="bi bi-eye-fill" aria-hidden="true"></i>
                    <span><?= ia_h(ia_listing_views_label_ru(ia_listing_views_count($listing))) ?></span>
                </p>
                <?php if (!empty($listing['prepayment_amount']) && (float) $listing['prepayment_amount'] > 0 && $carAvail === 'on_order'): ?>
                    <p class="ia-car-prepay small mb-3"><span class="ia-car-prepay-dot"></span>Размер предоплаты: <strong><?= ia_h(ia_listing_format_price((float) $listing['prepayment_amount'], $listingCur)) ?></strong></p>
                <?php endif; ?>
                <?php
                // Build full specs table
                $specs = [];
                if (($body = (string) ($listing['body_type'] ?? '')) !== '') { $lbl = ia_listing_body_label_ru_pub($body); if ($lbl !== '') $specs['Кузов'] = $lbl; }
                if ((int) ($listing['model_year'] ?? 0) >= 1950) $specs['Год выпуска'] = (int) $listing['model_year'];
                if (($cl = trim((string) ($listing['color'] ?? ''))) !== '') $specs['Цвет'] = $cl;
                if (($dr = (string) ($listing['drive_type'] ?? '')) !== '') $specs['Привод'] = ia_listing_drive_type_label_ru($dr);
                if (($ev = trim((string) ($listing['engine_volume'] ?? ''))) !== '') $specs['Объём двигателя'] = $ev;
                if (isset($listing['mileage_km']) && $listing['mileage_km'] !== null && $listing['mileage_km'] !== '') $specs['Пробег'] = number_format((float) $listing['mileage_km'], 0, '.', ' ') . ' км';
                if (array_key_exists('has_turbo', $listing)) $specs['Турбина'] = !empty($listing['has_turbo']) ? 'Да' : 'Нет';
                if (($cs = (string) ($listing['condition_state'] ?? '')) !== '') $specs['Состояние'] = ia_listing_condition_label_ru($cs);
                if (($ft = (string) ($listing['fuel_type'] ?? '')) !== '') { $lbl = ia_listing_fuel_label_ru($ft); if ($lbl !== '') $specs['Вид топлива'] = $lbl; }
                if (array_key_exists('customs_cleared', $listing)) $specs['Растаможен в РТ'] = !empty($listing['customs_cleared']) ? 'Да' : 'Нет';
                if (($tr = (string) ($listing['transmission'] ?? '')) !== '') { $lbl = ia_listing_transmission_label_ru($tr); if ($lbl !== '') $specs['Коробка передач'] = $lbl; }
                if (array_key_exists('taxi_license', $listing)) $specs['Лицензия на такси'] = !empty($listing['taxi_license']) ? 'Да' : 'Нет';
                if (($ct = trim((string) ($listing['city'] ?? ''))) !== '') $specs['Город'] = $ct;
                $geoPlaceLabel = ia_listing_geo_place_label($listing);
                if ($geoPlaceLabel !== '') {
                    $specs['Местоположение'] = $geoPlaceLabel;
                }
                $vin = trim((string) ($listing['vin'] ?? ''));
                if ($vin !== '') {
                    $specs['VIN'] = $vin;
                }
                ?>
                <?php if (!empty($specs)): ?>
                    <h2 class="h6 text-uppercase text-secondary mb-2">Характеристики</h2>
                    <div class="ia-car-specs-grid ia-car-specs-grid--force-2 mb-3" role="list">
                        <?php foreach ($specs as $k => $v): ?>
                            <article class="ia-car-spec-card" role="listitem">
                                <span class="ia-car-spec-icon" aria-hidden="true"><i class="bi <?= ia_h(ia_car_spec_icon((string) $k)) ?>"></i></span>
                                <div class="ia-car-spec-body">
                                    <span class="ia-car-spec-label"><?= ia_h((string) $k) ?></span>
                                    <span class="ia-car-spec-value"><?= ia_h((string) $v) ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($listingGeo !== null): ?>
                    <div class="ia-car-map-cta mb-3">
                        <button type="button" class="ia-car-map-btn" data-bs-toggle="modal" data-bs-target="#iaCarGeoMapModal" aria-label="Показать расположение на карте">
                            <span class="ia-car-map-btn-icon" aria-hidden="true"><i class="bi bi-map-fill"></i></span>
                            <span class="ia-car-map-btn-label">На карте</span>
                        </button>
                    </div>
                <?php endif; ?>
                <?php if (trim((string) ($listing['city'] ?? '')) !== '' && $listingGeo === null): ?>
                    <p class="small text-secondary mb-4">Город: <?= ia_h((string) $listing['city']) ?></p>
                <?php endif; ?>
                <div class="ia-car-meta-strip mb-3" role="list">
                    <div class="ia-car-meta-item" role="listitem">
                        <span class="ia-car-meta-icon" aria-hidden="true"><i class="bi bi-person-circle"></i></span>
                        <span class="ia-car-meta-body">
                            <span class="ia-car-meta-label">Продавец</span>
                            <span class="ia-car-meta-value"><?= ia_h((string) $listing['seller_name']) ?></span>
                        </span>
                    </div>
                    <div class="ia-car-meta-item" role="listitem">
                        <span class="ia-car-meta-icon ia-car-meta-icon--status" aria-hidden="true"><i class="bi bi-patch-check-fill"></i></span>
                        <span class="ia-car-meta-body">
                            <span class="ia-car-meta-label">Статус</span>
                            <span class="ia-car-meta-value"><span class="ia-car-status-pill <?= ia_h(ia_pub_listing_status_css_class((string) ($listing['status'] ?? ''))) ?>"><?= ia_h($statusRu) ?></span></span>
                        </span>
                    </div>
                </div>

                <?php $inCompare = ia_pub_is_in_compare($pdo, $cu ? (int) $cu['id'] : 0, (int) $listing['id']); ?>
                <div class="ia-car-actions mb-3">
                    <div class="ia-car-actions-primary">
                    <?php if (!$isOwner && ($listing['status'] ?? '') === 'approved' && $cu): ?>
                        <form method="post" class="ia-car-actions-form mb-0">
                            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                            <input type="hidden" name="action" value="start_chat">
                            <button type="submit" class="btn ia-btn-accent ia-car-cta">Написать</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($canCall): ?>
                        <?php if ($phoneRevealed): ?>
                            <a class="btn btn-outline-secondary ia-car-cta ia-car-cta--phone" id="phone" href="tel:<?= ia_h($ownerPhone) ?>">
                                <span class="ia-car-cta-stack">
                                    <span class="ia-car-cta-stack-label">Позвонить</span>
                                    <span class="ia-car-cta-stack-value"><?= ia_h($ownerPhone) ?></span>
                                </span>
                            </a>
                        <?php else: ?>
                            <form method="post" class="ia-car-actions-form mb-0">
                                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                <input type="hidden" name="action" value="show_phone">
                                <button type="submit" class="btn btn-outline-secondary ia-car-cta">Позвонить</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                    </div>
                    <div class="ia-car-actions-secondary">
                    <form method="post" class="ia-car-actions-form mb-0">
                        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                        <input type="hidden" name="action" value="toggle_compare">
                        <input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
                        <button type="submit" class="ia-car-action-btn ia-car-action-btn--compare<?= $inCompare ? ' is-active' : '' ?>" aria-label="<?= $inCompare ? 'Убрать из сравнения' : 'Добавить к сравнению' ?>" title="<?= $inCompare ? 'Убрать из сравнения' : 'Добавить к сравнению' ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9 3v18H7v-3H3v-2h4V8H3V6h4V3h2zm6 0v3h4v2h-4v8h4v2h-4v3h-2V3h2z"/></svg>
                            <span class="ia-car-action-btn-text"><?= $inCompare ? 'В сравнении' : 'Сравнить' ?></span>
                        </button>
                    </form>
                    <?php if ($cu): ?>
                        <form method="post" class="ia-car-actions-form mb-0">
                            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                            <input type="hidden" name="action" value="toggle_fav">
                            <input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
                            <button type="submit" class="ia-car-action-btn ia-car-action-btn--fav<?= $fav ? ' is-active' : '' ?>" aria-label="<?= $fav ? 'Убрать из избранного' : 'В избранное' ?>" title="<?= $fav ? 'Убрать из избранного' : 'В избранное' ?>">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 21.2l-1.45-1.32C5.4 15.25 2 12.16 2 8.5 2 5.41 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.41 22 8.5c0 3.66-3.4 6.75-8.55 11.39z"/></svg>
                            <span class="ia-car-action-btn-text"><?= $fav ? 'В избранном' : 'Избранное' ?></span>
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if ($cu && !$isOwner): ?>
                        <button type="button" class="ia-car-action-btn ia-car-action-btn--report" data-bs-toggle="modal" data-bs-target="#iaReportListingModal" aria-label="Пожаловаться на объявление">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2 1 21h22L12 2zm0 4.5 7.5 13.5h-15L12 6.5zM11 10v4h2v-4h-2zm0 6v2h2v-2h-2z"/></svg>
                            <span class="ia-car-action-btn-text">Жалоба</span>
                        </button>
                    <?php endif; ?>
                    </div>
                </div>
                <?php if (!$cu && !$isOwner && ($listing['status'] ?? '') === 'approved'): ?>
                    <div class="ia-car-contact-banner mb-3" role="note">
                        <p class="ia-car-contact-banner-title mb-1">Связь с продавцом — на <?= ia_h($siteNameShare) ?></p>
                        <p class="ia-car-contact-banner-text small mb-2">Не нужно искать контакты вне сайта. Войдите или зарегистрируйтесь — и напишите продавцу в безопасном чате на платформе.</p>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-sm ia-btn-accent" href="<?= ia_h(ia_public_url('login.php?redirect=' . rawurlencode('car.php?id=' . $id))) ?>">Войти и написать</a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= ia_h(ia_public_url('register.php?redirect=' . rawurlencode('car.php?id=' . $id))) ?>">Регистрация</a>
                        </div>
                    </div>
                <?php elseif (!$cu): ?>
                    <p class="small text-secondary mb-3"><a href="<?= ia_h(ia_public_url('login.php?redirect=' . rawurlencode('car.php?id=' . $id))) ?>">Войдите</a>, чтобы написать продавцу или добавить в избранное.</p>
                <?php endif; ?>

                <?php if ($desc !== ''): ?>
                    <?php $needsClamp = mb_strlen($desc) > 320 || substr_count($desc, "\n") > 6; ?>
                    <div class="mt-4 ia-car-desc-block">
                        <h2 class="h6 text-uppercase text-secondary mb-2">Описание</h2>
                        <div class="ia-car-desc<?= $needsClamp ? ' is-collapsed' : '' ?>" data-clamped="<?= $needsClamp ? '1' : '0' ?>">
                            <div class="ia-car-desc-text"><?= nl2br(ia_h($desc)) ?></div>
                            <?php if ($needsClamp): ?>
                                <div class="ia-car-desc-fade" aria-hidden="true"></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($needsClamp): ?>
                            <button type="button" class="ia-car-desc-toggle" data-collapsed="Развернуть" data-expanded="Свернуть">Развернуть</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($isOwner): ?>
                    <div class="mt-5 pt-4 border-top border-secondary">
                        <h2 class="h6 text-uppercase text-secondary mb-3">Это ваше объявление</h2>
                        <p class="small text-secondary mb-3">Редактируйте данные, фото и цену. Покупатели могут написать вам в разделе «Сообщения».</p>
                        <a class="btn ia-btn-accent" href="<?= ia_h(ia_public_url('edit-listing.php?id=' . (int) $listing['id'])) ?>">Редактировать объявление</a>
                        <a class="btn btn-outline-light ms-2" href="<?= ia_h(ia_public_url('profile.php')) ?>">Мои объявления в профиле</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<div class="ia-car-share-backdrop" id="iaCarShareBackdrop" hidden aria-hidden="true"></div>

<?php if ($cu && !$isOwner): ?>
<div class="modal fade" id="iaReportListingModal" tabindex="-1" aria-labelledby="iaReportListingLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content ia-form-surface">
            <form method="post">
                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                <input type="hidden" name="action" value="report_listing">
                <input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
                <div class="modal-header border-0">
                    <h2 class="modal-title h5" id="iaReportListingLabel">Пожаловаться на объявление</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary">Опишите, что не так с объявлением. Модераторы проверят жалобу.</p>
                    <textarea name="reason" class="form-control" rows="4" required maxlength="2000" placeholder="Например: фото не соответствуют автомобилю"></textarea>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn ia-btn-accent">Отправить жалобу</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($listingGeo !== null): ?>
<div class="modal fade" id="iaCarGeoMapModal" tabindex="-1" aria-labelledby="iaCarGeoMapLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content ia-form-surface">
            <div class="modal-header border-0">
                <div>
                    <h2 class="modal-title h5 mb-0" id="iaCarGeoMapLabel">На карте</h2>
                    <p class="small text-secondary mb-0 mt-1" id="iaCarGeoMapSub"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body pt-0">
                <div class="ia-car-map-wrap rounded border overflow-hidden">
                    <div id="iaCarGeoMapCanvas" class="ia-car-nearby-map-el"></div>
                </div>
                <ul class="ia-car-map-legend list-unstyled small d-flex flex-wrap gap-3 mb-0 mt-2" aria-label="Обозначения на карте">
                    <li class="ia-car-map-legend-item"><span class="ia-car-map-dot ia-car-map-dot--home" aria-hidden="true"></span>Это объявление</li>
                    <li class="ia-car-map-legend-item"><span class="ia-car-map-dot ia-car-map-dot--near" aria-hidden="true"></span>Другие авто рядом</li>
                </ul>
                <?php
                $zoneRadiusM = (int) round(ia_listing_geo_nearby_radius_m());
                if ($nearbyForMap !== []): ?>
                <div class="ia-car-geo-zone-list border-top pt-2 mt-2">
                    <div class="fw-semibold small mb-2">Другие авто в зоне ~<?= $zoneRadiusM ?> м на сайте</div>
                    <ul class="list-unstyled small mb-0 ia-car-geo-zone-scroll">
                        <?php foreach ($nearbyForMap as $nr): ?>
                            <li class="mb-1 d-flex justify-content-between align-items-baseline gap-2">
                                <a href="<?= ia_h(ia_public_url('car.php?id=' . (int) $nr['id'])) ?>"><?= ia_h(trim((string) $nr['brand'] . ' ' . (string) $nr['model'])) ?></a>
                                <span class="text-nowrap text-secondary"><?= ia_h(ia_listing_format_price((float) $nr['price'], (string) ($nr['currency'] ?? 'TJS'))) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <p class="small text-secondary mt-2 mb-0">
                    <a id="iaCarGeoMapOsm" href="#" target="_blank" rel="noopener noreferrer">Открыть в OpenStreetMap</a>
                </p>
            </div>
        </div>
    </div>
</div>
<script>window.iaCarMapData = <?= $iaCarMapJson ?>;</script>
<script src="<?= ia_h(ia_public_asset_version('assets/vendor/leaflet/leaflet.js')) ?>" defer></script>
<script src="<?= ia_h(ia_script_href('assets/js/car-map.js', 'assets/js/car-map.min.js')) ?>" defer></script>
<?php endif; ?>

<?php
$jsImages = array_map(static function (array $im): string {
    return ia_listing_photo_src((string) $im['stored_path']);
}, $images);
if (empty($jsImages)) {
    $jsImages = [$ph];
}
?>
<?php
$iaCarShareJson = json_encode([
    'url' => $carShareUrl,
    'title' => $carShareTitle,
    'text' => $carShareText,
    'copy' => $carShareDisclaimer !== '' ? ($carShareDisclaimer . "\n\n" . $carShareCopy) : $carShareCopy,
    'disclaimer' => $carShareDisclaimer,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
if ($iaCarShareJson === false) {
    $iaCarShareJson = '{}';
}
?>
<script type="application/json" id="iaCarShareData"><?= $iaCarShareJson ?></script>
<div class="ia-lightbox" id="iaLightbox" role="dialog" aria-modal="true" aria-label="Просмотр фото" hidden>
    <div class="ia-lightbox-toolbar">
        <button type="button" class="ia-lightbox-btn" data-act="zoom-out" title="Уменьшить" aria-label="Уменьшить">−</button>
        <span class="ia-lightbox-zoom" id="iaLightboxZoom">100%</span>
        <button type="button" class="ia-lightbox-btn" data-act="zoom-in" title="Увеличить" aria-label="Увеличить">+</button>
        <button type="button" class="ia-lightbox-btn" data-act="reset" title="100%" aria-label="100%">⟲</button>
        <span class="ia-lightbox-counter" id="iaLightboxCounter"></span>
        <button type="button" class="ia-lightbox-btn ia-lightbox-close" data-act="close" title="Закрыть" aria-label="Закрыть">✕</button>
    </div>
    <button type="button" class="ia-lightbox-nav ia-lightbox-prev" data-act="prev" aria-label="Предыдущее фото">‹</button>
    <button type="button" class="ia-lightbox-nav ia-lightbox-next" data-act="next" aria-label="Следующее фото">›</button>
    <div class="ia-lightbox-stage" id="iaLightboxStage">
        <img id="iaLightboxImg" alt="">
    </div>
</div>
<script>
(function(){
  var hero = document.getElementById('iaCarHeroImg');
  var btns = document.querySelectorAll('.ia-car-thumb');
  var heroOriginal = hero ? (hero.getAttribute('src') || '') : '';
  btns.forEach(function(btn){
    btn.addEventListener('click', function(){
      var src = btn.getAttribute('data-full-src');
      if (src && hero) {
        hero.src = src;
        heroOriginal = src;
      }
      btns.forEach(function(b){ b.classList.remove('border-primary'); });
      btn.classList.add('border-primary');
    });
    btn.addEventListener('mouseenter', function(){
      var src = btn.getAttribute('data-full-src');
      if (src && hero) hero.src = src;
    });
    btn.addEventListener('focus', function(){
      var src = btn.getAttribute('data-full-src');
      if (src && hero) hero.src = src;
    });
  });
  var thumbsRow = btns.length ? btns[0].parentNode : null;
  if (thumbsRow && hero) {
    thumbsRow.addEventListener('mouseleave', function(){
      if (heroOriginal) hero.src = heroOriginal;
    });
  }

  var images = <?= json_encode(array_values($jsImages), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  var lb = document.getElementById('iaLightbox');
  var lbImg = document.getElementById('iaLightboxImg');
  var lbStage = document.getElementById('iaLightboxStage');
  var lbZoomLabel = document.getElementById('iaLightboxZoom');
  var lbCounter = document.getElementById('iaLightboxCounter');
  var heroZoom = document.getElementById('iaCarHeroZoom');
  var idx = 0, scale = 1;

  function render() {
    if (!images.length) return;
    lbImg.src = images[idx];
    scale = 1;
    applyScale();
    lbCounter.textContent = (idx + 1) + ' / ' + images.length;
  }
  function applyScale() {
    lbImg.style.transform = 'scale(' + scale + ')';
    lbZoomLabel.textContent = Math.round(scale * 100) + '%';
  }
  function open(byHero) {
    if (!images.length) return;
    if (byHero && hero) {
      var heroSrc = (hero.getAttribute('src') || '').split('?')[0];
      images.forEach(function (src, i) { if ((src || '').split('?')[0] === heroSrc) idx = i; });
    }
    lb.hidden = false;
    document.body.classList.add('ia-no-scroll');
    render();
  }
  function close() {
    lb.hidden = true;
    document.body.classList.remove('ia-no-scroll');
  }
  function prev() { idx = (idx - 1 + images.length) % images.length; render(); }
  function next() { idx = (idx + 1) % images.length; render(); }
  function zoom(delta) {
    scale = Math.max(0.5, Math.min(4, scale + delta));
    applyScale();
  }

  if (heroZoom) heroZoom.addEventListener('click', function () { open(true); });
  if (hero) hero.addEventListener('click', function () { open(true); });
  btns.forEach(function (btn, i) {
    btn.addEventListener('dblclick', function () { idx = i; open(false); });
  });

  lb.addEventListener('click', function (e) {
    var act = (e.target && e.target.getAttribute('data-act')) || '';
    if (act === 'close' || e.target === lb) close();
    else if (act === 'prev') prev();
    else if (act === 'next') next();
    else if (act === 'zoom-in') zoom(0.25);
    else if (act === 'zoom-out') zoom(-0.25);
    else if (act === 'reset') { scale = 1; applyScale(); }
  });
  document.addEventListener('keydown', function (e) {
    if (lb.hidden) return;
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowLeft') prev();
    else if (e.key === 'ArrowRight') next();
    else if (e.key === '+' || e.key === '=') zoom(0.25);
    else if (e.key === '-' || e.key === '_') zoom(-0.25);
  });
  if (lbStage) {
    lbStage.addEventListener('wheel', function (e) {
      if (lb.hidden) return;
      e.preventDefault();
      zoom(e.deltaY > 0 ? -0.15 : 0.15);
    }, { passive: false });
  }

  // Description toggle
  document.querySelectorAll('.ia-car-desc-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var block = btn.previousElementSibling;
      if (!block) return;
      var collapsed = block.classList.toggle('is-collapsed');
      btn.textContent = collapsed
        ? (btn.getAttribute('data-collapsed') || 'Развернуть')
        : (btn.getAttribute('data-expanded') || 'Свернуть');
    });
  });
})();
</script>
<?php
?>
<script src="<?= ia_h(ia_script_href('assets/js/car-share.js', 'assets/js/car-share.min.js')) ?>" defer></script>
<?php require IA_ROOT . '/includes/partials/site-footer.php'; ?>
