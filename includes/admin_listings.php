<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

require_once IA_ROOT . '/includes/platform_notifications.php';
require_once IA_ROOT . '/includes/listing_lifecycle.php';
require_once IA_ROOT . '/includes/ia_cache.php';

function ia_admin_listings_touch_public_cache(): void
{
    ia_cache_forget('pub_body_type_counts');
}

function ia_admin_listing_status_ru(string $status): string
{
    return ia_pub_listing_status_ru($status);
}

function ia_admin_listings_filters(): array
{
    return [
        'date_from' => ia_get_date('date_from'),
        'date_to' => ia_get_date('date_to'),
        'status' => ia_input_enum($_GET['status'] ?? '', ia_listing_status_codes()),
        'vip' => ia_input_enum($_GET['vip'] ?? '', ['0', '1']),
        'availability' => ia_input_enum($_GET['availability'] ?? '', ['in_stock', 'on_order']),
        'body_type' => ia_input_enum($_GET['body_type'] ?? '', array_keys(ia_listing_body_types_map())),
    ];
}

function ia_admin_listings_filters_open(array $filters): bool
{
    return $filters['date_from'] !== ''
        || $filters['date_to'] !== ''
        || $filters['status'] !== ''
        || $filters['vip'] !== ''
        || $filters['availability'] !== ''
        || trim((string) ($filters['body_type'] ?? '')) !== '';
}

function ia_admin_listing_hard_delete($pdo, int $id): bool
{
    require_once IA_ROOT . '/includes/listing_media.php';
    $st = $pdo->prepare('SELECT id FROM ad_listings WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    if (!$st->fetchColumn()) {
        return false;
    }
    ia_listing_block_chat_threads_for_listing($pdo, $id);
    ia_listing_purge_all_files_for_listing($pdo, $id);
    $st = $pdo->prepare('DELETE FROM ad_listings WHERE id = ?');
    $st->execute([$id]);

    return $st->rowCount() > 0;
}

function ia_admin_listings_handle_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('listings_error', 'Сессия устарела. Повторите действие.');
        ia_redirect(ia_admin_url('listings.php'));
    }
    $action = (string) ($_POST['action'] ?? '');
    $pdo = ia_db();

    if (in_array($action, ['bulk_delete', 'bulk_archive', 'bulk_approve'], true)) {
        $rawIds = $_POST['listing_ids'] ?? [];
        $ids = ia_input_int_list(is_array($rawIds) ? $rawIds : []);
        if (empty($ids)) {
            ia_flash('listings_error', 'Не выбрано ни одного объявления.');
            ia_redirect(ia_admin_url('listings.php'));
        }
        $place = implode(',', array_fill(0, count($ids), '?'));
        if ($action === 'bulk_delete') {
            $st = $pdo->prepare("UPDATE ad_listings SET status='archived' WHERE id IN ($place)");
            $st->execute($ids);
            foreach ($ids as $delId) {
                ia_listing_block_chat_threads_for_listing($pdo, (int) $delId);
            }
            ia_flash('listings_ok', sprintf('Скрыто из каталога (записи сохранены в базе): %d.', count($ids)));
        } elseif ($action === 'bulk_archive') {
            $st = $pdo->prepare("UPDATE ad_listings SET status='archived' WHERE id IN ($place)");
            $st->execute($ids);
            foreach ($ids as $aid) {
                ia_listing_block_chat_threads_for_listing($pdo, (int) $aid);
            }
            ia_flash('listings_ok', sprintf('В архив отправлено: %d.', count($ids)));
        } else {
            $approved = [];
            $blockedPay = 0;
            foreach ($ids as $lid) {
                if (!ia_promotion_can_admin_approve_listing($pdo, (int) $lid)) {
                    $blockedPay++;
                    continue;
                }
                $approved[] = (int) $lid;
            }
            if ($approved === []) {
                ia_flash('listings_error', 'Нельзя подтвердить: ожидается оплата VIP/TOP.');
                ia_redirect(ia_admin_url('listings.php'));
            }
            $placeOk = implode(',', array_fill(0, count($approved), '?'));
            $expiresAt = ia_platform_listing_expires_at_value();
            $st = $pdo->prepare("UPDATE ad_listings SET status='approved', rejection_reason=NULL, expires_at=?, last_engagement_at=NOW() WHERE id IN ($placeOk)");
            $st->execute(array_merge([$expiresAt], $approved));
            foreach ($approved as $approvedId) {
                ia_listing_unblock_chat_threads_for_listing($pdo, $approvedId);
                ia_platform_notify_listing_moderation($pdo, $approvedId, 'approved', null);
            }
            $msg = sprintf('Подтверждено: %d.', count($approved));
            if ($blockedPay > 0) {
                $msg .= sprintf(' Пропущено (нет оплаты VIP/TOP): %d.', $blockedPay);
            }
            ia_flash('listings_ok', $msg);
        }
        ia_admin_listings_touch_public_cache();
        ia_redirect(ia_admin_url('listings.php'));
    }

    $id = ia_post_int('listing_id');
    if ($id <= 0) {
        ia_flash('listings_error', 'Некорректное объявление.');
        ia_redirect(ia_admin_url('listings.php'));
    }
    if ($action === 'approve') {
        if (!ia_promotion_can_admin_approve_listing($pdo, $id)) {
            ia_flash('listings_error', 'Сначала пользователь должен оплатить тариф VIP/TOP.');
            ia_redirect(ia_admin_url('listings.php'));
        }
        $expiresAt = ia_platform_listing_expires_at_value();
        $st = $pdo->prepare("UPDATE ad_listings SET status='approved', rejection_reason=NULL, expires_at=?, last_engagement_at=NOW() WHERE id=?");
        $st->execute([$expiresAt, $id]);
        ia_listing_unblock_chat_threads_for_listing($pdo, $id);
        ia_platform_notify_listing_moderation($pdo, $id, 'approved', null);
        ia_flash('listings_ok', 'Объявление подтверждено.');
    } elseif ($action === 'archive') {
        $st = $pdo->prepare("UPDATE ad_listings SET status='archived' WHERE id=?");
        $st->execute([$id]);
        ia_listing_block_chat_threads_for_listing($pdo, $id);
        ia_flash('listings_ok', 'Объявление архивировано.');
    } elseif ($action === 'toggle_vip') {
        $st = $pdo->prepare('UPDATE ad_listings SET is_vip = CASE WHEN is_vip=1 THEN 0 ELSE 1 END WHERE id=?');
        $st->execute([$id]);
        ia_flash('listings_ok', 'Статус VIP изменён.');
    } elseif ($action === 'toggle_top') {
        $st = $pdo->prepare('UPDATE ad_listings SET is_top = CASE WHEN is_top=1 THEN 0 ELSE 1 END WHERE id=?');
        $st->execute([$id]);
        ia_flash('listings_ok', 'Закрепление «Топ» изменено.');
    } elseif ($action === 'delete') {
        $st = $pdo->prepare("UPDATE ad_listings SET status='archived' WHERE id=?");
        $st->execute([$id]);
        ia_listing_block_chat_threads_for_listing($pdo, $id);
        ia_flash('listings_ok', 'Объявление скрыто из каталога (запись в базе сохранена).');
    } elseif ($action === 'purge') {
        if (!ia_admin_listing_hard_delete($pdo, $id)) {
            ia_flash('listings_error', 'Не удалось удалить объявление.');
            ia_redirect(ia_admin_url('listings.php'));
        }
        ia_flash('listings_ok', 'Объявление удалено безвозвратно.');
    }
    ia_admin_listings_touch_public_cache();
    ia_redirect(ia_admin_url('listings.php'));
}

function ia_admin_listings_list(array $filters): array
{
    $sql = 'SELECT l.*,u.name AS user_name,u.phone AS user_phone,u.email AS user_email FROM ad_listings l
            LEFT JOIN platform_users u ON u.id=l.user_id WHERE 1=1';
    $params = [];
    if ($filters['status'] !== '') {
        $sql .= ' AND l.status=:status';
        $params['status'] = $filters['status'];
    }
    if ($filters['vip'] !== '') {
        $sql .= ' AND l.is_vip=:vip';
        $params['vip'] = (int) $filters['vip'];
    }
    $af = $filters['availability'] ?? '';
    if ($af === 'on_order' || $af === 'in_stock') {
        $sql .= ' AND l.availability=:avail';
        $params['avail'] = $af;
    }
    $bf = trim((string) ($filters['body_type'] ?? ''));
    if ($bf !== '') {
        $allowedBody = array_keys(ia_listing_body_types_map());
        if (in_array($bf, $allowedBody, true)) {
            $sql .= ' AND l.body_type=:bt';
            $params['bt'] = $bf;
        }
    }
    if ($filters['date_from'] !== '') {
        $sql .= ' AND DATE(l.created_at) >= :df';
        $params['df'] = $filters['date_from'];
    }
    if ($filters['date_to'] !== '') {
        $sql .= ' AND DATE(l.created_at) <= :dt';
        $params['dt'] = $filters['date_to'];
    }
    $sql .= ' ORDER BY l.id DESC';
    $st = ia_db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll() ?: [];
}

function ia_admin_listing_by_id(int $id): ?array
{
    $st = ia_db()->prepare('SELECT * FROM ad_listings WHERE id=? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function ia_admin_listing_body_types(): array
{
    return [
        '' => '— не указан —',
    ] + ia_listing_body_types_map();
}

function ia_admin_listing_body_label_ru(string $code): string
{
    $map = ia_admin_listing_body_types();
    return $map[$code] ?? '—';
}

function ia_admin_listing_update(int $id, array $data): bool
{
    $bodyAllowed = array_keys(ia_admin_listing_body_types());
    $bodyType = trim((string) ($data['body_type'] ?? ''));
    if (!in_array($bodyType, $bodyAllowed, true)) {
        $bodyType = '';
    }
    $mileageRaw = $data['mileage_km'] ?? null;
    $mileage = ($mileageRaw === null || $mileageRaw === '') ? null : max(0, (int) $mileageRaw);
    $modelYearRaw = $data['model_year'] ?? null;
    $modelYear = ($modelYearRaw === null || $modelYearRaw === '') ? null : max(1900, min(2100, (int) $modelYearRaw));
    $transmission = trim((string) ($data['transmission'] ?? ''));
    $fuelType = trim((string) ($data['fuel_type'] ?? ''));
    if (!function_exists('ia_tj_city_normalize')) {
        require_once IA_ROOT . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'tj_cities.php';
    }
    $city = ia_tj_city_normalize(trim((string) ($data['city'] ?? '')));

    $color = mb_substr(trim((string) ($data['color'] ?? '')), 0, 40);
    $driveAllowed = ['', 'front', 'rear', 'awd', '4wd'];
    $driveRaw = trim((string) ($data['drive_type'] ?? ''));
    $driveType = in_array($driveRaw, $driveAllowed, true) ? $driveRaw : '';
    $engineVolume = mb_substr(trim((string) ($data['engine_volume'] ?? '')), 0, 40);
    $hasTurbo = empty($data['has_turbo']) ? 0 : 1;
    $condAllowed = ['', 'new', 'used'];
    $condRaw = trim((string) ($data['condition_state'] ?? ''));
    $conditionState = in_array($condRaw, $condAllowed, true) ? $condRaw : '';
    $customsCleared = empty($data['customs_cleared']) ? 0 : 1;
    $taxiLicense = empty($data['taxi_license']) ? 0 : 1;
    $availabilityNorm = trim((string) ($data['availability'] ?? '')) === 'on_order' ? 'on_order' : 'in_stock';
    $prepayRaw = $data['prepayment_amount'] ?? null;
    $prepayClean = ($prepayRaw === null || trim((string) $prepayRaw) === '') ? null
        : max(0, (float) str_replace([' ', ','], ['', '.'], (string) $prepayRaw));
    if ($availabilityNorm !== 'on_order') {
        $prepayClean = null;
    }

    $st = ia_db()->prepare(
        'UPDATE ad_listings SET photo_url=:photo_url,brand=:brand,model=:model,price=:price,seller_name=:seller_name,
            availability=:availability,status=:status,is_vip=:is_vip,is_top=:is_top,
            body_type=:body_type,mileage_km=:mileage_km,model_year=:model_year,
            transmission=:transmission,fuel_type=:fuel_type,city=:city,
            color=:color,drive_type=:drive_type,engine_volume=:engine_volume,has_turbo=:has_turbo,
            condition_state=:condition_state,customs_cleared=:customs_cleared,taxi_license=:taxi_license,
            prepayment_amount=:prepayment_amount,currency=:currency
         WHERE id=:id'
    );
    $ok = $st->execute([
        'id' => $id,
        'photo_url' => trim((string) ($data['photo_url'] ?? '')),
        'brand' => trim((string) ($data['brand'] ?? '')),
        'model' => trim((string) ($data['model'] ?? '')),
        'price' => (float) ($data['price'] ?? 0),
        'seller_name' => trim((string) ($data['seller_name'] ?? '')),
        'availability' => $availabilityNorm,
        'status' => trim((string) ($data['status'] ?? 'pending')),
        'is_vip' => empty($data['is_vip']) ? 0 : 1,
        'is_top' => empty($data['is_top']) ? 0 : 1,
        'body_type' => $bodyType,
        'mileage_km' => $mileage,
        'model_year' => $modelYear,
        'transmission' => $transmission,
        'fuel_type' => $fuelType,
        'city' => $city,
        'color' => $color,
        'drive_type' => $driveType,
        'engine_volume' => $engineVolume,
        'has_turbo' => $hasTurbo,
        'condition_state' => $conditionState,
        'customs_cleared' => $customsCleared,
        'taxi_license' => $taxiLicense,
        'prepayment_amount' => $prepayClean,
        'currency' => ia_listing_currency_normalize($data['currency'] ?? null),
    ]);
    if ($ok) {
        ia_admin_listings_touch_public_cache();
    }

    return $ok;
}

function ia_admin_listing_reject(int $id, string $reason): bool
{
    $st = ia_db()->prepare('UPDATE ad_listings SET status = :status, rejection_reason = :reason WHERE id=:id');
    $ok = $st->execute([
        'id' => $id,
        'status' => 'rejected',
        'reason' => trim($reason),
    ]);
    if ($ok) {
        ia_admin_listings_touch_public_cache();
    }

    return $ok;
}
