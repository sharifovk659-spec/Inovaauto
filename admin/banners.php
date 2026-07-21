<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_banners.php';

ia_require_section('content');
ia_admin_banners_handle_post();

$slot = (string) ($_GET['slot'] ?? '');
$validSlots = ['homepage' => 'Главная (hero)', 'promo_slider' => 'Промо-слайдер', 'ads' => 'Реклама'];
$pdo = ia_db();
$banners = ia_admin_banners_list($pdo, $slot !== '' && isset($validSlots[$slot]) ? $slot : null);
$user = ia_current_user();
$pageTitle = 'Баннеры';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<main class="container-fluid px-3 px-lg-4 py-4">
    <h1 class="h4 mb-3">Управление баннерами</h1>
    <?php if ($msg = ia_flash('banners_ok')): ?><div class="alert alert-success"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <?php if ($msg = ia_flash('banners_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>

    <div class="btn-group mb-3 flex-wrap">
        <a class="btn btn-sm <?= $slot === '' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= ia_h(ia_admin_url('banners.php')) ?>">Все</a>
        <?php foreach ($validSlots as $k => $label): ?>
            <a class="btn btn-sm <?= $slot === $k ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= ia_h(ia_admin_url('banners.php?slot=' . rawurlencode($k))) ?>"><?= ia_h($label) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">Загрузить баннер</h2>
            <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                <input type="hidden" name="action" value="upload">
                <div class="col-md-2">
                    <label class="form-label">Тип</label>
                    <?php if ($slot !== '' && isset($validSlots[$slot])): ?>
                        <p class="form-control-plaintext border rounded px-2 py-1 mb-0 small bg-white"><?= ia_h($validSlots[$slot]) ?></p>
                        <input type="hidden" name="slot" value="<?= ia_h($slot) ?>">
                    <?php else: ?>
                        <select name="slot" class="form-select" required>
                            <?php foreach ($validSlots as $k => $label): ?>
                                <option value="<?= ia_h($k) ?>"><?= ia_h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Заголовок</label>
                    <input type="text" name="title" class="form-control" maxlength="200">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ссылка</label>
                    <input type="url" name="link_url" class="form-control" placeholder="https://">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Порядок</label>
                    <input type="number" name="sort_order" class="form-control" value="0" min="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Изображение</label>
                    <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Старт показа</label>
                    <input type="datetime-local" name="starts_at" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Конец показа</label>
                    <input type="datetime-local" name="ends_at" class="form-control">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">Загрузить</button>
                </div>
            </form>
            <p class="small text-secondary mb-0 mt-2">JPEG, PNG, WebP, GIF, до 2 МБ. Если указать период, баннер будет показываться только в это время.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Тип</th>
                    <th>Заголовок</th>
                    <th>Превью</th>
                    <th>Ссылка</th>
                    <th>Порядок</th>
                    <th>Период показа</th>
                    <th>Активен</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($banners as $b): ?>
                <tr>
                    <td><?= (int) $b['id'] ?></td>
                    <td><?= ia_h($validSlots[(string) $b['slot']] ?? (string) $b['slot']) ?></td>
                    <td><?= ia_h((string) ($b['title'] ?? '')) ?></td>
                    <td>
                        <?php $src = ia_uploads_banners_public_url((string) ($b['image_path'] ?? '')); ?>
                        <a href="<?= ia_h($src) ?>" target="_blank" rel="noopener"><img src="<?= ia_h($src) ?>" alt="" style="max-height:48px;max-width:160px;object-fit:contain"></a>
                    </td>
                    <td class="small"><a href="<?= ia_h((string) ($b['link_url'] ?? '')) ?>" target="_blank" rel="noopener"><?= ia_h((string) ($b['link_url'] ?? '')) ?></a></td>
                    <td>
                        <form method="post" class="d-flex gap-1 align-items-center">
                            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                            <input type="hidden" name="action" value="save_sort">
                            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                            <?php if ($slot !== ''): ?><input type="hidden" name="slot" value="<?= ia_h($slot) ?>"><?php endif; ?>
                            <input type="number" name="sort_order" class="form-control form-control-sm" style="width:5rem" value="<?= (int) ($b['sort_order'] ?? 0) ?>" min="0">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">OK</button>
                        </form>
                    </td>
                    <td style="min-width: 18rem;">
                        <form method="post" class="d-flex flex-column gap-1">
                            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                            <input type="hidden" name="action" value="save_schedule">
                            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                            <?php if ($slot !== ''): ?><input type="hidden" name="slot" value="<?= ia_h($slot) ?>"><?php endif; ?>
                            <?php
                            $startsVal = '';
                            if (!empty($b['starts_at'])) {
                                $ts = strtotime((string) $b['starts_at']);
                                if ($ts !== false) {
                                    $startsVal = date('Y-m-d\TH:i', $ts);
                                }
                            }
                            $endsVal = '';
                            if (!empty($b['ends_at'])) {
                                $te = strtotime((string) $b['ends_at']);
                                if ($te !== false) {
                                    $endsVal = date('Y-m-d\TH:i', $te);
                                }
                            }
                            ?>
                            <input type="datetime-local" name="starts_at" class="form-control form-control-sm" value="<?= ia_h($startsVal) ?>" title="Старт показа">
                            <input type="datetime-local" name="ends_at" class="form-control form-control-sm" value="<?= ia_h($endsVal) ?>" title="Конец показа">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Сохранить период</button>
                        </form>
                        <?php
                        $now = time();
                        $isNowVisible = (int) ($b['is_active'] ?? 0) === 1;
                        if (!empty($b['starts_at'])) {
                            $st = strtotime((string) $b['starts_at']);
                            if ($st !== false && $st > $now) {
                                $isNowVisible = false;
                            }
                        }
                        if (!empty($b['ends_at'])) {
                            $en = strtotime((string) $b['ends_at']);
                            if ($en !== false && $en <= $now) {
                                $isNowVisible = false;
                            }
                        }
                        ?>
                        <div class="small mt-1 <?= $isNowVisible ? 'text-success' : 'text-secondary' ?>">
                            Сейчас: <?= $isNowVisible ? 'показывается' : 'не показывается' ?>
                        </div>
                    </td>
                    <td><?= (int) ($b['is_active'] ?? 0) ? '<span class="text-success">да</span>' : 'нет' ?></td>
                    <td class="d-flex flex-column gap-1">
                        <form method="post">
                            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                            <?php if ($slot !== ''): ?><input type="hidden" name="slot" value="<?= ia_h($slot) ?>"><?php endif; ?>
                            <button type="submit" class="btn btn-sm btn-outline-primary"><?= (int) ($b['is_active'] ?? 0) ? 'Выключить' : 'Включить' ?></button>
                        </form>
                        <form method="post" onsubmit="return confirm('Удалить баннер?');">
                            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                            <?php if ($slot !== ''): ?><input type="hidden" name="slot" value="<?= ia_h($slot) ?>"><?php endif; ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($banners) === 0): ?>
                <tr><td colspan="9" class="text-secondary">Баннеров нет.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require __DIR__ . '/partials/foot.php'; ?>
