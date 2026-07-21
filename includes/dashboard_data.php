<?php

declare(strict_types=1);

/**
 * Агрегаты и ряды для главной страницы админки (дашборд).
 *
 * @return array{
 *   users_total:int,
 *   users_active:int,
 *   users_blocked:int,
 *   listings_active:int,
 *   listings_pending:int,
 *   listings_rejected:int,
 *   cars_total:int,
 *   revenue_site:float,
 *   revenue_vip:float
 * }
 */
function ia_dashboard_kpis(): array
{
    $pdo = ia_db();

    try {
        $usersTotal = (int) $pdo->query('SELECT COUNT(*) FROM platform_users')->fetchColumn();
        $usersActive = (int) $pdo->query("SELECT COUNT(*) FROM platform_users WHERE status = 'active'")->fetchColumn();
        $usersBlocked = (int) $pdo->query("SELECT COUNT(*) FROM platform_users WHERE status = 'blocked'")->fetchColumn();

        $la = (int) $pdo->query("SELECT COUNT(*) FROM ad_listings WHERE status = 'approved'")->fetchColumn();
        $lp = (int) $pdo->query("SELECT COUNT(*) FROM ad_listings WHERE status = 'pending'")->fetchColumn();
        $lr = (int) $pdo->query("SELECT COUNT(*) FROM ad_listings WHERE status = 'rejected'")->fetchColumn();
        $carsTotal = (int) $pdo->query('SELECT COUNT(*) FROM ad_listings')->fetchColumn();

        $revSite = (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM site_payments WHERE kind = 'general'")->fetchColumn();
        $revVip = (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM site_payments WHERE kind = 'vip'")->fetchColumn();
    } catch (\PDOException $e) {
        if (!empty(ia_db_connection_info()['is_supabase'])) {
            error_log('ia_dashboard_kpis Supabase: ' . $e->getMessage());
        }

        return [
            'users_total' => 0,
            'users_active' => 0,
            'users_blocked' => 0,
            'listings_active' => 0,
            'listings_pending' => 0,
            'listings_rejected' => 0,
            'cars_total' => 0,
            'revenue_site' => 0.0,
            'revenue_vip' => 0.0,
            '_db_error' => $e->getMessage(),
        ];
    }

    return [
        'users_total' => $usersTotal,
        'users_active' => $usersActive,
        'users_blocked' => $usersBlocked,
        'listings_active' => $la,
        'listings_pending' => $lp,
        'listings_rejected' => $lr,
        'cars_total' => $carsTotal,
        'revenue_site' => $revSite,
        'revenue_vip' => $revVip,
    ];
}

/**
 * @return array{labels: list<string>, values: list<int|float>}
 */
function ia_dashboard_series_listings_by_day(int $days): array
{
    return ia_dashboard_daily_aggregate(
        'SELECT DATE(created_at) AS d, COUNT(*) AS v FROM ad_listings WHERE created_at >= :from GROUP BY DATE(created_at)',
        $days,
        true
    );
}

/**
 * @return array{labels: list<string>, values: list<int|float>}
 */
function ia_dashboard_series_users_by_day(int $days): array
{
    return ia_dashboard_daily_aggregate(
        'SELECT DATE(created_at) AS d, COUNT(*) AS v FROM platform_users WHERE created_at >= :from GROUP BY DATE(created_at)',
        $days,
        true
    );
}

/**
 * Суммарный доход по дням (общий + VIP).
 *
 * @return array{labels: list<string>, values: list<int|float>}
 */
function ia_dashboard_series_revenue_by_day(int $days): array
{
    return ia_dashboard_daily_aggregate(
        'SELECT DATE(created_at) AS d, COALESCE(SUM(amount), 0) AS v FROM site_payments WHERE created_at >= :from GROUP BY DATE(created_at)',
        $days,
        false
    );
}

/**
 * @param callable(string):list{array{d:string,v:float|int|string}} $fetcher optional override — not used; SQL string instead
 */
function ia_dashboard_daily_aggregate(string $sql, int $days, bool $intValues): array
{
    $days = max(1, min(90, $days));
    $pdo = ia_db();
    $from = (new DateTimeImmutable('today'))->modify(sprintf('-%d days', $days - 1))->format('Y-m-d 00:00:00');

    try {
        $st = $pdo->prepare($sql);
        $st->execute(['from' => $from]);
        $rows = $st->fetchAll();
    } catch (\PDOException) {
        $rows = [];
    }

    $map = [];
    foreach ($rows as $row) {
        $d = (string) ($row['d'] ?? '');
        if ($d !== '') {
            $map[$d] = $intValues ? (int) ($row['v'] ?? 0) : (float) ($row['v'] ?? 0);
        }
    }

    $labels = [];
    $values = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $day = (new DateTimeImmutable('today'))->modify(sprintf('-%d days', $i));
        $key = $day->format('Y-m-d');
        $labels[] = $day->format('d.m');
        $raw = $map[$key] ?? ($intValues ? 0 : 0.0);
        $values[] = $intValues ? (int) $raw : round((float) $raw, 2);
    }

    return ['labels' => $labels, 'values' => $values];
}
