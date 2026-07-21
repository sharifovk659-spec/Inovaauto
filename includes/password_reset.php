<?php

declare(strict_types=1);

require_once IA_ROOT . '/includes/helpers.php';

function ia_password_reset_request(string $email): void
{
    $email = ia_input_email($email);
    if ($email === '') {
        ia_flash('reset_info', 'Если указанный адрес существует, на него отправлена инструкция по восстановлению пароля.');

        return;
    }

    $st = ia_db()->prepare('SELECT id FROM admin_users WHERE email = ? AND is_active = 1 LIMIT 1');
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

    $del = ia_db()->prepare('DELETE FROM admin_password_resets WHERE user_id = ?');
    $del->execute([$userId]);

    $ins = ia_db()->prepare(
        'INSERT INTO admin_password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
    );
    $ins->execute([$userId, $tokenHash, $expires->format('Y-m-d H:i:s')]);

    $link = ia_admin_url('reset-password.php') . '?token=' . rawurlencode($plainToken);

    if (!empty($cfg['log_reset_link_instead_of_mail'])) {
        ia_append_reset_log($email, $link);
    } else {
        ia_mail_send_reset($email, $link);
    }

    ia_flash('reset_info', 'Если указанный адрес существует, на него отправлена инструкция по восстановлению пароля.');
}

function ia_append_reset_log(string $email, string $link): void
{
    $dir = IA_ROOT . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $line = sprintf("[%s] %s -> %s\n", date('c'), $email, $link);
    @file_put_contents($dir . '/password_reset.log', $line, FILE_APPEND);
}

function ia_mail_send_reset(string $to, string $link): void
{
    $from = ia_config()['mail']['from'];
    $subject = 'InnovaAuto — восстановление пароля';
    $body = "Перейдите по ссылке для сброса пароля (действует ограниченное время):\r\n\r\n" . $link . "\r\n";
    $headers = 'From: ' . $from . "\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

/**
 * @return array{user_id:int}|null
 */
function ia_password_reset_find_valid(string $plainToken): ?array
{
    $plainToken = trim($plainToken);
    if (strlen($plainToken) !== 64) {
        return null;
    }
    $hash = hash('sha256', $plainToken);
    $st = ia_db()->prepare(
        'SELECT id, user_id, expires_at FROM admin_password_resets WHERE token_hash = ? LIMIT 1'
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

function ia_password_reset_complete(int $userId, string $plainToken, string $newPassword): bool
{
    $check = ia_password_reset_find_valid($plainToken);
    if ($check === null || $check['user_id'] !== $userId) {
        return false;
    }
    if (strlen($newPassword) < 8) {
        return false;
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $up = ia_db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
    $up->execute([$hash, $userId]);

    $del = ia_db()->prepare(
        'DELETE FROM admin_password_resets WHERE user_id = ?'
    );
    $del->execute([$userId]);

    $rt = ia_db()->prepare('DELETE FROM admin_remember_tokens WHERE user_id = ?');
    $rt->execute([$userId]);

    return true;
}
