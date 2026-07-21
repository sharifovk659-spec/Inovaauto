<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/public_queries.php';

$pdo = ia_db();
$cu = ia_platform_current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = (string) ($_POST['action'] ?? '');
    if ($postAction === 'toggle_fav') {
        $isAjaxFav = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        $favOk = false;
        $favMsg = '';
        $favLid = 0;
        $favIsActive = false;
        if ($cu === null) {
            $favMsg = 'Войдите, чтобы добавить в избранное.';
            if (!$isAjaxFav) {
                ia_flash('pub_error', $favMsg);
            }
        } elseif (!Csrf::validate($_POST['_csrf'] ?? null)) {
            $favMsg = 'Сессия устарела.';
            if (!$isAjaxFav) {
                ia_flash('pub_error', $favMsg);
            }
        } else {
            $favLid = (int) ($_POST['listing_id'] ?? 0);
            if ($favLid > 0) {
                $favIsActive = ia_pub_toggle_favorite($pdo, (int) $cu['id'], $favLid);
                $favOk = true;
                $favMsg = 'Избранное обновлено.';
                if (!$isAjaxFav) {
                    ia_flash('pub_ok', $favMsg);
                }
            }
        }
        if ($isAjaxFav) {
            header('Content-Type: application/json; charset=utf-8');
            if ($favOk && $cu !== null) {
                echo json_encode([
                    'ok' => true,
                    'is_fav' => $favIsActive,
                    'listing_id' => $favLid,
                    'fav_count' => ia_pub_favorite_visible_count($pdo, (int) $cu['id']),
                    'message' => $favMsg,
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'ok' => false,
                    'login_required' => $cu === null,
                    'message' => $favMsg !== '' ? $favMsg : 'Не удалось обновить избранное.',
                ], JSON_UNESCAPED_UNICODE);
            }
            exit;
        }
        $qs = (string) ($_POST['return_qs'] ?? '');
        $target = ia_public_url('catalog.php');
        if ($qs !== '') {
            $target .= '?' . ltrim($qs, '?');
        }
        ia_redirect($target);
    } elseif ($postAction === 'toggle_compare') {
        if (Csrf::validate($_POST['_csrf'] ?? null)) {
            $lid = (int) ($_POST['listing_id'] ?? 0);
            if ($lid > 0) {
                $r = ia_pub_toggle_compare($pdo, $cu ? (int) $cu['id'] : 0, $lid);
                ia_flash('pub_ok', ($r['action'] ?? '') === 'added' ? 'Добавлено к сравнению.' : 'Убрано из сравнения.');
            }
        }
        $qs = (string) ($_POST['return_qs'] ?? '');
        $target = ia_public_url('catalog.php');
        if ($qs !== '') {
            $target .= '?' . ltrim($qs, '?');
        }
        ia_redirect($target);
    }
}
$brands = ia_pub_brands_ordered($pdo);
$brandId = (int) ($_GET['brand_id'] ?? 0);
$modelId = (int) ($_GET['model_id'] ?? 0);
$brandName = '';
$modelName = '';
if ($brandId > 0) {
    $st = $pdo->prepare('SELECT name FROM car_brands WHERE id = ?');
    $st->execute([$brandId]);
    $brandName = (string) $st->fetchColumn();
}
if ($brandId <= 0) {
    $modelId = 0;
}
if ($modelId > 0 && $brandId > 0) {
    $st = $pdo->prepare('SELECT name FROM car_models WHERE id = ? AND brand_id = ?');
    $st->execute([$modelId, $brandId]);
    $modelName = (string) $st->fetchColumn();
    if ($modelName === '') {
        $modelId = 0;
    }
}

require_once IA_ROOT . '/includes/tj_cities.php';
$catalogCityFilter = ia_tj_city_normalize((string) ($_GET['city'] ?? ''));

$data = ia_pub_listings_catalog($pdo, [
    'q' => (string) ($_GET['q'] ?? ''),
    'brand' => $brandName,
    'model' => $modelName,
    'price_min' => $_GET['price_min'] ?? '',
    'price_max' => $_GET['price_max'] ?? '',
    'year' => (string) ($_GET['year'] ?? ''),
    'mileage_max' => (string) ($_GET['mileage_max'] ?? ''),
    'fuel_type' => (string) ($_GET['fuel_type'] ?? ''),
    'transmission' => (string) ($_GET['transmission'] ?? ''),
    'city' => $catalogCityFilter,
    'body_type' => (string) ($_GET['body_type'] ?? ''),
    'availability' => (string) ($_GET['availability'] ?? ''),
    'sort' => (string) ($_GET['sort'] ?? 'new'),
    'page' => (int) ($_GET['page'] ?? 1),
    'per' => 12,
]);
$rows = $data['rows'];
$total = $data['total'];
$catalogFuzzy = !empty($data['fuzzy']);
$catalogFuzzyQ = (string) ($data['fuzzy_query'] ?? '');
$searchQ = trim((string) ($_GET['q'] ?? ''));
$catalogBodyType = trim((string) ($_GET['body_type'] ?? ''));
$suggestRows = [];
if (count($rows) === 0 && $searchQ !== '' && $catalogBodyType === '') {
    $suggestRows = ia_pub_listings_search_suggestions($pdo, $searchQ, 8);
}
$gridRows = count($rows) > 0 ? $rows : $suggestRows;
$catalogSuggest = count($rows) === 0 && $suggestRows !== [];
$listingThumbs = ia_pub_listing_thumbs_for_ids($pdo, array_map(static fn ($r): int => (int) ($r['id'] ?? 0), $gridRows));
$per = 12;
$page = max(1, (int) ($_GET['page'] ?? 1));
$pages = max(1, (int) ceil($total / $per));
$favMap = [];
if ($cu !== null) {
    $favMap = array_fill_keys(ia_pub_favorite_ids($pdo, (int) $cu['id']), true);
}

$pageTitle = 'Каталог';
$iaBodyExtraClass = 'ia-page-catalog';
if (!function_exists('ia_listing_catalog_count_word_ru')) {
    function ia_listing_catalog_count_word_ru(int $count): string
    {
        $n = abs($count) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) {
            return 'автомобилей';
        }
        if ($n1 === 1) {
            return 'автомобиль';
        }
        if ($n1 >= 2 && $n1 <= 4) {
            return 'автомобиля';
        }

        return 'автомобилей';
    }
}
$catalogBodyTypeLabel = $catalogBodyType !== '' ? (ia_listing_body_types_map()[$catalogBodyType] ?? '') : '';
$catalogReturnQs = (string) ($_SERVER['QUERY_STRING'] ?? '');
$catalogSort = (string) ($_GET['sort'] ?? 'new');
$catalogFoundWord = ia_listing_catalog_count_word_ru($total);
$catalogCardPartial = IA_ROOT . '/includes/partials/catalog-listing-card.php';
$catalogHasCardPartial = is_file($catalogCardPartial);
$catalogFiltersActive = $brandId > 0
    || $modelId > 0
    || $searchQ !== ''
    || $catalogBodyType !== ''
    || trim((string) ($_GET['price_min'] ?? '')) !== ''
    || trim((string) ($_GET['price_max'] ?? '')) !== ''
    || trim((string) ($_GET['year'] ?? '')) !== ''
    || trim((string) ($_GET['mileage_max'] ?? '')) !== ''
    || trim((string) ($_GET['fuel_type'] ?? '')) !== ''
    || trim((string) ($_GET['transmission'] ?? '')) !== ''
    || trim((string) ($_GET['city'] ?? '')) !== ''
    || trim((string) ($_GET['availability'] ?? '')) !== ''
    || (($_GET['sort'] ?? 'new') !== 'new');

require IA_ROOT . '/includes/partials/site-header.php';
?>

<section class="py-4 py-lg-5 ia-page-section ia-catalog-page-section">
    <div class="container ia-container ia-catalog-shell">
        <?php if ($msg = ia_flash('pub_ok')): ?><div class="alert alert-success mb-3"><?= ia_h((string) $msg) ?></div><?php endif; ?>
        <?php if ($msg = ia_flash('pub_error')): ?><div class="alert alert-danger mb-3"><?= ia_h((string) $msg) ?></div><?php endif; ?>

        <div class="ia-catalog-head">
            <h1 class="ia-catalog-page-title mb-0">
                <span class="ia-catalog-head-ico" aria-hidden="true"><i class="bi bi-grid-1x2-fill"></i></span>
                Каталог
            </h1>
            <button
                class="ia-catalog-mob-filter-btn d-lg-none"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#iaCatalogFilterPanel"
                aria-expanded="false"
                aria-controls="iaCatalogFilterPanel"
            >
                <span class="ia-catalog-mob-filter-btn__ico" aria-hidden="true"><i class="bi bi-sliders"></i></span>
                <span class="ia-catalog-mob-filter-btn__text">Фильтры</span>
            </button>
        </div>

        <form class="ia-catalog-form" method="get" action="">
            <?php if ($catalogBodyType !== ''): ?>
                <input type="hidden" name="body_type" value="<?= ia_h($catalogBodyType) ?>">
            <?php endif; ?>

            <div id="iaCatalogFilterPanel" class="collapse ia-catalog-filter-panel">
            <div class="ia-catalog-quick-bar">
                <div class="ia-catalog-quick-field">
                    <label class="ia-catalog-quick-label" for="catalogBrand">Марка</label>
                    <select name="brand_id" id="catalogBrand" class="form-select form-select-sm ia-catalog-quick-control" onchange="this.form.submit()">
                        <option value="0">Все</option>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?= (int) $b['id'] ?>" <?= $brandId === (int) $b['id'] ? 'selected' : '' ?>><?= ia_h((string) $b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ia-catalog-quick-field">
                    <label class="ia-catalog-quick-label" for="catalogModel">Модель</label>
                    <select name="model_id" id="catalogModel" class="form-select form-select-sm ia-catalog-quick-control" <?= $brandId <= 0 ? 'disabled' : '' ?> onchange="this.form.submit()">
                        <option value="0">Все</option>
                        <?php if ($brandId > 0): ?>
                            <?php foreach (ia_pub_models_for_brand($pdo, $brandId) as $m): ?>
                                <option value="<?= (int) $m['id'] ?>" <?= $modelId === (int) $m['id'] ? 'selected' : '' ?>><?= ia_h((string) $m['name']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="ia-catalog-quick-field ia-catalog-quick-field--year">
                    <label class="ia-catalog-quick-label" for="catalogYear">Год</label>
                    <?php
                    $iaYearSelectName = 'year';
                    $iaYearSelectId = 'catalogYear';
                    $iaYearSelectClass = 'form-select form-select-sm ia-catalog-quick-control';
                    $iaYearSelectValue = (string) ($_GET['year'] ?? '');
                    $iaYearSelectEmpty = 'Все';
                    require IA_ROOT . '/includes/partials/year-select.php';
                    ?>
                </div>
                <div class="ia-catalog-quick-field ia-catalog-quick-field--price">
                    <label class="ia-catalog-quick-label">Цена (TJS)</label>
                    <div class="ia-catalog-price-range">
                        <input type="number" name="price_min" class="form-control form-control-sm ia-catalog-quick-control" value="<?= ia_h((string) ($_GET['price_min'] ?? '')) ?>" min="0" step="1000" placeholder="от" aria-label="Цена от">
                        <input type="number" name="price_max" class="form-control form-control-sm ia-catalog-quick-control" value="<?= ia_h((string) ($_GET['price_max'] ?? '')) ?>" min="0" step="1000" placeholder="до" aria-label="Цена до">
                    </div>
                </div>
                <button
                    class="ia-catalog-more-filters btn btn-sm d-none d-lg-inline-flex<?= $catalogFiltersActive ? ' is-active' : '' ?>"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#iaCatalogFilters"
                    aria-expanded="false"
                    aria-controls="iaCatalogFilters"
                >
                    <i class="bi bi-funnel" aria-hidden="true"></i>
                    <span>Фильтры</span>
                </button>
                <button type="submit" class="ia-catalog-quick-submit btn btn-sm ia-btn-accent d-none d-md-inline-flex">Найти</button>
            </div>

            <div id="iaCatalogFilters" class="collapse ia-catalog-extended">
                <div class="ia-catalog-extended-inner">
                    <div class="ia-catalog-extended-grid">
                        <div class="ia-catalog-field">
                            <label class="ia-catalog-label" for="catalogMileage">Пробег, км</label>
                            <input type="number" name="mileage_max" id="catalogMileage" class="form-control form-control-sm" value="<?= ia_h((string) ($_GET['mileage_max'] ?? '')) ?>" min="0" step="1000" placeholder="макс.">
                        </div>
                        <div class="ia-catalog-field">
                            <label class="ia-catalog-label" for="catalogFuel">Топливо</label>
                            <?php $fuel = (string) ($_GET['fuel_type'] ?? ''); ?>
                            <select name="fuel_type" id="catalogFuel" class="form-select form-select-sm">
                                <option value="">Все</option>
                                <option value="petrol" <?= $fuel === 'petrol' ? 'selected' : '' ?>>Бензин</option>
                                <option value="diesel" <?= $fuel === 'diesel' ? 'selected' : '' ?>>Дизель</option>
                                <option value="gas" <?= $fuel === 'gas' ? 'selected' : '' ?>>Газ</option>
                                <option value="hybrid" <?= $fuel === 'hybrid' ? 'selected' : '' ?>>Гибрид</option>
                                <option value="electric" <?= $fuel === 'electric' ? 'selected' : '' ?>>Электро</option>
                            </select>
                        </div>
                        <div class="ia-catalog-field">
                            <label class="ia-catalog-label" for="catalogTrans">Коробка</label>
                            <?php $tr = (string) ($_GET['transmission'] ?? ''); ?>
                            <select name="transmission" id="catalogTrans" class="form-select form-select-sm">
                                <option value="">Все</option>
                                <option value="auto" <?= $tr === 'auto' ? 'selected' : '' ?>>Автомат</option>
                                <option value="manual" <?= $tr === 'manual' ? 'selected' : '' ?>>Механика</option>
                                <option value="robot" <?= $tr === 'robot' ? 'selected' : '' ?>>Робот</option>
                                <option value="cvt" <?= $tr === 'cvt' ? 'selected' : '' ?>>CVT</option>
                            </select>
                        </div>
                        <div class="ia-catalog-field">
                            <label class="ia-catalog-label" for="catalogCity">Город</label>
                            <?php
                            $iaCitySelectName = 'city';
                            $iaCitySelectId = 'catalogCity';
                            $iaCitySelectClass = 'form-select form-select-sm';
                            $iaCitySelectValue = ia_tj_city_normalize((string) ($_GET['city'] ?? ''));
                            $iaCitySelectEmpty = '— любой —';
                            $iaCitySelectAllowLegacy = true;
                            require IA_ROOT . '/includes/partials/city-select.php';
                            ?>
                        </div>
                        <div class="ia-catalog-field">
                            <label class="ia-catalog-label" for="catalogAvail">Наличие</label>
                            <?php $avCat = trim((string) ($_GET['availability'] ?? '')); ?>
                            <select name="availability" id="catalogAvail" class="form-select form-select-sm">
                                <option value="">Все</option>
                                <option value="in_stock" <?= $avCat === 'in_stock' ? 'selected' : '' ?>>В наличии</option>
                                <option value="on_order" <?= $avCat === 'on_order' ? 'selected' : '' ?>>На заказ</option>
                            </select>
                        </div>
                        <div class="ia-catalog-field ia-catalog-field--q">
                            <label class="ia-catalog-label" for="catalogQ">Поиск</label>
                            <input type="search" name="q" id="catalogQ" class="form-control form-control-sm" value="<?= ia_h($searchQ) ?>" placeholder="бренд / модель / продавец">
                        </div>
                    </div>
                    <div class="ia-catalog-extended-actions">
                        <button type="submit" class="btn btn-sm ia-btn-accent ia-catalog-apply-btn">Применить</button>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= ia_h(ia_public_url('catalog.php')) ?>">Сбросить</a>
                    </div>
                </div>
            </div>
            </div>

            <div class="ia-catalog-toolbar">
                <p class="ia-catalog-found mb-0">
                    Найдено: <strong><?= (int) $total ?></strong> <?= ia_h($catalogFoundWord) ?><?= $catalogFuzzy ? ' <span class="text-secondary">(похожие)</span>' : '' ?>
                </p>
                <div class="ia-catalog-toolbar-right">
                    <label class="ia-catalog-sort-label" for="catalogSort">Сортировка:</label>
                    <select name="sort" id="catalogSort" class="form-select form-select-sm ia-catalog-sort" onchange="this.form.submit()">
                        <option value="new" <?= $catalogSort === 'new' ? 'selected' : '' ?>>Новые сверху</option>
                        <option value="price_asc" <?= $catalogSort === 'price_asc' ? 'selected' : '' ?>>Дешевле</option>
                        <option value="price_desc" <?= $catalogSort === 'price_desc' ? 'selected' : '' ?>>Дороже</option>
                        <option value="mileage_asc" <?= $catalogSort === 'mileage_asc' ? 'selected' : '' ?>>По пробегу</option>
                    </select>
                </div>
            </div>
        </form>

        <?php if ($catalogFuzzy && $catalogFuzzyQ !== '' && $catalogBodyType === ''): ?>
            <div class="alert alert-info ia-catalog-fuzzy-hint py-2 px-3 mb-3 small" role="status">
                Точных совпадений по запросу «<?= ia_h($catalogFuzzyQ) ?>» нет. Показаны похожие автомобили по марке или модели.
            </div>
        <?php endif; ?>

        <?php if (count($rows) === 0): ?>
            <div class="ia-catalog-empty" role="status">
                <div class="ia-catalog-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="48" height="48"><path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                </div>
                <?php if ($catalogBodyTypeLabel !== ''): ?>
                    <h2 class="h5 mb-2">В категории «<?= ia_h($catalogBodyTypeLabel) ?>» пока нет объявлений</h2>
                    <p class="text-secondary mb-3">Попробуйте другую категорию или откройте весь каталог.</p>
                <?php else: ?>
                    <h2 class="h5 mb-2">По вашему запросу ничего не найдено</h2>
                    <p class="text-secondary mb-3">Измените фильтры, сортировку или сбросьте поиск и откройте каталог заново.</p>
                <?php endif; ?>
                <a class="btn ia-btn-accent" href="<?= ia_h(ia_public_url('catalog.php')) ?>">Сбросить фильтры</a>
            </div>
        <?php endif; ?>

        <?php if (count($gridRows) > 0): ?>
            <?php if ($catalogSuggest): ?>
                <div class="ia-catalog-suggest mt-2 mb-3">
                    <h2 class="h6 text-uppercase text-secondary mb-0">Возможно, вас заинтересует</h2>
                </div>
            <?php endif; ?>
            <div class="ia-catalog-grid<?= $catalogSuggest ? ' ia-catalog-suggest-grid' : '' ?>">
                <?php if (!$catalogHasCardPartial): ?>
                    <div class="col-12">
                        <div class="alert alert-warning mb-0">
                            Не найден файл карточки каталога. Загрузите на сервер:
                            <code>includes/partials/catalog-listing-card.php</code>
                        </div>
                    </div>
                <?php else: ?>
                <?php $catalogCardIdx = 0; foreach ($gridRows as $row): ?>
                    <?php require $catalogCardPartial; ?>
                <?php $catalogCardIdx++; endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($pages > 1 && count($rows) > 0): ?>
                <nav class="ia-catalog-pagination mt-4" aria-label="Страницы каталога">
                    <?php
                    $qs = $_GET;
                    for ($p = 1; $p <= $pages; $p++) {
                        $qs['page'] = (string) $p;
                        $url = ia_public_url('catalog.php?' . http_build_query($qs));
                        ?>
                        <a class="btn btn-sm <?= $p === $page ? 'ia-btn-accent' : 'btn-outline-secondary' ?>" href="<?= ia_h($url) ?>"><?= $p ?></a>
                    <?php } ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require IA_ROOT . '/includes/partials/site-footer.php'; ?>
<script>
(function () {
  var panel = document.getElementById('iaCatalogFilterPanel');
  var inner = document.getElementById('iaCatalogFilters');
  var mobBtn = document.querySelector('.ia-catalog-mob-filter-btn');
  if (!panel || typeof bootstrap === 'undefined' || !bootstrap.Collapse) {
    return;
  }

  var isMobile = function () {
    return window.matchMedia('(max-width: 991.98px)').matches;
  };

  var syncMobBtn = function () {
    if (!mobBtn) {
      return;
    }
    var open = panel.classList.contains('show');
    mobBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    mobBtn.classList.toggle('is-open', open);
  };

  panel.addEventListener('shown.bs.collapse', syncMobBtn);
  panel.addEventListener('hidden.bs.collapse', syncMobBtn);

  panel.addEventListener('show.bs.collapse', function () {
    if (!isMobile() || !inner) {
      return;
    }
    var innerCollapse = bootstrap.Collapse.getOrCreateInstance(inner, { toggle: false });
    if (!inner.classList.contains('show')) {
      innerCollapse.show();
    }
  });

  syncMobBtn();

  var moreBtn = document.querySelector('.ia-catalog-more-filters');
  if (inner && moreBtn) {
    inner.addEventListener('shown.bs.collapse', function () {
      moreBtn.setAttribute('aria-expanded', 'true');
    });
    inner.addEventListener('hidden.bs.collapse', function () {
      moreBtn.setAttribute('aria-expanded', 'false');
    });
  }
})();

(function () {
  var favLabels = {
    add: 'Добавить в избранное',
    remove: 'Убрать из избранного'
  };

  var syncFavBadges = function (count) {
    document.querySelectorAll('.ia-nav-icon-badge, .ia-mobile-header-badge').forEach(function (badge) {
      var n = Math.max(0, count | 0);
      if (n > 0) {
        badge.textContent = String(n);
        badge.hidden = false;
        badge.style.display = '';
      } else {
        badge.textContent = '';
        badge.hidden = true;
        badge.style.display = 'none';
      }
    });
    document.querySelectorAll('.ia-mobile-header-btn--fav, .ia-nav-icon-btn[aria-label^="Избранное"]').forEach(function (link) {
      var n = Math.max(0, count | 0);
      link.setAttribute('aria-label', n > 0 ? ('Избранное (' + n + ')') : 'Избранное');
    });
  };

  document.querySelectorAll('.ia-page-catalog .ia-card-action-form').forEach(function (form) {
    var actionInput = form.querySelector('input[name="action"]');
    if (!actionInput || actionInput.value !== 'toggle_fav') {
      return;
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var btn = form.querySelector('.ia-card-icon-btn--fav');
      if (!btn || btn.disabled) {
        return;
      }

      btn.disabled = true;
      fetch(form.getAttribute('action') || window.location.href, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (!data || !data.ok) {
            if (data && data.login_required) {
              window.location.href = <?= json_encode(ia_public_url('login.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> + '?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
              return;
            }
            if (data && data.message) {
              window.alert(data.message);
            }
            return;
          }

          var isFav = !!data.is_fav;
          btn.classList.toggle('is-active', isFav);
          btn.setAttribute('aria-label', isFav ? favLabels.remove : favLabels.add);
          btn.setAttribute('title', isFav ? favLabels.remove : favLabels.add);
          if (typeof data.fav_count === 'number') {
            syncFavBadges(data.fav_count);
          }
        })
        .catch(function () {
          form.submit();
        })
        .finally(function () {
          btn.disabled = false;
        });
    });
  });
})();
</script>
