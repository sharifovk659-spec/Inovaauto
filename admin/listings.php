<?php
declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_listings.php';
require_once IA_ROOT . '/includes/listing_geo.php';
use InnovaAuto\Security\Csrf;

ia_require_section('listings');
$pdo = ia_db();

$iaListingsHasCorePurge = function_exists('ia_admin_listing_hard_delete');

if (!function_exists('ia_admin_listings_filters_open')) {
    function ia_admin_listings_filters_open(array $filters): bool
    {
        return $filters['date_from'] !== ''
            || $filters['date_to'] !== ''
            || $filters['status'] !== ''
            || $filters['vip'] !== ''
            || $filters['availability'] !== ''
            || trim((string) ($filters['body_type'] ?? '')) !== '';
    }
}

if (!$iaListingsHasCorePurge) {
    function ia_admin_listing_hard_delete($pdo, int $id): bool
    {
        require_once IA_ROOT . '/includes/listing_media.php';
        $st = $pdo->prepare('SELECT id FROM ad_listings WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        if (!$st->fetchColumn()) {
            return false;
        }
        ia_listing_block_chat_threads_for_listing($pdo, $id);
        ia_listing_purge_all_files_for_listing($pdo, $id);
        $st = $pdo->prepare('DELETE FROM ad_listings WHERE id = ?');
        $st->execute([$id]);

        return $st->rowCount() > 0;
    }
}

if (!$iaListingsHasCorePurge && $_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'purge') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('listings_error', 'Сессия устарела. Повторите действие.');
        ia_redirect(ia_admin_url('listings.php'));
    }
    $purgeId = ia_post_int('listing_id');
    if ($purgeId <= 0) {
        ia_flash('listings_error', 'Некорректное объявление.');
        ia_redirect(ia_admin_url('listings.php'));
    }
    if (!ia_admin_listing_hard_delete($pdo, $purgeId)) {
        ia_flash('listings_error', 'Не удалось удалить объявление.');
        ia_redirect(ia_admin_url('listings.php'));
    }
    ia_admin_listings_touch_public_cache();
    ia_flash('listings_ok', 'Объявление удалено безвозвратно.');
    ia_redirect(ia_admin_url('listings.php'));
}

ia_admin_listings_handle_post();

$filters = ia_admin_listings_filters();
$filtersOpen = ia_admin_listings_filters_open($filters);
$rows = ia_admin_listings_list($filters);
$geoPoints = ia_listing_geo_points_from_rows($rows);
$geoRadiusM = ia_listing_geo_nearby_radius_m();
$statTotal = count($rows);
$statPending = 0;
$statApproved = 0;
$statSold = 0;
$statExpired = 0;
$statBlocked = 0;
foreach ($rows as $rStat) {
    $s = (string) ($rStat['status'] ?? 'pending');
    match ($s) {
        'approved' => $statApproved++,
        'sold' => $statSold++,
        'archived' => $statExpired++,
        'rejected' => $statBlocked++,
        default => $statPending++,
    };
}
$user = ia_current_user();
$pageTitle = 'Объявления';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<main class="container-fluid px-3 px-lg-4 py-4 ia-listings-page">
    <div class="ia-listings-head d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Управление объявлениями</h1>
            <p class="text-secondary small mb-0">Модерация, статусы и быстрые действия по карточкам.</p>
        </div>
        <div class="ia-listings-stat-grid">
            <div class="ia-listings-stat"><span>Всего</span><b><?= $statTotal ?></b></div>
            <div class="ia-listings-stat"><span>На проверке</span><b><?= $statPending ?></b></div>
            <div class="ia-listings-stat"><span>Активные</span><b><?= $statApproved ?></b></div>
            <div class="ia-listings-stat"><span>Продано</span><b><?= $statSold ?></b></div>
            <div class="ia-listings-stat"><span>Срок истёк</span><b><?= $statExpired ?></b></div>
            <div class="ia-listings-stat"><span>Заблокировано</span><b><?= $statBlocked ?></b></div>
        </div>
    </div>
    <?php if ($msg = ia_flash('listings_ok')): ?><div class="alert alert-success"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <?php if ($msg = ia_flash('listings_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>

    <form class="card card-body mb-3 ia-listings-filter-card" method="get">
        <div class="d-flex justify-content-between align-items-center ia-listings-filter-head<?= $filtersOpen ? ' mb-3' : ' ia-listings-filter-head--collapsed' ?>">
            <div class="fw-semibold"><i class="bi bi-funnel-fill me-2 text-primary"></i>Фильтры</div>
            <button
                type="button"
                class="btn btn-sm btn-link text-secondary text-decoration-none p-0 ia-listings-filter-toggle"
                data-bs-toggle="collapse"
                data-bs-target="#iaListingsFilters"
                aria-expanded="<?= $filtersOpen ? 'true' : 'false' ?>"
                aria-controls="iaListingsFilters"
            >
                Показать / Скрыть <i class="bi bi-chevron-down ms-1 ia-listings-filter-chevron<?= $filtersOpen ? ' ia-listings-filter-chevron--open' : '' ?>" aria-hidden="true"></i>
            </button>
        </div>
        <div class="collapse<?= $filtersOpen ? ' show' : '' ?>" id="iaListingsFilters">
        <div class="row g-2">
            <div class="col-md-3"><label class="form-label">От даты</label><input class="form-control" type="date" name="date_from" value="<?= ia_h($filters['date_from']) ?>"></div>
            <div class="col-md-3"><label class="form-label">До даты</label><input class="form-control" type="date" name="date_to" value="<?= ia_h($filters['date_to']) ?>"></div>
            <div class="col-md-3">
                <label class="form-label">Статус</label>
                <select class="form-select" name="status">
                    <option value="">Все</option>
                    <?php foreach (ia_listing_status_codes() as $st): ?>
                        <option value="<?= $st ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= ia_h(ia_admin_listing_status_ru($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">VIP</label>
                <select class="form-select" name="vip">
                    <option value="">Все</option>
                    <option value="1" <?= $filters['vip'] === '1' ? 'selected' : '' ?>>Да</option>
                    <option value="0" <?= $filters['vip'] === '0' ? 'selected' : '' ?>>Нет</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Наличие</label>
                <select class="form-select" name="availability">
                    <option value="">Все</option>
                    <option value="in_stock" <?= $filters['availability'] === 'in_stock' ? 'selected' : '' ?>>В наличии</option>
                    <option value="on_order" <?= $filters['availability'] === 'on_order' ? 'selected' : '' ?>>На заказ</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Тип кузова</label>
                <select class="form-select" name="body_type">
                    <?php foreach (ia_admin_listing_body_types() as $bk => $bv): ?>
                        <option value="<?= ia_h((string) $bk) ?>" <?= ($filters['body_type'] ?? '') === (string) $bk ? 'selected' : '' ?>><?= ia_h((string) $bv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Применить</button>
                <a class="btn btn-outline-secondary" href="<?= ia_h(ia_admin_url('listings.php')) ?>">Сброс</a>
            </div>
        </div>
        </div>
    </form>

    <form method="post" id="iaBulkForm" class="d-none">
        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
        <input type="hidden" name="action" value="" id="iaBulkActionInput">
    </form>

    <div class="ia-listings-bulkbar d-flex flex-wrap align-items-center gap-2 mb-3 p-3 rounded border" style="background: #f8fafc;">
        <span class="small text-secondary">
            Выбрано: <b id="iaBulkCount">0</b> из <?= (int) $statTotal ?>
        </span>
        <div class="d-flex flex-wrap gap-2 ms-auto">
            <button type="button" class="btn btn-sm btn-outline-success js-bulk-action" data-bulk-action="bulk_approve" data-bulk-confirm="Подтвердить выбранные?">Подтвердить выбранные</button>
            <button type="button" class="btn btn-sm btn-outline-secondary js-bulk-action" data-bulk-action="bulk_archive" data-bulk-confirm="Отправить выбранные в архив?">В архив</button>
            <button type="button" class="btn btn-sm btn-danger js-bulk-action" data-bulk-action="bulk_delete" data-bulk-confirm="Скрыть выбранные объявления из каталога? Записи в базе сохранятся.">Скрыть выбранные</button>
        </div>
    </div>

    <div class="card ia-listings-table-card">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle ia-listings-table mb-0">
                <thead class="table-light"><tr>
                    <th class="ia-listings-check-cell">
                        <input type="checkbox" class="form-check-input" id="iaSelectAll" aria-label="Выбрать все">
                    </th>
                    <th>ID</th><th>Фото</th><th>Бренд</th><th>Модель</th><th>Цена</th><th>Продавец</th><th>Статус</th><th>Наличие</th><th>VIP/Top</th><th>Дата/Время</th><th class="ia-listings-geo-cell" title="Место размещения"><i class="bi bi-car-front-fill" aria-hidden="true"></i></th><th>Управление</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="ia-listings-check-cell">
                            <input type="checkbox" class="form-check-input js-bulk-check" name="listing_ids[]" value="<?= (int) $row['id'] ?>" form="iaBulkForm" aria-label="Выбрать объявление #<?= (int) $row['id'] ?>">
                        </td>
                        <td class="fw-semibold">#<?= (int) $row['id'] ?></td>
                        <td><?php if (!empty($row['photo_url'])): ?><img src="<?= ia_h(ia_listing_photo_src((string) $row['photo_url'])) ?>" alt="" width="64" height="48" class="rounded border object-fit-cover"><?php else: ?><span class="text-secondary small">—</span><?php endif; ?></td>
                        <td><?= ia_h((string) $row['brand']) ?></td>
                        <td><?= ia_h((string) $row['model']) ?></td>
                        <td class="fw-semibold"><?= ia_h(number_format((float) $row['price'], 2, '.', ' ')) ?></td>
                        <td>
                            <div class="fw-medium"><?= ia_h((string) ($row['seller_name'] ?: $row['user_name'])) ?></div>
                            <div class="small text-secondary"><?= ia_h((string) $row['user_phone']) ?></div>
                        </td>
                        <?php $st = (string) ($row['status'] ?? 'pending'); ?>
                        <td>
                            <span class="ia-badge-status <?= ia_h(ia_listing_status_admin_badge_class($st)) ?>">
                                <?= ia_h(ia_admin_listing_status_ru($st)) ?>
                            </span>
                            <?php if ($st === 'pending' && ia_promotion_listing_awaiting_payment($pdo, (int) $row['id'])): ?>
                                <span class="badge text-bg-warning mt-1 d-inline-block">Ожидает оплаты VIP/TOP</span>
                            <?php endif; ?>
                        </td>
                        <?php $av = ia_listing_availability_normalize((string) ($row['availability'] ?? '')); ?>
                        <td>
                            <span class="ia-badge-availability <?= $av === 'on_order' ? 'ia-badge-availability--on-order' : 'ia-badge-availability--in-stock' ?>">
                                <?= ia_h(ia_listing_availability_label_ru($av)) ?>
                            </span>
                        </td>
                        <td>
                            <div class="small d-flex flex-wrap gap-1">
                                <?php $isVipRow = (int) ($row['is_vip'] ?? 0) === 1; ?>
                                <?php $isTopRow = (int) ($row['is_top'] ?? 0) === 1; ?>
                                <span class="ia-badge-flag <?= $isVipRow ? 'ia-badge-flag--on' : 'ia-badge-flag--off' ?>">VIP: <?= $isVipRow ? 'Да' : 'Нет' ?></span>
                                <span class="ia-badge-flag <?= $isTopRow ? 'ia-badge-flag--on' : 'ia-badge-flag--off' ?>">Top: <?= $isTopRow ? 'Да' : 'Нет' ?></span>
                            </div>
                        </td>
                        <td class="small text-secondary ia-listings-date-cell"><?= ia_h(str_replace(' ', "\n", (string) $row['created_at'])) ?></td>
                        <?php $listingGeo = ia_listing_geo_from_row($row); ?>
                        <td class="ia-listings-geo-cell text-center">
                            <?php if ($listingGeo !== null): ?>
                                <?php
                                $geoCaptured = ia_listing_geo_captured_text($listingGeo['captured_at']);
                                $geoAccuracy = ia_listing_geo_accuracy_text($listingGeo['accuracy_m']);
                                $geoMeta = array_values(array_filter([$geoCaptured, $geoAccuracy], static fn (string $part): bool => $part !== ''));
                                $geoAreaCount = ia_listing_geo_area_count($geoPoints, $listingGeo['lat'], $listingGeo['lng'], $geoRadiusM);
                                $geoDensityTone = ia_listing_geo_density_tone($geoAreaCount);
                                $geoDensityLabel = ia_listing_geo_density_label_ru($geoAreaCount, $geoRadiusM);
                                ?>
                                <button
                                    type="button"
                                    class="btn btn-sm ia-listing-geo-open ia-listing-geo-open--<?= ia_h($geoDensityTone) ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#iaListingGeoModal"
                                    data-listing-id="<?= (int) $row['id'] ?>"
                                    data-lat="<?= ia_h((string) $listingGeo['lat']) ?>"
                                    data-lng="<?= ia_h((string) $listingGeo['lng']) ?>"
                                    data-coords="<?= ia_h(ia_listing_geo_coords_text($listingGeo['lat'], $listingGeo['lng'])) ?>"
                                    data-meta="<?= ia_h(implode(' · ', $geoMeta)) ?>"
                                    data-area-count="<?= (int) $geoAreaCount ?>"
                                    data-density="<?= ia_h($geoDensityTone) ?>"
                                    data-map-url="<?= ia_h(ia_listing_geo_maps_url($listingGeo['lat'], $listingGeo['lng'])) ?>"
                                    aria-label="Показать место размещения объявления #<?= (int) $row['id'] ?>"
                                    title="<?= ia_h($geoDensityLabel) ?>"
                                >
                                    <i class="bi bi-car-front-fill" aria-hidden="true"></i>
                                </button>
                            <?php else: ?>
                                <span class="text-secondary small" title="Местоположение не зафиксировано">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="ia-listings-actions-cell">
                            <form method="post" class="ia-listings-actions ia-listings-actions-menu">
                                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                <input type="hidden" name="listing_id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="action" value="" class="js-action-value">
                                <?php $isPendingRow = $st === 'pending'; ?>
                                <?php if ($isPendingRow): ?>
                                    <div class="d-grid gap-1">
                                        <button type="button" class="btn btn-sm btn-success w-100 js-listing-post-action" data-action="approve">Подтвердить</button>
                                        <a class="btn btn-sm btn-warning w-100" href="<?= ia_h(ia_admin_url('listing-reject.php?id=' . (int) $row['id'])) ?>">Отклонить</a>
                                        <button type="button" class="btn btn-sm btn-outline-danger w-100 js-listing-post-action" data-action="purge" data-confirm="Удалить объявление безвозвратно? Фото и запись в базе будут удалены.">Удалить</button>
                                    </div>
                                <?php else: ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            Действия
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end ia-listings-action-dropdown">
                                            <li><button type="button" class="dropdown-item js-listing-post-action" data-action="approve">Подтвердить</button></li>
                                            <li><a class="dropdown-item" href="<?= ia_h(ia_admin_url('listing-reject.php?id=' . (int) $row['id'])) ?>">Отклонить</a></li>
                                            <li><a class="dropdown-item" href="<?= ia_h(ia_admin_url('listing-edit.php?id=' . (int) $row['id'])) ?>">Редактировать</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><button type="button" class="dropdown-item js-listing-post-action" data-action="toggle_vip">Переключить VIP</button></li>
                                            <li><button type="button" class="dropdown-item js-listing-post-action" data-action="toggle_top">Переключить Топ</button></li>
                                            <li><button type="button" class="dropdown-item js-listing-post-action" data-action="archive">В архив</button></li>
                                            <li><button type="button" class="dropdown-item js-listing-post-action" data-action="delete" data-confirm="Скрыть объявление из каталога? Данные в базе сохранятся.">Скрыть</button></li>
                                            <li><button type="button" class="dropdown-item text-danger js-listing-post-action" data-action="purge" data-confirm="Удалить объявление безвозвратно? Фото и запись в базе будут удалены.">Удалить</button></li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </form>
                            <?php if (!empty($row['rejection_reason'])): ?>
                                <div class="small text-danger mt-1">Причина: <?= ia_h((string) $row['rejection_reason']) ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="iaListingGeoModal" tabindex="-1" aria-labelledby="iaListingGeoModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="iaListingGeoModalTitle">Место размещения объявления</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary mb-2" id="iaListingGeoModalMeta"></p>
                    <div class="ia-listing-geo-legend small mb-2">
                        <span class="ia-listing-geo-legend-item"><span class="ia-geo-marker ia-geo-marker--car ia-geo-marker--car-selected ia-geo-marker--legend" aria-hidden="true"><i class="bi bi-car-front-fill"></i></span> выбранное объявление</span>
                        <span class="ia-listing-geo-legend-item"><span class="ia-geo-marker ia-geo-marker--car ia-geo-marker--car-sparse ia-geo-marker--legend" aria-hidden="true"><i class="bi bi-car-front-fill"></i></span> рядом мало объявлений</span>
                        <span class="ia-listing-geo-legend-item"><span class="ia-geo-marker ia-geo-marker--car ia-geo-marker--car-medium ia-geo-marker--legend" aria-hidden="true"><i class="bi bi-car-front-fill"></i></span> средняя плотность</span>
                        <span class="ia-listing-geo-legend-item"><span class="ia-geo-marker ia-geo-marker--car ia-geo-marker--car-dense ia-geo-marker--legend" aria-hidden="true"><i class="bi bi-car-front-fill"></i></span> много объявлений</span>
                    </div>
                    <div id="iaListingGeoModalMap" class="ia-listing-geo-modal-map" role="img" aria-label="Карта местоположения объявлений"></div>
                </div>
                <div class="modal-footer">
                    <a href="#" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary" id="iaListingGeoModalOpen">Открыть на карте</a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                </div>
            </div>
        </div>
    </div>
</main>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" crossorigin="anonymous"></script>
<script>
window.iaAdminListingGeoPoints = <?= json_encode($geoPoints, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.iaAdminListingGeoRadiusM = <?= json_encode($geoRadiusM, JSON_THROW_ON_ERROR) ?>;
</script>
<script>
(function () {
  var btns = document.querySelectorAll('.js-listing-post-action');
  btns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var form = btn.closest('form');
      if (!form) return;
      var action = String(btn.getAttribute('data-action') || '');
      if (!action) return;
      var ask = String(btn.getAttribute('data-confirm') || '');
      if (ask && !window.confirm(ask)) return;
      var hidden = form.querySelector('.js-action-value');
      if (!hidden) return;
      hidden.value = action;
      form.submit();
    });
  });

  var selectAll = document.getElementById('iaSelectAll');
  var checks = document.querySelectorAll('.js-bulk-check');
  var counter = document.getElementById('iaBulkCount');
  var bulkForm = document.getElementById('iaBulkForm');
  var bulkActionInput = document.getElementById('iaBulkActionInput');

  function refreshCount() {
    var n = 0;
    checks.forEach(function (c) { if (c.checked) n++; });
    if (counter) counter.textContent = String(n);
    if (selectAll) {
      selectAll.checked = (n > 0 && n === checks.length);
      selectAll.indeterminate = (n > 0 && n < checks.length);
    }
  }

  if (selectAll) {
    selectAll.addEventListener('change', function () {
      checks.forEach(function (c) { c.checked = selectAll.checked; });
      refreshCount();
    });
  }
  checks.forEach(function (c) { c.addEventListener('change', refreshCount); });

  document.querySelectorAll('.js-bulk-action').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!bulkForm || !bulkActionInput) return;
      var any = false;
      checks.forEach(function (c) { if (c.checked) any = true; });
      if (!any) {
        window.alert('Выберите хотя бы одно объявление.');
        return;
      }
      var ask = String(btn.getAttribute('data-bulk-confirm') || '');
      if (ask && !window.confirm(ask)) return;
      bulkActionInput.value = String(btn.getAttribute('data-bulk-action') || '');
      bulkForm.submit();
    });
  });

  refreshCount();

  var filterCollapse = document.getElementById('iaListingsFilters');
  if (filterCollapse) {
    filterCollapse.addEventListener('show.bs.collapse', function () {
      var head = document.querySelector('.ia-listings-filter-head');
      var chevron = document.querySelector('.ia-listings-filter-chevron');
      if (head) {
        head.classList.remove('ia-listings-filter-head--collapsed');
        head.classList.add('mb-3');
      }
      if (chevron) chevron.classList.add('ia-listings-filter-chevron--open');
    });
    filterCollapse.addEventListener('hide.bs.collapse', function () {
      var head = document.querySelector('.ia-listings-filter-head');
      var chevron = document.querySelector('.ia-listings-filter-chevron');
      if (head) {
        head.classList.add('ia-listings-filter-head--collapsed');
        head.classList.remove('mb-3');
      }
      if (chevron) chevron.classList.remove('ia-listings-filter-chevron--open');
    });
  }

  var geoModal = document.getElementById('iaListingGeoModal');
  var geoMap = null;
  var geoMarkerLayer = null;
  var geoPoints = Array.isArray(window.iaAdminListingGeoPoints) ? window.iaAdminListingGeoPoints : [];
  var geoRadiusM = Number(window.iaAdminListingGeoRadiusM || 1000);

  function geoHaversineM(lat1, lng1, lat2, lng2) {
    var earthRadius = 6371000;
    var p1 = lat1 * Math.PI / 180;
    var p2 = lat2 * Math.PI / 180;
    var dP = (lat2 - lat1) * Math.PI / 180;
    var dL = (lng2 - lng1) * Math.PI / 180;
    var a = Math.sin(dP / 2) * Math.sin(dP / 2) + Math.cos(p1) * Math.cos(p2) * Math.sin(dL / 2) * Math.sin(dL / 2);
    return 2 * earthRadius * Math.asin(Math.min(1, Math.sqrt(a)));
  }

  function geoAreaCount(lat, lng) {
    var count = 0;
    geoPoints.forEach(function (point) {
      if (geoHaversineM(lat, lng, Number(point.lat), Number(point.lng)) <= geoRadiusM) {
        count++;
      }
    });
    return count;
  }

  function geoDensityTone(areaCount) {
    if (areaCount <= 2) return 'sparse';
    if (areaCount <= 5) return 'medium';
    return 'dense';
  }

  function geoCarIcon(tone) {
    return L.divIcon({
      className: 'ia-geo-marker ia-geo-marker--car ia-geo-marker--car-' + tone,
      html: '<i class="bi bi-car-front-fill" aria-hidden="true"></i>',
      iconSize: [30, 30],
      iconAnchor: [15, 15],
      popupAnchor: [0, -14]
    });
  }

  function geoRadiusLabel() {
    var km = geoRadiusM / 1000;
    return Math.abs(km - 1) < 0.01 ? '1 км' : km.toFixed(1).replace('.', ',') + ' км';
  }

  if (geoModal && window.L) {
    geoModal.addEventListener('shown.bs.modal', function (ev) {
      var trigger = ev.relatedTarget;
      if (!trigger || !trigger.classList.contains('ia-listing-geo-open')) return;
      var lat = Number(trigger.getAttribute('data-lat'));
      var lng = Number(trigger.getAttribute('data-lng'));
      var listingId = Number(trigger.getAttribute('data-listing-id'));
      var coords = String(trigger.getAttribute('data-coords') || '');
      var meta = String(trigger.getAttribute('data-meta') || '');
      var mapUrl = String(trigger.getAttribute('data-map-url') || '#');
      var areaCount = Number(trigger.getAttribute('data-area-count') || geoAreaCount(lat, lng));
      var metaEl = document.getElementById('iaListingGeoModalMeta');
      var mapEl = document.getElementById('iaListingGeoModalMap');
      var openEl = document.getElementById('iaListingGeoModalOpen');
      if (!mapEl || !isFinite(lat) || !isFinite(lng)) return;

      if (!geoMap) {
        geoMap = L.map(mapEl, { scrollWheelZoom: true }).setView([lat, lng], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; OpenStreetMap'
        }).addTo(geoMap);
        geoMarkerLayer = L.layerGroup().addTo(geoMap);
      } else {
        geoMap.setView([lat, lng], 14);
        geoMarkerLayer.clearLayers();
      }

      L.circle([lat, lng], {
        radius: geoRadiusM,
        color: '#dc2626',
        fillColor: '#dc2626',
        fillOpacity: 0.08,
        weight: 1.5
      }).addTo(geoMarkerLayer);

      var markerBounds = [];
      geoPoints.forEach(function (point) {
        var pointLat = Number(point.lat);
        var pointLng = Number(point.lng);
        if (geoHaversineM(lat, lng, pointLat, pointLng) > geoRadiusM) return;
        var localCount = geoAreaCount(pointLat, pointLng);
        var tone = Number(point.id) === listingId ? 'selected' : geoDensityTone(localCount);
        L.marker([pointLat, pointLng], { icon: geoCarIcon(tone) })
          .addTo(geoMarkerLayer)
          .bindPopup('<strong>#' + point.id + '</strong><br>' + String(point.label || 'Объявление'));
        markerBounds.push([pointLat, pointLng]);
      });

      if (markerBounds.length > 1) {
        geoMap.fitBounds(markerBounds, { padding: [28, 28], maxZoom: 15 });
      }

      if (metaEl) {
        var metaLine = meta ? (coords + ' · ' + meta) : coords;
        metaEl.textContent = metaLine + ' · В радиусе ' + geoRadiusLabel() + ': ' + areaCount + ' объявл.';
      }
      if (openEl) {
        openEl.href = mapUrl;
      }
      geoMap.invalidateSize();
    });
  }
})();
</script>
<?php require __DIR__ . '/partials/foot.php'; ?>
