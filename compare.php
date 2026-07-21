<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/public_queries.php';

$pdo = ia_db();
$cu = ia_platform_current_user();
$uid = $cu ? (int) $cu['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (Csrf::validate($_POST['_csrf'] ?? null)) {
        if ($action === 'remove') {
            ia_pub_toggle_compare($pdo, $uid, (int) ($_POST['listing_id'] ?? 0));
            ia_flash('pub_ok', 'Убрано из сравнения.');
        } elseif ($action === 'clear') {
            ia_pub_compare_clear($pdo, $uid);
            ia_flash('pub_ok', 'Список сравнения очищен.');
        }
    }
    ia_redirect(ia_public_url('compare.php'));
}

$ids = ia_pub_compare_ids($pdo, $uid);
$items = ia_pub_compare_listings($pdo, $ids);

$pageTitle = 'Сравнение автомобилей';
$iaBodyExtraClass = 'ia-page-compare';

$rows = [
    ['key' => 'price', 'label' => 'Цена', 'fmt' => 'price'],
    ['key' => 'availability', 'label' => 'Наличие', 'fmt' => 'availability'],
    ['key' => 'prepayment_amount', 'label' => 'Предоплата', 'fmt' => 'prepay'],
    ['key' => 'model_year', 'label' => 'Год выпуска', 'fmt' => 'int_or_dash'],
    ['key' => 'mileage_km', 'label' => 'Пробег', 'fmt' => 'mileage'],
    ['key' => 'body_type', 'label' => 'Кузов', 'fmt' => 'body_ru'],
    ['key' => 'color', 'label' => 'Цвет', 'fmt' => 'string'],
    ['key' => 'drive_type', 'label' => 'Привод', 'fmt' => 'drive_ru'],
    ['key' => 'engine_volume', 'label' => 'Объём двигателя', 'fmt' => 'string'],
    ['key' => 'fuel_type', 'label' => 'Вид топлива', 'fmt' => 'fuel_ru'],
    ['key' => 'transmission', 'label' => 'Коробка передач', 'fmt' => 'trans_ru'],
    ['key' => 'has_turbo', 'label' => 'Турбина', 'fmt' => 'yesno'],
    ['key' => 'condition_state', 'label' => 'Состояние', 'fmt' => 'condition_ru'],
    ['key' => 'customs_cleared', 'label' => 'Растаможен в РТ', 'fmt' => 'yesno'],
    ['key' => 'taxi_license', 'label' => 'Лицензия на такси', 'fmt' => 'yesno'],
    ['key' => 'city', 'label' => 'Город', 'fmt' => 'string'],
];

$render = static function (array $ad, array $row): string {
    $val = $ad[$row['key']] ?? '';
    $cur = (string) ($ad['currency'] ?? 'TJS');
    switch ($row['fmt']) {
        case 'price':
            $p = (float) $val;

            return $p > 0 ? ia_h(ia_listing_format_price($p, $cur)) : '—';
        case 'availability':
            $av = ia_listing_availability_normalize((string) $val);
            $cls = $av === 'on_order' ? 'ia-badge-availability--on-order' : 'ia-badge-availability--in-stock';

            return '<span class="ia-badge-availability ' . $cls . '">' . ia_h(ia_listing_availability_label_ru($av)) . '</span>';
        case 'int_or_dash':
            $v = (int) $val;

            return $v > 0 ? (string) $v : '—';
        case 'mileage':
            if ($val === null || $val === '') {
                return '—';
            }

            return number_format((int) $val, 0, '.', ' ') . ' км';
        case 'prepay':
            $p = (float) $val;

            return $p > 0 ? ia_h(ia_listing_format_price($p, $cur)) : '—';
        case 'yesno':
            return ((int) $val) === 1 ? 'Да' : 'Нет';
        case 'fuel_ru':
            $s = trim((string) $val);
            $lbl = ia_listing_fuel_label_ru($s);

            return $lbl !== '' ? ia_h($lbl) : '—';
        case 'trans_ru':
            $s = trim((string) $val);
            $lbl = ia_listing_transmission_label_ru($s);

            return $lbl !== '' ? ia_h($lbl) : '—';
        case 'body_ru':
            $s = trim((string) $val);
            $lbl = ia_listing_body_label_ru_pub($s);

            return $lbl !== '' ? ia_h($lbl) : '—';
        case 'drive_ru':
            $s = trim((string) $val);
            $lbl = ia_listing_drive_type_label_ru($s);

            return $lbl !== '—' ? ia_h($lbl) : '—';
        case 'condition_ru':
            $s = trim((string) $val);
            $lbl = ia_listing_condition_label_ru($s);

            return $lbl !== '—' ? ia_h($lbl) : '—';
        case 'string':
        default:
            $s = trim((string) $val);

            return $s !== '' ? ia_h($s) : '—';
    }
};

require IA_ROOT . '/includes/partials/site-header.php';
?>

<section class="py-4 py-md-5 ia-page-section ia-compare-page-section">
    <div class="container ia-container">
        <div class="ia-compare-page-head">
            <div class="ia-compare-page-head-text">
                <h1 class="ia-compare-page-title">Сравнение</h1>
                <p class="ia-compare-page-sub mb-0">До <?= (int) IA_COMPARE_MAX ?> авто одновременно</p>
            </div>
            <?php if (!empty($items)): ?>
                <form class="ia-compare-clear-form" method="post" onsubmit="return confirm('Очистить весь список сравнения?');">
                    <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="btn btn-outline-danger btn-sm ia-compare-clear-btn">Очистить</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($msg = ia_flash('pub_ok')): ?><div class="alert alert-success"><?= ia_h((string) $msg) ?></div><?php endif; ?>
        <?php if ($msg = ia_flash('pub_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>

        <?php if (empty($items)): ?>
            <div class="ia-compare-empty">
                <div class="ia-compare-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="48" height="48"><path fill="currentColor" d="M9 3v18H7v-3H3v-2h4V8H3V6h4V3h2zm6 0v3h4v2h-4v8h4v2h-4v3h-2V3h2z"/></svg>
                </div>
                <h2 class="h5 mb-2">Список сравнения пуст</h2>
                <p class="text-secondary mb-3">Откройте интересующее объявление и нажмите «Добавить к сравнению».</p>
                <a class="btn ia-btn-accent" href="<?= ia_h(ia_public_url('catalog.php')) ?>">Перейти в каталог</a>
            </div>
        <?php else: ?>
            <div class="ia-compare-mobile d-lg-none">
                <?php foreach ($items as $idx => $ad): ?>
                    <?php $thumb = ia_listing_photo_src($ad['photo_url'] ?? null); ?>
                    <article class="ia-compare-mobile-card">
                        <div class="ia-compare-mobile-card-top">
                            <a class="ia-compare-mobile-photo" href="<?= ia_h(ia_public_url('car.php?id=' . (int) $ad['id'])) ?>">
                                <img src="<?= ia_h($thumb) ?>" alt="" <?= ia_img_perf_attrs(['width' => 320, 'height' => 240]) ?>>
                            </a>
                            <div class="ia-compare-mobile-card-intro">
                                <span class="ia-compare-mobile-num">№<?= (int) ($idx + 1) ?></span>
                                <h2 class="ia-compare-mobile-title">
                                    <a href="<?= ia_h(ia_public_url('car.php?id=' . (int) $ad['id'])) ?>"><?= ia_h((string) $ad['brand'] . ' ' . (string) $ad['model']) ?></a>
                                </h2>
                                <?php if (!empty($ad['is_vip'])): ?><span class="ia-badge-vip">VIP</span><?php endif; ?>
                                <div class="ia-compare-mobile-price"><?= $render($ad, ['key' => 'price', 'label' => 'Цена', 'fmt' => 'price']) ?></div>
                                <div class="ia-compare-mobile-avail"><?= $render($ad, ['key' => 'availability', 'label' => 'Наличие', 'fmt' => 'availability']) ?></div>
                            </div>
                        </div>
                        <dl class="ia-compare-mobile-specs">
                            <?php foreach ($rows as $r): ?>
                                <?php if (in_array($r['key'], ['price', 'availability'], true)) {
                                    continue;
                                } ?>
                                <div class="ia-compare-mobile-spec-row">
                                    <dt><?= ia_h((string) $r['label']) ?></dt>
                                    <dd><?= $render($ad, $r) ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                        <form method="post" class="ia-compare-mobile-remove">
                            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="listing_id" value="<?= (int) $ad['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Убрать из сравнения</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="ia-compare-wrap d-none d-lg-block">
                <div class="ia-compare-grid" style="--cmp-cols: <?= (int) count($items) ?>;">
                    <div class="ia-compare-col ia-compare-col--head">
                        <div class="ia-compare-card-spacer"></div>
                        <?php foreach ($rows as $r): ?>
                            <div class="ia-compare-row-label"><?= ia_h((string) $r['label']) ?></div>
                        <?php endforeach; ?>
                    </div>

                    <?php foreach ($items as $ad): ?>
                        <?php $thumb = ia_listing_photo_src($ad['photo_url'] ?? null); ?>
                        <div class="ia-compare-col">
                            <div class="ia-compare-card">
                                <a class="ia-compare-photo" href="<?= ia_h(ia_public_url('car.php?id=' . (int) $ad['id'])) ?>">
                                    <img src="<?= ia_h($thumb) ?>" alt="" <?= ia_img_perf_attrs(['width' => 320, 'height' => 240]) ?>>
                                </a>
                                <div class="ia-compare-card-body">
                                    <div class="ia-compare-title">
                                        <a href="<?= ia_h(ia_public_url('car.php?id=' . (int) $ad['id'])) ?>"><?= ia_h((string) $ad['brand'] . ' ' . (string) $ad['model']) ?></a>
                                    </div>
                                    <?php if (!empty($ad['is_vip'])): ?><span class="ia-badge-vip mt-1">VIP</span><?php endif; ?>
                                    <form method="post" class="mt-2">
                                        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="listing_id" value="<?= (int) $ad['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Убрать</button>
                                    </form>
                                </div>
                            </div>
                            <?php foreach ($rows as $r): ?>
                                <div class="ia-compare-row-cell"><?= $render($ad, $r) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require IA_ROOT . '/includes/partials/site-footer.php'; ?>
