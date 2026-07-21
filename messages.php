<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/public_queries.php';
require_once IA_ROOT . '/includes/chat_media.php';
require_once IA_ROOT . '/includes/user_avatar.php';

ia_platform_require_login();

$pdo = ia_db();
$uid = (int) ia_platform_current_user()['id'];
$threads = ia_pub_threads_for_user($pdo, $uid);
ia_pub_mark_all_threads_seen($pdo, $uid);

$threadId = ia_get_int('thread');
$activeThread = null;
$messages = [];
$seenMap = isset($_SESSION['chat_seen']) && is_array($_SESSION['chat_seen']) ? $_SESSION['chat_seen'] : [];

if ($threadId > 0) {
    $activeThread = ia_pub_thread_for_participant($pdo, $threadId, $uid);
    if ($activeThread !== null) {
        $messages = ia_pub_messages_for_thread($pdo, $threadId);
        ia_pub_mark_thread_seen($pdo, $threadId, $uid);
        $ts = (string) time();
        $_SESSION['chat_seen'][(string) $threadId] = $ts;
        $seenMap[(string) $threadId] = $ts;
    } else {
        $threadId = 0;
    }
}
$activeLastId = $threadId > 0 ? ia_pub_thread_last_message_id($pdo, $threadId) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    $postTid = ia_post_int('thread_id');
    $back = ia_public_url('messages.php' . ($postTid > 0 ? '?thread=' . $postTid : ''));
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('pub_error', 'Сессия устарела. Обновите страницу.');
    } else {
        $body = ia_input_long_text($_POST['body'] ?? '', 8000);
        $threadErr = ia_pub_chat_thread_send_error($pdo, $postTid, $uid);
        if ($threadErr !== null) {
            ia_flash('pub_error', $threadErr);
        } elseif ($body === '' || mb_strlen($body) > 8000) {
            ia_flash('pub_error', 'Сообщение пустое или слишком длинное (до 8000 символов).');
        } elseif (!ia_pub_send_chat_message($pdo, $postTid, $uid, $body)) {
            ia_flash('pub_error', 'Не удалось отправить сообщение.');
        } else {
            ia_redirect(ia_public_url('messages.php?thread=' . $postTid));
        }
    }
    ia_redirect($back);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_media') {
    $postTid = ia_post_int('thread_id');
    $back = ia_public_url('messages.php' . ($postTid > 0 ? '?thread=' . $postTid : ''));
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('pub_error', 'Сессия устарела. Обновите страницу.');
    } else {
        $mediaType = ia_input_enum($_POST['media_type'] ?? '', ['image', 'voice', 'file']);
        $caption = ia_input_text($_POST['caption'] ?? '', 500);
        $file = $_FILES['attachment'] ?? null;
        $threadErr = ia_pub_chat_thread_send_error($pdo, $postTid, $uid);
        if ($threadErr !== null) {
            ia_flash('pub_error', $threadErr);
        } elseif (!is_array($file)) {
            ia_flash('pub_error', 'Файл не выбран.');
        } elseif ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            ia_flash('pub_error', ia_chat_upload_error_message((int) $file['error']));
        } else {
            $kind = $mediaType === 'image' ? 'image' : ($mediaType === 'voice' ? 'voice' : 'file');
            $saved = ia_chat_save_upload($file, $postTid, $uid, $kind);
            if ($saved === null) {
                $err = match ($kind) {
                    'image' => 'Не удалось отправить фото. JPG, PNG, WEBP или GIF до 8 МБ.',
                    'voice' => 'Не удалось отправить голосовое. Удерживайте кнопку микрофона.',
                    default => 'Не удалось отправить файл (до 15 МБ).',
                };
                ia_flash('pub_error', $err);
            } elseif (!ia_pub_send_chat_media(
                $pdo,
                $postTid,
                $uid,
                $kind,
                (string) $saved['path'],
                (string) $saved['name'],
                (string) $saved['mime'],
                $caption
            )) {
                ia_chat_delete_attachment((string) $saved['path']);
                ia_flash('pub_error', 'Не удалось сохранить сообщение. Обновите страницу и попробуйте снова.');
            } else {
                ia_redirect(ia_public_url('messages.php?thread=' . $postTid));
            }
        }
    }
    ia_redirect($back);
}

$pageTitle = 'Сообщения';
$iaBodyExtraClass = 'ia-page-messages' . ($threadId > 0 ? ' ia-chat-open' : '');

$readStatus = static function (array $t, int $uid): string {
    $lastSender = (int) ($t['last_sender_id'] ?? 0);
    if ($lastSender !== $uid) {
        return '';
    }
    $lastAt = strtotime((string) ($t['last_at'] ?? '')) ?: 0;
    $peerSeen = strtotime((string) ($t['peer_last_seen_at'] ?? '')) ?: 0;

    return $peerSeen >= $lastAt && $lastAt > 0 ? 'read' : 'sent';
};

$messageReadStatus = static function (bool $mine, string $createdAt, int $peerSeenTs): string {
    if (!$mine) {
        return '';
    }
    $msgTs = strtotime($createdAt) ?: 0;

    return $msgTs > 0 && $peerSeenTs >= $msgTs ? 'read' : 'sent';
};

$shortTime = static function (?string $iso): string {
    $ts = $iso !== null ? (strtotime($iso) ?: 0) : 0;
    if ($ts <= 0) {
        return '';
    }
    $now = time();
    $today = strtotime(date('Y-m-d', $now));
    $yesterday = $today - 86400;
    $msgDay = strtotime(date('Y-m-d', $ts));
    if ($msgDay === $today) {
        return date('H:i', $ts);
    }
    if ($msgDay === $yesterday) {
        return 'Вчера';
    }
    if ($ts >= $today - 6 * 86400) {
        $weekDays = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];

        return $weekDays[(int) date('w', $ts)] ?? date('d.m', $ts);
    }

    return date('d.m.Y', $ts);
};

$messageTime = static function (?string $iso): string {
    $ts = $iso !== null ? (strtotime($iso) ?: 0) : 0;
    if ($ts <= 0) {
        return '';
    }

    return date('H:i', $ts);
};

$messageDateLabel = static function (int $ts): string {
    $today = strtotime(date('Y-m-d'));
    $yesterday = $today - 86400;
    $msgDay = strtotime(date('Y-m-d', $ts));
    if ($msgDay === $today) {
        return 'Сегодня';
    }
    if ($msgDay === $yesterday) {
        return 'Вчера';
    }
    $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $m = $months[(int) date('n', $ts) - 1] ?? '';
    if ((int) date('Y', $ts) === (int) date('Y')) {
        return (int) date('j', $ts) . ' ' . $m;
    }

    return (int) date('j', $ts) . ' ' . $m . ' ' . date('Y', $ts);
};

require IA_ROOT . '/includes/partials/site-header.php';
?>

<section class="ia-chat-page py-4 py-md-4 ia-page-section">
    <div class="container ia-container">
        <h1 class="ia-chat-title h4 mb-3">Сообщения</h1>
        <?php if ($msg = ia_flash('pub_error')): ?>
            <div class="alert alert-danger"><?= ia_h((string) $msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = ia_flash('pub_ok')): ?>
            <div class="alert alert-success"><?= ia_h((string) $msg) ?></div>
        <?php endif; ?>

        <div class="ia-chat-shell<?= $activeThread !== null ? ' ia-chat-shell--has-active' : '' ?>">
            <aside class="ia-chat-sidebar">
                <div class="ia-chat-sidebar-head">
                    <div class="ia-chat-sidebar-title">Диалоги</div>
                    <div class="ia-chat-sidebar-counter"><?= count($threads) ?></div>
                </div>
                <div class="ia-chat-thread-list">
                    <?php if (count($threads) === 0): ?>
                        <div class="ia-chat-empty-list">
                            <div class="ia-chat-empty-list-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="28" height="28"><path fill="currentColor" d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H8l-4 4V6a2 2 0 0 1 2-2Z"/></svg>
                            </div>
                            <div class="ia-chat-empty-title">Пока нет переписок</div>
                            <div class="ia-chat-empty-text">Откройте объявление и напишите продавцу — диалог появится здесь.</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($threads as $t): ?>
                            <?php
                            $tid = (int) $t['id'];
                            $title = trim((string) ($t['listing_brand'] ?? '') . ' ' . (string) ($t['listing_model'] ?? ''));
                            if ($title === '') {
                                $title = 'Объявление #' . (int) ($t['listing_id'] ?? 0);
                            }
                            $isActive = $threadId === $tid;
                            $seenAt = (int) ($seenMap[(string) $tid] ?? 0);
                            $lastAt = strtotime((string) ($t['last_at'] ?? '')) ?: 0;
                            $isUnread = !$isActive && (int) ($t['last_sender_id'] ?? 0) !== $uid && $lastAt > $seenAt;
                            $peer = ia_pub_chat_peer_profile($t, $uid);
                            $name = $peer['name'];
                            $rs = $readStatus($t, $uid);
                            $lastBody = trim((string) ($t['last_body'] ?? ''));
                            ?>
                            <a href="<?= ia_h(ia_public_url('messages.php?thread=' . $tid)) ?>" class="ia-chat-thread<?= $isActive ? ' is-active' : '' ?><?= $isUnread ? ' is-unread' : '' ?>">
                                <?php
                                $avatarUrl = $peer['avatar_url'];
                                $displayName = $name;
                                $extraClass = '';
                                require IA_ROOT . '/includes/partials/chat-avatar.php';
                                ?>
                                <span class="ia-chat-thread-body">
                                    <span class="ia-chat-thread-row">
                                        <span class="ia-chat-thread-name"><?= ia_h($name) ?></span>
                                        <span class="ia-chat-thread-time"><?= ia_h($shortTime((string) ($t['last_at'] ?? ''))) ?></span>
                                    </span>
                                    <span class="ia-chat-thread-listing"><?= ia_h($title) ?></span>
                                    <span class="ia-chat-thread-row">
                                        <?php if ($lastBody !== ''): ?>
                                            <span class="ia-chat-thread-preview">
                                                <?php if ((int) ($t['last_sender_id'] ?? 0) === $uid): ?>
                                                    <span class="ia-chat-thread-prefix">Вы:</span>
                                                <?php endif; ?>
                                                <?= ia_h(mb_strlen($lastBody) > 60 ? mb_substr($lastBody, 0, 60) . '…' : $lastBody) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="ia-chat-thread-preview ia-chat-thread-preview--muted">Нет сообщений</span>
                                        <?php endif; ?>
                                        <?php if ($isUnread): ?>
                                            <span class="ia-chat-thread-badge" aria-label="Новое сообщение">●</span>
                                        <?php elseif ($rs !== ''): ?>
                                            <?php $tickStatus = $rs; require IA_ROOT . '/includes/partials/chat-tick.php'; ?>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>

            <main class="ia-chat-main<?= $activeThread !== null ? ' ia-chat-main--active' : '' ?>">
                <?php if ($activeThread === null): ?>
                    <div class="ia-chat-placeholder">
                        <div class="ia-chat-placeholder-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="44" height="44"><path fill="currentColor" d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H8l-4 4V6a2 2 0 0 1 2-2Z"/></svg>
                        </div>
                        <div class="ia-chat-placeholder-title">
                            <?= count($threads) === 0 ? 'Здесь появятся ваши сообщения' : 'Выберите диалог слева' ?>
                        </div>
                        <div class="ia-chat-placeholder-text">
                            <?= count($threads) === 0 ? 'Найдите интересующее объявление и нажмите «Написать продавцу».' : 'Кликните на собеседника, чтобы открыть переписку.' ?>
                        </div>
                        <a class="btn ia-btn-accent mt-3" href="<?= ia_h(ia_public_url('catalog.php')) ?>">Перейти в каталог</a>
                    </div>
                <?php else: ?>
                    <?php
                    $lb = trim((string) ($activeThread['listing_brand'] ?? ''));
                    $lm = trim((string) ($activeThread['listing_model'] ?? ''));
                    $lid = (int) ($activeThread['listing_id'] ?? 0);
                    $ltitle = trim($lb . ' ' . $lm);
                    if ($ltitle === '') {
                        $ltitle = '#' . $lid;
                    }
                    $activePeer = ia_pub_chat_peer_profile($activeThread, $uid);
                    $activePeerName = $activePeer['name'];
                    $activePeerSeenTs = strtotime((string) ($activeThread['peer_last_seen_at'] ?? '')) ?: 0;
                    ?>
                    <header class="ia-chat-head">
                        <a class="ia-chat-back" href="<?= ia_h(ia_public_url('messages.php')) ?>" aria-label="Назад к диалогам">
                            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M15.4 6.6 14 5.2 7.2 12l6.8 6.8 1.4-1.4L10.05 12z"/></svg>
                        </a>
                        <?php
                        $avatarUrl = $activePeer['avatar_url'];
                        $displayName = $activePeerName;
                        $extraClass = 'ia-chat-head-avatar';
                        require IA_ROOT . '/includes/partials/chat-avatar.php';
                        ?>
                        <div class="ia-chat-head-info">
                            <div class="ia-chat-head-name"><?= ia_h($activePeerName) ?></div>
                            <div class="ia-chat-head-sub">
                                <span class="ia-chat-head-listing"><?= ia_h($ltitle) ?></span>
                            </div>
                        </div>
                        <?php if ($lid > 0): ?>
                            <a class="ia-chat-head-action" href="<?= ia_h(ia_public_url('car.php?id=' . $lid)) ?>" title="Открыть объявление">
                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M14 3v2h3.6l-9.3 9.3 1.4 1.4L19 6.4V10h2V3zM5 5h6v2H6v11h11v-5h2v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z"/></svg>
                                <span>Объявление</span>
                            </a>
                        <?php endif; ?>
                    </header>

                    <div class="ia-chat-body" id="iaChatBody">
                        <?php
                        $prevDay = '';
                        if (count($messages) === 0): ?>
                            <div class="ia-chat-empty">
                                <div class="ia-chat-empty-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="40" height="40"><path fill="currentColor" d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H8l-4 4V6a2 2 0 0 1 2-2Z"/></svg>
                                </div>
                                <div class="ia-chat-empty-title">Напишите первое сообщение</div>
                                <div class="ia-chat-empty-text">Спросите о состоянии, цене или договоритесь о встрече.</div>
                            </div>
                        <?php endif; ?>
                        <?php foreach ($messages as $m): ?>
                            <?php
                            $mine = (int) ($m['sender_id'] ?? 0) === $uid;
                            $messageId = (int) ($m['id'] ?? 0);
                            $createdAt = (string) ($m['created_at'] ?? '');
                            $ts = strtotime($createdAt) ?: 0;
                            $dayKey = $ts > 0 ? date('Y-m-d', $ts) : '';
                            $senderName = (string) ($m['sender_name'] ?? '');
                            ?>
                            <?php if ($dayKey !== '' && $dayKey !== $prevDay): ?>
                                <div class="ia-chat-day-sep"><span><?= ia_h($messageDateLabel($ts)) ?></span></div>
                                <?php $prevDay = $dayKey; ?>
                            <?php endif; ?>
                            <div class="ia-chat-msg ia-chat-msg--<?= $mine ? 'mine' : 'theirs' ?>">
                                <?php if (!$mine): ?>
                                    <?php
                                    $senderAvatar = ia_user_avatar_src(
                                        trim((string) ($m['sender_avatar_path'] ?? '')) !== ''
                                            ? (string) $m['sender_avatar_path']
                                            : null
                                    );
                                    if ($senderAvatar === null) {
                                        $senderAvatar = $activePeer['avatar_url'];
                                    }
                                    $avatarUrl = $senderAvatar;
                                    $displayName = $senderName !== '' ? $senderName : $activePeerName;
                                    $extraClass = 'ia-chat-msg-avatar';
                                    require IA_ROOT . '/includes/partials/chat-avatar.php';
                                    ?>
                                <?php endif; ?>
                                <div class="ia-chat-bubble">
                                    <?php require IA_ROOT . '/includes/partials/chat-message-body.php'; ?>
                                    <div class="ia-chat-bubble-meta">
                                        <span class="ia-chat-bubble-time"><?= ia_h($messageTime($createdAt)) ?></span>
                                        <?php if ($mine): ?>
                                            <?php
                                            $tickStatus = $messageReadStatus(true, $createdAt, $activePeerSeenTs);
                                            $extraClass = 'ia-chat-bubble-tick';
                                            require IA_ROOT . '/includes/partials/chat-tick.php';
                                            ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php require IA_ROOT . '/includes/partials/chat-composer.php'; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>
</section>

<?php if ($threadId > 0): ?>
<script src="<?= ia_h(ia_script_href('assets/chat.js', 'assets/chat.min.js')) ?>" defer></script>
<script defer>
window.iaChatInit({
  threadId: <?= (int) $threadId ?>,
  lastId: <?= (int) $activeLastId ?>,
  pollUrl: <?= json_encode(ia_public_url('chat-poll.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
});
</script>
<?php endif; ?>
<?php require IA_ROOT . '/includes/partials/site-footer.php'; ?>
