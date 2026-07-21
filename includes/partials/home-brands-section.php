<?php

declare(strict_types=1);

/** Популярные бренды: premium slider (ПК + телефон). Стили внутри — для хостинга. */

$dataFile = IA_ROOT . '/includes/partials/home-brands-data.php';
if (!is_file($dataFile)) {
    $dataFile = IA_ROOT . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'home-brands-data.php';
}
require_once $dataFile;

try {
    if (!isset($popularBrandsList) || !is_array($popularBrandsList) || $popularBrandsList === []) {
        $popularBrandsList = ia_home_popular_brands_list($pdo ?? ia_db());
    }
} catch (Throwable $e) {
    $popularBrandsList = ia_home_popular_brands_fallback();
}

$catalogUrl = isset($catalogUrl) ? (string) $catalogUrl : ia_public_url('catalog.php');

if ($popularBrandsList === []) {
    $popularBrandsList = ia_home_popular_brands_fallback();
}

$brandCardPartial = IA_ROOT . '/includes/partials/home-brand-card.php';
if (!is_file($brandCardPartial)) {
    $brandCardPartial = IA_ROOT . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'home-brand-card.php';
}

$renderBrandCards = static function () use ($popularBrandsList, $brandCardPartial): void {
    if (!is_file($brandCardPartial)) {
        return;
    }
    foreach ($popularBrandsList as $b) {
        require $brandCardPartial;
    }
};

$brandsJsDev = function_exists('ia_assets_dev_mode') && ia_assets_dev_mode();
$brandsJsFile = IA_ROOT . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js'
    . DIRECTORY_SEPARATOR . ($brandsJsDev ? 'home-brands-slider.js' : 'home-brands-slider.min.js');
if (!$brandsJsDev && !is_readable($brandsJsFile)) {
    $brandsJsFile = IA_ROOT . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'home-brands-slider.js';
}
?>
<!-- ia-brands-block-v17 -->
<section class="py-3 ia-page-section ia-brands-section ia-brands-section--premium" data-ia-brands-slider aria-label="Популярные бренды">
    <style>
        .ia-brands-section--premium {
            --ia-brands-card-w: 4.35rem;
            --ia-brands-mark: 2.85rem;
            --ia-brands-logo: 1.55rem;
            --ia-brands-label: 0.62rem;
            --ia-brands-count: 0.54rem;
            border-top: 1px solid rgba(148, 163, 184, 0.22);
            border-bottom: 1px solid rgba(148, 163, 184, 0.16);
            background:
                radial-gradient(ellipse 80% 120% at 50% -20%, rgba(59, 130, 246, 0.07), transparent 58%),
                linear-gradient(180deg, rgba(248, 250, 255, 0.92) 0%, rgba(255, 255, 255, 0.55) 100%);
        }
        .ia-brands-section--premium .ia-brands-head h2 {
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.1em;
        }
        .ia-brands-section--premium .ia-brands-more {
            font-size: 0.8rem;
            font-weight: 600;
            color: #2563eb;
            text-decoration: none;
        }
        .ia-brands-section--premium .ia-brands-more:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }
        .ia-brands-slider-wrap {
            position: relative;
            max-width: 100%;
            padding: 0.2rem 0 0.1rem;
        }
        .ia-brands-section--premium .ia-brands-slider {
            overflow: hidden !important;
            width: 100%;
            touch-action: pan-x;
            cursor: grab;
            -webkit-mask-image: linear-gradient(90deg, transparent 0, #000 2.5%, #000 97.5%, transparent 100%);
            mask-image: linear-gradient(90deg, transparent 0, #000 2.5%, #000 97.5%, transparent 100%);
        }
        .ia-brands-section--premium .ia-brands-slider__inner {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            align-items: flex-start;
            width: max-content;
            min-width: 0;
            will-change: transform;
            /* CSS fallback until JS starts */
            animation: ia-brands-marquee 32s linear infinite;
        }
        .ia-brands-section--premium .ia-brands-slider__inner.is-js-marquee {
            animation: none !important;
        }
        .ia-brands-section--premium .ia-brands-slider.is-dragging {
            cursor: grabbing;
        }
        @keyframes ia-brands-marquee {
            from { transform: translate3d(0, 0, 0); }
            to { transform: translate3d(-50%, 0, 0); }
        }
        .ia-brands-section--premium .ia-brands-slider__track {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            align-items: flex-start;
            gap: 0.65rem;
            width: max-content !important;
            min-width: 0 !important;
            max-width: none !important;
            padding: 0.15rem 0.65rem 0.35rem;
            box-sizing: border-box;
            flex: 0 0 auto;
        }
        .ia-brands-section--premium .ia-brand-card {
            display: flex !important;
            flex: 0 0 auto;
            flex-direction: column;
            align-items: center;
            width: var(--ia-brands-card-w);
            text-decoration: none !important;
            text-align: center;
            color: var(--ia-text, #111827);
            transition: transform 0.22s ease, color 0.18s ease;
        }
        .ia-brands-section--premium .ia-brand-card:hover {
            transform: translateY(-3px);
            color: #1d4ed8;
        }
        .ia-brands-section--premium .ia-brand-card-mark {
            display: flex;
            align-items: center;
            justify-content: center;
            width: var(--ia-brands-mark);
            height: var(--ia-brands-mark);
            margin: 0 0 0.32rem;
            border-radius: 999px;
            background: linear-gradient(165deg, #ffffff 0%, #f1f5f9 100%);
            border: 1px solid rgba(148, 163, 184, 0.28);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.95),
                0 10px 24px rgba(15, 23, 42, 0.08);
            overflow: hidden;
            transition: box-shadow 0.22s ease, border-color 0.22s ease, transform 0.22s ease;
        }
        .ia-brands-section--premium .ia-brand-card:hover .ia-brand-card-mark {
            border-color: rgba(59, 130, 246, 0.35);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.98),
                0 14px 28px rgba(37, 99, 235, 0.14);
            transform: scale(1.04);
        }
        .ia-brands-section--premium .ia-brand-card-logo {
            width: var(--ia-brands-logo);
            height: var(--ia-brands-logo);
            object-fit: contain;
            display: block;
        }
        .ia-brands-section--premium .ia-brand-card-fallback {
            font-size: 1rem;
            font-weight: 700;
            color: #334155;
        }
        .ia-brands-section--premium .ia-brand-card-label {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
            width: 100%;
            min-height: 2.1em;
            font-size: var(--ia-brands-label);
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.01em;
            word-break: break-word;
            white-space: normal;
        }
        .ia-brands-section--premium .ia-brand-card-count {
            display: block;
            margin-top: 0.14rem;
            width: 100%;
            font-size: var(--ia-brands-count);
            font-weight: 500;
            line-height: 1.2;
            color: #64748b;
            white-space: nowrap;
        }
        @media (min-width: 992px) {
            .ia-brands-section--premium {
                --ia-brands-card-w: 7.75rem;
                --ia-brands-mark: 5.25rem;
                --ia-brands-logo: 3rem;
                --ia-brands-label: 0.88rem;
                --ia-brands-count: 0.76rem;
                padding-top: 1.35rem !important;
                padding-bottom: 1.45rem !important;
            }
            .ia-brands-section--premium .ia-brands-head h2 {
                font-size: 0.95rem;
            }
            .ia-brands-section--premium .ia-brands-more {
                font-size: 0.88rem;
            }
            .ia-brands-section--premium .ia-brands-slider__track {
                gap: 1.35rem;
                padding-left: 0.85rem;
                padding-right: 0.85rem;
            }
        }
        @media (min-width: 1200px) {
            .ia-brands-section--premium {
                --ia-brands-card-w: 8.35rem;
                --ia-brands-mark: 5.65rem;
                --ia-brands-logo: 3.25rem;
            }
        }
        @media (max-width: 991.98px) {
            .ia-brands-section--premium .ia-brands-slider__inner:not(.is-js-marquee) {
                animation-duration: 24s;
            }
        }
        @media (max-width: 575.98px) {
            .ia-brands-section--premium {
                --ia-brands-card-w: 4rem;
                --ia-brands-mark: 2.65rem;
                --ia-brands-logo: 1.45rem;
                --ia-brands-label: 0.58rem;
                --ia-brands-count: 0.5rem;
            }
            .ia-brands-section--premium .ia-brands-slider__track {
                gap: 0.55rem;
                padding-left: 0.55rem;
                padding-right: 0.55rem;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .ia-brands-slider__inner { animation: none !important; }
            .ia-brands-section--premium .ia-brand-card,
            .ia-brands-section--premium .ia-brand-card-mark {
                transition: none;
            }
        }
        html[data-bs-theme='dark'] .ia-brands-section--premium {
            background:
                radial-gradient(ellipse 80% 120% at 50% -20%, rgba(59, 130, 246, 0.12), transparent 58%),
                linear-gradient(180deg, rgba(15, 23, 42, 0.72) 0%, rgba(2, 6, 23, 0.35) 100%);
            border-color: rgba(71, 85, 105, 0.45);
        }
        html[data-bs-theme='dark'] .ia-brands-section--premium .ia-brand-card { color: #f8fafc; }
        html[data-bs-theme='dark'] .ia-brands-section--premium .ia-brand-card-mark {
            background: radial-gradient(circle at 30% 18%, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0.05) 70%);
            border-color: rgba(148, 163, 184, 0.28);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.05), 0 10px 22px rgba(2, 8, 23, 0.35);
        }
        html[data-bs-theme='dark'] .ia-brands-section--premium .ia-brand-card-count { color: rgba(148, 163, 184, 0.92); }
        html[data-bs-theme='dark'] .ia-brands-section--premium .ia-brand-card-logo { filter: brightness(0) invert(1); }
    </style>
    <div class="container ia-container">
        <div class="ia-brands-head d-flex flex-nowrap justify-content-between align-items-center gap-2 mb-2">
            <h2 class="h5 mb-0 text-secondary text-uppercase letter-spacing flex-shrink-1">Популярные бренды</h2>
            <a class="small ia-brands-more flex-shrink-0 text-nowrap" href="<?= ia_h($catalogUrl) ?>">Все бренды →</a>
        </div>
    </div>
    <div class="ia-brands-slider-wrap">
        <div class="ia-brands-slider" aria-label="Популярные бренды">
            <div class="ia-brands-slider__inner">
                <div class="ia-brands-slider__track">
                    <?php $renderBrandCards(); ?>
                </div>
                <div class="ia-brands-slider__track" aria-hidden="true">
                    <?php $renderBrandCards(); ?>
                </div>
            </div>
        </div>
    </div>
    <?php if (is_readable($brandsJsFile)): ?>
    <script><?php readfile($brandsJsFile); ?></script>
    <?php endif; ?>
</section>
