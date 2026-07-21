<?php

declare(strict_types=1);

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';

$pdo = ia_db();
$siteName = ia_site_setting_get($pdo, 'site_name', 'InovaAuto');
$pageTitle = 'Политика конфиденциальности';

require IA_ROOT . '/includes/partials/site-header.php';
?>

<section class="ia-legal-page ia-page-section py-4 py-md-5">
    <div class="container ia-container" style="max-width: 760px;">
        <header class="ia-legal-head mb-4">
            <span class="text-secondary small text-uppercase" style="letter-spacing: 0.12em;"><?= ia_h($siteName) ?></span>
            <h1 class="h3 fw-bold mt-2 mb-3">Политика конфиденциальности</h1>
            <p class="ia-legal-lead text-secondary mb-0">
                Добро пожаловать на <?= ia_h($siteName) ?>. Мы уважаем вашу конфиденциальность и стремимся
                обеспечить защиту персональных данных каждого пользователя платформы.
            </p>
        </header>

        <article class="ia-legal-body">
            <section class="ia-legal-section">
                <h2 class="h6 fw-bold">1. Какие данные мы собираем</h2>
                <p>При использовании сайта <?= ia_h($siteName) ?> мы можем собирать следующую информацию:</p>
                <ul>
                    <li>Имя и фамилия;</li>
                    <li>Номер телефона;</li>
                    <li>Адрес электронной почты;</li>
                    <li>Пароль учетной записи (в зашифрованном виде);</li>
                    <li>Информация об объявлениях автомобилей;</li>
                    <li>Фотографии, загруженные пользователем;</li>
                    <li>IP-адрес и технические данные устройства;</li>
                    <li>История действий на сайте.</li>
                </ul>
            </section>

            <section class="ia-legal-section">
                <h2 class="h6 fw-bold">2. Для чего используются данные</h2>
                <p>Собранная информация используется для:</p>
                <ul>
                    <li>Регистрации и авторизации пользователей;</li>
                    <li>Размещения и управления объявлениями;</li>
                    <li>Связи между продавцами и покупателями;</li>
                    <li>Обеспечения безопасности платформы;</li>
                    <li>Улучшения работы сайта и качества обслуживания;</li>
                    <li>Предотвращения мошеннических действий.</li>
                </ul>
            </section>

            <section class="ia-legal-section">
                <h2 class="h6 fw-bold">3. Передача данных третьим лицам</h2>
                <p><?= ia_h($siteName) ?> не продает и не передает персональные данные пользователей третьим лицам, за исключением случаев:</p>
                <ul>
                    <li>Когда это требуется законодательством;</li>
                    <li>Для защиты прав и безопасности пользователей и платформы;</li>
                    <li>При использовании платежных или технических сервисов, необходимых для работы сайта.</li>
                </ul>
            </section>

            <section class="ia-legal-section">
                <h2 class="h6 fw-bold">4. Защита информации</h2>
                <p>
                    Мы применяем современные технические и организационные меры безопасности для защиты данных
                    пользователей от несанкционированного доступа, изменения, раскрытия или уничтожения.
                </p>
            </section>

            <section class="ia-legal-section">
                <h2 class="h6 fw-bold">5. Cookies</h2>
                <p>Сайт использует файлы Cookie для:</p>
                <ul>
                    <li>Авторизации пользователей;</li>
                    <li>Сохранения настроек;</li>
                    <li>Анализа посещаемости;</li>
                    <li>Улучшения пользовательского опыта.</li>
                </ul>
                <p class="mb-0">Пользователь может отключить Cookie в настройках браузера.</p>
            </section>

            <section class="ia-legal-section">
                <h2 class="h6 fw-bold">6. Права пользователя</h2>
                <p>Каждый пользователь имеет право:</p>
                <ul>
                    <li>Получить информацию о своих данных;</li>
                    <li>Изменить или обновить свои данные;</li>
                    <li>Удалить учетную запись;</li>
                    <li>Отозвать согласие на обработку персональных данных.</li>
                </ul>
            </section>

            <section class="ia-legal-section">
                <h2 class="h6 fw-bold">7. Контакты</h2>
                <p>По вопросам обработки персональных данных вы можете связаться с администрацией <?= ia_h($siteName) ?>:</p>
                <ul class="mb-0">
                    <li>Email: <a href="mailto:info@inovaauto.com">info@inovaauto.com</a></li>
                    <li>Сайт: <a href="https://inovaauto.com" rel="noopener noreferrer">inovaauto.com</a></li>
                    <li>Адрес: Душанбе, Республика Таджикистан</li>
                </ul>
            </section>

            <section class="ia-legal-section">
                <h2 class="h6 fw-bold">8. Изменение политики</h2>
                <p class="mb-0">
                    Администрация <?= ia_h($siteName) ?> оставляет за собой право вносить изменения в настоящую
                    Политику конфиденциальности. Актуальная версия всегда публикуется на сайте.
                </p>
            </section>

            <p class="ia-legal-note text-secondary small mt-4 mb-0">
                Используя сайт <?= ia_h($siteName) ?>, вы подтверждаете свое согласие с настоящей Политикой
                конфиденциальности и условиями обработки персональных данных.
            </p>
        </article>
    </div>
</section>

<?php require IA_ROOT . '/includes/partials/site-footer.php'; ?>
