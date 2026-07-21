<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';

ia_require_section('database');

$info = ia_db_connection_info();
$checks = [
    'pgsql' => extension_loaded('pgsql'),
    'env_file' => is_readable(IA_ROOT . '/.env'),
];
$counts = [];
$error = null;

try {
    $pdo = ia_db();
    foreach (['platform_users', 'ad_listings', 'admin_users'] as $table) {
        $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }
    $version = (string) $pdo->query('SELECT version()')->fetchColumn();
} catch (Throwable $e) {
    $error = $e->getMessage();
    $version = '';
}

$pageTitle = 'Статус Supabase';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';
?>
<main class="container-fluid px-3 px-lg-4 py-4">
    <h1 class="h4 mb-3">Пайвастшавии база (воқеӣ)</h1>
    <p class="text-secondary small">Агар ин ҷо «Supabase» ва шумораҳо бо Table Editor яксон бошанд — ҳамаи сабтҳо дар он ҷо мераванд. Демо/fake маълумот хомӯш аст.</p>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <table class="table table-sm mb-0">
                <tbody>
                    <tr><th scope="row">Манбаъ</th><td><strong><?= ia_h($info['label']) ?></strong></td></tr>
                    <tr><th scope="row">Host</th><td><code><?= ia_h($info['host']) ?></code></td></tr>
                    <tr><th scope="row">База</th><td><code><?= ia_h($info['name']) ?></code></td></tr>
                    <tr><th scope="row">Корбар</th><td><code><?= ia_h($info['user']) ?></code></td></tr>
                    <tr><th scope="row">pgsql (pg_connect)</th><td><?= $checks['pgsql'] ? '✓ фаъол' : '✗ хомӯш — php.ini extension=pgsql' ?></td></tr>
                    <tr><th scope="row">.env</th><td><?= $checks['env_file'] ? '✓ ёфт шуд' : '✗ нест' ?></td></tr>
                    <?php if ($version !== ''): ?>
                        <tr><th scope="row">PostgreSQL</th><td class="small text-break"><?= ia_h($version) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger"><?= ia_h($error) ?></div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent"><strong>Шумора дар база</strong></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($counts as $table => $n): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <code><?= ia_h($table) ?></code>
                        <span><?= (int) $n ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <p class="small text-secondary mt-3">
            Агар сабт кардаед, аммо ин ҷо 0 аст — дар Supabase SQL Editor иҷро кунед: <code>sql/supabase_disable_rls.sql</code>
        </p>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/partials/foot.php'; ?>
