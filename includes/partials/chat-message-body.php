<?php

declare(strict_types=1);

/** @var array<string,mixed> $m */

$msgType = strtolower(trim((string) ($m['msg_type'] ?? 'text')));
$attachUrl = trim((string) ($m['attachment_path'] ?? '')) !== ''
    ? ia_chat_attachment_public_url((string) $m['attachment_path'])
    : '';

if ($msgType === 'image' && $attachUrl !== ''): ?>
    <a href="<?= ia_h($attachUrl) ?>" class="ia-chat-bubble-media ia-chat-bubble-media--image" target="_blank" rel="noopener">
        <img src="<?= ia_h($attachUrl) ?>" alt="Фото" loading="lazy" decoding="async">
    </a>
<?php elseif ($msgType === 'voice' && $attachUrl !== ''): ?>
    <div class="ia-chat-bubble-media ia-chat-bubble-media--voice">
        <audio controls preload="metadata" src="<?= ia_h($attachUrl) ?>"></audio>
    </div>
<?php elseif ($msgType === 'file' && $attachUrl !== ''): ?>
    <a href="<?= ia_h($attachUrl) ?>" class="ia-chat-bubble-file" target="_blank" rel="noopener" download>
        <span class="ia-chat-bubble-file-ico" aria-hidden="true">📎</span>
        <span class="ia-chat-bubble-file-name"><?= ia_h((string) ($m['attachment_name'] ?? 'Файл')) ?></span>
    </a>
<?php endif; ?>
<?php if (trim((string) ($m['body'] ?? '')) !== ''): ?>
    <div class="ia-chat-bubble-text"><?= nl2br(ia_h((string) ($m['body'] ?? ''))) ?></div>
<?php endif; ?>
