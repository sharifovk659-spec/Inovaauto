<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_tariffs.php';

use InnovaAuto\Security\Csrf;

ia_require_section('billing');
ia_billing_tariffs_post_redirect();

$pdo = ia_db();
$tariffs = ia_billing_tariffs_list($pdo);
$user = ia_current_user();
$pageTitle = 'Тарифы';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<main class="container-fluid px-3 px-lg-4 py-4">
    <h1 class="h4 mb-3">VIP / управление тарифами</h1>
    <p class="text-secondary small">Для каждого тарифа: цена, срок (дней), преимущества.</p>

    <?php if ($msg = ia_flash('billing_ok')): ?><div class="alert alert-success"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <?php if ($msg = ia_flash('billing_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>

    <div class="card card-body mb-4">
        <h2 class="h6">Добавить тариф</h2>
        <form method="post" class="row g-2">
            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
            <input type="hidden" name="action" value="create">
            <div class="col-md-2">
                <label class="form-label">Код</label>
                <input type="text" name="code" class="form-control" placeholder="premium" maxlength="32" required>
                <div class="form-text small">латиница, например standard, vip</div>
            </div>
            <div class="col-md-2">
                <label class="form-label">Название</label>
                <input type="text" name="name" class="form-control" required maxlength="160">
            </div>
            <div class="col-md-2">
                <label class="form-label">Нарх (с.)</label>
                <input type="text" name="price" class="form-control" value="0" inputmode="decimal">
            </div>
            <div class="col-md-2">
                <label class="form-label">Срок (дней)</label>
                <input type="number" name="duration_days" class="form-control" min="0" placeholder="пусто = без лимита">
            </div>
            <div class="col-md-3">
                <label class="form-label">Преимущества</label>
                <input type="text" name="benefits" class="form-control" maxlength="65535" placeholder="Кратко о плюсах">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Add</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Код</th>
                    <th>Название</th>
                    <th>Цена</th>
                    <th>Срок (дн.)</th>
                    <th>Преимущества</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tariffs as $t): ?>
                <tr>
                    <td><?= (int) $t['id'] ?></td>
                    <td><code><?= ia_h((string) $t['code']) ?></code></td>
                    <td><?= ia_h((string) $t['name']) ?></td>
                    <td><?= ia_h(number_format((float) $t['price'], 2, '.', ' ')) ?> с.</td>
                    <td><?= $t['duration_days'] === null ? '—' : (int) $t['duration_days'] ?></td>
                    <td class="small"><?php
                        $ben = (string) ($t['benefits'] ?? '');
                        echo ia_h(strlen($ben) > 100 ? substr($ben, 0, 97) . '...' : $ben);
                    ?></td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            <a class="btn btn-sm btn-outline-primary" href="<?= ia_h(ia_admin_url('tariff-edit.php?id=' . (int) $t['id'])) ?>">Edit</a>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                <button type="submit" name="action" value="sort_up" class="btn btn-sm btn-outline-secondary">↑</button>
                                <button type="submit" name="action" value="sort_down" class="btn btn-sm btn-outline-secondary">↓</button>
                            </form>
                            <form method="post" class="d-inline" onsubmit="return confirm('Удалить тариф? Связанные платежи останутся без тарифа.');">
                                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">Delete</button>
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
