<?php

declare(strict_types=1);

/** @var string|null $avatarUrl */
/** @var string $displayName */
/** @var string $extraClass */

$avatarUrl = $avatarUrl ?? null;
$displayName = trim((string) ($displayName ?? ''));
$extraClass = trim((string) ($extraClass ?? ''));
$initials = '?';
if ($displayName !== '') {
    $parts = preg_split('/\s+/u', $displayName) ?: [];
    $first = mb_strtoupper(mb_substr((string) ($parts[0] ?? ''), 0, 1));
    $second = count($parts) > 1 ? mb_strtoupper(mb_substr((string) $parts[1], 0, 1)) : '';
    $initials = $first . $second;
}
?>
<span class="ia-chat-avatar<?= $extraClass !== '' ? ' ' . ia_h($extraClass) : '' ?>"<?= $avatarUrl !== null ? '' : ' aria-hidden="true"' ?>>
    <?php if ($avatarUrl !== null): ?>
        <img src="<?= ia_h($avatarUrl) ?>" alt="" class="ia-chat-avatar-img" loading="lazy" decoding="async">
    <?php else: ?>
        <span class="ia-chat-avatar-initials"><?= ia_h($initials) ?></span>
    <?php endif; ?>
</span>
