<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

/**
 * @return list<array<string,mixed>>
 */
function ia_admin_team_list(IaPgConnection|IaPdoConnection $pdo): array
{
    $sql = 'SELECT id, email, username, role, is_active, last_login_at, created_at FROM admin_users ORDER BY id ASC';

    return $pdo->query($sql)->fetchAll() ?: [];
}

function ia_admin_team_handle_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('team_error', 'Сессия устарела.');
        ia_redirect(ia_admin_url('team.php'));
    }
    $allowed = ['super_admin', 'moderator', 'finance', 'support', 'manager'];
    $action = (string) ($_POST['action'] ?? '');
    $pdo = ia_db();

    if ($action === 'set_role') {
        $id = (int) ($_POST['admin_id'] ?? 0);
        $role = (string) ($_POST['role'] ?? '');
        if ($id <= 0 || !in_array($role, $allowed, true)) {
            ia_flash('team_error', 'Некорректные данные.');
            ia_redirect(ia_admin_url('team.php'));
        }
        $me = (int) (ia_current_user()['id'] ?? 0);
        if ($id === $me) {
            $st = $pdo->prepare('SELECT role FROM admin_users WHERE id = ?');
            $st->execute([$me]);
            $was = (string) $st->fetchColumn();
            if ($was === 'super_admin' && $role !== 'super_admin') {
                $cnt = (int) $pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'super_admin'")->fetchColumn();
                if ($cnt <= 1) {
                    ia_flash('team_error', 'Нельзя снять последнего супер-администратора.');
                    ia_redirect(ia_admin_url('team.php'));
                }
            }
        }
        $pdo->prepare('UPDATE admin_users SET role = ? WHERE id = ?')->execute([$role, $id]);
        ia_flash('team_ok', 'Роль обновлена.');
        ia_redirect(ia_admin_url('team.php'));
    }

    ia_redirect(ia_admin_url('team.php'));
}
