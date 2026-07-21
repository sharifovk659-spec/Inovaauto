<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/site_settings.php';
require_once IA_ROOT . '/includes/admin_site_settings.php';

ia_require_section('settings');
ia_admin_site_settings_handle_post();

$pdo = ia_db();
$map = ia_site_settings_map($pdo);
$user = ia_current_user();
$pageTitle = 'Настройки сайта';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/nav.php';

$logoUrl = '';
if (($map['logo_path'] ?? '') !== '') {
    $logoUrl = ia_site_logo_public_url($map['logo_path']);
}
$logoHeight = (int) ($map['logo_height'] ?? 56);
if ($logoHeight < 24) {
    $logoHeight = 24;
}
if ($logoHeight > 80) {
    $logoHeight = 80;
}
$logoBgMode = (string) ($map['logo_bg_mode'] ?? 'transparent');
if (!in_array($logoBgMode, ['transparent', 'white', 'dark', 'custom'], true)) {
    $logoBgMode = 'transparent';
}
$logoBgColor = (string) ($map['logo_bg_color'] ?? '');
if ($logoBgColor !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $logoBgColor)) {
    $logoBgColor = '';
}
$listingPhotoQaOn = (($map['listing_photo_qa_enabled'] ?? '0') === '1');
$iaPromoStatus = ia_promotion_status($pdo);
$promoLaunchInput = $iaPromoStatus['launch_at']->format('Y-m-d\TH:i');
$promoMonOn = $iaPromoStatus['monetization_enabled'];
$promoFreeMonths = $iaPromoStatus['free_months'];
$iaPromoTopPrice = ia_promotion_tariff_price($pdo, 'top');
$iaPromoVipPrice = ia_promotion_tariff_price($pdo, 'vip');
?>
<main class="container-fluid px-3 px-lg-4 py-4">
    <h1 class="h4 mb-3">Настройки</h1>
    <?php if ($msg = ia_flash('settings_ok')): ?><div class="alert alert-success"><?= ia_h((string) $msg) ?></div><?php endif; ?>
    <?php if ($msg = ia_flash('settings_error')): ?><div class="alert alert-danger"><?= ia_h((string) $msg) ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">Общие</h2>
                    <form method="post">
                        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                        <input type="hidden" name="action" value="save_text">
                        <div class="mb-2">
                            <label class="form-label">Название сайта</label>
                            <input type="text" name="site_name" class="form-control" value="<?= ia_h($map['site_name'] ?? '') ?>" maxlength="120">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Телефон</label>
                            <input type="text" name="contact_phone" class="form-control" value="<?= ia_h($map['contact_phone'] ?? '') ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Email контактов</label>
                            <input type="email" name="contact_email" class="form-control" value="<?= ia_h($map['contact_email'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Адрес / доп. контакты</label>
                            <textarea name="contact_address" class="form-control" rows="3"><?= ia_h($map['contact_address'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h6 mb-3">Логотип</h2>

                    <div class="ia-logo-preview-wrap mb-3" id="iaLogoPreviewWrap"
                        data-bg-mode="<?= ia_h($logoBgMode) ?>"
                        data-bg-color="<?= ia_h($logoBgColor) ?>"
                        data-height="<?= (int) $logoHeight ?>">
                        <div class="ia-logo-preview-strip" id="iaLogoPreviewStrip">
                            <?php if ($logoUrl !== ''): ?>
                                <img src="<?= ia_h($logoUrl) ?>" alt="Logo" id="iaLogoPreviewImg">
                            <?php else: ?>
                                <span class="ia-logo-preview-empty">Логотип не загружен</span>
                            <?php endif; ?>
                        </div>
                        <div class="ia-logo-preview-meta small text-secondary mt-1">
                            Так логотип отображается в шапке сайта.
                        </div>
                    </div>

                    <?php if ($logoUrl !== ''): ?>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                <input type="hidden" name="action" value="reprocess_logo">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Повторно убрать однотонный фон с текущего файла">Убрать фон</button>
                            </form>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                                <input type="hidden" name="action" value="remove_logo">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Удалить логотип</button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data" class="mt-2">
                        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                        <input type="hidden" name="action" value="upload_logo">
                        <label class="form-label small mb-1">Файл логотипа (PNG / WebP / SVG / JPG)</label>
                        <input type="file" name="logo" id="iaLogoUploadInput" class="form-control form-control-sm mb-2" accept="image/*,.svg">
                        <div class="small text-secondary mb-2">JPEG автоматически конвертируется в PNG с прозрачным фоном.</div>
                        <button type="submit" class="btn btn-sm btn-outline-primary">Загрузить</button>
                    </form>

                    <hr class="my-3">

                    <form method="post" id="iaLogoViewForm">
                        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                        <input type="hidden" name="action" value="save_logo_view">

                        <div class="mb-3">
                            <label class="form-label small d-flex justify-content-between mb-1">
                                <span>Размер логотипа в шапке</span>
                                <span><b id="iaLogoHeightLabel"><?= (int) $logoHeight ?></b> px</span>
                            </label>
                            <input type="range" min="24" max="80" step="1" value="<?= (int) $logoHeight ?>" name="logo_height" id="iaLogoHeightInput" class="form-range">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small mb-2">Фон логотипа</label>
                            <div class="d-flex flex-wrap gap-2 align-items-center" id="iaLogoBgChoices">
                                <label class="ia-logo-bg-choice">
                                    <input type="radio" name="logo_bg_mode" value="transparent" <?= $logoBgMode === 'transparent' ? 'checked' : '' ?>>
                                    <span class="ia-logo-bg-swatch ia-logo-bg-swatch--transparent" title="Прозрачный"></span>
                                    <span class="small">Прозрачный</span>
                                </label>
                                <label class="ia-logo-bg-choice">
                                    <input type="radio" name="logo_bg_mode" value="white" <?= $logoBgMode === 'white' ? 'checked' : '' ?>>
                                    <span class="ia-logo-bg-swatch" style="background:#ffffff;border:1px solid #d0d6e0"></span>
                                    <span class="small">Белый</span>
                                </label>
                                <label class="ia-logo-bg-choice">
                                    <input type="radio" name="logo_bg_mode" value="dark" <?= $logoBgMode === 'dark' ? 'checked' : '' ?>>
                                    <span class="ia-logo-bg-swatch" style="background:#0f172a"></span>
                                    <span class="small">Тёмный</span>
                                </label>
                                <label class="ia-logo-bg-choice">
                                    <input type="radio" name="logo_bg_mode" value="custom" <?= $logoBgMode === 'custom' ? 'checked' : '' ?>>
                                    <span class="ia-logo-bg-swatch" id="iaLogoBgCustomSwatch" style="background:<?= ia_h($logoBgColor !== '' ? $logoBgColor : '#3d7eff') ?>"></span>
                                    <span class="small">Свой цвет</span>
                                </label>
                                <input type="color" name="logo_bg_color" id="iaLogoBgColorInput"
                                    value="<?= ia_h($logoBgColor !== '' ? $logoBgColor : '#3d7eff') ?>"
                                    class="form-control form-control-color form-control-sm"
                                    style="width:46px;<?= $logoBgMode === 'custom' ? '' : 'opacity:0.5;' ?>">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-sm btn-primary">Сохранить вид логотипа</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">SEO</h2>
                    <form method="post">
                        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                        <input type="hidden" name="action" value="save_text">
                        <div class="mb-2">
                            <label class="form-label">Meta title</label>
                            <input type="text" name="meta_title" class="form-control" value="<?= ia_h($map['meta_title'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Meta description</label>
                            <textarea name="meta_description" class="form-control" rows="3"><?= ia_h($map['meta_description'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Сохранить SEO</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h6 mb-3">Футер сайта</h2>
                    <p class="small text-secondary mb-3">Текст бренда и описание в нижней части всех страниц. Контакты — в блоке «Общие».</p>
                    <form method="post">
                        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                        <input type="hidden" name="action" value="save_text">
                        <div class="mb-2">
                            <label class="form-label">Название бренда в футере</label>
                            <input type="text" name="footer_brand_title" class="form-control" value="<?= ia_h($map['footer_brand_title'] ?? 'Innovaauto.com') ?>" maxlength="80" placeholder="Innovaauto.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Краткое описание</label>
                            <textarea name="footer_company_text" class="form-control" rows="2" maxlength="240" placeholder="InnovaAuto — маркетплейс автомобилей в Таджикистане."><?= ia_h($map['footer_company_text'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Сохранить футер</button>
                    </form>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">Соцсети</h2>
                    <p class="small text-secondary mb-3">Ссылки отображаются в футере. Пустое поле — иконка без ссылки.</p>
                    <form method="post">
                        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                        <input type="hidden" name="action" value="save_text">
                        <?php foreach (['social_instagram' => 'Instagram', 'social_telegram' => 'Telegram', 'social_facebook' => 'Facebook', 'social_youtube' => 'YouTube', 'social_vk' => 'VK'] as $field => $lab): ?>
                            <div class="mb-2">
                                <label class="form-label"><?= ia_h($lab) ?> (URL)</label>
                                <input type="url" name="<?= ia_h($field) ?>" class="form-control" value="<?= ia_h($map[$field] ?? '') ?>" placeholder="https://">
                            </div>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary">Сохранить ссылки</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">Публикация объявлений</h2>
                    <p class="small text-secondary mb-3">Геолокация при размещении объявления <strong>всегда обязательна</strong> (разрешение в браузере). Здесь настраивается только <strong>автопроверка фото</strong> (ракурс, резкость и т.д.): по умолчанию выключена — загружаются обычные файлы без анализа. Порядок слотов 1 → 2 → … на сайте не отключается.</p>
                    <form method="post">
                        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                        <input type="hidden" name="action" value="save_listing_publish_rules">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" name="listing_photo_qa_enabled" value="1" id="iaListingPhotoQaSwitch" <?= $listingPhotoQaOn ? 'checked' : '' ?>>
                            <label class="form-check-label" for="iaListingPhotoQaSwitch">Включить автопроверку фото (ракурс и качество)</label>
                        </div>
                        <p class="small text-secondary mb-3 mb-md-2">Пока переключатель выключен, сервер не отклоняет фото по ракурсу и эвристике — только проверка типа/размера файла (до 5 МБ на снимок).</p>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="ia-promo-mon-card" id="iaPromoMonCard" data-grace-end="<?= ia_h($iaPromoStatus['grace_ends_iso']) ?>" data-paid-required="<?= $iaPromoStatus['paid_required'] ? '1' : '0' ?>" data-mon-on="<?= $promoMonOn ? '1' : '0' ?>">
                <div class="ia-promo-mon-card__glow" aria-hidden="true"></div>
                <header class="ia-promo-mon-card__head">
                    <div>
                        <h2 class="ia-promo-mon-card__title">VIP / TOP — монетизация</h2>
                        <p class="ia-promo-mon-card__lead">Первые <?= (int) $promoFreeMonths ?> мес. бесплатно, затем оплата при размещении → модерация админом.</p>
                    </div>
                    <div class="ia-promo-mon-card__prices">
                        <span class="ia-promo-mon-price ia-promo-mon-price--top">TOP <?= ia_h(ia_promotion_format_price($iaPromoTopPrice)) ?></span>
                        <span class="ia-promo-mon-price ia-promo-mon-price--vip">VIP <?= ia_h(ia_promotion_format_price($iaPromoVipPrice)) ?></span>
                    </div>
                </header>

                <div class="ia-promo-mon-timer" id="iaPromoCountdown" <?= !$promoMonOn || $iaPromoStatus['paid_required'] ? 'hidden' : '' ?>>
                    <p class="ia-promo-mon-timer__label">До платного режима осталось</p>
                    <div class="ia-promo-mon-timer__digits" aria-live="polite">
                        <div class="ia-promo-mon-unit"><span id="iaPromoCdDays">00</span><small>дней</small></div>
                        <div class="ia-promo-mon-unit"><span id="iaPromoCdHours">00</span><small>час</small></div>
                        <div class="ia-promo-mon-unit"><span id="iaPromoCdMin">00</span><small>мин</small></div>
                        <div class="ia-promo-mon-unit"><span id="iaPromoCdSec">00</span><small>сек</small></div>
                    </div>
                    <p class="ia-promo-mon-timer__end">Окончание бесплатного периода: <strong id="iaPromoCdEndLabel"><?= ia_h($iaPromoStatus['grace_ends_at']->format('d.m.Y H:i:s')) ?></strong></p>
                </div>

                <div class="ia-promo-mon-status" id="iaPromoStatusBox">
                    <?php if (!$promoMonOn): ?>
                        <span class="ia-promo-mon-pill ia-promo-mon-pill--off">Монетизация выключена — VIP/TOP бесплатно</span>
                    <?php elseif ($iaPromoStatus['in_grace_period']): ?>
                        <span class="ia-promo-mon-pill ia-promo-mon-pill--free">Бесплатный период активен</span>
                    <?php else: ?>
                        <span class="ia-promo-mon-pill ia-promo-mon-pill--paid">Платный режим · оплата при публикации</span>
                    <?php endif; ?>
                </div>

                <form method="post" class="ia-promo-mon-form">
                    <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="save_promotion_monetization">
                    <input type="hidden" name="promotion_restart_timer" id="iaPromoRestartTimer" value="0">

                    <div class="ia-promo-mon-switch-row">
                        <div class="form-check form-switch ia-promo-mon-switch">
                            <input class="form-check-input" type="checkbox" role="switch" name="promotion_monetization_enabled" value="1" id="iaPromoMonSwitch" <?= $promoMonOn ? 'checked' : '' ?>>
                            <label class="form-check-label" for="iaPromoMonSwitch">Включить монетизацию (6 мес. + автооплата)</label>
                        </div>
                        <p class="small text-secondary mb-0">При включении отсчёт стартует с <strong>текущих</strong> даты и времени.</p>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-7">
                            <label class="form-label" for="iaPromoLaunch">Дата старта (вручную, если не перезапускать)</label>
                            <input type="datetime-local" class="form-control" id="iaPromoLaunch" name="promotion_launch_at" value="<?= ia_h($promoLaunchInput) ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="iaPromoFreeMonths">Бесплатно (мес.)</label>
                            <input type="number" class="form-control" id="iaPromoFreeMonths" name="promotion_free_months" min="1" max="36" value="<?= (int) $promoFreeMonths ?>">
                        </div>
                    </div>

                    <div class="ia-promo-mon-actions">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="iaPromoRestartBtn">Перезапустить отсчёт сейчас</button>
                        <button type="submit" class="btn btn-primary ia-promo-mon-save">Сохранить VIP/TOP</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">API-ключи (интеграции)</h2>
                    <p class="small text-secondary">Хранятся в базе. Для reCAPTCHA используйте также переменные окружения или <code>config/local.php</code>.</p>
                    <form method="post">
                        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                        <input type="hidden" name="action" value="save_text">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label">Карты / геолокация</label>
                                <input type="text" name="api_maps_key" class="form-control" value="<?= ia_h($map['api_maps_key'] ?? '') ?>" autocomplete="off">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">SMS-шлюз</label>
                                <input type="text" name="api_sms_gateway_key" class="form-control" value="<?= ia_h($map['api_sms_gateway_key'] ?? '') ?>" autocomplete="off">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Push (серверный ключ)</label>
                                <input type="text" name="api_push_server_key" class="form-control" value="<?= ia_h($map['api_push_server_key'] ?? '') ?>" autocomplete="off">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-2">Сохранить ключи</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.ia-logo-preview-wrap { width: 100%; }
.ia-logo-preview-strip {
    display: flex; align-items: center; justify-content: flex-start;
    width: 100%; min-height: 96px; padding: 16px 20px;
    border: 1px dashed rgba(148, 163, 184, .35); border-radius: 12px;
    background-image:
        linear-gradient(45deg, rgba(148,163,184,.18) 25%, transparent 25%, transparent 75%, rgba(148,163,184,.18) 75%, rgba(148,163,184,.18)),
        linear-gradient(45deg, rgba(148,163,184,.18) 25%, transparent 25%, transparent 75%, rgba(148,163,184,.18) 75%, rgba(148,163,184,.18));
    background-size: 16px 16px; background-position: 0 0, 8px 8px;
    transition: background-color .15s ease;
}
.ia-logo-preview-strip[data-bg="white"] { background-image: none; background-color: #ffffff; }
.ia-logo-preview-strip[data-bg="dark"] { background-image: none; background-color: #0f172a; }
.ia-logo-preview-strip[data-bg="custom"] { background-image: none; }
.ia-logo-preview-strip img { display: block; max-width: 100%; height: var(--logo-h, 64px); object-fit: contain; }
.ia-logo-preview-empty { color: #94a3b8; font-size: .85rem; }
.ia-logo-bg-choice { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; padding: 4px 10px 4px 4px; border: 1px solid rgba(148,163,184,.25); border-radius: 999px; }
.ia-logo-bg-choice input { display: none; }
.ia-logo-bg-choice:has(input:checked) { border-color: #3d7eff; box-shadow: 0 0 0 2px rgba(61,126,255,.18); }
.ia-logo-bg-swatch { width: 22px; height: 22px; border-radius: 50%; display: inline-block; }
.ia-logo-bg-swatch--transparent {
    background-image:
        linear-gradient(45deg, #cbd5e1 25%, transparent 25%, transparent 75%, #cbd5e1 75%, #cbd5e1),
        linear-gradient(45deg, #cbd5e1 25%, transparent 25%, transparent 75%, #cbd5e1 75%, #cbd5e1);
    background-size: 8px 8px; background-position: 0 0, 4px 4px;
    border: 1px solid #d0d6e0;
}
</style>

<script>
(function () {
    var wrap = document.getElementById('iaLogoPreviewWrap');
    if (!wrap) return;
    var strip = document.getElementById('iaLogoPreviewStrip');
    var heightInput = document.getElementById('iaLogoHeightInput');
    var heightLabel = document.getElementById('iaLogoHeightLabel');
    var bgRadios = document.querySelectorAll('input[name="logo_bg_mode"]');
    var colorInput = document.getElementById('iaLogoBgColorInput');
    var customSwatch = document.getElementById('iaLogoBgCustomSwatch');
    var fileInput = document.getElementById('iaLogoUploadInput');
    var img = document.getElementById('iaLogoPreviewImg');

    function applyBg() {
        var mode = (document.querySelector('input[name="logo_bg_mode"]:checked') || {}).value || 'transparent';
        strip.setAttribute('data-bg', mode);
        if (mode === 'custom' && colorInput) {
            strip.style.backgroundColor = colorInput.value;
            colorInput.style.opacity = '1';
        } else {
            if (mode === 'white') strip.style.backgroundColor = '#ffffff';
            else if (mode === 'dark') strip.style.backgroundColor = '#0f172a';
            else strip.style.backgroundColor = '';
            if (colorInput) colorInput.style.opacity = '0.5';
        }
    }

    function applyHeight() {
        if (!heightInput || !strip) return;
        var v = parseInt(heightInput.value, 10) || 64;
        strip.style.setProperty('--logo-h', v + 'px');
        if (heightLabel) heightLabel.textContent = String(v);
    }

    if (heightInput) {
        heightInput.addEventListener('input', applyHeight);
    }
    bgRadios.forEach(function (r) { r.addEventListener('change', applyBg); });
    if (colorInput) {
        colorInput.addEventListener('input', function () {
            if (customSwatch) customSwatch.style.background = colorInput.value;
            var customRadio = document.querySelector('input[name="logo_bg_mode"][value="custom"]');
            if (customRadio) { customRadio.checked = true; }
            applyBg();
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            var f = fileInput.files && fileInput.files[0];
            if (!f) return;
            if (!img) {
                var empty = strip.querySelector('.ia-logo-preview-empty');
                if (empty) empty.remove();
                img = document.createElement('img');
                img.id = 'iaLogoPreviewImg';
                strip.appendChild(img);
            }
            img.src = URL.createObjectURL(f);
        });
    }

    applyHeight();
    applyBg();
})();

(function () {
    var card = document.getElementById('iaPromoMonCard');
    var countdown = document.getElementById('iaPromoCountdown');
    var restartBtn = document.getElementById('iaPromoRestartBtn');
    var restartInput = document.getElementById('iaPromoRestartTimer');
    var monSwitch = document.getElementById('iaPromoMonSwitch');
    if (!card || !countdown) return;

    var endIso = card.getAttribute('data-grace-end') || '';
    var endMs = Date.parse(endIso);
    if (!endMs || isNaN(endMs)) return;

    function pad(n) { return (n < 10 ? '0' : '') + n; }

    function tick() {
        var left = Math.max(0, Math.floor((endMs - Date.now()) / 1000));
        var d = Math.floor(left / 86400);
        var h = Math.floor((left % 86400) / 3600);
        var m = Math.floor((left % 3600) / 60);
        var s = left % 60;
        var elD = document.getElementById('iaPromoCdDays');
        var elH = document.getElementById('iaPromoCdHours');
        var elM = document.getElementById('iaPromoCdMin');
        var elS = document.getElementById('iaPromoCdSec');
        if (elD) elD.textContent = pad(d);
        if (elH) elH.textContent = pad(h);
        if (elM) elM.textContent = pad(m);
        if (elS) elS.textContent = pad(s);
        if (left <= 0 && card.getAttribute('data-mon-on') === '1') {
            countdown.hidden = true;
        }
    }

    tick();
    setInterval(tick, 1000);

    if (restartBtn && restartInput) {
        restartBtn.addEventListener('click', function () {
            restartInput.value = '1';
            if (monSwitch) monSwitch.checked = true;
        });
    }
    if (monSwitch && restartInput) {
        monSwitch.addEventListener('change', function () {
            if (monSwitch.checked && card.getAttribute('data-mon-on') !== '1') {
                restartInput.value = '1';
            }
        });
    }
})();
</script>

<?php require __DIR__ . '/partials/foot.php'; ?>
