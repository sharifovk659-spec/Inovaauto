<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/password_reset.php';

use InnovaAuto\Security\Csrf;

if (ia_current_user() !== null) {
    ia_redirect(ia_admin_url('dashboard.php'));
}

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');

$valid = strlen($token) === 64 ? ia_password_reset_find_valid($token) : null;
if ($valid === null && $_SERVER['REQUEST_METHOD'] === 'GET' && $token !== '') {
    ia_flash('login_error', 'Ссылка недействительна или срок её действия истёк.');
    ia_redirect(ia_admin_url('login.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('reset_form_error', 'Сессия устарела. Обновите страницу и попробуйте снова.');
    } elseif ($valid === null) {
        ia_flash('login_error', 'Ссылка недействительна или срок её действия истёк.');
        ia_redirect(ia_admin_url('login.php'));
    } else {
        $p1 = (string) ($_POST['password'] ?? '');
        $p2 = (string) ($_POST['password_confirm'] ?? '');
        if (strlen($p1) < 8) {
            ia_flash('reset_form_error', 'Пароль должен быть не короче 8 символов.');
        } elseif ($p1 !== $p2) {
            ia_flash('reset_form_error', 'Пароли не совпадают.');
        } elseif (ia_password_reset_complete($valid['user_id'], $token, $p1)) {
            ia_flash('login_ok', 'Пароль обновлён. Войдите с новым паролем.');
            ia_redirect(ia_admin_url('login.php'));
        } else {
            ia_flash('reset_form_error', 'Не удалось сбросить пароль. Запросите ссылку снова.');
        }
    }
    ia_redirect(ia_admin_url('reset-password.php') . '?token=' . rawurlencode($token));
}

$pageTitle = 'Новый пароль';
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
                            <div class="text-secondary small">Установка нового пароля</div>
                        </div>

                        <?php if ($valid === null): ?>
                            <p class="small text-secondary">Для сброса пароля перейдите по ссылке из письма.</p>
                            <a href="<?= ia_h(ia_admin_url('login.php')) ?>" class="btn btn-primary w-100">На страницу входа</a>
                        <?php else: ?>
                            <?php if ($err = ia_flash('reset_form_error')): ?>
                                <div class="alert alert-danger small"><?= ia_h((string) $err) ?></div>
                            <?php endif; ?>

                            <form method="post" action="">
                                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                <input type="hidden" name="token" value="<?= ia_h($token) ?>">

                                <div class="mb-3">
                                    <label for="password" class="form-label">Новый пароль</label>
                                    <input type="password" class="form-control" id="password" name="password" required minlength="8" autocomplete="new-password">
                                    <div class="form-text">Минимум 8 символов.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="password_confirm" class="form-label">Повторите пароль</label>
                                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Сохранить</button>
                                    <a href="<?= ia_h(ia_admin_url('login.php')) ?>" class="btn btn-outline-secondary">Отмена</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/partials/foot.php'; ?>
