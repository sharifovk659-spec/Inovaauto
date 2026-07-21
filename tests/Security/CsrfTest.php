<?php

declare(strict_types=1);

namespace InnovaAuto\Tests\Security;

use InnovaAuto\Security\Csrf;
use PHPUnit\Framework\TestCase;

/**
 * Тесты CSRF-токена: генерация и проверка должны быть предсказуемы и безопасны.
 */
final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        Csrf::resetForTesting();
        $_SESSION = [];
    }

    /** Токен длиной 64 hex-символов успешно проходит validate(). */
    public function testGeneratedTokenPassesValidation(): void
    {
        $token = Csrf::token();
        $this->assertSame(64, strlen($token));
        $this->assertTrue(Csrf::validate($token));
    }

    /** Подделанный токен не должен совпадать с session. */
    public function testWrongTokenIsRejected(): void
    {
        Csrf::token();
        $this->assertFalse(Csrf::validate('00' . str_repeat('a', 62)));
    }

    /** Пустое значение из формы не принимается. */
    public function testEmptyTokenIsRejected(): void
    {
        Csrf::token();
        $this->assertFalse(Csrf::validate(''));
        $this->assertFalse(Csrf::validate(null));
    }
}
