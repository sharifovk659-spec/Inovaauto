<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_tariffs.php';

use InnovaAuto\Security\Csrf;

ia_require_section('billing');
$pdo = ia_db();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$row = $id > 0 ? ia_billing_tariff_by_id($pdo, $id) : null;
if ($row === null) {
    ia_flash('billing_error', 'Тариф не найден.');
    ia_redirect(ia_admin_url('tariffs.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('billing_error', 'Сессия устарела.');
        ia_redirect(ia_admin_url('tariff-edit.php?id=' . $id));
    }
    if (ia_billing_tariff_update($pdo, $id, $_POST)) {
        ia_flash('billing_ok', 'Тариф сохранён.');
        ia_redirect(ia_admin_url('tariffs.php'));
    }
    ia_flash('billing_error', 'Проверьте название и сумму.');
    ia_redirect(ia_admin_url('tariff-edit.php?id=' . $id));
}

$user = ia_current_user();
$pageTitle = 'Редактирование тарифа';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';

$dval = $row['duration_days'];
$dstr = $dval === null ? '' : (string) (int) $dval;
?>
<main class="container py-4">
    <h1 class="h4 mb-3">Редактирование тарифа</h1>
    <p class="small text-secondary">Код: <code><?= ia_h((string) $row['code']) ?></code> (не меняется)</p>

    <?php if ($msg = ia_flash('billing_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>

    <form method="post" class="card card-body" style="max-width: 640px;">
        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
        <div class="mb-3">
            <label class="form-label">Название</label>
            <input type="text" name="name" class="form-control" value="<?= ia_h((string) $row['name']) ?>" required maxlength="160">
        </div>
        <div class="mb-3">
            <label class="form-label">Нарх (с.)</label>
            <input type="text" name="price" class="form-control" value="<?= ia_h((string) $row['price']) ?>" inputmode="decimal" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Срок (дней)</label>
            <input type="number" name="duration_days" class="form-control" min="0" value="<?= ia_h($dstr) ?>" placeholder="пусто — без ограничения по дням">
        </div>
        <div class="mb-3">
            <label class="form-label">Преимущества</label>
            <textarea name="benefits" class="form-control" rows="4"><?= ia_h((string) ($row['benefits'] ?? '')) ?></textarea>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a class="btn btn-outline-secondary" href="<?= ia_h(ia_admin_url('tariffs.php')) ?>">Отмена</a>
        </div>
    </form>
</main>
<?php require __DIR__ . '/partials/foot.php'; ?>
