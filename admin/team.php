<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_team.php';

ia_require_section('team');
ia_admin_team_handle_post();

$pdo = ia_db();
$team = ia_admin_team_list($pdo);
$user = ia_current_user();
$pageTitle = 'Команда администраторов';

$roles = [
    'super_admin' => ia_admin_role_label_ru('super_admin'),
    'moderator' => ia_admin_role_label_ru('moderator'),
    'finance' => ia_admin_role_label_ru('finance'),
    'support' => ia_admin_role_label_ru('support'),
    'manager' => 'Менеджер (legacy → поддержка)',
];

require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<main class="container-fluid px-3 px-lg-4 py-4">
    <h1 class="h4 mb-3">Роли администраторов (ТЗ §18)</h1>
    <p class="small text-secondary mb-4">
        <strong>Супер-администратор</strong> — полный доступ.<br>
        <strong>Модератор</strong> — только объявления.<br>
        <strong>Финансы</strong> — тарифы и платежи.<br>
        <strong>Поддержка</strong> — пользователи платформы.<br>
        Роль <code>manager</code> трактуется как поддержка (совместимость со старыми БД).
    </p>
    <?php if ($msg = ia_flash('team_ok')): ?><div class="alert alert-success"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <?php if ($msg = ia_flash('team_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>

    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Логин</th>
                    <th>Email</th>
                    <th>Роль</th>
                    <th>Активен</th>
                    <th>Последний вход</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($team as $row): ?>
                <tr>
                    <td><?= (int) $row['id'] ?></td>
                    <td><?= ia_h((string) ($row['username'] ?? '')) ?></td>
                    <td><?= ia_h((string) ($row['email'] ?? '')) ?></td>
                    <td style="min-width:220px">
                        <form method="post" class="d-flex gap-2 align-items-center flex-wrap">
                            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                            <input type="hidden" name="action" value="set_role">
                            <input type="hidden" name="admin_id" value="<?= (int) $row['id'] ?>">
                            <select name="role" class="form-select form-select-sm" style="max-width:14rem">
                                <?php foreach ($roles as $rk => $rl): ?>
                                    <option value="<?= ia_h($rk) ?>" <?= ((string) ($row['role'] ?? '') === $rk) ? 'selected' : '' ?>><?= ia_h($rl) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary">OK</button>
                        </form>
                    </td>
                    <td><?= (int) ($row['is_active'] ?? 0) ? 'да' : 'нет' ?></td>
                    <td class="small"><?= ia_h((string) ($row['last_login_at'] ?? '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require __DIR__ . '/partials/foot.php'; ?>
