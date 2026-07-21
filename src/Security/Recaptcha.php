<?php

declare(strict_types=1);

namespace InnovaAuto\Security;

final class Recaptcha
{
    public static function isEnabled(array $config): bool
    {
        $site = trim((string) ($config['recaptcha']['site_key'] ?? ''));
        $secret = trim((string) ($config['recaptcha']['secret'] ?? ''));

        return $site !== '' && $secret !== '';
    }

    public static function verify(array $config, ?string $responseToken): bool
    {
        if (!self::isEnabled($config)) {
            return true;
        }
        $secret = trim((string) ($config['recaptcha']['secret'] ?? ''));
        $token = trim((string) ($responseToken ?? ''));
        if ($token === '') {
            return false;
        }

        $payload = http_build_query([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 5,
            ],
        ]);

        $raw = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
        if ($raw === false) {
            return false;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return false;
        }

        return !empty($data['success']);
    }
}
