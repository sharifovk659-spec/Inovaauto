<?php

declare(strict_types=1);

require_once IA_ROOT . '/includes/listing_media.php';
require_once IA_ROOT . '/includes/listing_uploads.php';

/**
 * @return list<array{id:int,src:string}|null>
 */
function ia_listing_edit_slot_prefill(IaPgConnection|IaPdoConnection $pdo, int $listingId): array
{
    $rows = ia_listing_media_list($pdo, $listingId);
    $images = array_values(array_filter(
        $rows,
        static fn (array $row): bool => ($row['media_kind'] ?? '') === 'image'
    ));
    $slots = array_fill(0, IA_LISTING_PHOTO_SLOT_COUNT, null);
    foreach ($images as $idx => $row) {
        if ($idx >= IA_LISTING_PHOTO_SLOT_COUNT) {
            break;
        }
        $slots[$idx] = [
            'id' => (int) ($row['id'] ?? 0),
            'src' => ia_listing_photo_src((string) ($row['stored_path'] ?? '')),
        ];
    }

    return $slots;
}

/**
 * @return array<int, array{kind:string,path:string}>
 */
function ia_listing_collect_slot_uploads(?string &$rejectMessage = null): array
{
    $out = [];
    if (!isset($_FILES['listing_slot_photo']) || !is_array($_FILES['listing_slot_photo']['name'] ?? null)) {
        return $out;
    }

    foreach ($_FILES['listing_slot_photo']['name'] as $slotRaw => $name) {
        $slot = (int) $slotRaw;
        if ($slot < 0 || $slot >= IA_LISTING_PHOTO_SLOT_COUNT) {
            continue;
        }
        if (trim((string) $name) === '') {
            continue;
        }
        $f = [
            'name' => (string) ($_FILES['listing_slot_photo']['name'][$slotRaw] ?? ''),
            'type' => (string) ($_FILES['listing_slot_photo']['type'][$slotRaw] ?? ''),
            'tmp_name' => (string) ($_FILES['listing_slot_photo']['tmp_name'][$slotRaw] ?? ''),
            'error' => (int) ($_FILES['listing_slot_photo']['error'][$slotRaw] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($_FILES['listing_slot_photo']['size'][$slotRaw] ?? 0),
        ];
        if ((int) ($f['error'] ?? 0) !== UPLOAD_ERR_OK) {
            continue;
        }
        $one = ia_listing_try_save_upload_entry($f, $slot, $rejectMessage);
        if ($one !== null) {
            $out[$slot] = $one;
            continue;
        }
        if ($rejectMessage !== null && $rejectMessage !== '') {
            return [];
        }
    }

    return $out;
}

/**
 * @param array<int, array{kind:string,path:string}> $newBySlot
 */
function ia_listing_edit_apply_slot_photos(IaPgConnection|IaPdoConnection $pdo, int $listingId, int $userId, array $newBySlot, ?string &$error): bool
{
    $error = null;
    $keepRaw = $_POST['slot_keep_media_id'] ?? [];
    if (!is_array($keepRaw)) {
        $keepRaw = [];
    }

    $existing = ia_listing_media_list($pdo, $listingId);
    $existingById = [];
    foreach ($existing as $row) {
        $existingById[(int) ($row['id'] ?? 0)] = $row;
    }

    $resolved = [];
    for ($slot = 0; $slot < IA_LISTING_PHOTO_SLOT_COUNT; $slot++) {
        if (isset($newBySlot[$slot])) {
            $resolved[$slot] = ['type' => 'new', 'data' => $newBySlot[$slot]];
            continue;
        }
        $keepId = (int) ($keepRaw[$slot] ?? 0);
        if ($keepId > 0 && isset($existingById[$keepId]) && ($existingById[$keepId]['media_kind'] ?? '') === 'image') {
            $resolved[$slot] = ['type' => 'keep', 'id' => $keepId];
            continue;
        }
        if (ia_listing_photo_slot_is_required($slot)) {
            $error = ia_listing_photo_required_missing_message();

            return false;
        }
    }

    $keepIds = [];
    foreach ($resolved as $item) {
        if (($item['type'] ?? '') === 'keep') {
            $keepIds[(int) $item['id']] = true;
        }
    }

    $pdo->beginTransaction();
    try {
        foreach ($existing as $row) {
            $rid = (int) ($row['id'] ?? 0);
            if ($rid <= 0) {
                continue;
            }
            if (($row['media_kind'] ?? '') === 'image' && !isset($keepIds[$rid])) {
                ia_listing_media_delete_row($pdo, $listingId, $rid);
            }
        }

        $ins = $pdo->prepare(
            'INSERT INTO ad_listing_media (listing_id, sort_order, media_kind, stored_path) VALUES (?, ?, ?, ?)'
        );
        $upd = $pdo->prepare('UPDATE ad_listing_media SET sort_order = ? WHERE id = ? AND listing_id = ?');

        foreach ($resolved as $slot => $item) {
            if (($item['type'] ?? '') === 'new') {
                $data = $item['data'];
                $ins->execute([$listingId, $slot, $data['kind'] ?? 'image', $data['path'] ?? '']);
            } else {
                $upd->execute([$slot, (int) $item['id'], $listingId]);
            }
        }

        ia_listing_sync_primary_photo($pdo, $listingId);
        $pdo->commit();

        return true;
    } catch (\Throwable $e) {
        $pdo->rollBack();
        $error = 'Не удалось сохранить фото объявления.';

        return false;
    }
}
