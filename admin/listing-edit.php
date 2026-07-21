<?php
declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_listings.php';
require_once IA_ROOT . '/includes/listing_media.php';
require_once IA_ROOT . '/includes/listing_geo.php';
require_once IA_ROOT . '/includes/listing_integrity.php';
use InnovaAuto\Security\Csrf;

ia_require_section('listings');
$id = ia_request_int('id');
$row = $id > 0 ? ia_admin_listing_by_id($id) : null;
if ($row === null) {
    ia_flash('listings_error', 'Объявление не найдено.');
    ia_redirect(ia_admin_url('listings.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('listings_error', 'Сессия устарела.');
    } else {
        $availPost = ia_listing_availability_normalize($_POST['availability'] ?? null);
        $photoUrlTrim = ia_input_url($_POST['photo_url'] ?? '');
        $mediaErr = ia_listing_validate_media_for_admin_listing(ia_db(), $id, $availPost, $photoUrlTrim);
        if ($mediaErr !== null) {
            ia_flash('listings_error', $mediaErr);
        } else {
            ia_admin_listing_update($id, $_POST);
            ia_flash('listings_ok', 'Объявление обновлено.');
            ia_redirect(ia_admin_url('listings.php'));
        }
    }
}

$user = ia_current_user();
$mediaAdmin = ia_listing_media_list(ia_db(), (int) $row['id']);
$pageTitle = 'Редактирование объявления';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<main class="container py-4">
    <h1 class="h4 mb-3">Редактирование объявления #<?= (int) $row['id'] ?></h1>
    <?php $listingGeo = ia_listing_geo_from_row($row); ?>
    <?php if ($listingGeo !== null): ?>
        <div class="alert alert-light border small ia-listing-geo-admin mb-3">
            <div class="fw-semibold mb-1">Место размещения объявления</div>
            <div>
                <a href="<?= ia_h(ia_listing_geo_maps_url($listingGeo['lat'], $listingGeo['lng'])) ?>" target="_blank" rel="noopener noreferrer">
                    <?= ia_h(ia_listing_geo_coords_text($listingGeo['lat'], $listingGeo['lng'])) ?>
                </a>
            </div>
            <?php
            $geoCaptured = ia_listing_geo_captured_text($listingGeo['captured_at']);
            $geoAccuracy = ia_listing_geo_accuracy_text($listingGeo['accuracy_m']);
            $geoMeta = array_values(array_filter([$geoCaptured, $geoAccuracy], static fn (string $part): bool => $part !== ''));
            ?>
            <?php if ($geoMeta !== []): ?>
                <div class="text-secondary mt-1"><?= ia_h(implode(' · ', $geoMeta)) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if (count($mediaAdmin) > 0): ?>
        <p class="text-secondary small mb-2">Медиафайлы: <?= count($mediaAdmin) ?> (фото и видео хранятся в каталоге; основное фото объявления синхронизируется с первым изображением)</p>
        <div class="d-flex flex-wrap gap-2 mb-4">
            <?php foreach ($mediaAdmin as $m): ?>
                <?php
                $msrc = ia_listing_photo_src((string) $m['stored_path']);
                $isV = ($m['media_kind'] ?? '') === 'video';
                ?>
                <div class="border rounded p-1" style="width:100px">
                    <?php if ($isV): ?>
                        <div class="small text-center text-secondary">Видео</div>
                        <video src="<?= ia_h($msrc) ?>" class="w-100 rounded" style="height:64px;object-fit:cover" muted preload="metadata"></video>
                    <?php else: ?>
                        <img src="<?= ia_h($msrc) ?>" alt="" class="w-100 rounded" style="height:64px;object-fit:cover">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form method="post" class="card card-body">
        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">URL главного фото (резерв)</label><input class="form-control" name="photo_url" value="<?= ia_h((string) $row['photo_url']) ?>"></div>
            <div class="col-md-4"><label class="form-label">Бренд</label><input class="form-control" name="brand" value="<?= ia_h((string) $row['brand']) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Модель</label><input class="form-control" name="model" value="<?= ia_h((string) $row['model']) ?>" required></div>
            <div class="col-md-4">
                <label class="form-label">Валюта</label>
                <?php $rowCur = ia_listing_currency_normalize((string) ($row['currency'] ?? 'TJS')); ?>
                <select class="form-select" name="currency">
                    <?php foreach (ia_listing_currencies() as $cc => $ci): ?>
                        <option value="<?= ia_h($cc) ?>" <?= $rowCur === $cc ? 'selected' : '' ?>><?= ia_h((string) $ci['label_ru']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><label class="form-label">Цена</label><input class="form-control" type="number" step="0.01" min="0" name="price" value="<?= ia_h((string) $row['price']) ?>"></div>
            <div class="col-md-4"><label class="form-label">Продавец</label><input class="form-control" name="seller_name" value="<?= ia_h((string) $row['seller_name']) ?>"></div>
            <div class="col-md-4">
                <label class="form-label">Наличие</label>
                <?php $rowAvail = ia_listing_availability_normalize((string) ($row['availability'] ?? '')); ?>
                <select class="form-select" name="availability">
                    <option value="in_stock" <?= $rowAvail === 'in_stock' ? 'selected' : '' ?>>В наличии</option>
                    <option value="on_order" <?= $rowAvail === 'on_order' ? 'selected' : '' ?>>На заказ</option>
                </select>
                <div class="form-text">Фото/видео для объявления не обязательны: можно оставить без медиа или добавить любое количество (до лимита).</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Статус</label>
                <select class="form-select" name="status">
                    <?php foreach (ia_listing_status_codes() as $st): ?>
                        <option value="<?= $st ?>" <?= (string) $row['status'] === $st ? 'selected' : '' ?>><?= ia_h(ia_admin_listing_status_ru($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php
            $riskHints = ia_listing_risk_hints_ru($row);
            if ($riskHints !== []):
                ?>
            <div class="col-12">
                <div class="alert alert-warning py-2 small mb-0">
                    <div class="fw-semibold mb-1">Подозрительные признаки (для модератора)</div>
                    <ul class="mb-0 ps-3"><?php foreach ($riskHints as $h): ?><li><?= ia_h($h) ?></li><?php endforeach; ?></ul>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-md-3 form-check ms-3">
                <input class="form-check-input" id="is_vip" type="checkbox" name="is_vip" value="1" <?= (int) $row['is_vip'] === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_vip">VIP</label>
            </div>
            <div class="col-md-3 form-check ms-3">
                <input class="form-check-input" id="is_top" type="checkbox" name="is_top" value="1" <?= (int) $row['is_top'] === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_top">Закрепить в топе</label>
            </div>
            <div class="col-md-4">
                <label class="form-label">Тип кузова</label>
                <select class="form-select" name="body_type">
                    <?php $rowBody = (string) ($row['body_type'] ?? ''); ?>
                    <?php foreach (ia_admin_listing_body_types() as $bk => $bv): ?>
                        <option value="<?= ia_h((string) $bk) ?>" <?= $rowBody === (string) $bk ? 'selected' : '' ?>><?= ia_h((string) $bv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="adminFldYear">Год выпуска</label>
                <?php
                $iaYearSelectName = 'model_year';
                $iaYearSelectId = 'adminFldYear';
                $iaYearSelectClass = 'form-select';
                $iaYearSelectValue = (isset($row['model_year']) && (int) $row['model_year'] >= 1900)
                    ? (string) (int) $row['model_year']
                    : '';
                $iaYearSelectEmpty = 'Все';
                $iaYearSelectMin = 1900;
                $iaYearSelectAriaLabel = 'Год выпуска';
                require IA_ROOT . '/includes/partials/year-select.php';
                ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Пробег, км (от 0)</label>
                <input class="form-control" type="number" name="mileage_km" min="0" step="1" value="<?= ia_h((string) ($row['mileage_km'] ?? '')) ?>" placeholder="0">
                <div class="form-text">Минимум 0 км — для новых авто допускается значение 0.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Коробка</label>
                <select class="form-select" name="transmission">
                    <?php $tr = (string) ($row['transmission'] ?? ''); ?>
                    <option value="" <?= $tr === '' ? 'selected' : '' ?>>—</option>
                    <option value="manual" <?= $tr === 'manual' ? 'selected' : '' ?>>Механика</option>
                    <option value="automatic" <?= $tr === 'automatic' ? 'selected' : '' ?>>Автомат</option>
                    <option value="cvt" <?= $tr === 'cvt' ? 'selected' : '' ?>>Вариатор (CVT)</option>
                    <option value="robot" <?= $tr === 'robot' ? 'selected' : '' ?>>Робот</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Топливо</label>
                <select class="form-select" name="fuel_type">
                    <?php $fl = (string) ($row['fuel_type'] ?? ''); ?>
                    <option value="" <?= $fl === '' ? 'selected' : '' ?>>—</option>
                    <option value="petrol" <?= $fl === 'petrol' ? 'selected' : '' ?>>Бензин</option>
                    <option value="diesel" <?= $fl === 'diesel' ? 'selected' : '' ?>>Дизель</option>
                    <option value="hybrid" <?= $fl === 'hybrid' ? 'selected' : '' ?>>Гибрид</option>
                    <option value="electric" <?= $fl === 'electric' ? 'selected' : '' ?>>Электро</option>
                    <option value="gas" <?= $fl === 'gas' ? 'selected' : '' ?>>Газ</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Город</label>
                <?php
                $iaCitySelectName = 'city';
                $iaCitySelectId = 'adminListingCity';
                $iaCitySelectClass = 'form-select';
                $iaCitySelectValue = (string) ($row['city'] ?? '');
                $iaCitySelectNoEmpty = true;
                require IA_ROOT . '/includes/partials/city-select.php';
                ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Цвет</label>
                <input class="form-control" name="color" maxlength="40" value="<?= ia_h((string) ($row['color'] ?? '')) ?>" placeholder="Например, Белый">
            </div>
            <div class="col-md-4">
                <label class="form-label">Привод</label>
                <select class="form-select" name="drive_type">
                    <?php $rDrive = (string) ($row['drive_type'] ?? ''); ?>
                    <?php foreach (ia_listing_drive_type_options() as $dk => $dv): ?>
                        <option value="<?= ia_h((string) $dk) ?>" <?= $rDrive === (string) $dk ? 'selected' : '' ?>><?= ia_h((string) $dv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Объём двигателя</label>
                <input class="form-control" name="engine_volume" maxlength="40" value="<?= ia_h((string) ($row['engine_volume'] ?? '')) ?>" placeholder="2.0 L или Электрический">
            </div>
            <div class="col-md-4">
                <label class="form-label">Состояние</label>
                <select class="form-select" name="condition_state">
                    <?php $rCond = (string) ($row['condition_state'] ?? ''); ?>
                    <?php foreach (ia_listing_condition_options() as $ck => $cv): ?>
                        <option value="<?= ia_h((string) $ck) ?>" <?= $rCond === (string) $ck ? 'selected' : '' ?>><?= ia_h((string) $cv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Андозаи пешпардохт, с.</label>
                <input class="form-control" type="number" min="0" step="1" name="prepayment_amount" value="<?= isset($row['prepayment_amount']) && $row['prepayment_amount'] !== null ? (int) $row['prepayment_amount'] : '' ?>" placeholder="только для «На заказ»">
            </div>
            <div class="col-md-12 d-flex flex-wrap gap-3">
                <div class="form-check">
                    <input class="form-check-input" id="ia_has_turbo" type="checkbox" name="has_turbo" value="1" <?= !empty($row['has_turbo']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="ia_has_turbo">Турбина</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" id="ia_customs" type="checkbox" name="customs_cleared" value="1" <?= !empty($row['customs_cleared']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="ia_customs">Растаможен в РТ</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" id="ia_taxi" type="checkbox" name="taxi_license" value="1" <?= !empty($row['taxi_license']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="ia_taxi">Лицензия на такси</label>
                </div>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Сохранить</button>
            <a class="btn btn-outline-secondary" href="<?= ia_h(ia_admin_url('listings.php')) ?>">Отмена</a>
        </div>
    </form>
</main>
<?php require __DIR__ . '/partials/foot.php'; ?>
