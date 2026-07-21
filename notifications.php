<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/public_queries.php';
require_once IA_ROOT . '/includes/platform_notifications.php';

ia_platform_require_login();

$pdo = ia_db();
$uid = (int) ia_platform_current_user()['id'];
ia_platform_sync_seller_listing_notifications($pdo, $uid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('pub_error', 'Сессия устарела. Обновите страницу.');
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'mark_all_read') {
            ia_platform_notification_mark_all_read($pdo, $uid);
            ia_flash('pub_ok', 'Все уведомления отмечены прочитанными.');
        } elseif ($action === 'mark_read') {
            ia_platform_notification_mark_read($pdo, $uid, (int) ($_POST['notification_id'] ?? 0));
        }
    }
    ia_redirect(ia_public_url('notifications.php'));
}

$rows = ia_platform_notifications_for_user($pdo, $uid, 60);
$unreadCount = ia_platform_notifications_unread_count($pdo, $uid);
$pageTitle = 'Уведомления';

require IA_ROOT . '/includes/partials/site-header.php';
?>

<section class="py-5 ia-page-section ia-cabinet-page">
    <div class="container ia-container">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <h1 class="h4 mb-1">Уведомления</h1>
                <p class="text-secondary small mb-0">Новые сообщения, модерация и срок публикации объявлений.</p>
            </div>
            <?php if ($unreadCount > 0): ?>
                <form method="post" class="mb-0">
                    <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Отметить все прочитанными</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($msg = ia_flash('pub_ok')): ?><div class="alert alert-success"><?= ia_h((string) $msg) ?></div><?php endif; ?>
        <?php if ($msg = ia_flash('pub_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>

        <?php if (count($rows) === 0): ?>
            <div class="card ia-form-surface p-4 text-center text-secondary">Пока нет уведомлений.</div>
        <?php else: ?>
            <div class="ia-notifications-list">
                <?php foreach ($rows as $row): ?>
                    <?php
                    $isRead = !empty($row['is_read']);
                    $kind = (string) ($row['kind'] ?? '');
                    $link = trim((string) ($row['link_url'] ?? ''));
                    $createdAt = trim((string) ($row['created_at'] ?? ''));
                    $createdLabel = $createdAt;
                    try {
                        $createdLabel = (new DateTimeImmutable($createdAt))->format('d.m.Y, H:i');
                    } catch (\Throwable) {
                    }
                    ?>
                    <article class="ia-notification-item<?= $isRead ? '' : ' ia-notification-item--unread' ?>">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="flex-grow-1">
                                <div class="small text-secondary mb-1"><?= ia_h(ia_platform_notification_kind_label_ru($kind)) ?> · <?= ia_h($createdLabel) ?></div>
                                <h2 class="h6 mb-1"><?= ia_h((string) ($row['title'] ?? '')) ?></h2>
                                <p class="small text-secondary mb-0"><?= ia_h((string) ($row['body'] ?? '')) ?></p>
                                <?php if ($link !== ''): ?>
                                    <a class="small mt-2 d-inline-block" href="<?= ia_h($link) ?>">Открыть</a>
                                <?php endif; ?>
                            </div>
                            <?php if (!$isRead): ?>
                                <form method="post" class="mb-0">
                                    <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Прочитано</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require IA_ROOT . '/includes/partials/site-footer.php'; ?>
