<?php

declare(strict_types=1);

/**
 * @return array{status:string, date_from:string, date_to:string}
 */
function ia_billing_payment_filters(): array
{
    return [
        'status' => trim((string) ($_GET['status'] ?? '')),
        'date_from' => trim((string) ($_GET['date_from'] ?? '')),
        'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    ];
}

/**
 * @return list<array<string,mixed>>
 */
function ia_billing_transactions_list(IaPgConnection|IaPdoConnection $pdo, array $filters): array
{
    $sql = 'SELECT t.id, t.amount, t.status, t.method, t.created_at, t.paid_at, t.note,
            u.name AS user_name, u.email AS user_email,
            tr.name AS tariff_name, tr.code AS tariff_code
            FROM billing_transactions t
            INNER JOIN platform_users u ON u.id = t.platform_user_id
            LEFT JOIN billing_tariffs tr ON tr.id = t.tariff_id
            WHERE 1=1';
    $params = [];
    if ($filters['status'] !== '') {
        $sql .= ' AND t.status = :status';
        $params['status'] = $filters['status'];
    }
    if ($filters['date_from'] !== '') {
        $sql .= ' AND DATE(t.created_at) >= :df';
        $params['df'] = $filters['date_from'];
    }
    if ($filters['date_to'] !== '') {
        $sql .= ' AND DATE(t.created_at) <= :dt';
        $params['dt'] = $filters['date_to'];
    }
    $sql .= ' ORDER BY t.id DESC LIMIT 500';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll() ?: [];
}
