<?php

declare(strict_types=1);

if (!defined('IA_ROOT')) {
    return;
}

$pdo = ia_db();
$cu = ia_platform_current_user();
$here = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$layoutState = ia_pub_layout_state($pdo, $cu);
$compareCount = (int) ($layoutState['compare_count'] ?? 0);
$profileUrl = ia_public_url($cu ? 'profile.php' : 'login.php?redirect=profile.php');
$profileActive = in_array($here, ['profile.php', 'messages.php'], true);
$sellUrl = ia_public_url($cu ? 'add-listing.php' : 'login.php?redirect=add-listing.php');
$sellActive = in_array($here, ['add-listing.php', 'edit-listing.php'], true);
?>
<nav class="ia-mobile-tabbar d-lg-none" aria-label="Быстрая навигация">
    <a class="ia-mobile-tab<?= $here === 'index.php' ? ' is-active' : '' ?>" href="<?= ia_h(ia_public_url('index.php')) ?>">
        <span class="ia-mobile-tab-ico-wrap" aria-hidden="true">
            <i class="bi bi-house-door"></i>
        </span>
        <span class="ia-mobile-tab-label">Главная</span>
    </a>
    <a class="ia-mobile-tab<?= $here === 'catalog.php' ? ' is-active' : '' ?>" href="<?= ia_h(ia_public_url('catalog.php')) ?>">
        <span class="ia-mobile-tab-ico-wrap" aria-hidden="true">
            <i class="bi bi-grid"></i>
        </span>
        <span class="ia-mobile-tab-label">Каталог</span>
    </a>
    <a class="ia-mobile-tab ia-mobile-tab--sell<?= $sellActive ? ' is-active' : '' ?>" href="<?= ia_h($sellUrl) ?>">
        <span class="ia-mobile-tab-ico-wrap ia-mobile-tab-ico-wrap--sell" aria-hidden="true">
            <i class="bi bi-plus-lg"></i>
        </span>
        <span class="ia-mobile-tab-label">Продажа</span>
    </a>
    <a class="ia-mobile-tab<?= $here === 'compare.php' ? ' is-active' : '' ?>" href="<?= ia_h(ia_public_url('compare.php')) ?>">
        <span class="ia-mobile-tab-ico-wrap" aria-hidden="true">
            <i class="bi bi-sliders"></i>
            <?php if ($compareCount > 0): ?><em class="ia-mobile-tab-badge"><?= (int) $compareCount ?></em><?php endif; ?>
        </span>
        <span class="ia-mobile-tab-label">Сравнить</span>
    </a>
    <a class="ia-mobile-tab<?= $profileActive ? ' is-active' : '' ?>" href="<?= ia_h($profileUrl) ?>">
        <span class="ia-mobile-tab-ico-wrap" aria-hidden="true">
            <i class="bi bi-person"></i>
        </span>
        <span class="ia-mobile-tab-label"><?= $cu ? 'Кабинет' : 'Войти' ?></span>
    </a>
</nav>
