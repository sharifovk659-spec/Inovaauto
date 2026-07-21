<?php
declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_listings.php';
require_once IA_ROOT . '/includes/platform_notifications.php';
use InnovaAuto\Security\Csrf;

ia_require_section('listings');
$id = ia_request_int('id');
$row = $id > 0 ? ia_admin_listing_by_id($id) : null;
if ($row === null) {
    ia_flash('listings_error', 'Объявление не найдено.');
    ia_redirect(ia_admin_url('listings.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('listings_error', 'Сессия устарела.');
    } else {
        $reason = ia_input_long_text($_POST['reason'] ?? '', 2000);
        if ($reason === '') {
            ia_flash('listings_error', 'Укажите причину отклонения.');
        } else {
            ia_admin_listing_reject($id, $reason);
            ia_platform_notify_listing_moderation(ia_db(), $id, 'rejected', $reason);
            ia_flash('listings_ok', 'Объявление отклонено с указанием причины.');
            ia_redirect(ia_admin_url('listings.php'));
        }
    }
}

$user = ia_current_user();
$pageTitle = 'Отклонение объявления';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<main class="container py-4">
    <h1 class="h4 mb-3">Отклонить объявление #<?= (int) $row['id'] ?></h1>
    <?php if ($msg = ia_flash('listings_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <form method="post" class="card card-body">
        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
        <div class="mb-3">
            <label class="form-label">Причина отклонения</label>
            <textarea class="form-control" rows="4" name="reason" required><?= ia_h((string) $row['rejection_reason']) ?></textarea>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-warning" type="submit">Отклонить</button>
            <a class="btn btn-outline-secondary" href="<?= ia_h(ia_admin_url('listings.php')) ?>">Отмена</a>
        </div>
    </form>
</main>
<?php require __DIR__ . '/partials/foot.php'; ?>
