<?php

declare(strict_types=1);

/**
 * Города Таджикистана для фильтра и объявлений (RU в UI и в БД).
 *
 * @return list<string>
 */
function ia_tj_cities_ru_list(): array
{
    return [
        'Душанбе',
        'Истаравшан',
        'Худжанд',
        'Куляб',
        'Курган-Тюбе',
        'Ура-Тюбе',
        'Канибадам',
        'Вахдат',
        'Кофарнигон',
        'Кайракум',
        'Турсунзода',
        'Исфара',
        'Пенджикент',
        'Хорог',
        'Гиссар',
        'Норак',
        'Дангара',
        'Рогун',
        'Вахш',
        'Файзабад',
        'Панч',
        'Шахритус',
        'Гарм',
    ];
}

/**
 * @return array<string, string> lowercase alias => canonical RU
 */
function ia_tj_city_alias_map(): array
{
    static $map = null;
    if (is_array($map)) {
        return $map;
    }

    $map = [];
    foreach (ia_tj_cities_ru_list() as $ru) {
        $map[ia_tj_city_key($ru)] = $ru;
    }

    $latin = [
        'dushanbe' => 'Душанбе',
        'istaravshan' => 'Истаравшан',
        'staravshan' => 'Истаравшан',
        'khujand' => 'Худжанд',
        'khodjend' => 'Худжанд',
        'kulob' => 'Куляб',
        'kulyab' => 'Куляб',
        'qurghonteppa' => 'Курган-Тюбе',
        'kurgan-tyube' => 'Курган-Тюбе',
        'kurgan tyube' => 'Курган-Тюбе',
        'bokhtar' => 'Курган-Тюбе',
        'uroteppa' => 'Ура-Тюбе',
        'konibodom' => 'Канибадам',
        'kanibadam' => 'Канибадам',
        'vahdat' => 'Вахдат',
        'kofarnihon' => 'Кофарнигон',
        'qayraqqum' => 'Кайракум',
        'kayrakum' => 'Кайракум',
        'tursunzoda' => 'Турсунзода',
        'tursunzade' => 'Турсунзода',
        'isfara' => 'Исфара',
        'panjakent' => 'Пенджикент',
        'penjikent' => 'Пенджикент',
        'khorugh' => 'Хорог',
        'khorog' => 'Хорог',
        'hisor' => 'Гиссар',
        'gissar' => 'Гиссар',
        'hissar' => 'Гиссар',
        'norak' => 'Норак',
        'nurek' => 'Норак',
        'danghara' => 'Дангара',
        'dangara' => 'Дангара',
        'roghun' => 'Рогун',
        'rohun' => 'Рогун',
        'vakhsh' => 'Вахш',
        'fayzobod' => 'Файзабад',
        'fayzabad' => 'Файзабад',
        'panj' => 'Панч',
        'pyandzh' => 'Панч',
        'shahritus' => 'Шахритус',
        'shahrinav' => 'Шахритус',
        'garm' => 'Гарм',
    ];

    foreach ($latin as $alias => $ru) {
        if (in_array($ru, ia_tj_cities_ru_list(), true)) {
            $map[ia_tj_city_key($alias)] = $ru;
        }
    }

    return $map;
}

function ia_tj_city_key(string $value): string
{
    $v = function_exists('mb_strtolower') ? mb_strtolower(trim($value)) : strtolower(trim($value));
    $v = str_replace(['ё', '—', '_'], ['е', '-', ' '], $v);
    $v = preg_replace('/\s+/u', ' ', $v) ?? $v;

    return (string) $v;
}

/** Привести ввод (RU/Latin) к каноническому русскому названию. */
function ia_tj_city_normalize(string $input): string
{
    $raw = trim($input);
    if ($raw === '') {
        return '';
    }

    foreach (ia_tj_cities_ru_list() as $ru) {
        if (ia_tj_city_key($raw) === ia_tj_city_key($ru)) {
            return $ru;
        }
    }

    $aliases = ia_tj_city_alias_map();
    $key = ia_tj_city_key($raw);
    if (isset($aliases[$key])) {
        return $aliases[$key];
    }

    if (function_exists('mb_substr')) {
        return mb_substr($raw, 0, 120);
    }

    return substr($raw, 0, 120);
}

function ia_tj_city_is_allowed(string $city): bool
{
    $norm = ia_tj_city_normalize($city);

    return $norm !== '' && in_array($norm, ia_tj_cities_ru_list(), true);
}

/**
 * @param array{
 *   name?: string,
 *   id?: string,
 *   selected?: string,
 *   class?: string,
 *   required?: bool,
 *   empty_label?: string|null,
 *   allow_legacy?: bool,
 *   aria_label?: string
 * } $opts
 */
function ia_render_city_select(array $opts = []): void
{
    $name = (string) ($opts['name'] ?? 'city');
    $id = (string) ($opts['id'] ?? 'fldCity');
    $class = (string) ($opts['class'] ?? 'form-select');
    $required = !empty($opts['required']);
    $emptyLabel = array_key_exists('empty_label', $opts) ? $opts['empty_label'] : null;
    $allowLegacy = !isset($opts['allow_legacy']) || (bool) $opts['allow_legacy'];
    $ariaLabel = (string) ($opts['aria_label'] ?? 'Город');

    $selected = ia_tj_city_normalize((string) ($opts['selected'] ?? ''));
    $list = ia_tj_cities_ru_list();

    echo '<select name="' . ia_h($name) . '" id="' . ia_h($id) . '" class="' . ia_h($class) . '"'
        . ($required ? ' required' : '')
        . ' aria-label="' . ia_h($ariaLabel) . '">';

    if ($emptyLabel !== null) {
        echo '<option value="">' . ia_h((string) $emptyLabel) . '</option>';
    }

    foreach ($list as $ru) {
        $sel = $selected === $ru ? ' selected' : '';
        echo '<option value="' . ia_h($ru) . '"' . $sel . '>' . ia_h($ru) . '</option>';
    }

    if ($allowLegacy && $selected !== '' && !in_array($selected, $list, true)) {
        echo '<option value="' . ia_h($selected) . '" selected>' . ia_h($selected) . ' (архив)</option>';
    }

    echo '</select>';
}
