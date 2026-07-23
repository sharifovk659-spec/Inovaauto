<?php

declare(strict_types=1);

/**
 * One-shot: backup DB tables, inspect schema, insert [TEST] compare listings.
 * CLI only.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/public_bootstrap.php';

$backupRoot = IA_ROOT . '/backups/compare_intel_' . date('Ymd_His');
$filesDir = $backupRoot . '/files';
$dbDir = $backupRoot . '/db';
foreach ([$backupRoot, $filesDir, $dbDir] as $d) {
    if (!is_dir($d) && !mkdir($d, 0775, true) && !is_dir($d)) {
        throw new RuntimeException('Cannot create ' . $d);
    }
}

$filesToBackup = [
    'compare.php',
    'includes/public_queries.php',
    'includes/public_auth.php',
    'includes/schema_frontend.php',
    'includes/schema_platform.php',
    'assets/site.css',
    'car.php',
    'catalog.php',
    'index.php',
    'favorites.php',
    'database/migrations/002_compare_intelligent.sql',
];

foreach ($filesToBackup as $rel) {
    $src = IA_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($src)) {
        continue;
    }
    $dest = $filesDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $destParent = dirname($dest);
    if (!is_dir($destParent) && !mkdir($destParent, 0775, true) && !is_dir($destParent)) {
        throw new RuntimeException('Cannot create ' . $destParent);
    }
    copy($src, $dest);
}

$pdo = ia_db();
$tables = ['ad_listings', 'user_compare', 'car_maintenance_profiles', 'car_brands', 'car_models', 'platform_users'];

$schemaReport = [];
foreach ($tables as $table) {
    $exists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($table)
    )->fetchColumn();
    if ($exists === 0) {
        $schemaReport[$table] = ['exists' => false];
        continue;
    }
    $cols = $pdo->query(
        "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($table) . '
         ORDER BY ORDINAL_POSITION'
    )->fetchAll(PDO::FETCH_ASSOC);
    $schemaReport[$table] = ['exists' => true, 'columns' => $cols, 'row_count' => (int) $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '`')->fetchColumn()];

    $fh = fopen($dbDir . '/' . $table . '.sql', 'wb');
    if ($fh === false) {
        throw new RuntimeException('Cannot write export for ' . $table);
    }
    fwrite($fh, "-- Export {$table} " . date('c') . "\n");
    $rows = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $fields = array_map(static fn ($k) => '`' . str_replace('`', '``', (string) $k) . '`', array_keys($row));
        $vals = [];
        foreach ($row as $v) {
            $vals[] = $v === null ? 'NULL' : $pdo->quote((string) $v);
        }
        fwrite($fh, 'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $vals) . ");\n");
    }
    fclose($fh);
}

file_put_contents($backupRoot . '/schema_report.json', json_encode($schemaReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$listingCols = array_column($schemaReport['ad_listings']['columns'] ?? [], 'COLUMN_NAME');
$notNull = [];
foreach ($schemaReport['ad_listings']['columns'] ?? [] as $c) {
    if (($c['IS_NULLABLE'] ?? '') === 'NO' && ($c['COLUMN_DEFAULT'] ?? null) === null && ($c['EXTRA'] ?? '') !== 'auto_increment') {
        $notNull[] = $c['COLUMN_NAME'];
    }
}

$userId = (int) ($pdo->query("SELECT id FROM platform_users WHERE status = 'active' ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);
if ($userId <= 0) {
    $pdo->exec("INSERT INTO platform_users (name, phone, email, account_type, status) VALUES ('Test Seller', '+992900000000', 'test.compare@innovaauto.local', 'private', 'active')");
    $userId = (int) $pdo->lastInsertId();
}

$hasTitle = in_array('title', $listingCols, true);
$hasSlug = in_array('slug', $listingCols, true);

$cars = [
    [
        'brand' => 'Toyota', 'model' => 'Camry', 'model_year' => 2020, 'price' => 245000, 'mileage_km' => 85000,
        'engine_volume' => '2.5', 'has_turbo' => 0, 'transmission' => 'auto', 'drive_type' => 'front',
        'fuel_type' => 'petrol', 'body_type' => 'sedan', 'city' => 'Dushanbe',
    ],
    [
        'brand' => 'BMW', 'model' => '530i', 'model_year' => 2019, 'price' => 335000, 'mileage_km' => 105000,
        'engine_volume' => '2.0 Turbo', 'has_turbo' => 1, 'transmission' => 'auto', 'drive_type' => 'rear',
        'fuel_type' => 'petrol', 'body_type' => 'sedan', 'city' => 'Dushanbe',
    ],
    [
        'brand' => 'Hyundai', 'model' => 'Sonata', 'model_year' => 2021, 'price' => 225000, 'mileage_km' => 72000,
        'engine_volume' => '2.0', 'has_turbo' => 0, 'transmission' => 'auto', 'drive_type' => 'front',
        'fuel_type' => 'petrol', 'body_type' => 'sedan', 'city' => 'Khujand',
    ],
    [
        'brand' => 'Toyota', 'model' => 'RAV4', 'model_year' => 2020, 'price' => 310000, 'mileage_km' => 90000,
        'engine_volume' => '2.5', 'has_turbo' => 0, 'transmission' => 'auto', 'drive_type' => 'awd',
        'fuel_type' => 'petrol', 'body_type' => 'suv', 'city' => 'Dushanbe',
    ],
    [
        'brand' => 'Mercedes-Benz', 'model' => 'E300', 'model_year' => 2018, 'price' => 350000, 'mileage_km' => 120000,
        'engine_volume' => '2.0 Turbo', 'has_turbo' => 1, 'transmission' => 'auto', 'drive_type' => 'rear',
        'fuel_type' => 'petrol', 'body_type' => 'sedan', 'city' => 'Dushanbe',
    ],
];

$inserted = [];
$baseUrl = rtrim((string) (ia_config()['base_url'] ?? ''), '/');
if ($baseUrl === '') {
    $baseUrl = 'http://localhost/Test%20innovaauto';
}

foreach ($cars as $i => $car) {
    $title = sprintf('[TEST] %s %s %d', $car['brand'], $car['model'], $car['model_year']);
    $desc = '[TEST] Compare test listing #' . ($i + 1) . ' — source=compare_intel_setup — safe to delete.';
    $slug = $hasSlug ? ('test-compare-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $car['brand'] . '-' . $car['model']) ?: 'car') . '-' . $car['model_year'] . '-' . ($i + 1)) : null;

    $data = [
        'user_id' => $userId,
        'photo_url' => '',
        'brand' => $car['brand'],
        'model' => $car['model'],
        'description' => $desc,
        'model_year' => $car['model_year'],
        'mileage_km' => $car['mileage_km'],
        'city' => $car['city'],
        'body_type' => $car['body_type'],
        'fuel_type' => $car['fuel_type'],
        'transmission' => $car['transmission'],
        'price' => $car['price'],
        'seller_name' => 'Test Seller',
        'availability' => 'in_stock',
        'status' => 'approved',
        'is_vip' => 0,
        'is_top' => 0,
        'color' => '',
        'drive_type' => $car['drive_type'],
        'engine_volume' => $car['engine_volume'],
        'has_turbo' => $car['has_turbo'],
        'condition_state' => 'used',
        'customs_cleared' => 1,
        'taxi_license' => 0,
        'prepayment_amount' => null,
        'currency' => 'TJS',
    ];
    if ($hasTitle) {
        $data['title'] = $title;
    }
    if ($hasSlug && $slug !== null) {
        $data['slug'] = $slug;
    }

    $fields = [];
    $placeholders = [];
    $values = [];
    foreach ($data as $k => $v) {
        if (!in_array($k, $listingCols, true)) {
            continue;
        }
        $fields[] = '`' . str_replace('`', '``', $k) . '`';
        $placeholders[] = '?';
        $values[] = $v;
    }

    $sql = 'INSERT INTO ad_listings (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $st = $pdo->prepare($sql);
    $st->execute($values);
    $id = (int) $pdo->lastInsertId();
    $inserted[] = [
        'id' => $id,
        'title' => $title,
        'url' => $baseUrl . '/car.php?id=' . $id,
    ];
}

$migration002 = [
    'schema_migrations_exists' => false,
    'columns' => [],
    'car_maintenance_profiles' => $schemaReport['car_maintenance_profiles']['exists'] ?? false,
    'car_maintenance_profiles_rows' => $schemaReport['car_maintenance_profiles']['row_count'] ?? 0,
];
try {
    $migration002['schema_migrations_exists'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_migrations'"
    )->fetchColumn() > 0;
    if ($migration002['schema_migrations_exists']) {
        $migration002['applied'] = $pdo->query('SELECT migration FROM schema_migrations ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Throwable) {
}
foreach (['generation', 'engine_power', 'fuel_consumption', 'listing_options', 'seat_count', 'ground_clearance_mm', 'seller_type', 'credit_available'] as $col) {
    $migration002['columns'][$col] = in_array($col, $listingCols, true);
}

$result = [
    'backup_root' => $backupRoot,
    'tables_exported' => $tables,
    'ad_listings_columns' => $listingCols,
    'ad_listings_not_null_without_default' => $notNull,
    'migration_002' => $migration002,
    'inserted_count' => count($inserted),
    'inserted' => $inserted,
    'files_to_change_for_intelligent_ui' => [
        'compare.php',
        'includes/compare_analysis.php',
        'assets/site.css',
        'assets/site.min.css',
    ],
];

file_put_contents($backupRoot . '/setup_result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
