<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

/** @var array<string,mixed> $activeThread */

$tid = (int) ($activeThread['id'] ?? 0);
$emojis = ['😀', '😊', '👍', '❤️', '🙏', '😂', '😍', '🔥', '✅', '🚗', '💬', '📷', '📎', '🎤'];
?>
<div class="ia-chat-composer-wrap" id="iaChatComposerWrap">
    <div class="ia-chat-emoji-panel" id="iaChatEmojiPanel" role="listbox" aria-label="Эмодзи" hidden>
        <?php foreach ($emojis as $emo): ?>
            <button type="button" class="ia-chat-emoji-btn" data-emoji="<?= ia_h($emo) ?>" role="option"><?= $emo ?></button>
        <?php endforeach; ?>
    </div>
    <div class="ia-chat-tools-panel" id="iaChatToolsPanel" role="toolbar" aria-label="Вложения" hidden>
        <button type="button" class="ia-chat-tool-btn" id="iaChatEmojiToggle" title="Эмодзи" aria-label="Эмодзи">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16Zm-3.5 6.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm7 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM8.2 14.8a5.8 5.8 0 0 0 7.6 0 1 1 0 1 0-1.2-1.6 3.8 3.8 0 0 1-5.2 0 1 1 0 1 0-1.2 1.6Z"/></svg>
        </button>
        <label class="ia-chat-tool-btn" title="Камера" aria-label="Снять фото">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M4 7h2.5l1.5-2h8l1.5 2H20a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Zm8 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z"/></svg>
            <input type="file" form="iaChatMediaForm" id="iaChatCameraInput" accept="image/*" capture="environment" hidden data-media-kind="image">
        </label>
        <label class="ia-chat-tool-btn" title="Галерея" aria-label="Фото из галереи">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M9 3 7.2 5H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2.2L17 3H9Zm3 5a4 4 0 1 1 0 8 4 4 0 0 1 0-8Z"/></svg>
            <input type="file" form="iaChatMediaForm" id="iaChatGalleryInput" accept="image/jpeg,image/png,image/webp,image/gif,image/heic,image/heif" hidden data-media-kind="image">
        </label>
        <label class="ia-chat-tool-btn" title="Файл" aria-label="Отправить файл">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm1 2 5 5h-4a1 1 0 0 1-1-1V4ZM8 13h8v2H8v-2Zm0 4h5v2H8v-2Z"/></svg>
            <input type="file" form="iaChatMediaForm" id="iaChatFileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar,application/pdf" hidden data-media-kind="file">
        </label>
        <button type="button" class="ia-chat-tool-btn" id="iaChatVoiceBtn" title="Голосовое — удерживайте" aria-label="Записать голосовое">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 14a3 3 0 0 0 3-3V6a3 3 0 1 0-6 0v5a3 3 0 0 0 3 3Zm5-3a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.92V21h2v-3.08A7 7 0 0 0 19 11h-2Z"/></svg>
        </button>
    </div>
    <form method="post" class="ia-chat-composer" id="iaChatComposer" action="<?= ia_h(ia_public_url('messages.php?thread=' . $tid)) ?>">
        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
        <input type="hidden" name="action" value="send">
        <input type="hidden" name="thread_id" value="<?= $tid ?>">
        <button type="button" class="ia-chat-attach-btn" id="iaChatToolsToggle" aria-expanded="false" aria-controls="iaChatToolsPanel" title="Вложения" aria-label="Вложения">
            <svg class="ia-chat-attach-ico ia-chat-attach-ico--plus" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M12 5a1 1 0 0 1 1 1v5h5a1 1 0 1 1 0 2h-5v5a1 1 0 1 1-2 0v-5H6a1 1 0 1 1 0-2h5V6a1 1 0 0 1 1-1Z"/></svg>
            <svg class="ia-chat-attach-ico ia-chat-attach-ico--close" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" hidden><path fill="currentColor" d="M7.05 6.34 12 11.29l4.95-4.95 1.41 1.41L13.41 12.7l4.95 4.95-1.41 1.41L12 14.12l-4.95 4.94-1.41-1.41 4.94-4.95-4.94-4.95z"/></svg>
        </button>
        <textarea name="body" id="iaChatInput" class="ia-chat-input" rows="1" placeholder="Напишите сообщение…" maxlength="8000" autocomplete="off"></textarea>
        <button type="submit" class="ia-chat-send" aria-label="Отправить">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="m3 20 18-8L3 4l3 8-3 8Zm5-7-1.5-3.5L17.5 12 6.5 16.5 8 13l5-1-5-0Z"/></svg>
            <span class="ia-chat-send-label">Отправить</span>
        </button>
    </form>
    <form method="post" class="d-none" id="iaChatMediaForm" enctype="multipart/form-data" action="<?= ia_h(ia_public_url('messages.php?thread=' . $tid)) ?>">
        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
        <input type="hidden" name="action" value="send_media">
        <input type="hidden" name="thread_id" value="<?= $tid ?>">
        <input type="hidden" name="media_type" id="iaChatMediaType" value="">
        <input type="hidden" name="caption" id="iaChatMediaCaption" value="">
        <input type="file" name="attachment" id="iaChatMediaFile">
    </form>
    <div class="ia-chat-voice-rec" id="iaChatVoiceRec" aria-live="polite" hidden>
        <span class="ia-chat-voice-rec-dot" aria-hidden="true"></span>
        <span>Запись… отпустите для отправки</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="iaChatVoiceCancel">Отмена</button>
    </div>
</div>
