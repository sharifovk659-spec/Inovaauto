<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/dashboard_data.php';

ia_require_section('dashboard');

$user = ia_current_user();
$pageTitle = 'Главная';

$chartDays = (int) (ia_config()['app']['dashboard_chart_days'] ?? 14);
$kpi = ia_dashboard_kpis();
$sListings = ia_dashboard_series_listings_by_day($chartDays);
$sUsers = ia_dashboard_series_users_by_day($chartDays);
$sRevenue = ia_dashboard_series_revenue_by_day($chartDays);

$chartJson = static function (array $series): string {
    return json_encode($series, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
};

require __DIR__ . '/partials/head.php';
?>
<?php require __DIR__ . '/partials/nav.php'; ?>

<main class="container-fluid px-3 px-lg-4 py-4">
    <?php if ($msg = ia_flash('admin_error')): ?><div class="alert alert-warning"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <header class="mb-4">
        <h1 class="h3 mb-1">Главная панель</h1>
        <p class="text-secondary mb-0 small">Сводная статистика платформы InnovaAuto<?php if ($user): ?> · <?= ia_h(ia_admin_role_label_ru((string) ($user['role'] ?? ''))) ?><?php endif; ?></p>
        <div class="mt-2 d-flex flex-wrap gap-2">
            <?php if (ia_admin_can($user, 'users')): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= ia_h(ia_admin_url('users.php')) ?>">Перейти к пользователям</a>
            <?php endif; ?>
            <?php if (ia_admin_can($user, 'listings')): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= ia_h(ia_admin_url('listings.php')) ?>">Перейти к объявлениям</a>
            <?php endif; ?>
            <?php if (ia_admin_can($user, 'billing')): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= ia_h(ia_admin_url('payments.php')) ?>">Платежи</a>
            <?php endif; ?>
            <?php if (ia_admin_can($user, 'settings')): ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= ia_h(ia_admin_url('settings.php')) ?>">Настройки сайта</a>
            <?php endif; ?>
            <?php if (ia_admin_can($user, 'database')): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= ia_h(ia_admin_url('database.php')) ?>">База данных</a>
            <?php endif; ?>
        </div>
    </header>

    <section class="mb-4" aria-label="Показатели">
        <h2 class="h6 text-uppercase text-secondary mb-3">Статистика</h2>
        <div class="row g-3">
            <div class="col-md-6 col-xl-4">
                <div class="card ia-stat-card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-secondary small mb-2">Пользователи</div>
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span>Всего</span>
                            <span class="fw-semibold"><?= (int) $kpi['users_total'] ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span>Активные</span>
                            <span class="fw-semibold text-success"><?= (int) $kpi['users_active'] ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span>Заблокированные</span>
                            <span class="fw-semibold text-danger"><?= (int) $kpi['users_blocked'] ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-4">
                <div class="card ia-stat-card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-secondary small mb-2">Объявления</div>
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span>Активные</span>
                            <span class="fw-semibold text-success"><?= (int) $kpi['listings_active'] ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span>На проверке</span>
                            <span class="fw-semibold text-warning"><?= (int) $kpi['listings_pending'] ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span>Отклонённые</span>
                            <span class="fw-semibold text-danger"><?= (int) $kpi['listings_rejected'] ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-4">
                <div class="card ia-stat-card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-secondary small mb-2">Машины</div>
                        <div class="d-flex justify-content-between align-items-center py-2">
                            <span>Всего объявлений (позиций)</span>
                            <span class="fs-4 fw-bold text-primary"><?= (int) $kpi['cars_total'] ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-4">
                <div class="card ia-stat-card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-secondary small mb-2">Доход</div>
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span>Доход сайта (общий)</span>
                            <span class="fw-semibold"><?= ia_h(number_format($kpi['revenue_site'], 2, '.', ' ')) ?> с.</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span>VIP-услуги</span>
                            <span class="fw-semibold"><?= ia_h(number_format($kpi['revenue_vip'], 2, '.', ' ')) ?> с.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section aria-label="Графики">
        <h2 class="h6 text-uppercase text-secondary mb-3">Графики</h2>
        <p class="small text-secondary mb-3">Период: последние <?= (int) $chartDays ?> дней</p>
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm ia-chart-card">
                    <div class="card-body">
                        <h3 class="h6 card-title">Объявления по дням</h3>
                        <div class="ia-chart-wrap">
                            <canvas id="chartListings" width="400" height="220"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm ia-chart-card">
                    <div class="card-body">
                        <h3 class="h6 card-title">Регистрации пользователей по дням</h3>
                        <div class="ia-chart-wrap">
                            <canvas id="chartUsers" width="400" height="220"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm ia-chart-card">
                    <div class="card-body">
                        <h3 class="h6 card-title">Доход сайта по дням</h3>
                        <div class="ia-chart-wrap">
                            <canvas id="chartRevenue" width="400" height="220"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
(function () {
  const listings = <?= $chartJson($sListings) ?>;
  const users = <?= $chartJson($sUsers) ?>;
  const revenue = <?= $chartJson($sRevenue) ?>;

  const commonOpts = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false }
    },
    scales: {
      x: {
        grid: { color: 'rgba(148, 163, 184, 0.12)' },
        ticks: { color: '#94a3b8', maxRotation: 0, autoSkip: true },
        border: { display: false }
      },
      y: {
        beginAtZero: true,
        grid: { color: 'rgba(148, 163, 184, 0.12)' },
        ticks: { color: '#94a3b8' },
        border: { display: false }
      }
    }
  };

  new Chart(document.getElementById('chartListings'), {
    type: 'line',
    data: {
      labels: listings.labels,
      datasets: [{
        label: 'Объявления',
        data: listings.values,
        borderColor: 'rgb(13, 110, 253)',
        backgroundColor: 'rgba(13, 110, 253, 0.12)',
        tension: 0.25,
        fill: true
      }]
    },
    options: commonOpts
  });

  new Chart(document.getElementById('chartUsers'), {
    type: 'bar',
    data: {
      labels: users.labels,
      datasets: [{
        label: 'Регистрации',
        data: users.values,
        backgroundColor: 'rgba(25, 135, 84, 0.55)',
        borderColor: 'rgb(25, 135, 84)',
        borderWidth: 1
      }]
    },
    options: commonOpts
  });

  new Chart(document.getElementById('chartRevenue'), {
    type: 'line',
    data: {
      labels: revenue.labels,
      datasets: [{
        label: 'Доход (с.)',
        data: revenue.values,
        borderColor: 'rgb(111, 66, 193)',
        backgroundColor: 'rgba(111, 66, 193, 0.12)',
        tension: 0.25,
        fill: true
      }]
    },
    options: commonOpts
  });
})();
</script>

<?php require __DIR__ . '/partials/foot.php'; ?>
