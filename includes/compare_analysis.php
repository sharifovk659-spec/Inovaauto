<?php

declare(strict_types=1);

/**
 * Rule-based intelligent comparison analysis for ad_listings rows.
 * No AI, no brand bias, no guessed values.
 */

const IA_COMPARE_ANALYSIS_MAX_POINTS = 100;

/** @var list<string> */
const IA_COMPARE_ANALYSIS_COMPLETENESS_FIELDS = [
    'brand',
    'model',
    'model_year',
    'price',
    'mileage_km',
    'engine_volume',
    'transmission',
    'drive_type',
    'body_type',
    'fuel_type',
    'city',
];

/**
 * Loads approved listings for analysis by IDs (PDO prepared statement).
 *
 * @param list<int> $ids
 * @return list<array<string, mixed>>
 */
function ia_compare_analysis_load_by_ids(IaPgConnection|IaPdoConnection $pdo, array $ids): array
{
    require_once IA_ROOT . '/includes/public_queries.php';

    $in = ia_db_int_in_clause($ids);
    if ($in['ids'] === []) {
        return [];
    }

    $sql = "SELECT id, brand, model, title, price, photo_url, model_year, mileage_km, fuel_type, transmission,
                   body_type, city, availability, is_vip, is_top, status,
                   color, drive_type, engine_volume, engine_power, fuel_consumption, has_turbo,
                   condition_state, customs_cleared, taxi_license, prepayment_amount, currency, description
            FROM ad_listings
            WHERE id IN ({$in['place']}) AND status = 'approved'";
    $st = $pdo->prepare($sql);
    $st->execute($in['ids']);
    $rows = $st->fetchAll() ?: [];
    $byId = [];
    foreach ($rows as $r) {
        $byId[(int) $r['id']] = $r;
    }
    $ordered = [];
    foreach ($ids as $id) {
        $id = ia_input_int($id, 0, 1);
        if ($id > 0 && isset($byId[$id])) {
            $ordered[] = $byId[$id];
        }
    }

    return $ordered;
}

/**
 * @param list<array<string, mixed>> $listings
 * @return array{
 *   cars: list<array<string, mixed>>,
 *   scores: array<int, array<string, float|int>>,
 *   category_winners: array<string, int|null>,
 *   pros: array<int, list<string>>,
 *   cons: array<int, list<string>>,
 *   overall_winner: int|null,
 *   summary: string,
 *   missing_data: array<int, list<string>>
 * }
 */
function ia_compare_analysis(array $listings): array
{
    if ($listings === []) {
        return [
            'cars' => [],
            'scores' => [],
            'category_winners' => [],
            'pros' => [],
            'cons' => [],
            'overall_winner' => null,
            'summary' => 'Нет автомобилей для сравнения.',
            'missing_data' => [],
        ];
    }

    $cars = [];
    $missingData = [];
    $raw = [];
    $rowsById = [];

    foreach ($listings as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $rowsById[$id] = $row;

        $missing = ia_compare_analysis_missing_fields($row);
        $missingData[$id] = $missing;

        $price = ia_compare_analysis_float($row['price'] ?? null);
        $year = ia_compare_analysis_int($row['model_year'] ?? null);
        $mileage = ia_compare_analysis_int($row['mileage_km'] ?? null);
        $engineLiters = ia_compare_analysis_parse_engine_liters($row['engine_volume'] ?? null);
        $drive = ia_compare_analysis_normalize_code($row['drive_type'] ?? null);
        $body = ia_compare_analysis_normalize_code($row['body_type'] ?? null);
        $transmission = ia_compare_analysis_normalize_code($row['transmission'] ?? null);
        $fuel = ia_compare_analysis_normalize_code($row['fuel_type'] ?? null);

        $raw[$id] = [
            'price' => $price,
            'year' => $year,
            'mileage' => $mileage,
            'engine_liters' => $engineLiters,
            'drive' => $drive,
            'body' => $body,
        ];

        $cars[] = [
            'id' => $id,
            'brand' => trim((string) ($row['brand'] ?? '')),
            'model' => trim((string) ($row['model'] ?? '')),
            'title' => ia_compare_analysis_car_title($row),
            'price' => $price,
            'currency' => trim((string) ($row['currency'] ?? 'TJS')),
            'model_year' => $year,
            'mileage_km' => $mileage,
            'engine_volume' => trim((string) ($row['engine_volume'] ?? '')),
            'engine_liters' => $engineLiters,
            'transmission' => $transmission,
            'transmission_label' => ia_compare_analysis_label_or_missing($transmission, 'ia_listing_transmission_label_ru'),
            'drive_type' => $drive,
            'drive_label' => ia_compare_analysis_label_or_missing($drive, 'ia_listing_drive_type_label_ru'),
            'body_type' => $body,
            'body_label' => ia_compare_analysis_label_or_missing($body, 'ia_listing_body_label_ru_pub'),
            'fuel_type' => $fuel,
            'fuel_label' => ia_compare_analysis_label_or_missing($fuel, 'ia_listing_fuel_label_ru'),
            'city' => trim((string) ($row['city'] ?? '')),
            'photo_url' => trim((string) ($row['photo_url'] ?? '')),
            'has_turbo' => (int) ($row['has_turbo'] ?? 0) === 1,
        ];
    }

    $categoryWinners = ia_compare_analysis_category_winners($raw);
    $scores = [];
    $pros = [];
    $cons = [];

    foreach ($cars as $car) {
        $id = (int) $car['id'];
        $r = $raw[$id];

        $priceScore = ia_compare_analysis_rank_score_lower_better($r['price'], array_column($raw, 'price'), 25.0);
        $yearScore = ia_compare_analysis_rank_score_higher_better($r['year'], array_column($raw, 'year'), 20.0);
        $mileageScore = ia_compare_analysis_rank_score_lower_better($r['mileage'], array_column($raw, 'mileage'), 20.0);
        $engineScore = ia_compare_analysis_rank_score_lower_better($r['engine_liters'], array_column($raw, 'engine_liters'), 10.0);
        $driveScore = ia_compare_analysis_drive_score($r['drive']);
        $bodyScore = ia_compare_analysis_body_score($r['body']);
        $completenessScore = ia_compare_analysis_completeness_score($rowsById[$id] ?? []);

        $total = round($priceScore + $yearScore + $mileageScore + $engineScore + $driveScore + $bodyScore + $completenessScore, 2);

        $scores[$id] = [
            'price' => round($priceScore, 2),
            'year' => round($yearScore, 2),
            'mileage' => round($mileageScore, 2),
            'engine' => round($engineScore, 2),
            'drivetrain' => round($driveScore, 2),
            'body' => round($bodyScore, 2),
            'completeness' => round($completenessScore, 2),
            'total' => $total,
        ];

        [$carPros, $carCons] = ia_compare_analysis_build_pros_cons($id, $car, $r, $categoryWinners, $missingData[$id] ?? []);
        $pros[$id] = $carPros;
        $cons[$id] = $carCons;
    }

    $uiCategories = ia_compare_analysis_ui_categories($raw, $cars);
    $overallWinner = ia_compare_analysis_overall_winner($scores);
    $isCloseResult = ia_compare_analysis_is_close_result($scores, $overallWinner);
    $overallWinnerReason = ia_compare_analysis_winner_reason($overallWinner, $scores, $uiCategories, $cars);
    $summary = ia_compare_analysis_build_summary($cars, $scores, $uiCategories, $overallWinner, $isCloseResult);
    $summaryParagraphs = ia_compare_analysis_summary_paragraphs($cars, $scores, $uiCategories, $overallWinner, $isCloseResult);

    return [
        'cars' => $cars,
        'scores' => $scores,
        'category_winners' => $categoryWinners,
        'ui_categories' => $uiCategories,
        'pros' => $pros,
        'cons' => $cons,
        'overall_winner' => $overallWinner,
        'overall_winner_reason' => $overallWinnerReason,
        'is_close_result' => $isCloseResult,
        'summary' => $summary,
        'summary_paragraphs' => $summaryParagraphs,
        'missing_data' => $missingData,
    ];
}

/**
 * @param array<string, mixed> $row
 */
function ia_compare_analysis_car_title(array $row): string
{
    $title = trim((string) ($row['title'] ?? ''));
    if ($title !== '') {
        return $title;
    }

    $brand = trim((string) ($row['brand'] ?? ''));
    $model = trim((string) ($row['model'] ?? ''));
    $year = ia_compare_analysis_int($row['model_year'] ?? null);
    $parts = array_filter([$brand, $model, $year > 0 ? (string) $year : ''], static fn (string $p): bool => $p !== '');

    return $parts !== [] ? implode(' ', $parts) : 'Автомобиль #' . (int) ($row['id'] ?? 0);
}

/**
 * @param array<string, mixed> $row
 * @return list<string>
 */
function ia_compare_analysis_missing_fields(array $row): array
{
    $labels = [
        'brand' => 'Марка',
        'model' => 'Модель',
        'model_year' => 'Год выпуска',
        'price' => 'Цена',
        'mileage_km' => 'Пробег',
        'engine_volume' => 'Объём двигателя',
        'transmission' => 'Коробка передач',
        'drive_type' => 'Привод',
        'body_type' => 'Кузов',
        'fuel_type' => 'Топливо',
        'city' => 'Город',
    ];

    $missing = [];
    foreach (IA_COMPARE_ANALYSIS_COMPLETENESS_FIELDS as $field) {
        if (!ia_compare_analysis_field_present($row, $field)) {
            $missing[] = $labels[$field] ?? $field;
        }
    }

    return $missing;
}

/**
 * @param array<string, mixed> $row
 */
function ia_compare_analysis_field_present(array $row, string $field): bool
{
    if (!array_key_exists($field, $row)) {
        return false;
    }
    $val = $row[$field];
    if ($val === null) {
        return false;
    }
    if (is_string($val) && trim($val) === '') {
        return false;
    }
    if (in_array($field, ['price', 'model_year', 'mileage_km'], true)) {
        return ia_compare_analysis_numeric_present($field, $val);
    }

    return true;
}

function ia_compare_analysis_numeric_present(string $field, mixed $val): bool
{
    if ($field === 'price') {
        return ia_compare_analysis_float($val) > 0;
    }
    if ($field === 'model_year') {
        return ia_compare_analysis_int($val) > 0;
    }
    if ($field === 'mileage_km') {
        return ia_compare_analysis_int($val) >= 0 && trim((string) $val) !== '';
    }

    return false;
}

function ia_compare_analysis_float(mixed $val): float
{
    if ($val === null || $val === '') {
        return 0.0;
    }

    return max(0.0, (float) $val);
}

function ia_compare_analysis_int(mixed $val): int
{
    if ($val === null || $val === '') {
        return 0;
    }

    return max(0, (int) $val);
}

function ia_compare_analysis_normalize_code(mixed $val): string
{
    return strtolower(trim((string) $val));
}

/**
 * Extract first numeric engine volume (liters) from free text, e.g. "2.0 Turbo" -> 2.0
 */
function ia_compare_analysis_parse_engine_liters(mixed $val): ?float
{
    $s = trim((string) $val);
    if ($s === '') {
        return null;
    }
    if (!preg_match('/(\d+(?:[.,]\d+)?)/', $s, $m)) {
        return null;
    }
    $num = (float) str_replace(',', '.', $m[1]);

    return $num > 0 ? $num : null;
}

function ia_compare_analysis_label_or_missing(string $code, string $labelFn): string
{
    if ($code === '') {
        return 'Маълумот нест';
    }
    if (!function_exists($labelFn)) {
        require_once IA_ROOT . '/includes/helpers.php';
    }
    $label = $labelFn($code);

    return $label !== '' && $label !== '—' ? $label : 'Маълумот нест';
}

/**
 * Lower numeric value gets more points within the group.
 *
 * @param list<float|int|null> $allValues
 */
function ia_compare_analysis_rank_score_lower_better(?float $value, array $allValues, float $maxPoints): float
{
    if ($value === null || $value <= 0) {
        return 0.0;
    }

    $valid = array_values(array_filter($allValues, static fn ($v): bool => $v !== null && (float) $v > 0));
    if ($valid === []) {
        return 0.0;
    }
    if (count($valid) === 1) {
        return $maxPoints;
    }

    $min = (float) min($valid);
    $max = (float) max($valid);
    if ($max <= $min) {
        return $maxPoints;
    }

    return $maxPoints * (($max - (float) $value) / ($max - $min));
}

/**
 * Higher numeric value gets more points within the group.
 *
 * @param list<float|int|null> $allValues
 */
function ia_compare_analysis_rank_score_higher_better(?float $value, array $allValues, float $maxPoints): float
{
    if ($value === null || $value <= 0) {
        return 0.0;
    }

    $valid = array_values(array_filter($allValues, static fn ($v): bool => $v !== null && (float) $v > 0));
    if ($valid === []) {
        return 0.0;
    }
    if (count($valid) === 1) {
        return $maxPoints;
    }

    $min = (float) min($valid);
    $max = (float) max($valid);
    if ($max <= $min) {
        return $maxPoints;
    }

    return $maxPoints * (((float) $value - $min) / ($max - $min));
}

function ia_compare_analysis_drive_score(string $drive): float
{
    return match ($drive) {
        'awd', '4wd' => 10.0,
        'front' => 6.0,
        'rear' => 5.0,
        default => 0.0,
    };
}

function ia_compare_analysis_body_score(string $body): float
{
    return match ($body) {
        'suv' => 5.0,
        'sedan', 'hatchback' => 3.0,
        '' => 0.0,
        default => 2.0,
    };
}

/**
 * @param array<string, mixed> $row
 */
function ia_compare_analysis_completeness_score(array $row): float
{
    $total = count(IA_COMPARE_ANALYSIS_COMPLETENESS_FIELDS);
    if ($total === 0) {
        return 0.0;
    }
    $filled = 0;
    foreach (IA_COMPARE_ANALYSIS_COMPLETENESS_FIELDS as $field) {
        if (ia_compare_analysis_field_present($row, $field)) {
            ++$filled;
        }
    }

    return 10.0 * ($filled / $total);
}

/**
 * @param array<int, array{price: ?float, year: ?int, mileage: ?int, engine_liters: ?float, drive: string, body: string}> $raw
 * @return array<string, int|null>
 */
function ia_compare_analysis_category_winners(array $raw): array
{
    return [
        'price' => ia_compare_analysis_winner_lower($raw, 'price'),
        'year' => ia_compare_analysis_winner_higher($raw, 'year'),
        'mileage' => ia_compare_analysis_winner_lower($raw, 'mileage'),
        'engine' => ia_compare_analysis_winner_lower($raw, 'engine_liters'),
        'engine_bigger' => ia_compare_analysis_winner_higher($raw, 'engine_liters'),
        'drivetrain' => ia_compare_analysis_winner_drive($raw),
        'body' => ia_compare_analysis_winner_body($raw),
        'city' => ia_compare_analysis_winner_city($raw),
        'family' => ia_compare_analysis_winner_family($raw),
        'rough_roads' => ia_compare_analysis_winner_rough_roads($raw),
    ];
}

/**
 * UI-facing category winners with Russian labels.
 *
 * @param array<int, array<string, mixed>> $raw
 * @param list<array<string, mixed>> $cars
 * @return list<array{key: string, label: string, winner_id: int|null, winner_title: string|null}>
 */
function ia_compare_analysis_ui_categories(array $raw, array $cars): array
{
    $winners = ia_compare_analysis_category_winners($raw);
    $titles = [];
    foreach ($cars as $car) {
        $titles[(int) $car['id']] = (string) $car['title'];
    }

    $defs = [
        ['key' => 'price', 'label' => 'Лучшая цена', 'id' => $winners['price'] ?? null],
        ['key' => 'year', 'label' => 'Новее', 'id' => $winners['year'] ?? null],
        ['key' => 'mileage', 'label' => 'Меньше пробег', 'id' => $winners['mileage'] ?? null],
        ['key' => 'engine_bigger', 'label' => 'Больше двигатель', 'id' => $winners['engine_bigger'] ?? null],
        ['key' => 'city', 'label' => 'Для города', 'id' => $winners['city'] ?? null],
        ['key' => 'family', 'label' => 'Для семьи', 'id' => $winners['family'] ?? null],
        ['key' => 'rough_roads', 'label' => 'Для сложных дорог', 'id' => $winners['rough_roads'] ?? null],
    ];

    $out = [];
    foreach ($defs as $def) {
        $id = $def['id'];
        $out[] = [
            'key' => $def['key'],
            'label' => $def['label'],
            'winner_id' => $id,
            'winner_title' => $id !== null ? ($titles[$id] ?? null) : null,
        ];
    }

    return $out;
}

/**
 * @param array<int, array<string, mixed>> $raw
 */
function ia_compare_analysis_winner_city(array $raw): ?int
{
    $candidates = [];
    foreach ($raw as $id => $item) {
        $body = (string) ($item['body'] ?? '');
        if (!in_array($body, ['sedan', 'hatchback'], true)) {
            continue;
        }
        $mileage = $item['mileage'] ?? null;
        if ($mileage === null || (int) $mileage < 0) {
            continue;
        }
        $candidates[(int) $id] = (int) $mileage;
    }
    if ($candidates === []) {
        return null;
    }

    asort($candidates, SORT_NUMERIC);

    return (int) array_key_first($candidates);
}

/**
 * @param array<int, array<string, mixed>> $raw
 */
function ia_compare_analysis_winner_family(array $raw): ?int
{
    $bestId = null;
    $bestMileage = null;
    foreach ($raw as $id => $item) {
        if ((string) ($item['body'] ?? '') !== 'suv') {
            continue;
        }
        $mileage = $item['mileage'] ?? null;
        if ($mileage === null) {
            if ($bestId === null) {
                $bestId = (int) $id;
            }
            continue;
        }
        if ($bestMileage === null || (int) $mileage < $bestMileage) {
            $bestMileage = (int) $mileage;
            $bestId = (int) $id;
        }
    }

    return $bestId;
}

/**
 * @param array<int, array<string, mixed>> $raw
 */
function ia_compare_analysis_winner_rough_roads(array $raw): ?int
{
    return ia_compare_analysis_winner_drive($raw);
}

/**
 * @param array<int, array<string, mixed>> $raw
 */
function ia_compare_analysis_winner_lower(array $raw, string $key): ?int
{
    $bestId = null;
    $bestVal = null;
    foreach ($raw as $id => $item) {
        $val = $item[$key] ?? null;
        if ($val === null || (float) $val <= 0) {
            continue;
        }
        if ($bestVal === null || (float) $val < (float) $bestVal) {
            $bestVal = (float) $val;
            $bestId = (int) $id;
        }
    }

    return $bestId;
}

/**
 * @param array<int, array<string, mixed>> $raw
 */
function ia_compare_analysis_winner_higher(array $raw, string $key): ?int
{
    $bestId = null;
    $bestVal = null;
    foreach ($raw as $id => $item) {
        $val = $item[$key] ?? null;
        if ($val === null || (float) $val <= 0) {
            continue;
        }
        if ($bestVal === null || (float) $val > (float) $bestVal) {
            $bestVal = (float) $val;
            $bestId = (int) $id;
        }
    }

    return $bestId;
}

/**
 * @param array<int, array<string, mixed>> $raw
 */
function ia_compare_analysis_winner_drive(array $raw): ?int
{
    $priority = ['awd' => 4, '4wd' => 4, 'front' => 2, 'rear' => 1];
    $bestId = null;
    $bestRank = -1;
    foreach ($raw as $id => $item) {
        $drive = (string) ($item['drive'] ?? '');
        if ($drive === '' || !isset($priority[$drive])) {
            continue;
        }
        if ($priority[$drive] > $bestRank) {
            $bestRank = $priority[$drive];
            $bestId = (int) $id;
        }
    }

    return $bestId;
}

/**
 * @param array<int, array<string, mixed>> $raw
 */
function ia_compare_analysis_winner_body(array $raw): ?int
{
    $priority = ['suv' => 3, 'sedan' => 2, 'hatchback' => 2];
    $bestId = null;
    $bestRank = -1;
    foreach ($raw as $id => $item) {
        $body = (string) ($item['body'] ?? '');
        if ($body === '') {
            continue;
        }
        $rank = $priority[$body] ?? 1;
        if ($rank > $bestRank) {
            $bestRank = $rank;
            $bestId = (int) $id;
        }
    }

    return $bestId;
}

/**
 * @param array<string, int|null> $categoryWinners
 * @param list<string> $missing
 * @return array{0: list<string>, 1: list<string>}
 */
function ia_compare_analysis_build_pros_cons(int $id, array $car, array $raw, array $categoryWinners, array $missing): array
{
    $pros = [];
    $cons = [];

    if (($categoryWinners['price'] ?? null) === $id) {
        $pros[] = 'Самая низкая цена среди выбранных автомобилей.';
    } elseif ($raw['price'] !== null && $categoryWinners['price'] !== null && $categoryWinners['price'] !== $id) {
        $cons[] = 'Цена выше, чем у лидера по стоимости.';
    }

    if (($categoryWinners['year'] ?? null) === $id) {
        $pros[] = 'Самый новый год выпуска в сравнении.';
    } elseif ($raw['year'] !== null && $categoryWinners['year'] !== null && $categoryWinners['year'] !== $id) {
        $cons[] = 'Год выпуска старше, чем у самого нового авто в списке.';
    }

    if (($categoryWinners['mileage'] ?? null) === $id) {
        $pros[] = 'Минимальный пробег среди сравниваемых.';
    } elseif ($raw['mileage'] !== null && $categoryWinners['mileage'] !== null && $categoryWinners['mileage'] !== $id) {
        $cons[] = 'Пробег выше, чем у лидера по пробегу.';
    }

    if (($categoryWinners['engine_bigger'] ?? null) === $id && $raw['engine_liters'] !== null) {
        $pros[] = 'Наибольший заявленный объём двигателя среди выбранных.';
    }

    if (($categoryWinners['city'] ?? null) === $id) {
        $pros[] = 'Оптимален для городской эксплуатации (седан/хэтчбек с меньшим пробегом).';
    }

    if (($categoryWinners['family'] ?? null) === $id) {
        $pros[] = 'Лучший вариант для семьи и поездок (кузов SUV).';
    }

    if (($categoryWinners['rough_roads'] ?? null) === $id) {
        $pros[] = 'Лучший выбор для сложных дорог (полный привод).';
    }

    if (in_array($raw['drive'], ['awd', '4wd'], true)) {
        $pros[] = 'Полный привод — преимущество на сложных дорогах.';
    } elseif ($raw['drive'] === '') {
        $cons[] = 'Привод не указан.';
    }

    if ($raw['body'] === 'suv') {
        $pros[] = 'Кузов SUV — удобнее для семьи и поездок.';
    } elseif ($raw['body'] === 'sedan') {
        $pros[] = 'Седан — нейтральный и комфортный вариант для города.';
    } elseif ($raw['body'] === '') {
        $cons[] = 'Тип кузова не указан.';
    }

    if (!empty($car['has_turbo'])) {
        $pros[] = 'Указан турбированный двигатель.';
    }

    foreach ($missing as $label) {
        $cons[] = 'Не указано: ' . $label . '.';
    }

    if ($pros === []) {
        $pros[] = 'Достаточно данных для участия в сравнении.';
    }

    return [$pros, $cons];
}

/**
 * @param array<int, array<string, float|int>> $scores
 */
function ia_compare_analysis_overall_winner(array $scores): ?int
{
    if ($scores === []) {
        return null;
    }

    $bestId = null;
    $bestTotal = -1.0;
    foreach ($scores as $id => $score) {
        $total = (float) ($score['total'] ?? 0);
        if ($bestId === null || $total > $bestTotal) {
            $bestTotal = $total;
            $bestId = (int) $id;
        }
    }

    return $bestId;
}

/**
 * @param array<int, array<string, float|int>> $scores
 */
function ia_compare_analysis_is_close_result(array $scores, ?int $overallWinner, float $threshold = 8.0): bool
{
    if ($overallWinner === null || count($scores) < 2) {
        return false;
    }

    $totals = [];
    foreach ($scores as $id => $score) {
        $totals[(int) $id] = (float) ($score['total'] ?? 0);
    }
    arsort($totals, SORT_NUMERIC);
    $values = array_values($totals);
    if (count($values) < 2) {
        return false;
    }

    return ($values[0] - $values[1]) <= $threshold;
}

/**
 * @param list<array{key: string, label: string, winner_id: int|null, winner_title: string|null}> $uiCategories
 */
function ia_compare_analysis_winner_reason(?int $overallWinner, array $scores, array $uiCategories, array $cars): string
{
    if ($overallWinner === null) {
        return 'Недостаточно данных для вывода.';
    }

    $total = (float) ($scores[$overallWinner]['total'] ?? 0);
    $scoreParts = [];
    $scoreMap = $scores[$overallWinner] ?? [];
    if (($scoreMap['price'] ?? 0) >= 20) {
        $scoreParts[] = 'выгодная цена';
    }
    if (($scoreMap['year'] ?? 0) >= 15) {
        $scoreParts[] = 'новый год выпуска';
    }
    if (($scoreMap['mileage'] ?? 0) >= 15) {
        $scoreParts[] = 'небольшой пробег';
    }
    if (($scoreMap['drivetrain'] ?? 0) >= 8) {
        $scoreParts[] = 'подходящий привод';
    }

    $leadLabels = [];
    foreach ($uiCategories as $cat) {
        if (($cat['winner_id'] ?? null) === $overallWinner) {
            $leadLabels[] = (string) ($cat['label'] ?? '');
        }
    }
    $leadLabels = array_values(array_filter($leadLabels, static fn (string $s): bool => $s !== ''));

    if ($scoreParts !== []) {
        return 'Лидирует по сумме ' . number_format($total, 0, '.', ' ') . ' из ' . IA_COMPARE_ANALYSIS_MAX_POINTS . ' баллов: ' . implode(', ', $scoreParts) . '.';
    }
    if ($leadLabels !== []) {
        return 'Лидирует по категориям: ' . implode(', ', array_slice($leadLabels, 0, 3)) . '.';
    }

    return 'Лучший суммарный балл среди сравниваемых объявлений.';
}

/**
 * @param list<array{key: string, label: string, winner_id: int|null, winner_title: string|null}> $uiCategories
 */
function ia_compare_analysis_build_summary(array $cars, array $scores, array $uiCategories, ?int $overallWinner, bool $isCloseResult): string
{
    return implode(' ', ia_compare_analysis_summary_paragraphs($cars, $scores, $uiCategories, $overallWinner, $isCloseResult));
}

/**
 * @param list<array{key: string, label: string, winner_id: int|null, winner_title: string|null}> $uiCategories
 * @return list<string>
 */
function ia_compare_analysis_summary_paragraphs(array $cars, array $scores, array $uiCategories, ?int $overallWinner, bool $isCloseResult): array
{
    if ($overallWinner === null || $cars === []) {
        return ['Недостаточно данных для итогового вывода.'];
    }

    if ($isCloseResult) {
        return [
            'Однозначного победителя нет — выбор зависит от ваших приоритетов.',
            'Автомобили набрали близкие баллы по данным объявлений. Сравните категории «Лучшая цена», «Новее» и «Меньше пробег», чтобы выбрать подходящий вариант.',
            'Оценка основана только на указанных характеристиках объявлений, без учёта бренда и без AI.',
        ];
    }

    $winnerTitle = 'Автомобиль #' . $overallWinner;
    foreach ($cars as $car) {
        if ((int) $car['id'] === $overallWinner) {
            $winnerTitle = (string) $car['title'];
            break;
        }
    }

    $total = (float) ($scores[$overallWinner]['total'] ?? 0);
    $paragraphs = [
        'По сумме баллов (макс. ' . IA_COMPARE_ANALYSIS_MAX_POINTS . ') лидирует ' . $winnerTitle . ' — ' . number_format($total, 1, '.', '') . ' баллов.',
    ];

    $leaders = [];
    foreach ($uiCategories as $cat) {
        if (!empty($cat['winner_title'])) {
            $leaders[] = (string) $cat['label'] . ': ' . (string) $cat['winner_title'];
        }
    }
    if ($leaders !== []) {
        $paragraphs[] = 'Лидеры по категориям: ' . implode('; ', array_slice($leaders, 0, 4)) . '.';
    }

    $paragraphs[] = 'Оценка основана только на указанных характеристиках объявлений, без учёта бренда и без AI.';

    return $paragraphs;
}

/**
 * @param array<string, mixed> $analysis
 * @return array<string, string|int|float|null>
 */
function ia_compare_analysis_format_scores_for_display(array $analysis, int $listingId): array
{
    $scores = $analysis['scores'][$listingId] ?? [];

    return [
        'price' => isset($scores['price']) ? (string) $scores['price'] : '0',
        'year' => isset($scores['year']) ? (string) $scores['year'] : '0',
        'mileage' => isset($scores['mileage']) ? (string) $scores['mileage'] : '0',
        'engine' => isset($scores['engine']) ? (string) $scores['engine'] : '0',
        'drivetrain' => isset($scores['drivetrain']) ? (string) $scores['drivetrain'] : '0',
        'body' => isset($scores['body']) ? (string) $scores['body'] : '0',
        'completeness' => isset($scores['completeness']) ? (string) $scores['completeness'] : '0',
        'total' => isset($scores['total']) ? (string) $scores['total'] : '0',
    ];
}

/**
 * Escaped HTML is the caller's responsibility; this returns plain text chunks.
 *
 * @param array<string, mixed> $analysis
 * @return list<string>
 */
function ia_compare_analysis_pros_plain(array $analysis, int $listingId): array
{
    return $analysis['pros'][$listingId] ?? [];
}

/**
 * @param array<string, mixed> $analysis
 * @return list<string>
 */
function ia_compare_analysis_cons_plain(array $analysis, int $listingId): array
{
    return $analysis['cons'][$listingId] ?? [];
}
