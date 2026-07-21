<?php

declare(strict_types=1);

/** @var string $tickStatus 'read' | 'sent' */
/** @var string $extraClass optional extra classes */

$tickStatus = (string) ($tickStatus ?? 'sent');
$extraClass = trim((string) ($extraClass ?? ''));
if ($tickStatus !== 'read' && $tickStatus !== 'sent') {
    return;
}
$isRead = $tickStatus === 'read';
$label = $isRead ? 'Прочитано' : 'Доставлено';
?>
<span class="ia-chat-tick<?= $isRead ? ' ia-chat-tick--read' : '' ?><?= $extraClass !== '' ? ' ' . ia_h($extraClass) : '' ?>" aria-label="<?= ia_h($label) ?>" title="<?= ia_h($label) ?>">
    <svg viewBox="0 0 18 18" width="14" height="14" aria-hidden="true">
        <?php if ($isRead): ?>
            <path d="M1 9.5l3 3 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M5 9.5l3 3 9-9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <?php else: ?>
            <path d="M3 9.5l3.5 3.5L15 4.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <?php endif; ?>
    </svg>
</span>
