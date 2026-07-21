<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_moderation.php';

ia_require_section('moderation');
ia_moderation_threads_post();

$id = ia_request_int('id');
$pdo = ia_db();
$thread = $id > 0 ? ia_moderation_thread_by_id($pdo, $id) : null;
if ($thread === null) {
    ia_flash('mod_error', 'Диалог не найден.');
    ia_redirect(ia_admin_url('chat-threads.php'));
}

$messages = ia_moderation_messages_for_thread($pdo, $id);

$st = $pdo->prepare(
    'SELECT id, name, email FROM platform_users WHERE id IN (?,?) ORDER BY id ASC'
);
$st->execute([(int) $thread['user_low_id'], (int) $thread['user_high_id']]);
$participants = $st->fetchAll();
$user = ia_current_user();
$pageTitle = 'Чат #' . $id;
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<main class="container-fluid px-3 px-lg-4 py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= ia_h(ia_admin_url('chat-threads.php')) ?>">Чаты</a></li>
            <li class="breadcrumb-item active">#<?= $id ?></li>
        </ol>
    </nav>

    <h1 class="h4 mb-3">Диалог #<?= $id ?></h1>
    <?php if ($msg = ia_flash('mod_ok')): ?><div class="alert alert-success"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <?php if ($msg = ia_flash('mod_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 small">
                <div class="col-md-4"><strong>Объявление:</strong> <?= (int) $thread['listing_id'] === 0 ? '—' : (string) (int) $thread['listing_id'] ?></div>
                <div class="col-md-4"><strong>Блокировка чата:</strong> <?= (int) $thread['is_blocked'] ? 'да' : 'нет' ?></div>
            </div>
            <?php foreach ($participants as $p): ?>
                <div class="mt-2 small"><?= ia_h((string) $p['name']) ?> — <?= ia_h((string) $p['email']) ?> (id <?= (int) $p['id'] ?>)</div>
            <?php endforeach; ?>
            <form method="post" class="mt-3 d-inline">
                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                <input type="hidden" name="action" value="toggle_block">
                <input type="hidden" name="thread_id" value="<?= (int) $id ?>">
                <button type="submit" class="btn btn-warning btn-sm">
                    <?= (int) $thread['is_blocked'] ? 'Разблокировать чат' : 'Заблокировать чат' ?>
                </button>
            </form>
        </div>
    </div>

    <h2 class="h6 text-uppercase text-secondary mb-2">Сообщения</h2>
    <div class="list-group mb-4">
        <?php foreach ($messages as $m): ?>
            <div class="list-group-item">
                <div class="d-flex justify-content-between">
                    <strong><?= ia_h((string) ($m['sender_name'] ?? '')) ?></strong>
                    <span class="small text-secondary"><?= ia_h((string) ($m['created_at'] ?? '')) ?></span>
                </div>
                <div class="mt-1"><?= nl2br(ia_h((string) ($m['body'] ?? ''))) ?></div>
            </div>
        <?php endforeach; ?>
        <?php if (count($messages) === 0): ?>
            <div class="list-group-item text-secondary">Нет сообщений.</div>
        <?php endif; ?>
    </div>
</main>
<?php require __DIR__ . '/partials/foot.php'; ?>
