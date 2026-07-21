<?php

declare(strict_types=1);

if (!isset($ad) || !is_array($ad)) {
    return;
}

$stLabel = (string) ($ad['status'] ?? '');
$stClass = ia_pub_listing_status_css_class($stLabel);
$thumb = ia_listing_photo_src($ad['photo_url'] ?? null);
$carUrl = ia_public_url('car.php?id=' . (int) $ad['id']);
$priceLabel = ia_listing_format_price((float) $ad['price'], (string) ($ad['currency'] ?? 'TJS'));
$listTabLocal = (string) ($listTab ?? 'active');
$title = trim((string) $ad['brand'] . ' ' . (string) $ad['model']);
?>
<details class="ia-cabinet-item ia-cabinet-item--fold">
    <summary class="ia-cabinet-item-summary">
        <span class="ia-cabinet-item-summary-photo">
            <img src="<?= ia_h($thumb) ?>" alt="" <?= ia_img_perf_attrs(['width' => 64, 'height' => 48]) ?>>
        </span>
        <span class="ia-cabinet-item-summary-main">
            <span class="ia-cabinet-item-summary-title"><?= ia_h($title) ?></span>
            <span class="ia-cabinet-item-summary-meta"><?= ia_h(ia_pub_listing_status_ru($stLabel)) ?></span>
        </span>
        <span class="ia-cabinet-item-summary-end">
            <span class="ia-cabinet-item-summary-price"><?= ia_h($priceLabel) ?></span>
            <i class="bi bi-chevron-down ia-cabinet-item-chev" aria-hidden="true"></i>
        </span>
    </summary>
    <div class="ia-cabinet-item-body p-2">
        <p class="mb-2"><span class="ia-cabinet-status <?= $stClass ?>"><?= ia_h(ia_pub_listing_status_ru($stLabel)) ?></span></p>
        <a class="btn btn-sm ia-btn-accent w-100 mb-2" href="<?= ia_h(ia_public_url('edit-listing.php?id=' . (int) $ad['id'])) ?>">Изменить</a>
        <a class="btn btn-sm btn-outline-secondary w-100" href="<?= ia_h($carUrl) ?>">Открыть</a>
    </div>
</details>
