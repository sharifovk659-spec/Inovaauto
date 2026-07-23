<?php

declare(strict_types=1);

/** @var array<string, mixed> $compareAnalysis */
/** @var string $compareAiIdsKey */

if (empty($compareAnalysis) || empty($compareAnalysis['cars'])) {
    return;
}

$winnerId = isset($compareAnalysis['overall_winner']) ? (int) $compareAnalysis['overall_winner'] : 0;
$isCloseResult = !empty($compareAnalysis['is_close_result']);
$winnerCar = null;
foreach ($compareAnalysis['cars'] as $car) {
    if ((int) ($car['id'] ?? 0) === $winnerId) {
        $winnerCar = $car;
        break;
    }
}

$winnerScore = (float) ($compareAnalysis['scores'][$winnerId]['total'] ?? 0);
$winnerReason = trim((string) ($compareAnalysis['overall_winner_reason'] ?? ''));
$summaryParagraphs = is_array($compareAnalysis['summary_paragraphs'] ?? null) ? $compareAnalysis['summary_paragraphs'] : [];
$uiCategories = is_array($compareAnalysis['ui_categories'] ?? null) ? $compareAnalysis['ui_categories'] : [];
$scorePct = max(0, min(100, (int) round($winnerScore)));

$topCars = [];
$scoreRows = $compareAnalysis['scores'] ?? [];
if ($scoreRows !== []) {
    uasort($scoreRows, static fn (array $a, array $b): int => ((float) ($b['total'] ?? 0)) <=> ((float) ($a['total'] ?? 0)));
    foreach (array_keys($scoreRows) as $cid) {
        foreach ($compareAnalysis['cars'] as $car) {
            if ((int) ($car['id'] ?? 0) === (int) $cid) {
                $topCars[] = $car;
                break;
            }
        }
        if (count($topCars) >= 2) {
            break;
        }
    }
}

$catIcons = [
    'price' => 'bi-tag-fill',
    'year' => 'bi-calendar3',
    'mileage' => 'bi-speedometer2',
    'engine_bigger' => 'bi-lightning-charge-fill',
    'city' => 'bi-buildings',
    'family' => 'bi-people-fill',
    'rough_roads' => 'bi-signpost-split-fill',
];

$loadingSteps = [
    'Сравниваем цену',
    'Проверяем год и пробег',
    'Оцениваем двигатель и привод',
    'Формируем рекомендацию',
];

$compareAiIdsKey = isset($compareAiIdsKey) ? (string) $compareAiIdsKey : '';
$iaBotSvgUid = 'iacmpbot';
?>

<div
    class="ia-compare-ai-shell"
    id="ia-compare-ai-root"
    data-compare-key="<?= ia_h($compareAiIdsKey) ?>"
>
    <button
        type="button"
        class="ia-compare-ai-cta d-none d-lg-flex"
        id="ia-compare-ai-cta-desktop"
        data-ia-compare-ai-toggle
        aria-expanded="false"
        aria-controls="ia-compare-ai-panel"
    >
        <span class="ia-compare-ai-cta-icon ia-compare-ai-cta-icon--open" aria-hidden="true">
            <span class="ia-compare-ai-bot-icon-wrap">
                <?php $iaBotSvgSize = 34; require IA_ROOT . '/includes/partials/compare-ai-bot-icon.php'; ?>
            </span>
        </span>
        <span class="ia-compare-ai-cta-icon ia-compare-ai-cta-icon--close" aria-hidden="true" hidden>
            <i class="bi bi-chevron-up"></i>
        </span>
        <span class="ia-compare-ai-cta-text">
            <span class="ia-compare-ai-toggle-label ia-compare-ai-toggle-label--open">Получить AI-анализ</span>
            <span class="ia-compare-ai-toggle-label ia-compare-ai-toggle-label--close" hidden>Скрыть AI-анализ</span>
            <small class="ia-compare-ai-cta-sub">Узнайте, какой автомобиль подходит вам больше</small>
        </span>
        <span class="ia-compare-ai-cta-sparkle" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M9.5 2.5 11 8 5.5 11.5 12 14l5.5-2.5L13 8l1.5-5.5z"/></svg>
        </span>
    </button>

    <div class="ia-compare-ai-bot-wrap d-lg-none">
        <span class="ia-compare-ai-bot-tooltip" id="ia-compare-ai-bot-tooltip" role="tooltip">Сравнить с помощью AI</span>
        <button
            type="button"
            class="ia-compare-ai-bot-fab"
            id="ia-compare-ai-bot-fab"
            data-ia-compare-ai-toggle
            aria-expanded="false"
            aria-controls="ia-compare-ai-panel"
            aria-label="Получить AI-анализ"
        >
            <span class="ia-compare-ai-bot-fab-icon ia-compare-ai-bot-fab-icon--open" aria-hidden="true">
                <span class="ia-compare-ai-bot-icon-wrap ia-compare-ai-bot-icon-wrap--fab">
                    <?php $iaBotSvgSize = 40; require IA_ROOT . '/includes/partials/compare-ai-bot-icon.php'; ?>
                </span>
            </span>
            <span class="ia-compare-ai-bot-fab-ripple" aria-hidden="true"></span>
            <span class="ia-compare-ai-bot-fab-icon ia-compare-ai-bot-fab-icon--close" aria-hidden="true" hidden>
                <i class="bi bi-chevron-up"></i>
            </span>
            <span class="ia-compare-ai-bot-badge">AI</span>
        </button>
    </div>

    <div
        class="ia-compare-ai-panel"
        id="ia-compare-ai-panel"
        hidden
        aria-hidden="true"
    >
        <div class="ia-compare-ai-loading" id="ia-compare-ai-loading" hidden>
            <div class="ia-compare-ai-loading-orbit" aria-hidden="true"></div>
            <p class="ia-compare-ai-loading-title">Анализируем выбранные автомобили...</p>
            <ul class="ia-compare-ai-loading-steps" id="ia-compare-ai-loading-steps">
                <?php foreach ($loadingSteps as $i => $step): ?>
                    <li class="ia-compare-ai-loading-step<?= $i === 0 ? ' is-active' : '' ?>" data-step="<?= (int) $i ?>"><?= ia_h($step) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="ia-compare-ai-content" id="ia-compare-ai-content" hidden>
            <section class="ia-compare-ai" aria-labelledby="ia-compare-ai-title">
                <header class="ia-compare-ai-head">
                    <div class="ia-compare-ai-head-icon" aria-hidden="true">
                        <span class="ia-compare-ai-bot-icon-wrap ia-compare-ai-bot-icon-wrap--head">
                            <?php $iaBotSvgSize = 30; require IA_ROOT . '/includes/partials/compare-ai-bot-icon.php'; ?>
                        </span>
                    </div>
                    <div class="ia-compare-ai-head-text">
                        <h2 id="ia-compare-ai-title" class="ia-compare-ai-title" tabindex="-1">Интеллектуальный анализ InovaAuto</h2>
                        <p class="ia-compare-ai-sub mb-0">Автоматическое сравнение выбранных автомобилей</p>
                    </div>
                    <button type="button" class="ia-compare-ai-close" data-ia-compare-ai-toggle aria-label="Скрыть AI-анализ">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </header>

                <?php if ($isCloseResult && count($topCars) >= 2): ?>
                    <article class="ia-compare-ai-hero ia-compare-ai-hero--dual">
                        <div class="ia-compare-ai-hero-head">
                            <span class="ia-compare-ai-hero-badge">InovaAuto</span>
                            <h3 class="ia-compare-ai-hero-title">Выбор зависит от ваших приоритетов</h3>
                            <p class="ia-compare-ai-hero-lead">Два лидера с близкими баллами по данным объявлений.</p>
                        </div>
                        <div class="ia-compare-ai-hero-duo">
                            <?php foreach (array_slice($topCars, 0, 2) as $heroCar): ?>
                                <?php
                                $hid = (int) ($heroCar['id'] ?? 0);
                                $hScore = (float) ($compareAnalysis['scores'][$hid]['total'] ?? 0);
                                $hPct = max(0, min(100, (int) round($hScore)));
                                $hPhoto = ia_listing_photo_src($heroCar['photo_url'] ?? null);
                                ?>
                                <div class="ia-compare-ai-hero-duo-item">
                                    <a class="ia-compare-ai-hero-photo" href="<?= ia_h(ia_public_url('car.php?id=' . $hid)) ?>">
                                        <img src="<?= ia_h($hPhoto) ?>" alt="" <?= ia_img_perf_attrs(['width' => 240, 'height' => 180]) ?>>
                                    </a>
                                    <div class="ia-compare-ai-hero-duo-body">
                                        <div class="ia-compare-ai-score-ring ia-compare-ai-score-ring--sm" data-score="<?= (int) $hPct ?>" style="--ia-score-pct: 0;" aria-label="Общая оценка <?= ia_h(number_format($hScore, 0, '.', '')) ?> из 100">
                                            <div class="ia-compare-ai-score-ring-inner">
                                                <span class="ia-compare-ai-score-value"><?= ia_h(number_format($hScore, 0, '.', '')) ?></span>
                                                <span class="ia-compare-ai-score-max">/100</span>
                                            </div>
                                        </div>
                                        <p class="ia-compare-ai-score-label">Общая оценка</p>
                                        <h4 class="ia-compare-ai-hero-duo-title"><?= ia_h((string) ($heroCar['title'] ?? '')) ?></h4>
                                        <a class="btn btn-sm ia-btn-accent" href="<?= ia_h(ia_public_url('car.php?id=' . $hid)) ?>">Посмотреть автомобиль</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php elseif ($winnerCar !== null): ?>
                    <?php $winnerPhoto = ia_listing_photo_src($winnerCar['photo_url'] ?? null); ?>
                    <article class="ia-compare-ai-hero">
                        <div class="ia-compare-ai-hero-glow" aria-hidden="true"></div>
                        <div class="ia-compare-ai-hero-grid">
                            <a class="ia-compare-ai-hero-photo" href="<?= ia_h(ia_public_url('car.php?id=' . $winnerId)) ?>">
                                <img src="<?= ia_h($winnerPhoto) ?>" alt="" <?= ia_img_perf_attrs(['width' => 320, 'height' => 240]) ?>>
                            </a>
                            <div class="ia-compare-ai-hero-body">
                                <span class="ia-compare-ai-hero-badge">Рекомендация InovaAuto</span>
                                <h3 class="ia-compare-ai-hero-title"><?= ia_h((string) $winnerCar['title']) ?></h3>
                                <div class="ia-compare-ai-score-block">
                                    <div class="ia-compare-ai-score-ring" data-score="<?= (int) $scorePct ?>" style="--ia-score-pct: 0;" aria-label="Общая оценка <?= ia_h(number_format($winnerScore, 0, '.', '')) ?> из 100">
                                        <div class="ia-compare-ai-score-ring-inner">
                                            <span class="ia-compare-ai-score-value"><?= ia_h(number_format($winnerScore, 0, '.', '')) ?></span>
                                            <span class="ia-compare-ai-score-max">/100</span>
                                        </div>
                                    </div>
                                    <span class="ia-compare-ai-score-label">Общая оценка</span>
                                </div>
                                <?php if ($winnerReason !== ''): ?>
                                    <p class="ia-compare-ai-hero-reason"><?= ia_h($winnerReason) ?></p>
                                <?php endif; ?>
                                <a class="btn ia-btn-accent ia-compare-ai-hero-btn" href="<?= ia_h(ia_public_url('car.php?id=' . $winnerId)) ?>">Посмотреть автомобиль</a>
                            </div>
                        </div>
                    </article>
                <?php endif; ?>

                <div class="ia-compare-ai-categories">
                    <h3 class="ia-compare-ai-section-title">Победители по категориям</h3>
                    <div class="ia-compare-ai-categories-grid">
                        <?php foreach ($uiCategories as $cat): ?>
                            <?php $icon = $catIcons[(string) ($cat['key'] ?? '')] ?? 'bi-check2-circle'; ?>
                            <div class="ia-compare-ai-category-card">
                                <div class="ia-compare-ai-category-icon" aria-hidden="true"><i class="bi <?= ia_h($icon) ?>"></i></div>
                                <div class="ia-compare-ai-category-label"><?= ia_h((string) ($cat['label'] ?? '')) ?></div>
                                <div class="ia-compare-ai-category-value<?= empty($cat['winner_title']) ? ' ia-compare-ai-category-value--missing' : '' ?>">
                                    <?= !empty($cat['winner_title']) ? ia_h((string) $cat['winner_title']) : 'Недостаточно данных' ?>
                                </div>
                                <?php if (!empty($cat['winner_title'])): ?>
                                    <span class="ia-compare-ai-category-check" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ia-compare-ai-goals ia-compare-ai-goals--disabled" aria-disabled="true">
                    <h3 class="ia-compare-ai-section-title">Для чего вам нужен автомобиль?</h3>
                    <p class="ia-compare-ai-goals-note">Персональная рекомендация скоро будет доступна</p>
                    <div class="ia-compare-ai-goals-grid">
                        <?php foreach (['Для города', 'Для семьи', 'Для поездок', 'Для экономии', 'Для комфорта', 'Для плохих дорог'] as $goal): ?>
                            <span class="ia-compare-ai-goal-chip"><?= ia_h($goal) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ia-compare-ai-proscons">
                    <h3 class="ia-compare-ai-section-title">Плюсы и минусы</h3>
                    <div class="ia-compare-ai-proscons-grid">
                        <?php foreach ($compareAnalysis['cars'] as $car): ?>
                            <?php
                            $cid = (int) ($car['id'] ?? 0);
                            $pros = $compareAnalysis['pros'][$cid] ?? [];
                            $cons = $compareAnalysis['cons'][$cid] ?? [];
                            $missing = $compareAnalysis['missing_data'][$cid] ?? [];
                            $thumb = ia_listing_photo_src($car['photo_url'] ?? null);
                            ?>
                            <article class="ia-compare-ai-car-card">
                                <div class="ia-compare-ai-car-head">
                                    <img class="ia-compare-ai-car-thumb" src="<?= ia_h($thumb) ?>" alt="" width="56" height="42" loading="lazy" decoding="async">
                                    <div>
                                        <h4 class="ia-compare-ai-car-title"><?= ia_h((string) ($car['title'] ?? '')) ?></h4>
                                        <div class="ia-compare-ai-car-score">Балл: <strong><?= ia_h(number_format((float) ($compareAnalysis['scores'][$cid]['total'] ?? 0), 1, '.', '')) ?></strong> / 100</div>
                                    </div>
                                </div>
                                <?php if ($pros !== []): ?>
                                    <ul class="ia-compare-ai-list ia-compare-ai-list--pros">
                                        <?php foreach ($pros as $item): ?>
                                            <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span><?= ia_h((string) $item) ?></span></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <?php if ($cons !== []): ?>
                                    <ul class="ia-compare-ai-list ia-compare-ai-list--cons">
                                        <?php foreach ($cons as $item): ?>
                                            <li><i class="bi bi-dash-circle-fill" aria-hidden="true"></i><span><?= ia_h((string) $item) ?></span></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <?php if ($missing !== []): ?>
                                    <div class="ia-compare-ai-missing">
                                        <span class="ia-compare-ai-missing-label">Не указано:</span>
                                        <?= ia_h(implode(', ', array_map('strval', $missing))) ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($summaryParagraphs !== []): ?>
                    <div class="ia-compare-ai-summary">
                        <h3 class="ia-compare-ai-section-title">Вывод InovaAuto</h3>
                        <?php foreach ($summaryParagraphs as $paragraph): ?>
                            <p class="ia-compare-ai-summary-text"><?= ia_h((string) $paragraph) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <p class="ia-compare-ai-disclaimer">
                    Анализ выполнен на основе данных объявлений. Перед покупкой проверьте техническое состояние автомобиля.
                </p>
            </section>
        </div>
    </div>
</div>
