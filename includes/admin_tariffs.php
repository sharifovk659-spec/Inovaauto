<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

/**
 * @return list<array<string,mixed>>
 */
function ia_billing_tariffs_list(IaPgConnection|IaPdoConnection $pdo): array
{
    return $pdo->query('SELECT * FROM billing_tariffs ORDER BY sort_order ASC, id ASC')->fetchAll() ?: [];
}

function ia_billing_tariff_by_id(IaPgConnection|IaPdoConnection $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM billing_tariffs WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

function ia_billing_next_tariff_sort(IaPgConnection|IaPdoConnection $pdo): int
{
    return (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM billing_tariffs')->fetchColumn() + 10;
}

function ia_billing_swap_tariff_order(IaPgConnection|IaPdoConnection $pdo, int $id, string $dir): void
{
    $rows = $pdo->query('SELECT id, sort_order FROM billing_tariffs ORDER BY sort_order ASC, id ASC')->fetchAll();
    ia_billing_swap_rows($pdo, $rows, $id, $dir, 'billing_tariffs');
}

/**
 * @param list<array<string,mixed>> $rows
 */
function ia_billing_swap_rows(IaPgConnection|IaPdoConnection $pdo, array $rows, int $id, string $dir, string $table): void
{
    if (!in_array($table, ['billing_tariffs'], true)) {
        return;
    }
    $idx = null;
    foreach ($rows as $i => $r) {
        if ((int) $r['id'] === $id) {
            $idx = $i;
            break;
        }
    }
    if ($idx === null) {
        return;
    }
    $j = $dir === 'up' ? $idx - 1 : $idx + 1;
    if ($j < 0 || $j >= count($rows)) {
        return;
    }
    $a = $rows[$idx];
    $b = $rows[$j];
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("UPDATE {$table} SET sort_order = ? WHERE id = ?");
        $st->execute([(int) $b['sort_order'], (int) $a['id']]);
        $st->execute([(int) $a['sort_order'], (int) $b['id']]);
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function ia_billing_tariffs_post_redirect(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('billing_error', 'Сессия устарела.');
        ia_redirect(ia_admin_url('tariffs.php'));
    }
    $pdo = ia_db();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $code = strtolower(trim((string) ($_POST['code'] ?? '')));
        $code = (string) preg_replace('/[^a-z0-9_\-]/', '', $code);
        $name = trim((string) ($_POST['name'] ?? ''));
        $price = (float) str_replace(',', '.', (string) ($_POST['price'] ?? '0'));
        $durRaw = trim((string) ($_POST['duration_days'] ?? ''));
        $duration = $durRaw === '' ? null : (int) $durRaw;
        $benefits = trim((string) ($_POST['benefits'] ?? ''));
        if ($code === '' || strlen($code) > 32) {
            ia_flash('billing_error', 'Укажите корректный код (латиница, цифры, до 32 симв.).');
        } elseif ($name === '') {
            ia_flash('billing_error', 'Укажите название тарифа.');
        } else {
            try {
                $st = $pdo->prepare(
                    'INSERT INTO billing_tariffs (code, name, price, duration_days, benefits, sort_order) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $st->execute([$code, $name, $price, $duration, $benefits === '' ? null : $benefits, ia_billing_next_tariff_sort($pdo)]);
                ia_flash('billing_ok', 'Тариф добавлен.');
            } catch (\PDOException) {
                ia_flash('billing_error', 'Такой код уже существует.');
            }
        }
        ia_redirect(ia_admin_url('tariffs.php'));
    }
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM billing_tariffs WHERE id = ?')->execute([$id]);
            ia_flash('billing_ok', 'Тариф удалён.');
        }
        ia_redirect(ia_admin_url('tariffs.php'));
    }
    if ($action === 'sort_up' || $action === 'sort_down') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            ia_billing_swap_tariff_order($pdo, $id, $action === 'sort_up' ? 'up' : 'down');
            ia_flash('billing_ok', 'Порядок обновлён.');
        }
        ia_redirect(ia_admin_url('tariffs.php'));
    }
}

function ia_billing_tariff_update(IaPgConnection|IaPdoConnection $pdo, int $id, array $data): bool
{
    $name = trim((string) ($data['name'] ?? ''));
    $price = (float) str_replace(',', '.', (string) ($data['price'] ?? '0'));
    $durRaw = trim((string) ($data['duration_days'] ?? ''));
    $duration = $durRaw === '' ? null : (int) $durRaw;
    $benefits = trim((string) ($data['benefits'] ?? ''));
    if ($name === '') {
        return false;
    }
    $st = $pdo->prepare(
        'UPDATE billing_tariffs SET name = ?, price = ?, duration_days = ?, benefits = ? WHERE id = ?'
    );
    return $st->execute([$name, $price, $duration, $benefits === '' ? null : $benefits, $id]);
}
