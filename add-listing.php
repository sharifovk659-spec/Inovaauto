<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/public_queries.php';
require_once IA_ROOT . '/includes/listing_uploads.php';
require_once IA_ROOT . '/includes/listing_media.php';
require_once IA_ROOT . '/includes/listing_geo.php';

ia_platform_require_login();

$pdo = ia_db();
$uid = (int) ia_platform_current_user()['id'];
$iaPromoStatus = ia_promotion_status($pdo);
$iaPromoTop = ia_promotion_resolve($pdo, 'top');
$iaPromoVip = ia_promotion_resolve($pdo, 'vip');
$iaPromoTopDays = ia_promotion_tariff_days($pdo, 'top') ?? 30;
$iaPromoVipDays = ia_promotion_tariff_days($pdo, 'vip') ?? 60;
$brands = ia_pub_brands_ordered($pdo);
$modelsJson = json_encode(ia_pub_models_grouped_json($pdo), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$iaAddListingPostUrl = rtrim(ia_site_base_url(), '/') . '/add-listing.php';
$iaProfilePendingUrl = ia_public_url('profile.php?list=pending&published=1');

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && empty($_POST)
    && empty($_FILES)
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    ia_flash(
        'pub_error',
        'Слишком большой объём данных (фото/видео). Уменьшите размер файлов или загрузите меньше фото — лимит сервера (post_max_size / upload_max_filesize).'
    );
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('pub_error', 'Сессия устарела.');
    } else {
        try {
        $bid = ia_post_int('brand_id');
        $mid = ia_post_int('model_id');
        $price = ia_input_decimal($_POST['price'] ?? '0', 0) ?? 0.0;
        $desc = ia_input_long_text($_POST['description'] ?? '', 8000);
        $seller = ia_post_text('seller_name', 120);
        $promotion = ia_input_enum($_POST['promotion'] ?? 'normal', ['normal', 'top', 'vip'], 'normal');
        $promoFlags = ia_promotion_apply_to_flags($promotion);
        $isVip = $promoFlags['is_vip'];
        $isTop = $promoFlags['is_top'];
        $uploadReject = null;
        $savedMedia = ia_listing_collect_saved_uploads(IA_LISTING_MEDIA_MAX_FILES, $uploadReject);
        $uploadTried = false;
        foreach (ia_listing_normalize_files_array($_FILES['listing_media'] ?? null) as $fx) {
            if ((int) ($fx['error'] ?? 0) === UPLOAD_ERR_OK) {
                $uploadTried = true;
                break;
            }
        }
        if ($uploadReject !== null && $uploadReject !== '') {
            ia_flash('pub_error', $uploadReject);
        } elseif ($uploadTried && count($savedMedia) === 0) {
            ia_listing_rollback_saved_uploads($savedMedia);
            ia_flash('pub_error', 'Файлы не приняты: фото (JPEG/PNG/WebP/GIF до 5 МБ) или видео (MP4/WebM до 100 МБ).');
        } else {
            $availability = ia_listing_availability_normalize($_POST['availability'] ?? null);
            $countsMedia = ia_listing_count_media_kinds_from_saved($savedMedia);
            $receivedUploads = ia_listing_count_ok_upload_files($_FILES['listing_media'] ?? null);
            $mediaErr = ia_listing_validate_required_photo_slots($countsMedia['images'], $receivedUploads)
                ?? ia_listing_validate_media_for_availability(
                    $availability,
                    $countsMedia['images'],
                    $countsMedia['videos'],
                    false
                );
            if ($mediaErr !== null) {
                ia_listing_rollback_saved_uploads($savedMedia);
                ia_flash('pub_error', $mediaErr);
            } else {
            $primaryImageIndex = max(0, (int) ($_POST['primary_image_index'] ?? 0));
            $imagePos = [];
            foreach ($savedMedia as $idx => $sm) {
                if (($sm['kind'] ?? '') === 'image') {
                    $imagePos[] = $idx;
                }
            }
            if (isset($imagePos[$primaryImageIndex]) && isset($imagePos[0]) && $imagePos[$primaryImageIndex] !== $imagePos[0]) {
                $targetPos = $imagePos[$primaryImageIndex];
                $firstPos = $imagePos[0];
                $tmp = $savedMedia[$firstPos];
                $savedMedia[$firstPos] = $savedMedia[$targetPos];
                $savedMedia[$targetPos] = $tmp;
            }
            $photoStored = null;
            foreach ($savedMedia as $sm) {
                if ($sm['kind'] === 'image') {
                    $photoStored = $sm['path'];
                    break;
                }
            }
            $stb = $pdo->prepare('SELECT name FROM car_brands WHERE id = ?');
            $stb->execute([$bid]);
            $bname = (string) $stb->fetchColumn();
            $stm = $pdo->prepare('SELECT name FROM car_models WHERE id = ? AND brand_id = ?');
            $stm->execute([$mid, $bid]);
            $mname = (string) $stm->fetchColumn();
            $listingGeo = ia_listing_geo_validate_post($_POST);
            if ($bname === '' || $mname === '' || $price <= 0 || $seller === '') {
                ia_listing_rollback_saved_uploads($savedMedia);
                ia_flash('pub_error', 'Выберите бренд и модель из каталога, укажите цену и имя продавца.');
            } elseif ($listingGeo === null) {
                ia_listing_rollback_saved_uploads($savedMedia);
                $geoHint = 'Нажмите синюю кнопку с иконкой геолокации справа от поля «Имя продавца», разрешите доступ к местоположению и дождитесь сообщения «Местоположение зафиксировано».';
                if ($seller === '') {
                    ia_flash('pub_error', 'Укажите имя продавца и зафиксируйте местоположение. ' . $geoHint);
                } else {
                    ia_flash('pub_error', 'Местоположение не зафиксировано. ' . $geoHint);
                }
            } elseif (($promoErr = ia_promotion_validate_listing_choice($pdo, $promotion)) !== null) {
                ia_listing_rollback_saved_uploads($savedMedia);
                ia_flash('pub_error', $promoErr);
            } else {
                $needsPromoPayment = ia_promotion_payment_required_for_choice($pdo, $promotion);
                $saveVip = $needsPromoPayment ? 0 : $isVip;
                $saveTop = $needsPromoPayment ? 0 : $isTop;
                $y = ia_post_int('model_year');
                $modelYear = ($y >= 1950 && $y <= 2100) ? $y : null;
                $mileageKm = ia_post_int('mileage_km', -1, 0);
                $mileageKm = $mileageKm >= 0 ? $mileageKm : null;
                require_once IA_ROOT . '/includes/tj_cities.php';
                $city = ia_tj_city_normalize(ia_post_text('city', 80));
                $vinRaw = ia_input_vin($_POST['vin'] ?? '');
                $vin = $vinRaw === '' ? null : $vinRaw;
                if ($city !== '' && !ia_tj_city_is_allowed($city)) {
                    $city = '';
                }
                $geoPlace = ia_listing_geo_place_sanitize((string) ($_POST['listing_geo_place'] ?? ''));
                if ($geoPlace === '' && $listingGeo !== null) {
                    try {
                        $revPlace = ia_listing_geo_reverse_geocode($listingGeo['lat'], $listingGeo['lng']);
                        if ($revPlace !== null) {
                            if ($city === '' && $revPlace['city'] !== '') {
                                $city = ia_tj_city_normalize($revPlace['city']);
                            }
                            $geoPlace = ia_listing_geo_place_sanitize($revPlace['place']);
                        }
                    } catch (\Throwable $ignore) {
                    }
                }
                ia_require_home_quick_categories();
                $bodyAllowed = array_merge(
                    [''],
                    array_keys(ia_listing_form_body_type_options()),
                    ['wagon', 'coupe', 'cabrio', 'bus', 'other']
                );
                $bodyAllowed = array_values(array_unique($bodyAllowed));
                $bodyRaw = ia_input_enum($_POST['body_type'] ?? '', $bodyAllowed);
                $bodyType = $bodyRaw;
                $fuelAllowed = ['', 'petrol', 'diesel', 'gas', 'hybrid', 'electric'];
                $fuelType = ia_input_enum($_POST['fuel_type'] ?? '', $fuelAllowed);
                $transAllowed = ['', 'auto', 'manual', 'robot', 'cvt'];
                $transmission = ia_input_enum($_POST['transmission'] ?? '', $transAllowed);
                $color = ia_input_text($_POST['color'] ?? '', 40);
                $driveAllowed = ['', 'front', 'rear', 'awd', '4wd'];
                $driveType = ia_input_enum($_POST['drive_type'] ?? '', $driveAllowed);
                $engineVolume = ia_input_text($_POST['engine_volume'] ?? '', 40);
                $hasTurbo = !empty($_POST['has_turbo']) ? 1 : 0;
                $condAllowed = ['', 'new', 'used'];
                $conditionState = ia_input_enum($_POST['condition_state'] ?? '', $condAllowed);
                $customsCleared = !empty($_POST['customs_cleared']) ? 1 : 0;
                $taxiLicense = !empty($_POST['taxi_license']) ? 1 : 0;
                $prepayClean = ia_input_decimal($_POST['prepayment_amount'] ?? null, 0);
                if ($availability !== 'on_order') {
                    $prepayClean = null;
                }
                $currency = ia_listing_currency_normalize($_POST['currency'] ?? null);
                require_once IA_ROOT . '/includes/db_compat.php';
                ia_listing_ensure_save_ready($pdo);
                try {
                    $pdo->beginTransaction();
                    $insertSql = 'INSERT INTO ad_listings (user_id, photo_url, brand, model, vin, description, model_year, mileage_km, city, body_type, fuel_type, transmission, price, seller_name, availability, status, is_vip, is_top,
                            color, drive_type, engine_volume, has_turbo, condition_state, customs_cleared, taxi_license, prepayment_amount, currency,
                            listing_geo_lat, listing_geo_lng, listing_geo_captured_at, listing_geo_accuracy_m, listing_geo_place)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                    if (ia_db_is_pgsql($pdo)) {
                        $insertSql .= ' RETURNING id';
                    }
                    $ins = $pdo->prepare($insertSql);
                    $ins->execute([
                        $uid,
                        $photoStored,
                        $bname,
                        $mname,
                        $vin,
                        $desc !== '' ? $desc : null,
                        $modelYear,
                        $mileageKm,
                        $city,
                        $bodyType,
                        $fuelType,
                        $transmission,
                        $price,
                        $seller,
                        $availability,
                        $saveVip,
                        $saveTop,
                        $color,
                        $driveType,
                        $engineVolume,
                        $hasTurbo,
                        $conditionState,
                        $customsCleared,
                        $taxiLicense,
                        $prepayClean,
                        $currency,
                        $listingGeo['lat'],
                        $listingGeo['lng'],
                        $listingGeo['captured_at'],
                        $listingGeo['accuracy'],
                        $geoPlace,
                    ]);
                    if (ia_db_is_pgsql($pdo)) {
                        $newId = (int) $ins->fetchColumn();
                    } else {
                        $newId = ia_db_last_insert_id($pdo, null);
                    }
                    if ($newId <= 0) {
                        throw new \RuntimeException('Не получен идентификатор нового объявления после INSERT.');
                    }
                    $ord = 0;
                    foreach ($savedMedia as $sm) {
                        ia_listing_media_insert($pdo, $newId, $sm['kind'], $sm['path'], $ord++);
                    }
                    ia_listing_sync_primary_photo($pdo, $newId);
                    if ($needsPromoPayment) {
                        ia_promotion_create_listing_payment($pdo, $uid, $newId, $promotion);
                    }
                    $pdo->commit();
                    if ($needsPromoPayment) {
                        ia_flash('pub_ok', 'Объявление создано. Оплатите тариф — затем модерация.');
                        ia_redirect(ia_public_url('pay-promotion.php?listing_id=' . $newId));
                    }
                    ia_flash('pub_ok', 'Объявление отправлено на модерацию.');
                    ia_redirect(ia_public_url('profile.php?list=pending&published=1'));
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) {
                        try {
                    $pdo->rollBack();
                        } catch (\Throwable $ignore) {
                        }
                    }
                    ia_listing_rollback_saved_uploads($savedMedia);
                    error_log('ia_add_listing_save: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                    $failMsg = 'Не удалось сохранить объявление. Попробуйте ещё раз.';
                    if ($e instanceof \PDOException) {
                        $sqlState = (string) ($e->errorInfo[0] ?? '');
                        if (in_array($sqlState, ['42P01', '42S02'], true)) {
                            $failMsg = 'Ошибка базы: не найдена таблица (схема не обновлена). Обратитесь к администратору.';
                        } elseif (in_array($sqlState, ['42703', '42S22'], true)) {
                            $failMsg = 'Ошибка базы: отсутствует колонка (схема не обновлена). Обратитесь к администратору.';
                        } elseif ($sqlState === '23502') {
                            $failMsg = 'Ошибка базы: не заполнено обязательное поле.';
                        } elseif ($sqlState === '23503') {
                            $failMsg = 'Ошибка базы: нарушение связи данных (например, пользователь или медиа).';
                        } elseif ($sqlState === '23505') {
                            $failMsg = 'Ошибка базы: дублирование уникального значения.';
                        }
                    }
                    $dbg = getenv('IA_DEBUG');
                    $showTech = ($dbg === '1' || $dbg === 'true' || strtolower((string) $dbg) === 'on')
                        || !empty(ia_config()['app']['show_listing_save_errors']);
                    if ($showTech) {
                        $failMsg .= ' [' . $e->getMessage() . ']';
                    }
                    ia_flash('pub_error', $failMsg);
                    ia_redirect(ia_public_url('add-listing.php'));
                }
                }
            }
        }
        } catch (\Throwable $e) {
            if (isset($savedMedia) && is_array($savedMedia)) {
                ia_listing_rollback_saved_uploads($savedMedia);
            }
            error_log('ia_add_listing_post: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $failMsg = 'Не удалось сохранить объявление. Попробуйте ещё раз.';
            if ($e instanceof \PDOException) {
                $sqlState = (string) ($e->errorInfo[0] ?? '');
                if (in_array($sqlState, ['42P01', '42S02'], true)) {
                    $failMsg = 'Ошибка базы: не найдена таблица (схема не обновлена). Обратитесь к администратору.';
                } elseif (in_array($sqlState, ['42703', '42S22'], true)) {
                    $failMsg = 'Ошибка базы: отсутствует колонка (схема не обновлена). Обратитесь к администратору.';
                }
            }
            ia_flash('pub_error', $failMsg);
            ia_redirect(ia_public_url('add-listing.php'));
        }
    }
}

$pageTitle = 'Разместить объявление';
$iaBodyExtraClass = 'ia-page-add-listing ia-add-premium';
$GLOBALS['ia_extra_head_html'] = '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">'
    . '<link rel="stylesheet" href="' . ia_h(ia_stylesheet_href('assets/add-listing-premium.css', 'assets/add-listing-premium.min.css')) . '">';

require IA_ROOT . '/includes/partials/site-header.php';
?>

<div class="ia-premium-bg" aria-hidden="true">
    <div class="ia-premium-orb ia-premium-orb--1"></div>
    <div class="ia-premium-orb ia-premium-orb--2"></div>
    <div class="ia-premium-orb ia-premium-orb--3"></div>
</div>

<section class="ia-page-section ia-cabinet-page ia-add-listing-page-section ia-premium-page-section">
    <div class="container ia-container ia-premium-wrap">
        <header class="ia-premium-hero ia-premium-hero--clean">
            <div class="ia-premium-hero-copy">
                <h1 class="ia-premium-title">Разместить объявление</h1>
                <p class="ia-premium-lead">Заполните разделы формы и отправьте на модерацию</p>
                <div class="ia-premium-steps-meta ia-add-steps-meta visually-hidden" aria-hidden="true">
                    <span>Шаг <span id="iaAddStepCur">1</span> из 6</span>
                    <span class="ia-premium-steps-pct ia-add-steps-pct" id="iaAddStepPct">17%</span>
                </div>
                <div class="ia-premium-progress ia-add-steps-bar" id="iaAddProgressSegs" role="progressbar" aria-valuemin="1" aria-valuemax="6" aria-valuenow="1" aria-label="Прогресс заполнения">
                    <span class="ia-premium-progress-seg is-active"></span>
                    <span class="ia-premium-progress-seg"></span>
                    <span class="ia-premium-progress-seg"></span>
                    <span class="ia-premium-progress-seg"></span>
                    <span class="ia-premium-progress-seg"></span>
                    <span class="ia-premium-progress-seg"></span>
                </div>
            </div>
        </header>

        <?php if ($msg = ia_flash('pub_error')): ?><div class="alert alert-danger ia-premium-alert"><?= ia_h((string) $msg) ?></div><?php endif; ?>
        <div id="iaPublishOkBanner" class="alert alert-success ia-premium-alert ia-publish-ok-alert d-none" role="status" aria-live="polite">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Объявление на проверке. После одобрения модератора появится в каталоге.
        </div>

        <form method="post" enctype="multipart/form-data" action="<?= ia_h($iaAddListingPostUrl) ?>" class="ia-add-listing-layout" id="iaAddListingForm" novalidate data-ia-post-url="<?= ia_h($iaAddListingPostUrl) ?>" data-ia-profile-pending="<?= ia_h($iaProfilePendingUrl) ?>" data-ia-paid-promo="<?= $iaPromoStatus['paid_required'] ? '1' : '0' ?>">
            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="primary_image_index" id="iaPrimaryImageIndex" value="0">
                <div class="ia-add-main-card ia-premium-form-card">
                    <div class="ia-add-accordion ia-premium-accordion" id="iaAddAccordion">

                        <details class="ia-add-section" data-add-step="1">
                            <summary class="ia-add-section-head">
                                <span class="ia-add-section-num">1</span>
                                <span class="ia-add-section-icon" aria-hidden="true"><i class="bi bi-ui-checks"></i></span>
                                <span class="ia-add-section-head-text">
                                    <span class="ia-add-section-title">Основные данные</span>
                                    <span class="ia-add-section-sub">Марка, модель, год, цена</span>
                                </span>
                                <span class="ia-add-section-arrow" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                            </summary>
                            <div class="ia-add-section-body row g-3">
                        <div class="col-6 col-md-6">
                            <label class="form-label visually-hidden" for="fldBrand">Марка</label>
                            <select name="brand_id" id="fldBrand" class="form-select" required aria-label="Марка">
                                <option value="">Выберите марку</option>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?= (int) $b['id'] ?>"><?= ia_h((string) $b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-6">
                            <label class="form-label visually-hidden" for="fldModel">Модель</label>
                            <select name="model_id" id="fldModel" class="form-select" required disabled aria-label="Модель">
                                <option value="">Выберите модель</option>
                            </select>
                        </div>
                        <div class="col-12">
                                    <label class="form-label visually-hidden" for="fldVin">VIN</label>
                                    <input type="text" name="vin" id="fldVin" class="form-control" maxlength="17" autocomplete="off" placeholder="VIN" aria-label="VIN">
                                </div>
                                <div class="col-6 col-md-4">
                            <label class="form-label visually-hidden" for="fldYear">Год выпуска</label>
                            <?php
                            $iaYearSelectName = 'model_year';
                            $iaYearSelectId = 'fldYear';
                            $iaYearSelectClass = 'form-select';
                            $iaYearSelectValue = '';
                            $iaYearSelectEmpty = 'Все';
                            $iaYearSelectAriaLabel = 'Год выпуска';
                            $iaYearSelectRequired = true;
                            require IA_ROOT . '/includes/partials/year-select.php';
                            ?>
                        </div>
                                <div class="col-6 col-md-4">
                            <label class="form-label visually-hidden" for="fldCity">Город</label>
                                    <?php
                                    $iaCitySelectName = 'city';
                                    $iaCitySelectId = 'fldCity';
                                    $iaCitySelectClass = 'form-select';
                                    $iaCitySelectValue = 'Душанбе';
                                    $iaCitySelectRequired = true;
                                    $iaCitySelectNoEmpty = true;
                                    require IA_ROOT . '/includes/partials/city-select.php';
                                    ?>
                        </div>
                                <div class="col-6 col-md-4">
                            <label class="form-label visually-hidden" for="fldMileage">Пробег, км</label>
                                    <input type="number" name="mileage_km" id="fldMileage" class="form-control" min="0" step="1" value="0" placeholder="Пробег, км" aria-label="Пробег, км">
                                </div>
                                <div class="col-6 col-md-4">
                                    <label class="form-label visually-hidden" for="fldCurrency">Валюта</label>
                                    <select name="currency" id="fldCurrency" class="form-select" required aria-label="Валюта">
                                        <?php foreach (ia_listing_currencies() as $cc => $ci): ?>
                                            <option value="<?= ia_h($cc) ?>" <?= $cc === 'TJS' ? 'selected' : '' ?>><?= ia_h((string) $ci['label_ru']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-md-8">
                                    <label class="form-label visually-hidden" for="fldPrice">Цена</label>
                                    <input type="number" name="price" id="fldPrice" class="form-control" min="1" step="1" required placeholder="Цена" aria-label="Цена">
                        </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label visually-hidden" for="fldSellerName">Имя продавца</label>
                                    <div class="input-group ia-seller-geo-shell">
                                        <input type="text" name="seller_name" id="fldSellerName" class="form-control ia-seller-name-input" required maxlength="150" autocomplete="name" placeholder="Имя продавца" aria-label="Имя продавца">
                                        <button type="button" class="btn ia-listing-geo-btn" id="iaListingGeoBtn" title="Местоположение" aria-label="Зафиксировать местоположение">
                                            <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <input type="hidden" name="listing_geo_lat" id="iaListingGeoLat" value="">
                                    <input type="hidden" name="listing_geo_lng" id="iaListingGeoLng" value="">
                                    <input type="hidden" name="listing_geo_captured_at" id="iaListingGeoTs" value="">
                                    <input type="hidden" name="listing_geo_accuracy_m" id="iaListingGeoAcc" value="">
                                    <input type="hidden" name="listing_geo_place" id="iaListingGeoPlace" value="">
                                    <input type="hidden" name="listing_geo_payload" id="iaListingGeoPayload" value="">
                                    <div id="iaListingGeoBanner" class="ia-listing-geo-banner ia-listing-geo-banner--hint" role="status" aria-live="polite">Введите имя продавца и нажмите <strong>синюю кнопку геолокации</strong> справа — без этого объявление не отправится.</div>
                                </div>
                                <div class="col-12 ia-avail-stack">
                                    <label class="form-label visually-hidden">Наличие автомобиля</label>
                                    <div class="ia-avail-segment" id="iaAvailSegment" role="group" aria-label="Наличие автомобиля">
                                        <label class="ia-avail-segment-btn">
                                            <input type="radio" name="availability" value="in_stock" checked>
                                            <span class="ia-avail-segment-inner"><i class="bi bi-check2" aria-hidden="true"></i> В наличии</span>
                                        </label>
                                        <label class="ia-avail-segment-btn">
                                            <input type="radio" name="availability" value="on_order">
                                            <span class="ia-avail-segment-inner"><i class="bi bi-check2" aria-hidden="true"></i> На заказ</span>
                                        </label>
                                    </div>
                                    <div class="ia-prepay-wrap d-none" id="iaPrepayWrap">
                                        <label class="form-label visually-hidden" for="fldPrepay">Размер предоплаты</label>
                                        <input type="number" name="prepayment_amount" class="form-control" min="0" step="1" id="fldPrepay" placeholder="Размер предоплаты" aria-label="Размер предоплаты">
                                        <div class="form-text">Только для «На заказ» — размер предоплаты для бронирования.</div>
                                    </div>
                                </div>
                            </div>
                        </details>

                        <details class="ia-add-section" data-add-step="2">
                            <summary class="ia-add-section-head">
                                <span class="ia-add-section-num">2</span>
                                <span class="ia-add-section-icon" aria-hidden="true"><i class="bi bi-gear"></i></span>
                                <span class="ia-add-section-head-text">
                                    <span class="ia-add-section-title">Технические характеристики</span>
                                    <span class="ia-add-section-sub">Укажите параметры и комплектацию</span>
                                </span>
                                <span class="ia-add-section-arrow" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                            </summary>
                            <div class="ia-add-section-body row g-3">
                        <div class="col-md-6">
                            <label class="form-label visually-hidden" for="fldBodyType">Тип кузова</label>
                            <select name="body_type" id="fldBodyType" class="form-select" aria-label="Тип кузова">
                                        <option value="">— не указан —</option>
                                        <?php
                                        ia_require_home_quick_categories();
                                        foreach (ia_listing_form_body_type_options() as $bodyCode => $bodyLabel): ?>
                                            <option value="<?= ia_h((string) $bodyCode) ?>"><?= ia_h((string) $bodyLabel) ?></option>
                                        <?php endforeach; ?>
                            </select>
                        </div>
                                <div class="col-md-6">
                                    <label class="form-label visually-hidden" for="fldColor">Цвет</label>
                                    <input type="text" name="color" id="fldColor" class="form-control" maxlength="40" placeholder="Цвет" aria-label="Цвет">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label visually-hidden" for="fldDrive">Привод</label>
                                    <select name="drive_type" id="fldDrive" class="form-select" aria-label="Привод">
                                        <?php foreach (ia_listing_drive_type_options() as $dk => $dv): ?>
                                            <option value="<?= ia_h((string) $dk) ?>"><?= ia_h((string) $dv) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label visually-hidden" for="fldEngine">Объём двигателя</label>
                                    <input type="text" name="engine_volume" id="fldEngine" class="form-control" maxlength="40" placeholder="Объём двигателя" aria-label="Объём двигателя">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label visually-hidden" for="fldFuel">Топливо</label>
                            <select name="fuel_type" id="fldFuel" class="form-select" aria-label="Топливо">
                                        <option value="">— не указано —</option>
                                <option value="petrol">Бензин</option>
                                <option value="diesel">Дизель</option>
                                <option value="gas">Газ</option>
                                <option value="hybrid">Гибрид</option>
                                        <option value="electric">Электричество</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                                    <label class="form-label visually-hidden" for="fldTransmission">Коробка передач</label>
                            <select name="transmission" id="fldTransmission" class="form-select" aria-label="Коробка передач">
                                        <option value="">— не указано —</option>
                                <option value="auto">Автомат</option>
                                <option value="manual">Механика</option>
                                <option value="robot">Робот</option>
                                        <option value="cvt">Вариатор</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                                    <label class="form-label visually-hidden" for="fldCondition">Состояние</label>
                                    <select name="condition_state" id="fldCondition" class="form-select" aria-label="Состояние">
                                        <?php foreach (ia_listing_condition_options() as $ck => $cv): ?>
                                            <option value="<?= ia_h((string) $ck) ?>"><?= ia_h((string) $cv) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                        </div>
                        <div class="col-12">
                                    <h3 class="h6 ia-doc-checks-title mb-2">Документы</h3>
                        </div>
                                <div class="col-md-6 ia-doc-checks d-flex flex-column pt-md-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="fldTurbo" name="has_turbo" value="1">
                                        <label class="form-check-label" for="fldTurbo">Турбина</label>
                        </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="fldCustoms" name="customs_cleared" value="1" checked>
                                        <label class="form-check-label" for="fldCustoms">Растаможен в РТ</label>
                        </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="fldTaxi" name="taxi_license" value="1">
                                        <label class="form-check-label" for="fldTaxi">Лицензия на такси</label>
                    </div>
                    </div>
                </div>
                        </details>

                        <details class="ia-add-section" id="iaAddPhotoSection" data-add-step="3">
                            <summary class="ia-add-section-head">
                                <span class="ia-add-section-num">3</span>
                                <span class="ia-add-section-icon" aria-hidden="true"><i class="bi bi-camera"></i></span>
                                <span class="ia-add-section-head-text">
                                    <span class="ia-add-section-title">Фото автомобиля</span>
                                    <span class="ia-add-section-sub" id="iaPhotoSectionMeta">Добавьте качественные фотографии</span>
                                </span>
                                <span class="ia-add-section-arrow" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                            </summary>
                            <div class="ia-add-section-body ia-photo-studio">
                                <div class="ia-photo-studio-head">
                                    <h2 class="ia-photo-studio-title h5 mb-2">Сделайте <?= (int) IA_LISTING_PHOTO_REQUIRED_COUNT ?> обязательных фото автомобиля</h2>
                                    <p class="ia-photo-studio-lead mb-0">Ещё <?= (int) (IA_LISTING_PHOTO_SLOT_COUNT - IA_LISTING_PHOTO_REQUIRED_COUNT) ?> ракурсов — по желанию. Первый слот — главное фото.</p>
            </div>
                                <ul class="ia-photo-checklist list-unstyled row g-2 g-md-3 mb-3" id="iaPhotoChecklist">
                                    <li class="col-6 col-md-4 col-xl" data-photo-check="quality"><span class="ia-photo-checklist-item is-pending"><i class="bi bi-magic" aria-hidden="true"></i>Автопроверка качества</span></li>
                                    <li class="col-6 col-md-4 col-xl" data-photo-check="angle"><span class="ia-photo-checklist-item is-pending"><i class="bi bi-bounding-box" aria-hidden="true"></i>Правильный ракурс</span></li>
                                    <li class="col-6 col-md-4 col-xl" data-photo-check="lighting"><span class="ia-photo-checklist-item is-pending"><i class="bi bi-brightness-high" aria-hidden="true"></i>Хорошее освещение</span></li>
                                    <li class="col-6 col-md-4 col-xl" data-photo-check="crop"><span class="ia-photo-checklist-item is-pending"><i class="bi bi-aspect-ratio" aria-hidden="true"></i>Без обрезки</span></li>
                                    <li class="col-6 col-md-4 col-xl" data-photo-check="clarity"><span class="ia-photo-checklist-item is-pending"><i class="bi bi-eye" aria-hidden="true"></i>Чёткость изображения</span></li>
                    </ul>
                                <div id="iaPhotoSlotBanner" class="alert alert-warning d-none mb-3" role="alert"></div>
                                <input type="file" id="fldListingMedia" class="d-none" multiple accept="image/jpeg,image/png,image/webp">
                                <div class="ia-photo-workspace">
                                    <div class="ia-photo-workspace-main">
                                        <div class="ia-photo-slot-grid" id="iaPhotoSlotGrid">
                                            <?php foreach (ia_listing_photo_slot_labels_ru() as $slotIdx => $slotLabel):
                                                $slotCardLabel = ia_listing_photo_slot_display_label_ru((int) $slotIdx);
                                                $slotExampleRaster = ia_listing_photo_slot_example_uses_raster((int) $slotIdx);
                                                $slotHasSample = ia_listing_photo_slot_example_size((int) $slotIdx) !== null;
                                                ?>
                                                <article class="ia-photo-slot<?= $slotHasSample ? ' ia-photo-slot--sample-overlay' : '' ?><?= ia_listing_photo_slot_is_required((int) $slotIdx) ? '' : ' ia-photo-slot--optional' ?>" data-slot="<?= (int) $slotIdx ?>" aria-label="<?= ia_h($slotCardLabel) ?>">
                                                    <div class="ia-photo-slot-stage">
                                                        <header class="ia-photo-slot-head">
                                                            <span class="ia-photo-slot-num"><?= (int) $slotIdx + 1 ?></span>
                                                            <h3 class="ia-photo-slot-label"><?= ia_h($slotCardLabel) ?></h3>
                                                        </header>
                                                        <div class="ia-photo-slot-example<?= $slotExampleRaster ? ' ia-photo-slot-example--raster' : '' ?>" aria-hidden="true">
                                                            <?= ia_listing_photo_slot_example_markup((int) $slotIdx) ?>
                </div>
                                                        <input type="file" class="ia-photo-slot-input d-none" data-slot="<?= (int) $slotIdx ?>" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" capture="environment">
                                                        <div class="ia-photo-slot-preview d-none" data-slot="<?= (int) $slotIdx ?>">
                                                            <img src="" alt="">
                                                            <button type="button" class="ia-photo-slot-remove" data-slot="<?= (int) $slotIdx ?>" aria-label="Удалить фото">×</button>
                </div>
                                                        <button type="button" class="ia-photo-slot-btn" data-slot="<?= (int) $slotIdx ?>" aria-label="<?= ia_h('Снять фото: ' . $slotCardLabel) ?>">
                                                            <i class="bi bi-camera-fill" aria-hidden="true"></i>
                                                            <span>Снять фото</span>
                                                        </button>
                        </div>
                                                    <div class="ia-photo-slot-status" data-slot="<?= (int) $slotIdx ?>" data-status="pending">
                                                        <span class="ia-photo-slot-status-icon" aria-hidden="true"></span>
                                                        <span class="ia-photo-slot-status-label">Ожидает фото</span>
                                                    </div>
                                                    <div class="ia-photo-slot-error small text-danger d-none" data-slot="<?= (int) $slotIdx ?>" role="alert"></div>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <aside class="ia-photo-studio-aside" aria-label="Превью и советы">
                                        <div class="ia-photo-hero-preview ia-form-surface">
                                            <div class="ia-photo-hero-empty" id="iaPhotoHeroEmpty">Снимите ракурсы — здесь появится крупное превью.</div>
                                            <img src="" alt="" class="ia-photo-hero-image d-none" id="iaPhotoHeroImage">
                                        </div>
                                        <div class="ia-photo-thumb-wall d-none" id="iaAddThumbGrid" role="list" aria-label="Миниатюры принятых фото"></div>
                                        <div class="ia-photo-tips ia-form-surface">
                                            <h3 class="h6 mb-2">Советы для лучших фото</h3>
                                            <ul class="ia-photo-tips-list list-unstyled mb-0">
                                                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i>Снимайте при хорошем освещении</li>
                                                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i>Не обрезайте автомобиль</li>
                                                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i>Держите камеру ровно</li>
                                                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i>Автомобиль должен быть чистым</li>
                                                <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i>Госномер должен читаться</li>
                                            </ul>
                                        </div>
                                        <div class="ia-photo-help ia-form-surface">
                                            <h3 class="h6 mb-2">Нужна помощь?</h3>
                                            <p class="small text-secondary mb-2">Видеообзор можно добавить в следующем разделе «Видео».</p>
                                            <button type="button" class="btn btn-outline-primary btn-sm w-100" id="iaPhotoHelpVideoBtn">Перейти к видео</button>
                                        </div>
                                    </aside>
                                </div>
                                <div class="ia-photo-studio-how ia-form-surface">
                                    <h3 class="h6 mb-3">Как это работает?</h3>
                                    <ol class="ia-photo-how-steps list-unstyled mb-0">
                                        <li><span class="ia-photo-how-num">1</span>Нажмите «Снять фото»</li>
                                        <li><span class="ia-photo-how-num">2</span>Сделайте фото по инструкции</li>
                                        <li><span class="ia-photo-how-num">3</span>Система проверяет качество</li>
                                        <li><span class="ia-photo-how-num">4</span>Фото сохраняется в объявлении</li>
                                    </ol>
                                </div>
                                <div class="ia-photo-progress ia-form-surface" id="iaPhotoProgress" aria-live="polite">
                                    <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
                                        <h3 class="h6 mb-0">Прогресс загрузки</h3>
                                        <span class="small fw-semibold" id="iaPhotoProgressLabel">0 из <?= (int) IA_LISTING_PHOTO_REQUIRED_COUNT ?> обязательных</span>
                                    </div>
                                    <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="<?= (int) IA_LISTING_PHOTO_REQUIRED_COUNT ?>" aria-valuenow="0" aria-labelledby="iaPhotoProgressLabel">
                                        <div class="progress-bar" id="iaPhotoProgressBar" style="width:0%"></div>
                                    </div>
                                    <p class="small text-secondary mb-0 mt-2" id="iaPhotoProgressDone">Загрузите <?= (int) IA_LISTING_PHOTO_REQUIRED_COUNT ?> обязательных ракурсов.</p>
                                </div>
                                <div class="ia-photo-studio-nav d-flex justify-content-between align-items-center gap-3 mt-3">
                                    <button type="button" class="btn btn-outline-secondary" id="iaPhotoStepBack">← Назад</button>
                                    <button type="button" class="btn ia-btn-accent px-4" id="iaPhotoStepNext" disabled>Продолжить →</button>
                                </div>
                            </div>
                        </details>

                        <details class="ia-add-section" id="iaAddVideoSection" data-add-step="4">
                            <summary class="ia-add-section-head">
                                <span class="ia-add-section-num">4</span>
                                <span class="ia-add-section-icon" aria-hidden="true"><i class="bi bi-camera-video"></i></span>
                                <span class="ia-add-section-head-text">
                                    <span class="ia-add-section-title">Видео</span>
                                    <span class="ia-add-section-sub">Прикрепите видеообзор автомобиля</span>
                                </span>
                                <span class="ia-add-section-arrow" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                            </summary>
                            <div class="ia-add-section-body ia-video-studio">
                                <h2 class="h5 mb-2">Видеообзор автомобиля</h2>
                                <p class="ia-video-studio-lead text-secondary small mb-3">Необязательный ролик. MP4 или WebM до 100 МБ — отдельно от обязательных фото.</p>
                                <label class="form-label visually-hidden" for="fldListingVideo">Файл видео</label>
                                <input type="file" id="fldListingVideo" class="form-control" accept="video/mp4,video/webm">
                                <div class="ia-add-video-shell d-none mt-3" id="iaAddVideoFormPreview">
                                    <video id="iaAddPreviewVideoForm" controls playsinline preload="metadata"></video>
                                </div>
                            </div>
                        </details>

                        <details class="ia-add-section" data-add-step="5">
                            <summary class="ia-add-section-head">
                                <span class="ia-add-section-num">5</span>
                                <span class="ia-add-section-icon" aria-hidden="true"><i class="bi bi-file-text"></i></span>
                                <span class="ia-add-section-head-text">
                                    <span class="ia-add-section-title">Описание</span>
                                    <span class="ia-add-section-sub">Расскажите подробнее об автомобиле</span>
                                </span>
                                <span class="ia-add-section-arrow" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                            </summary>
                            <div class="ia-add-section-body">
                                <textarea name="description" class="form-control" rows="5" placeholder="Описание автомобиля" aria-label="Описание"></textarea>
                            </div>
                        </details>

                        <details class="ia-add-section" data-add-step="6">
                            <summary class="ia-add-section-head">
                                <span class="ia-add-section-num">6</span>
                                <span class="ia-add-section-icon" aria-hidden="true"><i class="bi bi-tag"></i></span>
                                <span class="ia-add-section-head-text">
                                    <span class="ia-add-section-title">Тариф размещения</span>
                                    <span class="ia-add-section-sub">Выберите подходящий тариф</span>
                                </span>
                                <span class="ia-add-section-arrow" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                            </summary>
                            <div class="ia-add-section-body">
                                <?php if ($iaPromoStatus['in_grace_period']): ?>
                                    <p class="alert alert-success small mb-3 mb-md-2" role="status">
                                        VIP и TOP бесплатно до <?= ia_h($iaPromoStatus['grace_ends_at']->format('d.m.Y')) ?>
                                        (осталось <?= (int) $iaPromoStatus['days_left'] ?> дн.).
                                    </p>
                                <?php elseif (!$iaPromoStatus['monetization_enabled']): ?>
                                    <p class="alert alert-info small mb-3 mb-md-2" role="status">Монетизация VIP/TOP отключена администратором — пакеты бесплатны.</p>
                                <?php elseif ($iaPromoStatus['paid_required']): ?>
                                    <p class="alert alert-warning small mb-3 mb-md-2" role="status">VIP и TOP платные: после публикации — оплата, затем проверка администратором.</p>
                                <?php endif; ?>
                                <div class="ia-premium-tariffs">
                                    <label class="ia-premium-tariff">
                                        <input type="radio" name="promotion" value="normal" checked>
                                        <p class="ia-premium-tariff-name">Базовый</p>
                                        <p class="ia-premium-tariff-price">0 TJS</p>
                                        <p class="ia-premium-tariff-period">7 дней</p>
                                        <ul class="ia-premium-tariff-features">
                                            <li>Стандартное размещение</li>
                                            <li>До 10 фото</li>
                                        </ul>
                                    </label>
                                    <label class="ia-premium-tariff">
                                        <input type="radio" name="promotion" value="top">
                                        <span class="ia-premium-tariff-badge">Популярный</span>
                                        <p class="ia-premium-tariff-name">Стандарт</p>
                                        <p class="ia-premium-tariff-price"><?= ia_h($iaPromoTop['price_label']) ?></p>
                                        <p class="ia-premium-tariff-period"><?= (int) $iaPromoTopDays ?> дней</p>
                                        <ul class="ia-premium-tariff-features">
                                            <li>Приоритет в каталоге</li>
                                            <li>До 20 фото</li>
                                            <li>3 поднятия в топ</li>
                                        </ul>
                                    </label>
                                    <label class="ia-premium-tariff">
                                        <input type="radio" name="promotion" value="vip">
                                        <p class="ia-premium-tariff-name">VIP</p>
                                        <p class="ia-premium-tariff-price"><?= ia_h($iaPromoVip['price_label']) ?></p>
                                        <p class="ia-premium-tariff-period"><?= (int) $iaPromoVipDays ?> дней</p>
                                        <ul class="ia-premium-tariff-features">
                                            <li>Топ позиция</li>
                                            <li>Безлимит фото</li>
                                            <li>VIP-бейдж</li>
                                        </ul>
                                    </label>
                                </div>
                            </div>
                        </details>

                    </div>
                    <div class="ia-add-form-actions ia-premium-actions">
                        <button type="button" class="ia-premium-btn ia-premium-btn--ghost" id="iaAddDraftBtn">
                            <i class="bi bi-save" aria-hidden="true"></i> Сохранить черновик
                        </button>
                        <div class="ia-publish-wrap" id="iaPublishWrap">
                            <button type="submit" class="ia-premium-btn ia-premium-btn--primary" id="iaAddListingSubmitBtn" disabled>
                                <i class="bi bi-send-fill" aria-hidden="true"></i> Опубликовать объявление
                            </button>
                        </div>
                        <p class="ia-publish-hint small mb-0 d-none" id="iaPublishHint" role="status" aria-live="polite"></p>
                    </div>
                </div>
        </form>
        <div id="iaAddListingSubmitOverlay" class="ia-add-submit-overlay d-none" aria-hidden="true" role="status" aria-live="polite">
            <div class="ia-add-submit-overlay-inner">
                <span class="ia-add-submit-spinner" aria-hidden="true"></span>
                <p class="mb-0">Публикуем объявление…</p>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="iaPhotoCameraModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content ia-photo-camera-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="iaPhotoCameraTitle">Снять фото</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body p-0">
                <video id="iaPhotoCameraVideo" class="ia-photo-camera-video" playsinline autoplay muted></video>
                <p class="small text-danger d-none px-3 py-2 mb-0" id="iaPhotoCameraError"></p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0">
                <button type="button" class="btn btn-primary px-4" id="iaPhotoCameraSnap">Снять</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= ia_h(ia_script_href('assets/add-listing-photo-qa.js', 'assets/add-listing-photo-qa.min.js')) ?>"></script>
<script src="<?= ia_h(ia_script_href('assets/add-listing-photo-camera.js', 'assets/add-listing-photo-camera.min.js')) ?>"></script>
<script src="<?= ia_h(ia_script_href('assets/add-listing-photo-mobile-gallery.js', 'assets/add-listing-photo-mobile-gallery.min.js')) ?>"></script>
<script>
(function(){
  var models = <?= $modelsJson ?>;
  var b = document.getElementById('fldBrand');
  var m = document.getElementById('fldModel');
  function sync() {
    var bid = b.value;
    m.innerHTML = '<option value="">Выберите модель</option>';
    if (!bid || !models[bid]) {
      m.disabled = true;
      return;
    }
    m.disabled = false;
    models[bid].forEach(function(x) {
      var o = document.createElement('option');
      o.value = x.id;
      o.textContent = x.name;
      m.appendChild(o);
    });
  }
  b.addEventListener('change', sync);
  sync();
})();

(function () {
  var avRadios = document.querySelectorAll('input[name="availability"]');
  var wrap = document.getElementById('iaPrepayWrap');
  var inp = document.getElementById('fldPrepay');
  var segment = document.getElementById('iaAvailSegment');
  if (!avRadios.length || !wrap) return;
  function currentAvailability() {
    var checked = document.querySelector('input[name="availability"]:checked');
    return checked ? checked.value : 'in_stock';
  }
  function refresh() {
    var isOrder = currentAvailability() === 'on_order';
    if (isOrder) {
      wrap.classList.remove('d-none');
    } else {
      wrap.classList.add('d-none');
      if (inp) inp.value = '';
    }
    if (segment) {
      segment.classList.toggle('ia-avail-segment--on-order', isOrder);
    }
    if (isOrder && navigator.vibrate) {
      try { navigator.vibrate(10); } catch (e) {}
    }
  }
  avRadios.forEach(function (radio) {
    radio.addEventListener('change', refresh);
  });
  refresh();
})();

(function(){
  var SLOT_COUNT = <?= (int) IA_LISTING_PHOTO_SLOT_COUNT ?>;
  var REQUIRED_COUNT = <?= (int) IA_LISTING_PHOTO_REQUIRED_COUNT ?>;
  var hiddenInput = document.getElementById('fldListingMedia');
  var slotGrid = document.getElementById('iaPhotoSlotGrid');
  var thumbGrid = document.getElementById('iaAddThumbGrid');
  var heroEmpty = document.getElementById('iaPhotoHeroEmpty');
  var heroImage = document.getElementById('iaPhotoHeroImage');
  var primaryIndexInput = document.getElementById('iaPrimaryImageIndex');
  var videoField = document.getElementById('fldListingVideo');
  var videoFormShell = document.getElementById('iaAddVideoFormPreview');
  var videoFormEl = document.getElementById('iaAddPreviewVideoForm');
  var videoSection = document.getElementById('iaAddVideoSection');
  var listingForm = document.querySelector('form.ia-add-listing-layout');
  var accordion = document.getElementById('iaAddAccordion');
  var photoBanner = document.getElementById('iaPhotoSlotBanner');
  var photoSection = document.getElementById('iaAddPhotoSection');
  var photoSectionMeta = document.getElementById('iaPhotoSectionMeta');
  var photoProgress = document.getElementById('iaPhotoProgress');
  var photoProgressLabel = document.getElementById('iaPhotoProgressLabel');
  var photoProgressBar = document.getElementById('iaPhotoProgressBar');
  var photoProgressDone = document.getElementById('iaPhotoProgressDone');
  var photoStepBack = document.getElementById('iaPhotoStepBack');
  var photoStepNext = document.getElementById('iaPhotoStepNext');
  var photoHelpVideoBtn = document.getElementById('iaPhotoHelpVideoBtn');
  var photoChecklist = document.getElementById('iaPhotoChecklist');
  var publishBtn = document.getElementById('iaAddListingSubmitBtn');
  var publishWrap = document.getElementById('iaPublishWrap');
  var publishHint = document.getElementById('iaPublishHint');
  var PHOTO_SECTION_IDX = 2;
  if (!hiddenInput || !slotGrid || !primaryIndexInput) return;
  var slotFiles = new Array(SLOT_COUNT).fill(null);
  var slotPreviewUrls = new Array(SLOT_COUNT).fill('');
  var videoFile = null;
  var sideBlobUrls = [];
  var videoBlobUrl = '';

  function isImageFile(f) {
    if (!f) return false;
    var t = String(f.type || '');
    var n = String(f.name || '');
    if (/^image\//.test(t)) return true;
    if (/\.(jpe?g|png|webp|gif|heic|heif)$/i.test(n)) return true;
    return t === '' && (f.size || 0) > 0;
  }
  function isVideoFile(f) {
    if (!f) return false;
    return /^video\//.test(f.type || '') || /\.(mp4|webm)$/i.test(String(f.name || ''));
  }
  function clearNodes(node) {
    while (node.firstChild) node.removeChild(node.firstChild);
  }
  function revokeSideBlobs() {
    sideBlobUrls.forEach(function(u) { try { URL.revokeObjectURL(u); } catch (e) {} });
    sideBlobUrls = [];
    if (videoBlobUrl) {
      try { URL.revokeObjectURL(videoBlobUrl); } catch (e) {}
      videoBlobUrl = '';
    }
  }
  function revokeSlotUrl(idx) {
    if (slotPreviewUrls[idx]) {
      try { URL.revokeObjectURL(slotPreviewUrls[idx]); } catch (e) {}
      slotPreviewUrls[idx] = '';
    }
  }
  function slotRoot(idx) {
    return slotGrid.querySelector('.ia-photo-slot[data-slot="' + idx + '"]');
  }
  function slotErrorEl(idx) {
    return slotGrid.querySelector('.ia-photo-slot-error[data-slot="' + idx + '"]');
  }
  function slotStatusEl(idx) {
    return slotGrid.querySelector('.ia-photo-slot-status[data-slot="' + idx + '"]');
  }
  function setSlotStatus(idx, status) {
    var statusEl = slotStatusEl(idx);
    var root = slotRoot(idx);
    var labelEl = statusEl ? statusEl.querySelector('.ia-photo-slot-status-label') : null;
    var labels = { pending: 'Ожидает фото', checking: 'Проверяется', accepted: 'Фото принято', error: 'Ошибка' };
    if (statusEl) {
      statusEl.setAttribute('data-status', status);
      if (labelEl) labelEl.textContent = labels[status] || labels.pending;
    }
    if (root) {
      root.classList.toggle('ia-photo-slot--accepted', status === 'accepted');
      root.classList.toggle('ia-photo-slot--error', status === 'error');
      root.classList.toggle('ia-photo-slot--checking', status === 'checking');
      root.classList.toggle('is-filled', status === 'accepted');
      var btn = root.querySelector('.ia-photo-slot-btn');
      if (btn) {
        btn.disabled = status === 'checking';
        btn.classList.toggle('d-none', status === 'accepted');
      }
    }
    refreshPhotoSectionLocks();
    updatePhotoProgress();
    syncPublishButton();
  }
  function requiredAcceptedPhotoCount() {
    var count = 0;
    for (var i = 0; i < REQUIRED_COUNT; i++) {
      var statusEl = slotStatusEl(i);
      if (statusEl && statusEl.getAttribute('data-status') === 'accepted') count++;
    }
    return count;
  }
  function acceptedPhotoCount() {
    var count = 0;
    for (var i = 0; i < SLOT_COUNT; i++) {
      var statusEl = slotStatusEl(i);
      if (statusEl && statusEl.getAttribute('data-status') === 'accepted') count++;
    }
    return count;
  }
  function resetPhotoChecklist() {
    if (!photoChecklist) return;
    photoChecklist.querySelectorAll('.ia-photo-checklist-item').forEach(function (item) {
      item.classList.remove('is-pass', 'is-fail');
      item.classList.add('is-pending');
    });
  }
  function applyPhotoChecklist(checks) {
    if (!photoChecklist || !checks) return;
    Object.keys(checks).forEach(function (key) {
      var row = photoChecklist.querySelector('[data-photo-check="' + key + '"] .ia-photo-checklist-item');
      if (!row) return;
      row.classList.remove('is-pending', 'is-pass', 'is-fail');
      row.classList.add(checks[key] ? 'is-pass' : 'is-fail');
    });
  }
  function markPhotoChecklistComplete() {
    if (!photoChecklist) return;
    photoChecklist.querySelectorAll('.ia-photo-checklist-item').forEach(function (item) {
      item.classList.remove('is-pending', 'is-fail');
      item.classList.add('is-pass');
    });
  }
  function updatePhotoProgress() {
    if (!photoProgressLabel || !photoProgressBar) return;
    var count = requiredAcceptedPhotoCount();
    var ready = count >= REQUIRED_COUNT;
    var extra = acceptedPhotoCount() - count;
    photoProgressLabel.textContent = count + ' из ' + REQUIRED_COUNT + ' обязательных'
      + (extra > 0 ? ' (+' + extra + ' доп.)' : '');
    var pct = REQUIRED_COUNT > 0 ? Math.round((count / REQUIRED_COUNT) * 100) : 0;
    photoProgressBar.style.width = pct + '%';
    photoProgressBar.setAttribute('aria-valuenow', String(count));
    if (photoProgress) {
      photoProgress.setAttribute('aria-valuenow', String(count));
    }
    if (photoProgressDone) {
      photoProgressDone.textContent = ready
        ? 'Обязательные фото загружены' + (acceptedPhotoCount() < SLOT_COUNT ? ' — дополнительные ракурсы по желанию.' : '.')
        : 'Загрузите ' + REQUIRED_COUNT + ' обязательных ракурсов.';
    }
    if (photoStepNext) {
      photoStepNext.disabled = !ready;
    }
    if (ready) {
      markPhotoChecklistComplete();
    }
  }
  function listingGeoReady() {
    if (typeof window.iaAddListingGeoIsReady === 'function') {
      return window.iaAddListingGeoIsReady();
    }
    return false;
  }
  function publishBlockReason() {
    if (!canAdvancePastPhotos()) {
      return 'Загрузите ' + REQUIRED_COUNT + ' обязательных фото автомобиля.';
    }
    if (!listingGeoReady()) {
      return 'Нажмите синюю кнопку геолокации справа от поля «Имя продавца» и разрешите доступ.';
    }
    return '';
  }
  function syncPublishButton() {
    if (!publishBtn) return;
    var ready = canAdvancePastPhotos() && listingGeoReady();
    publishBtn.disabled = !ready;
    publishBtn.setAttribute('aria-disabled', ready ? 'false' : 'true');
    var reason = publishBlockReason();
    if (!ready && reason && publishHint) {
      publishHint.textContent = reason;
      publishHint.classList.remove('d-none');
    } else if (publishHint) {
      publishHint.textContent = '';
      publishHint.classList.add('d-none');
    }
    if (!ready) {
      publishBtn.title = reason;
    } else {
      publishBtn.title = '';
    }
  }
  window.iaAddListingRefreshPublish = syncPublishButton;
  function openAccordionSection(idx) {
    if (!accordion) return;
    var sections = accordion.querySelectorAll('.ia-add-section');
    if (!sections[idx]) return;
    sections.forEach(function (sec, i) {
      sec.open = i === idx;
    });
    sections[idx].scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  function setSlotError(idx, msg) {
    var el = slotErrorEl(idx);
    if (!el) return;
    el.textContent = msg || '';
    el.classList.toggle('d-none', !msg);
    if (msg) {
      setSlotStatus(idx, 'error');
      blockPhotoAdvance(idx);
    }
  }
  function slotInputEl(idx) {
    if (listingForm) {
      var inForm = listingForm.querySelector('.ia-photo-slot-input[data-slot="' + idx + '"]');
      if (inForm) return inForm;
    }
    return slotGrid.querySelector('.ia-photo-slot-input[data-slot="' + idx + '"]');
  }
  function slotHasFile(idx) {
    return !!fileForSlot(idx);
  }
  function photosComplete() {
    for (var i = 0; i < REQUIRED_COUNT; i++) {
      if (!slotHasFile(i)) return false;
      var statusEl = slotStatusEl(i);
      if (statusEl && statusEl.getAttribute('data-status') === 'checking') return false;
    }
    return true;
  }
  function hasPhotoErrors() {
    return !!slotGrid.querySelector('.ia-photo-slot-status[data-status="error"]');
  }
  function canAdvancePastPhotos() {
    return photosComplete() && !hasPhotoErrors();
  }
  function firstMissingPhotoSlot() {
    for (var i = 0; i < REQUIRED_COUNT; i++) {
      if (!slotHasFile(i)) return i;
      var statusEl = slotStatusEl(i);
      if (statusEl && statusEl.getAttribute('data-status') === 'checking') return i;
    }
    return -1;
  }
  function showPhotoBanner(text) {
    if (!photoBanner) return;
    photoBanner.textContent = text;
    photoBanner.classList.remove('d-none');
  }
  function hidePhotoBanner() {
    if (!photoBanner) return;
    photoBanner.textContent = '';
    photoBanner.classList.add('d-none');
  }
  function refreshPhotoSectionLocks() {
    if (!accordion) return;
    var sections = accordion.querySelectorAll('.ia-add-section');
    var locked = !canAdvancePastPhotos();
    sections.forEach(function (sec, idx) {
      if (idx <= PHOTO_SECTION_IDX) return;
      sec.classList.toggle('ia-add-section--photo-locked', locked);
      var summary = sec.querySelector('summary');
      if (summary) {
        if (locked) {
          summary.setAttribute('aria-disabled', 'true');
        } else {
          summary.removeAttribute('aria-disabled');
        }
      }
      if (locked && sec.open) {
        sec.open = false;
      }
    });
    if (canAdvancePastPhotos()) {
      hidePhotoBanner();
    }
    refreshPhotoSectionComplete();
  }
  function refreshPhotoSectionComplete() {
    if (!photoSection) return;
    var done = canAdvancePastPhotos();
    photoSection.classList.toggle('ia-add-section--complete', done);
    if (photoSectionMeta) {
      photoSectionMeta.textContent = done ? 'Готово' : (REQUIRED_COUNT + ' обязательных фото');
    }
  }
  function blockPhotoAdvance(focusSlot) {
    var missing = firstMissingPhotoSlot();
    var slotIdx = typeof focusSlot === 'number' ? focusSlot : missing;
    if (hasPhotoErrors()) {
      showPhotoBanner('Исправьте ошибки в кадрах — к следующему шагу и публикации можно перейти только после успешной проверки.');
    } else if (missing >= 0) {
      showPhotoBanner('Загрузите ' + REQUIRED_COUNT + ' обязательных фото. Осталось: ' + (missing + 1) + '.');
    }
    if (!accordion) return;
    var sections = accordion.querySelectorAll('.ia-add-section');
    var photoSec = sections[PHOTO_SECTION_IDX];
    if (photoSec) {
      photoSec.open = true;
      photoSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    if (slotIdx >= 0) {
      var root = slotRoot(slotIdx);
      if (root) {
        root.classList.add('ia-photo-slot--attention');
        root.scrollIntoView({ behavior: 'smooth', block: 'center' });
        window.setTimeout(function () {
          root.classList.remove('ia-photo-slot--attention');
        }, 1800);
      }
    }
    refreshPhotoSectionLocks();
  }
  function updateSlotUi(idx) {
    var root = slotRoot(idx);
    if (!root) return;
    var preview = root.querySelector('.ia-photo-slot-preview');
    var example = root.querySelector('.ia-photo-slot-example');
    var img = preview ? preview.querySelector('img') : null;
    var file = slotFiles[idx];
    if (example) example.classList.toggle('d-none', !!file);
    if (preview) preview.classList.toggle('d-none', !file);
    if (file && img) {
      revokeSlotUrl(idx);
      slotPreviewUrls[idx] = URL.createObjectURL(file);
      img.src = slotPreviewUrls[idx];
      img.alt = root.querySelector('.ia-photo-slot-label') ? root.querySelector('.ia-photo-slot-label').textContent : '';
    } else if (img) {
      img.removeAttribute('src');
    }
  }
  function inputHasChosenFile(input) {
    return !!(input && input.files && input.files.length > 0 && input.files[0]);
  }
  function assignInputFile(input, file) {
    if (!input || !file) return false;
    if (inputHasChosenFile(input)) return true;
    try {
      var dt = new DataTransfer();
      dt.items.add(file);
      input.files = dt.files;
      return inputHasChosenFile(input);
    } catch (e) {
      return false;
    }
  }
  function syncHiddenInput() {
    var dt = new DataTransfer();
    for (var i = 0; i < SLOT_COUNT; i++) {
      if (slotFiles[i]) dt.items.add(slotFiles[i]);
    }
    if (videoFile) dt.items.add(videoFile);
    try {
      hiddenInput.files = dt.files;
    } catch (e) {}
  }
  function openMediaSectionsForSubmit() {
    if (photoSection) photoSection.open = true;
    if (videoSection && (videoFile || (videoField && inputHasChosenFile(videoField)))) {
      videoSection.open = true;
    }
    if (accordion) {
      accordion.querySelectorAll('details.ia-add-section').forEach(function (sec) {
        if (sec.querySelector('.ia-photo-slot-input, #fldListingVideo')) {
          sec.open = true;
        }
      });
    }
  }
  function stageInputForSubmit(inp, offScreen) {
    inp.setAttribute('name', 'listing_media[]');
    inp.classList.remove('d-none');
    inp.style.cssText = offScreen;
  }
  function prepareListingMediaForSubmit() {
    if (!hiddenInput || !slotGrid || !listingForm) return;
    openMediaSectionsForSubmit();
    var offScreen = 'position:absolute;left:-10000px;top:0;width:1px;height:1px;opacity:0;overflow:hidden;';
    var slots = listingForm.querySelectorAll('.ia-photo-slot-input[data-slot]');
    var slotNamed = false;
    slots.forEach(function (inp) {
      var idx = parseInt(inp.getAttribute('data-slot'), 10);
      if (isNaN(idx)) {
        inp.removeAttribute('name');
        return;
      }
      if (!slotFiles[idx] && !fileForSlot(idx)) {
        inp.removeAttribute('name');
        return;
      }
      if (!inputHasChosenFile(inp)) {
        var pick = fileForSlot(idx);
        if (pick) assignInputFile(inp, pick);
      }
      if (!inputHasChosenFile(inp)) {
        inp.removeAttribute('name');
        return;
      }
      stageInputForSubmit(inp, offScreen);
      slotNamed = true;
    });
    if (videoField) {
      if (videoFile || inputHasChosenFile(videoField)) {
        stageInputForSubmit(videoField, offScreen);
        slotNamed = true;
      } else {
        videoField.removeAttribute('name');
      }
    }
    if (slotNamed) {
      hiddenInput.removeAttribute('name');
      hiddenInput.classList.add('d-none');
      hiddenInput.style.cssText = '';
      return;
    }
    syncHiddenInput();
    if (hiddenInput.files && hiddenInput.files.length > 0) {
      stageInputForSubmit(hiddenInput, offScreen);
    } else {
      hiddenInput.removeAttribute('name');
    }
  }
  function beginPublishUi() {
    showSubmitOverlay();
    if (publishBtn) publishBtn.classList.add('is-loading');
    if (publishHint) publishHint.classList.add('d-none');
  }
  function endPublishUi() {
    hideSubmitOverlay();
  }
  function enableModelForSubmit() {
    var model = document.getElementById('fldModel');
    var brand = document.getElementById('fldBrand');
    if (model && brand && brand.value) {
      model.disabled = false;
    }
  }
  function basicFieldsOk() {
    enableModelForSubmit();
    var brand = document.getElementById('fldBrand');
    var model = document.getElementById('fldModel');
    var price = document.getElementById('fldPrice');
    var seller = document.getElementById('fldSellerName');
    if (!brand || !brand.value) return false;
    if (!model || !model.value) return false;
    if (!price || !String(price.value).trim() || parseFloat(price.value) <= 0) return false;
    if (!seller || !String(seller.value).trim()) return false;
    return true;
  }
  function isTouchMobile() {
    var touch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
    var narrow = !window.matchMedia || window.matchMedia('(max-width: 991px)').matches;
    return touch && narrow;
  }
  function refreshSlotFilesFromInputs() {
    for (var i = 0; i < REQUIRED_COUNT; i++) {
      var inp = slotInputEl(i);
      if (inputHasChosenFile(inp)) {
        slotFiles[i] = inp.files[0];
      }
    }
  }
  function fileForSlot(i) {
    var inp = slotInputEl(i);
    if (inputHasChosenFile(inp)) return inp.files[0];
    return slotFiles[i] || null;
  }
  function countRequiredPhotosForSubmit() {
    var n = 0;
    for (var i = 0; i < REQUIRED_COUNT; i++) {
      if (fileForSlot(i)) n++;
    }
    return n;
  }
  function slotUploadFileName(file, slotIdx) {
    var ext = '';
    var m = String(file.name || '').match(/\.[a-z0-9]+$/i);
    if (m) {
      ext = m[0];
    } else if (String(file.type || '').indexOf('png') >= 0) {
      ext = '.png';
    } else if (String(file.type || '').indexOf('webp') >= 0) {
      ext = '.webp';
    } else if (/heic|heif/i.test(String(file.type || ''))) {
      ext = '.heic';
    } else {
      ext = '.jpg';
    }
    if (slotIdx >= 0) return 'ia-slot-' + (slotIdx + 1) + ext;
    return file.name || ('video' + ext);
  }
  function collectSlotUploadFiles() {
    var out = [];
    for (var i = 0; i < SLOT_COUNT; i++) {
      var file = fileForSlot(i);
      if (file) out.push({ slot: i, file: file });
    }
    if (videoFile) {
      out.push({ slot: -1, file: videoFile });
    } else if (videoField && inputHasChosenFile(videoField)) {
      out.push({ slot: -1, file: videoField.files[0] });
    }
    return out;
  }
  function shouldUseFetchSubmit() {
    if (!isTouchMobile()) return false;
    return countRequiredPhotosForSubmit() > 0;
  }
  function buildListingFormData() {
    if (typeof window.iaAddListingGeoPrepareSubmit === 'function') {
      window.iaAddListingGeoPrepareSubmit();
    }
    enableModelForSubmit();
    openMediaSectionsForSubmit();
    refreshSlotFilesFromInputs();
    var fd = new FormData();
    listingForm.querySelectorAll('input, select, textarea').forEach(function (el) {
      if (!el.name || el.disabled || el.type === 'file') return;
      if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
      fd.append(el.name, el.value);
    });
    collectSlotUploadFiles().forEach(function (row) {
      fd.append('listing_media[]', row.file, slotUploadFileName(row.file, row.slot));
    });
    return fd;
  }
  function listingPostUrl() {
    if (!listingForm) return window.location.href.split('#')[0];
    var direct = listingForm.getAttribute('data-ia-post-url');
    if (direct) return direct;
    return listingForm.getAttribute('action') || listingForm.action || window.location.href.split('#')[0];
  }
  function profilePendingUrl() {
    if (!listingForm) return '';
    return listingForm.getAttribute('data-ia-profile-pending') || '';
  }
  function showPublishSuccessUi(msg) {
    var text = msg || 'Объявление на проверке. После одобрения модератора появится в каталоге.';
    var banner = document.getElementById('iaPublishOkBanner');
    if (banner) {
      banner.innerHTML = '<i class="bi bi-check-circle-fill" aria-hidden="true"></i> ' + text;
      banner.classList.remove('d-none');
      try {
        banner.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } catch (e) {}
    }
    var overlay = document.getElementById('iaAddListingSubmitOverlay');
    if (overlay) {
      if (overlay.parentNode !== document.body) {
        document.body.appendChild(overlay);
      }
      overlay.classList.remove('d-none');
      overlay.setAttribute('aria-hidden', 'false');
      var inner = overlay.querySelector('.ia-add-submit-overlay-inner');
      var spinner = overlay.querySelector('.ia-add-submit-spinner');
      var p = overlay.querySelector('p');
      if (inner) inner.classList.add('ia-add-submit-overlay-inner--success');
      if (spinner) spinner.classList.add('d-none');
      if (p) p.textContent = 'Объявление отправлено на модерацию!';
    }
    if (publishBtn) publishBtn.classList.remove('is-loading');
  }
  function goAfterPublishSuccess(url, msg) {
    showPublishSuccessUi(msg);
    window.setTimeout(function () {
      if (url) window.location.assign(url);
    }, 1300);
  }
  function isPublishSuccessUrl(url) {
    var u = String(url || '');
    return /\/profile(\?|\/|$)/i.test(u) || /\/pay-promotion(\?|\/|$)/i.test(u);
  }
  function resolveRedirectTarget(res, startUrl) {
    if (res.status >= 300 && res.status < 400) {
      var loc = res.headers.get('Location');
      if (loc) {
        try {
          return new URL(loc, window.location.origin).href;
        } catch (e) {
          return loc;
        }
      }
    }
    var finalUrl = (res.url || '').split('#')[0];
    if (finalUrl && finalUrl !== startUrl && isPublishSuccessUrl(finalUrl)) {
      return finalUrl;
    }
    if (isPublishSuccessUrl(finalUrl)) {
      return finalUrl;
    }
    return '';
  }
  function submitListingViaFetch() {
    refreshSlotFilesFromInputs();
    var ready = countRequiredPhotosForSubmit();
    if (ready < REQUIRED_COUNT) {
      endPublishUi();
      if (publishHint) {
        publishHint.textContent = 'К отправке готово только ' + ready + ' из ' + REQUIRED_COUNT + ' фото. Откройте раздел фото и выберите снимки снова.';
        publishHint.classList.remove('d-none');
      }
      blockPhotoAdvance();
      return;
    }
    beginPublishUi();
    var startUrl = window.location.href.split('#')[0];
    var postUrl = listingPostUrl();
    fetch(postUrl, {
      method: 'POST',
      body: buildListingFormData(),
      credentials: 'same-origin',
      redirect: 'manual'
    }).then(function (res) {
      var target = resolveRedirectTarget(res, startUrl);
      if (!target && (res.type === 'opaqueredirect' || res.status === 0)) {
        target = profilePendingUrl();
      }
      if (target && isPublishSuccessUrl(target)) {
        goAfterPublishSuccess(target);
        return;
      }
      if (res.status === 404) {
        endPublishUi();
        if (publishHint) {
          publishHint.textContent = 'Ошибка 404: на сервере нет add-listing.php. Загрузите файлы проекта на Hostinger.';
          publishHint.classList.remove('d-none');
        }
        return;
      }
      if (!res.ok) {
        endPublishUi();
        if (publishHint) {
          publishHint.textContent = 'Ошибка сервера (' + res.status + '). Попробуйте ещё раз.';
          publishHint.classList.remove('d-none');
        }
        return;
      }
      return res.text().then(function (html) {
        if (/<html/i.test(html)) {
          document.open();
          document.write(html);
          document.close();
          return;
        }
        endPublishUi();
      });
    }).catch(function () {
      endPublishUi();
      if (publishHint) {
        publishHint.textContent = 'Не удалось отправить объявление. Проверьте интернет и попробуйте снова.';
        publishHint.classList.remove('d-none');
      }
    });
  }
  function finalizeListingSubmit(e) {
    if (shouldUseFetchSubmit()) {
      if (e) e.preventDefault();
      submitListingViaFetch();
      return true;
    }
    prepareListingMediaForSubmit();
    return false;
  }
  function showSubmitOverlay() {
    var el = document.getElementById('iaAddListingSubmitOverlay');
    if (el) {
      if (el.parentNode !== document.body) {
        document.body.appendChild(el);
      }
      el.classList.remove('d-none');
      el.setAttribute('aria-hidden', 'false');
    }
  }
  function hideSubmitOverlay() {
    var el = document.getElementById('iaAddListingSubmitOverlay');
    if (el) {
      el.classList.add('d-none');
      el.setAttribute('aria-hidden', 'true');
    }
    if (publishBtn) {
      publishBtn.classList.remove('is-loading');
    }
    syncPublishButton();
  }
  function validateCameraPhoto(file, slotIdx, done) {
    var settled = false;
    function finish(err, checks) {
      if (settled) return;
      settled = true;
      clearTimeout(timer);
      if (checks) applyPhotoChecklist(checks);
      done(err);
    }
    var timer = window.setTimeout(function () { finish(null, null); }, 8000);
    if (window.iaListingPhotoQa && typeof window.iaListingPhotoQa.validate === 'function') {
      window.iaListingPhotoQa.validate(file, slotIdx, finish);
      return;
    }
    finish(null, null);
  }
  function renderSidePreview() {
    revokeSideBlobs();
    if (thumbGrid) clearNodes(thumbGrid);
    if (heroImage) {
      heroImage.removeAttribute('src');
      heroImage.classList.add('d-none');
    }
    if (heroEmpty) heroEmpty.classList.remove('d-none');
    if (thumbGrid) thumbGrid.classList.add('d-none');
    if (videoFormEl) videoFormEl.removeAttribute('src');
    if (videoFormShell) videoFormShell.classList.add('d-none');
    primaryIndexInput.value = '0';

    var filled = 0;
    var heroIdx = -1;
    for (var i = 0; i < SLOT_COUNT; i++) {
      if (!slotFiles[i]) continue;
      filled++;
      if (heroIdx < 0) heroIdx = i;
    }
    if (filled && heroIdx >= 0 && heroImage) {
      var heroRoot = slotRoot(heroIdx);
      var heroCaption = heroRoot && heroRoot.querySelector('.ia-photo-slot-label')
        ? heroRoot.querySelector('.ia-photo-slot-label').textContent
        : ('Ракурс ' + (heroIdx + 1));
      var heroUrl = URL.createObjectURL(slotFiles[heroIdx]);
      sideBlobUrls.push(heroUrl);
      heroImage.src = heroUrl;
      heroImage.alt = heroCaption;
      heroImage.classList.remove('d-none');
      if (heroEmpty) heroEmpty.classList.add('d-none');
    }
    if (filled && thumbGrid) {
      thumbGrid.classList.remove('d-none');
      for (var s = 0; s < SLOT_COUNT; s++) {
        if (!slotFiles[s]) continue;
        var root = slotRoot(s);
        var caption = root && root.querySelector('.ia-photo-slot-label')
          ? root.querySelector('.ia-photo-slot-label').textContent
          : ('Ракурс ' + (s + 1));
        var u = URL.createObjectURL(slotFiles[s]);
        sideBlobUrls.push(u);
        var cell = document.createElement('button');
        cell.type = 'button';
        cell.className = 'ia-photo-thumb-tile';
        cell.setAttribute('role', 'listitem');
        cell.setAttribute('data-slot', String(s));
        cell.setAttribute('aria-label', (s + 1) + '. ' + caption);
        var im = document.createElement('img');
        im.src = u;
        im.alt = '';
        cell.appendChild(im);
        thumbGrid.appendChild(cell);
      }
    }
    if (videoFile && videoFormEl) {
      videoBlobUrl = URL.createObjectURL(videoFile);
      videoFormEl.src = videoBlobUrl;
      if (videoFormShell) videoFormShell.classList.remove('d-none');
    }
  }
  function clearSlot(idx) {
    revokeSlotUrl(idx);
    slotFiles[idx] = null;
    var errEl = slotErrorEl(idx);
    if (errEl) {
      errEl.textContent = '';
      errEl.classList.add('d-none');
    }
    setSlotStatus(idx, 'pending');
    var input = slotInputEl(idx);
    if (input) input.value = '';
    updateSlotUi(idx);
    syncHiddenInput();
    renderSidePreview();
    refreshPhotoSectionLocks();
  }
  function assignSlotFile(idx, file) {
    resetPhotoChecklist();
    setSlotStatus(idx, 'checking');
    var errEl = slotErrorEl(idx);
    if (errEl) {
      errEl.textContent = '';
      errEl.classList.add('d-none');
    }
    validateCameraPhoto(file, idx, function(err) {
      if (err) {
        slotFiles[idx] = null;
        updateSlotUi(idx);
        setSlotError(idx, err);
        var input = slotInputEl(idx);
        if (input) input.value = '';
        syncHiddenInput();
        renderSidePreview();
        return;
      }
      slotFiles[idx] = file;
      setSlotStatus(idx, 'accepted');
      var input = slotInputEl(idx);
      if (input && !inputHasChosenFile(input)) assignInputFile(input, file);
      updateSlotUi(idx);
      syncHiddenInput();
      renderSidePreview();
      refreshPhotoSectionLocks();
    });
  }
  slotGrid.querySelectorAll('.ia-photo-slot-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var idx = parseInt(btn.getAttribute('data-slot'), 10);
      var input = slotInputEl(idx);
      if (input) input.click();
    });
  });
  slotGrid.querySelectorAll('.ia-photo-slot-input').forEach(function(input) {
    input.addEventListener('change', function() {
      var idx = parseInt(input.getAttribute('data-slot'), 10);
      var file = input.files && input.files[0] ? input.files[0] : null;
      if (!file) return;
      assignSlotFile(idx, file);
    });
  });
  slotGrid.querySelectorAll('.ia-photo-slot-remove').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var idx = parseInt(btn.getAttribute('data-slot'), 10);
      clearSlot(idx);
    });
  });
  if (thumbGrid) {
    thumbGrid.addEventListener('click', function (e) {
      var tile = e.target.closest('.ia-photo-thumb-tile');
      if (!tile || !heroImage) return;
      var img = tile.querySelector('img');
      if (!img || !img.src) return;
      heroImage.src = img.src;
      heroImage.alt = tile.getAttribute('aria-label') || '';
      heroImage.classList.remove('d-none');
      if (heroEmpty) heroEmpty.classList.add('d-none');
    });
  }
  if (photoStepBack) {
    photoStepBack.addEventListener('click', function () {
      openAccordionSection(PHOTO_SECTION_IDX - 1);
    });
  }
  if (photoStepNext) {
    photoStepNext.addEventListener('click', function () {
      if (!canAdvancePastPhotos()) {
        blockPhotoAdvance();
        return;
      }
      openAccordionSection(PHOTO_SECTION_IDX + 1);
    });
  }
  if (photoHelpVideoBtn) {
    photoHelpVideoBtn.addEventListener('click', function () {
      if (!canAdvancePastPhotos()) {
        blockPhotoAdvance();
        return;
      }
      if (videoSection) {
        videoSection.open = true;
        videoSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
      }
      openAccordionSection(PHOTO_SECTION_IDX + 1);
    });
  }
  if (videoField) {
    videoField.addEventListener('change', function() {
      var file = videoField.files && videoField.files[0] ? videoField.files[0] : null;
      if (file && !isVideoFile(file)) {
        videoField.value = '';
        videoFile = null;
      } else {
        videoFile = file;
      }
      syncHiddenInput();
      renderSidePreview();
    });
  }
  if (publishBtn) {
    publishBtn.addEventListener('click', function () {
      if (publishBtn.disabled) return;
      beginPublishUi();
    }, true);
  }
  if (listingForm) {
    listingForm.addEventListener('submit', function(e) {
      beginPublishUi();
      if (typeof window.iaAddListingGeoPrepareSubmit === 'function') {
        window.iaAddListingGeoPrepareSubmit();
      }
      enableModelForSubmit();
      var reason = publishBlockReason();
      if (reason) {
        e.preventDefault();
        endPublishUi();
        if (!canAdvancePastPhotos()) {
          blockPhotoAdvance();
        } else {
          openAccordionSection(0);
        }
        if (publishHint) {
          publishHint.textContent = reason;
          publishHint.classList.remove('d-none');
        }
        return;
      }
      if (!basicFieldsOk()) {
        e.preventDefault();
        endPublishUi();
        openAccordionSection(0);
        if (publishHint) {
          publishHint.textContent = 'Заполните марку, модель, цену и имя продавца.';
          publishHint.classList.remove('d-none');
        }
        return;
      }
      if (typeof window.iaAddListingGeoIsReady === 'function' && window.iaAddListingGeoIsReady()) {
        if (finalizeListingSubmit(e)) return;
        return;
      }
      e.preventDefault();
      if (typeof window.iaAddListingEnsureGeoForSubmit !== 'function') {
        endPublishUi();
        return;
      }
      window.iaAddListingEnsureGeoForSubmit(function (geoOk) {
        if (!geoOk) {
          endPublishUi();
          openAccordionSection(0);
          if (publishHint) {
            publishHint.textContent = 'Нажмите синюю кнопку геолокации справа от поля «Имя продавца» и разрешите доступ.';
            publishHint.classList.remove('d-none');
          }
          return;
        }
        if (shouldUseFetchSubmit()) {
          submitListingViaFetch();
          return;
        }
        prepareListingMediaForSubmit();
        beginPublishUi();
        if (typeof listingForm.requestSubmit === 'function') {
          listingForm.requestSubmit();
        }
      });
    });
  }
  if (publishWrap) {
    publishWrap.addEventListener('click', function (e) {
      if (!publishBtn || !publishBtn.disabled) return;
      e.preventDefault();
      var reason = publishBlockReason();
      if (!reason) return;
      if (!canAdvancePastPhotos()) {
        blockPhotoAdvance();
      } else {
        openAccordionSection(0);
      }
      if (publishHint) {
        publishHint.textContent = reason;
        publishHint.classList.remove('d-none');
      }
    });
  }
  if (accordion) {
    var sections = accordion.querySelectorAll('.ia-add-section');
    sections.forEach(function (sec, idx) {
      if (idx <= PHOTO_SECTION_IDX) return;
      var summary = sec.querySelector('summary');
      if (summary) {
        summary.addEventListener('click', function (e) {
          if (canAdvancePastPhotos()) return;
          e.preventDefault();
          blockPhotoAdvance();
        });
      }
      sec.addEventListener('toggle', function () {
        if (!sec.open || canAdvancePastPhotos()) return;
        sec.open = false;
        blockPhotoAdvance();
      });
    });
    refreshPhotoSectionLocks();
  }
  for (var init = 0; init < SLOT_COUNT; init++) {
    setSlotStatus(init, 'pending');
    updateSlotUi(init);
  }
  renderSidePreview();
  updatePhotoProgress();
  syncPublishButton();
  refreshPhotoSectionComplete();
  window.addEventListener('pageshow', function () {
    hideSubmitOverlay();
  });
  var pubErrAlert = document.querySelector('.ia-premium-alert.alert-danger');
  if (pubErrAlert) {
    pubErrAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
  if (window.iaListingPhotoQa && typeof window.iaListingPhotoQa.ensureOpenCv === 'function') {
    window.iaListingPhotoQa.ensureOpenCv(function() {});
  }
  window.iaAddListingPrepareMedia = prepareListingMediaForSubmit;
  window.iaAddListingBeginPublish = beginPublishUi;
  window.iaAddListingEndPublish = endPublishUi;
})();

(function () {
  var btn = document.getElementById('iaListingGeoBtn');
  var seller = document.getElementById('fldSellerName');
  var cityEl = document.getElementById('fldCity');
  var latEl = document.getElementById('iaListingGeoLat');
  var lngEl = document.getElementById('iaListingGeoLng');
  var tsEl = document.getElementById('iaListingGeoTs');
  var accEl = document.getElementById('iaListingGeoAcc');
  var placeEl = document.getElementById('iaListingGeoPlace');
  var payloadEl = document.getElementById('iaListingGeoPayload');
  var banner = document.getElementById('iaListingGeoBanner');
  var accordion = document.getElementById('iaAddAccordion');
  var geoReverseUrl = <?= json_encode(ia_public_url('geo-reverse.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  if (!btn || !latEl || !lngEl || !tsEl || !accEl || !banner) return;
  var GEO_STORAGE_KEY = 'ia_add_listing_geo_v1';
  var MSG = {
    needPerm: 'Разрешите доступ к местоположению в браузере.',
    denied: 'Геолокация отключена. Разрешите её в настройках браузера и нажмите значок снова.',
    noApi: 'Геолокация не поддерживается браузером.',
    locating: 'Определяем местоположение…',
    resolving: 'Определяем адрес…',
    stepBlocked: 'Сначала укажите имя продавца и зафиксируйте местоположение.',
    submitBlocked: 'Укажите местоположение перед публикацией.',
    captured: 'Местоположение зафиксировано.',
    err: { 1: 'Доступ к геолокации запрещён.', 2: 'Местоположение временно недоступно.', 3: 'Превышено время ожидания.' }
  };

  var permState = null;
  var capturing = false;
  var autoCaptureRequested = false;

  function geoFilled() {
    if (latEl.value === '' || lngEl.value === '') return false;
    if (tsEl.value === '') tsEl.value = new Date().toISOString();
    return true;
  }
  function syncGeoPayload() {
    if (!payloadEl) return;
    if (!latEl.value || !lngEl.value) {
      payloadEl.value = '';
      return;
    }
    var bundle = {
      lat: latEl.value,
      lng: lngEl.value,
      ts: tsEl.value || new Date().toISOString(),
      acc: accEl.value || '',
      place: placeEl ? placeEl.value : ''
    };
    try {
      payloadEl.value = btoa(unescape(encodeURIComponent(JSON.stringify(bundle))));
    } catch (e) {
      payloadEl.value = JSON.stringify(bundle);
    }
  }
  function persistGeo() {
    if (!geoFilled()) return;
    try {
      sessionStorage.setItem(GEO_STORAGE_KEY, JSON.stringify({
        lat: latEl.value,
        lng: lngEl.value,
        ts: tsEl.value,
        acc: accEl.value,
        place: placeEl ? placeEl.value : ''
      }));
    } catch (e) {}
    syncGeoPayload();
  }
  function restoreGeo() {
    if (geoFilled()) return;
    try {
      var raw = sessionStorage.getItem(GEO_STORAGE_KEY);
      if (!raw) return;
      var data = JSON.parse(raw);
      if (!data || !data.lat || !data.lng) return;
      latEl.value = String(data.lat);
      lngEl.value = String(data.lng);
      tsEl.value = data.ts ? String(data.ts) : new Date().toISOString();
      if (accEl && data.acc != null) accEl.value = String(data.acc);
      if (placeEl && data.place) placeEl.value = String(data.place);
      btn.classList.add('is-captured');
      btn.classList.remove('ia-listing-geo-btn--needs-action');
      syncGeoPayload();
    } catch (e) {}
  }
  function refreshGeoTimestamp() {
    if (!latEl.value || !lngEl.value) return;
    tsEl.value = new Date().toISOString();
    persistGeo();
  }
  window.iaAddListingGeoIsReady = function () {
    restoreGeo();
    return geoFilled();
  };
  window.iaAddListingGeoPrepareSubmit = function () {
    restoreGeo();
    refreshGeoTimestamp();
    syncGeoPayload();
    latEl.removeAttribute('disabled');
    lngEl.removeAttribute('disabled');
    tsEl.removeAttribute('disabled');
    if (accEl) accEl.removeAttribute('disabled');
    if (placeEl) placeEl.removeAttribute('disabled');
    if (payloadEl) payloadEl.removeAttribute('disabled');
    return geoFilled();
  };

  function applyGeoAddress(lat, lng) {
    if (!geoReverseUrl) {
      return;
    }
    showBanner('info', MSG.resolving);
    fetch(geoReverseUrl + '?lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
      .then(function (res) {
        return res.json().catch(function () { return null; });
      })
      .then(function (data) {
        if (!data || !data.ok) {
          hideBanner();
          return;
        }
        if (cityEl && data.city) {
          cityEl.value = String(data.city);
        }
        if (placeEl && data.place) {
          placeEl.value = String(data.place);
        }
        if (data.place) {
          showBanner('info', 'Местоположение: ' + String(data.place) + (data.city ? ', ' + String(data.city) : ''));
          window.setTimeout(hideBanner, 4000);
        } else if (data.city) {
          showBanner('info', 'Город: ' + String(data.city));
          window.setTimeout(hideBanner, 3500);
        } else {
          hideBanner();
        }
      })
      .catch(function () {
        hideBanner();
      });
  }

  function hideBanner() {
    banner.classList.add('d-none');
    banner.textContent = '';
    banner.className = 'ia-listing-geo-banner d-none';
  }

  function showBanner(variant, text) {
    banner.className = 'ia-listing-geo-banner ia-listing-geo-banner--' + (variant || 'info');
    banner.textContent = text;
    banner.classList.remove('d-none');
  }

  function syncGeoButtonAttention() {
    var need = !geoFilled() && seller && String(seller.value || '').trim().length > 0;
    btn.classList.toggle('ia-listing-geo-btn--needs-action', need);
  }

  function blockGeoAdvance() {
    showBanner('warn', MSG.stepBlocked);
    maybeShowSellerPermissionHelp();
    if (!accordion) return;
    var sections = accordion.querySelectorAll('.ia-add-section');
    var first = sections[0];
    if (first) {
      first.open = true;
      first.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    refreshGeoSectionLocks();
  }

  function refreshGeoSectionLocks() {
    if (!accordion) return;
    var sections = accordion.querySelectorAll('.ia-add-section');
    var locked = !geoFilled();
    sections.forEach(function (sec, idx) {
      var isLocked = locked && idx > 0;
      sec.classList.toggle('ia-add-section--geo-locked', isLocked);
      var summary = sec.querySelector('summary');
      if (summary) {
        if (isLocked) {
          summary.setAttribute('aria-disabled', 'true');
        } else {
          summary.removeAttribute('aria-disabled');
        }
      }
      if (isLocked && sec.open) {
        sec.open = false;
      }
    });
    if (locked && sections[0]) {
      sections[0].open = true;
    }
  }

  function maybeShowSellerPermissionHelp() {
    if (geoFilled()) {
      hideBanner();
      syncGeoButtonAttention();
      return;
    }
    if (permState === 'denied') {
      showBanner('danger', MSG.denied);
    } else {
      showBanner('info', MSG.needPerm);
    }
    syncGeoButtonAttention();
  }

  function watchPermission() {
    if (!navigator.permissions || !navigator.permissions.query) return;
    try {
      navigator.permissions.query({ name: 'geolocation' }).then(function (result) {
        permState = result.state;
        if (permState === 'denied' && !geoFilled()) {
          showBanner('danger', MSG.denied);
        }
        result.onchange = function () {
          permState = result.state;
          if (permState === 'denied' && !geoFilled()) {
            showBanner('danger', MSG.denied);
          } else if (!geoFilled() && seller && document.activeElement === seller) {
            maybeShowSellerPermissionHelp();
          } else if (geoFilled()) {
            hideBanner();
          }
        };
      }).catch(function () {});
    } catch (e) {}
  }

  function captureGeo(options) {
    options = options || {};
    if (capturing) return;
    if (geoFilled()) {
      if (typeof options.onDone === 'function') options.onDone(true);
      return;
    }
    if (!navigator.geolocation) {
      showBanner('warn', MSG.noApi);
      if (typeof options.onDone === 'function') options.onDone(false);
      return;
    }
    capturing = true;
    showBanner('info', MSG.locating);
    btn.disabled = true;
    btn.classList.add('is-locating');
    navigator.geolocation.getCurrentPosition(
      function (pos) {
        capturing = false;
        var lat = pos.coords.latitude;
        var lng = pos.coords.longitude;
        if (typeof lat !== 'number' || typeof lng !== 'number' || isNaN(lat) || isNaN(lng)) {
          showBanner('warn', 'Не удалось получить координаты. Попробуйте ещё раз.');
          btn.disabled = false;
          btn.classList.remove('is-locating');
          if (typeof options.onDone === 'function') options.onDone(false);
          return;
        }
        latEl.value = String(lat);
        lngEl.value = String(lng);
        tsEl.value = new Date().toISOString();
        accEl.value = pos.coords.accuracy != null ? String(pos.coords.accuracy) : '';
        btn.classList.remove('ia-listing-geo-btn--needs-action', 'is-locating');
        btn.classList.add('is-captured');
        btn.disabled = false;
        btn.title = MSG.captured;
        persistGeo();
        refreshGeoSectionLocks();
        applyGeoAddress(lat, lng);
        if (typeof window.iaAddListingRefreshPublish === 'function') {
          window.iaAddListingRefreshPublish();
        }
        if (typeof options.onDone === 'function') options.onDone(true);
      },
      function (err) {
        capturing = false;
        btn.classList.remove('is-locating');
        if (err.code === 1) {
          permState = 'denied';
          showBanner('danger', MSG.denied);
        } else {
          showBanner('warn', MSG.err[err.code] || 'Повторите попытку.');
        }
        btn.disabled = false;
        refreshGeoSectionLocks();
        if (typeof options.onDone === 'function') options.onDone(false);
      },
      { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
    );
  }

  function maybeAutoCaptureGeo() {
    if (!seller || geoFilled() || capturing || permState === 'denied' || autoCaptureRequested) return;
    if (String(seller.value || '').trim().length < 1) return;
    autoCaptureRequested = true;
    captureGeo({ auto: true });
  }

  if (seller) {
    seller.addEventListener('focus', function () {
      if (geoFilled()) return;
      maybeShowSellerPermissionHelp();
    });
    seller.addEventListener('input', function () {
      if (geoFilled()) {
        hideBanner();
        syncGeoButtonAttention();
        return;
      }
      maybeShowSellerPermissionHelp();
      maybeAutoCaptureGeo();
    });
  }

  btn.addEventListener('click', function () {
    autoCaptureRequested = false;
    captureGeo({ manual: true });
  });

  watchPermission();

  if (accordion) {
    var sections = accordion.querySelectorAll('.ia-add-section');
    sections.forEach(function (sec, idx) {
      if (idx === 0) return;
      var summary = sec.querySelector('summary');
      if (summary) {
        summary.addEventListener('click', function (e) {
          if (geoFilled()) return;
          e.preventDefault();
          blockGeoAdvance();
        });
      }
      sec.addEventListener('toggle', function () {
        if (!sec.open || geoFilled()) return;
        sec.open = false;
        blockGeoAdvance();
      });
    });
    refreshGeoSectionLocks();
  }

  window.iaAddListingEnsureGeoForSubmit = function (callback) {
    if (typeof callback !== 'function') return;
    if (geoFilled()) {
      callback(true);
      return;
    }
    if (seller && String(seller.value || '').trim() === '') {
      showBanner('warn', 'Сначала введите имя продавца.');
      callback(false);
      return;
    }
    showBanner('info', MSG.locating);
    captureGeo({ auto: true, onDone: function (ok) {
      if (ok && geoFilled()) {
        if (typeof window.iaAddListingGeoPrepareSubmit === 'function') {
          window.iaAddListingGeoPrepareSubmit();
        }
        showBanner('info', MSG.captured);
        callback(true);
      } else {
        showBanner('warn', MSG.submitBlocked);
        maybeShowSellerPermissionHelp();
        callback(false);
      }
    }});
  };

  syncGeoButtonAttention();
  restoreGeo();
  refreshGeoSectionLocks();
  if (typeof window.iaAddListingRefreshPublish === 'function') {
    window.iaAddListingRefreshPublish();
  }
})();

(function () {
  var accordion = document.getElementById('iaAddAccordion');
  var stepCur = document.getElementById('iaAddStepCur');
  var stepPct = document.getElementById('iaAddStepPct');
  var stepWrap = document.querySelector('.ia-add-steps-bar');
  var progressSegs = document.querySelectorAll('#iaAddProgressSegs .ia-premium-progress-seg');
  if (!accordion) return;

  function pulseStepNum(sec) {
    var num = sec ? sec.querySelector('.ia-add-section-num') : null;
    if (!num) return;
    num.classList.remove('is-step-pop');
    void num.offsetWidth;
    num.classList.add('is-step-pop');
    num.addEventListener('animationend', function onEnd(e) {
      if (e.animationName !== 'iaStepPop') return;
      num.classList.remove('is-step-pop');
      num.removeEventListener('animationend', onEnd);
    });
  }

  function revealSectionBody(sec) {
    var body = sec ? sec.querySelector('.ia-add-section-body') : null;
    if (!body) return;
    body.classList.remove('is-revealing');
    void body.offsetWidth;
    body.classList.add('is-revealing');
  }

  function updateAddStepProgress() {
    var sections = accordion.querySelectorAll('.ia-add-section');
    var step = 1;
    for (var i = 0; i < sections.length; i++) {
      if (sections[i].open) {
        step = i + 1;
        break;
      }
    }
    var pct = Math.max(17, Math.round((step / sections.length) * 100));
    if (stepCur) stepCur.textContent = String(step);
    if (stepPct) stepPct.textContent = pct + '%';
    if (stepWrap) stepWrap.setAttribute('aria-valuenow', String(step));
    progressSegs.forEach(function (seg, i) {
      seg.classList.remove('is-active', 'is-done');
      if (i + 1 < step) seg.classList.add('is-done');
      else if (i + 1 === step) seg.classList.add('is-active');
    });
    sections.forEach(function (sec, i) {
      sec.classList.remove('is-step-current', 'is-step-done');
      if (i + 1 < step) sec.classList.add('is-step-done');
      else if (i + 1 === step) sec.classList.add('is-step-current');
    });
  }

  accordion.querySelectorAll('.ia-add-section').forEach(function (sec) {
    sec.addEventListener('toggle', function () {
      if (sec.open) {
        accordion.querySelectorAll('.ia-add-section').forEach(function (other) {
          if (other !== sec) other.open = false;
        });
        pulseStepNum(sec);
        revealSectionBody(sec);
      }
      updateAddStepProgress();
    });
  });

  updateAddStepProgress();

  var firstOpen = accordion.querySelector('.ia-add-section[open]');
  if (firstOpen) {
    revealSectionBody(firstOpen);
    pulseStepNum(firstOpen);
  }

  (function () {
    var addForm = document.getElementById('iaAddListingForm');
    var paidPromo = addForm && addForm.getAttribute('data-ia-paid-promo') === '1';
    var pubBtn = document.getElementById('iaAddListingSubmitBtn');
    if (!paidPromo || !pubBtn) return;
    function syncPromoBtn() {
      var sel = document.querySelector('input[name="promotion"]:checked');
      var v = sel ? sel.value : 'normal';
      if (v === 'vip' || v === 'top') {
        pubBtn.innerHTML = '<i class="bi bi-credit-card" aria-hidden="true"></i> Продолжить к оплате';
      } else {
        pubBtn.innerHTML = '<i class="bi bi-send-fill" aria-hidden="true"></i> Опубликовать объявление';
      }
    }
    document.querySelectorAll('input[name="promotion"]').forEach(function (r) {
      r.addEventListener('change', syncPromoBtn);
    });
    syncPromoBtn();
  })();

  var draftBtn = document.getElementById('iaAddDraftBtn');
  if (draftBtn) {
    draftBtn.addEventListener('click', function () {
      try {
        sessionStorage.setItem('ia_add_listing_draft_saved', String(Date.now()));
      } catch (e) {}
      draftBtn.disabled = true;
      var label = draftBtn.innerHTML;
      draftBtn.innerHTML = '<i class="bi bi-check2" aria-hidden="true"></i> Черновик сохранён';
      setTimeout(function () {
        draftBtn.disabled = false;
        draftBtn.innerHTML = label;
      }, 2200);
    });
  }
  })();
</script>

<?php require IA_ROOT . '/includes/partials/site-footer.php'; ?>
