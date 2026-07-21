<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/public_queries.php';

ia_platform_require_login();

$pdo = ia_db();
$uid = (int) ia_platform_current_user()['id'];
$lid = ia_request_int('listing_id');

$listing = $lid > 0 ? ia_pub_listing_owned_by($pdo, $lid, $uid) : null;
if ($listing === null) {
    http_response_code(404);
    $pageTitle = 'Оплата';
    require IA_ROOT . '/includes/partials/site-header.php';
    echo '<section class="py-5 ia-page-section"><div class="container ia-container"><p class="text-secondary">Объявление не найдено.</p><a href="' . ia_h(ia_public_url('profile.php')) . '">Профиль</a></div></section>';
    require IA_ROOT . '/includes/partials/site-footer.php';
    exit;
}

$pending = ia_promotion_listing_pending_payment($pdo, $lid);
if ($pending === null) {
    ia_flash('pub_ok', 'Оплата не требуется или уже выполнена.');
    ia_redirect(ia_public_url('profile.php?list=pending'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('pub_error', 'Сессия устарела.');
        ia_redirect(ia_public_url('pay-promotion.php?listing_id=' . $lid));
    }
    $txId = ia_post_int('transaction_id', (int) ($pending['transaction_id'] ?? 0));
    $method = ia_input_enum($_POST['payment_method'] ?? 'card', ['card', 'sbp', 'bank_transfer', 'cash'], 'card');
    if (ia_promotion_complete_payment($pdo, $txId, $uid, $method)) {
        ia_flash('pub_ok', 'Оплата принята. Объявление на проверке у администратора.');
        ia_redirect(ia_public_url('profile.php?list=pending'));
    }
    ia_flash('pub_error', 'Не удалось провести оплату. Попробуйте снова.');
    ia_redirect(ia_public_url('pay-promotion.php?listing_id=' . $lid));
}

$pageTitle = 'Оплата ' . strtoupper($pending['code']);
$iaBodyExtraClass = 'ia-page-pay-promo';
$carTitle = trim((string) (($listing['brand'] ?? '') . ' ' . ($listing['model'] ?? '')));

require IA_ROOT . '/includes/partials/site-header.php';
?>

<section class="py-4 py-md-5 ia-page-section ia-pay-promo-section">
    <div class="container ia-container ia-pay-promo-wrap">
        <header class="ia-pay-promo-head">
            <a class="ia-pay-promo-back" href="<?= ia_h(ia_public_url('profile.php?list=pending')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> На проверке</a>
            <h1 class="ia-pay-promo-title">Оплата тарифа</h1>
            <p class="ia-pay-promo-sub">После оплаты объявление останется на модерации. Администратор подтвердит публикацию.</p>
        </header>

        <div class="ia-pay-promo-grid">
            <article class="ia-pay-promo-card ia-pay-promo-card--listing">
                <span class="ia-pay-promo-kicker">Объявление</span>
                <h2 class="ia-pay-promo-car"><?= ia_h($carTitle !== '' ? $carTitle : 'Без названия') ?></h2>
                <p class="ia-pay-promo-meta">ID <?= (int) $lid ?> · статус: на проверке</p>
            </article>

            <article class="ia-pay-promo-card ia-pay-promo-card--tariff ia-pay-promo-card--<?= ia_h($pending['code']) ?>">
                <div class="ia-pay-promo-tariff-badge">
                    <i class="bi <?= $pending['code'] === 'vip' ? 'bi-gem' : 'bi-star-fill' ?>" aria-hidden="true"></i>
                    <?= ia_h(strtoupper($pending['code'])) ?>
                </div>
                <p class="ia-pay-promo-tariff-name"><?= ia_h($pending['tariff_name']) ?></p>
                <p class="ia-pay-promo-amount"><?= ia_h(ia_promotion_format_price($pending['amount'])) ?></p>
                <p class="ia-pay-promo-hint">Бейдж <?= ia_h(strtoupper($pending['code'])) ?> появится после оплаты и одобрения админом.</p>
            </article>
        </div>

        <form class="ia-pay-promo-form" method="post">
            <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
            <input type="hidden" name="action" value="pay">
            <input type="hidden" name="listing_id" value="<?= (int) $lid ?>">
            <input type="hidden" name="transaction_id" value="<?= (int) $pending['transaction_id'] ?>">

            <fieldset class="ia-pay-promo-methods">
                <legend class="form-label">Способ оплаты</legend>
                <label class="ia-pay-promo-method">
                    <input type="radio" name="payment_method" value="card" checked>
                    <span class="ia-pay-promo-method-icon"><i class="bi bi-credit-card-2-front" aria-hidden="true"></i></span>
                    <span class="ia-pay-promo-method-text"><strong>Банковская карта</strong><small>Мгновенное зачисление (демо)</small></span>
                </label>
                <label class="ia-pay-promo-method">
                    <input type="radio" name="payment_method" value="sbp">
                    <span class="ia-pay-promo-method-icon"><i class="bi bi-phone" aria-hidden="true"></i></span>
                    <span class="ia-pay-promo-method-text"><strong>СБП / перевод</strong><small>Подтверждение вручную (демо)</small></span>
                </label>
            </fieldset>

            <button type="submit" class="ia-pay-promo-submit">
                <i class="bi bi-shield-check" aria-hidden="true"></i>
                Оплатить <?= ia_h(ia_promotion_format_price($pending['amount'])) ?>
            </button>
            <p class="ia-pay-promo-footnote">Нажимая «Оплатить», вы подтверждаете выбор тарифа <?= ia_h(strtoupper($pending['code'])) ?>.</p>
        </form>
    </div>
</section>

<style>
.ia-pay-promo-section { --ia-pay-accent: #3b82f6; }
.ia-pay-promo-wrap { max-width: 720px; }
.ia-pay-promo-head { margin-bottom: 1.5rem; }
.ia-pay-promo-back { display: inline-flex; align-items: center; gap: .35rem; color: var(--bs-secondary-color); text-decoration: none; font-size: .9rem; margin-bottom: .75rem; }
.ia-pay-promo-title { font-size: clamp(1.35rem, 4vw, 1.75rem); font-weight: 800; margin: 0 0 .35rem; letter-spacing: -.02em; }
.ia-pay-promo-sub { margin: 0; color: var(--bs-secondary-color); font-size: .95rem; line-height: 1.5; }
.ia-pay-promo-grid { display: grid; gap: 1rem; margin-bottom: 1.25rem; }
@media (min-width: 576px) { .ia-pay-promo-grid { grid-template-columns: 1fr 1fr; } }
.ia-pay-promo-card { border-radius: 16px; padding: 1.15rem 1.25rem; border: 1px solid rgba(148,163,184,.22); background: linear-gradient(145deg, rgba(255,255,255,.06), rgba(15,23,42,.04)); box-shadow: 0 12px 32px rgba(15,23,42,.08); }
.ia-pay-promo-kicker { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: var(--bs-secondary-color); }
.ia-pay-promo-car { font-size: 1.15rem; font-weight: 700; margin: .35rem 0 .2rem; }
.ia-pay-promo-meta { margin: 0; font-size: .85rem; color: var(--bs-secondary-color); }
.ia-pay-promo-card--vip { border-color: rgba(245,158,11,.35); background: linear-gradient(145deg, rgba(251,191,36,.12), rgba(15,23,42,.02)); }
.ia-pay-promo-card--top { border-color: rgba(59,130,246,.35); background: linear-gradient(145deg, rgba(59,130,246,.1), rgba(15,23,42,.02)); }
.ia-pay-promo-tariff-badge { display: inline-flex; align-items: center; gap: .4rem; font-weight: 800; font-size: .85rem; letter-spacing: .06em; }
.ia-pay-promo-tariff-name { margin: .5rem 0 .15rem; font-weight: 600; }
.ia-pay-promo-amount { font-size: 1.65rem; font-weight: 800; margin: 0 0 .5rem; }
.ia-pay-promo-hint { margin: 0; font-size: .82rem; color: var(--bs-secondary-color); }
.ia-pay-promo-form { border-radius: 18px; padding: 1.25rem; border: 1px solid rgba(148,163,184,.2); background: var(--bs-body-bg); box-shadow: 0 16px 40px rgba(15,23,42,.1); }
.ia-pay-promo-methods { border: 0; margin: 0 0 1rem; padding: 0; display: grid; gap: .65rem; }
.ia-pay-promo-method { display: flex; align-items: center; gap: .75rem; padding: .85rem 1rem; border-radius: 12px; border: 1px solid rgba(148,163,184,.25); cursor: pointer; transition: border-color .15s, box-shadow .15s; }
.ia-pay-promo-method:has(input:checked) { border-color: var(--ia-pay-accent); box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
.ia-pay-promo-method input { accent-color: var(--ia-pay-accent); }
.ia-pay-promo-method-icon { width: 2.5rem; height: 2.5rem; border-radius: 10px; display: grid; place-items: center; background: rgba(59,130,246,.12); color: var(--ia-pay-accent); font-size: 1.1rem; }
.ia-pay-promo-method-text { display: flex; flex-direction: column; gap: .1rem; }
.ia-pay-promo-method-text small { color: var(--bs-secondary-color); }
.ia-pay-promo-submit { width: 100%; border: 0; border-radius: 14px; padding: .95rem 1.2rem; font-weight: 700; font-size: 1.05rem; color: #fff; background: linear-gradient(135deg, #2563eb, #7c3aed); box-shadow: 0 10px 28px rgba(37,99,235,.35); display: inline-flex; align-items: center; justify-content: center; gap: .5rem; }
.ia-pay-promo-submit:hover { filter: brightness(1.05); color: #fff; }
.ia-pay-promo-footnote { margin: .75rem 0 0; text-align: center; font-size: .78rem; color: var(--bs-secondary-color); }
</style>

<?php require IA_ROOT . '/includes/partials/site-footer.php'; ?>
