<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_billing_payments.php';

ia_require_section('billing');

$pdo = ia_db();
$filters = ia_billing_payment_filters();
$rows = ia_billing_transactions_list($pdo, $filters);
$user = ia_current_user();
$pageTitle = 'Платежи';

$statusRu = static function (string $s): string {
    return match ($s) {
        'paid' => 'Оплачен',
        'pending' => 'Ожидает',
        'failed' => 'Ошибка',
        default => $s,
    };
};

$methodRu = static function (string $m): string {
    return match ($m) {
        'card' => 'Карта',
        'sbp' => 'СБП',
        'bank_transfer' => 'Банк',
        'cash' => 'Наличные',
        'unknown' => '—',
        default => $m,
    };
};

require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<main class="container-fluid px-3 px-lg-4 py-4">
    <h1 class="h4 mb-3">Учёт платежей</h1>

    <form class="card card-body mb-3" method="get">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Статус</label>
                <select class="form-select" name="status">
                    <option value="">Все</option>
                    <option value="paid" <?= $filters['status'] === 'paid' ? 'selected' : '' ?>>Оплачен</option>
                    <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Ожидает</option>
                    <option value="failed" <?= $filters['status'] === 'failed' ? 'selected' : '' ?>>Ошибка</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">От даты</label>
                <input type="date" class="form-control" name="date_from" value="<?= ia_h($filters['date_from']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">До даты</label>
                <input type="date" class="form-control" name="date_to" value="<?= ia_h($filters['date_to']) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Фильтр</button>
                <a class="btn btn-outline-secondary" href="<?= ia_h(ia_admin_url('payments.php')) ?>">Сброс</a>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-striped table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Пользователь</th>
                    <th>Тариф</th>
                    <th>Сумма</th>
                    <th>Статус</th>
                    <th>Способ оплаты</th>
                    <th>Дата</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int) $r['id'] ?></td>
                    <td>
                        <?= ia_h((string) $r['user_name']) ?>
                        <div class="small text-secondary"><?= ia_h((string) $r['user_email']) ?></div>
                    </td>
                    <td><?= $r['tariff_name'] !== null ? ia_h((string) $r['tariff_name']) : '—' ?></td>
                    <td><?= ia_h(number_format((float) $r['amount'], 2, '.', ' ')) ?> с.</td>
                    <td><?= ia_h($statusRu((string) $r['status'])) ?></td>
                    <td><?= ia_h($methodRu((string) $r['method'])) ?></td>
                    <td class="small"><?= ia_h((string) $r['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="small text-secondary">Показано до 500 последних записей.</p>
</main>
<?php require __DIR__ . '/partials/foot.php'; ?>
