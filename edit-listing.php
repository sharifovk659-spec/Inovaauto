<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/public_queries.php';
require_once IA_ROOT . '/includes/listing_uploads.php';
require_once IA_ROOT . '/includes/listing_media.php';
require_once IA_ROOT . '/includes/listing_edit_slots.php';

ia_platform_require_login();

$pdo = ia_db();
$uid = (int) ia_platform_current_user()['id'];
$lid = ia_request_int('id');
if ($lid <= 0) {
    $lid = ia_post_int('listing_id');
}

$listing = $lid > 0 ? ia_pub_listing_owned_by($pdo, $lid, $uid) : null;
if ($listing === null) {
    http_response_code(404);
    $pageTitle = 'Не найдено';
    require IA_ROOT . '/includes/partials/site-header.php';
    echo '<section class="py-5 ia-page-section"><div class="container ia-container"><p class="text-secondary">Объявление не найдено или не принадлежит вам.</p><a href="' . ia_h(ia_public_url('profile.php')) . '">Профиль</a></div></section>';
    require IA_ROOT . '/includes/partials/site-footer.php';
    exit;
}

$brands = ia_pub_brands_ordered($pdo);
$modelsJson = json_encode(ia_pub_models_grouped_json($pdo), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$res = ia_pub_resolve_brand_model_ids($pdo, (string) ($listing['brand'] ?? ''), (string) ($listing['model'] ?? ''));
$selBrandId = $res['brand_id'];
$selModelId = $res['model_id'];
$iaPromoStatus = ia_promotion_status($pdo);
$iaPromoTop = ia_promotion_resolve($pdo, 'top');
$iaPromoVip = ia_promotion_resolve($pdo, 'vip');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('pub_error', 'Сессия устарела.');
    } else {
        $bid = ia_post_int('brand_id');
        $mid = ia_post_int('model_id');
        $price = ia_input_decimal($_POST['price'] ?? '0', 0) ?? 0.0;
        $desc = ia_input_long_text($_POST['description'] ?? '', 8000);
        $seller = ia_post_text('seller_name', 120);
        $promotion = ia_input_enum($_POST['promotion'] ?? 'normal', ['normal', 'top', 'vip'], 'normal');
        $promoFlags = ia_promotion_apply_to_flags($promotion);
        $isVip = $promoFlags['is_vip'];
        $isTop = $promoFlags['is_top'];
        $stb = $pdo->prepare('SELECT name FROM car_brands WHERE id = ?');
        $stb->execute([$bid]);
        $bname = (string) $stb->fetchColumn();
        $stm = $pdo->prepare('SELECT name FROM car_models WHERE id = ? AND brand_id = ?');
        $stm->execute([$mid, $bid]);
        $mname = (string) $stm->fetchColumn();
        if ($bname === '' || $mname === '' || $price <= 0 || $seller === '') {
            ia_flash('pub_error', 'Выберите бренд и модель, укажите цену и имя продавца.');
        } else {
            $prevPromo = ((int) ($listing['is_vip'] ?? 0) === 1) ? 'vip' : (((int) ($listing['is_top'] ?? 0) === 1) ? 'top' : 'normal');
            if (($promoErr = ia_promotion_validate_listing_choice($pdo, $promotion, $prevPromo)) !== null) {
                ia_flash('pub_error', $promoErr);
            } else {
            $promoChanged = strtolower($promotion) !== $prevPromo;
            $needsPromoPayment = $promoChanged && ia_promotion_payment_required_for_choice($pdo, $promotion);
            $saveVip = $needsPromoPayment ? (int) ($listing['is_vip'] ?? 0) : $isVip;
            $saveTop = $needsPromoPayment ? (int) ($listing['is_top'] ?? 0) : $isTop;
            $uploadReject = null;
            $newBySlot = ia_listing_collect_slot_uploads($uploadReject);
            if ($uploadReject !== null && $uploadReject !== '') {
                ia_listing_rollback_saved_uploads(array_values($newBySlot));
                ia_flash('pub_error', $uploadReject);
            } else {
                $mediaApplyErr = null;
                if (!ia_listing_edit_apply_slot_photos($pdo, $lid, $uid, $newBySlot, $mediaApplyErr)) {
                    ia_listing_rollback_saved_uploads(array_values($newBySlot));
                    ia_flash('pub_error', $mediaApplyErr ?? 'Не удалось сохранить фото.');
                } else {
                $availability = ia_listing_availability_normalize($_POST['availability'] ?? null);
                $y = ia_post_int('model_year');
                $modelYear = ($y >= 1950 && $y <= 2100) ? $y : null;
                $mileageKm = ia_post_int('mileage_km', -1, 0);
                $mileageKm = $mileageKm >= 0 ? $mileageKm : null;
                require_once IA_ROOT . '/includes/tj_cities.php';
                $city = ia_tj_city_normalize(ia_post_text('city', 80));
                if (mb_strlen($city) > 120) {
                    $city = mb_substr($city, 0, 120);
                }
                $vinRaw = ia_input_vin($_POST['vin'] ?? '');
                $vin = $vinRaw === '' ? null : $vinRaw;
                ia_require_home_quick_categories();
                $bodyAllowed = array_merge(
                    [''],
                    array_keys(ia_listing_form_body_type_options()),
                    ['wagon', 'coupe', 'cabrio', 'bus', 'other']
                );
                $bodyAllowed = array_values(array_unique($bodyAllowed));
                $bodyType = ia_input_enum($_POST['body_type'] ?? '', $bodyAllowed);
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
                try {
                    $pdo->prepare(
                        'UPDATE ad_listings SET brand = ?, model = ?, vin = ?, description = ?, model_year = ?, mileage_km = ?, city = ?, body_type = ?, fuel_type = ?, transmission = ?, price = ?, seller_name = ?, availability = ?, is_vip = ?, is_top = ?,
                            color = ?, drive_type = ?, engine_volume = ?, has_turbo = ?, condition_state = ?, customs_cleared = ?, taxi_license = ?, prepayment_amount = ?, currency = ?
                         WHERE id = ? AND user_id = ?'
                    )->execute([
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
                        $lid,
                        $uid,
                    ]);
                    require_once IA_ROOT . '/includes/ia_cache.php';
                    ia_cache_forget('pub_body_type_counts');
                    require_once IA_ROOT . '/includes/listing_lifecycle.php';
                    ia_listing_touch_engagement($pdo, $lid);
                    if ($needsPromoPayment) {
                        ia_promotion_create_listing_payment($pdo, $uid, $lid, $promotion);
                        ia_flash('pub_ok', 'Оплатите тариф VIP/TOP — после оплаты объявление уйдёт на модерацию.');
                        ia_redirect(ia_public_url('pay-promotion.php?listing_id=' . $lid));
                    }
                    ia_flash('pub_ok', 'Изменения сохранены.');
                    ia_redirect(ia_public_url('car.php?id=' . $lid));
                } catch (\Throwable $e) {
                    ia_flash('pub_error', 'Не удалось сохранить изменения.');
                    ia_redirect(ia_public_url('edit-listing.php?id=' . $lid));
                }
                }
            }
            }
        }
    }
    ia_redirect(ia_public_url('edit-listing.php?id=' . $lid));
}

$pageTitle = 'Редактировать объявление';
$iaBodyExtraClass = 'ia-page-edit-listing ia-edit-premium';
$slotPrefill = ia_listing_edit_slot_prefill($pdo, $lid);
$statusRu = ia_pub_listing_status_ru((string) ($listing['status'] ?? ''));

require IA_ROOT . '/includes/partials/site-header.php';
?>

<section class="py-3 py-md-4 ia-page-section ia-edit-listing-page-section">
    <div class="container ia-container ia-edit-listing-wrap">
        <header class="ia-edit-premium-head mb-3">
            <a class="ia-edit-premium-back" href="<?= ia_h(ia_public_url('profile.php')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Кабинет</a>
            <h1 class="ia-edit-premium-title">Редактировать объявление</h1>
            <p class="ia-edit-premium-meta">ID <?= (int) $listing['id'] ?> · <?= ia_h($statusRu) ?></p>
        </header>
        <?php if ($msg = ia_flash('pub_error')): ?><div class="alert alert-danger ia-edit-premium-alert mb-3"><?= ia_h((string) $msg) ?></div><?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="ia-edit-premium-form">
            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
            <div class="ia-edit-premium-card ia-form-surface">
            <div class="ia-edit-accordion">
            <details class="ia-edit-section" open>
                <summary class="ia-edit-section-head">
                    <span class="ia-edit-section-icon" aria-hidden="true"><i class="bi bi-images"></i></span>
                    <span class="ia-edit-section-head-text">
                        <span class="ia-edit-section-title">Фото автомобиля</span>
                        <span class="ia-edit-section-sub"><?= (int) IA_LISTING_PHOTO_REQUIRED_COUNT ?> обязательных слотов</span>
                    </span>
                    <span class="ia-edit-section-arrow" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                </summary>
                <div class="ia-edit-section-body">
                <p class="ia-edit-section-lead">Нажмите на слот, чтобы заменить фото.</p>
                <div class="ia-photo-edit">
                <div class="ia-photo-slot-grid" id="iaEditPhotoSlotGrid">
                    <?php foreach (ia_listing_photo_slot_labels_ru() as $slotIdx => $slotLabel):
                        $slotCardLabel = ia_listing_photo_slot_display_label_ru((int) $slotIdx);
                        $prefill = $slotPrefill[(int) $slotIdx] ?? null;
                        ?>
                        <article class="ia-photo-slot ia-photo-slot--edit<?= $prefill !== null ? ' ia-photo-slot--accepted' : '' ?><?= ia_listing_photo_slot_is_required((int) $slotIdx) ? '' : ' ia-photo-slot--optional' ?>" data-slot="<?= (int) $slotIdx ?>">
                            <div class="ia-photo-slot-stage">
                                <header class="ia-photo-slot-head">
                                    <span class="ia-photo-slot-num"><?= (int) $slotIdx + 1 ?></span>
                                    <h3 class="ia-photo-slot-label"><?= ia_h($slotCardLabel) ?></h3>
                                </header>
                                <input type="hidden" name="slot_keep_media_id[<?= (int) $slotIdx ?>]" value="<?= $prefill !== null ? (int) $prefill['id'] : 0 ?>" data-slot-keep="<?= (int) $slotIdx ?>">
                                <input type="file" class="ia-photo-slot-input d-none" name="listing_slot_photo[<?= (int) $slotIdx ?>]" data-slot="<?= (int) $slotIdx ?>" accept="image/jpeg,image/png,image/webp">
                                <div class="ia-photo-slot-preview<?= $prefill === null ? ' d-none' : '' ?>" data-slot="<?= (int) $slotIdx ?>">
                                    <img src="<?= $prefill !== null ? ia_h((string) $prefill['src']) : '' ?>" alt="">
                                </div>
                                <button type="button" class="ia-photo-slot-btn" data-slot="<?= (int) $slotIdx ?>" aria-label="<?= ia_h($prefill !== null ? 'Заменить: ' . $slotCardLabel : 'Загрузить: ' . $slotCardLabel) ?>"><?= $prefill !== null ? 'Заменить' : 'Загрузить' ?></button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                </div>
                </div>
            </details>

            <details class="ia-edit-section" open>
                <summary class="ia-edit-section-head">
                    <span class="ia-edit-section-icon" aria-hidden="true"><i class="bi bi-car-front"></i></span>
                    <span class="ia-edit-section-head-text">
                        <span class="ia-edit-section-title">Данные автомобиля</span>
                        <span class="ia-edit-section-sub">Марка, характеристики, VIN</span>
                    </span>
                    <span class="ia-edit-section-arrow" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                </summary>
                <div class="ia-edit-section-body">
            <div class="ia-edit-fields">
            <div class="mb-3">
                <label class="form-label">Бренд</label>
                <select name="brand_id" id="fldBrand" class="form-select" required>
                    <option value="">—</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= (int) $b['id'] ?>" <?= $selBrandId === (int) $b['id'] ? 'selected' : '' ?>><?= ia_h((string) $b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Модель</label>
                <select name="model_id" id="fldModel" class="form-select" required <?= $selBrandId <= 0 ? 'disabled' : '' ?>></select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="fldYearEdit">Год выпуска</label>
                <?php
                $iaYearSelectName = 'model_year';
                $iaYearSelectId = 'fldYearEdit';
                $iaYearSelectClass = 'form-select';
                $iaYearSelectValue = (isset($listing['model_year']) && (int) $listing['model_year'] >= 1950)
                    ? (string) (int) $listing['model_year']
                    : '';
                $iaYearSelectEmpty = 'Все';
                $iaYearSelectAriaLabel = 'Год выпуска';
                $iaYearSelectRequired = true;
                require IA_ROOT . '/includes/partials/year-select.php';
                ?>
            </div>
            <div class="mb-3">
                <label class="form-label">Пробег, км</label>
                <input type="number" name="mileage_km" class="form-control" min="0" step="1" value="<?= isset($listing['mileage_km']) ? (int) $listing['mileage_km'] : '' ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Город</label>
                <?php
                $iaCitySelectName = 'city';
                $iaCitySelectId = 'fldCityEdit';
                $iaCitySelectClass = 'form-select';
                $iaCitySelectValue = (string) ($listing['city'] ?? '');
                $iaCitySelectRequired = false;
                $iaCitySelectNoEmpty = true;
                require IA_ROOT . '/includes/partials/city-select.php';
                ?>
            </div>
            <div class="mb-3">
                <label class="form-label">VIN</label>
                <input type="text" name="vin" class="form-control" maxlength="17" value="<?= ia_h((string) ($listing['vin'] ?? '')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Тип кузова</label>
                <select name="body_type" class="form-select">
                    <?php $bt = (string) ($listing['body_type'] ?? ''); ?>
                    <option value="">— не указан —</option>
                    <?php
                    ia_require_home_quick_categories();
                    foreach (ia_listing_form_body_type_options() as $bv => $bl): ?>
                        <option value="<?= ia_h((string) $bv) ?>" <?= $bt === (string) $bv ? 'selected' : '' ?>><?= ia_h((string) $bl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Цвет</label>
                <input type="text" name="color" class="form-control" maxlength="40" value="<?= ia_h((string) ($listing['color'] ?? '')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Привод</label>
                <?php $dt = (string) ($listing['drive_type'] ?? ''); ?>
                <select name="drive_type" class="form-select">
                    <?php foreach (ia_listing_drive_type_options() as $dk => $dv): ?>
                        <option value="<?= ia_h((string) $dk) ?>" <?= $dt === (string) $dk ? 'selected' : '' ?>><?= ia_h((string) $dv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Объём двигателя</label>
                <input type="text" name="engine_volume" class="form-control" maxlength="40" value="<?= ia_h((string) ($listing['engine_volume'] ?? '')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Топливо</label>
                <?php $ft = (string) ($listing['fuel_type'] ?? ''); ?>
                <select name="fuel_type" class="form-select">
                    <option value="">—</option>
                    <option value="petrol" <?= $ft === 'petrol' ? 'selected' : '' ?>>Бензин</option>
                    <option value="diesel" <?= $ft === 'diesel' ? 'selected' : '' ?>>Дизель</option>
                    <option value="gas" <?= $ft === 'gas' ? 'selected' : '' ?>>Газ</option>
                    <option value="hybrid" <?= $ft === 'hybrid' ? 'selected' : '' ?>>Гибрид</option>
                    <option value="electric" <?= $ft === 'electric' ? 'selected' : '' ?>>Электро</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Коробка</label>
                <?php $tr = (string) ($listing['transmission'] ?? ''); ?>
                <select name="transmission" class="form-select">
                    <option value="">—</option>
                    <option value="auto" <?= $tr === 'auto' ? 'selected' : '' ?>>Автомат</option>
                    <option value="manual" <?= $tr === 'manual' ? 'selected' : '' ?>>Механика</option>
                    <option value="robot" <?= $tr === 'robot' ? 'selected' : '' ?>>Робот</option>
                    <option value="cvt" <?= $tr === 'cvt' ? 'selected' : '' ?>>CVT</option>
                </select>
            </div>
            </div>
                </div>
            </details>

            <details class="ia-edit-section" data-ia-fold="desktop-open">
                <summary class="ia-edit-section-head">
                    <span class="ia-edit-section-icon" aria-hidden="true"><i class="bi bi-tag"></i></span>
                    <span class="ia-edit-section-head-text">
                        <span class="ia-edit-section-title">Цена и публикация</span>
                        <span class="ia-edit-section-sub">Стоимость, продвижение, описание</span>
                    </span>
                    <span class="ia-edit-section-arrow" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                </summary>
                <div class="ia-edit-section-body">
            <div class="ia-edit-fields">
            <div class="mb-3">
                <label class="form-label">Валюта</label>
                <?php $curNow = ia_listing_currency_normalize((string) ($listing['currency'] ?? 'TJS')); ?>
                <select name="currency" class="form-select" required>
                    <?php foreach (ia_listing_currencies() as $cc => $ci): ?>
                        <option value="<?= ia_h($cc) ?>" <?= $curNow === $cc ? 'selected' : '' ?>><?= ia_h((string) $ci['label_ru']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Цена</label>
                <input type="number" name="price" class="form-control" min="1" step="1" required value="<?= (float) ($listing['price'] ?? 0) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Имя продавца</label>
                <input type="text" name="seller_name" class="form-control" required maxlength="150" value="<?= ia_h((string) ($listing['seller_name'] ?? '')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Продвижение</label>
                <?php
                $promoNow = ((int) ($listing['is_vip'] ?? 0) === 1) ? 'vip' : (((int) ($listing['is_top'] ?? 0) === 1) ? 'top' : 'normal');
                ?>
                <select name="promotion" class="form-select">
                    <option value="normal" <?= $promoNow === 'normal' ? 'selected' : '' ?>>Обычное объявление</option>
                    <option value="top" <?= $promoNow === 'top' ? 'selected' : '' ?>>Топ — <?= ia_h($iaPromoTop['price_label']) ?></option>
                    <option value="vip" <?= $promoNow === 'vip' ? 'selected' : '' ?>>VIP — <?= ia_h($iaPromoVip['price_label']) ?></option>
                </select>
                <?php if ($iaPromoStatus['in_grace_period']): ?>
                    <p class="form-text text-success mb-0">VIP/TOP бесплатно до <?= ia_h($iaPromoStatus['grace_ends_at']->format('d.m.Y')) ?>.</p>
                <?php elseif ($iaPromoStatus['paid_required']): ?>
                    <p class="form-text text-warning mb-0">После бесплатного периода VIP/TOP платные.</p>
                <?php endif; ?>
            </div>
            <?php $curAvail = ia_listing_availability_normalize((string) ($listing['availability'] ?? '')); ?>
            <div class="mb-3">
                <label class="form-label">Состояние</label>
                <?php $cs = (string) ($listing['condition_state'] ?? ''); ?>
                <select name="condition_state" class="form-select">
                    <?php foreach (ia_listing_condition_options() as $ck => $cv): ?>
                        <option value="<?= ia_h((string) $ck) ?>" <?= $cs === (string) $ck ? 'selected' : '' ?>><?= ia_h((string) $cv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3 ia-edit-span-2 d-flex flex-column gap-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="iaEditTurbo" name="has_turbo" value="1" <?= !empty($listing['has_turbo']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="iaEditTurbo">Турбина</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="iaEditCustoms" name="customs_cleared" value="1" <?= !empty($listing['customs_cleared']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="iaEditCustoms">Растаможен в РТ</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="iaEditTaxi" name="taxi_license" value="1" <?= !empty($listing['taxi_license']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="iaEditTaxi">Лицензия на такси</label>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Наличие автомобиля</label>
                <select name="availability" class="form-select" id="iaEditAvailability">
                    <option value="in_stock" <?= $curAvail === 'in_stock' ? 'selected' : '' ?>>В наличии</option>
                    <option value="on_order" <?= $curAvail === 'on_order' ? 'selected' : '' ?>>На заказ</option>
                </select>
                <div class="form-text">Для «На заказ» можно указать размер предоплаты.</div>
            </div>
            <div class="mb-3 ia-prepay-wrap<?= $curAvail === 'on_order' ? '' : ' d-none' ?>" id="iaEditPrepayWrap">
                <label class="form-label">Размер предоплаты</label>
                <input type="number" name="prepayment_amount" class="form-control" min="0" step="1" id="iaEditPrepay" value="<?= isset($listing['prepayment_amount']) && $listing['prepayment_amount'] !== null ? (int) $listing['prepayment_amount'] : '' ?>">
                <div class="form-text">Только для «На заказ» — размер предоплаты для бронирования.</div>
            </div>
            <div class="mb-3 ia-edit-span-2">
                <label class="form-label">Описание</label>
                <textarea name="description" class="form-control" rows="4"><?= ia_h((string) ($listing['description'] ?? '')) ?></textarea>
            </div>
            </div>
                </div>
            </details>
            </div>

            <div class="ia-edit-premium-actions">
                <button type="submit" class="btn ia-btn-accent ia-edit-premium-save">Сохранить</button>
                <a class="btn btn-outline-secondary" href="<?= ia_h(ia_public_url('car.php?id=' . $lid)) ?>">Отмена</a>
            </div>
            </div>
        </form>
    </div>
</section>

<script>
(function(){
  var models = <?= $modelsJson ?>;
  var b = document.getElementById('fldBrand');
  var m = document.getElementById('fldModel');
  var preModelId = <?= (int) $selModelId ?>;
  function sync() {
    var bid = b.value;
    m.innerHTML = '<option value="">—</option>';
    if (!bid || !models[bid]) {
      m.disabled = true;
      return;
    }
    m.disabled = false;
    models[bid].forEach(function(x) {
      var o = document.createElement('option');
      o.value = x.id;
      o.textContent = x.name;
      if (preModelId && x.id === preModelId) o.selected = true;
      m.appendChild(o);
    });
    if (preModelId) {
      m.value = String(preModelId);
    }
  }
  b.addEventListener('change', function(){ preModelId = 0; sync(); });
  sync();
})();

(function () {
  var av = document.getElementById('iaEditAvailability');
  var wrap = document.getElementById('iaEditPrepayWrap');
  var inp = document.getElementById('iaEditPrepay');
  if (!av || !wrap) return;
  function refresh() {
    if (av.value === 'on_order') {
      wrap.classList.remove('d-none');
    } else {
      wrap.classList.add('d-none');
      if (inp) inp.value = '';
    }
  }
  av.addEventListener('change', refresh);
  refresh();
})();

(function () {
  var mq = window.matchMedia('(min-width: 992px)');
  function applyEditFolds() {
    document.querySelectorAll('.ia-edit-premium [data-ia-fold="desktop-open"]').forEach(function (el) {
      if (mq.matches) {
        el.setAttribute('open', '');
      } else {
        el.removeAttribute('open');
      }
    });
  }
  applyEditFolds();
  if (mq.addEventListener) {
    mq.addEventListener('change', applyEditFolds);
  } else if (mq.addListener) {
    mq.addListener(applyEditFolds);
  }
})();

(function() {
  var grid = document.getElementById('iaEditPhotoSlotGrid');
  if (!grid) return;
  grid.querySelectorAll('.ia-photo-slot-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var idx = btn.getAttribute('data-slot');
      var input = grid.querySelector('.ia-photo-slot-input[data-slot="' + idx + '"]');
      if (input) input.click();
    });
  });
  grid.querySelectorAll('.ia-photo-slot-stage').forEach(function(stage) {
    stage.addEventListener('click', function(e) {
      if (e.target.closest('.ia-photo-slot-btn')) return;
      var slot = stage.closest('.ia-photo-slot');
      if (!slot) return;
      var idx = slot.getAttribute('data-slot');
      var input = grid.querySelector('.ia-photo-slot-input[data-slot="' + idx + '"]');
      if (input) input.click();
    });
  });
  grid.querySelectorAll('.ia-photo-slot-input').forEach(function(input) {
    input.addEventListener('change', function() {
      var file = input.files && input.files[0];
      var idx = input.getAttribute('data-slot');
      if (!file || idx === null) return;
      var preview = grid.querySelector('.ia-photo-slot-preview[data-slot="' + idx + '"]');
      var keep = grid.querySelector('[data-slot-keep="' + idx + '"]');
      var img = preview ? preview.querySelector('img') : null;
      var slot = grid.querySelector('.ia-photo-slot[data-slot="' + idx + '"]');
      if (!preview || !img) return;
      img.src = URL.createObjectURL(file);
      preview.classList.remove('d-none');
      if (keep) keep.value = '0';
      if (slot) slot.classList.add('ia-photo-slot--accepted');
    });
  });
})();
</script>

<?php require IA_ROOT . '/includes/partials/site-footer.php'; ?>
