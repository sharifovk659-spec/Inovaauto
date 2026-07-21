<?php

declare(strict_types=1);

require_once IA_ROOT . '/includes/password_reset.php';
require_once IA_ROOT . '/includes/schema_public_moderation.php';

function ia_platform_password_reset_request(string $email): void
{
    ia_ensure_public_moderation_schema(ia_db());
    $email = ia_input_email($email);
    if ($email === '') {
        ia_flash('reset_info', 'Если указанный адрес существует, на него отправлена инструкция по восстановлению пароля.');

        return;
    }

    $st = ia_db()->prepare("SELECT id FROM platform_users WHERE email = ? AND status = 'active' LIMIT 1");
    $st->execute([$email]);
    $row = $st->fetch();
    if (!$row) {
        ia_flash('reset_info', 'Если указанный адрес существует, на него отправлена инструкция по восстановлению пароля.');

        return;
    }

    $userId = (int) $row['id'];
    $plainToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $plainToken);

    $cfg = ia_config()['security'];
    $minutes = (int) $cfg['reset_token_minutes'];
    $expires = (new DateTimeImmutable())->modify('+' . $minutes . ' minutes');

    $pdo = ia_db();
    $pdo->prepare('DELETE FROM platform_password_resets WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare(
        'INSERT INTO platform_password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
    )->execute([$userId, $tokenHash, $expires->format('Y-m-d H:i:s')]);

    $link = ia_public_url('reset-password.php') . '?token=' . rawurlencode($plainToken);

    if (!empty($cfg['log_reset_link_instead_of_mail'])) {
        ia_append_reset_log($email, $link);
    } else {
        ia_mail_send_reset($email, $link);
    }

    ia_flash('reset_info', 'Если указанный адрес существует, на него отправлена инструкция по восстановлению пароля.');
}

/**
 * @return array{user_id:int}|null
 */
function ia_platform_password_reset_find_valid(string $plainToken): ?array
{
    ia_ensure_public_moderation_schema(ia_db());
    $plainToken = trim($plainToken);
    if (strlen($plainToken) !== 64) {
        return null;
    }
    $hash = hash('sha256', $plainToken);
    $st = ia_db()->prepare(
        'SELECT id, user_id, expires_at FROM platform_password_resets WHERE token_hash = ? LIMIT 1'
    );
    $st->execute([$hash]);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    if (strtotime((string) $row['expires_at']) < time()) {
        return null;
    }

    return ['user_id' => (int) $row['user_id']];
}

function ia_platform_password_reset_complete(int $userId, string $plainToken, string $newPassword): bool
{
    $check = ia_platform_password_reset_find_valid($plainToken);
    if ($check === null || $check['user_id'] !== $userId) {
        return false;
    }
    if (strlen($newPassword) < 8) {
        return false;
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $pdo = ia_db();
    $pdo->prepare('UPDATE platform_users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
    $pdo->prepare('DELETE FROM platform_password_resets WHERE user_id = ?')->execute([$userId]);

    return true;
}
