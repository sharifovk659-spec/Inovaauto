<?php

declare(strict_types=1);

/** @var string $iaPreloaderLogoUrl */
$iaPreloaderLogoUrl = ia_public_asset_version('assets/brand/innovaauto-logo-dark.svg');
?>
<div id="iaPreloader" class="ia-preloader" hidden aria-hidden="true" aria-live="polite" aria-busy="true" role="status">
    <div class="ia-preloader__inner">
        <div class="ia-preloader__logo" aria-hidden="true">
            <img src="<?= ia_h($iaPreloaderLogoUrl) ?>" width="220" height="46" alt="" decoding="async" fetchpriority="high">
        </div>
        <p class="ia-preloader__text">Загрузка автомобилей…</p>
        <div class="ia-preloader__track" aria-hidden="true">
            <div class="ia-preloader__bar"></div>
        </div>
        <span class="ia-preloader__pct" aria-hidden="true">0%</span>
    </div>
</div>
