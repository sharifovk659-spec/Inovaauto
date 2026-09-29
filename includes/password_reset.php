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

    if (!ia_mail_send_reset($email, $link)) {
        ia_flash('reset_error', 'Не удалось отправить письмо со ссылкой. Попробуйте ещё раз через несколько минут.');

        return;
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

function ia_mail_from_address(): string
{
    $from = trim((string) (ia_config()['mail']['from'] ?? ''));
    if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL) !== false && !str_ends_with(strtolower($from), '.local')) {
        return $from;
    }

    $host = '';
    if (function_exists('ia_edge_canonical_host')) {
        $host = ia_edge_canonical_host();
    }
    if ($host === '' && function_exists('ia_env')) {
        $base = rtrim((string) ia_env('IA_BASE_URL', ''), '/');
        if ($base !== '' && preg_match('#^https?://([^/]+)#i', $base, $m)) {
            $host = strtolower($m[1]);
        }
    }
    $host = strtolower(preg_replace('/:\d+$/', '', $host) ?? '');
    if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
        $host = 'inovaauto.com';
    }

    return 'noreply@' . $host;
}

function ia_mail_send(string $to, string $subject, string $body): bool
{
    $to = trim($to);
    if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }

    $from = ia_mail_from_address();
    $headers = implode("\r\n", [
        'From: InnovaAuto <' . $from . '>',
        'Reply-To: ' . $from,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ]);
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $ok = PHP_OS_FAMILY === 'Windows'
        ? @mail($to, $encodedSubject, $body, $headers)
        : @mail($to, $encodedSubject, $body, $headers, '-f' . $from);

    return $ok === true;
}

/**
 * На проде письмо всегда уходит на почту.
 * Локально, если mail() недоступен или включён IA_DEBUG_RESET_LOG, текст пишется в storage/logs.
 */
function ia_mail_deliver(string $to, string $subject, string $body): bool
{
    $local = function_exists('ia_is_local_request') && ia_is_local_request();
    $debugLog = !empty(ia_config()['security']['log_reset_link_instead_of_mail']);
    if ($local && $debugLog) {
        ia_append_reset_log($to, $subject . "\n" . $body);

        return true;
    }

    $ok = ia_mail_send($to, $subject, $body);
    if (!$ok && $local) {
        ia_append_reset_log($to, $subject . "\n" . $body);

        return true;
    }
    if (!$ok) {
        error_log('ia_mail_send failed for ' . $to);
    }

    return $ok;
}

function ia_mail_send_reset(string $to, string $link): bool
{
    $subject = 'InnovaAuto — восстановление пароля';
    $body = "Перейдите по ссылке, чтобы задать новый пароль. Ссылка действует ограниченное время:\r\n\r\n"
        . $link . "\r\n\r\n"
        . "Если вы не запрашивали сброс пароля, просто удалите это письмо.\r\n";

    return ia_mail_deliver($to, $subject, $body);
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
