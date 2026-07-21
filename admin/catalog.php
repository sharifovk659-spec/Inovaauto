<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_catalog.php';

use InnovaAuto\Security\Csrf;

ia_require_section('catalog');

$pdo = ia_db();

// ---- POST router ----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $brandActions = ['create', 'create_bulk', 'delete', 'rename', 'sort_up', 'sort_down', 'bulk_delete'];
    $modelActions = ['model_create', 'model_create_bulk', 'model_delete', 'model_rename', 'model_sort_up', 'model_sort_down', 'model_bulk_delete', 'model_move'];

    if ($action === 'model_bulk_delete_any') {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            ia_flash('catalog_error', 'Сессия устарела.');
            ia_redirect(ia_admin_url('catalog.php?view=models'));
        }
        $rawIds = $_POST['model_ids'] ?? [];
        if (!is_array($rawIds)) {
            $rawIds = [];
        }
        try {
            $deleted = ia_catalog_models_bulk_delete_any($pdo, array_map('intval', $rawIds));
            if ($deleted > 0) {
                ia_flash('catalog_ok', sprintf('Удалено моделей: %d.', $deleted));
            } else {
                ia_flash('catalog_error', 'Ничего не удалено (возможно, ничего не выбрано).');
            }
        } catch (\PDOException $e) {
            ia_flash('catalog_error', 'Не удалось удалить модели: ' . ia_catalog_friendly_db_error($e));
        }
        ia_catalog_admin_redirect(ia_admin_url('catalog.php?view=models'));
    }

    if ($action === 'models_bulk_move') {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            ia_flash('catalog_error', 'Сессия устарела.');
            ia_redirect(ia_admin_url('catalog.php?view=models'));
        }
        $rawIds = $_POST['model_ids'] ?? [];
        if (!is_array($rawIds)) {
            $rawIds = [];
        }
        $targetBrand = ia_post_int('target_brand_id');
        if ($targetBrand <= 0) {
            ia_flash('catalog_error', 'Не указан целевой бренд.');
        } elseif (empty($rawIds)) {
            ia_flash('catalog_error', 'Не выбрано ни одной модели.');
        } else {
            try {
                $res = ia_catalog_models_bulk_move($pdo, array_map('intval', $rawIds), $targetBrand);
                if ($res['moved'] > 0) {
                    ia_flash('catalog_ok', sprintf('Перенесено моделей: %d. Пропущено: %d (дубликаты или уже в этом бренде).', $res['moved'], $res['skipped']));
                } else {
                    ia_flash('catalog_error', sprintf('Ничего не перенесено. Пропущено: %d.', $res['skipped']));
                }
            } catch (\PDOException $e) {
                ia_flash('catalog_error', 'Не удалось перенести модели: ' . ia_catalog_friendly_db_error($e));
            }
        }
        ia_catalog_admin_redirect(ia_admin_url('catalog.php?view=models&brand_id=' . $targetBrand));
    }

    if (in_array($action, $brandActions, true)) {
        ia_catalog_brands_post_redirect();
    } elseif (in_array($action, $modelActions, true)) {
        $bid = ia_post_int('brand_id_ctx');
        if ($bid <= 0) {
            ia_flash('catalog_error', 'Не указан бренд.');
            ia_redirect(ia_admin_url('catalog.php?view=models'));
        }
        $_POST['action'] = substr($action, 6);
        ia_catalog_models_post_redirect($bid);
    }
}

// ---- View switch ----------------------------------------------------------
$view = (string) ($_GET['view'] ?? 'brands');
if ($view !== 'models') {
    $view = 'brands';
}
$selectedBrandId = ia_get_int('brand_id');

// ---- CSV export -----------------------------------------------------------
$exportKind = isset($_GET['export']) ? (string) $_GET['export'] : '';
if ($exportKind === 'brands' || $exportKind === 'models') {
    $brandsAll = ia_catalog_brands_list($pdo);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="catalog-' . $exportKind . '-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    if ($exportKind === 'brands') {
        $countsRows = $pdo->query('SELECT brand_id, COUNT(*) AS c FROM car_models GROUP BY brand_id')->fetchAll() ?: [];
        $cnt = [];
        foreach ($countsRows as $r) {
            $cnt[(int) $r['brand_id']] = (int) $r['c'];
        }
        fputcsv($out, ['ID', 'Бренд', 'Моделей', 'Порядок', 'Создан']);
        foreach ($brandsAll as $b) {
            fputcsv($out, [
                (int) $b['id'],
                (string) $b['name'],
                (int) ($cnt[(int) $b['id']] ?? 0),
                (int) $b['sort_order'],
                (string) $b['created_at'],
            ]);
        }
    } else {
        fputcsv($out, ['ID модели', 'Бренд', 'Модель', 'Порядок', 'Создана']);
        $sql = 'SELECT m.id, b.name AS brand_name, m.name AS model_name, m.sort_order, m.created_at, m.brand_id
                FROM car_models m INNER JOIN car_brands b ON b.id = m.brand_id';
        $params = [];
        if ($selectedBrandId > 0) {
            $sql .= ' WHERE m.brand_id = :bid';
            $params['bid'] = $selectedBrandId;
        }
        $sql .= ' ORDER BY b.sort_order, b.id, m.sort_order, m.id';
        $st = $pdo->prepare($sql);
        $st->execute($params);
        foreach ($st as $row) {
            fputcsv($out, [
                (int) $row['id'],
                (string) $row['brand_name'],
                (string) $row['model_name'],
                (int) $row['sort_order'],
                (string) $row['created_at'],
            ]);
        }
    }
    fclose($out);
    exit;
}

// ---- Common data ----------------------------------------------------------
$brands = ia_catalog_brands_list($pdo);

$counts = $pdo->query('SELECT brand_id, COUNT(*) AS c FROM car_models GROUP BY brand_id')->fetchAll() ?: [];
$modelCount = [];
foreach ($counts as $row) {
    $modelCount[(int) $row['brand_id']] = (int) $row['c'];
}

$brandUsageRows = $pdo->query("SELECT TRIM(brand) AS brand, COUNT(*) AS c FROM ad_listings WHERE TRIM(brand) <> '' GROUP BY TRIM(brand)")->fetchAll() ?: [];
$brandUsageByName = [];
foreach ($brandUsageRows as $row) {
    $brandUsageByName[mb_strtolower((string) $row['brand'])] = (int) $row['c'];
}
$brandUsage = [];
foreach ($brands as $b) {
    $brandUsage[(int) $b['id']] = $brandUsageByName[mb_strtolower((string) $b['name'])] ?? 0;
}

// ---- Models view: sort + load list ---------------------------------------
$sortKey = (string) ($_GET['sort'] ?? '');
$sortDir = strtolower((string) ($_GET['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
$validSorts = [
    'id'    => 'm.id',
    'name'  => 'm.name',
    'date'  => 'm.created_at',
    'brand' => 'b.name',
];
$sortSql = $validSorts[$sortKey] ?? null;

$modelsList = [];
$selectedBrand = null;
$modelUsage = [];
if ($view === 'models') {
    if ($selectedBrandId > 0) {
        $selectedBrand = ia_catalog_brand_by_id($pdo, $selectedBrandId);
        if ($selectedBrand !== null) {
            $orderClause = $sortSql !== null
                ? sprintf('ORDER BY %s %s, m.id ASC', $sortSql, $sortDir)
                : 'ORDER BY m.sort_order ASC, m.id ASC';
            $st = $pdo->prepare("SELECT m.id, m.brand_id, m.name, m.sort_order, m.created_at, b.name AS brand_name
                                 FROM car_models m INNER JOIN car_brands b ON b.id = m.brand_id
                                 WHERE m.brand_id = :bid
                                 $orderClause");
            $st->execute(['bid' => $selectedBrandId]);
            $modelsList = $st->fetchAll() ?: [];
        }
    } else {
        $orderClause = $sortSql !== null
            ? sprintf('ORDER BY %s %s, m.id ASC', $sortSql, $sortDir)
            : 'ORDER BY b.sort_order, b.id, m.sort_order, m.id';
        $st = $pdo->query("SELECT m.id, m.brand_id, m.name, m.sort_order, m.created_at, b.name AS brand_name
                           FROM car_models m INNER JOIN car_brands b ON b.id = m.brand_id
                           $orderClause");
        $modelsList = $st->fetchAll() ?: [];
    }

    // Per-model listings usage (matches by brand+model name, case-insensitive)
    if (!empty($modelsList)) {
        $usageRows = $pdo->query("SELECT LOWER(TRIM(brand)) AS b, LOWER(TRIM(model)) AS m, COUNT(*) AS c
                                  FROM ad_listings
                                  WHERE TRIM(brand) <> '' AND TRIM(model) <> ''
                                  GROUP BY LOWER(TRIM(brand)), LOWER(TRIM(model))")->fetchAll() ?: [];
        $usageMap = [];
        foreach ($usageRows as $r) {
            $usageMap[$r['b'] . '|' . $r['m']] = (int) $r['c'];
        }
        foreach ($modelsList as $row) {
            $key = mb_strtolower((string) ($row['brand_name'] ?? '')) . '|' . mb_strtolower((string) $row['name']);
            $modelUsage[(int) $row['id']] = $usageMap[$key] ?? 0;
        }
    }
}

/**
 * Build URL preserving current view/brand_id, with new sort + dir params.
 */
function ia_catalog_sort_url(string $key, string $currentSort, string $currentDir, int $brandId): string
{
    $newDir = ($key === $currentSort && strtolower($currentDir) === 'asc') ? 'desc' : 'asc';
    $qs = ['view' => 'models', 'sort' => $key, 'dir' => $newDir];
    if ($brandId > 0) {
        $qs['brand_id'] = $brandId;
    }

    return ia_admin_url('catalog.php?' . http_build_query($qs));
}

$user = ia_current_user();
$pageTitle = $view === 'models' ? 'Каталог: модели' : 'Каталог: бренды';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<main class="container-fluid px-3 px-lg-4 py-4 ia-catalog-page">

    <!-- ====== Header + view tabs ====== -->
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Каталог</h1>
            <p class="text-secondary small mb-0">Управление брендами и моделями автомобилей.</p>
        </div>
        <div class="ia-listings-stat-grid">
            <div class="ia-listings-stat"><span>Брендов</span><b><?= count($brands) ?></b></div>
            <div class="ia-listings-stat"><span>Моделей</span><b><?= array_sum($modelCount) ?></b></div>
            <div class="ia-listings-stat"><span>Используется</span><b><?= count(array_filter($brandUsage)) ?></b></div>
        </div>
    </div>

    <ul class="nav nav-pills ia-catalog-tabs mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link<?= $view === 'brands' ? ' active' : '' ?>" href="<?= ia_h(ia_admin_url('catalog.php?view=brands')) ?>">
                <i class="bi bi-tag-fill me-1"></i>Бренды <span class="badge text-bg-light ms-1"><?= count($brands) ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link<?= $view === 'models' ? ' active' : '' ?>" href="<?= ia_h(ia_admin_url('catalog.php?view=models')) ?>">
                <i class="bi bi-list-ul me-1"></i>Модели <span class="badge text-bg-light ms-1"><?= array_sum($modelCount) ?></span>
            </a>
        </li>
    </ul>

    <?php if ($msg = ia_flash('catalog_ok')): ?><div class="alert alert-success"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <?php if ($msg = ia_flash('catalog_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>

<?php if ($view === 'brands'): ?>

    <!-- ============ BRANDS VIEW ============ -->
    <div class="card card-body mb-3 ia-catalog-toolbar">
        <div class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" id="iaBrandSearch" class="form-control" placeholder="Поиск по бренду...">
                </div>
            </div>
            <div class="col-md-7 d-flex flex-wrap justify-content-md-end gap-2">
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#iaBrandCreateModal">
                    <i class="bi bi-plus-lg me-1"></i>Новый бренд
                </button>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#iaBrandBulkModal">
                    <i class="bi bi-cloud-upload me-1"></i>Массовый импорт
                </button>
                <a class="btn btn-outline-secondary btn-sm" href="<?= ia_h(ia_admin_url('catalog.php?export=brands')) ?>">
                    <i class="bi bi-filetype-csv me-1"></i>Экспорт CSV
                </a>
                <button type="button" class="btn btn-sm btn-danger js-brand-bulk d-none" data-confirm="Удалить выбранные бренды и все их модели?">
                    <i class="bi bi-trash me-1"></i>Удалить выбранные
                </button>
            </div>
        </div>
    </div>

    <form method="post" id="iaBulkBrandForm" class="d-none">
        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
        <input type="hidden" name="action" value="bulk_delete">
    </form>

    <div class="card ia-listings-table-card">
        <div class="ia-catalog-list-head d-flex flex-wrap align-items-center gap-2 p-3 border-bottom">
            <strong>Бренды</strong>
            <span class="small text-secondary">(<?= count($brands) ?>)</span>
            <span class="ms-auto small text-secondary d-none d-md-inline">Кликните «Модели», чтобы перейти к моделям бренда.</span>
        </div>

        <?php if (empty($brands)): ?>
            <div class="text-center text-secondary py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                Пока нет ни одного бренда. Нажмите «Новый бренд», чтобы добавить.
            </div>
        <?php else: ?>
            <ul class="list-group list-group-flush ia-catalog-brand-list" id="iaBrandList">
                <?php foreach ($brands as $b): ?>
                    <?php
                    $bid = (int) $b['id'];
                    $cnt = $modelCount[$bid] ?? 0;
                    $usage = $brandUsage[$bid] ?? 0;
                    ?>
                    <li class="list-group-item ia-catalog-brand-item" data-search="<?= ia_h(mb_strtolower((string) $b['name'])) ?>">
                        <div class="ia-catalog-brand-row">
                            <input type="checkbox" class="form-check-input js-brand-check ia-catalog-brand-check"
                                   name="brand_ids[]" value="<?= $bid ?>" form="iaBulkBrandForm" aria-label="Выбрать бренд">
                            <span class="ia-catalog-brand-name flex-grow-1"><?= ia_h((string) $b['name']) ?></span>
                            <span class="ia-catalog-brand-badges">
                                <span class="badge text-bg-primary" title="Моделей в каталоге"><?= $cnt ?></span>
                                <?php if ($usage > 0): ?>
                                    <span class="badge text-bg-success" title="В объявлениях"><i class="bi bi-megaphone-fill"></i> <?= $usage ?></span>
                                <?php endif; ?>
                            </span>
                            <a class="btn btn-sm ia-catalog-models-btn"
                               href="<?= ia_h(ia_admin_url('catalog.php?view=models&brand_id=' . $bid)) ?>"
                               title="Открыть модели бренда">
                                <i class="bi bi-list-ul"></i><span class="ms-1">Модели</span>
                            </a>
                            <div class="dropdown ia-catalog-brand-menu">
                                <button type="button" class="btn btn-sm btn-outline-secondary ia-catalog-brand-menu-btn" data-bs-toggle="dropdown" aria-expanded="false" title="Действия">⋯</button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button type="button" class="dropdown-item js-brand-rename"
                                                data-id="<?= $bid ?>" data-name="<?= ia_h((string) $b['name']) ?>">
                                            <i class="bi bi-pencil me-2"></i>Переименовать
                                        </button>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?= ia_h(ia_admin_url('brand-edit.php?id=' . $bid)) ?>">
                                            <i class="bi bi-gear me-2"></i>Подробно
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="post" class="m-0">
                                            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                            <input type="hidden" name="id" value="<?= $bid ?>">
                                            <button type="submit" name="action" value="sort_up" class="dropdown-item"><i class="bi bi-arrow-up me-2"></i>Поднять выше</button>
                                        </form>
                                    </li>
                                    <li>
                                        <form method="post" class="m-0">
                                            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                            <input type="hidden" name="id" value="<?= $bid ?>">
                                            <button type="submit" name="action" value="sort_down" class="dropdown-item"><i class="bi bi-arrow-down me-2"></i>Опустить ниже</button>
                                        </form>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="post" class="m-0" onsubmit="return confirm('Удалить бренд и все его модели?');">
                                            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                            <input type="hidden" name="id" value="<?= $bid ?>">
                                            <button type="submit" name="action" value="delete" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Удалить</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div id="iaBrandSearchEmpty" class="text-center text-secondary py-4 d-none">
                <i class="bi bi-search"></i> Ничего не найдено.
            </div>
        <?php endif; ?>
    </div>

<?php else: /* ============ MODELS VIEW ============ */ ?>

    <div class="card card-body mb-3 ia-catalog-toolbar">
        <div class="row g-2 align-items-center">
            <div class="col-md-3">
                <select id="iaModelBrandFilter" class="form-select form-select-sm" data-base-url="<?= ia_h(ia_admin_url('catalog.php?view=models')) ?>">
                    <option value="0">Все бренды</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= (int) $b['id'] ?>" <?= $selectedBrandId === (int) $b['id'] ? 'selected' : '' ?>>
                            <?= ia_h((string) $b['name']) ?>
                            (<?= (int) ($modelCount[(int) $b['id']] ?? 0) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" id="iaModelSearch" class="form-control" placeholder="Поиск по модели...">
                </div>
            </div>
            <div class="col-md-6 d-flex flex-wrap justify-content-md-end gap-2">
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#iaModelCreateModal" <?= $selectedBrandId > 0 ? '' : 'disabled title="Сначала выберите бренд"' ?>>
                    <i class="bi bi-plus-lg me-1"></i>Новая модель
                </button>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#iaModelBulkModal" <?= $selectedBrandId > 0 ? '' : 'disabled title="Сначала выберите бренд"' ?>>
                    <i class="bi bi-cloud-upload me-1"></i>Массовый импорт
                </button>
                <a class="btn btn-outline-secondary btn-sm" href="<?= ia_h(ia_admin_url('catalog.php?export=models' . ($selectedBrandId > 0 ? '&brand_id=' . $selectedBrandId : ''))) ?>">
                    <i class="bi bi-filetype-csv me-1"></i>Экспорт CSV
                </a>
            </div>
        </div>
    </div>

    <?php
    // Bulk delete form: brand-scoped if brand selected, otherwise cross-brand.
    $bulkAction = $selectedBrandId > 0 ? 'model_bulk_delete' : 'model_bulk_delete_any';
    ?>
    <form method="post" id="iaBulkModelForm" class="d-none">
        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
        <input type="hidden" name="action" value="<?= ia_h($bulkAction) ?>">
        <?php if ($selectedBrandId > 0): ?>
            <input type="hidden" name="brand_id_ctx" value="<?= (int) $selectedBrandId ?>">
        <?php endif; ?>
    </form>

    <div class="card ia-listings-table-card">
        <div class="ia-catalog-list-head d-flex flex-wrap align-items-center gap-2 p-3 border-bottom">
            <strong>
                Модели
                <?php if ($selectedBrand !== null): ?>
                    бренда <span class="text-primary"><?= ia_h((string) $selectedBrand['name']) ?></span>
                <?php endif; ?>
            </strong>
            <span class="badge text-bg-primary"><?= count($modelsList) ?></span>
            <?php if ($selectedBrandId === 0): ?>
                <span class="ms-auto small text-secondary d-none d-md-inline"><i class="bi bi-info-circle me-1"></i>Выберите бренд в фильтре, чтобы сортировать вручную.</span>
            <?php endif; ?>
        </div>

        <div class="ia-catalog-bulk-toolbar d-none" id="iaModelBulkToolbar">
            <div>Выбрано моделей: <b id="iaModelSelCount">0</b></div>
            <div class="d-flex gap-2 ms-auto">
                <button type="button" class="btn btn-sm btn-outline-primary js-model-bulk-move">
                    <i class="bi bi-arrow-left-right me-1"></i>Перенести в бренд...
                </button>
                <button type="button" class="btn btn-sm btn-danger js-model-bulk" data-confirm="Удалить выбранные модели?">
                    <i class="bi bi-trash me-1"></i>Удалить выбранные
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle ia-listings-table ia-catalog-models-tbl mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ia-listings-check-cell">
                            <input type="checkbox" class="form-check-input" id="iaModelSelAll" aria-label="Выбрать все">
                        </th>
                        <th style="width:6%">
                            <a class="ia-sort-link" href="<?= ia_h(ia_catalog_sort_url('id', $sortKey, $sortDir, $selectedBrandId)) ?>">ID
                                <?= $sortKey === 'id' ? ($sortDir === 'ASC' ? '<i class="bi bi-caret-up-fill"></i>' : '<i class="bi bi-caret-down-fill"></i>') : '<i class="bi bi-chevron-expand text-muted"></i>' ?>
                            </a>
                        </th>
                        <th>
                            <a class="ia-sort-link" href="<?= ia_h(ia_catalog_sort_url('name', $sortKey, $sortDir, $selectedBrandId)) ?>">Модель
                                <?= $sortKey === 'name' ? ($sortDir === 'ASC' ? '<i class="bi bi-caret-up-fill"></i>' : '<i class="bi bi-caret-down-fill"></i>') : '<i class="bi bi-chevron-expand text-muted"></i>' ?>
                            </a>
                        </th>
                        <?php if ($selectedBrandId === 0): ?>
                        <th style="width:14%">
                            <a class="ia-sort-link" href="<?= ia_h(ia_catalog_sort_url('brand', $sortKey, $sortDir, $selectedBrandId)) ?>">Бренд
                                <?= $sortKey === 'brand' ? ($sortDir === 'ASC' ? '<i class="bi bi-caret-up-fill"></i>' : '<i class="bi bi-caret-down-fill"></i>') : '<i class="bi bi-chevron-expand text-muted"></i>' ?>
                            </a>
                        </th>
                        <?php endif; ?>
                        <th style="width:14%" class="text-center">Объявления</th>
                        <th style="width:16%">
                            <a class="ia-sort-link" href="<?= ia_h(ia_catalog_sort_url('date', $sortKey, $sortDir, $selectedBrandId)) ?>">Дата
                                <?= $sortKey === 'date' ? ($sortDir === 'ASC' ? '<i class="bi bi-caret-up-fill"></i>' : '<i class="bi bi-caret-down-fill"></i>') : '<i class="bi bi-chevron-expand text-muted"></i>' ?>
                            </a>
                        </th>
                        <th style="width:18%">Управление</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($modelsList)): ?>
                        <tr><td colspan="<?= $selectedBrandId === 0 ? 7 : 6 ?>" class="text-center text-secondary py-5">
                            <i class="bi bi-folder2-open d-block mb-2 fs-3"></i>
                            <?php if ($selectedBrand !== null): ?>
                                Для бренда «<?= ia_h((string) $selectedBrand['name']) ?>» моделей пока нет.
                            <?php else: ?>
                                Пока нет ни одной модели.
                            <?php endif; ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($modelsList as $m): ?>
                            <?php
                            $mbid = (int) ($m['brand_id'] ?? $selectedBrandId);
                            $mid = (int) $m['id'];
                            $usage = (int) ($modelUsage[$mid] ?? 0);
                            ?>
                            <tr data-search="<?= ia_h(mb_strtolower((string) $m['name'])) ?>">
                                <td class="ia-listings-check-cell">
                                    <input type="checkbox" class="form-check-input js-model-check"
                                           name="model_ids[]" value="<?= $mid ?>"
                                           data-name="<?= ia_h((string) $m['name']) ?>" data-brand="<?= $mbid ?>"
                                           form="iaBulkModelForm">
                                </td>
                                <td class="text-muted">#<?= $mid ?></td>
                                <td class="fw-semibold"><?= ia_h((string) $m['name']) ?></td>
                                <?php if ($selectedBrandId === 0): ?>
                                    <td>
                                        <a class="text-decoration-none ia-catalog-brand-link-inline" href="<?= ia_h(ia_admin_url('catalog.php?view=models&brand_id=' . $mbid)) ?>">
                                            <?= ia_h((string) ($m['brand_name'] ?? '')) ?>
                                        </a>
                                    </td>
                                <?php endif; ?>
                                <td class="text-center">
                                    <?php if ($usage > 0): ?>
                                        <span class="badge text-bg-success ia-usage-badge"><i class="bi bi-megaphone-fill"></i> <?= $usage ?></span>
                                    <?php else: ?>
                                        <span class="badge text-bg-light text-muted ia-usage-badge">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-secondary"><?= ia_h((string) $m['created_at']) ?></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-secondary js-model-rename"
                                                data-brand="<?= $mbid ?>" data-id="<?= $mid ?>" data-name="<?= ia_h((string) $m['name']) ?>"
                                                title="Переименовать">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary js-model-move"
                                                data-brand="<?= $mbid ?>" data-id="<?= $mid ?>" data-name="<?= ia_h((string) $m['name']) ?>"
                                                title="Перенести в другой бренд">
                                            <i class="bi bi-arrow-left-right"></i>
                                        </button>
                                        <?php if ($selectedBrandId > 0 && $sortKey === ''): ?>
                                            <form method="post" class="d-inline-flex gap-1">
                                                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                                <input type="hidden" name="brand_id_ctx" value="<?= $mbid ?>">
                                                <input type="hidden" name="id" value="<?= $mid ?>">
                                                <button type="submit" name="action" value="model_sort_up" class="btn btn-sm btn-outline-dark" title="Выше"><i class="bi bi-arrow-up"></i></button>
                                                <button type="submit" name="action" value="model_sort_down" class="btn btn-sm btn-outline-dark" title="Ниже"><i class="bi bi-arrow-down"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Удалить модель «<?= ia_h((string) $m['name']) ?>»?');">
                                            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                            <input type="hidden" name="brand_id_ctx" value="<?= $mbid ?>">
                                            <input type="hidden" name="id" value="<?= $mid ?>">
                                            <button type="submit" name="action" value="model_delete" class="btn btn-sm btn-danger" title="Удалить"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div id="iaModelSearchEmpty" class="text-center text-secondary py-4 d-none">
            <i class="bi bi-search"></i> Ничего не найдено.
        </div>
    </div>

<?php endif; /* end view switch */ ?>

</main>

<!-- ====== Modal: New brand ====== -->
<div class="modal fade" id="iaBrandCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Новый бренд</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="create">
                    <label class="form-label">Название бренда</label>
                    <input type="text" name="name" class="form-control" required maxlength="120" placeholder="Toyota" autofocus>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Добавить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====== Modal: Bulk import brands ====== -->
<div class="modal fade" id="iaBrandBulkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Массовый импорт брендов</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="create_bulk">
                    <label class="form-label">По одному бренду в строке или через запятую</label>
                    <textarea name="names" class="form-control" rows="5" required placeholder="Toyota&#10;BMW&#10;Mercedes, Audi, Honda"></textarea>
                    <p class="form-text">Существующие бренды (по названию) пропускаются автоматически.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-success">Импортировать</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====== Modal: Rename brand ====== -->
<div class="modal fade" id="iaBrandRenameModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="iaBrandRenameForm">
                <div class="modal-header">
                    <h5 class="modal-title">Переименовать бренд</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="rename">
                    <input type="hidden" name="id" id="iaBrandRenameId" value="">
                    <label class="form-label">Новое название</label>
                    <input type="text" name="name" id="iaBrandRenameName" class="form-control" required maxlength="120">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($view === 'models' && $selectedBrandId > 0): ?>
<!-- ====== Modal: New model ====== -->
<div class="modal fade" id="iaModelCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Новая модель <span class="text-secondary fw-normal">в «<?= ia_h((string) $selectedBrand['name']) ?>»</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="model_create">
                    <input type="hidden" name="brand_id_ctx" value="<?= (int) $selectedBrandId ?>">
                    <label class="form-label">Название модели</label>
                    <input type="text" name="name" class="form-control" required maxlength="120" placeholder="Camry" autofocus>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Добавить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====== Modal: Bulk import models ====== -->
<div class="modal fade" id="iaModelBulkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Массовый импорт моделей <span class="text-secondary fw-normal">в «<?= ia_h((string) $selectedBrand['name']) ?>»</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="model_create_bulk">
                    <input type="hidden" name="brand_id_ctx" value="<?= (int) $selectedBrandId ?>">
                    <label class="form-label">По одной модели в строке или через запятую</label>
                    <textarea name="names" class="form-control" rows="5" required placeholder="Camry&#10;Corolla&#10;Land Cruiser, RAV4"></textarea>
                    <p class="form-text">Существующие модели в этом бренде пропускаются автоматически.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-success">Импортировать</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ====== Modal: Move model to another brand ====== -->
<div class="modal fade" id="iaModelMoveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="iaModelMoveForm">
                <div class="modal-header">
                    <h5 class="modal-title">Перенести модель в другой бренд</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="model_move">
                    <input type="hidden" name="brand_id_ctx" id="iaModelMoveBrandSrc" value="">
                    <input type="hidden" name="id" id="iaModelMoveId" value="">
                    <p class="mb-2">Модель: <b id="iaModelMoveName"></b></p>
                    <label class="form-label">Целевой бренд</label>
                    <select name="target_brand_id" id="iaModelMoveTarget" class="form-select" required>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?= (int) $b['id'] ?>"><?= ia_h((string) $b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-text">Если в целевом бренде уже есть модель с таким названием — перенос будет отклонён.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Перенести</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====== Modal: Bulk move models to another brand ====== -->
<div class="modal fade" id="iaModelBulkMoveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="iaModelBulkMoveForm">
                <div class="modal-header">
                    <h5 class="modal-title">Перенести выбранные модели</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="models_bulk_move">
                    <div id="iaModelBulkMoveIds"></div>
                    <p class="mb-2">Выбрано моделей: <b id="iaModelBulkMoveCount">0</b></p>
                    <div id="iaModelBulkMovePreview" class="small text-secondary mb-3 ia-model-bulk-preview" style="max-height:140px;overflow:auto;"></div>
                    <label class="form-label">Целевой бренд</label>
                    <select name="target_brand_id" id="iaModelBulkMoveTarget" class="form-select" required>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?= (int) $b['id'] ?>"><?= ia_h((string) $b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-text">Модели с таким же названием в целевом бренде будут пропущены.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Перенести</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====== Modal: Rename model ====== -->
<div class="modal fade" id="iaModelRenameModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="iaModelRenameForm">
                <div class="modal-header">
                    <h5 class="modal-title">Переименовать модель</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="model_rename">
                    <input type="hidden" name="brand_id_ctx" id="iaModelRenameBrand" value="">
                    <input type="hidden" name="id" id="iaModelRenameId" value="">
                    <label class="form-label">Новое название модели</label>
                    <input type="text" name="name" id="iaModelRenameName" class="form-control" required maxlength="120">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    // ---- Live brand search (brands view) ----------------------------------
    var bSearch = document.getElementById('iaBrandSearch');
    var bEmpty = document.getElementById('iaBrandSearchEmpty');
    if (bSearch) {
        bSearch.addEventListener('input', function () {
            var q = this.value.trim().toLowerCase();
            var any = false;
            document.querySelectorAll('.ia-catalog-brand-item').forEach(function (item) {
                var key = item.getAttribute('data-search') || '';
                var ok = q === '' || key.indexOf(q) !== -1;
                item.style.display = ok ? '' : 'none';
                if (ok) any = true;
            });
            if (bEmpty) bEmpty.classList.toggle('d-none', any || q === '');
        });
    }

    // ---- Brand bulk delete -------------------------------------------------
    var brandChecks = document.querySelectorAll('.js-brand-check');
    var brandBulkBtn = document.querySelector('.js-brand-bulk');
    var brandForm = document.getElementById('iaBulkBrandForm');
    function refreshBrandBulk() {
        var any = Array.prototype.some.call(brandChecks, function (c) { return c.checked; });
        if (brandBulkBtn) brandBulkBtn.classList.toggle('d-none', !any);
    }
    brandChecks.forEach(function (c) { c.addEventListener('change', refreshBrandBulk); });
    if (brandBulkBtn) {
        brandBulkBtn.addEventListener('click', function () {
            var any = Array.prototype.some.call(brandChecks, function (c) { return c.checked; });
            if (!any) { window.alert('Ничего не выбрано.'); return; }
            var ask = brandBulkBtn.getAttribute('data-confirm');
            if (ask && !window.confirm(ask)) return;
            if (brandForm) brandForm.submit();
        });
    }

    // ---- Models view: brand filter dropdown -------------------------------
    var brandFilter = document.getElementById('iaModelBrandFilter');
    if (brandFilter) {
        brandFilter.addEventListener('change', function () {
            var bid = parseInt(this.value, 10) || 0;
            var base = this.getAttribute('data-base-url') || '';
            window.location.href = bid > 0 ? (base + '&brand_id=' + bid) : base;
        });
    }

    // ---- Live model search (models view) ----------------------------------
    var mSearch = document.getElementById('iaModelSearch');
    var mEmpty = document.getElementById('iaModelSearchEmpty');
    if (mSearch) {
        mSearch.addEventListener('input', function () {
            var q = this.value.trim().toLowerCase();
            var any = false;
            document.querySelectorAll('.ia-listings-table tbody tr[data-search]').forEach(function (tr) {
                var key = tr.getAttribute('data-search') || '';
                var ok = q === '' || key.indexOf(q) !== -1;
                tr.style.display = ok ? '' : 'none';
                if (ok) any = true;
            });
            if (mEmpty) mEmpty.classList.toggle('d-none', any || q === '');
        });
    }

    // ---- Model bulk: select-all, counter, toolbar, delete ------------------
    var modelChecks = document.querySelectorAll('.js-model-check');
    var modelSelAll = document.getElementById('iaModelSelAll');
    var modelBulkToolbar = document.getElementById('iaModelBulkToolbar');
    var modelBulkBtn = document.querySelector('.js-model-bulk');
    var modelBulkMove = document.querySelector('.js-model-bulk-move');
    var modelSelCount = document.getElementById('iaModelSelCount');
    var modelForm = document.getElementById('iaBulkModelForm');
    function refreshModelBulk() {
        var n = 0;
        modelChecks.forEach(function (c) { if (c.checked) n++; });
        if (modelSelCount) modelSelCount.textContent = String(n);
        if (modelSelAll) {
            modelSelAll.checked = (n > 0 && n === modelChecks.length);
            modelSelAll.indeterminate = (n > 0 && n < modelChecks.length);
        }
        if (modelBulkToolbar) modelBulkToolbar.classList.toggle('d-none', n === 0);
    }
    if (modelSelAll) modelSelAll.addEventListener('change', function () {
        modelChecks.forEach(function (c) { c.checked = modelSelAll.checked; });
        refreshModelBulk();
    });
    modelChecks.forEach(function (c) { c.addEventListener('change', refreshModelBulk); });
    if (modelBulkBtn) {
        modelBulkBtn.addEventListener('click', function () {
            var any = Array.prototype.some.call(modelChecks, function (c) { return c.checked; });
            if (!any) { window.alert('Ничего не выбрано.'); return; }
            var ask = modelBulkBtn.getAttribute('data-confirm');
            if (ask && !window.confirm(ask)) return;
            if (modelForm) modelForm.submit();
        });
    }
    var bulkMoveModalEl = document.getElementById('iaModelBulkMoveModal');
    var bulkMoveModal = bulkMoveModalEl && window.bootstrap ? new bootstrap.Modal(bulkMoveModalEl) : null;
    if (modelBulkMove) {
        modelBulkMove.addEventListener('click', function () {
            var selected = [];
            modelChecks.forEach(function (c) {
                if (c.checked) selected.push({ id: c.value, name: c.getAttribute('data-name') || '#' + c.value, brand: c.getAttribute('data-brand') });
            });
            if (!selected.length) { window.alert('Ничего не выбрано.'); return; }

            var idsBox = document.getElementById('iaModelBulkMoveIds');
            var preview = document.getElementById('iaModelBulkMovePreview');
            var counter = document.getElementById('iaModelBulkMoveCount');
            if (idsBox) {
                while (idsBox.firstChild) idsBox.removeChild(idsBox.firstChild);
                selected.forEach(function (s) {
                    var inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'model_ids[]';
                    inp.value = String(s.id);
                    idsBox.appendChild(inp);
                });
            }
            if (preview) {
                preview.textContent = selected.slice(0, 50).map(function (s) {
                    return '• ' + (s.name || '#' + s.id);
                }).join('\n') + (selected.length > 50 ? '\n… и ещё ' + (selected.length - 50) : '');
            }
            if (counter) counter.textContent = String(selected.length);
            if (bulkMoveModal) bulkMoveModal.show();
        });
    }

    // ---- Inline rename: brand ---------------------------------------------
    var brandRenameModalEl = document.getElementById('iaBrandRenameModal');
    var brandRenameModal = brandRenameModalEl && window.bootstrap ? new bootstrap.Modal(brandRenameModalEl) : null;
    document.querySelectorAll('.js-brand-rename').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            document.getElementById('iaBrandRenameId').value = btn.getAttribute('data-id') || '';
            document.getElementById('iaBrandRenameName').value = btn.getAttribute('data-name') || '';
            if (brandRenameModal) brandRenameModal.show();
        });
    });

    // ---- Inline rename: model ---------------------------------------------
    var modelRenameModalEl = document.getElementById('iaModelRenameModal');
    var modelRenameModal = modelRenameModalEl && window.bootstrap ? new bootstrap.Modal(modelRenameModalEl) : null;
    document.querySelectorAll('.js-model-rename').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            document.getElementById('iaModelRenameBrand').value = btn.getAttribute('data-brand') || '';
            document.getElementById('iaModelRenameId').value = btn.getAttribute('data-id') || '';
            document.getElementById('iaModelRenameName').value = btn.getAttribute('data-name') || '';
            if (modelRenameModal) modelRenameModal.show();
        });
    });

    // ---- Move model to another brand --------------------------------------
    var modelMoveModalEl = document.getElementById('iaModelMoveModal');
    var modelMoveModal = modelMoveModalEl && window.bootstrap ? new bootstrap.Modal(modelMoveModalEl) : null;
    function openMoveModal(brandSrc, modelId, modelName) {
        var srcInp = document.getElementById('iaModelMoveBrandSrc');
        var idInp = document.getElementById('iaModelMoveId');
        var nameEl = document.getElementById('iaModelMoveName');
        var targetSel = document.getElementById('iaModelMoveTarget');
        if (srcInp) srcInp.value = brandSrc || '';
        if (idInp) idInp.value = modelId || '';
        if (nameEl) nameEl.textContent = modelName || '';
        if (targetSel) {
            Array.prototype.forEach.call(targetSel.options, function (opt) {
                opt.disabled = false;
            });
            Array.prototype.forEach.call(targetSel.options, function (opt) {
                opt.disabled = (opt.value === String(brandSrc));
            });
            var picked = false;
            for (var i = 0; i < targetSel.options.length; i++) {
                if (!targetSel.options[i].disabled) {
                    targetSel.selectedIndex = i;
                    picked = true;
                    break;
                }
            }
            if (!picked) targetSel.selectedIndex = -1;
        }
        if (modelMoveModal) modelMoveModal.show();
    }
    document.querySelectorAll('.js-model-move').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            openMoveModal(btn.getAttribute('data-brand'), btn.getAttribute('data-id'), btn.getAttribute('data-name'));
        });
    });
})();
</script>
<?php require __DIR__ . '/partials/foot.php'; ?>
