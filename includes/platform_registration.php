<?php

declare(strict_types=1);

require_once IA_ROOT . '/includes/password_reset.php';

const IA_REG_CODE_TTL = 900;
const IA_REG_CODE_MAX_ATTEMPTS = 5;
const IA_REG_RESEND_COOLDOWN = 60;

function ia_platform_mask_email(string $email): string
{
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
        return '***';
    }
    $visible = mb_substr($parts[0], 0, 1);

    return $visible . '***@' . $parts[1];
}

/**
 * @return array<string, mixed>|null
 */
function ia_platform_reg_pending(): ?array
{
    $pending = $_SESSION['ia_reg_pending'] ?? null;
    if (!is_array($pending) || empty($pending['email']) || empty($pending['code_hash'])) {
        return null;
    }
    if ((int) ($pending['expires_at'] ?? 0) < time()) {
        unset($_SESSION['ia_reg_pending']);

        return null;
    }

    return $pending;
}

function ia_platform_reg_clear(): void
{
    unset($_SESSION['ia_reg_pending']);
}

/**
 * @return array{email_masked:string}|null
 */
function ia_platform_reg_pending_public(): ?array
{
    $raw = $_SESSION['ia_reg_pending'] ?? null;
    if (is_array($raw) && (int) ($raw['expires_at'] ?? 0) > 0 && (int) $raw['expires_at'] < time()) {
        ia_platform_reg_clear();
        ia_flash('pub_error', 'Срок кода истёк. Заполните форму ещё раз.');

        return null;
    }

    $pending = ia_platform_reg_pending();
    if ($pending === null) {
        return null;
    }

    return [
        'email_masked' => ia_platform_mask_email((string) $pending['email']),
    ];
}

function ia_platform_reg_send_code_mail(string $email, string $code): bool
{
    $subject = 'InnovaAuto — код подтверждения';
    $body = "Здравствуйте!\r\n\r\n"
        . "Код для завершения регистрации: " . $code . "\r\n\r\n"
        . "Код действует 15 минут. Никому его не сообщайте.\r\n"
        . "Если вы не регистрировались на InnovaAuto, просто удалите это письмо.\r\n";

    return ia_mail_deliver($email, $subject, $body);
}

/**
 * @param array<string, mixed> $pending
 */
function ia_platform_reg_store(array $pending, string $code): void
{
    $pending['code_hash'] = hash('sha256', $code);
    $pending['expires_at'] = time() + IA_REG_CODE_TTL;
    $pending['attempts'] = 0;
    $pending['sent_at'] = time();
    $_SESSION['ia_reg_pending'] = $pending;
}

function ia_platform_reg_begin(
    string $name,
    string $phone,
    string $email,
    string $password,
    string $accountType
): bool {
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    if (!ia_platform_reg_send_code_mail($email, $code)) {
        return false;
    }

    ia_platform_reg_store([
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'account_type' => $accountType === 'dealer' ? 'dealer' : 'private',
    ], $code);

    return true;
}

function ia_platform_reg_resend(): string
{
    $pending = ia_platform_reg_pending();
    if ($pending === null) {
        return 'missing';
    }
    $sentAt = (int) ($pending['sent_at'] ?? 0);
    if ($sentAt > 0 && (time() - $sentAt) < IA_REG_RESEND_COOLDOWN) {
        return 'cooldown';
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    if (!ia_platform_reg_send_code_mail((string) $pending['email'], $code)) {
        return 'mail_failed';
    }

    $pending['code_hash'] = hash('sha256', $code);
    $pending['expires_at'] = time() + IA_REG_CODE_TTL;
    $pending['attempts'] = 0;
    $pending['sent_at'] = time();
    $_SESSION['ia_reg_pending'] = $pending;

    return 'ok';
}

function ia_platform_reg_confirm(string $code): string
{
    $pending = ia_platform_reg_pending();
    if ($pending === null) {
        return 'expired';
    }

    $code = preg_replace('/\D+/', '', $code) ?? '';
    if (strlen($code) !== 6) {
        return 'bad_code';
    }

    $attempts = (int) ($pending['attempts'] ?? 0);
    if ($attempts >= IA_REG_CODE_MAX_ATTEMPTS) {
        ia_platform_reg_clear();

        return 'locked';
    }

    $expected = (string) ($pending['code_hash'] ?? '');
    if ($expected === '' || !hash_equals($expected, hash('sha256', $code))) {
        $pending['attempts'] = $attempts + 1;
        $_SESSION['ia_reg_pending'] = $pending;
        if ($pending['attempts'] >= IA_REG_CODE_MAX_ATTEMPTS) {
            ia_platform_reg_clear();

            return 'locked';
        }

        return 'bad_code';
    }

    $email = (string) $pending['email'];
    if (ia_platform_find_by_email($email) !== null) {
        ia_platform_reg_clear();

        return 'email_taken';
    }

    if (!ia_platform_insert_user(
        (string) $pending['name'],
        (string) $pending['phone'],
        $email,
        (string) $pending['password_hash'],
        (string) $pending['account_type']
    )) {
        return 'save_failed';
    }

    $user = ia_platform_find_by_email($email);
    ia_platform_reg_clear();
    if ($user === null) {
        return 'save_failed';
    }

    ia_platform_login_user($user);

    return 'ok';
}
