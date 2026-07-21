<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_moderation.php';

ia_require_section('moderation');
ia_moderation_listing_complaints_post();

$pdo = ia_db();
$complaints = ia_moderation_listing_complaints_list($pdo);
$pageTitle = 'Жалобы на объявления';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';

$statusRu = static function (string $s): string {
    return match ($s) {
        'pending' => 'ожидает',
        'reviewed' => 'рассмотрена',
        'dismissed' => 'отклонена',
        default => $s,
    };
};
?>
<main class="container-fluid px-3 px-lg-4 py-4">
    <h1 class="h4 mb-3">Модерация: жалобы на объявления</h1>
    <?php if ($msg = ia_flash('mod_ok')): ?><div class="alert alert-success"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <?php if ($msg = ia_flash('mod_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>

    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Статус</th>
                    <th>Объявление</th>
                    <th>Жалоба</th>
                    <th>Кто пожаловался</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($complaints as $c): ?>
                <tr>
                    <td><?= (int) $c['id'] ?></td>
                    <td><span class="badge <?= ($c['status'] ?? '') === 'pending' ? 'text-bg-warning' : 'text-bg-secondary' ?>"><?= ia_h($statusRu((string) ($c['status'] ?? ''))) ?></span></td>
                    <td class="small">
                        <a href="<?= ia_h(ia_public_url('car.php?id=' . (int) ($c['listing_id'] ?? 0))) ?>" target="_blank" rel="noopener noreferrer">
                            <?= ia_h(trim((string) ($c['brand'] ?? '') . ' ' . (string) ($c['model'] ?? ''))) ?>
                        </a>
                        <div class="text-secondary">#<?= (int) ($c['listing_id'] ?? 0) ?></div>
                    </td>
                    <td class="small" style="max-width:260px"><?= nl2br(ia_h((string) ($c['reason'] ?? ''))) ?></td>
                    <td class="small"><?= ia_h((string) ($c['reporter_name'] ?? '')) ?><br><?= ia_h((string) ($c['reporter_email'] ?? '')) ?></td>
                    <td>
                        <?php if (($c['status'] ?? '') === 'pending'): ?>
                            <form method="post" class="d-grid gap-1">
                                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                <input type="hidden" name="complaint_id" value="<?= (int) $c['id'] ?>">
                                <textarea name="admin_note" class="form-control form-control-sm" rows="2" placeholder="Заметка администратора"></textarea>
                                <button type="submit" name="action" value="review" class="btn btn-sm btn-success">Рассмотреть</button>
                                <button type="submit" name="action" value="dismiss" class="btn btn-sm btn-outline-secondary">Отклонить</button>
                            </form>
                        <?php else: ?>
                            <span class="small text-secondary"><?= ia_h((string) ($c['admin_note'] ?? '—')) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($complaints) === 0): ?>
                <tr><td colspan="6" class="text-secondary">Жалоб нет.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require __DIR__ . '/partials/foot.php'; ?>
