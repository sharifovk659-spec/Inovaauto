<?php

declare(strict_types=1);

namespace InnovaAuto\Tests\Security;

use InnovaAuto\Security\Recaptcha;
use PHPUnit\Framework\TestCase;

/**
 * Логика reCAPTCHA: при отключённых ключах проверка не блокирует вход.
 */
final class RecaptchaTest extends TestCase
{
    /** Без site/secret ключей вход не блокируется (локальная разработка). */
    public function testDisabledWhenKeysAreEmpty(): void
    {
        $config = [
            'recaptcha' => [
                'site_key' => '',
                'secret' => '',
            ],
        ];
        $this->assertFalse(Recaptcha::isEnabled($config));
        $this->assertTrue(Recaptcha::verify($config, null));
    }

    /** Если ключи заданы, пустой g-recaptcha-response отклоняется. */
    public function testEmptyResponseFailsWhenEnabled(): void
    {
        $config = [
            'recaptcha' => [
                'site_key' => 'x',
                'secret' => 'y',
            ],
        ];
        $this->assertTrue(Recaptcha::isEnabled($config));
        $this->assertFalse(Recaptcha::verify($config, ''));
    }
}
