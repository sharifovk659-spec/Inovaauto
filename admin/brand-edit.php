<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_catalog.php';

use InnovaAuto\Security\Csrf;

ia_require_section('catalog');
$pdo = ia_db();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$row = $id > 0 ? ia_catalog_brand_by_id($pdo, $id) : null;
if ($row === null) {
    ia_flash('catalog_error', 'Бренд не найден.');
    ia_redirect(ia_admin_url('brands.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('catalog_error', 'Сессия устарела.');
        ia_redirect(ia_admin_url('brand-edit.php?id=' . $id));
    }
    $name = ia_post_text('name', 120);
    if ($name === '') {
        ia_flash('catalog_error', 'Укажите название.');
        ia_redirect(ia_admin_url('brand-edit.php?id=' . $id));
    }
    $pdo->prepare('UPDATE car_brands SET name = ? WHERE id = ?')->execute([$name, $id]);
    ia_catalog_invalidate_public_cache();
    ia_flash('catalog_ok', 'Бренд сохранён.');
    ia_redirect(ia_admin_url('brands.php'));
}

$user = ia_current_user();
$pageTitle = 'Редактирование бренда';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<main class="container py-4">
    <h1 class="h4 mb-3">Редактирование бренда</h1>
    <?php if ($msg = ia_flash('catalog_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <form method="post" class="card card-body" style="max-width: 520px;">
        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
        <div class="mb-3">
            <label class="form-label">Название</label>
            <input type="text" name="name" class="form-control" value="<?= ia_h((string) $row['name']) ?>" required maxlength="120">
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a class="btn btn-outline-secondary" href="<?= ia_h(ia_admin_url('brands.php')) ?>">Отмена</a>
        </div>
    </form>
</main>
<?php require __DIR__ . '/partials/foot.php'; ?>
