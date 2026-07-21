<?php

declare(strict_types=1);

if (!function_exists('ia_render_year_select')) {
    require_once IA_ROOT . '/includes/helpers.php';
}

ia_render_year_select([
    'name' => (string) ($iaYearSelectName ?? 'year'),
    'id' => (string) ($iaYearSelectId ?? 'fldYear'),
    'selected' => (string) ($iaYearSelectValue ?? ''),
    'class' => (string) ($iaYearSelectClass ?? 'form-select'),
    'empty_label' => isset($iaYearSelectEmpty) ? $iaYearSelectEmpty : 'Все',
    'min' => isset($iaYearSelectMin) ? (int) $iaYearSelectMin : 1950,
    'max' => isset($iaYearSelectMax) ? (int) $iaYearSelectMax : ((int) date('Y') + 1),
    'aria_label' => (string) ($iaYearSelectAriaLabel ?? 'Год'),
    'required' => !empty($iaYearSelectRequired),
]);
