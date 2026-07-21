<?php

declare(strict_types=1);

if (!defined('IA_ROOT') || $cu === null) {
    return;
}

$chatUnread = (int) ($layoutState['chat_unread'] ?? 0);
$chatVisible = $chatUnread > 0;
$messagesUrl = ia_public_url('messages.php');
?>
<style id="ia-chat-fab-pos-v1">
header .ia-chat-fab,
.ia-mobile-header .ia-chat-fab,
.ia-mobile-header-actions .ia-chat-fab {
  display: none !important;
}

body > #iaChatFab.ia-chat-fab {
  position: fixed !important;
  top: auto !important;
  left: auto !important;
  right: max(1rem, env(safe-area-inset-right, 0px)) !important;
  bottom: max(1rem, env(safe-area-inset-bottom, 0px)) !important;
  z-index: 1080 !important;
  margin: 0 !important;
}

body > #iaChatFab.ia-chat-fab:not(.ia-chat-fab--visible) {
  display: none !important;
}

body > #iaChatFab.ia-chat-fab.ia-chat-fab--visible {
  display: inline-flex !important;
}

@media (max-width: 991.98px) {
  body > #iaChatFab.ia-chat-fab {
    bottom: calc(4.75rem + env(safe-area-inset-bottom, 0px)) !important;
  }

  body.ia-page-messages > #iaChatFab.ia-chat-fab,
  body.ia-page-messages.ia-chat-open > #iaChatFab.ia-chat-fab {
    display: none !important;
  }
}
</style>
<a
    href="<?= ia_h($messagesUrl) ?>"
    class="ia-chat-fab<?= $chatVisible ? ' ia-chat-fab--visible ia-chat-fab--unread' : '' ?>"
    id="iaChatFab"
    aria-label="<?= $chatVisible ? 'Сообщения (' . (int) $chatUnread . ' новых)' : 'Сообщения' ?>"
    <?= $chatVisible ? '' : ' hidden' ?>
>
    <span class="ia-chat-fab-icon" aria-hidden="true"><i class="bi bi-chat-dots-fill"></i></span>
    <span class="ia-chat-fab-badge<?= $chatVisible ? '' : ' d-none' ?>" id="iaChatFabBadge"><?= (int) $chatUnread ?></span>
</a>
<script>
(function () {
  var fab = document.getElementById('iaChatFab');
  if (!fab || fab.parentElement === document.body) {
    return;
  }
  document.body.appendChild(fab);
})();
</script>
