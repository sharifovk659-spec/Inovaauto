<?php

/**
 * Простой прогон проверок без PHPUnit (если Composer недоступен).
 * Запуск: php tests/unit_simple.php
 */

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));

require_once IA_ROOT . '/src/Security/Csrf.php';
require_once IA_ROOT . '/src/Security/Recaptcha.php';

use InnovaAuto\Security\Csrf;
use InnovaAuto\Security\Recaptcha;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = [];

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

$t = Csrf::token();
ok(strlen($t) === 64 && Csrf::validate($t), 'CSRF токен должен проходить проверку');
ok(!Csrf::validate('bad'), 'Неверный CSRF отклоняется');

$cfg = ['recaptcha' => ['site_key' => '', 'secret' => '']];
ok(Recaptcha::verify($cfg, null), 'При отключённой капче проверка не блокирует');

echo "OK: все простые проверки прошли.\n";
