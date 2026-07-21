<?php

declare(strict_types=1);

/**
 * Загрузка автозагрузчика и старт сессии для тестов (CSRF и т.п.).
 */
define('IA_ROOT', dirname(__DIR__));

require_once IA_ROOT . '/vendor/autoload.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
