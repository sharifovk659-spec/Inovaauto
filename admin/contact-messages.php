<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_contact_inbox.php';

ia_require_section('content');
ia_admin_contact_inbox_handle_post();

$pdo = ia_db();
$statusFilter = trim((string) ($_GET['status'] ?? ''));
if ($statusFilter !== '' && !in_array($statusFilter, ['new', 'reviewed', 'closed'], true)) {
    $statusFilter = '';
}
$rows = ia_admin_contact_inbox_list(
    $pdo,
    $statusFilter !== '' ? $statusFilter : null,
    100,
    0
);
$newCount = ia_admin_contact_inbox_new_count($pdo);

$user = ia_current_user();
$pageTitle = 'Обращения с сайта';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<main class="container-fluid px-3 px-lg-4 py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Обращения с сайта</h1>
            <p class="text-secondary small mb-0">Сообщения из формы «Контакты» на публичном сайте.</p>
        </div>
        <?php if ($newCount > 0): ?>
            <span class="badge text-bg-primary">Новых: <?= (int) $newCount ?></span>
        <?php endif; ?>
    </div>

    <?php if ($msg = ia_flash('contact_inbox_ok')): ?><div class="alert alert-success"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <?php if ($msg = ia_flash('contact_inbox_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>

    <form class="card card-body mb-3" method="get">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Статус</label>
                <select name="status" class="form-select">
                    <option value="">Все</option>
                    <option value="new" <?= $statusFilter === 'new' ? 'selected' : '' ?>>Новые</option>
                    <option value="reviewed" <?= $statusFilter === 'reviewed' ? 'selected' : '' ?>>Просмотренные</option>
                    <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Закрытые</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Фильтр</button>
                <a class="btn btn-outline-secondary" href="<?= ia_h(ia_admin_url('contact-messages.php')) ?>">Сброс</a>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Имя</th>
                    <th>Email</th>
                    <th>Сообщение</th>
                    <th>Статус</th>
                    <th>Дата</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <?php
                $rid = (int) ($r['id'] ?? 0);
                $st = (string) ($r['status'] ?? 'new');
                $msgText = (string) ($r['message'] ?? '');
                $msgShort = mb_strlen($msgText) > 120 ? mb_substr($msgText, 0, 117) . '…' : $msgText;
                $listBack = ia_admin_url('contact-messages.php' . ($statusFilter !== '' ? '?status=' . rawurlencode($statusFilter) : ''));
                ?>
                <tr>
                    <td><?= $rid ?></td>
                    <td><?= ia_h((string) ($r['from_name'] ?? '')) ?></td>
                    <td class="small"><?php
                        $em = trim((string) ($r['from_email'] ?? ''));
                        echo $em !== '' ? ia_h($em) : '—';
                    ?></td>
                    <td class="small" style="max-width: 320px; white-space: pre-wrap;"><?= ia_h($msgShort) ?></td>
                    <td>
                        <span class="badge <?= $st === 'new' ? 'text-bg-warning' : ($st === 'closed' ? 'text-bg-secondary' : 'text-bg-info') ?>">
                            <?= ia_h(ia_admin_contact_inbox_status_label_ru($st)) ?>
                        </span>
                    </td>
                    <td class="small text-nowrap"><?= ia_h((string) ($r['created_at'] ?? '')) ?></td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            <?php if ($st !== 'reviewed'): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                <input type="hidden" name="action" value="mark_reviewed">
                                <input type="hidden" name="id" value="<?= $rid ?>">
                                <input type="hidden" name="redirect" value="<?= ia_h($listBack) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary">Просмотрено</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($st !== 'closed'): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                <input type="hidden" name="action" value="mark_closed">
                                <input type="hidden" name="id" value="<?= $rid ?>">
                                <input type="hidden" name="redirect" value="<?= ia_h($listBack) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Закрыть</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($rows) === 0): ?>
                <tr><td colspan="7" class="text-secondary">Обращений пока нет.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require __DIR__ . '/partials/foot.php'; ?>
