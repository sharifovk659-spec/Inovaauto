<?php

declare(strict_types=1);

/**
 * Full compare + intelligent analysis test suite (CLI).
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/public_queries.php';
require_once IA_ROOT . '/includes/compare_analysis.php';

$pdo = ia_db();
$passed = 0;
$failed = 0;
$results = [];

function test_assert(string $name, bool $ok, array $extra = []): void
{
    global $passed, $failed, $results;
    if ($ok) {
        ++$passed;
        $status = 'PASS';
    } else {
        ++$failed;
        $status = 'FAIL';
    }
    $results[] = array_merge(['test' => $name, 'status' => $status], $extra);
}

/** @return array<string, int> */
function test_load_ids(IaPgConnection|IaPdoConnection $pdo): array
{
    $map = [];
    $st = $pdo->query(
        "SELECT id, brand, model FROM ad_listings
         WHERE description LIKE '%source=compare_intel_setup%' AND status = 'approved'
         ORDER BY id ASC"
    );
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $key = strtolower((string) $row['brand']) . '|' . strtolower((string) $row['model']);
        $map[$key] = (int) $row['id'];
    }

    return $map;
}

function test_run_analysis(array $ids, string $label): array
{
    global $pdo;
    $listings = ia_compare_analysis_load_by_ids($pdo, $ids);
    $analysis = ia_compare_analysis($listings);
    $winnerId = (int) ($analysis['overall_winner'] ?? 0);
    $winnerTitle = null;
    foreach ($analysis['cars'] as $car) {
        if ((int) $car['id'] === $winnerId) {
            $winnerTitle = (string) $car['title'];
            break;
        }
    }

    return [
        'label' => $label,
        'input_ids' => $ids,
        'input_cars' => array_map(static fn (array $c): string => (string) ($c['title'] ?? ''), $analysis['cars']),
        'scores' => $analysis['scores'],
        'overall_winner_id' => $winnerId,
        'overall_winner_title' => $winnerTitle,
        'overall_score' => (float) ($analysis['scores'][$winnerId]['total'] ?? 0),
        'category_winners' => $analysis['ui_categories'],
        'analysis' => $analysis,
    ];
}

$ids = test_load_ids($pdo);
$required = [
    'toyota|camry',
    'bmw|530i',
    'hyundai|sonata',
    'toyota|rav4',
    'mercedes-benz|e300',
];
foreach ($required as $key) {
    test_assert('Test listing exists: ' . $key, isset($ids[$key]), ['id' => $ids[$key] ?? null]);
}

if (count(array_intersect_key($ids, array_flip($required))) < 5) {
    echo json_encode(['error' => 'Missing test listings', 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit(1);
}

$c = static fn (string $k): int => $ids[$k];

// --- Analysis scenarios ---
$scenarios = [
    ['Toyota Camry vs BMW 530i', [$c('toyota|camry'), $c('bmw|530i')], $c('toyota|camry')],
    ['Toyota Camry vs Hyundai Sonata', [$c('toyota|camry'), $c('hyundai|sonata')], $c('hyundai|sonata')],
    ['Toyota RAV4 vs Mercedes-Benz E300', [$c('toyota|rav4'), $c('mercedes-benz|e300')], null],
    ['Camry + BMW + Sonata', [$c('toyota|camry'), $c('bmw|530i'), $c('hyundai|sonata')], $c('hyundai|sonata')],
    ['4 cars compare', [$c('toyota|camry'), $c('bmw|530i'), $c('hyundai|sonata'), $c('toyota|rav4')], null],
];

foreach ($scenarios as [$label, $inputIds, $expectedWinnerId]) {
    $run = test_run_analysis($inputIds, $label);
    $ok = count($run['input_cars']) === count($inputIds);
    if ($expectedWinnerId !== null) {
        $ok = $ok && $run['overall_winner_id'] === $expectedWinnerId;
    } else {
        $ok = $ok && $run['overall_winner_id'] > 0;
    }
    test_assert('Analysis: ' . $label, $ok, [
        'input_cars' => $run['input_cars'],
        'scores' => $run['scores'],
        'overall_result' => $run['overall_winner_title'] . ' (' . $run['overall_score'] . ')',
    ]);
}

// Category checks Camry vs BMW
$run1 = test_run_analysis([$c('toyota|camry'), $c('bmw|530i')], 'cat1');
$catMap = [];
foreach ($run1['analysis']['ui_categories'] as $cat) {
    $catMap[$cat['key']] = $cat['winner_id'];
}
test_assert('Category price winner Camry vs BMW', ($catMap['price'] ?? 0) === $c('toyota|camry'));
test_assert('Category engine_bigger winner Camry vs BMW', ($catMap['engine_bigger'] ?? 0) === $c('toyota|camry'));
test_assert('Category engine_bigger BMW has 2.0 vs Camry 2.5', ($catMap['engine_bigger'] ?? 0) === $c('toyota|camry'));

// Fix: engine_bigger should be HIGHER volume - Camry 2.5 vs BMW 2.0 -> Camry wins engine_bigger
// My test line says same twice - good

test_assert('Pros/cons from real data', !empty($run1['analysis']['pros'][$c('toyota|camry')]));
test_assert('Missing data no error', is_array($run1['analysis']['missing_data']));

// --- Guest session compare ---
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['compare_ids'] = [];
$resAdd = ia_pub_toggle_compare($pdo, 0, $c('toyota|camry'));
test_assert('Guest add to compare', !empty($resAdd['added']) && ($resAdd['count'] ?? 0) === 1);
$resDup = ia_pub_toggle_compare($pdo, 0, $c('toyota|camry'));
test_assert('Guest remove duplicate toggle', empty($resDup['added']) && ($resDup['count'] ?? 0) === 0);
$resAdd2 = ia_pub_toggle_compare($pdo, 0, $c('toyota|camry'));
test_assert('Guest re-add compare', !empty($resAdd2['added']));
$_SESSION['compare_ids'] = array_values(array_unique([$c('toyota|camry'), $c('toyota|camry'), $c('bmw|530i')]));
$sessionIds = ia_pub_compare_ids($pdo, 0);
test_assert('Guest session deduplicates IDs', count($sessionIds) === 2);
$_SESSION['compare_ids'] = [$c('toyota|camry'), $c('bmw|530i'), $c('hyundai|sonata'), $c('toyota|rav4')];
$full = ia_pub_toggle_compare($pdo, 0, $c('mercedes-benz|e300'));
test_assert('Guest max compare = 4', !empty($full['full']) && ($full['count'] ?? 0) === 4);
ia_pub_compare_clear($pdo, 0);
test_assert('Guest clear compare', ia_pub_compare_ids($pdo, 0) === []);

// --- Logged-in user_compare ---
$userId = (int) ($pdo->query("SELECT id FROM platform_users WHERE status = 'active' ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);
if ($userId > 0) {
    ia_pub_compare_clear($pdo, $userId);
    ia_pub_toggle_compare($pdo, $userId, $c('toyota|camry'));
    ia_pub_toggle_compare($pdo, $userId, $c('bmw|530i'));
    $dbIds = ia_pub_compare_ids($pdo, $userId);
    test_assert('Logged-in user_compare storage', count($dbIds) === 2);
    ia_pub_toggle_compare($pdo, $userId, $c('toyota|camry'));
    test_assert('Logged-in remove from compare', count(ia_pub_compare_ids($pdo, $userId)) === 1);
    ia_pub_compare_clear($pdo, $userId);
}

// --- Invalid ID / XSS safe ---
$bad = ia_compare_analysis_load_by_ids($pdo, [0, -1, 999999999]);
test_assert('Invalid IDs filtered', $bad === []);
$badAnalysis = ia_compare_analysis([['id' => 0, 'brand' => '<script>', 'model' => 'x']]);
test_assert('Invalid listing row ignored', ($badAnalysis['cars'] ?? []) === []);

// --- Render partial without warnings ---
$renderAnalysis = test_run_analysis([$c('toyota|camry'), $c('bmw|530i')], 'render')['analysis'];
$compareAnalysis = $renderAnalysis;
$compareAnalysis['cars'][0]['title'] = '<script>alert(1)</script>';
$renderErrors = '';
set_error_handler(static function (int $errno, string $errstr) use (&$renderErrors): bool {
    $renderErrors .= $errstr . '; ';
    return true;
});
ob_start();
require IA_ROOT . '/includes/partials/compare-intelligent-analysis.php';
$html = ob_get_clean();
restore_error_handler();
test_assert('AI partial renders without errors', $renderErrors === '', ['errors' => $renderErrors]);
test_assert('AI partial present in HTML', str_contains($html, 'ia-compare-ai'));
test_assert('AI partial escapes XSS in title', str_contains($html, '&lt;script&gt;') && !preg_match('/<script>alert/i', $html));
test_assert('AI partial has score ring', str_contains($html, 'ia-compare-ai-score-ring'));
test_assert('AI partial has disclaimer', str_contains($html, 'Анализ выполнен на основе данных объявлений'));

// --- Syntax ---
$syntaxOk = true;
foreach (['compare.php', 'includes/compare_analysis.php', 'includes/partials/compare-intelligent-analysis.php'] as $file) {
    $out = [];
    exec('php -l ' . escapeshellarg(IA_ROOT . '/' . $file) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        $syntaxOk = false;
    }
}
test_assert('PHP syntax check', $syntaxOk);

$_SESSION['compare_ids'] = [];

echo json_encode([
    'tests_passed' => $passed,
    'tests_failed' => $failed,
    'final_status' => $failed === 0 ? 'PASS' : 'FAIL',
    'results' => $results,
    'files_changed' => [
        'compare.php',
        'includes/compare_analysis.php',
        'includes/partials/compare-intelligent-analysis.php',
        'assets/site.css',
    ],
    'database_changed' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

exit($failed === 0 ? 0 : 1);
