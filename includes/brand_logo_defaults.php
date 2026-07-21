<?php

declare(strict_types=1);

/**
 * Built-in SVG logos when CDN has no icon (e.g. Lexus) or local file missing on host.
 */
function ia_brand_builtin_logo_svg(string $slug): ?string
{
    return match ($slug) {
        'lexus' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" role="img" aria-label="Lexus">
  <defs>
    <linearGradient id="lx-chrome" x1="22" y1="18" x2="106" y2="110" gradientUnits="userSpaceOnUse">
      <stop offset="0%" stop-color="#f8fafc"/>
      <stop offset="12%" stop-color="#cbd5e1"/>
      <stop offset="32%" stop-color="#64748b"/>
      <stop offset="55%" stop-color="#1e293b"/>
      <stop offset="78%" stop-color="#94a3b8"/>
      <stop offset="100%" stop-color="#e2e8f0"/>
    </linearGradient>
    <linearGradient id="lx-chrome-hi" x1="30" y1="28" x2="98" y2="96" gradientUnits="userSpaceOnUse">
      <stop offset="0%" stop-color="#ffffff" stop-opacity="0.85"/>
      <stop offset="100%" stop-color="#ffffff" stop-opacity="0"/>
    </linearGradient>
    <linearGradient id="lx-l-mark" x1="40" y1="36" x2="92" y2="92" gradientUnits="userSpaceOnUse">
      <stop offset="0%" stop-color="#64748b"/>
      <stop offset="45%" stop-color="#334155"/>
      <stop offset="100%" stop-color="#0f172a"/>
    </linearGradient>
  </defs>
  <g transform="rotate(-11 64 64)">
    <ellipse cx="64" cy="64" rx="49" ry="30.5" fill="none" stroke="url(#lx-chrome)" stroke-width="4.6"/>
    <ellipse cx="64" cy="64" rx="49" ry="30.5" fill="none" stroke="url(#lx-chrome-hi)" stroke-width="1.4"/>
  </g>
  <path d="M41.5 39.5V84.5H87.5" fill="none" stroke="url(#lx-l-mark)" stroke-width="8.8" stroke-linecap="square" stroke-linejoin="miter"/>
</svg>
SVG,
        'byd' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" role="img" aria-label="BYD">
  <text x="64" y="78" text-anchor="middle" transform="skewX(-7)" font-family="Arial Black, Impact, Helvetica Neue, sans-serif" font-size="44" font-weight="900" fill="#c41230" letter-spacing="-1.5">BYD</text>
</svg>
SVG,
        'zeekr' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" role="img" aria-label="Zeekr">
  <path fill="#1f2937" d="M16 46h58l12 12H28z"/>
  <path fill="#1f2937" d="M42 70h58l12 12H54z"/>
</svg>
SVG,
        'liauto' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" role="img" aria-label="Li Auto">
  <rect x="28" y="28" width="72" height="72" rx="16" fill="#111827"/>
  <path fill="#ffffff" d="M44 86V48h8v30h28v8H44z"/>
</svg>
SVG,
        'opel' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" role="img" aria-label="Opel">
  <circle cx="64" cy="64" r="42" fill="none" stroke="#1f2937" stroke-width="7"/>
  <path fill="none" stroke="#1f2937" stroke-width="7" stroke-linecap="square" stroke-linejoin="miter" d="M30 64h18l8-14 8 28 8-28 8 14h18"/>
</svg>
SVG,
        default => null,
    };
}

/** Slugs with no reliable public CDN icon — must use local/builtin SVG. */
function ia_brand_cdn_unsupported_slugs(): array
{
    return ['lexus', 'byd', 'zeekr', 'liauto', 'opel'];
}
