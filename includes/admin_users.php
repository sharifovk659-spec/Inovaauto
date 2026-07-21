<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

function ia_admin_users_filters(): array
{
    return [
        'date_from' => ia_get_date('date_from'),
        'date_to' => ia_get_date('date_to'),
        'status' => ia_input_enum($_GET['status'] ?? '', ['active', 'blocked']),
        'account_type' => ia_input_enum($_GET['account_type'] ?? '', ['private', 'dealer']),
        'q' => ia_get_search('q', 120),
    ];
}

function ia_admin_users_handle_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('users_error', 'Сессия устарела. Повторите действие.');
        ia_redirect(ia_admin_url('users.php'));
    }
    $id = ia_post_int('user_id');
    $action = (string) ($_POST['action'] ?? '');
    if ($id <= 0) {
        ia_flash('users_error', 'Некорректный пользователь.');
        ia_redirect(ia_admin_url('users.php'));
    }

    $pdo = ia_db();
    if ($action === 'block') {
        $st = $pdo->prepare("UPDATE platform_users SET status='blocked' WHERE id=?");
        $st->execute([$id]);
        ia_flash('users_ok', 'Пользователь заблокирован.');
    } elseif ($action === 'activate') {
        $st = $pdo->prepare("UPDATE platform_users SET status='active' WHERE id=?");
        $st->execute([$id]);
        ia_flash('users_ok', 'Пользователь активирован.');
    } elseif ($action === 'delete') {
        $st = $pdo->prepare('DELETE FROM platform_users WHERE id=?');
        $st->execute([$id]);
        ia_flash('users_ok', 'Пользователь удалён.');
    }
    ia_redirect(ia_admin_url('users.php'));
}

function ia_admin_users_list(array $filters): array
{
    $sql = 'SELECT id,name,phone,email,account_type,status,created_at FROM platform_users WHERE 1=1';
    $params = [];
    if ($filters['status'] !== '') {
        $sql .= ' AND status = :status';
        $params['status'] = $filters['status'];
    }
    if ($filters['account_type'] !== '') {
        $sql .= ' AND account_type = :account_type';
        $params['account_type'] = $filters['account_type'];
    }
    if ($filters['date_from'] !== '') {
        $sql .= ' AND DATE(created_at) >= :df';
        $params['df'] = $filters['date_from'];
    }
    if ($filters['date_to'] !== '') {
        $sql .= ' AND DATE(created_at) <= :dt';
        $params['dt'] = $filters['date_to'];
    }
    if ($filters['q'] !== '') {
        $likeParts = ia_db_like_or(['name', 'email', 'phone'], 'uq', '%' . $filters['q'] . '%', $params);
        $sql .= ' AND ' . $likeParts;
    }
    $sql .= ' ORDER BY id DESC';
    $st = ia_db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll() ?: [];
}

function ia_admin_user_by_id(int $id): ?array
{
    $st = ia_db()->prepare('SELECT * FROM platform_users WHERE id=? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function ia_admin_user_account_type_ru(string $type): string
{
    return match ($type) {
        'dealer' => 'Дилер',
        'private' => 'Частный',
        default => $type !== '' ? $type : '—',
    };
}

function ia_admin_user_status_ru(string $status): string
{
    return match ($status) {
        'active' => 'Активный',
        'blocked' => 'Заблокирован',
        default => $status !== '' ? $status : '—',
    };
}

function ia_admin_user_account_type_badge(string $type): string
{
    return match ($type) {
        'dealer' => 'text-bg-primary',
        'private' => 'text-bg-secondary',
        default => 'text-bg-light',
    };
}

function ia_admin_user_status_badge(string $status): string
{
    return match ($status) {
        'active' => 'text-bg-success',
        'blocked' => 'text-bg-danger',
        default => 'text-bg-secondary',
    };
}

function ia_admin_user_update(int $id, array $data): bool
{
    $st = ia_db()->prepare(
        'UPDATE platform_users SET name=:name,phone=:phone,email=:email,account_type=:account_type,status=:status WHERE id=:id'
    );
    return $st->execute([
        'id' => $id,
        'name' => trim((string) ($data['name'] ?? '')),
        'phone' => trim((string) ($data['phone'] ?? '')),
        'email' => trim((string) ($data['email'] ?? '')),
        'account_type' => trim((string) ($data['account_type'] ?? 'private')),
        'status' => trim((string) ($data['status'] ?? 'active')),
    ]);
}
