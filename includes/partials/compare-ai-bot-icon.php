<?php

declare(strict_types=1);

/** @var string $iaBotSvgUid */
/** @var int $iaBotSvgSize */

$uid = isset($iaBotSvgUid) && $iaBotSvgUid !== '' ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $iaBotSvgUid) : 'iacmpbot';
$size = isset($iaBotSvgSize) ? max(24, min(72, (int) $iaBotSvgSize)) : 36;
?>
<svg
    class="ia-compare-ai-bot-mascot"
    viewBox="0 0 64 72"
    width="<?= (int) $size ?>"
    height="<?= (int) round($size * 72 / 64) ?>"
    aria-hidden="true"
    focusable="false"
>
    <defs>
        <linearGradient id="<?= ia_h($uid) ?>-shell" x1="18" y1="8" x2="46" y2="62" gradientUnits="userSpaceOnUse">
            <stop offset="0%" stop-color="#eef6ff"/>
            <stop offset="38%" stop-color="#5b9dff"/>
            <stop offset="100%" stop-color="#2446b8"/>
        </linearGradient>
        <linearGradient id="<?= ia_h($uid) ?>-screen" x1="18" y1="18" x2="46" y2="36" gradientUnits="userSpaceOnUse">
            <stop offset="0%" stop-color="#1e293b"/>
            <stop offset="100%" stop-color="#0b1220"/>
        </linearGradient>
        <radialGradient id="<?= ia_h($uid) ?>-glow" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(32 26) scale(14)">
            <stop offset="0%" stop-color="#67e8f9" stop-opacity="0.55"/>
            <stop offset="100%" stop-color="#67e8f9" stop-opacity="0"/>
        </radialGradient>
        <filter id="<?= ia_h($uid) ?>-shadow" x="-20%" y="-20%" width="140%" height="140%">
            <feDropShadow dx="0" dy="3" stdDeviation="2.5" flood-color="#0f172a" flood-opacity="0.28"/>
        </filter>
    </defs>

    <ellipse class="ia-compare-ai-bot-mascot-shadow" cx="32" cy="67" rx="17" ry="3.5" fill="rgba(15,23,42,0.22)"/>

    <g class="ia-compare-ai-bot-mascot-body" filter="url(#<?= ia_h($uid) ?>-shadow)">
        <rect x="20" y="40" width="24" height="20" rx="7" fill="url(#<?= ia_h($uid) ?>-shell)"/>
        <rect x="24" y="44" width="16" height="10" rx="4" fill="rgba(255,255,255,0.18)"/>
        <path class="ia-compare-ai-bot-mascot-spark" d="M32 46.5 33.1 49.8 36.4 50.9 33.1 52 32 55.3 30.9 52 27.6 50.9 30.9 49.8Z" fill="#fde68a"/>

        <rect x="15" y="14" width="34" height="28" rx="11" fill="url(#<?= ia_h($uid) ?>-shell)"/>
        <rect x="19" y="18" width="26" height="18" rx="7" fill="url(#<?= ia_h($uid) ?>-screen)"/>
        <circle cx="32" cy="27" r="11" fill="url(#<?= ia_h($uid) ?>-glow)" class="ia-compare-ai-bot-mascot-face-glow"/>

        <circle class="ia-compare-ai-bot-eye ia-compare-ai-bot-eye--left" cx="26" cy="27" r="3.2" fill="#22d3ee"/>
        <circle class="ia-compare-ai-bot-eye ia-compare-ai-bot-eye--right" cx="38" cy="27" r="3.2" fill="#22d3ee"/>
        <circle class="ia-compare-ai-bot-eye-shine" cx="27.1" cy="25.9" r="1" fill="#fff" opacity="0.85"/>
        <circle class="ia-compare-ai-bot-eye-shine" cx="39.1" cy="25.9" r="1" fill="#fff" opacity="0.85"/>

        <path d="M24 33.5c2.2 1.4 13.8 1.4 16 0" stroke="#7dd3fc" stroke-width="1.6" stroke-linecap="round" fill="none" opacity="0.85"/>

        <line class="ia-compare-ai-bot-antenna" x1="32" y1="14" x2="32" y2="6" stroke="#bfdbfe" stroke-width="2.2" stroke-linecap="round"/>
        <circle class="ia-compare-ai-bot-antenna-tip" cx="32" cy="5" r="3.2" fill="#fde047"/>
    </g>
</svg>
