<?php

declare(strict_types=1);

/** Мобильная главная (≤991px): баннер + поиск. Стили внутри — для хостинга. */

if (!isset($heroMobileSet) || !is_array($heroMobileSet) || (string) ($heroMobileSet['fallback'] ?? '') === '') {
    if (function_exists('ia_home_hero_mobile_set')) {
        $heroMobileSet = ia_home_hero_mobile_set();
    }
}

if (!is_array($heroMobileSet) || (string) ($heroMobileSet['fallback'] ?? '') === '') {
    $heroImageCandidates = [
        'IMG/baneri telefon.jpeg',
        'IMG/baneri telefon.jpg',
        'IMG/baneri-telefon.jpeg',
        'IMG/baneri-telefon.jpg',
        'IMG/bANER.png',
        'IMG/BANER.png',
    ];
    foreach ($heroImageCandidates as $rel) {
        $abs = IA_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($abs)) {
            continue;
        }
        $url = function_exists('ia_public_asset_version')
            ? ia_public_asset_version($rel)
            : (function_exists('ia_public_asset') ? ia_public_asset($rel) : $rel);
        $heroMobileSet = [
            'has_webp' => false,
            'fallback' => (string) $url,
            'width' => 1080,
            'height' => 608,
            'sources' => [],
            'srcset' => '',
            'preload' => (string) $url,
        ];
        $info = @getimagesize($abs);
        if (is_array($info) && isset($info[0], $info[1])) {
            $heroMobileSet['width'] = (int) $info[0];
            $heroMobileSet['height'] = (int) $info[1];
        }
        break;
    }
}

$heroMobileBannerUrl = is_array($heroMobileSet) ? (string) ($heroMobileSet['fallback'] ?? '') : '';
$heroHasImage = $heroMobileBannerUrl !== '';
?>
<!-- ia-home-hero-mobile-v2 -->
<style>
@media (max-width: 991.98px) {
  .ia-home-mobile {
    width: 100vw;
    max-width: 100vw;
    margin-left: calc(50% - 50vw);
    margin-right: calc(50% - 50vw);
  }
  .ia-home-mobile .ia-home-hero {
    position: relative;
    overflow: hidden;
    color: #f8fafc;
    width: 100%;
    margin: 0;
    padding: 0;
    background: #050b18;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.22);
  }
  .ia-home-mobile .ia-home-hero-visual {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 52%) minmax(0, 48%);
    align-items: stretch;
    width: 100%;
    aspect-ratio: 390 / 220;
    min-height: clamp(210px, 56vw, 300px);
    max-height: min(52vh, 340px);
    overflow: hidden;
    background: #050b18;
  }
  .ia-home-mobile .ia-home-hero-media {
    position: absolute;
    inset: 0;
    z-index: 0;
    overflow: hidden;
    background-color: #0a0e14;
  }
  .ia-home-mobile .ia-home-hero-media picture,
  .ia-home-mobile .ia-home-hero-photo {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    object-position: 82% center;
  }
  .ia-home-mobile .ia-home-hero-overlay {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background:
      linear-gradient(102deg, rgba(5, 8, 14, 0.92) 0%, rgba(5, 8, 14, 0.72) 30%, rgba(5, 8, 14, 0.28) 50%, transparent 72%),
      linear-gradient(180deg, rgba(5, 8, 14, 0.35) 0%, transparent 42%, rgba(5, 8, 14, 0.5) 100%);
  }
  .ia-home-mobile .ia-home-hero-head {
    position: relative;
    grid-column: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-self: stretch;
    min-width: 0;
    padding: 1.1rem 0.65rem 1.5rem max(1.05rem, env(safe-area-inset-left, 0px));
    z-index: 2;
  }
  .ia-home-mobile .ia-home-hero-accent {
    display: block;
    width: 2.15rem;
    height: 3px;
    margin: 0 0 0.7rem;
    border-radius: 999px;
    background: linear-gradient(90deg, #3d7eff 0%, #5b92ff 55%, #7c3aed 100%);
    box-shadow: 0 0 12px rgba(61, 126, 255, 0.45);
  }
  .ia-home-mobile .ia-home-hero-title {
    margin: 0;
    max-width: 13.5rem;
    font-size: clamp(1.15rem, 5.4vw, 1.55rem);
    font-weight: 800;
    line-height: 1.12;
    letter-spacing: -0.03em;
    color: #fff;
    text-shadow: 0 2px 20px rgba(0, 0, 0, 0.45);
  }
  .ia-home-mobile .ia-home-hero-aside {
    grid-column: 2;
    position: relative;
    z-index: 1;
    min-height: 0;
    pointer-events: none;
  }
  .ia-home-mobile .ia-home-hero-actions--dock {
    position: relative;
    z-index: 6;
    margin-top: -1.05rem;
    padding: 0 0.95rem 0.8rem;
    padding-left: max(0.95rem, env(safe-area-inset-left, 0px));
    padding-right: max(0.95rem, env(safe-area-inset-right, 0px));
    background: linear-gradient(180deg, transparent 0%, #050b18 32%);
  }
  html[data-bs-theme='light']:not([data-ia-palette='sepia']) .ia-home-mobile .ia-home-hero-actions--dock {
    background: linear-gradient(180deg, transparent 0%, #f8fafc 30%);
  }
}
</style>
<div class="ia-home-mobile d-lg-none">
    <form class="ia-home-mobile-form" method="get" action="<?= ia_h($catalogUrl) ?>" id="iaHomeMobileSearch">
        <section class="ia-home-hero<?= $heroHasImage ? ' ia-home-hero--has-img' : '' ?>">
            <div class="ia-home-hero-visual">
                <div class="ia-home-hero-media" aria-hidden="true">
                    <?php if ($heroHasImage): ?>
                        <?php if (function_exists('ia_render_responsive_picture')): ?>
                            <?php
                            ia_render_responsive_picture($heroMobileSet, [
                                'class' => 'ia-home-hero-photo',
                                'alt' => '',
                                'sizes' => '100vw',
                                'loading' => 'eager',
                                'fetchpriority' => 'high',
                                'decoding' => 'async',
                            ]);
                            ?>
                        <?php else: ?>
                            <img
                                class="ia-home-hero-photo"
                                src="<?= ia_h($heroMobileBannerUrl) ?>"
                                alt=""
                                width="<?= (int) ($heroMobileSet['width'] ?? 1080) ?>"
                                height="<?= (int) ($heroMobileSet['height'] ?? 608) ?>"
                                loading="eager"
                                fetchpriority="high"
                                decoding="async"
                            >
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="ia-home-hero-overlay"></div>
                </div>
                <div class="ia-home-hero-head">
                    <span class="ia-home-hero-accent" aria-hidden="true"></span>
                    <h1 class="ia-home-hero-title">Все автомобили Таджикистана<br>в одном месте</h1>
                </div>
                <div class="ia-home-hero-aside" aria-hidden="true"></div>
            </div>
        </section>

        <div class="ia-home-hero-actions ia-home-hero-actions--dock">
                <div class="ia-home-hero-search">
                    <label class="visually-hidden" for="heroMobileQ">Поиск</label>
                    <span class="ia-home-hero-search-ico" aria-hidden="true"><i class="bi bi-search"></i></span>
                    <input
                        type="search"
                        name="q"
                        id="heroMobileQ"
                        class="ia-home-hero-search-input"
                        placeholder="Поиск по бренду, модели, городу…"
                        value="<?= ia_h($prefillQ) ?>"
                        autocomplete="off"
                        enterkeyhint="search"
                    >
                    <button
                        type="button"
                        class="ia-home-hero-filter-toggle"
                        id="iaHomeFilterToggle"
                        aria-label="Показать фильтры"
                        aria-expanded="false"
                        aria-controls="iaHomeMobileFilters"
                    >
                        <i class="bi bi-sliders" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="ia-home-filters-panel" id="iaHomeMobileFilters" aria-label="Фильтры поиска" hidden>
                    <div class="ia-home-filters-grid">
                        <div class="ia-home-filter-field ia-home-filter-field--select">
                            <span class="ia-home-filter-ico" aria-hidden="true"><i class="bi bi-car-front"></i></span>
                            <select name="brand_id" id="heroMobileBrand" class="ia-home-filter-control" aria-label="Бренд">
                                <option value="0">Бренд</option>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?= (int) $b['id'] ?>"><?= ia_h((string) $b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="bi bi-chevron-down ia-home-filter-chev" aria-hidden="true"></i>
                        </div>
                        <div class="ia-home-filter-field ia-home-filter-field--select">
                            <span class="ia-home-filter-ico" aria-hidden="true"><i class="bi bi-truck"></i></span>
                            <select name="model_id" id="heroMobileModel" class="ia-home-filter-control" aria-label="Модель" disabled>
                                <option value="0">Модель</option>
                            </select>
                            <i class="bi bi-chevron-down ia-home-filter-chev" aria-hidden="true"></i>
                        </div>
                        <div class="ia-home-filter-field">
                            <span class="ia-home-filter-ico" aria-hidden="true"><i class="bi bi-currency-dollar"></i></span>
                            <input type="number" name="price_min" id="heroMobilePmin" class="ia-home-filter-control" min="0" step="1000" placeholder="Цена от" inputmode="numeric" aria-label="Цена от">
                        </div>
                        <div class="ia-home-filter-field">
                            <span class="ia-home-filter-ico" aria-hidden="true"><i class="bi bi-currency-dollar"></i></span>
                            <input type="number" name="price_max" id="heroMobilePmax" class="ia-home-filter-control" min="0" step="1000" placeholder="Цена до" inputmode="numeric" aria-label="Цена до">
                        </div>
                        <div class="ia-home-filter-field ia-home-filter-field--select">
                            <span class="ia-home-filter-ico" aria-hidden="true"><i class="bi bi-calendar3"></i></span>
                            <?php
                            $iaYearSelectName = 'year';
                            $iaYearSelectId = 'heroMobileYear';
                            $iaYearSelectClass = 'ia-home-filter-control';
                            $iaYearSelectValue = (string) ($_GET['year'] ?? '');
                            $iaYearSelectEmpty = 'Все';
                            $iaYearSelectAriaLabel = 'Год выпуска';
                            require IA_ROOT . '/includes/partials/year-select.php';
                            ?>
                            <i class="bi bi-chevron-down ia-home-filter-chev" aria-hidden="true"></i>
                        </div>
                        <div class="ia-home-filter-field ia-home-filter-field--city">
                            <span class="ia-home-filter-ico" aria-hidden="true"><i class="bi bi-geo-alt"></i></span>
                            <?php
                            if (!function_exists('ia_tj_city_normalize')) {
                                require_once IA_ROOT . '/includes/tj_cities.php';
                            }
                            $iaCitySelectName = 'city';
                            $iaCitySelectId = 'heroMobileCity';
                            $iaCitySelectClass = 'ia-home-filter-control';
                            $iaCitySelectValue = ia_tj_city_normalize((string) ($_GET['city'] ?? ''));
                            $iaCitySelectEmpty = 'Все';
                            require IA_ROOT . '/includes/partials/city-select.php';
                            ?>
                        </div>
                    </div>
                    <button type="submit" class="ia-home-filters-submit">
                        <span>Найти авто</span>
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </button>
                </div>
        </div>
    </form>
</div>
