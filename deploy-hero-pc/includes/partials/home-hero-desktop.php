<?php

declare(strict_types=1);

/** Desktop hero (≥992px) — макет InnovaAuto с фоном IMG/bANER.png */
?>
<section class="ia-hero-pc d-none d-lg-block" aria-label="Поиск автомобилей">
    <div class="container ia-container ia-hero-pc-wrap">
        <div class="ia-hero-pc-shell">
            <div class="ia-hero-pc-bg" aria-hidden="true"></div>

            <div class="ia-hero-pc-body">
                <div class="ia-hero-pc-copy">
                    <p class="ia-hero-pc-badge">
                        <span class="ia-hero-pc-badge-ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M12 2l2.39 4.84L20 7.9l-3.8 3.7.9 5.25L12 14.77 6.9 17.85l.9-5.25L4 7.9l5.61-1.06L12 2z"/></svg>
                        </span>
                        <span>№1 автомаркетплейс в Таджикистане</span>
                    </p>
                    <h1 class="ia-hero-pc-title">Все автомобили Таджикистана<br>в одном месте</h1>
                    <p class="ia-hero-pc-lead">Покупайте, продавайте и находите автомобили быстро, удобно и безопасно.</p>
                    <ul class="ia-hero-pc-features">
                        <li class="ia-hero-pc-feature">
                            <span class="ia-hero-pc-feature-ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                            </span>
                            <span>Проверенные продавцы</span>
                        </li>
                        <li class="ia-hero-pc-feature">
                            <span class="ia-hero-pc-feature-ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C8.01 14 6 11.99 6 9.5S8.01 5 10.5 5 15 7.01 15 9.5 12.99 14 10.5 14z"/></svg>
                            </span>
                            <span>Удобный поиск</span>
                        </li>
                        <li class="ia-hero-pc-feature">
                            <span class="ia-hero-pc-feature-ico" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M12 1 3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                            </span>
                            <span>Безопасные сделки</span>
                        </li>
                    </ul>
                </div>

                <form class="ia-hero-pc-search" method="get" action="<?= ia_h($catalogUrl) ?>">
                    <div class="ia-hero-pc-search-fields">
                        <div class="ia-hero-pc-field">
                            <label class="ia-hero-pc-label" for="heroBrand">Бренд</label>
                            <select name="brand_id" id="heroBrand" class="ia-hero-pc-control">
                                <option value="0">Все бренды</option>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?= (int) $b['id'] ?>"><?= ia_h((string) $b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ia-hero-pc-field">
                            <label class="ia-hero-pc-label" for="heroModel">Модель</label>
                            <select name="model_id" id="heroModel" class="ia-hero-pc-control" disabled>
                                <option value="0">Все модели</option>
                            </select>
                        </div>
                        <div class="ia-hero-pc-field">
                            <label class="ia-hero-pc-label" for="heroPmin">Цена от</label>
                            <input type="number" name="price_min" id="heroPmin" class="ia-hero-pc-control" min="0" step="1000" placeholder="0" inputmode="numeric">
                        </div>
                        <div class="ia-hero-pc-field">
                            <label class="ia-hero-pc-label" for="heroPmax">Цена до</label>
                            <input type="number" name="price_max" id="heroPmax" class="ia-hero-pc-control" min="0" step="1000" placeholder="∞" inputmode="numeric">
                        </div>
                        <div class="ia-hero-pc-field">
                            <label class="ia-hero-pc-label" for="heroYear">Год</label>
                            <?php
                            $iaYearSelectName = 'year';
                            $iaYearSelectId = 'heroYear';
                            $iaYearSelectClass = 'ia-hero-pc-control';
                            $iaYearSelectValue = (string) ($_GET['year'] ?? '');
                            $iaYearSelectEmpty = 'Все';
                            require IA_ROOT . '/includes/partials/year-select.php';
                            ?>
                        </div>
                        <div class="ia-hero-pc-field">
                            <label class="ia-hero-pc-label" for="heroCity">Город</label>
                            <?php
                            require_once IA_ROOT . '/includes/tj_cities.php';
                            $iaCitySelectName = 'city';
                            $iaCitySelectId = 'heroCity';
                            $iaCitySelectClass = 'ia-hero-pc-control';
                            $iaCitySelectValue = ia_tj_city_normalize((string) ($_GET['city'] ?? ''));
                            $iaCitySelectEmpty = '— любой —';
                            require IA_ROOT . '/includes/partials/city-select.php';
                            ?>
                        </div>
                    </div>
                    <div class="ia-hero-pc-search-action">
                        <button type="submit" class="ia-hero-pc-submit">
                            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C8.01 14 6 11.99 6 9.5S8.01 5 10.5 5 15 7.01 15 9.5 12.99 14 10.5 14z"/></svg>
                            <span>Поиск</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
