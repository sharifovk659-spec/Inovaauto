<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

/**
 * @return array<string, mixed>|null
 */
function ia_platform_current_user(bool $forceReload = false): ?array
{
    static $loadedUid = null;
    static $loadedUser = null;
    static $resolved = false;

    if ($forceReload) {
        $resolved = false;
        $loadedUid = null;
        $loadedUser = null;
    }

    $sessionUid = empty($_SESSION['platform_user_id']) ? 0 : (int) $_SESSION['platform_user_id'];
    if ($resolved && $loadedUid === $sessionUid) {
        return $loadedUser;
    }

    $resolved = true;
    $loadedUid = $sessionUid;
    $loadedUser = null;

    if ($sessionUid <= 0) {
        return null;
    }

    $st = ia_db()->prepare(
        'SELECT id, name, phone, email, account_type, status, created_at, avatar_path FROM platform_users WHERE id = ? LIMIT 1'
    );
    $st->execute([$sessionUid]);
    $row = $st->fetch();
    if (!$row || (string) $row['status'] !== 'active') {
        ia_platform_logout();

        return null;
    }

    $loadedUser = $row;

    return $loadedUser;
}

function ia_platform_require_login(): void
{
    if (ia_platform_current_user() === null) {
        $here = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
        $qs = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
        $target = $here . ($qs !== '' ? '?' . $qs : '');
        ia_redirect(ia_public_url('login.php?redirect=' . rawurlencode($target)));
    }
}

function ia_platform_reset_user_cache(): void
{
    ia_platform_current_user(true);
}

function ia_platform_logout(): void
{
    unset($_SESSION['platform_user_id']);
    ia_platform_reset_user_cache();
}

/**
 * @param array<string, mixed> $user строка platform_users
 */
function ia_platform_login_user(array $user): void
{
    if (!headers_sent()) {
        session_regenerate_id(true);
    }
    $_SESSION['platform_user_id'] = (int) $user['id'];
    ia_platform_reset_user_cache();
}

function ia_platform_find_by_email(string $email): ?array
{
    $email = trim($email);
    if ($email === '') {
        return null;
    }
    $st = ia_db()->prepare('SELECT * FROM platform_users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $row = $st->fetch();

    return $row ?: null;
}

function ia_platform_register(string $name, string $phone, string $email, string $password, string $accountType): bool
{
    $email = trim($email);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $st = ia_db()->prepare(
        'INSERT INTO platform_users (name, phone, email, password_hash, account_type, status) VALUES (?, ?, ?, ?, ?, \'active\')'
    );

    try {
        return $st->execute([trim($name), trim($phone), $email, $hash, $accountType === 'dealer' ? 'dealer' : 'private']);
    } catch (\PDOException $e) {
        error_log('platform_register: ' . $e->getMessage());

        return false;
    }
}

function ia_platform_handle_login_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'login') {
        return;
    }
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('pub_error', 'Сессия устарела. Обновите страницу.');

        return;
    }
    $email = ia_input_login_id($_POST['email'] ?? '', 254);
    $password = ia_post_password('password');
    if ($email === '' || $password === '') {
        ia_flash('pub_error', 'Введите email и пароль.');

        return;
    }
    $u = ia_platform_find_by_email($email);
    if ($u === null || empty($u['password_hash']) || !password_verify($password, (string) $u['password_hash'])) {
        ia_flash('pub_error', 'Неверный email или пароль.');

        return;
    }
    if ((string) ($u['status'] ?? '') !== 'active') {
        ia_flash('pub_error', 'Учётная запись недоступна.');

        return;
    }
    ia_platform_login_user($u);
    ia_redirect(ia_public_url(ia_public_safe_redirect_full((string) ($_POST['redirect'] ?? 'index.php'))));
}

/** После входа: только локальный script (с .php или без) и безопасная query-string. */
function ia_public_safe_redirect_full(string $raw): string
{
    $raw = trim(str_replace(["\0", "\r", "\n"], '', $raw));
    if ($raw === '' || $raw === '/' || strcasecmp($raw, 'index') === 0) {
        return 'index.php';
    }
    if (preg_match('/^[a-z0-9._-]+\.php(\?[a-zA-Z0-9._=&%-]*)?$/i', $raw)) {
        return $raw;
    }
    if (preg_match('/^[a-z0-9._-]+(\?[a-zA-Z0-9._=&%-]*)?$/i', $raw) && !str_contains($raw, '..')) {
        $qPos = strpos($raw, '?');
        $script = $qPos !== false ? substr($raw, 0, $qPos) : $raw;
        $query = $qPos !== false ? substr($raw, $qPos) : '';
        if (strcasecmp($script, 'index') === 0) {
            return 'index.php' . $query;
        }

        return $script . '.php' . $query;
    }

    return 'index.php';
}

function ia_platform_handle_register_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'register') {
        return;
    }
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('pub_error', 'Сессия устарела.');

        return;
    }
    $name = ia_post_text('name', 150);
    $phone = ia_post_phone('phone');
    $email = ia_post_email('email');
    $password = ia_post_password('password');
    $password2 = ia_post_password('password_confirm');
    $accountType = ia_input_enum($_POST['account_type'] ?? 'private', ['private', 'dealer'], 'private');
    if ($name === '' || $email === '' || strlen($password) < 8) {
        ia_flash('pub_error', 'Заполните имя, email и пароль (не короче 8 символов).');

        return;
    }
    if ($password !== $password2) {
        ia_flash('pub_error', 'Пароли не совпадают.');

        return;
    }
    if (ia_platform_find_by_email($email) !== null) {
        ia_flash('pub_error', 'Пользователь с таким email уже зарегистрирован.');

        return;
    }
    if (!ia_platform_register($name, $phone, $email, $password, $accountType)) {
        ia_flash('pub_error', 'Не удалось создать учётную запись.');

        return;
    }
    $u = ia_platform_find_by_email($email);
    if ($u !== null) {
        ia_platform_login_user($u);
    }
    ia_flash('pub_ok', 'Добро пожаловать!');
    ia_redirect(ia_public_url('index.php'));
}
