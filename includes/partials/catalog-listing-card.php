<?php

declare(strict_types=1);

/** @var array<string, mixed> $row */
/** @var array<int, true> $favMap */
/** @var array<int, list<string>> $listingThumbs */
/** @var int $catalogCardIdx */
/** @var int $page */
/** @var string $catalogReturnQs */

$row = is_array($row ?? null) ? $row : [];
$favMap = is_array($favMap ?? null) ? $favMap : [];
$listingThumbs = is_array($listingThumbs ?? null) ? $listingThumbs : [];
$catalogCardIdx = (int) ($catalogCardIdx ?? 0);
$page = max(1, (int) ($page ?? 1));
$catalogReturnQs = (string) ($catalogReturnQs ?? '');

$lid = (int) ($row['id'] ?? 0);
$isFav = isset($favMap[$lid]);
$carUrl = ia_public_url('car.php?id=' . $lid);
$ph = ia_listing_photo_src($row['photo_url'] ?? null);
$cardThumbs = $listingThumbs[$lid] ?? [];
$hoverThumbs = $cardThumbs;
if ($hoverThumbs === [] && $ph !== '') {
    $hoverThumbs = [$ph];
}
if ($hoverThumbs !== [] && (!isset($hoverThumbs[0]) || $hoverThumbs[0] !== $ph) && $ph !== '') {
    array_unshift($hoverThumbs, $ph);
    $hoverThumbs = array_values(array_unique($hoverThumbs));
}
$photoCount = max(1, count($hoverThumbs));
$thumbsAttr = count($hoverThumbs) > 1
    ? json_encode(array_slice($hoverThumbs, 0, 6), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    : null;

$rowY = isset($row['model_year']) ? (int) $row['model_year'] : 0;
$rowYOk = $rowY >= 1950;
$rowC = trim((string) ($row['city'] ?? ''));
$rowTr = ia_listing_transmission_label_ru((string) ($row['transmission'] ?? ''));
$rowFuel = ia_listing_fuel_label_ru((string) ($row['fuel_type'] ?? ''));
$viewsN = ia_listing_views_count($row);
$viewsLabel = ia_listing_views_label_ru($viewsN);

$createdTs = strtotime(trim((string) ($row['created_at'] ?? '')));
$isNewListing = $createdTs !== false && $createdTs > (time() - 14 * 86400);
$showVip = ia_listing_is_vip($row);
$showTop = ia_listing_is_top($row);
$catalogAvail = ia_listing_availability_normalize((string) ($row['availability'] ?? ''));
?>
<article class="ia-listing-card ia-listing-card--catalog ia-listing-card--catalog-v2">
    <div class="ia-catalog-card-media">
        <a
            href="<?= ia_h($carUrl) ?>"
            class="ia-listing-card-img-wrap ia-catalog-card-img-wrap ia-card-hover<?= $thumbsAttr ? ' has-hover-thumbs' : '' ?>"
            <?= $thumbsAttr ? ' data-thumbs=' . "'" . htmlspecialchars($thumbsAttr, ENT_QUOTES, 'UTF-8') . "'" : '' ?>
        >
            <img
                class="ia-listing-card-img"
                src="<?= ia_h($ph) ?>"
                alt=""
                <?= ia_img_perf_attrs([
                    'loading' => ($page === 1 && $catalogCardIdx < 8) ? 'eager' : 'lazy',
                    'width' => 480,
                    'height' => 360,
                ]) ?>
            >
            <?php if ($showTop || $showVip || $isNewListing): ?>
                <div class="ia-catalog-card-badges-bar" aria-hidden="true">
                    <div class="ia-catalog-card-badges-left">
                        <?php if ($showTop): ?><span class="ia-catalog-badge ia-catalog-badge--top">ТОП</span><?php endif; ?>
                        <?php if ($isNewListing): ?><span class="ia-catalog-badge ia-catalog-badge--new">НОВОЕ</span><?php endif; ?>
                    </div>
                    <?php if ($showVip): ?>
                        <div class="ia-catalog-card-badges-right">
                            <span class="ia-catalog-badge ia-catalog-badge--vip">VIP</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($thumbsAttr): ?>
                <span class="ia-card-hover-dots" aria-hidden="true">
                    <?php foreach ($hoverThumbs as $hi => $_): ?>
                        <span class="ia-card-hover-dot<?= $hi === 0 ? ' is-active' : '' ?>"></span>
                    <?php endforeach; ?>
                </span>
            <?php endif; ?>
        </a>
        <div class="ia-card-actions ia-catalog-card-fav">
            <form method="post" class="ia-card-action-form">
                <input type="hidden" name="_csrf" value="<?= ia_h(\InnovaAuto\Security\Csrf::token()) ?>">
                <input type="hidden" name="action" value="toggle_fav">
                <input type="hidden" name="listing_id" value="<?= $lid ?>">
                <input type="hidden" name="return_qs" value="<?= ia_h($catalogReturnQs) ?>">
                <button
                    type="submit"
                    class="ia-card-icon-btn ia-card-icon-btn--fav<?= $isFav ? ' is-active' : '' ?>"
                    aria-label="<?= $isFav ? 'Убрать из избранного' : 'Добавить в избранное' ?>"
                    title="<?= $isFav ? 'Убрать из избранного' : 'Добавить в избранное' ?>"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 21.2l-1.45-1.32C5.4 15.25 2 12.16 2 8.5 2 5.41 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.41 22 8.5c0 3.66-3.4 6.75-8.55 11.39z"/></svg>
                </button>
            </form>
        </div>
        <span class="ia-catalog-card-photos" title="<?= ia_h($photoCount . ' фото') ?>">
            <i class="bi bi-camera-fill" aria-hidden="true"></i>
            <span><?= (int) $photoCount ?></span>
        </span>
        <span class="ia-catalog-card-views" title="<?= ia_h($viewsLabel) ?>" aria-label="<?= ia_h($viewsLabel) ?>">
            <i class="bi bi-eye-fill" aria-hidden="true"></i>
            <span><?= ia_h(number_format($viewsN, 0, '.', ' ')) ?></span>
        </span>
    </div>
    <div class="ia-catalog-card-body">
        <div class="ia-catalog-card-head">
            <a class="ia-catalog-card-title" href="<?= ia_h($carUrl) ?>"><?= ia_h(trim((string) $row['brand'] . ' ' . (string) $row['model'])) ?></a>
        </div>
        <ul class="ia-catalog-card-specs">
            <li><i class="bi bi-calendar3" aria-hidden="true"></i><span><?= ia_h($rowYOk ? (string) $rowY : '—') ?></span></li>
            <li><i class="bi bi-speedometer2" aria-hidden="true"></i><span><?= ia_h(ia_listing_mileage_label_ru($row['mileage_km'] ?? 0)) ?></span></li>
            <li><i class="bi bi-gear-wide-connected" aria-hidden="true"></i><span><?= ia_h($rowTr !== '' ? $rowTr : '—') ?></span></li>
            <li><i class="bi bi-fuel-pump" aria-hidden="true"></i><span><?= ia_h($rowFuel !== '' ? $rowFuel : '—') ?></span></li>
        </ul>
        <div class="ia-catalog-card-foot">
            <span class="ia-catalog-card-city">
                <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                <span><?= ia_h($rowC !== '' ? $rowC : '—') ?></span>
            </span>
            <a class="ia-catalog-card-go" href="<?= ia_h($carUrl) ?>" aria-label="Открыть объявление">
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
            </a>
        </div>
        <div class="ia-catalog-card-meta ia-listing-card-meta">
            <span class="ia-badge-availability <?= $catalogAvail === 'on_order' ? 'ia-badge-availability--on-order' : 'ia-badge-availability--in-stock' ?>"><?= ia_h(ia_listing_availability_label_ru($catalogAvail)) ?></span>
            <span class="ia-catalog-card-price ia-price ia-price-card"><?= ia_h(ia_listing_format_price((float) $row['price'], (string) ($row['currency'] ?? 'TJS'))) ?></span>
        </div>
    </div>
</article>
