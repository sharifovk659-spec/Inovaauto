<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/public_queries.php';
require_once IA_ROOT . '/includes/listing_media.php';
require_once IA_ROOT . '/includes/public_moderation.php';
require_once IA_ROOT . '/includes/user_avatar.php';
require_once IA_ROOT . '/includes/platform_notifications.php';

ia_platform_require_login();

$pdo = ia_db();
$cu = ia_platform_current_user();
if ($cu === null) {
    exit;
}
$uid = (int) $cu['id'];
ia_platform_sync_seller_listing_notifications($pdo, $uid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $rtab = ia_input_enum($_POST['list_tab'] ?? 'active', ['active', 'pending', 'archive'], 'active');

    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('pub_error', 'Сессия устарела. Обновите страницу.');
        ia_redirect(ia_public_url('profile.php?list=' . rawurlencode($rtab)));
    }

    if ($action === 'profile') {
        $name = ia_post_text('name', 150);
        $phone = ia_post_phone('phone');
        $nw = ia_post_password('new_password');
        $nw2 = ia_post_password('new_password2');
        if ($nw !== '' && (strlen($nw) < 8 || $nw !== $nw2)) {
            ia_flash('pub_error', 'Пароль: минимум 8 символов и совпадение полей.');
        } else {
            $pdo->prepare('UPDATE platform_users SET name = ?, phone = ? WHERE id = ?')
                ->execute([$name, $phone, $uid]);
            if ($nw !== '') {
                $hash = password_hash($nw, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE platform_users SET password_hash = ? WHERE id = ?')
                    ->execute([$hash, $uid]);
            }
            ia_flash('pub_ok', 'Профиль сохранён.');
        }
    } elseif ($action === 'avatar_upload') {
        if (!isset($_FILES['avatar']) || !is_array($_FILES['avatar'])) {
            $tooBig = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && empty($_POST) && empty($_FILES);
            ia_flash(
                'pub_error',
                $tooBig
                    ? 'Файл слишком большой для настроек PHP (upload_max_filesize / post_max_size).'
                    : 'Файл не выбран.'
            );
        } else {
            $stored = ia_user_avatar_save($_FILES['avatar'], $uid);
            if ($stored === null) {
                ia_flash('pub_error', ia_user_avatar_save_error($_FILES['avatar']));
            } else {
                $st = $pdo->prepare('SELECT avatar_path FROM platform_users WHERE id = ?');
                $st->execute([$uid]);
                $old = (string) ($st->fetchColumn() ?: '');
                $pdo->prepare('UPDATE platform_users SET avatar_path = ? WHERE id = ?')
                    ->execute([$stored, $uid]);
                if ($old !== '' && $old !== $stored) {
                    ia_user_avatar_delete($old);
                }
                ia_flash('pub_ok', 'Фото профиля обновлено.');
            }
        }
    } elseif ($action === 'avatar_remove') {
        $st = $pdo->prepare('SELECT avatar_path FROM platform_users WHERE id = ?');
        $st->execute([$uid]);
        $old = (string) ($st->fetchColumn() ?: '');
        if ($old !== '') {
            ia_user_avatar_delete($old);
        }
        $pdo->prepare('UPDATE platform_users SET avatar_path = NULL WHERE id = ?')->execute([$uid]);
        ia_flash('pub_ok', 'Фото удалено.');
    } elseif ($action === 'mark_sold') {
        $lid = ia_post_int('listing_id');
        if ($lid > 0) {
            $st = $pdo->prepare("UPDATE ad_listings SET status='sold', sold_at=NOW() WHERE id=? AND user_id=? AND status='approved'");
            $st->execute([$lid, $uid]);
            if ($st->rowCount() > 0) {
                require_once IA_ROOT . '/includes/ia_cache.php';
                ia_cache_forget('pub_body_type_counts');
                require_once IA_ROOT . '/includes/listing_lifecycle.php';
                ia_listing_block_chat_threads_for_listing($pdo, $lid);
                ia_platform_notify_listing_sold($pdo, $lid, $uid);
                ia_flash('pub_ok', 'Объявление отмечено как проданное.');
            } else {
                ia_flash('pub_error', 'Не удалось отметить объявление как проданное.');
            }
        }
    } elseif ($action === 'reactivate_listing') {
        $lid = ia_post_int('listing_id');
        if ($lid > 0 && ia_pub_reactivate_archived_listing($pdo, $lid, $uid)) {
            ia_flash('pub_ok', 'Объявление отправлено на повторную проверку. После одобрения модератором снова появится в каталоге.');
        } else {
            ia_flash('pub_error', 'Вернуть можно только объявление из архива (скрытое из каталога).');
        }
    } elseif ($action === 'delete_listing') {
        $lid = ia_post_int('listing_id');
        if ($lid > 0 && ia_pub_delete_owned_listing($pdo, $lid, $uid)) {
            ia_flash('pub_ok', 'Объявление снято с публикации и перенесено в архив (данные сохранены).');
        } else {
            ia_flash('pub_error', 'Не удалось снять объявление с публикации.');
        }
    }

    ia_redirect(ia_public_url('profile.php?list=' . rawurlencode($rtab)));
}

$st = $pdo->prepare('SELECT * FROM platform_users WHERE id = ?');
$st->execute([$uid]);
$user = $st->fetch() ?: $cu;

$myListings = ia_pub_listings_for_owner($pdo, $uid);
$listTab = ia_input_enum($_GET['list'] ?? 'active', ['active', 'pending', 'archive'], 'active');
$showListingPublished = ($listTab === 'pending' && (string) ($_GET['published'] ?? '') === '1');

$activeCount = 0;
$pendingCount = 0;
$archiveCount = 0;
$viewsSum = 0;
$clicksSum = 0;
$favoritesSum = 0;
$messagesSum = 0;
foreach ($myListings as $ad) {
    $stLabel = (string) ($ad['status'] ?? '');
    if ($stLabel === 'approved') {
        $activeCount++;
    } elseif ($stLabel === 'pending') {
        $pendingCount++;
    } else {
        $archiveCount++;
    }
    $viewsSum += (int) ($ad['views_count'] ?? 0);
    $clicksSum += (int) ($ad['clicks_count'] ?? 0);
    $favoritesSum += (int) ($ad['favorites_count'] ?? 0);
    $messagesSum += (int) ($ad['messages_count'] ?? 0);
}

$filteredListings = array_values(array_filter(
    $myListings,
    static function (array $ad) use ($listTab): bool {
        $s = (string) ($ad['status'] ?? '');
        return match ($listTab) {
            'active' => $s === 'approved',
            'pending' => $s === 'pending',
            'archive' => in_array($s, ['rejected', 'archived', 'sold'], true),
            default => false,
        };
    }
));

$pageTitle = 'Профиль';
$iaBodyExtraClass = 'ia-page-profile';
$avatarUrl = ia_user_avatar_src($user['avatar_path'] ?? null);
$initials = mb_strtoupper(mb_substr((string) ($user['name'] ?: $user['email']), 0, 1));

$chatUnread = 0;
$notifUnread = 0;
if (function_exists('ia_pub_layout_state')) {
    $layoutState = ia_pub_layout_state($pdo, $cu);
    $chatUnread = (int) ($layoutState['chat_unread'] ?? 0);
    $notifUnread = (int) ($layoutState['notification_unread'] ?? 0);
}
$cabinetProfileUrl = ia_public_url('profile.php?list=active');

$iaCabinetChartMax = max(1, $viewsSum, $clicksSum, $messagesSum, $favoritesSum);
$iaCabinetChartBars = [
    ['label' => 'Просмотры', 'value' => $viewsSum, 'tone' => 'views'],
    ['label' => 'Клики', 'value' => $clicksSum, 'tone' => 'clicks'],
    ['label' => 'Сообщения', 'value' => $messagesSum, 'tone' => 'messages'],
    ['label' => 'В избранном', 'value' => $favoritesSum, 'tone' => 'favorites'],
];
$iaCabinetListingBars = [
    ['label' => 'Активные', 'value' => $activeCount, 'tone' => 'active'],
    ['label' => 'На проверке', 'value' => $pendingCount, 'tone' => 'pending'],
    ['label' => 'Архив', 'value' => $archiveCount, 'tone' => 'archive'],
];
$iaCabinetListingMax = max(1, $activeCount, $pendingCount, $archiveCount, count($myListings));

require IA_ROOT . '/includes/partials/site-header.php';
?>

<section class="py-4 py-lg-5 ia-page-section ia-cabinet-page ia-cabinet-page--mobile-premium">
    <div class="container ia-container">
        <?php if ($msg = ia_flash('pub_ok')): ?><div class="alert alert-success mb-4" role="status"><?= ia_h((string) $msg) ?></div><?php elseif ($showListingPublished): ?><div class="alert alert-success mb-4" role="status">Объявление на проверке. После одобрения модератора появится в каталоге.</div><?php endif; ?>
        <?php if ($msg = ia_flash('pub_error')): ?><div class="alert alert-danger mb-4"><?= ia_h((string) $msg) ?></div><?php endif; ?>
        <div class="row g-4 align-items-start">
            <aside class="col-12 col-xl-3 col-lg-4 ia-cabinet-aside">
                <div class="card ia-form-surface ia-cabinet-sidebar ia-cabinet-sidebar--compact">
                    <div class="ia-cabinet-user d-flex align-items-center gap-3">
                        <div class="ia-account-avatar ia-cabinet-avatar ia-cabinet-avatar--editable" id="iaCabinetAvatar" role="button" tabindex="0" title="Двойной клик — сменить фото" aria-label="Сменить фото профиля">
                            <?php if ($avatarUrl !== null): ?>
                                <img src="<?= ia_h($avatarUrl) ?>" alt="" class="ia-cabinet-avatar-img" width="96" height="96" <?= ia_img_perf_attrs(['loading' => 'eager']) ?>>
                            <?php else: ?>
                                <span><?= ia_h($initials) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-truncate"><?= ia_h((string) ($user['name'] ?: 'Пользователь')) ?></div>
                            <div class="small text-secondary text-truncate"><?= ia_h((string) ($user['phone'] ?: 'Телефон не указан')) ?></div>
                        </div>
                    </div>
                    <form method="post" enctype="multipart/form-data" class="ia-cabinet-avatar-form d-none" id="iaAvatarUploadForm">
                        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                        <input type="hidden" name="action" value="avatar_upload">
                        <input type="hidden" name="list_tab" value="<?= ia_h($listTab) ?>">
                        <input type="file" name="avatar" id="iaAvatarInput" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
                    </form>
                    <?php if ($avatarUrl !== null): ?>
                        <form method="post" class="mt-2">
                            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                            <input type="hidden" name="action" value="avatar_remove">
                            <input type="hidden" name="list_tab" value="<?= ia_h($listTab) ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">Удалить фото</button>
                        </form>
                    <?php endif; ?>
                    <nav class="ia-cabinet-nav ia-cabinet-nav--mobile d-lg-none" aria-label="Кабинет продавца">
                        <a class="ia-cabinet-nav-link" href="<?= ia_h(ia_public_url('index.php')) ?>"><i class="bi bi-house-door" aria-hidden="true"></i><span>Главная</span></a>
                        <a class="ia-cabinet-nav-link active" href="<?= ia_h($cabinetProfileUrl) ?>"><i class="bi bi-car-front" aria-hidden="true"></i><span>Мои объявления</span></a>
                        <a class="ia-cabinet-nav-link" href="<?= ia_h(ia_public_url('favorites.php')) ?>"><i class="bi bi-heart" aria-hidden="true"></i><span>Избранное</span></a>
                        <a class="ia-cabinet-nav-link" href="<?= ia_h(ia_public_url('messages.php')) ?>"><i class="bi bi-chat-dots" aria-hidden="true"></i><span>Сообщения</span><?php if ($chatUnread > 0): ?><em class="ia-cabinet-nav-badge"><?= $chatUnread ?></em><?php endif; ?></a>
                        <a class="ia-cabinet-nav-link" href="<?= ia_h(ia_public_url('notifications.php')) ?>"><i class="bi bi-bell" aria-hidden="true"></i><span>Уведомления</span><?php if ($notifUnread > 0): ?><em class="ia-cabinet-nav-badge"><?= $notifUnread ?></em><?php endif; ?></a>
                        <a class="ia-cabinet-nav-link" href="#profile-form"><i class="bi bi-gear" aria-hidden="true"></i><span>Настройки профиля</span></a>
                        <a class="ia-cabinet-nav-link ia-cabinet-nav-link--danger" href="<?= ia_h(ia_public_url('logout.php')) ?>"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Выход</span></a>
                    </nav>
                    <nav class="ia-cabinet-nav mt-3 d-none d-lg-grid" aria-label="Кабинет продавца">
                        <a class="ia-cabinet-nav-link" href="<?= ia_h(ia_public_url('index.php')) ?>"><i class="bi bi-house-door" aria-hidden="true"></i><span>Главная</span></a>
                        <a class="ia-cabinet-nav-link active" href="<?= ia_h($cabinetProfileUrl) ?>"><i class="bi bi-car-front" aria-hidden="true"></i><span>Мои объявления</span></a>
                        <a class="ia-cabinet-nav-link" href="<?= ia_h(ia_public_url('favorites.php')) ?>"><i class="bi bi-heart" aria-hidden="true"></i><span>Избранное</span></a>
                        <a class="ia-cabinet-nav-link" href="<?= ia_h(ia_public_url('messages.php')) ?>"><i class="bi bi-chat-dots" aria-hidden="true"></i><span>Сообщения</span><?php if ($chatUnread > 0): ?><em class="ia-cabinet-nav-badge"><?= $chatUnread ?></em><?php endif; ?></a>
                        <a class="ia-cabinet-nav-link" href="<?= ia_h(ia_public_url('notifications.php')) ?>"><i class="bi bi-bell" aria-hidden="true"></i><span>Уведомления</span><?php if ($notifUnread > 0): ?><em class="ia-cabinet-nav-badge"><?= $notifUnread ?></em><?php endif; ?></a>
                        <a class="ia-cabinet-nav-link" href="#profile-form"><i class="bi bi-gear" aria-hidden="true"></i><span>Настройки профиля</span></a>
                        <a class="ia-cabinet-nav-link ia-cabinet-nav-link--danger" href="<?= ia_h(ia_public_url('logout.php')) ?>"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Выход</span></a>
                    </nav>
                </div>
            </aside>
            <div class="col-12 col-xl-9 col-lg-8 ia-cabinet-main">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 ia-cabinet-main-head">
                    <h1 class="h4 mb-0">Мои объявления</h1>
                    <a class="btn ia-btn-accent btn-sm d-none d-md-inline-flex" href="<?= ia_h(ia_public_url('add-listing.php')) ?>">Разместить объявление</a>
                </div>
                <div class="row g-3 g-xl-4 mb-3 mb-xl-4 ia-cabinet-panels-row">
                    <div class="col-xl-4 order-1 order-xl-2 ia-cabinet-col-analytics">
                        <div class="card ia-form-surface ia-cabinet-panel ia-cabinet-analytics p-0 h-100">
                            <details class="ia-cabinet-fold ia-cabinet-fold--analytics" data-ia-fold="desktop-open">
                                <summary class="ia-cabinet-fold-summary">
                                    <span class="ia-cabinet-fold-summary-text">
                                        <i class="bi bi-bar-chart-line" aria-hidden="true"></i>
                                        <span class="ia-cabinet-fold-title">Базовая аналитика</span>
                                    </span>
                                    <span class="ia-cabinet-fold-hint small text-secondary"><?= count($myListings) ?> объявл.</span>
                                    <i class="bi bi-chevron-down ia-cabinet-fold-chev" aria-hidden="true"></i>
                                </summary>
                                <div class="ia-cabinet-fold-body p-3 p-md-4 pt-0 pt-md-0">
                            <div class="ia-cabinet-stats-grid">
                                <div class="ia-stat-mini"><div class="ia-stat-mini-head"><i class="bi bi-eye" aria-hidden="true"></i><span class="small text-secondary">Просмотры</span></div><div class="ia-stat-mini-value"><?= (int) $viewsSum ?></div></div>
                                <div class="ia-stat-mini"><div class="ia-stat-mini-head"><i class="bi bi-lightning-charge" aria-hidden="true"></i><span class="small text-secondary">Клики</span></div><div class="ia-stat-mini-value"><?= (int) $clicksSum ?></div></div>
                                <div class="ia-stat-mini"><div class="ia-stat-mini-head"><i class="bi bi-chat-dots" aria-hidden="true"></i><span class="small text-secondary">Сообщения</span></div><div class="ia-stat-mini-value"><?= (int) $messagesSum ?></div></div>
                                <div class="ia-stat-mini"><div class="ia-stat-mini-head"><i class="bi bi-heart" aria-hidden="true"></i><span class="small text-secondary">В избранном</span></div><div class="ia-stat-mini-value"><?= (int) $favoritesSum ?></div></div>
                                <div class="ia-stat-mini ia-stat-mini--wide"><div class="ia-stat-mini-head"><i class="bi bi-car-front" aria-hidden="true"></i><span class="small text-secondary">Объявления</span></div><div class="ia-stat-mini-value"><?= count($myListings) ?></div></div>
                            </div>
                            <div class="ia-cabinet-charts mt-3" aria-label="Диаграммы статистики">
                                <div class="ia-cabinet-chart-block">
                                    <h3 class="ia-cabinet-chart-title">Активность</h3>
                                    <div class="ia-cabinet-bars">
                                        <?php foreach ($iaCabinetChartBars as $bar): ?>
                                            <?php
                                            $barVal = (int) ($bar['value'] ?? 0);
                                            $barPct = (int) round(100 * $barVal / $iaCabinetChartMax);
                                            ?>
                                            <div class="ia-cabinet-bar-row">
                                                <span class="ia-cabinet-bar-label"><?= ia_h((string) $bar['label']) ?></span>
                                                <div class="ia-cabinet-bar-track" role="presentation">
                                                    <span
                                                        class="ia-cabinet-bar-fill ia-cabinet-bar-fill--<?= ia_h((string) ($bar['tone'] ?? 'views')) ?>"
                                                        style="width: <?= $barPct ?>%"
                                                    ></span>
                                                </div>
                                                <span class="ia-cabinet-bar-value"><?= $barVal ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="ia-cabinet-chart-block">
                                    <h3 class="ia-cabinet-chart-title">Объявления по статусу</h3>
                                    <div class="ia-cabinet-bars">
                                        <?php foreach ($iaCabinetListingBars as $bar): ?>
                                            <?php
                                            $barVal = (int) ($bar['value'] ?? 0);
                                            $barPct = (int) round(100 * $barVal / $iaCabinetListingMax);
                                            ?>
                                            <div class="ia-cabinet-bar-row">
                                                <span class="ia-cabinet-bar-label"><?= ia_h((string) $bar['label']) ?></span>
                                                <div class="ia-cabinet-bar-track" role="presentation">
                                                    <span
                                                        class="ia-cabinet-bar-fill ia-cabinet-bar-fill--<?= ia_h((string) ($bar['tone'] ?? 'active')) ?>"
                                                        style="width: <?= $barPct ?>%"
                                                    ></span>
                                                </div>
                                                <span class="ia-cabinet-bar-value"><?= $barVal ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="ia-cabinet-actions mt-2 mt-md-3">
                                <a class="btn ia-btn-accent w-100" href="<?= ia_h(ia_public_url('add-listing.php')) ?>">Продажа авто</a>
                                <p class="small text-secondary text-center mb-0 ia-cabinet-actions-hint">Минимум <?= (int) IA_LISTING_PHOTO_REQUIRED_COUNT ?> фото для публикации</p>
                                <a class="btn btn-outline-secondary" href="<?= ia_h(ia_public_url('messages.php')) ?>">
                                    <i class="bi bi-chat-dots me-1" aria-hidden="true"></i>Сообщения<?= $chatUnread > 0 ? ' (' . $chatUnread . ')' : '' ?>
                                </a>
                                <a class="btn btn-outline-secondary" href="<?= ia_h(ia_public_url('notifications.php')) ?>">
                                    <i class="bi bi-bell me-1" aria-hidden="true"></i>Уведомления<?= $notifUnread > 0 ? ' (' . $notifUnread . ')' : '' ?>
                                </a>
                                <a class="btn btn-outline-secondary" href="<?= ia_h(ia_public_url('favorites.php')) ?>">Избранное</a>
                            </div>
                                </div>
                            </details>
                        </div>
                    </div>
                    <div class="col-xl-8 order-2 order-xl-1 ia-cabinet-col-listings">
                        <div class="card ia-form-surface ia-cabinet-panel ia-cabinet-listings-card ia-cabinet-listings-card--premium p-0 h-100">
                            <div class="ia-cabinet-listings-head p-3 p-md-4 pb-2">
                            <div class="ia-listing-tabs ia-listing-tabs--premium mb-0" role="tablist" aria-label="Категории объявлений">
                                <a class="ia-listing-tab<?= $listTab === 'active' ? ' active' : '' ?>" href="<?= ia_h(ia_public_url('profile.php?list=active')) ?>" role="tab" aria-selected="<?= $listTab === 'active' ? 'true' : 'false' ?>"><span class="ia-listing-tab-label">Активные</span><span class="ia-listing-tab-count"><?= $activeCount ?></span></a>
                                <a class="ia-listing-tab<?= $listTab === 'pending' ? ' active' : '' ?>" href="<?= ia_h(ia_public_url('profile.php?list=pending')) ?>" role="tab" aria-selected="<?= $listTab === 'pending' ? 'true' : 'false' ?>"><span class="ia-listing-tab-label">На проверке</span><span class="ia-listing-tab-count"><?= $pendingCount ?></span></a>
                                <a class="ia-listing-tab<?= $listTab === 'archive' ? ' active' : '' ?>" href="<?= ia_h(ia_public_url('profile.php?list=archive')) ?>" role="tab" aria-selected="<?= $listTab === 'archive' ? 'true' : 'false' ?>"><span class="ia-listing-tab-label">Архив</span><span class="ia-listing-tab-count"><?= $archiveCount ?></span></a>
                            </div>
                            </div>
                            <div class="ia-cabinet-listings-body px-3 px-md-4 pb-3 pb-md-4">
                            <?php if (count($myListings) === 0): ?>
                                <div class="text-secondary py-2">Пока нет объявлений. <a href="<?= ia_h(ia_public_url('add-listing.php')) ?>">Разместить первое</a></div>
                            <?php elseif (count($filteredListings) === 0): ?>
                                <div class="text-secondary py-2">В этой категории пока нет объявлений.</div>
                            <?php else: ?>
                                <p class="ia-cabinet-list-hint ia-cabinet-list-hint--premium">Нажмите на объявление, чтобы открыть действия и статистику.</p>
                                <div class="ia-cabinet-list ia-cabinet-list--compact ia-cabinet-list--premium">
                                    <?php foreach ($filteredListings as $ad):
                                        $stLabel = (string) ($ad['status'] ?? '');
                                        $stClass = ia_pub_listing_status_css_class($stLabel);
                                        $thumb = ia_listing_photo_src($ad['photo_url'] ?? null);
                                        $cabAvail = ia_listing_availability_normalize((string) ($ad['availability'] ?? ''));
                                        $year = (int) ($ad['model_year'] ?? 0);
                                        $mileage = (int) ($ad['mileage_km'] ?? 0);
                                        $fuel = trim((string) ($ad['fuel_type'] ?? ''));
                                        $metaParts = [];
                                        $metaParts[] = $year > 0 ? (string) $year : '—';
                                        $metaParts[] = $mileage > 0 ? number_format($mileage, 0, '.', ' ') . ' км' : '— км';
                                        $fuelLbl = $fuel !== '' && function_exists('ia_listing_fuel_label_ru') ? ia_listing_fuel_label_ru($fuel) : $fuel;
                                        $metaParts[] = $fuelLbl !== '' ? $fuelLbl : '—';
                                        $carUrl = ia_public_url('car.php?id=' . (int) $ad['id']);
                                        $priceLabel = ia_listing_format_price((float) $ad['price'], (string) ($ad['currency'] ?? 'TJS'));
                                        $carTitle = (string) $ad['brand'] . ' ' . (string) $ad['model'];
                                        ?>
                                    <details class="ia-cabinet-item ia-cabinet-item--fold ia-cabinet-item--premium">
                                        <summary class="ia-cabinet-item-summary">
                                            <span class="ia-cabinet-item-summary-photo">
                                                <img src="<?= ia_h($thumb) ?>" alt="" <?= ia_img_perf_attrs(['width' => 72, 'height' => 54]) ?>>
                                            </span>
                                            <span class="ia-cabinet-item-summary-main">
                                                <span class="ia-cabinet-item-summary-title"><?= ia_h($carTitle) ?></span>
                                                <span class="ia-cabinet-item-summary-meta"><?= ia_h(implode(' · ', $metaParts)) ?></span>
                                            </span>
                                            <span class="ia-cabinet-item-summary-end">
                                                <span class="ia-cabinet-item-summary-price"><?= ia_h($priceLabel) ?></span>
                                                <span class="ia-cabinet-status <?= $stClass ?>"><?= ia_h(ia_pub_listing_status_ru($stLabel)) ?></span>
                                                <span class="ia-cabinet-item-chev-wrap" aria-hidden="true"><i class="bi bi-chevron-down ia-cabinet-item-chev"></i></span>
                                            </span>
                                        </summary>
                                        <div class="ia-cabinet-item-body">
                                            <div class="ia-cabinet-item-panel">
                                                <div class="ia-cabinet-item-panel-head">
                                                    <span class="ia-badge-availability <?= $cabAvail === 'on_order' ? 'ia-badge-availability--on-order' : 'ia-badge-availability--in-stock' ?>"><?= ia_h(ia_listing_availability_label_ru($cabAvail)) ?></span>
                                                    <span class="ia-cabinet-item-panel-meta"><?= ia_h(implode(' · ', $metaParts)) ?></span>
                                                </div>
                                                <div class="ia-cabinet-item-stats" aria-label="Статистика объявления">
                                                    <span class="ia-cab-stat ia-cab-stat--tile" title="Просмотры"><i class="bi bi-eye" aria-hidden="true"></i><span class="ia-cab-stat-val"><?= (int) ($ad['views_count'] ?? 0) ?></span><span class="ia-cab-stat-lbl">Просмотры</span></span>
                                                    <span class="ia-cab-stat ia-cab-stat--tile" title="Клики"><i class="bi bi-lightning-charge" aria-hidden="true"></i><span class="ia-cab-stat-val"><?= (int) ($ad['clicks_count'] ?? 0) ?></span><span class="ia-cab-stat-lbl">Клики</span></span>
                                                    <span class="ia-cab-stat ia-cab-stat--tile" title="В избранном"><i class="bi bi-heart" aria-hidden="true"></i><span class="ia-cab-stat-val"><?= (int) ($ad['favorites_count'] ?? 0) ?></span><span class="ia-cab-stat-lbl">Избранное</span></span>
                                                    <span class="ia-cab-stat ia-cab-stat--tile" title="Сообщения"><i class="bi bi-chat-dots" aria-hidden="true"></i><span class="ia-cab-stat-val"><?= (int) ($ad['messages_count'] ?? 0) ?></span><span class="ia-cab-stat-lbl">Чат</span></span>
                                                </div>
                                                <div class="ia-cabinet-item-panel-price"><?= ia_h($priceLabel) ?></div>
                                                <div class="ia-cabinet-item-actions">
                                                    <a class="btn btn-sm ia-btn-accent ia-cabinet-btn ia-cabinet-btn--primary" href="<?= ia_h(ia_public_url('edit-listing.php?id=' . (int) $ad['id'])) ?>">Изменить</a>
                                                    <?php if ($stLabel === 'approved'): ?>
                                                    <form method="post" class="ia-cabinet-action-form" onsubmit="return confirm('Отметить объявление как проданное?');">
                                                        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                                        <input type="hidden" name="action" value="mark_sold">
                                                        <input type="hidden" name="list_tab" value="<?= ia_h($listTab) ?>">
                                                        <input type="hidden" name="listing_id" value="<?= (int) $ad['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary ia-cabinet-btn w-100">Продано</button>
                                                    </form>
                                                    <?php endif; ?>
                                                    <?php if ($stLabel === 'archived'): ?>
                                                    <form method="post" class="ia-cabinet-action-form" onsubmit="return confirm('Отправить объявление на повторную проверку?');">
                                                        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                                        <input type="hidden" name="action" value="reactivate_listing">
                                                        <input type="hidden" name="list_tab" value="<?= ia_h($listTab) ?>">
                                                        <input type="hidden" name="listing_id" value="<?= (int) $ad['id'] ?>">
                                                        <button type="submit" class="btn btn-sm ia-btn-accent ia-cabinet-btn ia-cabinet-btn--primary w-100">Вернуть в каталог</button>
                                                    </form>
                                                    <?php endif; ?>
                                                    <?php if (in_array($stLabel, ['approved', 'pending'], true)): ?>
                                                    <form method="post" class="ia-cabinet-action-form" onsubmit="return confirm('Снять объявление с публикации?');">
                                                        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                                        <input type="hidden" name="action" value="delete_listing">
                                                        <input type="hidden" name="list_tab" value="<?= ia_h($listTab) ?>">
                                                        <input type="hidden" name="listing_id" value="<?= (int) $ad['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger ia-cabinet-btn w-100">Снять с публикации</button>
                                                    </form>
                                                    <?php endif; ?>
                                                    <a class="btn btn-sm btn-outline-secondary ia-cabinet-btn ia-cabinet-btn--ghost" href="<?= ia_h($carUrl) ?>">Открыть объявление</a>
                                                </div>
                                            </div>
                                        </div>
                                    </details>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="ia-cabinet-settings-anchor">
                <details id="profile-form" class="card ia-form-surface ia-cabinet-panel ia-cabinet-settings ia-cabinet-fold ia-cabinet-fold--settings p-0" data-ia-fold="desktop-open">
                    <summary class="ia-cabinet-fold-summary ia-cabinet-settings-summary">
                        <span class="ia-cabinet-fold-summary-text">
                            <i class="bi bi-gear" aria-hidden="true"></i>
                            <span class="ia-cabinet-fold-title">Настройки профиля</span>
                        </span>
                        <i class="bi bi-chevron-down ia-cabinet-fold-chev" aria-hidden="true"></i>
                    </summary>
                    <form method="post" class="ia-cabinet-fold-body p-3 p-md-4 pt-0">
                    <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="profile">
                    <input type="hidden" name="list_tab" value="<?= ia_h($listTab) ?>">
                    <div class="row g-2 g-md-3 ia-cabinet-settings-fields">
                        <div class="col-12 col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?= ia_h((string) ($user['email'] ?? '')) ?>" disabled>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Имя</label>
                            <input type="text" name="name" class="form-control" value="<?= ia_h((string) ($user['name'] ?? '')) ?>" required maxlength="150">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Телефон</label>
                            <input type="text" name="phone" class="form-control" value="<?= ia_h((string) ($user['phone'] ?? '')) ?>" maxlength="32">
                        </div>
                    </div>
                    <p class="ia-cabinet-settings-pass-label small text-secondary mb-2 mt-2 mt-md-3">Смена пароля (необязательно)</p>
                    <div class="row g-2 g-md-3 ia-cabinet-settings-pass">
                        <div class="col-12 col-md-6">
                            <input type="password" name="new_password" class="form-control" placeholder="Новый пароль" minlength="8" autocomplete="new-password">
                        </div>
                        <div class="col-12 col-md-6">
                            <input type="password" name="new_password2" class="form-control" placeholder="Повтор пароля" autocomplete="new-password">
                        </div>
                    </div>
                    <button type="submit" class="btn ia-btn-accent w-100 w-md-auto mt-2 mt-md-3">Сохранить</button>
                    </form>
                </details>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
  var input = document.getElementById('iaAvatarInput');
  var form = document.getElementById('iaAvatarUploadForm');
  var avatar = document.getElementById('iaCabinetAvatar');
  if (!input || !form || !avatar) return;

  function openPicker() {
    input.click();
  }

  input.addEventListener('change', function () {
    if (input.files && input.files.length > 0) {
      form.submit();
    }
  });

  avatar.addEventListener('dblclick', function (e) {
    e.preventDefault();
    openPicker();
  });

  avatar.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      openPicker();
    }
  });
})();

(function () {
  var mq = window.matchMedia('(min-width: 992px)');
  function applyCabinetFolds() {
    document.querySelectorAll('[data-ia-fold="desktop-open"]').forEach(function (el) {
      if (mq.matches) {
        el.setAttribute('open', '');
      } else {
        el.removeAttribute('open');
      }
    });
  }
  applyCabinetFolds();
  if (mq.addEventListener) {
    mq.addEventListener('change', applyCabinetFolds);
  } else if (mq.addListener) {
    mq.addListener(applyCabinetFolds);
  }
  if (window.location.hash === '#profile-form') {
    var settings = document.getElementById('profile-form');
    if (settings) {
      settings.setAttribute('open', '');
      settings.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }
})();
</script>

<?php require IA_ROOT . '/includes/partials/site-footer.php'; ?>
