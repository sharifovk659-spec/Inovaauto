<?php

$pdo = ia_db();
$siteSettings = ia_site_settings_cached($pdo);
$siteName = $siteSettings['site_name'] ?? 'InnovaAuto';
$logoPath = trim((string) ($siteSettings['logo_path'] ?? ''));
$logoUrl = '';
if ($logoPath !== '') {
    $logoDisk = IA_ROOT . '/uploads/site/' . basename(str_replace(['\\', '/'], '', $logoPath));
    if (is_file($logoDisk)) {
        $logoUrl = ia_site_logo_public_url($logoPath);
    }
}
$useBuiltinBrand = ($logoUrl === '');
$logoHeightCfg = (int) ($siteSettings['logo_height'] ?? '56');
if ($logoHeightCfg < 24) {
    $logoHeightCfg = 24;
}
if ($logoHeightCfg > 80) {
    $logoHeightCfg = 80;
}
$logoBgMode = (string) ($siteSettings['logo_bg_mode'] ?? 'transparent');
if (!in_array($logoBgMode, ['transparent', 'white', 'dark', 'custom'], true)) {
    $logoBgMode = 'transparent';
}
$logoBgColor = (string) ($siteSettings['logo_bg_color'] ?? '');
if ($logoBgColor !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $logoBgColor)) {
    $logoBgColor = '';
}
$logoBgCss = 'transparent';
if ($logoBgMode === 'white') {
    $logoBgCss = '#ffffff';
} elseif ($logoBgMode === 'dark') {
    $logoBgCss = '#0f172a';
} elseif ($logoBgMode === 'custom' && $logoBgColor !== '') {
    $logoBgCss = $logoBgColor;
}
$profileLoginUrl = ia_public_url('login.php?redirect=profile.php');
$cu = ia_platform_current_user();
$pageTitle = $pageTitle ?? $siteName;
$metaDesc = $siteSettings['meta_description'] ?? '';
$here = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$prefillQ = ia_get_search('q');
$layoutState = ia_pub_layout_state($pdo, $cu);
$favCount = (int) ($layoutState['fav_count'] ?? 0);
$compareCount = (int) ($layoutState['compare_count'] ?? 0);
$notificationUnread = (int) ($layoutState['notification_unread'] ?? 0);
$cuAvatarUrl = null;
if ($cu && function_exists('ia_user_avatar_src')) {
    $cuAvatarUrl = ia_user_avatar_src($cu['avatar_path'] ?? null);
}
$iaBodyExtraClassStr = (string) ($iaBodyExtraClass ?? '');
$isHomePage = $iaBodyExtraClassStr !== '' && strpos($iaBodyExtraClassStr, 'ia-page-home') !== false;
$isAddPremium = $iaBodyExtraClassStr !== '' && strpos($iaBodyExtraClassStr, 'ia-add-premium') !== false;

$iaSiteCssHref = function_exists('ia_public_site_css_href')
    ? ia_public_site_css_href()
    : ia_public_asset('assets/site.css') . '?v=173';
$iaCriticalCssPath = function_exists('ia_critical_above_fold_css_path') ? ia_critical_above_fold_css_path() : null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <script>
(function () {
  try {
    var KEY = 'ia_theme_pref';
    var pref = localStorage.getItem(KEY);
    if (pref !== 'light' && pref !== 'dark' && pref !== 'sepia' && pref !== 'system') pref = 'system';
    function palette(p) {
      if (p === 'sepia') return 'sepia';
      if (p === 'light') return 'light';
      if (p === 'dark') return 'dark';
      return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    function bsTheme(p) {
      return palette(p) === 'dark' ? 'dark' : 'light';
    }
    var root = document.documentElement;
    var pal = palette(pref);
    root.setAttribute('data-ia-theme-pref', pref);
    root.setAttribute('data-ia-palette', pal);
    root.setAttribute('data-bs-theme', bsTheme(pref));
    var tc = pal === 'dark' ? '#050b18' : (pal === 'sepia' ? '#f3ebd9' : '#f8fafc');
    document.querySelectorAll('meta[name="theme-color"]').forEach(function (m) {
      m.setAttribute('content', tc);
    });
  } catch (e) {}
})();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8fafc" media="(max-width: 991.98px)">
    <title><?= ia_h($pageTitle) ?> — <?= ia_h($siteName) ?></title>
    <?php if ($metaDesc !== ''): ?><meta name="description" content="<?= ia_h($metaDesc) ?>"><?php endif; ?>
    <?php
    $iaCanonicalUrl = function_exists('ia_page_canonical_url') ? ia_page_canonical_url() : '';
    if ($iaCanonicalUrl !== ''): ?>
    <link rel="canonical" href="<?= ia_h($iaCanonicalUrl) ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous"></noscript>
    <link rel="stylesheet" href="<?= ia_h($iaSiteCssHref) ?>">
    <link rel="icon" href="<?= ia_h(ia_public_asset('assets/brand/innovaauto-mark.svg')) ?>" type="image/svg+xml">
    <style id="ia-mobile-canvas-critical-v3"><?php
    if ($iaCriticalCssPath !== null) {
        readfile($iaCriticalCssPath);
    }
    ?></style>
    <?php
    if (!empty($GLOBALS['ia_open_graph_html'])) {
        echo $GLOBALS['ia_open_graph_html'] . "\n";
    }
    if (!empty($GLOBALS['ia_extra_head_html'])) {
        echo $GLOBALS['ia_extra_head_html'];
    }
    ?>
</head>
<body class="ia-site ia-has-logo<?= !empty($iaBodyExtraClass) ? ' ' . ia_h((string) $iaBodyExtraClass) : '' ?><?= $isHomePage ? ' ia-page-home-body' : '' ?>" data-ia-tab-accent="light-blue">
<header class="ia-site-header ia-site-header--compact sticky-top<?= $isHomePage ? ' ia-site-header--home' : '' ?><?= $isAddPremium ? ' ia-site-header--add-premium' : '' ?>">
    <nav class="navbar navbar-expand-lg ia-nav shadow-sm">
        <div class="container ia-container">
            <?php
            $brandShellStyle = '';
            if ($logoUrl !== '' && $logoBgMode !== 'transparent') {
                $brandShellStyle = 'background:' . $logoBgCss . ';padding:6px 10px;border-radius:10px;';
            }
            ?>
            <a class="navbar-brand d-flex align-items-center ia-brand-shell<?= $useBuiltinBrand ? '' : ' ia-brand-shell--logo' ?><?= $useBuiltinBrand ? '' : ' ia-brand-shell--logo-mobile-text' ?>" href="<?= ia_h(ia_public_url('index.php')) ?>" aria-label="<?= ia_h($siteName) ?>"<?= $brandShellStyle !== '' ? ' style="' . ia_h($brandShellStyle) . '"' : '' ?>>
                <?php if ($useBuiltinBrand): ?>
                    <span class="ia-brand-lockup ia-brand-lockup--full d-none d-lg-inline-flex"><?php require IA_ROOT . '/includes/partials/brand-logo-inline.php'; ?></span>
                    <span class="ia-brand-lockup ia-brand-lockup--compact d-inline-flex d-lg-none align-items-center">
                        <?php require IA_ROOT . '/includes/partials/brand-mark-only.php'; ?>
                    </span>
                <?php else: ?>
                    <img src="<?= ia_h($logoUrl) ?>" alt="<?= ia_h($siteName) ?>" class="ia-brand-logo" style="height:<?= (int) $logoHeightCfg ?>px;max-height:<?= (int) $logoHeightCfg ?>px" width="<?= (int) max(24, $logoHeightCfg * 2) ?>" height="<?= (int) $logoHeightCfg ?>" <?= ia_img_perf_attrs(['loading' => 'eager', 'fetchpriority' => 'high']) ?> onerror="this.classList.add('ia-brand-logo--failed');this.closest('.ia-brand-shell')?.classList.add('ia-brand-shell--logo-failed');">
                <?php endif; ?>
                <span class="ia-brand-text"><?= ia_h($siteName) ?></span>
            </a>
            <div class="ia-mobile-header d-flex<?= $isAddPremium ? ' ia-mobile-header--add-form' : '' ?> d-lg-none align-items-center flex-grow-1 min-w-0">
                <form class="ia-mobile-search" method="get" action="<?= ia_h(ia_public_url('catalog.php')) ?>" role="search">
                    <input type="search" name="q" class="ia-mobile-search-input" placeholder="Поиск..." value="<?= ia_h($prefillQ) ?>" aria-label="Поиск" autocomplete="off" enterkeyhint="search">
                    <button class="ia-mobile-search-submit" type="submit" aria-label="Найти">
                        <i class="bi bi-search" aria-hidden="true"></i>
                    </button>
                </form>
                <div class="ia-mobile-header-actions">
                <button type="button" class="ia-mobile-header-btn ia-theme-cycle-btn" id="iaThemeCycleBtn" aria-label="Тема оформления" title="Тема оформления — нажмите, чтобы переключить">
                    <svg class="ia-theme-trigger-svg ia-theme-trigger-svg-light" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 18a6 6 0 1 1 0-12 6 6 0 0 1 0 12Zm0-16a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0V3a1 1 0 0 1 1-1Zm0 18a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0v-1a1 1 0 0 1 1-1ZM5.64 6.05l.71.71a1 1 0 1 1-1.41 1.41l-.71-.71a1 1 0 0 1 1.41-1.41Zm12.02 12.02.71.71a1 1 0 1 1-1.41 1.41l-.71-.71a1 1 0 0 1 1.41-1.41ZM4 13H3a1 1 0 1 1 0-2h1a1 1 0 1 1 0 2Zm18 0h-1a1 1 0 1 1 0-2h1a1 1 0 1 1 0 2ZM6.05 18.36l-.71.71a1 1 0 1 1-1.41-1.41l.71-.71a1 1 0 1 1 1.41 1.41Zm12.02-12.02-.71.71a1 1 0 1 1-1.41-1.41l.71-.71a1 1 0 1 1 1.41 1.41Z"/></svg>
                    <svg class="ia-theme-trigger-svg ia-theme-trigger-svg-dark" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 14.5A8.5 8.5 0 0 1 9.5 3 8.5 8.5 0 1 0 21 14.5Z"/></svg>
                    <svg class="ia-theme-trigger-svg ia-theme-trigger-svg-sepia" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-.99 0-.83.67-1.5 1.5-1.5H16c2.76 0 5-2.24 5-5 0-4.42-4.03-8-9-8Zm-5.5 9c-.83 0-1.5-.67-1.5-1.5S5.67 9 6.5 9 8 9.67 8 10.5 7.33 12 6.5 12Zm3-4C8.67 8 8 7.33 8 6.5S8.67 5 9.5 5s1.5.67 1.5 1.5S10.33 8 9.5 8Zm5 0c-.83 0-1.5-.67-1.5-1.5S13.67 5 14.5 5s1.5.67 1.5 1.5S15.33 8 14.5 8Zm3 4c-.83 0-1.5-.67-1.5-1.5S16.67 9 17.5 9s1.5.67 1.5 1.5-.67 1.5-1.5 1.5Z"/></svg>
                    <svg class="ia-theme-trigger-svg ia-theme-trigger-svg-system" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H4V5Zm2 2v7h12V7H6Zm2 14h8v-2H8v2Zm3-4h2v-2h-2v2Z"/></svg>
                </button>
                <a class="ia-mobile-header-btn ia-mobile-header-btn--fav<?= $here === 'favorites.php' ? ' is-active' : '' ?>" href="<?= ia_h(ia_public_url($cu ? 'favorites.php' : 'login.php?redirect=favorites.php')) ?>" aria-label="Избранное<?= $favCount > 0 ? ' (' . (int) $favCount . ')' : '' ?>" title="Избранное">
                    <span class="ia-mobile-header-btn-core">
                        <i class="bi bi-heart" aria-hidden="true"></i>
                        <?php if ($favCount > 0): ?><span class="ia-mobile-header-badge"><?= (int) $favCount ?></span><?php endif; ?>
                    </span>
                </a>
                <?php if ($cu): ?>
                    <a class="ia-mobile-header-btn ia-mobile-header-btn--notify" href="<?= ia_h(ia_public_url('notifications.php')) ?>" aria-label="Уведомления<?= $notificationUnread > 0 ? ' (' . (int) $notificationUnread . ')' : '' ?>" title="Уведомления">
                        <span class="ia-mobile-header-btn-core">
                            <i class="bi bi-bell" aria-hidden="true"></i>
                            <?php if ($notificationUnread > 0): ?><span class="ia-mobile-header-badge"><?= (int) $notificationUnread ?></span><?php endif; ?>
                        </span>
                    </a>
                <?php else: ?>
                    <a class="ia-mobile-header-btn ia-mobile-header-btn--notify" href="<?= ia_h(ia_public_url('login.php')) ?>" aria-label="Уведомления" title="Войдите для уведомлений">
                        <i class="bi bi-bell" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
                </div>
            </div>
            <div class="collapse navbar-collapse ia-nav-collapse" id="iaNavMain">
                <ul class="navbar-nav ia-nav-primary mx-lg-auto my-2 my-lg-0">
                    <li class="nav-item"><a class="nav-link <?= $here === 'index.php' ? 'active' : '' ?>" href="<?= ia_h(ia_public_url('index.php')) ?>">Главная</a></li>
                    <li class="nav-item"><a class="nav-link <?= $here === 'catalog.php' ? 'active' : '' ?>" href="<?= ia_h(ia_public_url('catalog.php')) ?>">Каталог</a></li>
                    <li class="nav-item"><a class="nav-link <?= $here === 'about.php' ? 'active' : '' ?>" href="<?= ia_h(ia_public_url('about.php')) ?>">Услуги</a></li>
                    <li class="nav-item"><a class="nav-link <?= $here === 'blog.php' ? 'active' : '' ?>" href="<?= ia_h(ia_public_url('blog.php')) ?>">Блог</a></li>
                </ul>
                <div class="ia-nav-tools d-flex flex-column flex-xl-row align-items-stretch align-items-xl-center gap-2 ms-xl-auto">
                    <form class="ia-mini-search d-flex flex-shrink-0" method="get" action="<?= ia_h(ia_public_url('catalog.php')) ?>" role="search">
                        <input type="search" name="q" class="form-control form-control-sm" placeholder="Поиск…" value="<?= ia_h($prefillQ) ?>" aria-label="Поиск">
                        <button class="btn btn-sm ia-btn-accent ms-1 flex-shrink-0" type="submit">Найти</button>
                    </form>
                    <div class="ia-nav-auth d-flex align-items-center gap-2">
                        <button type="button" class="ia-nav-icon-btn ia-theme-cycle-btn" id="iaThemeCycleBtnDesktop" aria-label="Тема оформления" title="Тема оформления — нажмите, чтобы переключить">
                            <svg class="ia-theme-trigger-svg ia-theme-trigger-svg-light" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 18a6 6 0 1 1 0-12 6 6 0 0 1 0 12Zm0-16a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0V3a1 1 0 0 1 1-1Zm0 18a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0v-1a1 1 0 0 1 1-1ZM5.64 6.05l.71.71a1 1 0 1 1-1.41 1.41l-.71-.71a1 1 0 0 1 1.41-1.41Zm12.02 12.02.71.71a1 1 0 1 1-1.41 1.41l-.71-.71a1 1 0 0 1 1.41-1.41ZM4 13H3a1 1 0 1 1 0-2h1a1 1 0 1 1 0 2Zm18 0h-1a1 1 0 1 1 0-2h1a1 1 0 1 1 0 2ZM6.05 18.36l-.71.71a1 1 0 1 1-1.41-1.41l.71-.71a1 1 0 1 1 1.41 1.41Zm12.02-12.02-.71.71a1 1 0 1 1-1.41-1.41l.71-.71a1 1 0 1 1 1.41 1.41Z"/></svg>
                            <svg class="ia-theme-trigger-svg ia-theme-trigger-svg-dark" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 14.5A8.5 8.5 0 0 1 9.5 3 8.5 8.5 0 1 0 21 14.5Z"/></svg>
                            <svg class="ia-theme-trigger-svg ia-theme-trigger-svg-sepia" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-.99 0-.83.67-1.5 1.5-1.5H16c2.76 0 5-2.24 5-5 0-4.42-4.03-8-9-8Zm-5.5 9c-.83 0-1.5-.67-1.5-1.5S5.67 9 6.5 9 8 9.67 8 10.5 7.33 12 6.5 12Zm3-4C8.67 8 8 7.33 8 6.5S8.67 5 9.5 5s1.5.67 1.5 1.5S10.33 8 9.5 8Zm5 0c-.83 0-1.5-.67-1.5-1.5S13.67 5 14.5 5s1.5.67 1.5 1.5S15.33 8 14.5 8Zm3 4c-.83 0-1.5-.67-1.5-1.5S16.67 9 17.5 9s1.5.67 1.5 1.5-.67 1.5-1.5 1.5Z"/></svg>
                            <svg class="ia-theme-trigger-svg ia-theme-trigger-svg-system" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H4V5Zm2 2v7h12V7H6Zm2 14h8v-2H8v2Zm3-4h2v-2h-2v2Z"/></svg>
                        </button>
                        <?php if ($cu): ?>
                            <a class="ia-nav-icon-btn" href="<?= ia_h(ia_public_url('compare.php')) ?>" aria-label="Сравнение" title="Сравнить автомобили">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3v18H7v-3H3v-2h4V8H3V6h4V3h2zm6 0v3h4v2h-4v8h4v2h-4v3h-2V3h2z"/></svg>
                                <?php if ($compareCount > 0): ?><span class="ia-nav-icon-badge"><?= (int) $compareCount ?></span><?php endif; ?>
                            </a>
                            <a class="ia-nav-icon-btn" href="<?= ia_h(ia_public_url('favorites.php')) ?>" aria-label="Избранное">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21.2l-1.45-1.32C5.4 15.25 2 12.16 2 8.5 2 5.41 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.41 22 8.5c0 3.66-3.4 6.75-8.55 11.39z"/></svg>
                                <?php if ($favCount > 0): ?><span class="ia-nav-icon-badge"><?= (int) $favCount ?></span><?php endif; ?>
                            </a>
                            <a class="ia-nav-icon-btn" href="<?= ia_h(ia_public_url('notifications.php')) ?>" aria-label="Уведомления" title="Уведомления">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22zm6-6V11a6 6 0 1 0-12 0v5L4 18v1h16v-1l-2-2z"/></svg>
                                <?php if ($notificationUnread > 0): ?><span class="ia-nav-icon-badge"><?= (int) $notificationUnread ?></span><?php endif; ?>
                            </a>
                            <a class="ia-nav-icon-btn ia-nav-user-btn<?= $cuAvatarUrl !== null ? ' ia-nav-user-btn--avatar' : '' ?>" href="<?= ia_h(ia_public_url('profile.php')) ?>" aria-label="Профиль / Кабинет" title="Профиль / Кабинет">
                                <?php if ($cuAvatarUrl !== null): ?>
                                    <img class="ia-nav-user-avatar" src="<?= ia_h($cuAvatarUrl) ?>" alt="" width="32" height="32" <?= ia_img_perf_attrs() ?>>
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-4.42 0-8 2-8 4.5V21h16v-2.5C20 16 16.42 14 12 14z"/></svg>
                                <?php endif; ?>
                            </a>
                        <?php else: ?>
                            <a class="ia-nav-icon-btn" href="<?= ia_h(ia_public_url('compare.php')) ?>" aria-label="Сравнение" title="Сравнить автомобили">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3v18H7v-3H3v-2h4V8H3V6h4V3h2zm6 0v3h4v2h-4v8h4v2h-4v3h-2V3h2z"/></svg>
                                <?php if ($compareCount > 0): ?><span class="ia-nav-icon-badge"><?= (int) $compareCount ?></span><?php endif; ?>
                            </a>
                            <a class="ia-nav-icon-btn" href="<?= ia_h(ia_public_url('login.php')) ?>" aria-label="Избранное" title="Избранное">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21.2l-1.45-1.32C5.4 15.25 2 12.16 2 8.5 2 5.41 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.41 22 8.5c0 3.66-3.4 6.75-8.55 11.39z"/></svg>
                            </a>
                            <a class="ia-nav-icon-btn" href="<?= ia_h(ia_public_url('login.php')) ?>" aria-label="Уведомления" title="Уведомления">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22zm6-6V11a6 6 0 1 0-12 0v5L4 18v1h16v-1l-2-2z"/></svg>
                            </a>
                            <a class="ia-nav-icon-btn" href="<?= ia_h($profileLoginUrl) ?>" aria-label="Профиль / Кабинет" title="Профиль / Кабинет">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-4.42 0-8 2-8 4.5V21h16v-2.5C20 16 16.42 14 12 14z"/></svg>
                            </a>
                        <?php endif; ?>
                        <a class="ia-btn-post-listing" href="<?= ia_h(ia_public_url('add-listing.php')) ?>" title="Разместить объявление о продаже автомобиля">
                            <svg class="ia-btn-post-listing-icon" viewBox="0 0 24 24" width="17" height="17" aria-hidden="true"><path fill="currentColor" d="M11 11V5h2v6h6v2h-6v6h-2v-6H5v-2h6z"/></svg>
                            <span>Продажа авто</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</header>
<main class="ia-site-main">
