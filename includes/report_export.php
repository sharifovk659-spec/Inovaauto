<?php

declare(strict_types=1);

/**
 * @param list<string> $headers
 * @param iterable<int, list<string|int|float>> $rows
 */
function ia_report_send_csv(string $filename, array $headers, iterable $rows): never
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    if ($out === false) {
        exit;
    }
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) {
        /** @var list<string|int|float> $row */
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

/**
 * @return iterable<int, list<string|int|float>>
 */
function ia_report_iter_users(IaPgConnection|IaPdoConnection $pdo): iterable
{
    $q = $pdo->query(
        'SELECT id, name, phone, email, account_type, status, created_at FROM platform_users ORDER BY id ASC'
    );
    while ($row = $q->fetch()) {
        yield [
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['phone'],
            (string) $row['email'],
            (string) $row['account_type'],
            (string) $row['status'],
            (string) $row['created_at'],
        ];
    }
}

/**
 * @return iterable<int, list<string|int|float>>
 */
function ia_report_iter_listings(IaPgConnection|IaPdoConnection $pdo): iterable
{
    $sql = 'SELECT l.id, l.user_id, u.email AS user_email, l.brand, l.model, l.price, l.status, l.is_vip, l.created_at
            FROM ad_listings l
            INNER JOIN platform_users u ON u.id = l.user_id
            ORDER BY l.id ASC';
    $q = $pdo->query($sql);
    while ($row = $q->fetch()) {
        yield [
            (int) $row['id'],
            (int) $row['user_id'],
            (string) $row['user_email'],
            (string) $row['brand'],
            (string) $row['model'],
            (float) $row['price'],
            (string) $row['status'],
            (int) $row['is_vip'],
            (string) $row['created_at'],
        ];
    }
}

/**
 * @return iterable<int, list<string|int|float>>
 */
function ia_report_iter_revenue(IaPgConnection|IaPdoConnection $pdo): iterable
{
    $q = $pdo->query('SELECT id, amount, kind, note, created_at FROM site_payments ORDER BY id ASC');
    while ($row = $q->fetch()) {
        yield [
            (int) $row['id'],
            (float) $row['amount'],
            (string) $row['kind'],
            (string) ($row['note'] ?? ''),
            (string) $row['created_at'],
        ];
    }
}

/**
 * @return iterable<int, list<string|int|float>>
 */
function ia_report_iter_vip(IaPgConnection|IaPdoConnection $pdo): iterable
{
    $q = $pdo->query("SELECT id, amount, kind, note, created_at FROM site_payments WHERE kind = 'vip' ORDER BY id ASC");
    while ($row = $q->fetch()) {
        yield [
            (int) $row['id'],
            (float) $row['amount'],
            (string) $row['kind'],
            (string) ($row['note'] ?? ''),
            (string) $row['created_at'],
        ];
    }
}

function ia_report_run_export(string $type, string $format): void
{
    $pdo = ia_db();

    if ($format === 'csv') {
        switch ($type) {
            case 'users':
                ia_report_send_csv(
                    'report_users.csv',
                    ['ID', 'Имя', 'Телефон', 'Email', 'Тип', 'Статус', 'Создан'],
                    ia_report_iter_users($pdo)
                );
            case 'listings':
                ia_report_send_csv(
                    'report_listings.csv',
                    ['ID', 'User ID', 'Email пользователя', 'Бренд', 'Модель', 'Цена', 'Статус', 'VIP', 'Создано'],
                    ia_report_iter_listings($pdo)
                );
            case 'revenue':
                ia_report_send_csv(
                    'report_revenue.csv',
                    ['ID', 'Сумма', 'Тип', 'Примечание', 'Дата'],
                    ia_report_iter_revenue($pdo)
                );
            case 'vip':
                ia_report_send_csv(
                    'report_vip.csv',
                    ['ID', 'Сумма', 'Тип', 'Примечание', 'Дата'],
                    ia_report_iter_vip($pdo)
                );
            default:
                ia_redirect(ia_admin_url('reports.php'));
        }
    }

    if ($format === 'html') {
        $titles = [
            'users' => 'Пользователи',
            'listings' => 'Объявления',
            'revenue' => 'Доход (платежи сайта)',
            'vip' => 'VIP-продажи',
        ];
        if (!isset($titles[$type])) {
            ia_redirect(ia_admin_url('reports.php'));
        }
        header('Content-Type: text/html; charset=UTF-8');
        $title = $titles[$type];
        echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>' . ia_h($title) . '</title>';
        echo '<style>body{font-family:system-ui,sans-serif;padding:1rem}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:6px;font-size:12px}th{background:#f0f0f0}@media print{.no-print{display:none}}</style></head><body>';
        echo '<p class="no-print"><a href="javascript:window.print()">Печать / PDF</a> · <a href="' . ia_h(ia_admin_url('reports.php')) . '">Назад</a></p>';
        echo '<h1>' . ia_h($title) . '</h1><table><thead><tr>';

        if ($type === 'users') {
            echo '<th>ID</th><th>Имя</th><th>Телефон</th><th>Email</th><th>Тип</th><th>Статус</th><th>Создан</th></tr></thead><tbody>';
            foreach (ia_report_iter_users($pdo) as $r) {
                echo '<tr><td>' . ia_h((string) $r[0]) . '</td><td>' . ia_h((string) $r[1]) . '</td><td>' . ia_h((string) $r[2]);
                echo '</td><td>' . ia_h((string) $r[3]) . '</td><td>' . ia_h((string) $r[4]) . '</td><td>' . ia_h((string) $r[5]);
                echo '</td><td>' . ia_h((string) $r[6]) . '</td></tr>';
            }
        } elseif ($type === 'listings') {
            echo '<th>ID</th><th>User</th><th>Email</th><th>Бренд</th><th>Модель</th><th>Цена</th><th>Статус</th><th>VIP</th><th>Создано</th></tr></thead><tbody>';
            foreach (ia_report_iter_listings($pdo) as $r) {
                echo '<tr><td>' . ia_h((string) $r[0]) . '</td><td>' . ia_h((string) $r[1]) . '</td><td>' . ia_h((string) $r[2]);
                echo '</td><td>' . ia_h((string) $r[3]) . '</td><td>' . ia_h((string) $r[4]) . '</td><td>' . ia_h((string) $r[5]);
                echo '</td><td>' . ia_h((string) $r[6]) . '</td><td>' . ia_h((string) $r[7]) . '</td><td>' . ia_h((string) $r[8]) . '</td></tr>';
            }
        } elseif ($type === 'revenue' || $type === 'vip') {
            echo '<th>ID</th><th>Сумма</th><th>Тип</th><th>Примечание</th><th>Дата</th></tr></thead><tbody>';
            $iter = $type === 'revenue' ? ia_report_iter_revenue($pdo) : ia_report_iter_vip($pdo);
            foreach ($iter as $r) {
                echo '<tr><td>' . ia_h((string) $r[0]) . '</td><td>' . ia_h((string) $r[1]) . '</td><td>' . ia_h((string) $r[2]);
                echo '</td><td>' . ia_h((string) $r[3]) . '</td><td>' . ia_h((string) $r[4]) . '</td></tr>';
            }
        }

        echo '</tbody></table></body></html>';
        exit;
    }

    ia_redirect(ia_admin_url('reports.php'));
}
