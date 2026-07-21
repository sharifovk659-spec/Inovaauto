<?php

declare(strict_types=1);

/** Навигация кабинета (desktop grid / mobile список). */
$iaCabinetNavVariant = (string) ($iaCabinetNavVariant ?? 'desktop');
$iaCabinetListTab = (string) ($iaCabinetListTab ?? 'active');
$iaCabinetChatUnread = (int) ($iaCabinetChatUnread ?? 0);
$iaCabinetNotifUnread = (int) ($iaCabinetNotifUnread ?? 0);

$navClass = $iaCabinetNavVariant === 'mobile'
    ? 'ia-cabinet-nav ia-cabinet-nav--mobile d-lg-none'
    : 'ia-cabinet-nav mt-3 d-none d-lg-grid';

$profileActiveUrl = ia_public_url('profile.php?list=active');
?>
<nav class="<?= ia_h($navClass) ?>" aria-label="Кабинет продавца">
    <a class="ia-cabinet-nav-link" href="<?= ia_h(ia_public_url('index.php')) ?>">
        <i class="bi bi-house-door" aria-hidden="true"></i><span>Главная</span>
    </a>
    <a class="ia-cabinet-nav-link active" href="<?= ia_h($profileActiveUrl) ?>">
        <i class="bi bi-car-front" aria-hidden="true"></i><span>Мои объявления</span>
    </a>
    <a class="ia-cabinet-nav-link" href="<?= ia_h(ia_public_url('favorites.php')) ?>">
        <i class="bi bi-heart" aria-hidden="true"></i><span>Избранное</span>
    </a>
    <a class="ia-cabinet-nav-link" href="<?= ia_h(ia_public_url('messages.php')) ?>">
        <i class="bi bi-chat-dots" aria-hidden="true"></i>
        <span>Сообщения</span>
        <?php if ($iaCabinetChatUnread > 0): ?>
            <em class="ia-cabinet-nav-badge"><?= (int) $iaCabinetChatUnread ?></em>
        <?php endif; ?>
    </a>
    <a class="ia-cabinet-nav-link" href="<?= ia_h(ia_public_url('notifications.php')) ?>">
        <i class="bi bi-bell" aria-hidden="true"></i>
        <span>Уведомления</span>
        <?php if ($iaCabinetNotifUnread > 0): ?>
            <em class="ia-cabinet-nav-badge"><?= (int) $iaCabinetNotifUnread ?></em>
        <?php endif; ?>
    </a>
    <a class="ia-cabinet-nav-link" href="#profile-form">
        <i class="bi bi-gear" aria-hidden="true"></i><span>Настройки профиля</span>
    </a>
    <a class="ia-cabinet-nav-link ia-cabinet-nav-link--danger" href="<?= ia_h(ia_public_url('logout.php')) ?>">
        <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Выход</span>
    </a>
</nav>
