<?php
declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_users.php';
ia_require_section('users');

$id = (int) ($_GET['id'] ?? 0);
$row = $id > 0 ? ia_admin_user_by_id($id) : null;
if ($row === null) {
    ia_flash('users_error', 'Пользователь не найден.');
    ia_redirect(ia_admin_url('users.php'));
}
$user = ia_current_user();
$pageTitle = 'Карточка пользователя';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<main class="container py-4">
    <h1 class="h4 mb-3">Карточка пользователя #<?= (int) $row['id'] ?></h1>
    <div class="card"><div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Имя</dt><dd class="col-sm-9"><?= ia_h((string) $row['name']) ?></dd>
            <dt class="col-sm-3">Телефон</dt><dd class="col-sm-9"><?= ia_h((string) $row['phone']) ?></dd>
            <dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?= ia_h((string) $row['email']) ?></dd>
            <dt class="col-sm-3">Тип аккаунта</dt><dd class="col-sm-9"><span class="badge <?= ia_h(ia_admin_user_account_type_badge((string) $row['account_type'])) ?>"><?= ia_h(ia_admin_user_account_type_ru((string) $row['account_type'])) ?></span></dd>
            <dt class="col-sm-3">Статус</dt><dd class="col-sm-9"><span class="badge <?= ia_h(ia_admin_user_status_badge((string) $row['status'])) ?>"><?= ia_h(ia_admin_user_status_ru((string) $row['status'])) ?></span></dd>
            <dt class="col-sm-3">Дата регистрации</dt><dd class="col-sm-9"><?= ia_h((string) $row['created_at']) ?></dd>
        </dl>
    </div></div>
    <a class="btn btn-outline-secondary mt-3" href="<?= ia_h(ia_admin_url('users.php')) ?>">Назад</a>
</main>
<?php require __DIR__ . '/partials/foot.php'; ?>
