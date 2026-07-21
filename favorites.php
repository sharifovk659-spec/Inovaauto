<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/public_queries.php';

ia_platform_require_login();

$pdo = ia_db();
$uid = (int) ia_platform_current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = (string) ($_POST['action'] ?? '');
    if (Csrf::validate($_POST['_csrf'] ?? null)) {
        $lid = (int) ($_POST['listing_id'] ?? 0);
        if ($postAction === 'toggle_fav' && $lid > 0) {
            ia_pub_toggle_favorite($pdo, $uid, $lid);
            ia_flash('pub_ok', 'Избранное обновлено.');
        } elseif ($postAction === 'toggle_compare' && $lid > 0) {
            $r = ia_pub_toggle_compare($pdo, $uid, $lid);
            ia_flash('pub_ok', ($r['action'] ?? '') === 'added' ? 'Добавлено к сравнению.' : 'Убрано из сравнения.');
        }
    } else {
        ia_flash('pub_error', 'Сессия устарела.');
    }
    ia_redirect(ia_public_url('favorites.php'));
}

ia_pub_prune_orphan_favorites($pdo, $uid);
$favorites = ia_pub_favorites_for_user($pdo, $uid);
$rows = $favorites['visible'];
$hiddenRows = $favorites['hidden'];
$listingThumbs = ia_pub_listing_thumbs_for_ids($pdo, array_map(static fn ($r): int => (int) ($r['id'] ?? 0), $rows));

$pageTitle = 'Избранное';
$iaBodyExtraClass = 'ia-page-favorites';

require IA_ROOT . '/includes/partials/site-header.php';
?>

<section class="py-5 ia-page-section">
    <div class="container ia-container">
        <?php if ($msg = ia_flash('pub_ok')): ?><div class="alert alert-success mb-3"><?= ia_h((string) $msg) ?></div><?php endif; ?>
        <?php if ($msg = ia_flash('pub_error')): ?><div class="alert alert-danger mb-3"><?= ia_h((string) $msg) ?></div><?php endif; ?>

        <h1 class="h4 mb-4">Избранное</h1>

        <?php if (count($rows) === 0 && count($hiddenRows) === 0): ?>
            <p class="text-secondary mb-3">Пока пусто. Добавляйте объявления со страницы автомобиля или из каталога.</p>
            <a href="<?= ia_h(ia_public_url('catalog.php')) ?>" class="btn btn-outline-secondary btn-sm">В каталог</a>
        <?php elseif (count($rows) === 0): ?>
            <p class="text-secondary mb-3">Активных объявлений в избранном нет. Ниже — снятые с публикации или на модерации.</p>
        <?php endif; ?>

        <?php if (count($rows) > 0): ?>
            <div class="row g-4">
                <?php foreach ($rows as $row): ?>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <article class="ia-listing-card ia-listing-card--catalog">
                            <?php
                            $ph = ia_listing_photo_src($row['photo_url'] ?? null);
                            $cardThumbs = $listingThumbs[(int) $row['id']] ?? [];
                            $hoverThumbs = $cardThumbs;
                            if (!empty($hoverThumbs) && (!isset($hoverThumbs[0]) || $hoverThumbs[0] !== $ph) && $ph !== '') {
                                array_unshift($hoverThumbs, $ph);
                                $hoverThumbs = array_values(array_unique($hoverThumbs));
                            }
                            $thumbsAttr = (count($hoverThumbs) > 1)
                                ? json_encode(array_slice($hoverThumbs, 0, 6), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                                : null;
                            ?>
                            <a href="<?= ia_h(ia_public_url('car.php?id=' . (int) $row['id'])) ?>" class="ia-listing-card-img-wrap ia-listing-card-img-wrap--square ia-card-hover<?= $thumbsAttr ? ' has-hover-thumbs' : '' ?>"<?= $thumbsAttr ? ' data-thumbs=' . "'" . htmlspecialchars($thumbsAttr, ENT_QUOTES, 'UTF-8') . "'" : '' ?>>
                                <img class="ia-listing-card-img" src="<?= ia_h($ph) ?>" alt="" <?= ia_img_perf_attrs(['width' => 480, 'height' => 480]) ?>>
                                <?php ia_render_listing_card_badges($row); ?>
<?php ia_render_listing_views_badge($row); ?>
                                <?php if ($thumbsAttr): ?>
                                    <span class="ia-card-hover-dots" aria-hidden="true">
                                        <?php foreach ($hoverThumbs as $hi => $_): ?><span class="ia-card-hover-dot<?= $hi === 0 ? ' is-active' : '' ?>"></span><?php endforeach; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <div class="ia-card-actions">
                                <form method="post" class="ia-card-action-form">
                                    <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                    <input type="hidden" name="action" value="toggle_fav">
                                    <input type="hidden" name="listing_id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="ia-card-icon-btn ia-card-icon-btn--fav is-active" aria-label="Убрать из избранного" title="Убрать из избранного">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 21.2l-1.45-1.32C5.4 15.25 2 12.16 2 8.5 2 5.41 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.41 22 8.5c0 3.66-3.4 6.75-8.55 11.39z"/></svg>
                                    </button>
                                </form>
                            </div>
                            <div class="p-3 flex-grow-1 d-flex flex-column">
                                <a class="text-decoration-none ia-listing-title-link fw-semibold d-block mb-1 ia-card-title-clamp" href="<?= ia_h(ia_public_url('car.php?id=' . (int) $row['id'])) ?>"><?= ia_h((string) $row['brand'] . ' ' . (string) $row['model']) ?></a>
                                <?php $favAvail = ia_listing_availability_normalize((string) ($row['availability'] ?? '')); ?>
                                <div class="mb-2">
                                    <span class="ia-badge-availability <?= $favAvail === 'on_order' ? 'ia-badge-availability--on-order' : 'ia-badge-availability--in-stock' ?>"><?= ia_h(ia_listing_availability_label_ru($favAvail)) ?></span>
                                </div>
                                <div class="ia-price mb-1"><?= ia_h(ia_listing_format_price((float) $row['price'], (string) ($row['currency'] ?? 'TJS'))) ?></div>
                                <div class="small text-secondary mt-auto">
                                    <?= (int) ($row['model_year'] ?? 0) >= 1950 ? 'Год: ' . (int) $row['model_year'] : '' ?>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (count($hiddenRows) > 0): ?>
            <div class="ia-fav-hidden mt-4 pt-3 border-top">
                <h2 class="h6 text-secondary mb-3">Недоступные объявления</h2>
                <ul class="list-group ia-fav-hidden-list">
                    <?php foreach ($hiddenRows as $hidden): ?>
                        <?php
                        $hid = (int) ($hidden['listing_id'] ?? 0);
                        $hStatus = (string) ($hidden['status'] ?? '');
                        $hLabel = ia_pub_listing_status_ru($hStatus);
                        if ($hLabel === $hStatus && $hStatus !== '') {
                            $hLabel = 'Недоступно';
                        }
                        ?>
                        <li class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <span>
                                <span class="fw-semibold"><?= ia_h(trim((string) ($hidden['brand'] ?? '') . ' ' . (string) ($hidden['model'] ?? ''))) ?></span>
                                <span class="badge text-bg-secondary ms-1"><?= ia_h($hLabel) ?></span>
                            </span>
                            <form method="post" class="m-0">
                                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                <input type="hidden" name="action" value="toggle_fav">
                                <input type="hidden" name="listing_id" value="<?= $hid ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm">Убрать</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require IA_ROOT . '/includes/partials/site-footer.php'; ?>
