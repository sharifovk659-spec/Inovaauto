<?php

declare(strict_types=1);

/** Карточка бренда: логотип в круге, название, количество. */

$brandId = (int) ($b['id'] ?? 0);
$brandName = trim((string) ($b['name'] ?? ''));
$brandSlugHint = trim((string) ($b['slug'] ?? ''));
$brandCount = max(0, (int) ($b['count'] ?? 0));

if ($brandName === '') {
    return;
}

$brandHref = $brandId > 0
    ? ia_public_url('catalog.php?brand_id=' . $brandId)
    : ia_public_url('catalog.php?q=' . rawurlencode($brandName));

$logo = function_exists('ia_brand_logo_urls')
    ? ia_brand_logo_urls($brandName, $brandSlugHint !== '' ? $brandSlugHint : null)
    : ['src' => '', 'fallback' => '', 'slug' => ''];

$logoSrc = (string) ($logo['src'] ?? '');
$logoFallback = (string) ($logo['fallback'] ?? '');

$initial = function_exists('mb_strtoupper')
    ? mb_strtoupper(mb_substr($brandName, 0, 1))
    : strtoupper(substr($brandName, 0, 1));
?>
<a class="ia-brand-card" href="<?= ia_h($brandHref) ?>" title="<?= ia_h($brandName) ?>">
    <span class="ia-brand-card-mark" aria-hidden="true">
        <?php if ($logoSrc !== ''): ?>
            <img
                class="ia-brand-card-logo"
                src="<?= ia_h($logoSrc) ?>"
                alt=""
                <?= ia_img_perf_attrs(['width' => 52, 'height' => 52]) ?>
                <?php if ($logoFallback !== ''): ?>
                data-ia-logo-fallback="<?= ia_h($logoFallback) ?>"
                <?php endif; ?>
                onerror="var el=this;if(el.dataset.iaLogoFallback&&!el.dataset.iaLogoTried){el.dataset.iaLogoTried='1';el.src=el.dataset.iaLogoFallback;return;}el.hidden=true;el.nextElementSibling.hidden=false;"
            >
            <span class="ia-brand-card-fallback" hidden><?= ia_h($initial) ?></span>
        <?php else: ?>
            <span class="ia-brand-card-fallback"><?= ia_h($initial) ?></span>
        <?php endif; ?>
    </span>
    <span class="ia-brand-card-label"><?= ia_h($brandName) ?></span>
    <span class="ia-brand-card-count"><?= ia_h((string) $brandCount) ?> авто</span>
</a>
