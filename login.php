<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/auth_page_bg.php';

ia_platform_handle_login_post();

if (ia_platform_current_user() !== null) {
    ia_redirect(ia_public_url('index.php'));
}

$pageTitle = 'Вход';
$redirect = ia_public_safe_redirect_full((string) ($_GET['redirect'] ?? 'index.php'));
$iaBodyExtraClass = 'ia-page-auth ia-page-login';

$iaAuthAttrs = ia_auth_page_app_attrs();
$iaAuthAppClass = $iaAuthAttrs['class'] . ' ia-auth-app--standalone';
$iaAuthBgStyle = $iaAuthAttrs['style'];

$authCssHref = ia_stylesheet_href('assets/auth-premium.css', 'assets/auth-premium.min.css');
$GLOBALS['ia_extra_head_html'] = '<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">'
    . '<link rel="stylesheet" href="' . ia_h($authCssHref) . '">';

$homeUrl = ia_public_url('index.php');
$registerUrl = ia_public_url('register.php');
$forgotUrl = ia_public_url('forgot-password.php');
$flashError = ia_flash('pub_error');
$flashOk = ia_flash('pub_ok');

require IA_ROOT . '/includes/partials/site-header.php';
?>

<div class="<?= ia_h($iaAuthAppClass) ?>"<?= $iaAuthBgStyle ?>>
    <div class="ia-auth-bg" aria-hidden="true"></div>
    <div class="ia-auth-overlay" aria-hidden="true"></div>

    <section class="ia-auth-login is-active ia-auth-login-page" aria-label="Вход">
        <div class="ia-auth-login-scrim" aria-hidden="true"></div>
        <div class="ia-auth-login-inner">
            <a class="ia-auth-back-btn" href="<?= ia_h($homeUrl) ?>" aria-label="На главную">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
            </a>

            <header class="ia-auth-login-head">
                <h1 class="ia-auth-login-title">Вход</h1>
                <p class="ia-auth-login-lead">Войдите в свой аккаунт InnovaAuto</p>
            </header>

            <?php if ($flashError !== null): ?>
                <div class="ia-auth-alert ia-auth-alert--error" role="alert"><?= ia_h((string) $flashError) ?></div>
            <?php endif; ?>
            <?php if ($flashOk !== null): ?>
                <div class="ia-auth-alert ia-auth-alert--ok" role="status"><?= ia_h((string) $flashOk) ?></div>
            <?php endif; ?>

            <form method="post" class="ia-auth-form ia-auth-form--login" id="iaAuthLoginForm">
                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="redirect" value="<?= ia_h($redirect) ?>">

                <label class="ia-auth-field">
                    <span class="ia-auth-label">Email</span>
                    <input type="email" name="email" class="ia-auth-input ia-auth-input--plain" required autocomplete="email" inputmode="email">
                </label>

                <label class="ia-auth-field">
                    <span class="ia-auth-label">Пароль</span>
                    <span class="ia-auth-input-wrap">
                        <input type="password" name="password" id="iaAuthLoginPassword" class="ia-auth-input ia-auth-input--plain ia-auth-input--password" required autocomplete="current-password">
                        <button type="button" class="ia-auth-pw-toggle" data-ia-pw-toggle aria-label="Показать пароль" aria-pressed="false">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </span>
                </label>

                <button type="submit" class="ia-auth-btn ia-auth-btn--primary ia-auth-btn--block">Войти</button>
            </form>

            <p class="ia-auth-register-foot">
                Нет аккаунта?
                <a href="<?= ia_h($registerUrl) ?>">Регистрация</a>
            </p>
            <p class="ia-auth-login-forgot">
                <a href="<?= ia_h($forgotUrl) ?>">Забыли пароль?</a>
            </p>
        </div>
    </section>
</div>

<script>
(function () {
  document.querySelectorAll('[data-ia-pw-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var wrap = btn.closest('.ia-auth-input-wrap');
      var input = wrap ? wrap.querySelector('input') : null;
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      btn.setAttribute('aria-label', show ? 'Скрыть пароль' : 'Показать пароль');
      var icon = btn.querySelector('i');
      if (icon) {
        icon.classList.toggle('bi-eye', !show);
        icon.classList.toggle('bi-eye-slash', show);
      }
    });
  });
})();
</script>

<?php require IA_ROOT . '/includes/partials/site-footer.php'; ?>
