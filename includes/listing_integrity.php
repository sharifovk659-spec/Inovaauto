<?php

declare(strict_types=1);

/**
 * Мягкие признаки для ручной проверки модератором (не блокируют публикацию).
 *
 * @param array<string, mixed> $listing строка ad_listings
 * @return list<string>
 */
function ia_listing_risk_hints_ru(array $listing): array
{
    $hints = [];
    $price = (float) ($listing['price'] ?? 0);
    $year = (int) ($listing['model_year'] ?? 0);
    if ($price > 0 && $price < 500) {
        $hints[] = 'Подозрительно низкая цена — проверьте объявление.';
    }
    $desc = trim((string) ($listing['description'] ?? ''));
    if ($desc === '' || mb_strlen($desc) < 25) {
        $hints[] = 'Очень короткое или пустое описание.';
    }
    $vin = trim((string) ($listing['vin'] ?? ''));
    if ($vin === '') {
        $hints[] = 'VIN не указан — выше риск дубликатов и «фейков».';
    }
    if ($year >= 2018 && $price > 0 && $price < 3000) {
        $hints[] = 'Несоответствие года и цены — рекомендуется проверка.';
    }

    return $hints;
}
