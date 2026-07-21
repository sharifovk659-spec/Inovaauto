<?php

declare(strict_types=1);

/** Встроенный премиум-логотип InnovaAuto (SVG, без внешних файлов — всегда виден в шапке). */
?>
<svg class="ia-brand-wordmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 232 48" width="232" height="48" role="img" aria-hidden="true" focusable="false">
  <defs>
    <linearGradient id="iaLogoMarkGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#1e3a8a"/>
      <stop offset="48%" stop-color="#3d7eff"/>
      <stop offset="100%" stop-color="#6d28d9"/>
    </linearGradient>
    <linearGradient id="iaLogoMarkGlow" x1="0%" y1="0%" x2="0%" y2="100%">
      <stop offset="0%" stop-color="#ffffff" stop-opacity="0.28"/>
      <stop offset="100%" stop-color="#ffffff" stop-opacity="0"/>
    </linearGradient>
    <linearGradient id="iaLogoAccentGrad" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%" stop-color="#3d7eff"/>
      <stop offset="100%" stop-color="#8b5cf6"/>
    </linearGradient>
    <filter id="iaLogoShadow" x="-20%" y="-20%" width="140%" height="140%">
      <feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#1e3a8a" flood-opacity="0.22"/>
    </filter>
  </defs>
  <g filter="url(#iaLogoShadow)">
    <rect x="0" y="4" width="40" height="40" rx="11" fill="url(#iaLogoMarkGrad)"/>
    <rect x="0" y="4" width="40" height="18" rx="11" fill="url(#iaLogoMarkGlow)"/>
    <text x="20" y="31" text-anchor="middle" font-family="'DM Sans', system-ui, sans-serif" font-size="14.5" font-weight="700" fill="#ffffff" letter-spacing="-0.5">IA</text>
  </g>
  <text x="50" y="32" font-family="'DM Sans', system-ui, sans-serif" font-size="21.5" font-weight="700" letter-spacing="-0.45">
    <tspan class="ia-brand-wordmark-innova">Innova</tspan><tspan class="ia-brand-wordmark-auto" fill="url(#iaLogoAccentGrad)">Auto</tspan>
  </text>
</svg>
