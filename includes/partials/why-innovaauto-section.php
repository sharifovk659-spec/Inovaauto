<?php

declare(strict_types=1);

/** Блок «Почему InnovaAuto» — используется на странице блога. */

/** @var list<array{icon:string,title:string,text:string}> */
$iaWhyItems = [
    [
        'icon' => 'bi-shield-check',
        'title' => 'Безопасно',
        'text' => 'Проверка объявлений, модерация контента и защищённые сессии пользователей.',
    ],
    [
        'icon' => 'bi-lightning-charge',
        'title' => 'Быстро',
        'text' => 'Удобный поиск по бренду, модели, цене и году с быстрым откликом.',
    ],
    [
        'icon' => 'bi-chat-dots',
        'title' => 'Легко',
        'text' => 'Размещение объявления за несколько минут и переписка с покупателями в чате.',
    ],
];
?>
<section class="ia-blog-why ia-page-section border-top border-secondary border-opacity-25" aria-labelledby="iaBlogWhyTitle">
    <div class="container ia-container">
        <h2 id="iaBlogWhyTitle" class="ia-blog-why-title">Почему InnovaAuto</h2>
        <div class="row g-3 g-lg-4 ia-blog-why-grid">
            <?php foreach ($iaWhyItems as $item): ?>
                <div class="col-12 col-md-4">
                    <article class="ia-blog-why-card h-100">
                        <span class="ia-blog-why-card-icon" aria-hidden="true">
                            <i class="bi <?= ia_h($item['icon']) ?>"></i>
                        </span>
                        <h3 class="ia-blog-why-card-title"><?= ia_h($item['title']) ?></h3>
                        <p class="ia-blog-why-card-text mb-0"><?= ia_h($item['text']) ?></p>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
