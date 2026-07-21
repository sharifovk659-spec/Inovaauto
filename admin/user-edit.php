<?php
declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_users.php';
use InnovaAuto\Security\Csrf;

ia_require_section('users');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$row = $id > 0 ? ia_admin_user_by_id($id) : null;
if ($row === null) {
    ia_flash('users_error', 'Пользователь не найден.');
    ia_redirect(ia_admin_url('users.php'));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('users_error', 'Сессия устарела.');
    } else {
        ia_admin_user_update($id, $_POST);
        ia_flash('users_ok', 'Изменения сохранены.');
        ia_redirect(ia_admin_url('users.php'));
    }
}

$user = ia_current_user();
$pageTitle = 'Редактирование пользователя';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<main class="container py-4">
    <h1 class="h4 mb-3">Редактирование пользователя #<?= (int) $row['id'] ?></h1>
    <form method="post" class="card card-body">
        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Имя</label><input class="form-control" name="name" value="<?= ia_h((string) $row['name']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Телефон</label><input class="form-control" name="phone" value="<?= ia_h((string) $row['phone']) ?>"></div>
            <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= ia_h((string) $row['email']) ?>" required></div>
            <div class="col-md-3">
                <label class="form-label">Тип аккаунта</label>
                <select class="form-select" name="account_type">
                    <option value="private" <?= (string) $row['account_type'] === 'private' ? 'selected' : '' ?>>Частный</option>
                    <option value="dealer" <?= (string) $row['account_type'] === 'dealer' ? 'selected' : '' ?>>Дилер</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Статус</label>
                <select class="form-select" name="status">
                    <option value="active" <?= (string) $row['status'] === 'active' ? 'selected' : '' ?>>Активный</option>
                    <option value="blocked" <?= (string) $row['status'] === 'blocked' ? 'selected' : '' ?>>Заблокирован</option>
                </select>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Сохранить</button>
            <a class="btn btn-outline-secondary" href="<?= ia_h(ia_admin_url('users.php')) ?>">Отмена</a>
        </div>
    </form>
</main>
<?php require __DIR__ . '/partials/foot.php'; ?>
