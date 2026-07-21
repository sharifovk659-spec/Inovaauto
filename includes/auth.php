<?php

declare(strict_types=1);

use InnovaAuto\Security\Recaptcha;

require_once IA_ROOT . '/includes/helpers.php';

/**
 * @return array<string, mixed>|null
 */
function ia_current_user(): ?array
{
    if (empty($_SESSION['admin_user_id'])) {
        return null;
    }

    $st = ia_db()->prepare(
        'SELECT id, email, username, role, is_active, last_login_at FROM admin_users WHERE id = ? LIMIT 1'
    );
    $st->execute([(int) $_SESSION['admin_user_id']]);
    $row = $st->fetch();
    if (!$row || !(int) $row['is_active']) {
        ia_logout();

        return null;
    }

    return $row;
}

function ia_require_login(): void
{
    if (empty($_SESSION['admin_user_id'])) {
        ia_redirect(ia_admin_url('login.php'));
    }
    if (ia_current_user() === null) {
        ia_redirect(ia_admin_url('login.php'));
    }
    $idle = (int) (ia_config()['security']['admin_idle_seconds'] ?? 1800);
    $now = time();
    $last = (int) ($_SESSION['admin_last_activity'] ?? $now);
    if ($idle > 0 && $now - $last > $idle) {
        ia_flash('login_error', 'Сессия завершена из-за отсутствия активности.');
        ia_logout();
        ia_redirect(ia_admin_url('login.php'));
    }
    $_SESSION['admin_last_activity'] = $now;
}

function ia_admin_url(string $path = ''): string
{
    $base = rtrim(ia_site_base_url(), '/');
    $path = ltrim($path, '/');

    return $path === '' ? $base . '/admin/' : $base . '/admin/' . $path;
}

function ia_login_attempts_exceeded(): bool
{
    $cfg = ia_config()['security'];
    $max = (int) $cfg['max_login_attempts'];
    $minutes = (int) $cfg['lockout_minutes'];
    $ip = ia_client_ip();

    $cutoff = (new DateTimeImmutable())->modify('-' . $minutes . ' minutes')->format('Y-m-d H:i:s');
    $st = ia_db()->prepare(
        'SELECT COUNT(*) FROM admin_login_attempts
         WHERE ip_address = ? AND attempted_at > ?'
    );
    $st->execute([$ip, $cutoff]);
    $count = (int) $st->fetchColumn();

    return $count >= $max;
}

function ia_record_failed_login(): void
{
    $st = ia_db()->prepare('INSERT INTO admin_login_attempts (ip_address) VALUES (?)');
    $st->execute([ia_client_ip()]);
}

function ia_clear_failed_logins(): void
{
    $st = ia_db()->prepare('DELETE FROM admin_login_attempts WHERE ip_address = ?');
    $st->execute([ia_client_ip()]);
}

/**
 * @return array<string, mixed>|null
 */
function ia_find_user_by_login(string $login): ?array
{
    $login = trim($login);
    if ($login === '') {
        return null;
    }
    $st = ia_db()->prepare(
        'SELECT * FROM admin_users WHERE (email = ? OR username = ?) AND is_active = 1 LIMIT 1'
    );
    $st->execute([$login, $login]);

    $row = $st->fetch();

    return $row ?: null;
}

/**
 * @param array<string, mixed> $user
 */
function ia_login_user(array $user, bool $remember): void
{
    session_regenerate_id(true);
    $_SESSION['admin_user_id'] = (int) $user['id'];
    $_SESSION['admin_login_at'] = time();
    $_SESSION['admin_ip'] = ia_client_ip();
    $_SESSION['admin_last_activity'] = time();

    $up = ia_db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?');
    $up->execute([(int) $user['id']]);

    ia_clear_failed_logins();

    if ($remember) {
        ia_set_remember_cookie((int) $user['id']);
    }
}

function ia_logout(): void
{
    if (!empty($_SESSION['admin_user_id'])) {
        ia_delete_remember_cookie();
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $p['path'],
            $p['domain'],
            $p['secure'],
            $p['httponly']
        );
    }
    session_destroy();
}

function ia_try_restore_session_from_cookie(): void
{
    if (!empty($_SESSION['admin_user_id'])) {
        return;
    }
    $cookie = $_COOKIE['ia_remember'] ?? '';
    if (!is_string($cookie) || !str_contains($cookie, ':')) {
        return;
    }
    [$selector, $validator] = explode(':', $cookie, 2);
    $selector = trim($selector);
    $validator = trim($validator);
    if (strlen($selector) !== 32 || strlen($validator) !== 64) {
        return;
    }

    $st = ia_db()->prepare(
        'SELECT rt.user_id, rt.validator_hash, rt.expires_at, u.is_active
         FROM admin_remember_tokens rt
         INNER JOIN admin_users u ON u.id = rt.user_id
         WHERE rt.selector = ? LIMIT 1'
    );
    $st->execute([$selector]);
    $row = $st->fetch();
    if (!$row || !(int) $row['is_active']) {
        ia_delete_remember_cookie();

        return;
    }
    if (strtotime((string) $row['expires_at']) < time()) {
        ia_delete_remember_token_by_selector($selector);
        ia_delete_remember_cookie();

        return;
    }
    if (!password_verify($validator, (string) $row['validator_hash'])) {
        ia_delete_remember_token_by_selector($selector);
        ia_delete_remember_cookie();

        return;
    }

    session_regenerate_id(true);
    $_SESSION['admin_user_id'] = (int) $row['user_id'];
    $_SESSION['admin_login_at'] = time();
    $_SESSION['admin_ip'] = ia_client_ip();
    $_SESSION['admin_last_activity'] = time();
    $_SESSION['via_remember'] = true;
}

function ia_delete_remember_token_by_selector(string $selector): void
{
    $st = ia_db()->prepare('DELETE FROM admin_remember_tokens WHERE selector = ?');
    $st->execute([$selector]);
}

function ia_set_remember_cookie(int $userId): void
{
    $days = (int) ia_config()['security']['remember_days'];
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $hash = password_hash($validator, PASSWORD_DEFAULT);
    $expires = (new DateTimeImmutable())->modify('+' . $days . ' days');

    $del = ia_db()->prepare('DELETE FROM admin_remember_tokens WHERE user_id = ?');
    $del->execute([$userId]);

    $ins = ia_db()->prepare(
        'INSERT INTO admin_remember_tokens (user_id, selector, validator_hash, expires_at)
         VALUES (?, ?, ?, ?)'
    );
    $ins->execute([
        $userId,
        $selector,
        $hash,
        $expires->format('Y-m-d H:i:s'),
    ]);

    $value = $selector . ':' . $validator;
    $secure = (bool) ia_config()['session']['secure'];
    setcookie('ia_remember', $value, [
        'expires' => $expires->getTimestamp(),
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function ia_delete_remember_cookie(): void
{
    if (!empty($_COOKIE['ia_remember'])) {
        $cookie = (string) $_COOKIE['ia_remember'];
        if (str_contains($cookie, ':')) {
            [$selector] = explode(':', $cookie, 2);
            ia_delete_remember_token_by_selector(trim($selector));
        }
    }
    $secure = (bool) ia_config()['session']['secure'];
    setcookie('ia_remember', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * @param array<string, mixed> $config
 */
function ia_verify_recaptcha_on_login(array $config): bool
{
    return Recaptcha::verify($config, $_POST['g-recaptcha-response'] ?? null);
}

require_once IA_ROOT . '/includes/admin_permissions.php';
