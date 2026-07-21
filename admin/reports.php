<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';

ia_require_section('reports');

$user = ia_current_user();
$pageTitle = 'Отчёты';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';

$link = static function (string $type, string $format): string {
    return ia_admin_url('reports-export.php?type=' . rawurlencode($type) . '&format=' . rawurlencode($format));
};
?>
<main class="container-fluid px-3 px-lg-4 py-4">
    <h1 class="h4 mb-3">Отчёты и экспорт</h1>
    <?php if ($msg = ia_flash('reports_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <p class="text-secondary small mb-4">Excel: CSV с разделителем «;» и UTF-8 (открывается в Excel). PDF: печать страницы в PDF из браузера.</p>

    <div class="row g-3">
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6">Пользователи</h2>
                    <p class="small text-secondary mb-3">Список учётных записей платформы.</p>
                    <a class="btn btn-sm btn-outline-primary me-1" href="<?= ia_h($link('users', 'csv')) ?>">Excel (CSV)</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= ia_h($link('users', 'html')) ?>" target="_blank" rel="noopener">Печать / PDF</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6">Объявления</h2>
                    <p class="small text-secondary mb-3">Объявления с привязкой к пользователю.</p>
                    <a class="btn btn-sm btn-outline-primary me-1" href="<?= ia_h($link('listings', 'csv')) ?>">Excel (CSV)</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= ia_h($link('listings', 'html')) ?>" target="_blank" rel="noopener">Печать / PDF</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6">Доход</h2>
                    <p class="small text-secondary mb-3">Платежи сайта (<code>site_payments</code>).</p>
                    <a class="btn btn-sm btn-outline-primary me-1" href="<?= ia_h($link('revenue', 'csv')) ?>">Excel (CSV)</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= ia_h($link('revenue', 'html')) ?>" target="_blank" rel="noopener">Печать / PDF</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6">VIP-продажи</h2>
                    <p class="small text-secondary mb-3">Платежи с типом VIP.</p>
                    <a class="btn btn-sm btn-outline-primary me-1" href="<?= ia_h($link('vip', 'csv')) ?>">Excel (CSV)</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= ia_h($link('vip', 'html')) ?>" target="_blank" rel="noopener">Печать / PDF</a>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/partials/foot.php'; ?>
