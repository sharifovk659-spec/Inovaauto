<?php

declare(strict_types=1);

require_once IA_ROOT . '/includes/listing_uploads.php';

/** Максимум файлов (фото + видео) на одно объявление */
const IA_LISTING_MEDIA_MAX_FILES = 20;

/** Фиксированные ракурсы фото при размещении объявления */
const IA_LISTING_PHOTO_SLOT_COUNT = 10;

/** Обязательные ракурсы (слоты 1–5); остальные — по желанию */
const IA_LISTING_PHOTO_REQUIRED_COUNT = 5;

function ia_listing_photo_slot_is_required(int $index): bool
{
    return $index >= 0 && $index < IA_LISTING_PHOTO_REQUIRED_COUNT;
}

function ia_listing_photo_required_missing_message(int $saved = 0, int $uploaded = 0): string
{
    $msg = 'Для публикации нужно минимум ' . IA_LISTING_PHOTO_REQUIRED_COUNT . ' обязательных фото автомобиля.';
    if ($uploaded > 0) {
        $msg .= ' Сервер получил файлов: ' . $uploaded . '.';
    }
    if ($saved > 0) {
        $msg .= ' Принято фото: ' . $saved . '.';
    }

    return $msg;
}

/**
 * @return list<string>
 */
function ia_listing_photo_slot_labels_ru(): array
{
    return [
        'Перед',
        'Зад',
        'Левая сторона',
        'Правая сторона',
        'Диагональ спереди',
        'Салон спереди',
        'Салон второй ракурс',
        'Задний салон',
        'Общий вид',
        'Финальный обзор',
    ];
}

/**
 * @return list<string>
 */
function ia_listing_photo_slot_card_label_ru(int $index): string
{
    return ia_listing_photo_slot_labels_ru()[$index] ?? '';
}

function ia_listing_photo_slot_display_label_ru(int $index): string
{
    return ia_listing_form_plain_label(ia_listing_photo_slot_card_label_ru($index));
}

function ia_listing_photo_slot_hints_ru(): array
{
    return [
        'Весь автомобиль спереди.',
        'Полный задний вид.',
        'Левый бок полностью.',
        'Правый бок полностью.',
        'Передний диагональный ракурс.',
        'Руль, приборная панель.',
        'Второй ракурс салона.',
        'Задний ряд сидений.',
        'Круговой обзор автомобиля.',
        'Итоговый обзорный кадр.',
    ];
}

function ia_listing_photo_slot_example_relative_path(int $index): string
{
    $slotNumber = max(1, min(IA_LISTING_PHOTO_SLOT_COUNT, $index + 1));

    return 'IMG/' . $slotNumber . '.png';
}

/**
 * @return array{width:int,height:int}|null
 */
function ia_listing_photo_slot_example_size(int $index): ?array
{
    $relative = ia_listing_photo_slot_example_relative_path($index);
    $absolute = IA_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($absolute)) {
        return null;
    }

    $size = @getimagesize($absolute);
    if ($size === false) {
        return null;
    }

    return [
        'width' => (int) $size[0],
        'height' => (int) $size[1],
    ];
}

function ia_listing_photo_slot_example_url(int $index): string
{
    $size = ia_listing_photo_slot_example_size($index);
    if ($size === null) {
        return '';
    }

    return ia_public_asset(ia_listing_photo_slot_example_relative_path($index));
}

function ia_listing_photo_slot_example_uses_raster(int $index): bool
{
    $size = ia_listing_photo_slot_example_size($index);
    if ($size === null) {
        return false;
    }

    return true;
}

function ia_listing_photo_slot_example_markup(int $index): string
{
    $url = ia_listing_photo_slot_example_url($index);
    if ($url === '') {
        return ia_listing_photo_slot_guide_svg($index);
    }

    $size = ia_listing_photo_slot_example_size($index);
    $attrs = 'class="ia-photo-slot-example-img" src="' . ia_h($url) . '" alt="" loading="lazy" decoding="async"';
    if ($size !== null) {
        $attrs .= ' width="' . (int) $size['width'] . '" height="' . (int) $size['height'] . '"';
    }

    return '<img ' . $attrs . '>';
}

function ia_listing_photo_slot_guide_svg(int $index): string
{
    $guides = [
        '<svg viewBox="0 0 120 90" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="24" y="24" width="72" height="34" rx="8" fill="rgba(61,126,255,0.14)" stroke="#3d7eff" stroke-width="2"/><path d="M34 24 L42 14 H78 L86 24" fill="none" stroke="#3d7eff" stroke-width="2"/><circle cx="36" cy="62" r="8" fill="#1d4ed8"/><circle cx="84" cy="62" r="8" fill="#1d4ed8"/><rect x="40" y="32" width="16" height="8" rx="2" fill="#93c5fd"/><rect x="64" y="32" width="16" height="8" rx="2" fill="#93c5fd"/></svg>',
        '<svg viewBox="0 0 120 90" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="24" y="24" width="72" height="34" rx="8" fill="rgba(61,126,255,0.14)" stroke="#3d7eff" stroke-width="2"/><path d="M34 24 L42 14 H78 L86 24" fill="none" stroke="#3d7eff" stroke-width="2" transform="rotate(180 60 24)"/><circle cx="36" cy="62" r="8" fill="#1d4ed8"/><circle cx="84" cy="62" r="8" fill="#1d4ed8"/><rect x="48" y="34" width="24" height="10" rx="2" fill="#93c5fd"/></svg>',
        '<svg viewBox="0 0 120 90" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M18 58 H92" stroke="#3d7eff" stroke-width="2"/><rect x="24" y="30" width="68" height="24" rx="10" fill="rgba(61,126,255,0.14)" stroke="#3d7eff" stroke-width="2"/><path d="M24 42 H18 V52 H24" fill="none" stroke="#3d7eff" stroke-width="2"/><path d="M92 42 H98 V52 H92" fill="none" stroke="#3d7eff" stroke-width="2"/><circle cx="34" cy="58" r="8" fill="#1d4ed8"/><circle cx="82" cy="58" r="8" fill="#1d4ed8"/></svg>',
        '<svg viewBox="0 0 120 90" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M18 58 H92" stroke="#3d7eff" stroke-width="2"/><rect x="28" y="30" width="68" height="24" rx="10" fill="rgba(61,126,255,0.14)" stroke="#3d7eff" stroke-width="2"/><path d="M28 42 H22 V52 H28" fill="none" stroke="#3d7eff" stroke-width="2"/><path d="M96 42 H102 V52 H96" fill="none" stroke="#3d7eff" stroke-width="2"/><circle cx="38" cy="58" r="8" fill="#1d4ed8"/><circle cx="86" cy="58" r="8" fill="#1d4ed8"/></svg>',
        '<svg viewBox="0 0 120 90" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M18 58 H92" stroke="#3d7eff" stroke-width="2"/><path d="M24 54 L38 28 H82 L96 54 Z" fill="rgba(61,126,255,0.14)" stroke="#3d7eff" stroke-width="2"/><circle cx="36" cy="58" r="8" fill="#1d4ed8"/><circle cx="78" cy="58" r="8" fill="#1d4ed8"/></svg>',
        '<svg viewBox="0 0 120 90" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M18 58 H92" stroke="#3d7eff" stroke-width="2"/><path d="M24 54 L38 28 H82 L96 54 Z" fill="rgba(61,126,255,0.14)" stroke="#3d7eff" stroke-width="2" transform="scale(-1,1) translate(-120,0)"/><circle cx="42" cy="58" r="8" fill="#1d4ed8"/><circle cx="84" cy="58" r="8" fill="#1d4ed8"/></svg>',
        '<svg viewBox="0 0 120 90" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="16" y="18" width="88" height="54" rx="8" fill="rgba(61,126,255,0.1)" stroke="#3d7eff" stroke-width="2"/><circle cx="34" cy="54" r="14" fill="none" stroke="#3d7eff" stroke-width="2"/><rect x="52" y="28" width="40" height="18" rx="4" fill="#93c5fd"/><rect x="20" y="62" width="80" height="10" rx="3" fill="rgba(61,126,255,0.18)" stroke="#3d7eff" stroke-width="1.5"/></svg>',
        '<svg viewBox="0 0 120 90" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="16" y="18" width="88" height="54" rx="8" fill="rgba(61,126,255,0.1)" stroke="#3d7eff" stroke-width="2"/><rect x="48" y="30" width="24" height="34" rx="4" fill="rgba(61,126,255,0.18)" stroke="#3d7eff" stroke-width="2"/><rect x="20" y="62" width="34" height="10" rx="3" fill="#93c5fd"/><rect x="66" y="62" width="34" height="10" rx="3" fill="#93c5fd"/></svg>',
        '<svg viewBox="0 0 120 90" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="16" y="18" width="88" height="54" rx="8" fill="rgba(61,126,255,0.1)" stroke="#3d7eff" stroke-width="2"/><rect x="24" y="62" width="72" height="10" rx="3" fill="#93c5fd"/><path d="M30 28 H90 V48 H30 Z" fill="rgba(61,126,255,0.14)" stroke="#3d7eff" stroke-width="2"/></svg>',
        '<svg viewBox="0 0 120 90" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="60" cy="46" r="28" fill="none" stroke="#3d7eff" stroke-width="2" stroke-dasharray="6 5"/><path d="M60 18 V30 M60 62 V74 M32 46 H44 M76 46 H88" stroke="#3d7eff" stroke-width="2"/><rect x="44" y="36" width="32" height="18" rx="6" fill="rgba(61,126,255,0.14)" stroke="#3d7eff" stroke-width="2"/></svg>',
    ];

    return $guides[$index] ?? $guides[0];
}

function ia_ensure_listing_media_table(IaPgConnection|IaPdoConnection $pdo): void
{
    require_once IA_ROOT . '/includes/db_compat.php';
    if (ia_db_table_exists($pdo, 'ad_listing_media')) {
        return;
    }
    if (ia_db_is_pgsql($pdo)) {
        $pdo->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS ad_listing_media (
    id BIGSERIAL PRIMARY KEY,
    listing_id INTEGER NOT NULL REFERENCES ad_listings(id) ON DELETE CASCADE,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    media_kind VARCHAR(10) NOT NULL DEFAULT 'image',
    stored_path VARCHAR(512) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_amedia_listing_sort ON ad_listing_media (listing_id, sort_order)');

        return;
    }
    $pdo->exec(
        <<<'SQL'
CREATE TABLE ad_listing_media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id INT UNSIGNED NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    media_kind ENUM('image','video') NOT NULL DEFAULT 'image',
    stored_path VARCHAR(512) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ia_idx_amedia_listing_sort (listing_id, sort_order),
    CONSTRAINT ia_fk_amedia_ad_listing FOREIGN KEY (listing_id) REFERENCES ad_listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );
}

/** Перед сохранением объявления: таблица медиа и колонки ad_listings (если миграция ещё не запускалась). */
function ia_listing_ensure_save_ready(IaPgConnection|IaPdoConnection $pdo): void
{
    require_once IA_ROOT . '/includes/db_compat.php';
    ia_ensure_listing_media_table($pdo);
    if (ia_db_is_pgsql($pdo)) {
        require_once IA_ROOT . '/includes/schema_pgsql.php';
        ia_ensure_pgsql_ad_listings_columns($pdo);
    } else {
        require_once IA_ROOT . '/includes/schema_platform.php';
        ia_ensure_platform_columns($pdo);
        require_once IA_ROOT . '/includes/schema_frontend.php';
        ia_ensure_frontend_schema($pdo);
    }
}

/**
 * @return list<array{id:int,listing_id:int,sort_order:int,media_kind:string,stored_path:string}>
 */
function ia_listing_media_list(IaPgConnection|IaPdoConnection $pdo, int $listingId): array
{
    $st = $pdo->prepare(
        'SELECT id, listing_id, sort_order, media_kind, stored_path FROM ad_listing_media WHERE listing_id = ? ORDER BY sort_order ASC, id ASC'
    );
    $st->execute([$listingId]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Строки из БД или одно legacy-фото из photo_url.
 *
 * @return list<array{id:int,listing_id:int,sort_order:int,media_kind:string,stored_path:string}>
 */
function ia_listing_media_resolved(IaPgConnection|IaPdoConnection $pdo, int $listingId, ?string $legacyPhotoUrl): array
{
    $rows = ia_listing_media_list($pdo, $listingId);
    if (count($rows) > 0) {
        return $rows;
    }
    $u = trim((string) $legacyPhotoUrl);
    if ($u !== '') {
        return [[
            'id' => 0,
            'listing_id' => $listingId,
            'sort_order' => 0,
            'media_kind' => 'image',
            'stored_path' => $u,
        ]];
    }

    return [];
}

function ia_listing_media_insert(IaPgConnection|IaPdoConnection $pdo, int $listingId, string $mediaKind, string $storedPath, int $sortOrder): void
{
    $st = $pdo->prepare(
        'INSERT INTO ad_listing_media (listing_id, sort_order, media_kind, stored_path) VALUES (?, ?, ?, ?)'
    );
    $st->execute([$listingId, $sortOrder, $mediaKind, $storedPath]);
}

function ia_listing_media_delete_row(IaPgConnection|IaPdoConnection $pdo, int $listingId, int $mediaId): bool
{
    $st = $pdo->prepare('SELECT stored_path FROM ad_listing_media WHERE id = ? AND listing_id = ? LIMIT 1');
    $st->execute([$mediaId, $listingId]);
    $path = $st->fetchColumn();
    if ($path === false) {
        return false;
    }
    ia_listing_delete_stored_file((string) $path);
    $pdo->prepare('DELETE FROM ad_listing_media WHERE id = ? AND listing_id = ?')->execute([$mediaId, $listingId]);

    return true;
}

function ia_listing_sync_primary_photo(IaPgConnection|IaPdoConnection $pdo, int $listingId): void
{
    $st = $pdo->prepare(
        "SELECT stored_path FROM ad_listing_media WHERE listing_id = ? AND media_kind = 'image' ORDER BY sort_order ASC, id ASC LIMIT 1"
    );
    $st->execute([$listingId]);
    $first = $st->fetchColumn();
    if ($first !== false && (string) $first !== '') {
        $pdo->prepare('UPDATE ad_listings SET photo_url = ? WHERE id = ?')->execute([(string) $first, $listingId]);

        return;
    }
    $pdo->prepare('UPDATE ad_listings SET photo_url = NULL WHERE id = ?')->execute([$listingId]);
}

/**
 * Удалить файлы с диска для объявления (перед DELETE объявления или при очистке).
 */
function ia_listing_purge_all_files_for_listing(IaPgConnection|IaPdoConnection $pdo, int $listingId): void
{
    foreach (ia_listing_media_list($pdo, $listingId) as $row) {
        ia_listing_delete_stored_file((string) ($row['stored_path'] ?? ''));
    }
    $st = $pdo->prepare('SELECT photo_url FROM ad_listings WHERE id = ?');
    $st->execute([$listingId]);
    $pu = $st->fetchColumn();
    if ($pu !== false) {
        ia_listing_delete_stored_file((string) $pu);
    }
}

function ia_listing_media_count(IaPgConnection|IaPdoConnection $pdo, int $listingId): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM ad_listing_media WHERE listing_id = ?');
    $st->execute([$listingId]);

    return (int) $st->fetchColumn();
}

function ia_listing_external_photo_url_valid(?string $url): bool
{
    $u = trim((string) $url);

    return $u !== '' && (bool) preg_match('#\Ahttps?://#i', $u);
}

/**
 * @param list<array{kind:string,...}> $savedItems
 *
 * @return array{images:int,videos:int}
 */
function ia_listing_count_media_kinds_from_saved(array $savedItems): array
{
    $i = 0;
    $v = 0;
    foreach ($savedItems as $sm) {
        if (($sm['kind'] ?? '') === 'video') {
            $v++;
        } else {
            $i++;
        }
    }

    return ['images' => $i, 'videos' => $v];
}

/**
 * @param list<array{media_kind:string,...}> $dbRows
 *
 * @return array{images:int,videos:int}
 */
function ia_listing_count_media_kinds_from_db_rows(array $dbRows): array
{
    $i = 0;
    $v = 0;
    foreach ($dbRows as $row) {
        if (($row['media_kind'] ?? '') === 'video') {
            $v++;
        } else {
            $i++;
        }
    }

    return ['images' => $i, 'videos' => $v];
}

/**
 * @param list<int> $removeMediaIds
 * @param list<array{kind:string,...}> $newSaved
 *
 * @return array{images:int,videos:int}
 */
function ia_listing_projected_media_counts(IaPgConnection|IaPdoConnection $pdo, int $listingId, array $removeMediaIds, array $newSaved): array
{
    $remove = array_flip(array_map(static fn (int $x): int => $x, $removeMediaIds));
    $i = 0;
    $v = 0;
    foreach (ia_listing_media_list($pdo, $listingId) as $row) {
        $rid = (int) ($row['id'] ?? 0);
        if ($rid > 0 && isset($remove[$rid])) {
            continue;
        }
        if (($row['media_kind'] ?? '') === 'video') {
            $v++;
        } else {
            $i++;
        }
    }
    $add = ia_listing_count_media_kinds_from_saved($newSaved);

    return ['images' => $i + $add['images'], 'videos' => $v + $add['videos']];
}

function ia_listing_validate_required_photo_slots(int $uploadedImages, int $receivedFiles = 0): ?string
{
    if ($uploadedImages < IA_LISTING_PHOTO_REQUIRED_COUNT) {
        return ia_listing_photo_required_missing_message($uploadedImages, $receivedFiles);
    }

    return null;
}

/**
 * @param 'in_stock'|'on_order' $availability
 */
function ia_listing_validate_media_for_availability(string $availability, int $uploadedImages, int $uploadedVideos, bool $hasExternalPhotoUrl): ?string
{
    // Медиа не является обязательной: продавец сам решает сколько фото/видео добавлять.
    // Ограничения по типам/размерам/макс. количеству контролируются на уровне загрузки.
    return null;
}

/**
 * Проверка для админки: только файлы в галерее и необязательный внешний URL обложки.
 *
 * @param 'in_stock'|'on_order' $availability
 */
function ia_listing_validate_media_for_admin_listing(IaPgConnection|IaPdoConnection $pdo, int $listingId, string $availability, string $photoUrlInput): ?string
{
    $rows = ia_listing_media_list($pdo, $listingId);
    $c = ia_listing_count_media_kinds_from_db_rows($rows);

    return ia_listing_validate_media_for_availability(
        $availability,
        $c['images'],
        $c['videos'],
        ia_listing_external_photo_url_valid($photoUrlInput)
    );
}
