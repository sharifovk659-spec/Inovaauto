<?php

declare(strict_types=1);

/**
 * Санҷиш ва sanitize барои GET/POST пеш аз SQL ё намоиш.
 */

function ia_input_int(mixed $value, int $default = 0, ?int $min = null, ?int $max = null): int
{
    if (is_int($value)) {
        $n = $value;
    } elseif (is_string($value) && $value !== '' && ctype_digit(ltrim($value, '-'))) {
        $n = (int) $value;
    } elseif (is_float($value) && $value == (int) $value) {
        $n = (int) $value;
    } else {
        return $default;
    }

    if ($min !== null && $n < $min) {
        return $default;
    }
    if ($max !== null && $n > $max) {
        return $default;
    }

    return $n;
}

function ia_get_int(string $key, int $default = 0, ?int $min = null, ?int $max = null): int
{
    return ia_input_int($_GET[$key] ?? null, $default, $min, $max);
}

function ia_post_int(string $key, int $default = 0, ?int $min = null, ?int $max = null): int
{
    return ia_input_int($_POST[$key] ?? null, $default, $min, $max);
}

function ia_request_int(string $key, int $default = 0, ?int $min = null, ?int $max = null): int
{
    if (array_key_exists($key, $_POST)) {
        return ia_post_int($key, $default, $min, $max);
    }

    return ia_get_int($key, $default, $min, $max);
}

/**
 * @param mixed $raw
 * @return list<int>
 */
function ia_input_int_list(mixed $raw, int $min = 1, ?int $max = null): array
{
    if (!is_array($raw)) {
        return [];
    }

    $out = [];
    foreach ($raw as $item) {
        $n = ia_input_int($item, 0, $min, $max);
        if ($n > 0) {
            $out[] = $n;
        }
    }

    return array_values(array_unique($out));
}

function ia_input_enum(mixed $value, array $allowed, string $default = ''): string
{
    $s = trim((string) $value);
    if ($s === '' || !in_array($s, $allowed, true)) {
        return $default;
    }

    return $s;
}

function ia_input_strip_control_chars(string $value, bool $allowNewlines = false): string
{
    $value = str_replace("\0", '', $value);
    if ($allowNewlines) {
        $value = preg_replace('/[^\P{C}\n\r\t]+/u', '', $value) ?? $value;
    } else {
        $value = preg_replace('/\p{C}+/u', '', $value) ?? $value;
    }

    return trim($value);
}

function ia_input_text(mixed $value, int $maxLen = 500, bool $allowNewlines = false): string
{
    $s = ia_input_strip_control_chars((string) $value, $allowNewlines);
    if ($maxLen > 0 && mb_strlen($s) > $maxLen) {
        $s = mb_substr($s, 0, $maxLen);
    }

    return $s;
}

function ia_input_long_text(mixed $value, int $maxLen = 8000): string
{
    return ia_input_text($value, $maxLen, true);
}

function ia_get_text(string $key, int $maxLen = 500, bool $allowNewlines = false, string $default = ''): string
{
    if (!array_key_exists($key, $_GET)) {
        return $default;
    }

    $s = ia_input_text($_GET[$key], $maxLen, $allowNewlines);

    return $s !== '' ? $s : $default;
}

function ia_post_text(string $key, int $maxLen = 500, bool $allowNewlines = false, string $default = ''): string
{
    if (!array_key_exists($key, $_POST)) {
        return $default;
    }

    $s = ia_input_text($_POST[$key], $maxLen, $allowNewlines);

    return $s !== '' ? $s : $default;
}

function ia_input_search(mixed $value, int $maxLen = 120): string
{
    $s = ia_input_strip_control_chars((string) $value, false);
    $s = preg_replace('/[<>`\'";\\\\]+/u', '', $s) ?? $s;
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    $s = trim($s);
    if ($maxLen > 0 && mb_strlen($s) > $maxLen) {
        $s = mb_substr($s, 0, $maxLen);
    }

    return $s;
}

function ia_get_search(string $key, int $maxLen = 120, string $default = ''): string
{
    if (!array_key_exists($key, $_GET)) {
        return $default;
    }

    return ia_input_search($_GET[$key], $maxLen);
}

function ia_input_email(mixed $value, int $maxLen = 254): string
{
    $s = ia_input_strip_control_chars((string) $value, false);
    $s = strtolower(trim($s));
    if ($maxLen > 0 && mb_strlen($s) > $maxLen) {
        $s = mb_substr($s, 0, $maxLen);
    }
    if ($s === '' || filter_var($s, FILTER_VALIDATE_EMAIL) === false) {
        return '';
    }

    return $s;
}

function ia_post_email(string $key): string
{
    if (!array_key_exists($key, $_POST)) {
        return '';
    }

    return ia_input_email($_POST[$key]);
}

function ia_input_phone(mixed $value, int $maxLen = 32): string
{
    $s = ia_input_strip_control_chars((string) $value, false);
    $s = preg_replace('/[^\d+\s().\-]/u', '', $s) ?? $s;
    $s = trim($s);
    if ($maxLen > 0 && mb_strlen($s) > $maxLen) {
        $s = mb_substr($s, 0, $maxLen);
    }

    return $s;
}

function ia_post_phone(string $key): string
{
    if (!array_key_exists($key, $_POST)) {
        return '';
    }

    return ia_input_phone($_POST[$key]);
}

/** Email ё username барои admin login */
function ia_input_login_id(mixed $value, int $maxLen = 150): string
{
    $s = ia_input_strip_control_chars((string) $value, false);
    $s = trim($s);
    if ($maxLen > 0 && mb_strlen($s) > $maxLen) {
        $s = mb_substr($s, 0, $maxLen);
    }

    return $s;
}

function ia_input_vin(mixed $value): string
{
    $s = strtoupper(ia_input_strip_control_chars((string) $value, false));
    $s = preg_replace('/[^A-HJ-NPR-Z0-9]/', '', $s) ?? $s;

    return mb_substr($s, 0, 17);
}

function ia_input_url(mixed $value, int $maxLen = 500): string
{
    $s = ia_input_strip_control_chars((string) $value, false);
    $s = trim($s);
    if ($s === '') {
        return '';
    }
    if ($maxLen > 0 && mb_strlen($s) > $maxLen) {
        $s = mb_substr($s, 0, $maxLen);
    }
    if (!preg_match('#\Ahttps?://#i', $s)) {
        return '';
    }

    return $s;
}

function ia_input_decimal(mixed $value, ?float $min = null, ?float $max = null): ?float
{
    if ($value === null || $value === '') {
        return null;
    }
    $s = ia_input_strip_control_chars((string) $value, false);
    $s = str_replace([' ', ','], ['', '.'], $s);
    if ($s === '' || !is_numeric($s)) {
        return null;
    }
    $f = (float) $s;
    if ($min !== null && $f < $min) {
        return null;
    }
    if ($max !== null && $f > $max) {
        return null;
    }

    return $f;
}

/** Query-string барои redirect (бидуни newline/null) */
function ia_input_safe_query_string(mixed $value, int $maxLen = 500): string
{
    $s = ia_input_strip_control_chars((string) $value, false);
    $s = ltrim($s, '?');
    if ($maxLen > 0 && mb_strlen($s) > $maxLen) {
        $s = mb_substr($s, 0, $maxLen);
    }

    return $s;
}

function ia_post_password(string $key): string
{
    if (!array_key_exists($key, $_POST)) {
        return '';
    }

    return (string) $_POST[$key];
}

function ia_input_date(mixed $value): string
{
    $s = ia_input_strip_control_chars((string) $value, false);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return $s;
    }

    return '';
}

function ia_get_date(string $key): string
{
    if (!array_key_exists($key, $_GET)) {
        return '';
    }

    return ia_input_date($_GET[$key]);
}
