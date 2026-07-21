<?php

declare(strict_types=1);

if (!function_exists('ia_render_city_select')) {
    $iaTjCitiesPath = IA_ROOT . '/includes/tj_cities.php';
    if (!is_file($iaTjCitiesPath)) {
        $iaCityFallback = (string) ($iaCitySelectValue ?? 'Душанбе');
        echo '<select name="' . htmlspecialchars((string) ($iaCitySelectName ?? 'city'), ENT_QUOTES, 'UTF-8') . '"'
            . ' id="' . htmlspecialchars((string) ($iaCitySelectId ?? 'fldCity'), ENT_QUOTES, 'UTF-8') . '"'
            . ' class="' . htmlspecialchars((string) ($iaCitySelectClass ?? 'form-select'), ENT_QUOTES, 'UTF-8') . '">'
            . '<option value="' . htmlspecialchars($iaCityFallback, ENT_QUOTES, 'UTF-8') . '" selected>'
            . htmlspecialchars($iaCityFallback, ENT_QUOTES, 'UTF-8') . '</option></select>';

        return;
    }
    require_once $iaTjCitiesPath;
}

$iaCityEmptyLabel = null;
if (empty($iaCitySelectNoEmpty)) {
    $iaCityEmptyLabel = isset($iaCitySelectEmpty)
        ? (string) $iaCitySelectEmpty
        : '— выберите город —';
}

ia_render_city_select([
    'name' => (string) ($iaCitySelectName ?? 'city'),
    'id' => (string) ($iaCitySelectId ?? 'fldCity'),
    'selected' => (string) ($iaCitySelectValue ?? 'Душанбе'),
    'class' => (string) ($iaCitySelectClass ?? 'form-select'),
    'required' => !empty($iaCitySelectRequired),
    'empty_label' => $iaCityEmptyLabel,
    'allow_legacy' => !isset($iaCitySelectAllowLegacy) || (bool) $iaCitySelectAllowLegacy,
    'aria_label' => (string) ($iaCitySelectAriaLabel ?? 'Город'),
]);
