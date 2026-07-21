<?php

declare(strict_types=1);

if (!isset($row) || !is_array($row)) {
    return;
}

$viewsN = ia_listing_views_count($row);
$viewsLabel = ia_listing_views_label_ru($viewsN);
?>
<div class="ia-listing-card-foot">
    <span class="ia-listing-views-footer" title="<?= ia_h($viewsLabel) ?>" aria-label="<?= ia_h($viewsLabel) ?>">
        <i class="bi bi-eye-fill" aria-hidden="true"></i>
        <span><?= ia_h($viewsLabel) ?></span>
    </span>
</div>
