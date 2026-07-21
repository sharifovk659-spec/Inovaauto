<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_catalog.php';

use InnovaAuto\Security\Csrf;

ia_require_section('catalog');
ia_catalog_categories_post_redirect();

$pdo = ia_db();
$cats = ia_catalog_categories_list($pdo);
$user = ia_current_user();
$pageTitle = 'Категории';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<main class="container-fluid px-3 px-lg-4 py-4">
    <h1 class="h4 mb-3">Категории кузова</h1>
    <p class="text-secondary small">Примеры: Sedan, SUV, Hatchback, EV, Pickup.</p>

    <?php if ($msg = ia_flash('catalog_ok')): ?><div class="alert alert-success"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <?php if ($msg = ia_flash('catalog_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>

    <div class="card card-body mb-4">
        <h2 class="h6">Добавить категорию</h2>
        <form method="post" class="row g-2 align-items-end">
            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
            <input type="hidden" name="action" value="create">
            <div class="col-md-6">
                <label class="form-label">Название</label>
                <input type="text" name="name" class="form-control" required maxlength="120" placeholder="SUV">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">Добавить</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Дата</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cats as $c): ?>
                <tr>
                    <td><?= (int) $c['id'] ?></td>
                    <td><?= ia_h((string) $c['name']) ?></td>
                    <td class="small text-secondary"><?= ia_h((string) $c['created_at']) ?></td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= ia_h(ia_admin_url('category-edit.php?id=' . (int) $c['id'])) ?>">Изменить</a>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button type="submit" name="action" value="sort_up" class="btn btn-sm btn-outline-dark">↑</button>
                                <button type="submit" name="action" value="sort_down" class="btn btn-sm btn-outline-dark">↓</button>
                            </form>
                            <form method="post" class="d-inline" onsubmit="return confirm('Удалить категорию?');">
                                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">Удалить</button>
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
