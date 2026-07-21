<?php

declare(strict_types=1);

/**
 * Register / auth welcome background (used by register.php).
 */
function ia_auth_page_app_attrs(): array
{
    $style = '';
    $url = function_exists('ia_login_welcome_background_url') ? ia_login_welcome_background_url() : '';
    if ($url !== '') {
        $style = ' style="--ia-auth-bg:url(' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . ')"';
    }

    return [
        'class' => 'ia-auth-app',
        'style' => $style,
    ];
}

function ia_auth_page_enqueue_assets(): void
{
    $cssHref = ia_stylesheet_href('assets/auth-premium.css', 'assets/auth-premium.min.css');
    $fontHref = 'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap';
    $GLOBALS['ia_extra_head_html'] = '<link href="' . htmlspecialchars($fontHref, ENT_QUOTES, 'UTF-8') . '" rel="stylesheet">'
        . '<link rel="stylesheet" href="' . htmlspecialchars($cssHref, ENT_QUOTES, 'UTF-8') . '">';
}
