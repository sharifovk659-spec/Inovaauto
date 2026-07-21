<?php

declare(strict_types=1);

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';

$pdo = ia_db();
$pageTitle = 'Блог';
$iaBodyExtraClass = 'ia-page-blog';
$intro = trim(ia_site_setting_get($pdo, 'page_blog_intro', ''));

/** @var list<array{title:string,tag:string,date:string,excerpt:string}> */
$posts = [
    [
        'title' => 'Как безопасно купить автомобиль с пробегом',
        'tag' => 'Покупка',
        'date' => '2026-03-18',
        'excerpt' => 'Проверка истории, VIN, осмотр и документы — короткий чек-лист перед сделкой на InnovaAuto.',
    ],
    [
        'title' => 'Фото объявления: что снимать в первую очередь',
        'tag' => 'Продавцам',
        'date' => '2026-03-25',
        'excerpt' => 'Салон, кузов по кругу, пробег и документы — качественные фото повышают доверие покупателей.',
    ],
    [
        'title' => 'Переписка с покупателем: этика и безопасность',
        'tag' => 'Сервис',
        'date' => '2026-04-02',
        'excerpt' => 'Общайтесь через встроенные сообщения, не переходите на подозрительные ссылки и фиксируйте договорённости.',
    ],
    [
        'title' => 'Тренды рынка: электромобили и кроссоверы',
        'tag' => 'Рынок',
        'date' => '2026-04-14',
        'excerpt' => 'Что ищут пользователи в каталоге и как правильно указать комплектацию и состояние батареи для EV.',
    ],
    [
        'title' => 'Модерация объявлений: зачем она нужна',
        'tag' => 'Платформа',
        'date' => '2026-04-22',
        'excerpt' => 'Мы проверяем объявления, чтобы в каталоге были только реальные предложения без спама и обмана.',
    ],
    [
        'title' => 'Подготовка авто к продаже за один день',
        'tag' => 'Продавцам',
        'date' => '2026-05-01',
        'excerpt' => 'Мойка, мелкий косметический ремонт и честное описание состояния помогают продать быстрее.',
    ],
];

require IA_ROOT . '/includes/partials/site-header.php';
?>

<section class="ia-blog-hero ia-page-section">
    <div class="container ia-container">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <p class="small text-uppercase letter-spacing text-secondary mb-2">Материалы и советы</p>
                <h1 class="display-6 fw-bold mb-3">Блог InnovaAuto</h1>
                <p class="lead text-secondary mb-0"><?php if ($intro !== ''): ?><?= nl2br(ia_h($intro)) ?><?php else: ?>Статьи о покупке и продаже автомобилей, безопасных сделках и работе с платформой.<?php endif; ?></p>
            </div>
            <div class="col-lg-4">
                <div class="ia-blog-stat-panel rounded-3 p-4">
                    <div class="fw-semibold mb-3">Полезное рядом</div>
                    <ul class="list-unstyled small mb-0">
                        <li class="mb-2"><a href="<?= ia_h(ia_public_url('catalog.php')) ?>" class="text-decoration-none">Каталог объявлений</a></li>
                        <li class="mb-2"><a href="<?= ia_h(ia_public_url('contact.php')) ?>" class="text-decoration-none">Контакты и поддержка</a></li>
                        <li class="mb-0"><a href="<?= ia_h(ia_public_url('about.php')) ?>" class="text-decoration-none">О компании и услугах</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 ia-page-section border-top border-secondary border-opacity-25">
    <div class="container ia-container">
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4">
            <h2 class="h5 mb-0">Последние публикации</h2>
            <span class="small text-secondary"><?= count($posts) ?> материалов</span>
        </div>
        <div class="row g-4">
            <?php foreach ($posts as $post): ?>
                <div class="col-md-6 col-xl-4">
                    <article class="ia-blog-card d-flex flex-column h-100">
                        <div class="ia-blog-card-top" aria-hidden="true"></div>
                        <div class="p-4 flex-grow-1 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                <span class="badge rounded-pill ia-blog-tag"><?= ia_h($post['tag']) ?></span>
                                <time class="small text-secondary" datetime="<?= ia_h($post['date']) ?>"><?= ia_h($post['date']) ?></time>
                            </div>
                            <h3 class="h5 fw-semibold mb-2"><?= ia_h($post['title']) ?></h3>
                            <p class="small text-secondary mb-4 flex-grow-1"><?= ia_h($post['excerpt']) ?></p>
                            <span class="ia-blog-read-more small fw-semibold">Скоро на сайте</span>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="small text-secondary text-center mt-5 mb-0">
            Редакция обновляет раздел. Тексты можно связать с вашей контент-стратегией для клиента.
        </p>
    </div>
</section>

<?php require IA_ROOT . '/includes/partials/why-innovaauto-section.php'; ?>

<?php require IA_ROOT . '/includes/partials/site-footer.php'; ?>
