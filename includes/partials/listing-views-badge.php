<?php

declare(strict_types=1);

if (!isset($row) || !is_array($row)) {
    return;
}

ia_render_listing_views_badge($row);
