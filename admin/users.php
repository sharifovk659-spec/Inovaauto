<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_users.php';

ia_require_section('users');
ia_admin_users_handle_post();

$filters = ia_admin_users_filters();
$users = ia_admin_users_list($filters);
$user = ia_current_user();
$pageTitle = 'Пользователи';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<main class="container-fluid px-3 px-lg-4 py-4">
    <h1 class="h4 mb-3">Управление пользователями</h1>

    <?php if ($msg = ia_flash('users_ok')): ?><div class="alert alert-success"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <?php if ($msg = ia_flash('users_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>

    <form class="card card-body mb-3" method="get">
        <div class="row g-2">
            <div class="col-12 col-md-6">
                <label class="form-label">Поиск (имя, email, телефон)</label>
                <input type="search" class="form-control" name="q" value="<?= ia_h($filters['q'] ?? '') ?>" placeholder="Совпадение с…" autocomplete="off">
            </div>
            <div class="col-md-3"><label class="form-label">От даты</label><input type="date" class="form-control" name="date_from" value="<?= ia_h($filters['date_from']) ?>"></div>
            <div class="col-md-3"><label class="form-label">До даты</label><input type="date" class="form-control" name="date_to" value="<?= ia_h($filters['date_to']) ?>"></div>
            <div class="col-md-3">
                <label class="form-label">Статус</label>
                <select class="form-select" name="status">
                    <option value="">Все</option>
                    <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Активный</option>
                    <option value="blocked" <?= $filters['status'] === 'blocked' ? 'selected' : '' ?>>Заблокирован</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Тип аккаунта</label>
                <select class="form-select" name="account_type">
                    <option value="">Все</option>
                    <option value="private" <?= $filters['account_type'] === 'private' ? 'selected' : '' ?>>Частный</option>
                    <option value="dealer" <?= $filters['account_type'] === 'dealer' ? 'selected' : '' ?>>Дилер</option>
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Фильтр</button>
                <a class="btn btn-outline-secondary" href="<?= ia_h(ia_admin_url('users.php')) ?>">Сброс</a>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th><th>Имя</th><th>Телефон</th><th>Email</th><th>Тип аккаунта</th><th>Статус</th><th>Дата</th><th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $row): ?>
                <tr>
                    <td><?= (int) $row['id'] ?></td>
                    <td><?= ia_h((string) $row['name']) ?></td>
                    <td><?= ia_h((string) $row['phone']) ?></td>
                    <td><?= ia_h((string) $row['email']) ?></td>
                    <td><span class="badge <?= ia_h(ia_admin_user_account_type_badge((string) $row['account_type'])) ?>"><?= ia_h(ia_admin_user_account_type_ru((string) $row['account_type'])) ?></span></td>
                    <td><span class="badge <?= ia_h(ia_admin_user_status_badge((string) $row['status'])) ?>"><?= ia_h(ia_admin_user_status_ru((string) $row['status'])) ?></span></td>
                    <td><?= ia_h((string) $row['created_at']) ?></td>
                    <td class="ia-admin-actions-cell">
                        <div class="ia-admin-row-actions" role="group" aria-label="Действия">
                            <a class="ia-admin-action ia-admin-action--view" href="<?= ia_h(ia_admin_url('user-view.php?id=' . (int) $row['id'])) ?>" title="Детали" aria-label="Детали">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </a>
                            <a class="ia-admin-action ia-admin-action--edit" href="<?= ia_h(ia_admin_url('user-edit.php?id=' . (int) $row['id'])) ?>" title="Редактировать" aria-label="Редактировать">
                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                            </a>
                            <form method="post" class="ia-admin-row-actions-form">
                                <input type="hidden" name="_csrf" value="<?= ia_h(\InnovaAuto\Security\Csrf::token()) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                <?php if ((string) $row['status'] === 'blocked'): ?>
                                    <button class="ia-admin-action ia-admin-action--success" name="action" value="activate" type="submit" title="Активировать" aria-label="Активировать">
                                        <i class="bi bi-check-circle" aria-hidden="true"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="ia-admin-action ia-admin-action--warn" name="action" value="block" type="submit" title="Заблокировать" aria-label="Заблокировать">
                                        <i class="bi bi-slash-circle" aria-hidden="true"></i>
                                    </button>
                                <?php endif; ?>
                                <button class="ia-admin-action ia-admin-action--danger" name="action" value="delete" type="submit" title="Удалить" aria-label="Удалить" onclick="return confirm('Удалить пользователя?')">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require __DIR__ . '/partials/foot.php'; ?>
