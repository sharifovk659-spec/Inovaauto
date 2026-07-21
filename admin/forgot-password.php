<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/password_reset.php';

use InnovaAuto\Security\Csrf;

if (ia_current_user() !== null) {
    ia_redirect(ia_admin_url('dashboard.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('reset_info', 'Сессия устарела. Обновите страницу и попробуйте снова.');
    } else {
        $email = ia_post_email('email');
        ia_password_reset_request($email);
    }
    ia_redirect(ia_admin_url('forgot-password.php'));
}

$pageTitle = 'Восстановление пароля';
$iaAdminShell = false;
require __DIR__ . '/partials/head.php';
?>

<main class="flex-grow-1 d-flex align-items-center py-5 px-3">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-auto">
                <div class="card ia-card border-0 rounded-4">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <div class="fw-semibold fs-4 ia-brand text-primary">InnovaAuto</div>
                            <div class="text-secondary small">Восстановление пароля</div>
                        </div>

                        <?php if ($info = ia_flash('reset_info')): ?>
                            <div class="alert alert-info small"><?= ia_h((string) $info) ?></div>
                        <?php endif; ?>

                        <p class="small text-secondary">
                            Укажите email учётной записи администратора. Если он зарегистрирован, вы получите ссылку для сброса пароля.
                        </p>

                        <form method="post" action="">
                            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required autocomplete="email">
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Отправить ссылку</button>
                                <a href="<?= ia_h(ia_admin_url('login.php')) ?>" class="btn btn-outline-secondary">Назад ко входу</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/partials/foot.php'; ?>
