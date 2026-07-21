<?php

declare(strict_types=1);

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';

$pdo = ia_db();
$siteName = ia_site_setting_get($pdo, 'site_name', 'InnovaAuto');
$pageTitle = 'О нас';

require IA_ROOT . '/includes/partials/site-header.php';
?>

<div class="ia-about-page">
    <section class="ia-about-hero" aria-labelledby="iaAboutTitle">
        <div class="ia-about-hero-bg" aria-hidden="true"></div>
        <div class="container ia-container">
            <div class="ia-about-hero-grid">
                <div class="ia-about-hero-copy">
                    <span class="ia-about-kicker"><?= ia_h($siteName) ?> &middot; маркетплейс автомобилей</span>
                    <h1 id="iaAboutTitle" class="ia-about-hero-title">О нас</h1>
                    <p class="ia-about-hero-lead">
                        <?= ia_h($siteName) ?> — цифровая площадка для покупки и продажи автомобилей в Таджикистане.
                        Мы соединяем частных владельцев и дилеров в одном удобном сервисе, где каждое объявление
                        сопровождается фотографиями, характеристиками и прямым контактом с продавцом.
                    </p>
                </div>
                <aside class="ia-about-hero-aside" aria-label="Ключевые преимущества">
                    <ul class="ia-about-hero-points list-unstyled mb-0">
                        <li><i class="bi bi-check2-circle" aria-hidden="true"></i> Проверенная модерация объявлений</li>
                        <li><i class="bi bi-check2-circle" aria-hidden="true"></i> Удобный поиск и сравнение авто</li>
                        <li><i class="bi bi-check2-circle" aria-hidden="true"></i> Размещение за несколько минут</li>
                    </ul>
                </aside>
            </div>
        </div>
    </section>

    <section class="ia-about-body py-5 ia-page-section">
        <div class="container ia-container ia-about-container">
            <article class="ia-about-panel ia-about-panel--mission">
                <p class="ia-about-panel-label">Наша миссия</p>
                <h2 class="ia-about-panel-title">Делаем автомобильный рынок понятным и доступным</h2>
                <p class="ia-about-panel-text mb-0">
                    Мы верим, что покупка или продажа машины не должна занимать недели. Наша цель — убрать лишние
                    барьеры: дать покупателю актуальную информацию, а продавцу — инструменты для быстрого и честного
                    размещения. <?= ia_h($siteName) ?> развивается вместе с рынком Таджикистана и учитывает реальные
                    потребности пользователей.
                </p>
            </article>

            <div class="row g-4 ia-about-audience">
                <div class="col-lg-6">
                    <article class="ia-about-panel ia-about-panel--card h-100">
                        <span class="ia-about-icon ia-about-icon--buy" aria-hidden="true"><i class="bi bi-search"></i></span>
                        <h2 class="ia-about-panel-title h5">Для покупателей</h2>
                        <p class="ia-about-panel-text">Найдите подходящий автомобиль без лишних звонков и сомнительных объявлений.</p>
                        <ul class="ia-about-checklist">
                            <li>Каталог с фильтрами по марке, модели, цене и городу</li>
                            <li>Детальные карточки: фото, характеристики, описание, контакты</li>
                            <li>Избранное, сравнение и уведомления о новых предложениях</li>
                            <li>Просмотр автомобилей рядом с вами на интерактивной карте</li>
                        </ul>
                    </article>
                </div>
                <div class="col-lg-6">
                    <article class="ia-about-panel ia-about-panel--card h-100">
                        <span class="ia-about-icon ia-about-icon--sell" aria-hidden="true"><i class="bi bi-megaphone"></i></span>
                        <h2 class="ia-about-panel-title h5">Для продавцов</h2>
                        <p class="ia-about-panel-text">Продавайте быстрее — с прозрачными условиями и удобным личным кабинетом.</p>
                        <ul class="ia-about-checklist">
                            <li>Публикация объявления с телефона или компьютера за минуты</li>
                            <li>Управление статусом, редактирование и архив в одном месте</li>
                            <li>VIP-размещение для выделения объявления в каталоге</li>
                            <li>Честные тарифы без скрытых платежей</li>
                        </ul>
                    </article>
                </div>
            </div>

            <section class="ia-about-trust" aria-labelledby="iaAboutTrustTitle">
                <h2 id="iaAboutTrustTitle" class="ia-about-section-title text-center">Почему выбирают <?= ia_h($siteName) ?></h2>
                <p class="ia-about-section-lead text-center">Мы строим сервис, которым удобно пользоваться каждый день — и с телефона, и с компьютера.</p>
                <div class="row g-3 g-lg-4">
                    <div class="col-sm-6 col-xl-3">
                        <article class="ia-about-trust-card h-100">
                            <i class="bi bi-shield-check" aria-hidden="true"></i>
                            <h3 class="h6">Надёжность</h3>
                            <p>Модерация контента и внимание к качеству объявлений на платформе.</p>
                        </article>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <article class="ia-about-trust-card h-100">
                            <i class="bi bi-phone" aria-hidden="true"></i>
                            <h3 class="h6">Удобство</h3>
                            <p>Адаптивный интерфейс: комфортная работа на смартфоне, планшете и ПК.</p>
                        </article>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <article class="ia-about-trust-card h-100">
                            <i class="bi bi-geo-alt" aria-hidden="true"></i>
                            <h3 class="h6">Локальный рынок</h3>
                            <p>Фокус на Таджикистане — Душанбе, областные центры и города страны.</p>
                        </article>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <article class="ia-about-trust-card h-100">
                            <i class="bi bi-graph-up-arrow" aria-hidden="true"></i>
                            <h3 class="h6">Развитие</h3>
                            <p>Регулярные обновления функций на основе отзывов пользователей.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="ia-about-cta" aria-labelledby="iaAboutCtaTitle">
                <div class="ia-about-cta-inner text-center">
                    <h2 id="iaAboutCtaTitle" class="ia-about-panel-title mb-2">Готовы начать?</h2>
                    <p class="ia-about-cta-text mb-4">
                        Откройте каталог или разместите объявление — первый шаг займёт всего несколько минут.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-2 gap-md-3">
                        <a class="btn ia-btn-accent btn-lg px-4" href="<?= ia_h(ia_public_url('catalog.php')) ?>">Смотреть каталог</a>
                        <a class="btn btn-outline-primary btn-lg px-4" href="<?= ia_h(ia_public_url('add-listing.php')) ?>">Продать автомобиль</a>
                    </div>
                </div>
            </section>
        </div>
    </section>
</div>

<?php require IA_ROOT . '/includes/partials/site-footer.php'; ?>
