<?php

declare(strict_types=1);

/**
 * Популярные категории (главная + форма «Разместить объявление»).
 * body_type — код в БД; label — текст для пользователя (без картинок в форме).
 *
 * @return list<array{label: string, body_type: string, img?: string, img_alts?: list<string>, q?: string}>
 */
function ia_home_quick_categories_definitions(): array
{
    return [
        ['label' => 'Седан', 'body_type' => 'sedan', 'img' => 'Imeg/categories/sedan.png'],
        ['label' => 'Внедорожник', 'body_type' => 'suv', 'img' => 'Imeg/categories/suv.png'],
        ['label' => 'Электромобиль', 'body_type' => 'ev', 'img' => 'Imeg/categories/ev.png'],
        ['label' => 'Спорт', 'body_type' => 'sport', 'img' => 'Imeg/categories/sport.png'],
        ['label' => 'Кроссовер премиум', 'body_type' => 'crossover', 'img' => 'Imeg/categories/crossover-premium.png'],
        ['label' => 'Премиум-седан', 'body_type' => 'sedan', 'img' => 'Imeg/categories/sedan-premium.png', 'q' => 'Mercedes'],
        ['label' => 'Хэтчбек', 'body_type' => 'hatchback', 'img' => 'Imeg/categories/hatchback.png'],
        ['label' => 'Пикап', 'body_type' => 'pickup', 'img' => 'Imeg/categories/pickup.png'],
        ['label' => 'Минивэн', 'body_type' => 'van', 'img' => 'Imeg/categories/van.png'],
        [
            'label' => 'Мотосикл',
            'body_type' => 'motorcycle',
            'img' => 'Imeg/categories/motorcycle.jpg',
            'img_alts' => ['Imeg/categories/motorcycle..jpg', 'Imeg/categories/motorcycle.svg'],
        ],
        ['label' => 'Коммерческий', 'body_type' => 'truck', 'img' => 'Imeg/categories/commercial.png'],
    ];
}

/**
 * Первый существующий файл картинки категории (на хостинге имена после upload могут отличаться).
 */
function ia_home_category_image_rel(string $primary, array $alternates = []): string
{
    foreach (array_merge([$primary], $alternates) as $rel) {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        if ($rel === '') {
            continue;
        }
        $abs = IA_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($abs)) {
            return $rel;
        }
    }

    return ltrim(str_replace('\\', '/', $primary), '/');
}

/**
 * Опции «Тип кузова» для add-listing / edit-listing (только названия, как на главной).
 *
 * @return array<string, string> body_type code => label
 */
function ia_listing_form_body_type_options(): array
{
    $options = [];
    foreach (ia_home_quick_categories_definitions() as $item) {
        $code = trim((string) ($item['body_type'] ?? ''));
        $label = trim((string) ($item['label'] ?? ''));
        if ($code === '' || $label === '') {
            continue;
        }
        if (!isset($options[$code])) {
            $options[$code] = $label;
        }
    }

    return $options;
}
