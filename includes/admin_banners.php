<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

require_once IA_ROOT . '/includes/ia_cache.php';

function ia_admin_banners_bust_cache(): void
{
    ia_cache_forget('pub_banners_home');
}

function ia_admin_banners_upload_dir(): string
{
    return IA_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'banners';
}

function ia_admin_banner_parse_dt(?string $raw): ?string
{
    $v = trim((string) $raw);
    if ($v === '') {
        return null;
    }
    $dt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $v);
    if ($dt === false) {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $v);
    }
    if ($dt === false) {
        return null;
    }

    return $dt->format('Y-m-d H:i:s');
}

/**
 * @return list<array<string,mixed>>
 */
function ia_admin_banners_list(IaPgConnection|IaPdoConnection $pdo, ?string $slot): array
{
    $sql = 'SELECT * FROM site_banners WHERE 1=1';
    $params = [];
    if ($slot !== null && $slot !== '' && in_array($slot, ['homepage', 'promo_slider', 'ads'], true)) {
        $sql .= ' AND slot = ?';
        $params[] = $slot;
    }
    $sql .= ' ORDER BY slot ASC, sort_order ASC, id ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll() ?: [];
}

function ia_admin_banners_handle_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('banners_error', 'Сессия устарела.');
        ia_redirect(ia_admin_url('banners.php'));
    }

    $pdo = ia_db();
    $action = (string) ($_POST['action'] ?? '');
    $slotPost = (string) ($_POST['slot'] ?? '');
    $returnSlot = $slotPost !== '' ? '?slot=' . rawurlencode($slotPost) : '';

    if ($action === 'upload') {
        $slot = $slotPost;
        if (!in_array($slot, ['homepage', 'promo_slider', 'ads'], true)) {
            ia_flash('banners_error', 'Некорректный тип размещения.');
            ia_redirect(ia_admin_url('banners.php' . $returnSlot));
        }
        $title = ia_post_text('title', 200);
        $linkUrl = ia_input_url($_POST['link_url'] ?? '');
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        $startsAt = ia_admin_banner_parse_dt($_POST['starts_at'] ?? null);
        $endsAt = ia_admin_banner_parse_dt($_POST['ends_at'] ?? null);
        if ($startsAt !== null && $endsAt !== null && strtotime($endsAt) <= strtotime($startsAt)) {
            ia_flash('banners_error', 'Время окончания должно быть позже времени начала.');
            ia_redirect(ia_admin_url('banners.php' . $returnSlot));
        }
        if (!isset($_FILES['image']) || !is_array($_FILES['image']) || (int) ($_FILES['image']['error'] ?? 0) !== UPLOAD_ERR_OK) {
            ia_flash('banners_error', 'Загрузите изображение (JPEG, PNG, WebP, GIF).');
            ia_redirect(ia_admin_url('banners.php' . $returnSlot));
        }
        $tmp = (string) $_FILES['image']['tmp_name'];
        $info = @getimagesize($tmp);
        if ($info === false) {
            ia_flash('banners_error', 'Файл не является изображением.');
            ia_redirect(ia_admin_url('banners.php' . $returnSlot));
        }
        $mime = (string) ($info['mime'] ?? '');
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (!isset($map[$mime])) {
            ia_flash('banners_error', 'Допустимы только JPEG, PNG, WebP, GIF.');
            ia_redirect(ia_admin_url('banners.php' . $returnSlot));
        }
        if ((int) ($_FILES['image']['size'] ?? 0) > 2 * 1024 * 1024) {
            ia_flash('banners_error', 'Размер файла не более 2 МБ.');
            ia_redirect(ia_admin_url('banners.php' . $returnSlot));
        }

        $dir = ia_admin_banners_upload_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $basename = bin2hex(random_bytes(16)) . '.' . $map[$mime];
        $dest = $dir . DIRECTORY_SEPARATOR . $basename;
        if (!@move_uploaded_file($tmp, $dest)) {
            ia_flash('banners_error', 'Не удалось сохранить файл.');
            ia_redirect(ia_admin_url('banners.php' . $returnSlot));
        }
        $rel = $basename;
        $pdo->prepare(
            'INSERT INTO site_banners (slot, title, image_path, link_url, sort_order, is_active, starts_at, ends_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)'
        )->execute([$slot, $title, $rel, $linkUrl, $sortOrder, $startsAt, $endsAt]);
        ia_flash('banners_ok', 'Баннер добавлен.');
        ia_admin_banners_bust_cache();
        ia_redirect(ia_admin_url('banners.php' . $returnSlot));
    }

    if ($action === 'toggle_active') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('UPDATE site_banners SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$id]);
            ia_flash('banners_ok', 'Статус баннера обновлён.');
        }
        ia_admin_banners_bust_cache();
        ia_redirect(ia_admin_url('banners.php' . $returnSlot));
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $pdo->prepare('SELECT image_path FROM site_banners WHERE id = ?');
            $st->execute([$id]);
            $row = $st->fetch();
            if ($row) {
                $path = (string) $row['image_path'];
                $pdo->prepare('DELETE FROM site_banners WHERE id = ?')->execute([$id]);
                if ($path !== '' && !str_starts_with($path, 'demo/')) {
                    $full = ia_admin_banners_upload_dir() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
                    if (is_file($full)) {
                        @unlink($full);
                    }
                }
                ia_flash('banners_ok', 'Баннер удалён.');
            }
        }
        ia_admin_banners_bust_cache();
        ia_redirect(ia_admin_url('banners.php' . $returnSlot));
    }

    if ($action === 'save_sort') {
        $id = (int) ($_POST['id'] ?? 0);
        $sort = (int) ($_POST['sort_order'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('UPDATE site_banners SET sort_order = ? WHERE id = ?')->execute([$sort, $id]);
            ia_flash('banners_ok', 'Порядок сохранён.');
        }
        ia_admin_banners_bust_cache();
        ia_redirect(ia_admin_url('banners.php' . $returnSlot));
    }

    if ($action === 'save_schedule') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $startsAt = ia_admin_banner_parse_dt($_POST['starts_at'] ?? null);
            $endsAt = ia_admin_banner_parse_dt($_POST['ends_at'] ?? null);
            if ($startsAt !== null && $endsAt !== null && strtotime($endsAt) <= strtotime($startsAt)) {
                ia_flash('banners_error', 'Время окончания должно быть позже времени начала.');
                ia_redirect(ia_admin_url('banners.php' . $returnSlot));
            }
            $pdo->prepare('UPDATE site_banners SET starts_at = ?, ends_at = ? WHERE id = ?')->execute([$startsAt, $endsAt, $id]);
            ia_flash('banners_ok', 'Период баннера сохранён.');
        }
        ia_admin_banners_bust_cache();
        ia_redirect(ia_admin_url('banners.php' . $returnSlot));
    }

    ia_redirect(ia_admin_url('banners.php'));
}
