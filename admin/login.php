<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';

use InnovaAuto\Security\Csrf;

if (ia_current_user() !== null) {
    ia_redirect(ia_admin_url('dashboard.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = Csrf::validate($_POST['_csrf'] ?? null);
    $loginId = ia_input_login_id($_POST['login'] ?? '', 150);
    $password = ia_post_password('password');
    $remember = !empty($_POST['remember']);

    if (!$csrfOk) {
        ia_flash('login_error', 'Сессия устарела. Обновите страницу и попробуйте снова.');
    } elseif (ia_login_attempts_exceeded()) {
        ia_flash('login_error', 'Слишком много попыток входа. Попробуйте позже.');
    } elseif (!ia_verify_recaptcha_on_login(ia_config())) {
        ia_flash('login_error', 'Подтвердите, что вы не робот.');
    } elseif ($loginId === '' || $password === '') {
        ia_flash('login_error', 'Введите логин и пароль.');
    } else {
        $user = ia_find_user_by_login($loginId);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            ia_record_failed_login();
            ia_flash('login_error', 'Неверный email/username или пароль.');
        } else {
            ia_login_user($user, $remember);
            ia_redirect(ia_admin_url('dashboard.php'));
        }
    }
}

$pageTitle = 'Вход';
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
                            <div class="text-secondary small">Панель администратора</div>
                        </div>

                        <?php if ($ok = ia_flash('login_ok')): ?>
                            <div class="alert alert-success small"><?= ia_h((string) $ok) ?></div>
                        <?php endif; ?>
                        <?php if ($err = ia_flash('login_error')): ?>
                            <div class="alert alert-danger small"><?= ia_h((string) $err) ?></div>
                        <?php endif; ?>

                        <?php
                        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
                        $isLocalHost = $host === 'localhost'
                            || str_starts_with($host, '127.0.0.1')
                            || str_starts_with($host, '[::1]');
                        $showHint = $isLocalHost && !empty(ia_config()['app']['show_dev_login_hint']);
                        ?>
                        <?php if ($showHint): ?>
                            <div class="alert alert-light border small text-start mb-3" role="note">
                                <div class="fw-semibold mb-1">Тестовый вход</div>
                                <p class="small text-secondary mb-2 mb-0">
                                    При пустой таблице пользователей учётная запись создаётся автоматически (как в <code>sql/schema.sql</code>).
                                    Обязательно введите пароль в поле ниже — без него вход невозможен.
                                </p>
                                <div class="mt-2"><span class="text-secondary">Email:</span> <kbd class="user-select-all">admin@innovaauto.local</kbd></div>
                                <div><span class="text-secondary">Или логин:</span> <kbd class="user-select-all">admin</kbd></div>
                                <div><span class="text-secondary">Пароль:</span> <kbd class="user-select-all">Admin123!</kbd></div>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="" class="needs-validation" novalidate autocomplete="on">
                            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">

                            <div class="mb-3">
                                <label for="login" class="form-label">Email или имя пользователя</label>
                                <input type="text" class="form-control" id="login" name="login" required
                                       value="<?= ia_h((string) ($_POST['login'] ?? '')) ?>"
                                       autocomplete="username" inputmode="email" spellcheck="false">
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Пароль</label>
                                <input type="password" class="form-control" id="password" name="password" required
                                       minlength="1" autocomplete="current-password">
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1"
                                    <?= !empty($_POST['remember']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="remember">Запомнить меня</label>
                            </div>

                            <?php if (ia_recaptcha_enabled(ia_config())): ?>
                                <div class="mb-3">
                                    <div class="g-recaptcha" data-sitekey="<?= ia_h(ia_config()['recaptcha']['site_key']) ?>"></div>
                                </div>
                            <?php endif; ?>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Войти</button>
                            </div>
                        </form>

                        <div class="d-flex justify-content-between mt-4 small">
                            <a href="<?= ia_h(ia_admin_url('forgot-password.php')) ?>">Забыли пароль?</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php if (ia_recaptcha_enabled(ia_config())): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>

<?php require __DIR__ . '/partials/foot.php'; ?>
