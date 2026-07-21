<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/admin_db_browser.php';

ia_require_section('database');

$pdo = ia_db();
$driverLabel = ia_admin_db_driver_label($pdo);
$dbInfo = ia_db_connection_info();
$dbName = (string) ($dbInfo['name'] ?? '');
$tables = ia_admin_db_list_tables($pdo);

$table = ia_input_text($_GET['table'] ?? '', 64);
if ($table !== '' && !ia_db_sql_ident_ok($table)) {
    $table = '';
}
$page = ia_get_int('page', 1, 1);
$perPage = 50;
$offset = ($page - 1) * $perPage;

$pageData = null;
if ($table !== '') {
    $pageData = ia_admin_db_fetch_table_page($pdo, $table, $perPage, $offset);
}

$user = ia_current_user();
$pageTitle = 'База данных';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<main class="container-fluid px-3 px-lg-4 py-4">
    <h1 class="h4 mb-2">Просмотр базы данных</h1>
    <p class="mb-3"><a class="btn btn-sm btn-outline-primary" href="<?= ia_h(ia_admin_url('db-status.php')) ?>">Статус Supabase / пайвастшавӣ</a></p>
    <p class="text-secondary small mb-4">
        Драйвер: <strong><?= ia_h($driverLabel) ?></strong>
        <?php if (!empty($dbInfo['is_supabase'])): ?>
            · <span class="text-success">Supabase</span>
        <?php endif; ?>
        <?php if ($dbName !== ''): ?> · База: <code><?= ia_h($dbName) ?></code><?php endif; ?>
        <?php if (!empty($dbInfo['host'])): ?> · Хост: <code><?= ia_h((string) $dbInfo['host']) ?></code><?php endif; ?>
        <br>
        Только чтение: список таблиц текущей схемы и до <?= (int) $perPage ?> строк на страницу. Произвольный SQL недоступен.
    </p>

    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent py-3">
                    <h2 class="h6 mb-0">Таблицы (<?= count($tables) ?>)</h2>
                </div>
                <div class="list-group list-group-flush" style="max-height: 70vh; overflow-y: auto;">
                    <?php foreach ($tables as $t): ?>
                        <?php $tn = (string) $t['name']; ?>
                        <a class="list-group-item list-group-item-action small<?= $tn === $table ? ' active' : '' ?>"
                           href="<?= ia_h(ia_admin_url('database.php?table=' . rawurlencode($tn))) ?>">
                            <?= ia_h($tn) ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if (count($tables) === 0): ?>
                        <div class="list-group-item text-secondary small">Таблицы не найдены.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-9">
            <?php if ($table === ''): ?>
                <div class="alert alert-info mb-0">Выберите таблицу слева, чтобы увидеть строки в браузере.</div>
            <?php elseif ($pageData !== null && !empty($pageData['error'])): ?>
                <div class="alert alert-danger"><?= ia_h((string) $pageData['error']) ?></div>
            <?php elseif ($pageData !== null && $pageData['ok']): ?>
                <?php
                $hasMore = (bool) ($pageData['has_more'] ?? false);
                $rows = $pageData['rows'];
                $cols = $pageData['columns'];
                ?>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h2 class="h6 mb-0">Таблица <code><?= ia_h($table) ?></code></h2>
                    <span class="small text-secondary">Страница <?= (int) $page ?> · Строк на странице: <?= count($rows) ?><?= $hasMore ? ' · есть ещё' : '' ?></span>
                </div>
                <?php if ($page > 1 || $hasMore): ?>
                    <nav class="mb-3" aria-label="Страницы">
                        <ul class="pagination pagination-sm mb-0 flex-wrap">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= ia_h(ia_admin_url('database.php?table=' . rawurlencode($table) . '&page=' . ($page - 1))) ?>">Назад</a>
                                </li>
                            <?php endif; ?>
                            <?php if ($hasMore): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= ia_h(ia_admin_url('database.php?table=' . rawurlencode($table) . '&page=' . ($page + 1))) ?>">Вперёд</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
                <div class="table-responsive border rounded shadow-sm" style="max-height: 65vh; overflow: auto;">
                    <table class="table table-sm table-striped table-hover mb-0" style="font-size: 0.8rem;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <?php foreach ($cols as $c): ?>
                                    <th scope="col" class="text-nowrap"><?= ia_h($c) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <?php foreach ($cols as $c): ?>
                                        <td class="text-break"><?php
                                            $v = $r[$c] ?? null;
                                            if ($v === null) {
                                                echo '<span class="text-secondary">NULL</span>';
                                            } elseif (is_bool($v)) {
                                                echo ia_h($v ? 'true' : 'false');
                                            } elseif (is_scalar($v)) {
                                                $s = (string) $v;
                                                echo ia_h(strlen($s) > 200 ? substr($s, 0, 197) . '…' : $s);
                                            } else {
                                                echo '<span class="text-secondary">(object)</span>';
                                            }
                                        ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($rows) === 0): ?>
                                <tr><td colspan="<?= count($cols) ?>" class="text-secondary">Нет строк в этом диапазоне.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require __DIR__ . '/partials/foot.php'; ?>
