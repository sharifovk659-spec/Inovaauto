<?php

declare(strict_types=1);

/**
 * Нормализация роли: legacy manager → support (ТЗ §18).
 */
function ia_admin_role_normalized(array $user): string
{
    $r = (string) ($user['role'] ?? 'support');

    return $r === 'manager' ? 'support' : $r;
}

/**
 * Проверка доступа к разделу админки по ТЗ §18.
 *
 * Разделы: dashboard, users, listings, catalog, billing, moderation, content, reports, settings, security, team, database
 */
function ia_admin_can(?array $user, string $section): bool
{
    if ($user === null) {
        return false;
    }
    $r = ia_admin_role_normalized($user);
    if ($r === 'super_admin') {
        return true;
    }

    return match ($section) {
        'dashboard', 'security' => true,
        'users' => $r === 'support',
        'listings' => $r === 'moderator',
        'billing' => $r === 'finance',
        'catalog', 'moderation', 'content', 'reports', 'settings', 'team', 'database' => false,
        default => false,
    };
}

function ia_admin_role_label_ru(string $role): string
{
    $r = $role === 'manager' ? 'support' : $role;

    return match ($r) {
        'super_admin' => 'Супер-администратор',
        'moderator' => 'Модератор',
        'finance' => 'Финансы',
        'support' => 'Поддержка',
        default => $role,
    };
}

/**
 * Требует входа, проверяет таймаут бездействия и права на раздел.
 */
function ia_require_section(string $section): void
{
    ia_require_login();
    $u = ia_current_user();
    if (ia_admin_can($u, $section)) {
        return;
    }
    ia_flash('admin_error', 'Недостаточно прав для этого раздела.');
    ia_redirect(ia_admin_url('dashboard.php'));
}
