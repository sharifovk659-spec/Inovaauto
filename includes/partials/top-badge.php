<?php

declare(strict_types=1);

/**
 * Обёртка для TOP — использует общий блок VIP/TOP.
 * <?php $iaTopBadge = !empty($row['is_top']); require IA_ROOT . '/includes/partials/top-badge.php'; ?>
 */
$row = $row ?? [];
if (empty($iaTopBadge) && empty($row['is_top']) && empty($row['is_vip'])) {
    return;
}
ia_render_listing_card_badges($row);
