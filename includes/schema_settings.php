<?php

declare(strict_types=1);

function ia_ensure_site_settings_schema(IaPgConnection|IaPdoConnection $pdo): void
{
    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(64) NOT NULL,
    setting_value LONGTEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );
}

/**
 * Расширяет ENUM роли администратора под ТЗ §18 (moderator, finance, support).
 */
function ia_migrate_admin_roles_enum(IaPgConnection|IaPdoConnection $pdo): void
{
    try {
        $pdo->exec(
            <<<'SQL'
ALTER TABLE admin_users
MODIFY COLUMN role ENUM('super_admin','moderator','finance','support','manager')
NOT NULL DEFAULT 'manager'
SQL
        );
    } catch (\PDOException) {
        // колонка уже в нужном виде или нет прав
    }
}

/**
 * Начальные значения настроек сайта (если таблица пуста).
 */
function ia_seed_default_site_settings(IaPgConnection|IaPdoConnection $pdo): void
{
    try {
        $c = (int) $pdo->query('SELECT COUNT(*) FROM site_settings')->fetchColumn();
    } catch (\PDOException) {
        return;
    }
    if ($c > 0) {
        return;
    }
    $rows = [
        ['site_name', 'InnovaAuto'],
        ['contact_phone', ''],
        ['contact_email', ''],
        ['contact_address', ''],
        ['meta_title', 'InnovaAuto — продажа автомобилей'],
        ['meta_description', ''],
        ['social_vk', ''],
        ['social_telegram', ''],
        ['social_instagram', ''],
        ['social_facebook', ''],
        ['api_maps_key', ''],
        ['api_sms_gateway_key', ''],
        ['api_push_server_key', ''],
        ['logo_path', ''],
    ];
    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)');
        foreach ($rows as [$k, $v]) {
            $ins->execute([$k, $v]);
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
