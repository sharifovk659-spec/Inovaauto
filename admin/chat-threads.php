<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_moderation.php';

ia_require_section('moderation');

$pdo = ia_db();
$threads = ia_moderation_threads_list($pdo);
$user = ia_current_user();
$pageTitle = 'Чаты';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<main class="container-fluid px-3 px-lg-4 py-4">
    <h1 class="h4 mb-3">Модерация: переписки</h1>
    <?php if ($msg = ia_flash('mod_ok')): ?><div class="alert alert-success"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <?php if ($msg = ia_flash('mod_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>

    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Объявление</th>
                    <th>Участник 1</th>
                    <th>Участник 2</th>
                    <th>Сообщений</th>
                    <th>Жалоб</th>
                    <th>Блок</th>
                    <th>Обновлён</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($threads as $t): ?>
                <tr>
                    <td><?= (int) $t['id'] ?></td>
                    <td><?= (int) $t['listing_id'] === 0 ? '—' : (string) (int) $t['listing_id'] ?></td>
                    <td><?= ia_h((string) ($t['n1'] ?? '')) ?><br><span class="small text-secondary"><?= ia_h((string) ($t['e1'] ?? '')) ?></span></td>
                    <td><?= ia_h((string) ($t['n2'] ?? '')) ?><br><span class="small text-secondary"><?= ia_h((string) ($t['e2'] ?? '')) ?></span></td>
                    <td><?= (int) ($t['msg_count'] ?? 0) ?></td>
                    <td><?= (int) ($t['open_reports'] ?? 0) ?><?= (int) ($t['open_reports'] ?? 0) > 0 ? ' <span class="badge text-bg-warning">жалобы</span>' : '' ?></td>
                    <td><?= (int) ($t['is_blocked'] ?? 0) ? '<span class="text-danger">да</span>' : 'нет' ?></td>
                    <td class="small"><?= ia_h((string) ($t['updated_at'] ?? '')) ?></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="<?= ia_h(ia_admin_url('chat-thread.php?id=' . (int) $t['id'])) ?>">Открыть</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($threads) === 0): ?>
                <tr><td colspan="9" class="text-secondary">Нет диалогов.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require __DIR__ . '/partials/foot.php'; ?>
