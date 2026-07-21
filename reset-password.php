<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/platform_password_reset.php';

if (ia_platform_current_user() !== null) {
    ia_redirect(ia_public_url('index.php'));
}

$token = ia_input_text($_GET['token'] ?? ($_POST['token'] ?? ''), 128);
$valid = $token !== '' ? ia_platform_password_reset_find_valid($token) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('pub_error', 'Сессия устарела.');
        ia_redirect(ia_public_url('reset-password.php?token=' . rawurlencode($token)));
    }
    $userId = (int) ($_POST['user_id'] ?? 0);
    $pw = ia_post_password('password');
    $pw2 = ia_post_password('password2');
    if ($valid === null || $userId !== (int) ($valid['user_id'] ?? 0)) {
        ia_flash('pub_error', 'Ссылка недействительна или устарела.');
    } elseif (strlen($pw) < 8 || $pw !== $pw2) {
        ia_flash('pub_error', 'Пароль: минимум 8 символов и совпадение полей.');
    } elseif (ia_platform_password_reset_complete($userId, $token, $pw)) {
        ia_flash('pub_ok', 'Пароль обновлён. Теперь можно войти.');
        ia_redirect(ia_public_url('login.php'));
    } else {
        ia_flash('pub_error', 'Не удалось обновить пароль.');
    }
    ia_redirect(ia_public_url('reset-password.php?token=' . rawurlencode($token)));
}

$pageTitle = 'Новый пароль';

require IA_ROOT . '/includes/partials/site-header.php';
?>

<section class="py-5 ia-page-section">
    <div class="container ia-container" style="max-width: 440px;">
        <h1 class="h4 mb-3">Новый пароль</h1>
        <?php if ($msg = ia_flash('pub_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>
        <?php if ($valid === null): ?>
            <p class="text-secondary">Ссылка для сброса пароля недействительна или устарела. <a href="<?= ia_h(ia_public_url('forgot-password.php')) ?>">Запросить новую</a>.</p>
        <?php else: ?>
            <form method="post" class="card p-4 ia-form-surface">
                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                <input type="hidden" name="action" value="reset">
                <input type="hidden" name="token" value="<?= ia_h($token) ?>">
                <input type="hidden" name="user_id" value="<?= (int) $valid['user_id'] ?>">
                <div class="mb-3">
                    <label class="form-label">Новый пароль</label>
                    <input type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
                </div>
                <div class="mb-3">
                    <label class="form-label">Повтор пароля</label>
                    <input type="password" name="password2" class="form-control" required minlength="8" autocomplete="new-password">
                </div>
                <button type="submit" class="btn ia-btn-accent w-100">Сохранить пароль</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php require IA_ROOT . '/includes/partials/site-footer.php'; ?>
