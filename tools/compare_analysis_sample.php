<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/public_queries.php';
require_once IA_ROOT . '/includes/compare_analysis.php';

$pdo = ia_db();

$camryId = (int) ($pdo->query("SELECT id FROM ad_listings WHERE description LIKE '%source=compare_intel_setup%' AND brand = 'Toyota' AND model = 'Camry' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0);
$bmwId = (int) ($pdo->query("SELECT id FROM ad_listings WHERE description LIKE '%source=compare_intel_setup%' AND brand = 'BMW' AND model = '530i' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0);

if ($camryId <= 0 || $bmwId <= 0) {
    fwrite(STDERR, "Test listings not found.\n");
    exit(1);
}

$listings = ia_compare_analysis_load_by_ids($pdo, [$camryId, $bmwId]);
$analysis = ia_compare_analysis($listings);

$sample = [
    'listing_ids' => [$camryId, $bmwId],
    'category_winners' => $analysis['category_winners'],
    'scores' => [
        $camryId => $analysis['scores'][$camryId] ?? null,
        $bmwId => $analysis['scores'][$bmwId] ?? null,
    ],
    'pros' => [
        'Toyota Camry' => $analysis['pros'][$camryId] ?? [],
        'BMW 530i' => $analysis['pros'][$bmwId] ?? [],
    ],
    'cons' => [
        'Toyota Camry' => $analysis['cons'][$camryId] ?? [],
        'BMW 530i' => $analysis['cons'][$bmwId] ?? [],
    ],
    'overall_winner' => $analysis['overall_winner'],
    'summary' => $analysis['summary'],
    'missing_data' => [
        $camryId => $analysis['missing_data'][$camryId] ?? [],
        $bmwId => $analysis['missing_data'][$bmwId] ?? [],
    ],
];

echo json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
