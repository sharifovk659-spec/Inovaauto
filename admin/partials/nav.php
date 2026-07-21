<?php

declare(strict_types=1);

/** @var array<string,mixed>|null $user */
$user = $user ?? ia_current_user();
$current = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$is = static fn (string $f): bool => $current === $f;
$na = static function (array $files) use ($is): bool {
    foreach ($files as $f) {
        if ($is($f)) {
            return true;
        }
    }

    return false;
};

$catalogOpen = $na(['catalog.php', 'brands.php', 'brand-edit.php', 'models.php', 'model-edit.php', 'categories.php', 'category-edit.php']);
$billingOpen = $na(['tariffs.php', 'tariff-edit.php', 'payments.php']);
$modOpen = $na(['chat-threads.php', 'chat-thread.php', 'chat-reports.php', 'listing-reports.php']);
$contentOpen = $na(['banners.php', 'notifications.php', 'contact-messages.php']);
$reportsOpen = $na(['reports.php', 'reports-export.php']);
$systemOpen = $na(['settings.php', 'team.php', 'security.php', 'database.php']);

$canUsers = ia_admin_can($user, 'users');
$canListings = ia_admin_can($user, 'listings');
$canCatalog = ia_admin_can($user, 'catalog');
$canBilling = ia_admin_can($user, 'billing');
$canMod = ia_admin_can($user, 'moderation');
$canContent = ia_admin_can($user, 'content');
$canReports = ia_admin_can($user, 'reports');
$canSettings = ia_admin_can($user, 'settings');
$canTeam = ia_admin_can($user, 'team');
$canSecurity = ia_admin_can($user, 'security');
$canDatabase = ia_admin_can($user, 'database');
$roleLabel = $user ? ia_admin_role_label_ru((string) ($user['role'] ?? '')) : '';

$adminContactNew = 0;
$adminContactPreview = [];
if ($canContent && defined('IA_ROOT')) {
    require_once IA_ROOT . '/includes/admin_contact_inbox.php';
    try {
        $adminContactNew = ia_admin_contact_inbox_new_count(ia_db());
        $adminContactPreview = ia_admin_contact_inbox_recent(ia_db(), 6, 'new');
    } catch (Throwable) {
        $adminContactNew = 0;
        $adminContactPreview = [];
    }
}

$searchQ = ia_get_search('q', 120);
$searchUsersUrl = ia_admin_url('users.php');
?>
<div class="ia-admin-root d-flex min-vh-100">
<div id="iaSidebarBackdrop" class="ia-sidebar-backdrop" aria-hidden="true"></div>
<aside class="ia-sidebar d-flex flex-column" id="iaSidebar" aria-label="Боковое меню">
    <div class="ia-sidebar-brand">
        <div class="ia-sidebar-brand-mark">IA</div>
        <div>
            <div class="ia-sidebar-brand-text">InnovaAuto</div>
            <div class="ia-sidebar-brand-sub">Admin</div>
        </div>
    </div>
    <nav class="ia-sidebar-nav">
        <div class="ia-nav-section-label">Меню</div>
        <a class="nav-link <?= $is('dashboard.php') ? 'active' : '' ?>" href="<?= ia_h(ia_admin_url('dashboard.php')) ?>">
            <i class="bi bi-speedometer2" aria-hidden="true"></i><span>Панель</span>
        </a>
        <?php if ($canUsers): ?>
        <a class="nav-link <?= $na(['users.php', 'user-view.php', 'user-edit.php']) ? 'active' : '' ?>" href="<?= ia_h(ia_admin_url('users.php')) ?>">
            <i class="bi bi-people" aria-hidden="true"></i><span>Пользователи</span>
        </a>
        <?php endif; ?>
        <?php if ($canListings): ?>
        <a class="nav-link <?= $na(['listings.php', 'listing-edit.php', 'listing-reject.php']) ? 'active' : '' ?>" href="<?= ia_h(ia_admin_url('listings.php')) ?>">
            <i class="bi bi-grid-1x2" aria-hidden="true"></i><span>Объявления</span>
        </a>
        <?php endif; ?>
        <?php if ($canCatalog): ?>
        <a class="nav-link <?= $na(['catalog.php', 'brands.php', 'brand-edit.php', 'models.php', 'model-edit.php']) ? 'active' : '' ?>" href="<?= ia_h(ia_admin_url('catalog.php')) ?>">
            <i class="bi bi-bookmark-star" aria-hidden="true"></i><span>Бренды и модели</span>
        </a>
        <?php endif; ?>
        <?php if ($canBilling): ?>
        <a class="nav-link <?= $na(['payments.php']) ? 'active' : '' ?>" href="<?= ia_h(ia_admin_url('payments.php')) ?>">
            <i class="bi bi-wallet2" aria-hidden="true"></i><span>Платежи</span>
        </a>
        <?php endif; ?>
        <?php if ($canReports): ?>
        <a class="nav-link <?= $reportsOpen ? 'active' : '' ?>" href="<?= ia_h(ia_admin_url('reports.php')) ?>">
            <i class="bi bi-bar-chart-line" aria-hidden="true"></i><span>Отчёты</span>
        </a>
        <?php endif; ?>
        <?php if ($canSettings): ?>
        <a class="nav-link <?= $is('settings.php') ? 'active' : '' ?>" href="<?= ia_h(ia_admin_url('settings.php')) ?>">
            <i class="bi bi-sliders" aria-hidden="true"></i><span>Настройки</span>
        </a>
        <?php endif; ?>
        <?php if ($canDatabase): ?>
        <a class="nav-link <?= $is('database.php') ? 'active' : '' ?>" href="<?= ia_h(ia_admin_url('database.php')) ?>">
            <i class="bi bi-database" aria-hidden="true"></i><span>База данных</span>
        </a>
        <?php endif; ?>

        <?php
        $showMore = $canCatalog || $canBilling || $canMod || $canContent || $canTeam || $canSecurity || $canDatabase;
        if ($showMore):
            $moreId = 'iaNavMore';
            $moreOpen = $catalogOpen || $billingOpen || $modOpen || $contentOpen || $systemOpen;
        ?>
        <div class="ia-nav-section-label mt-2 d-flex align-items-center justify-content-between">
            <span>Ещё</span>
            <button class="btn btn-link btn-sm text-secondary p-0 text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#<?= ia_h($moreId) ?>" aria-expanded="<?= $moreOpen ? 'true' : 'false' ?>">
                <i class="bi bi-chevron-down small"></i>
            </button>
        </div>
        <div class="collapse <?= $moreOpen ? 'show' : '' ?>" id="<?= ia_h($moreId) ?>">
            <?php if ($canCatalog): ?>
            <a class="nav-link py-2 <?= $na(['categories.php', 'category-edit.php']) ? 'active' : '' ?>" href="<?= ia_h(ia_admin_url('categories.php')) ?>">
                <i class="bi bi-folder2" aria-hidden="true"></i><span>Категории</span>
            </a>
            <?php endif; ?>
            <?php if ($canBilling): ?>
            <a class="nav-link py-2 <?= $na(['tariffs.php', 'tariff-edit.php']) ? 'active' : '' ?>" href="<?= ia_h(ia_admin_url('tariffs.php')) ?>">
                <i class="bi bi-tags" aria-hidden="true"></i><span>Тарифы</span>
            </a>
            <?php endif; ?>
            <?php if ($canMod): ?>
            <a class="nav-link py-2 <?= $na(['chat-threads.php', 'chat-thread.php']) ? 'active' : '' ?>" href="<?= ia_h(ia_admin_url('chat-threads.php')) ?>">
                <i class="bi bi-chat-dots" aria-hidden="true"></i><span>Чаты</span>
            </a>
            <a class="nav-link py-2 <?= $is('chat-reports.php') ? 'active' : '' ?>" href="<?= ia_h(ia_admin_url('chat-reports.php')) ?>">
                <i class="bi bi-flag" aria-hidden="true"></i><span>Жалобы на сообщения</span>
            </a>
            <a class="nav-link py-2 <?= $is('listing-reports.php') ? 'active' : '' ?>" href="<?= ia_h(ia_admin_url('listing-reports.php')) ?>">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>Жалобы на объявления</span>
            </a>
            <?php endif; ?>
            <?php if ($canContent): ?>
            <a class="nav-link py-2 <?= $is('banners.php') ? 'active' : '' ?>" href="<?= ia_h(ia_admin_url('banners.php')) ?>">
                <i class="bi bi-image" aria-hidden="true"></i><span>Баннеры</span>
            </a>
            <a class="nav-link py-2 <?= $is('notifications.php') ? 'active' : '' ?>" href="<?= ia_h(ia_admin_url('notifications.php')) ?>">
                <i class="bi bi-send" aria-hidden="true"></i><span>Рассылки</span>
            </a>
            <a class="nav-link py-2 <?= $is('contact-messages.php') ? 'active' : '' ?>" href="<?= ia_h(ia_admin_url('contact-messages.php')) ?>">
                <i class="bi bi-envelope-paper" aria-hidden="true"></i><span>Обращения<?php if ($adminContactNew > 0): ?> <span class="badge text-bg-warning ms-1"><?= (int) $adminContactNew ?></span><?php endif; ?></span>
            </a>
            <?php endif; ?>
            <?php if ($canTeam): ?>
            <a class="nav-link py-2 <?= $is('team.php') ? 'active' : '' ?>" href="<?= ia_h(ia_admin_url('team.php')) ?>">
                <i class="bi bi-person-badge" aria-hidden="true"></i><span>Команда</span>
            </a>
            <?php endif; ?>
            <?php if ($canSecurity): ?>
            <a class="nav-link py-2 <?= $is('security.php') ? 'active' : '' ?>" href="<?= ia_h(ia_admin_url('security.php')) ?>">
                <i class="bi bi-shield-check" aria-hidden="true"></i><span>Безопасность</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </nav>
</aside>

<div class="ia-workspace flex-grow-1 d-flex flex-column min-vh-100">
<header class="ia-topbar">
    <button type="button" class="ia-topbar-toggle d-lg-none" data-ia-sidebar-toggle aria-label="Открыть меню">
        <i class="bi bi-list fs-4"></i>
    </button>
    <?php if ($canUsers): ?>
    <form class="ia-search-form ms-lg-0" method="get" action="<?= ia_h($searchUsersUrl) ?>" role="search">
        <div class="ia-search-wrap">
            <i class="bi bi-search ia-search-icon" aria-hidden="true"></i>
            <label class="visually-hidden" for="iaTopSearch">Поиск пользователей</label>
            <input type="search" name="q" id="iaTopSearch" class="form-control" placeholder="Поиск пользователей…" value="<?= ia_h($searchQ) ?>" autocomplete="off">
        </div>
    </form>
    <?php else: ?>
    <div class="flex-grow-1"></div>
    <?php endif; ?>

    <div class="ia-topbar-actions ms-auto">
        <button type="button" class="ia-icon-btn ia-theme-cycle-btn ia-admin-theme-btn" id="iaThemeCycleBtn" aria-label="Тема оформления" title="Тема оформления — нажмите, чтобы переключить">
            <svg class="ia-theme-trigger-svg ia-theme-trigger-svg-light" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 18a6 6 0 1 1 0-12 6 6 0 0 1 0 12Zm0-16a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0V3a1 1 0 0 1 1-1Zm0 18a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0v-1a1 1 0 0 1 1-1ZM5.64 6.05l.71.71a1 1 0 1 1-1.41 1.41l-.71-.71a1 1 0 0 1 1.41-1.41Zm12.02 12.02.71.71a1 1 0 1 1-1.41 1.41l-.71-.71a1 1 0 0 1 1.41-1.41ZM4 13H3a1 1 0 1 1 0-2h1a1 1 0 1 1 0 2Zm18 0h-1a1 1 0 1 1 0-2h1a1 1 0 1 1 0 2ZM6.05 18.36l-.71.71a1 1 0 1 1-1.41-1.41l.71-.71a1 1 0 1 1 1.41 1.41Zm12.02-12.02-.71.71a1 1 0 1 1-1.41-1.41l.71-.71a1 1 0 1 1 1.41 1.41Z"/></svg>
            <svg class="ia-theme-trigger-svg ia-theme-trigger-svg-dark" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 14.5A8.5 8.5 0 0 1 9.5 3 8.5 8.5 0 1 0 21 14.5Z"/></svg>
            <svg class="ia-theme-trigger-svg ia-theme-trigger-svg-sepia" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-.99 0-.83.67-1.5 1.5-1.5H16c2.76 0 5-2.24 5-5 0-4.42-4.03-8-9-8Zm-5.5 9c-.83 0-1.5-.67-1.5-1.5S5.67 9 6.5 9 8 9.67 8 10.5 7.33 12 6.5 12Zm3-4C8.67 8 8 7.33 8 6.5S8.67 5 9.5 5s1.5.67 1.5 1.5S10.33 8 9.5 8Zm5 0c-.83 0-1.5-.67-1.5-1.5S13.67 5 14.5 5s1.5.67 1.5 1.5S15.33 8 14.5 8Zm3 4c-.83 0-1.5-.67-1.5-1.5S16.67 9 17.5 9s1.5.67 1.5 1.5-.67 1.5-1.5 1.5Z"/></svg>
            <svg class="ia-theme-trigger-svg ia-theme-trigger-svg-system" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H4V5Zm2 2v7h12V7H6Zm2 14h8v-2H8v2Zm3-4h2v-2h-2v2Z"/></svg>
        </button>
        <div class="dropdown">
            <button type="button" class="ia-icon-btn position-relative" data-bs-toggle="dropdown" aria-expanded="false" title="Уведомления">
                <i class="bi bi-bell"></i>
                <?php if ($adminContactNew > 0): ?>
                <span class="ia-notify-badge" aria-label="<?= (int) $adminContactNew ?> новых уведомлений"><?= $adminContactNew > 99 ? '99+' : (string) (int) $adminContactNew ?></span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow py-0" style="min-width: 320px; max-height: 420px; overflow-y: auto;">
                <li class="px-3 py-2 border-bottom border-secondary small d-flex justify-content-between align-items-center">
                    <span class="text-secondary">Уведомления</span>
                    <?php if ($adminContactNew > 0): ?>
                        <span class="badge text-bg-warning"><?= (int) $adminContactNew ?> нов.</span>
                    <?php endif; ?>
                </li>
                <?php if ($canContent && count($adminContactPreview) > 0): ?>
                    <?php foreach ($adminContactPreview as $cr): ?>
                        <?php
                        $crName = (string) ($cr['from_name'] ?? '');
                        $crMsg = (string) ($cr['message'] ?? '');
                        $crMsgShort = mb_strlen($crMsg) > 80 ? mb_substr($crMsg, 0, 77) . '…' : $crMsg;
                        ?>
                        <li>
                            <a class="dropdown-item py-2" href="<?= ia_h(ia_admin_url('contact-messages.php?status=new')) ?>">
                                <div class="small fw-semibold"><?= ia_h($crName !== '' ? $crName : 'Без имени') ?></div>
                                <div class="small text-secondary text-truncate d-block" style="max-width: 280px;"><?= ia_h($crMsgShort) ?></div>
                                <div class="small text-secondary opacity-75"><?= ia_h((string) ($cr['created_at'] ?? '')) ?></div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li><hr class="dropdown-divider my-0"></li>
                    <li><a class="dropdown-item small text-center" href="<?= ia_h(ia_admin_url('contact-messages.php')) ?>">Все обращения →</a></li>
                <?php else: ?>
                    <li class="px-3 py-4 text-center text-secondary small">Нет новых обращений с сайта</li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="dropdown">
            <button type="button" class="ia-icon-btn d-flex align-items-center gap-2 ps-2 pe-2" style="width:auto;min-width:auto;" data-bs-toggle="dropdown" aria-expanded="false" title="Профиль">
                <i class="bi bi-person-circle fs-5"></i>
                <span class="d-none d-md-inline small text-truncate" style="max-width: 120px;"><?= ia_h((string) ($user['username'] ?? '')) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><h6 class="dropdown-header"><?= ia_h((string) ($user['email'] ?? '')) ?></h6></li>
                <li><span class="dropdown-item-text small text-secondary"><?= ia_h($roleLabel) ?></span></li>
                <li><hr class="dropdown-divider"></li>
                <?php if ($canSettings): ?>
                <li><a class="dropdown-item" href="<?= ia_h(ia_admin_url('settings.php')) ?>"><i class="bi bi-sliders me-2"></i>Настройки</a></li>
                <?php endif; ?>
                <?php if ($canTeam): ?>
                <li><a class="dropdown-item" href="<?= ia_h(ia_admin_url('team.php')) ?>"><i class="bi bi-person-badge me-2"></i>Команда</a></li>
                <?php endif; ?>
                <?php if ($canSecurity): ?>
                <li><a class="dropdown-item" href="<?= ia_h(ia_admin_url('security.php')) ?>"><i class="bi bi-shield-check me-2"></i>Безопасность</a></li>
                <?php endif; ?>
                <?php if ($canDatabase): ?>
                <li><a class="dropdown-item" href="<?= ia_h(ia_admin_url('database.php')) ?>"><i class="bi bi-database me-2"></i>База данных</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= ia_h(ia_admin_url('logout.php')) ?>"><i class="bi bi-box-arrow-right me-2"></i>Выход</a></li>
            </ul>
        </div>
    </div>
</header>
