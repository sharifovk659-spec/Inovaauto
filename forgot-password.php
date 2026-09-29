<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/platform_password_reset.php';

if (ia_platform_current_user() !== null) {
    ia_redirect(ia_public_url('index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('reset_info', 'Сессия устарела. Обновите страницу и попробуйте снова.');
    } else {
        ia_platform_password_reset_request(ia_post_email('email'));
    }
    ia_redirect(ia_public_url('forgot-password.php'));
}

$pageTitle = 'Восстановление пароля';

require IA_ROOT . '/includes/partials/site-header.php';
?>

<section class="ia-page-section ia-reset-page">
    <div class="ia-reset-wrap">
        <h1 class="ia-reset-title">Восстановление пароля</h1>
        <p class="small text-secondary ia-reset-lead">Укажите email аккаунта. Если он зарегистрирован, мы отправим ссылку для сброса пароля.</p>
        <?php if ($err = ia_flash('reset_error')): ?><div class="alert alert-danger ia-reset-alert"><?= ia_h((string) $err) ?></div><?php endif; ?>
        <?php if ($info = ia_flash('reset_info')): ?><div class="alert alert-info ia-reset-alert"><?= ia_h((string) $info) ?></div><?php endif; ?>
        <form method="post" class="card ia-form-surface ia-reset-card">
            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required autocomplete="email">
            </div>
            <button type="submit" class="btn ia-btn-accent w-100">Отправить ссылку</button>
        </form>
        <p class="small text-secondary mt-3 mb-0"><a href="<?= ia_h(ia_public_url('login.php')) ?>">Назад ко входу</a></p>
    </div>
</section>

<?php require IA_ROOT . '/includes/partials/site-footer.php'; ?>
