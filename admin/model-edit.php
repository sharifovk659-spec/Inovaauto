<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_catalog.php';

use InnovaAuto\Security\Csrf;

ia_require_section('catalog');
$pdo = ia_db();
$brandId = (int) ($_GET['brand_id'] ?? $_POST['brand_id'] ?? 0);
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$brand = $brandId > 0 ? ia_catalog_brand_by_id($pdo, $brandId) : null;
if ($brand === null) {
    ia_flash('catalog_error', 'Бренд не найден.');
    ia_redirect(ia_admin_url('models.php'));
}
$row = $id > 0 ? ia_catalog_model_by_id($pdo, $id) : null;
if ($id > 0 && ($row === null || (int) $row['brand_id'] !== $brandId)) {
    ia_flash('catalog_error', 'Модель не найдена.');
    ia_redirect(ia_admin_url('models.php?brand_id=' . $brandId));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('catalog_error', 'Сессия устарела.');
        ia_redirect(ia_admin_url('model-edit.php?brand_id=' . $brandId . '&id=' . $id));
    }
    $name = ia_post_text('name', 120);
    if ($name === '') {
        ia_flash('catalog_error', 'Укажите название модели.');
        ia_redirect(ia_admin_url('model-edit.php?brand_id=' . $brandId . '&id=' . $id));
    }
    if ($id > 0) {
        $pdo->prepare('UPDATE car_models SET name = ? WHERE id = ? AND brand_id = ?')->execute([$name, $id, $brandId]);
        ia_catalog_invalidate_public_cache();
        ia_flash('catalog_ok', 'Модель сохранена.');
        ia_redirect(ia_admin_url('models.php?brand_id=' . $brandId));
    }
}

$user = ia_current_user();
$pageTitle = 'Редактирование модели';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
$nameVal = $row ? (string) $row['name'] : '';
?>
<main class="container py-4">
    <h1 class="h4 mb-3">Редактирование модели</h1>
    <?php if ($msg = ia_flash('catalog_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <p class="small text-secondary">Бренд: <strong><?= ia_h((string) $brand['name']) ?></strong></p>
    <form method="post" class="card card-body" style="max-width: 520px;">
        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
        <input type="hidden" name="brand_id" value="<?= $brandId ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="mb-3">
            <label class="form-label">Модель</label>
            <input type="text" name="name" class="form-control" value="<?= ia_h($nameVal) ?>" required maxlength="120">
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a class="btn btn-outline-secondary" href="<?= ia_h(ia_admin_url('models.php?brand_id=' . $brandId)) ?>">Отмена</a>
        </div>
    </form>
</main>
<?php require __DIR__ . '/partials/foot.php'; ?>
