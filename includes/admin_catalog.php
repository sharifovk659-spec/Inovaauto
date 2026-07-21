<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

require_once IA_ROOT . '/includes/ia_cache.php';
require_once IA_ROOT . '/includes/public_queries.php';

function ia_catalog_invalidate_public_cache(): void
{
    ia_pub_invalidate_catalog_cache();
}

/**
 * @param bool $touchPublicCatalogCache false только при ошибке CSRF (данные не менялись)
 */
function ia_catalog_admin_redirect(string $url, bool $touchPublicCatalogCache = true): void
{
    if ($touchPublicCatalogCache) {
        ia_catalog_invalidate_public_cache();
    }
    ia_redirect($url);
}

/**
 * @return list<array<string,mixed>>
 */
function ia_catalog_brands_list(IaPgConnection|IaPdoConnection $pdo): array
{
    return $pdo->query('SELECT id, name, sort_order, created_at FROM car_brands ORDER BY sort_order ASC, id ASC')->fetchAll() ?: [];
}

/**
 * @return list<array<string,mixed>>
 */
function ia_catalog_all_brands_for_select(IaPgConnection|IaPdoConnection $pdo): array
{
    return $pdo->query('SELECT id, name FROM car_brands ORDER BY sort_order ASC, name ASC')->fetchAll() ?: [];
}

function ia_catalog_brand_by_id(IaPgConnection|IaPdoConnection $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM car_brands WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

function ia_catalog_next_sort_brand(IaPgConnection|IaPdoConnection $pdo): int
{
    return (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM car_brands')->fetchColumn() + 10;
}

function ia_catalog_next_sort_model(IaPgConnection|IaPdoConnection $pdo, int $brandId): int
{
    $st = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM car_models WHERE brand_id = ?');
    $st->execute([$brandId]);
    return (int) $st->fetchColumn() + 10;
}

function ia_catalog_next_sort_category(IaPgConnection|IaPdoConnection $pdo): int
{
    return (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM vehicle_categories')->fetchColumn() + 10;
}

function ia_catalog_swap_brand_order(IaPgConnection|IaPdoConnection $pdo, int $id, string $dir): void
{
    $rows = $pdo->query('SELECT id, sort_order FROM car_brands ORDER BY sort_order ASC, id ASC')->fetchAll();
    ia_catalog_swap_ordered_rows($pdo, $rows, $id, $dir, 'car_brands');
}

/**
 * @param list<array<string,mixed>> $rows
 */
function ia_catalog_swap_ordered_rows(IaPgConnection|IaPdoConnection $pdo, array $rows, int $id, string $dir, string $table): void
{
    if (!in_array($table, ['car_brands', 'car_models', 'vehicle_categories'], true)) {
        return;
    }
    $idx = null;
    foreach ($rows as $i => $r) {
        if ((int) $r['id'] === $id) {
            $idx = $i;
            break;
        }
    }
    if ($idx === null) {
        return;
    }
    $j = $dir === 'up' ? $idx - 1 : $idx + 1;
    if ($j < 0 || $j >= count($rows)) {
        return;
    }
    $a = $rows[$idx];
    $b = $rows[$j];
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("UPDATE {$table} SET sort_order = ? WHERE id = ?");
        $st->execute([(int) $b['sort_order'], (int) $a['id']]);
        $st->execute([(int) $a['sort_order'], (int) $b['id']]);
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function ia_catalog_swap_model_order(IaPgConnection|IaPdoConnection $pdo, int $brandId, int $id, string $dir): void
{
    $st = $pdo->prepare('SELECT id, sort_order FROM car_models WHERE brand_id = ? ORDER BY sort_order ASC, id ASC');
    $st->execute([$brandId]);
    $rows = $st->fetchAll() ?: [];
    ia_catalog_swap_ordered_rows($pdo, $rows, $id, $dir, 'car_models');
}

function ia_catalog_swap_category_order(IaPgConnection|IaPdoConnection $pdo, int $id, string $dir): void
{
    $rows = $pdo->query('SELECT id, sort_order FROM vehicle_categories ORDER BY sort_order ASC, id ASC')->fetchAll() ?: [];
    ia_catalog_swap_ordered_rows($pdo, $rows, $id, $dir, 'vehicle_categories');
}

/**
 * @return list<array<string,mixed>>
 */
function ia_catalog_models_by_brand(IaPgConnection|IaPdoConnection $pdo, int $brandId): array
{
    $st = $pdo->prepare('SELECT id, brand_id, name, sort_order, created_at FROM car_models WHERE brand_id = ? ORDER BY sort_order ASC, id ASC');
    $st->execute([$brandId]);
    return $st->fetchAll() ?: [];
}

function ia_catalog_model_by_id(IaPgConnection|IaPdoConnection $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM car_models WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

/**
 * @return list<array<string,mixed>>
 */
function ia_catalog_categories_list(IaPgConnection|IaPdoConnection $pdo): array
{
    return $pdo->query('SELECT id, name, sort_order, created_at FROM vehicle_categories ORDER BY sort_order ASC, id ASC')->fetchAll() ?: [];
}

function ia_catalog_category_by_id(IaPgConnection|IaPdoConnection $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM vehicle_categories WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

function ia_catalog_first_brand_id(IaPgConnection|IaPdoConnection $pdo): ?int
{
    $id = $pdo->query('SELECT id FROM car_brands ORDER BY sort_order ASC, id ASC LIMIT 1')->fetchColumn();
    return $id !== false ? (int) $id : null;
}

function ia_catalog_brands_post_redirect(string $returnFlashKey = 'catalog_ok'): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('catalog_error', 'Сессия устарела. Повторите действие.');
        ia_catalog_admin_redirect(ia_admin_url('brands.php'), false);
    }
    $pdo = ia_db();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $name = ia_post_text('name', 120);
        if ($name === '') {
            ia_flash('catalog_error', 'Укажите название бренда.');
        } elseif (mb_strlen($name) > 120) {
            ia_flash('catalog_error', 'Название бренда не более 120 символов.');
        } else {
            try {
                $exists = $pdo->prepare('SELECT id FROM car_brands WHERE LOWER(name) = LOWER(?) LIMIT 1');
                $exists->execute([$name]);
                if ($exists->fetchColumn()) {
                    ia_flash('catalog_error', 'Бренд с таким названием уже существует.');
        } else {
            $st = $pdo->prepare('INSERT INTO car_brands (name, sort_order) VALUES (?, ?)');
            $st->execute([$name, ia_catalog_next_sort_brand($pdo)]);
            ia_flash($returnFlashKey, 'Бренд добавлен.');
                }
            } catch (\PDOException $e) {
                ia_flash('catalog_error', 'Не удалось добавить бренд: ' . ia_catalog_friendly_db_error($e));
            }
        }
        ia_catalog_admin_redirect(ia_admin_url('brands.php'));
    }
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
            $pdo->prepare('DELETE FROM car_brands WHERE id = ?')->execute([$id]);
            ia_flash($returnFlashKey, 'Бренд удалён.');
            } catch (\PDOException $e) {
                ia_flash('catalog_error', 'Не удалось удалить бренд: ' . ia_catalog_friendly_db_error($e));
            }
        }
        ia_catalog_admin_redirect(ia_admin_url('brands.php'));
    }
    if ($action === 'bulk_delete') {
        $rawIds = $_POST['brand_ids'] ?? [];
        if (!is_array($rawIds)) {
            $rawIds = [];
        }
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $rawIds),
            static fn (int $v): bool => $v > 0
        )));
        if (empty($ids)) {
            ia_flash('catalog_error', 'Не выбрано ни одного бренда.');
        } else {
            try {
                $place = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("DELETE FROM car_brands WHERE id IN ($place)")->execute($ids);
                ia_flash($returnFlashKey, sprintf('Удалено брендов: %d.', count($ids)));
            } catch (\PDOException $e) {
                ia_flash('catalog_error', 'Не удалось удалить бренды: ' . ia_catalog_friendly_db_error($e));
            }
        }
        ia_catalog_admin_redirect(ia_admin_url('brands.php'));
    }
    if ($action === 'sort_up' || $action === 'sort_down') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            ia_catalog_swap_brand_order($pdo, $id, $action === 'sort_up' ? 'up' : 'down');
            ia_flash($returnFlashKey, 'Порядок обновлён.');
        }
        ia_catalog_admin_redirect(ia_admin_url('brands.php'));
    }
    if ($action === 'create_bulk') {
        $raw = (string) ($_POST['names'] ?? '');
        $items = preg_split('/[\r\n,;]+/u', $raw) ?: [];
        $items = array_values(array_filter(array_map(static fn (string $s): string => trim($s), $items), static fn (string $s): bool => $s !== ''));
        if (empty($items)) {
            ia_flash('catalog_error', 'Введите хотя бы один бренд (по одному в строке или через запятую).');
            ia_catalog_admin_redirect(ia_admin_url('brands.php'));
        }
        $added = 0;
        $skipped = 0;
        $checkSt = $pdo->prepare('SELECT id FROM car_brands WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $insSt = $pdo->prepare('INSERT INTO car_brands (name, sort_order) VALUES (?, ?)');
        foreach ($items as $name) {
            if (mb_strlen($name) > 120) {
                $skipped++;
                continue;
            }
            try {
                $checkSt->execute([$name]);
                if ($checkSt->fetchColumn()) {
                    $skipped++;
                    continue;
                }
                $insSt->execute([$name, ia_catalog_next_sort_brand($pdo)]);
                $added++;
            } catch (\PDOException) {
                $skipped++;
            }
        }
        if ($added > 0) {
            ia_flash($returnFlashKey, sprintf('Добавлено брендов: %d. Пропущено: %d.', $added, $skipped));
        } else {
            ia_flash('catalog_error', sprintf('Ничего не добавлено. Пропущено: %d (дубликаты или ошибка).', $skipped));
        }
        ia_catalog_admin_redirect(ia_admin_url('brands.php'));
    }
    if ($action === 'rename') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = ia_post_text('name', 120);
        if ($id <= 0) {
            ia_flash('catalog_error', 'Не указан бренд.');
        } elseif ($name === '') {
            ia_flash('catalog_error', 'Укажите новое название бренда.');
        } elseif (mb_strlen($name) > 120) {
            ia_flash('catalog_error', 'Название бренда не более 120 символов.');
        } else {
            try {
                $exists = $pdo->prepare('SELECT id FROM car_brands WHERE LOWER(name) = LOWER(?) AND id <> ? LIMIT 1');
                $exists->execute([$name, $id]);
                if ($exists->fetchColumn()) {
                    ia_flash('catalog_error', 'Бренд с таким названием уже существует.');
                } else {
                    $pdo->prepare('UPDATE car_brands SET name = ? WHERE id = ?')->execute([$name, $id]);
                    ia_flash($returnFlashKey, 'Название бренда обновлено.');
                }
            } catch (\PDOException $e) {
                ia_flash('catalog_error', 'Не удалось переименовать бренд: ' . ia_catalog_friendly_db_error($e));
            }
        }
        ia_catalog_admin_redirect(ia_admin_url('brands.php'));
    }
}

/**
 * Translates a PDO exception into a user-friendly Russian message.
 */
function ia_catalog_friendly_db_error(\Throwable $e): string
{
    $code = (string) ($e->getCode() ?? '');
    $msg = (string) ($e->getMessage() ?? '');
    if ($code === '23505' || str_contains($msg, 'Duplicate entry') || str_contains($msg, 'duplicate key')) {
        return 'запись с таким значением уже существует.';
    }
    if ($code === '23503' || str_contains($msg, 'foreign key') || str_contains($msg, 'foreign-key')) {
        return 'есть связанные записи. Сначала удалите их.';
    }

    return 'ошибка базы данных.';
}

/**
 * Admin URL for the models tab (unified catalog page).
 */
function ia_catalog_models_admin_url(int $brandId): string
{
    $qs = ['view' => 'models'];
    if ($brandId > 0) {
        $qs['brand_id'] = $brandId;
    }

    return ia_admin_url('catalog.php?' . http_build_query($qs));
}

function ia_catalog_models_post_redirect(int $brandId): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('catalog_error', 'Сессия устарела.');
        ia_catalog_admin_redirect(ia_catalog_models_admin_url($brandId), false);
    }
    $pdo = ia_db();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $name = ia_post_text('name', 120);
        if ($name === '') {
            ia_flash('catalog_error', 'Укажите название модели.');
        } elseif (mb_strlen($name) > 120) {
            ia_flash('catalog_error', 'Название модели не более 120 символов.');
        } else {
            try {
                $exists = $pdo->prepare('SELECT id FROM car_models WHERE brand_id = ? AND LOWER(name) = LOWER(?) LIMIT 1');
                $exists->execute([$brandId, $name]);
                if ($exists->fetchColumn()) {
                    ia_flash('catalog_error', 'Модель с таким названием уже есть в этом бренде.');
        } else {
            $st = $pdo->prepare('INSERT INTO car_models (brand_id, name, sort_order) VALUES (?, ?, ?)');
            $st->execute([$brandId, $name, ia_catalog_next_sort_model($pdo, $brandId)]);
            ia_flash('catalog_ok', 'Модель добавлена.');
        }
            } catch (\PDOException $e) {
                ia_flash('catalog_error', 'Не удалось добавить модель: ' . ia_catalog_friendly_db_error($e));
            }
        }
        ia_catalog_admin_redirect(ia_catalog_models_admin_url($brandId));
    }
    if ($action === 'create_bulk') {
        $raw = (string) ($_POST['names'] ?? '');
        $items = preg_split('/[\r\n,;]+/u', $raw) ?: [];
        $items = array_values(array_filter(array_map(static fn (string $s): string => trim($s), $items), static fn (string $s): bool => $s !== ''));
        if (empty($items)) {
            ia_flash('catalog_error', 'Введите хотя бы одну модель (по одной в строке или через запятую).');
            ia_catalog_admin_redirect(ia_catalog_models_admin_url($brandId));
        }
        $added = 0;
        $skipped = 0;
        $checkSt = $pdo->prepare('SELECT id FROM car_models WHERE brand_id = ? AND LOWER(name) = LOWER(?) LIMIT 1');
        $insSt = $pdo->prepare('INSERT INTO car_models (brand_id, name, sort_order) VALUES (?, ?, ?)');
        foreach ($items as $name) {
            if (mb_strlen($name) > 120) {
                $skipped++;
                continue;
            }
            try {
                $checkSt->execute([$brandId, $name]);
                if ($checkSt->fetchColumn()) {
                    $skipped++;
                    continue;
                }
                $insSt->execute([$brandId, $name, ia_catalog_next_sort_model($pdo, $brandId)]);
                $added++;
            } catch (\PDOException) {
                $skipped++;
            }
        }
        if ($added > 0) {
            ia_flash('catalog_ok', sprintf('Добавлено моделей: %d. Пропущено: %d.', $added, $skipped));
        } else {
            ia_flash('catalog_error', sprintf('Ничего не добавлено. Пропущено: %d (дубликаты или ошибка).', $skipped));
        }
        ia_catalog_admin_redirect(ia_catalog_models_admin_url($brandId));
    }
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
            $pdo->prepare('DELETE FROM car_models WHERE id = ? AND brand_id = ?')->execute([$id, $brandId]);
            ia_flash('catalog_ok', 'Модель удалена.');
            } catch (\PDOException $e) {
                ia_flash('catalog_error', 'Не удалось удалить модель: ' . ia_catalog_friendly_db_error($e));
            }
        }
        ia_catalog_admin_redirect(ia_catalog_models_admin_url($brandId));
    }
    if ($action === 'bulk_delete') {
        $rawIds = $_POST['model_ids'] ?? [];
        if (!is_array($rawIds)) {
            $rawIds = [];
        }
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $rawIds),
            static fn (int $v): bool => $v > 0
        )));
        if (empty($ids)) {
            ia_flash('catalog_error', 'Не выбрано ни одной модели.');
        } else {
            try {
                $place = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("DELETE FROM car_models WHERE brand_id = ? AND id IN ($place)")
                    ->execute(array_merge([$brandId], $ids));
                ia_flash('catalog_ok', sprintf('Удалено моделей: %d.', count($ids)));
            } catch (\PDOException $e) {
                ia_flash('catalog_error', 'Не удалось удалить модели: ' . ia_catalog_friendly_db_error($e));
            }
        }
        ia_catalog_admin_redirect(ia_catalog_models_admin_url($brandId));
    }
    if ($action === 'sort_up' || $action === 'sort_down') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            ia_catalog_swap_model_order($pdo, $brandId, $id, $action === 'sort_up' ? 'up' : 'down');
            ia_flash('catalog_ok', 'Порядок обновлён.');
        }
        ia_catalog_admin_redirect(ia_catalog_models_admin_url($brandId));
    }
    if ($action === 'rename') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = ia_post_text('name', 120);
        if ($id <= 0) {
            ia_flash('catalog_error', 'Не указана модель.');
        } elseif ($name === '') {
            ia_flash('catalog_error', 'Укажите новое название модели.');
        } elseif (mb_strlen($name) > 120) {
            ia_flash('catalog_error', 'Название модели не более 120 символов.');
        } else {
            try {
                $exists = $pdo->prepare('SELECT id FROM car_models WHERE brand_id = ? AND LOWER(name) = LOWER(?) AND id <> ? LIMIT 1');
                $exists->execute([$brandId, $name, $id]);
                if ($exists->fetchColumn()) {
                    ia_flash('catalog_error', 'Модель с таким названием уже есть в этом бренде.');
                } else {
                    $pdo->prepare('UPDATE car_models SET name = ? WHERE id = ? AND brand_id = ?')
                        ->execute([$name, $id, $brandId]);
                    ia_flash('catalog_ok', 'Название модели обновлено.');
                }
            } catch (\PDOException $e) {
                ia_flash('catalog_error', 'Не удалось переименовать модель: ' . ia_catalog_friendly_db_error($e));
            }
        }
        ia_catalog_admin_redirect(ia_catalog_models_admin_url($brandId));
    }
    if ($action === 'move') {
        $id = (int) ($_POST['id'] ?? 0);
        $targetBrand = (int) ($_POST['target_brand_id'] ?? 0);
        $redirectBrandId = $brandId;
        if ($id <= 0 || $targetBrand <= 0) {
            ia_flash('catalog_error', 'Не указана модель или новый бренд.');
        } elseif ($targetBrand === $brandId) {
            ia_flash('catalog_error', 'Модель уже находится в этом бренде.');
        } else {
            try {
                $modelRow = $pdo->prepare('SELECT name FROM car_models WHERE id = ? AND brand_id = ?');
                $modelRow->execute([$id, $brandId]);
                $modelName = (string) ($modelRow->fetchColumn() ?: '');
                if ($modelName === '') {
                    ia_flash('catalog_error', 'Модель не найдена.');
                } else {
                    $exists = $pdo->prepare('SELECT id FROM car_models WHERE brand_id = ? AND LOWER(name) = LOWER(?) LIMIT 1');
                    $exists->execute([$targetBrand, $modelName]);
                    if ($exists->fetchColumn()) {
                        ia_flash('catalog_error', 'В целевом бренде уже есть модель с таким названием.');
                    } else {
                        $pdo->prepare('UPDATE car_models SET brand_id = ?, sort_order = ? WHERE id = ? AND brand_id = ?')
                            ->execute([$targetBrand, ia_catalog_next_sort_model($pdo, $targetBrand), $id, $brandId]);
                        ia_flash('catalog_ok', 'Модель перенесена в другой бренд.');
                        $redirectBrandId = $targetBrand;
                    }
                }
            } catch (\PDOException $e) {
                ia_flash('catalog_error', 'Не удалось перенести модель: ' . ia_catalog_friendly_db_error($e));
            }
        }
        ia_catalog_admin_redirect(ia_catalog_models_admin_url($redirectBrandId));
    }
}

/**
 * Cross-brand bulk delete for models (used in "All brands" view).
 *
 * @param list<int> $ids
 */
function ia_catalog_models_bulk_delete_any(IaPgConnection|IaPdoConnection $pdo, array $ids): int
{
    $ids = array_values(array_unique(array_filter(
        array_map('intval', $ids),
        static fn (int $v): bool => $v > 0
    )));
    if (empty($ids)) {
        return 0;
    }
    $place = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("DELETE FROM car_models WHERE id IN ($place)");
    $st->execute($ids);

    return $st->rowCount();
}

/**
 * Bulk move multiple models to one target brand.
 *
 * @param list<int> $ids
 * @return array{moved:int, skipped:int}
 */
function ia_catalog_models_bulk_move(IaPgConnection|IaPdoConnection $pdo, array $ids, int $targetBrandId): array
{
    $ids = array_values(array_unique(array_filter(
        array_map('intval', $ids),
        static fn (int $v): bool => $v > 0
    )));
    if (empty($ids) || $targetBrandId <= 0) {
        return ['moved' => 0, 'skipped' => 0];
    }
    $place = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, brand_id, name FROM car_models WHERE id IN ($place)");
    $st->execute($ids);
    $rows = $st->fetchAll() ?: [];

    $checkSt = $pdo->prepare('SELECT id FROM car_models WHERE brand_id = ? AND LOWER(name) = LOWER(?) LIMIT 1');
    $updSt = $pdo->prepare('UPDATE car_models SET brand_id = ?, sort_order = ? WHERE id = ?');

    $moved = 0;
    $skipped = 0;
    foreach ($rows as $r) {
        if ((int) $r['brand_id'] === $targetBrandId) {
            $skipped++;
            continue;
        }
        try {
            $checkSt->execute([$targetBrandId, (string) $r['name']]);
            if ($checkSt->fetchColumn()) {
                $skipped++;
                continue;
            }
            $updSt->execute([$targetBrandId, ia_catalog_next_sort_model($pdo, $targetBrandId), (int) $r['id']]);
            $moved++;
        } catch (\PDOException) {
            $skipped++;
        }
    }

    return ['moved' => $moved, 'skipped' => $skipped];
}

function ia_catalog_categories_post_redirect(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('catalog_error', 'Сессия устарела.');
        ia_redirect(ia_admin_url('categories.php'));
    }
    $pdo = ia_db();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $name = ia_post_text('name', 120);
        if ($name === '') {
            ia_flash('catalog_error', 'Укажите название категории.');
        } elseif (mb_strlen($name) > 120) {
            ia_flash('catalog_error', 'Название категории не более 120 символов.');
        } else {
            try {
                $exists = $pdo->prepare('SELECT id FROM vehicle_categories WHERE LOWER(name) = LOWER(?) LIMIT 1');
                $exists->execute([$name]);
                if ($exists->fetchColumn()) {
                    ia_flash('catalog_error', 'Категория «' . $name . '» уже существует.');
                } else {
                    $st = $pdo->prepare('INSERT INTO vehicle_categories (name, sort_order) VALUES (?, ?)');
                    $st->execute([$name, ia_catalog_next_sort_category($pdo)]);
                    ia_flash('catalog_ok', 'Категория добавлена.');
                }
            } catch (\Throwable $e) {
                ia_flash('catalog_error', 'Не удалось добавить категорию: ' . ia_catalog_friendly_db_error($e));
            }
        }
        ia_redirect(ia_admin_url('categories.php'));
    }
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare('DELETE FROM vehicle_categories WHERE id = ?')->execute([$id]);
                ia_flash('catalog_ok', 'Категория удалена.');
            } catch (\Throwable $e) {
                ia_flash('catalog_error', 'Не удалось удалить категорию: ' . ia_catalog_friendly_db_error($e));
            }
        }
        ia_redirect(ia_admin_url('categories.php'));
    }
    if ($action === 'sort_up' || $action === 'sort_down') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                ia_catalog_swap_category_order($pdo, $id, $action === 'sort_up' ? 'up' : 'down');
                ia_flash('catalog_ok', 'Порядок обновлён.');
            } catch (\Throwable $e) {
                ia_flash('catalog_error', 'Не удалось изменить порядок: ' . ia_catalog_friendly_db_error($e));
            }
        }
        ia_redirect(ia_admin_url('categories.php'));
    }
}
