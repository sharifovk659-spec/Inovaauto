<?php

declare(strict_types=1);

/** Одна карточка объявления в кабинете (свёрнута по умолчанию). */
if (!isset($ad) || !is_array($ad)) {
    return;
}

$stLabel = (string) ($ad['status'] ?? '');
$stClass = ia_pub_listing_status_css_class($stLabel);
$thumb = ia_listing_photo_src($ad['photo_url'] ?? null);
$cabAvail = ia_listing_availability_normalize((string) ($ad['availability'] ?? ''));
$year = (int) ($ad['model_year'] ?? 0);
$mileage = (int) ($ad['mileage_km'] ?? 0);
$fuel = trim((string) ($ad['fuel_type'] ?? ''));
$metaParts = [];
$metaParts[] = $year > 0 ? (string) $year : '—';
$metaParts[] = $mileage > 0 ? number_format($mileage, 0, '.', ' ') . ' км' : '— км';
$fuelLbl = $fuel !== '' ? ia_listing_fuel_label_ru($fuel) : '';
$metaParts[] = $fuelLbl !== '' ? $fuelLbl : '—';
$carUrl = ia_public_url('car.php?id=' . (int) $ad['id']);
$priceLabel = ia_listing_format_price((float) $ad['price'], (string) ($ad['currency'] ?? 'TJS'));
$listTabLocal = (string) ($listTab ?? 'active');
?>
<details class="ia-cabinet-item ia-cabinet-item--fold">
    <summary class="ia-cabinet-item-summary">
        <span class="ia-cabinet-item-summary-photo">
            <img src="<?= ia_h($thumb) ?>" alt="" <?= ia_img_perf_attrs(['width' => 64, 'height' => 48]) ?>>
        </span>
        <span class="ia-cabinet-item-summary-main">
            <span class="ia-cabinet-item-summary-title"><?= ia_h((string) $ad['brand'] . ' ' . (string) $ad['model']) ?></span>
            <span class="ia-cabinet-item-summary-meta"><?= ia_h(implode(' · ', $metaParts)) ?></span>
        </span>
        <span class="ia-cabinet-item-summary-end">
            <span class="ia-cabinet-item-summary-price"><?= ia_h($priceLabel) ?></span>
            <span class="ia-cabinet-status <?= $stClass ?>"><?= ia_h(ia_pub_listing_status_ru($stLabel)) ?></span>
            <i class="bi bi-chevron-down ia-cabinet-item-chev" aria-hidden="true"></i>
        </span>
    </summary>
    <div class="ia-cabinet-item-body">
        <div class="ia-cabinet-item-grid">
            <a class="ia-cabinet-item-photo" href="<?= ia_h($carUrl) ?>">
                <img src="<?= ia_h($thumb) ?>" alt="" width="160" height="120" <?= ia_img_perf_attrs() ?>>
            </a>
            <div class="ia-cabinet-item-main">
                <h3 class="ia-cabinet-item-title mb-1">
                    <a href="<?= ia_h($carUrl) ?>"><?= ia_h((string) $ad['brand'] . ' ' . (string) $ad['model']) ?></a>
                </h3>
                <div class="ia-cabinet-item-meta small text-secondary mb-2">
                    <span class="ia-badge-availability <?= $cabAvail === 'on_order' ? 'ia-badge-availability--on-order' : 'ia-badge-availability--in-stock' ?>"><?= ia_h(ia_listing_availability_label_ru($cabAvail)) ?></span>
                    <?= ia_h(implode(' • ', $metaParts)) ?>
                </div>
                <div class="ia-cabinet-item-bottom d-flex flex-wrap align-items-center gap-2">
                    <span class="ia-cab-stat" title="Просмотры">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                        <?= (int) ($ad['views_count'] ?? 0) ?>
                    </span>
                    <span class="ia-cab-stat" title="Клики">
                        <i class="bi bi-lightning-charge" aria-hidden="true"></i>
                        <?= (int) ($ad['clicks_count'] ?? 0) ?>
                    </span>
                    <span class="ia-cab-stat" title="В избранном">
                        <i class="bi bi-heart" aria-hidden="true"></i>
                        <?= (int) ($ad['favorites_count'] ?? 0) ?>
                    </span>
                    <span class="ia-cab-stat" title="Сообщения">
                        <i class="bi bi-chat-dots" aria-hidden="true"></i>
                        <?= (int) ($ad['messages_count'] ?? 0) ?>
                    </span>
                </div>
            </div>
            <div class="ia-cabinet-item-side">
                <div class="ia-cabinet-item-price mb-2"><?= ia_h($priceLabel) ?></div>
                <a class="btn btn-sm ia-btn-accent w-100 mb-2" href="<?= ia_h(ia_public_url('edit-listing.php?id=' . (int) $ad['id'])) ?>">Изменить</a>
                <?php if ($stLabel === 'approved'): ?>
                    <form method="post" class="mb-2" onsubmit="return confirm('Отметить объявление как проданное?');">
                        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                        <input type="hidden" name="action" value="mark_sold">
                        <input type="hidden" name="list_tab" value="<?= ia_h($listTabLocal) ?>">
                        <input type="hidden" name="listing_id" value="<?= (int) $ad['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Продано</button>
                    </form>
                <?php endif; ?>
                <?php if ($stLabel === 'archived'): ?>
                    <form method="post" class="mb-2" onsubmit="return confirm('Отправить объявление на повторную проверку?');">
                        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                        <input type="hidden" name="action" value="reactivate_listing">
                        <input type="hidden" name="list_tab" value="<?= ia_h($listTabLocal) ?>">
                        <input type="hidden" name="listing_id" value="<?= (int) $ad['id'] ?>">
                        <button type="submit" class="btn btn-sm ia-btn-accent w-100">Вернуть в каталог</button>
                    </form>
                <?php endif; ?>
                <?php if (in_array($stLabel, ['approved', 'pending'], true)): ?>
                    <form method="post" class="mb-2" onsubmit="return confirm('Снять объявление с публикации?');">
                        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                        <input type="hidden" name="action" value="delete_listing">
                        <input type="hidden" name="list_tab" value="<?= ia_h($listTabLocal) ?>">
                        <input type="hidden" name="listing_id" value="<?= (int) $ad['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">Снять с публикации</button>
                    </form>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-secondary w-100" href="<?= ia_h($carUrl) ?>">Открыть</a>
            </div>
        </div>
    </div>
</details>
