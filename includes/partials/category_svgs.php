<?php

declare(strict_types=1);

/**
 * Иконки для быстрых категорий (inline SVG, 32×32).
 */
function ia_category_svg(string $key): string
{
    $common = 'width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" class="ia-cat-icon" aria-hidden="true"';
    $stroke = 'stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"';

    return match ($key) {
        'sedan' => '<svg ' . $common . '><path d="M4 20h24l-1-6-4-4H9l-4 4-1 6z" ' . $stroke . '/><circle cx="9" cy="20" r="2" ' . $stroke . '/><circle cx="23" cy="20" r="2" ' . $stroke . '/><path d="M6 14h4M16 10h4" ' . $stroke . '/></svg>',
        'suv' => '<svg ' . $common . '><path d="M3 20h26l-2-7-5-3H10L5 13l-2 7z" ' . $stroke . '/><circle cx="8" cy="20" r="2" ' . $stroke . '/><circle cx="24" cy="20" r="2" ' . $stroke . '/><path d="M5 13h5l2-3h8l2 3h5" ' . $stroke . '/></svg>',
        'hatchback' => '<svg ' . $common . '><path d="M5 20h22l-2-5-4-4H11l-4 4-2 5z" ' . $stroke . '/><circle cx="9" cy="20" r="2" ' . $stroke . '/><circle cx="23" cy="20" r="2" ' . $stroke . '/><path d="M16 11v5h6l2 4M8 15l3-3h5" ' . $stroke . '/></svg>',
        'pickup' => '<svg ' . $common . '><path d="M3 18h15v4H3zM18 18l6-6h5v10h-11z" ' . $stroke . '/><circle cx="9" cy="20" r="2" ' . $stroke . '/><circle cx="26" cy="20" r="2" ' . $stroke . '/><path d="M3 18V14h8l2 4" ' . $stroke . '/></svg>',
        'ev' => '<svg ' . $common . '><path d="M6 20c0-4 3-7 7-7h6c3 0 5-2 5-5M6 20h4M6 20v-4" ' . $stroke . '/><path d="M10 8l2 2 4-4 2 2" ' . $stroke . '/><path d="M16 6v4M20 6l-2 2" ' . $stroke . '/><circle cx="10" cy="20" r="1.5" fill="currentColor"/></svg>',
        'sport' => '<svg ' . $common . '><path d="M3 20h24l-1-5-3-3H7l-3 3-1 5z" ' . $stroke . '/><circle cx="8" cy="20" r="2" ' . $stroke . '/><circle cx="24" cy="20" r="2" ' . $stroke . '/><path d="M6 15h6M20 12l3 3" ' . $stroke . '/><path d="M12 8l2 2h4" ' . $stroke . '/></svg>',
        'motorcycle' => '<svg ' . $common . '><circle cx="9" cy="22" r="3" ' . $stroke . '/><circle cx="23" cy="22" r="3" ' . $stroke . '/><path d="M12 22h8M9 22l4-8h6l3 4 2-4h4" ' . $stroke . '/><path d="M13 14l2-4h5l2 3" ' . $stroke . '/></svg>',
        default => '<svg ' . $common . '><circle cx="16" cy="16" r="8" ' . $stroke . '/></svg>',
    };
}
