<?php

declare(strict_types=1);

namespace InnovaAuto\Security;

final class Csrf
{
    private const SESSION_KEY = '_ia_csrf';

    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('Session must be active for CSRF protection.');
        }
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function validate(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $expected = $_SESSION[self::SESSION_KEY] ?? '';
        if ($expected === '' || $token === null || $token === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    /** Проверка для PHPUnit / сброса формы */
    public static function resetForTesting(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }
}
