<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_notifications.php';
require_once IA_ROOT . '/includes/admin_contact_inbox.php';

ia_require_section('content');
ia_admin_notifications_handle_post();
ia_admin_contact_inbox_handle_post();

$pdo = ia_db();
$campaigns = ia_admin_notifications_recent_campaigns($pdo, 40);
$contactInbox = ia_admin_contact_inbox_recent($pdo, 10, null);
$contactNewCount = ia_admin_contact_inbox_new_count($pdo);
$usersPick = $pdo->query('SELECT id, email, name FROM platform_users ORDER BY id DESC LIMIT 500')->fetchAll() ?: [];

$chRu = static fn (string $c): string => match ($c) {
    'push' => 'Push',
    'sms' => 'SMS',
    'email' => 'Email',
    default => $c,
};
$audRu = static fn (string $a): string => match ($a) {
    'all' => 'все',
    'group' => 'группа',
    'single' => 'один',
    default => $a,
};

$user = ia_current_user();
$pageTitle = 'Рассылки';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<main class="container-fluid px-3 px-lg-4 py-4">
    <h1 class="h4 mb-3">Уведомления (рассылки)</h1>
    <p class="text-secondary small">Email отправляется через <code>mail()</code> PHP. Push и SMS фиксируются в доставках до подключения провайдера.</p>
    <?php if ($msg = ia_flash('notif_ok')): ?><div class="alert alert-success"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <?php if ($msg = ia_flash('notif_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <?php if ($msg = ia_flash('contact_inbox_ok')): ?><div class="alert alert-success"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <?php if ($msg = ia_flash('contact_inbox_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h6 mb-0">Входящие с сайта (форма «Контакты»)</h2>
                <?php if ($contactNewCount > 0): ?>
                    <span class="badge text-bg-warning">Новых: <?= (int) $contactNewCount ?></span>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= ia_h(ia_admin_url('contact-messages.php')) ?>">Все обращения</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Имя</th>
                            <th>Email</th>
                            <th>Сообщение</th>
                            <th>Статус</th>
                            <th>Дата</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($contactInbox as $cr): ?>
                        <?php
                        $cm = (string) ($cr['message'] ?? '');
                        $cmShort = mb_strlen($cm) > 100 ? mb_substr($cm, 0, 97) . '…' : $cm;
                        $cst = (string) ($cr['status'] ?? 'new');
                        ?>
                        <tr>
                            <td><?= ia_h((string) ($cr['from_name'] ?? '')) ?></td>
                            <td class="small"><?php
                                $ce = trim((string) ($cr['from_email'] ?? ''));
                                echo $ce !== '' ? ia_h($ce) : '—';
                            ?></td>
                            <td class="small"><?= ia_h($cmShort) ?></td>
                            <td><span class="badge <?= $cst === 'new' ? 'text-bg-warning' : 'text-bg-secondary' ?>"><?= ia_h(ia_admin_contact_inbox_status_label_ru($cst)) ?></span></td>
                            <td class="small text-nowrap"><?= ia_h((string) ($cr['created_at'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($contactInbox) === 0): ?>
                        <tr><td colspan="5" class="text-secondary">Пока нет обращений.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">Новая рассылка</h2>
            <form method="post" class="row g-3">
                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                <input type="hidden" name="action" value="send">
                <div class="col-md-3">
                    <label class="form-label">Канал</label>
                    <select name="channel" class="form-select" required>
                        <option value="email">Email</option>
                        <option value="push">Push</option>
                        <option value="sms">SMS</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Аудитория</label>
                    <select name="audience" class="form-select" id="iaAudience" required>
                        <option value="all">Все пользователи</option>
                        <option value="group">Группа</option>
                        <option value="single">Один пользователь</option>
                    </select>
                </div>
                <div class="col-md-3" id="wrapGroup">
                    <label class="form-label">Группа</label>
                    <select name="group_key" class="form-select">
                        <option value="dealer">Дилеры</option>
                        <option value="private">Частные</option>
                        <option value="active">Активные аккаунты</option>
                    </select>
                </div>
                <div class="col-md-3 d-none" id="wrapSingle">
                    <label class="form-label">Пользователь</label>
                    <select name="target_user_id" class="form-select">
                        <option value="0">—</option>
                        <?php foreach ($usersPick as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"><?= ia_h((string) $u['email']) ?> (<?= ia_h((string) $u['name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Тема (для email)</label>
                    <input type="text" name="subject" class="form-control" maxlength="255" placeholder="Тема письма">
                </div>
                <div class="col-12">
                    <label class="form-label">Текст</label>
                    <textarea name="body" class="form-control" rows="5" required placeholder="Текст сообщения"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Отправить</button>
                </div>
            </form>
        </div>
    </div>

    <h2 class="h6 text-uppercase text-secondary mb-2">Последние кампании</h2>
    <div class="table-responsive">
        <table class="table table-sm table-striped">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Канал</th>
                    <th>Аудитория</th>
                    <th>Тема</th>
                    <th>Админ</th>
                    <th>Дата</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($campaigns as $c): ?>
                <tr>
                    <td><?= (int) $c['id'] ?></td>
                    <td><?= ia_h($chRu((string) ($c['channel'] ?? ''))) ?></td>
                    <td><?= ia_h($audRu((string) ($c['audience'] ?? ''))) ?></td>
                    <td class="small"><?= ia_h((string) ($c['subject'] ?? '')) ?></td>
                    <td class="small"><?= ia_h((string) ($c['admin_username'] ?? '—')) ?></td>
                    <td class="small"><?= ia_h((string) ($c['created_at'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($campaigns) === 0): ?>
                <tr><td colspan="6" class="text-secondary">Пока нет рассылок.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<script>
(function () {
  const aud = document.getElementById('iaAudience');
  const wg = document.getElementById('wrapGroup');
  const ws = document.getElementById('wrapSingle');
  function sync() {
    const v = aud.value;
    wg.classList.toggle('d-none', v !== 'group');
    ws.classList.toggle('d-none', v !== 'single');
  }
  aud.addEventListener('change', sync);
  sync();
})();
</script>
<?php require __DIR__ . '/partials/foot.php'; ?>
