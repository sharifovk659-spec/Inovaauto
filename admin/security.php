<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';

ia_require_section('security');

$cfg = ia_config();
$user = ia_current_user();
$pageTitle = 'Безопасность';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';

$idle = (int) ($cfg['security']['admin_idle_seconds'] ?? 0);
$sessionLife = (int) ($cfg['session']['lifetime'] ?? 3600);
?>
<main class="container-fluid px-3 px-lg-4 py-4">
    <h1 class="h4 mb-3">Безопасность (ТЗ §19)</h1>
    <p class="text-secondary small mb-4">Реализованные меры защиты в текущей сборке админ-панели.</p>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6">CSRF</h2>
                    <p class="small mb-0">Формы используют токен <code>InnovaAuto\Security\Csrf</code>: вход, сброс пароля, действия с пользователями/объявлениями, справочники, биллинг, модерация, баннеры, рассылки, настройки, команда.</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6">XSS</h2>
                    <p class="small mb-0">Вывод в шаблонах через <code>ia_h()</code> (htmlspecialchars). Пользовательский HTML не вставляется без экранирования.</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6">SQL-инъекции</h2>
                    <p class="small mb-0">Доступ к MySQL через PDO с подготовленными выражениями (<code>prepare</code> / плейсхолдеры), без конкатенации пользовательского ввода в SQL.</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6">Сессия и таймаут</h2>
                    <p class="small mb-0">Параметр cookie сессии: время жизни <strong><?= (int) $sessionLife ?> с</strong> (php.ini / настройка). Бездействие в админке: <strong><?= $idle > 0 ? $idle . ' с (' . round($idle / 60) . ' мин)' : 'отключено (0)' ?></strong> — настраивается в <code>config</code> как <code>security.admin_idle_seconds</code> или <code>IA_ADMIN_IDLE_SECONDS</code>.</p>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6">Пароли</h2>
                    <p class="small mb-0">Хранение: <code>password_hash</code> (bcrypt/argon в зависимости от PHP). Проверка: <code>password_verify</code>. Токены &laquo;запомнить меня&raquo; — через селектор + валидатор в БД, не в открытом виде.</p>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/partials/foot.php'; ?>
