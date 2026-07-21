<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/public_bootstrap.php';
require_once IA_ROOT . '/includes/public_moderation.php';

$pdo = ia_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'contact') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('pub_error', 'Сессия устарела.');
    } elseif (!ia_pub_save_contact_request(
        $pdo,
        ia_post_text('from_name', 120),
        ia_post_email('from_email'),
        ia_post_text('message', 8000, true)
    )) {
        ia_flash('pub_error', 'Проверьте имя и текст сообщения.');
    } else {
        ia_flash('pub_ok', 'Спасибо! Сообщение принято. Мы свяжемся с вами по контактам из настроек сайта.');
    }
    ia_redirect(ia_public_url('contact.php'));
}

$intro = ia_site_setting_get($pdo, 'page_contact_intro', '');
$phone = ia_site_setting_get($pdo, 'contact_phone', '');
$email = ia_site_setting_get($pdo, 'contact_email', '');
$addr = ia_site_setting_get($pdo, 'contact_address', '');
$pageTitle = 'Контакты';

require IA_ROOT . '/includes/partials/site-header.php';
?>

<section class="ia-contact-hero" aria-labelledby="iaContactTitle">
    <div class="ia-contact-hero-glow" aria-hidden="true"></div>
    <div class="container ia-container">
        <div class="ia-contact-hero-inner">
            <span class="ia-contact-kicker">InnovaAuto · поддержка</span>
            <h1 id="iaContactTitle" class="ia-contact-hero-title">Свяжитесь с нами</h1>
            <p class="ia-contact-hero-lead">Вопросы по объявлениям, сотрудничеству или работе платформы — напишите, и мы ответим в ближайшее время.</p>
        </div>
    </div>
</section>

<section class="ia-contact-main py-5 ia-page-section">
    <div class="container ia-container">
        <div class="row g-4 g-xl-5 align-items-stretch">
            <div class="col-lg-5">
                <div class="ia-contact-side h-100">
                    <?php if ($intro !== ''): ?>
                        <p class="ia-contact-intro"><?= nl2br(ia_h($intro)) ?></p>
                    <?php else: ?>
                        <p class="ia-contact-intro">Мы на связи каждый день. Выберите удобный способ или заполните форму справа.</p>
                    <?php endif; ?>

                    <div class="ia-contact-info-stack">
                        <?php if ($phone !== ''): ?>
                        <article class="ia-contact-info-card">
                            <span class="ia-contact-info-icon" aria-hidden="true"><i class="bi bi-telephone-fill"></i></span>
                            <div>
                                <h2 class="ia-contact-info-label">Телефон</h2>
                                <a class="ia-contact-info-value" href="tel:<?= ia_h(preg_replace('/\s+/', '', $phone) ?? $phone) ?>"><?= ia_h($phone) ?></a>
                            </div>
                        </article>
                        <?php endif; ?>
                        <?php if ($email !== ''): ?>
                        <article class="ia-contact-info-card">
                            <span class="ia-contact-info-icon" aria-hidden="true"><i class="bi bi-envelope-fill"></i></span>
                            <div>
                                <h2 class="ia-contact-info-label">Email</h2>
                                <a class="ia-contact-info-value" href="mailto:<?= ia_h($email) ?>"><?= ia_h($email) ?></a>
                            </div>
                        </article>
                        <?php endif; ?>
                        <?php if ($addr !== ''): ?>
                        <article class="ia-contact-info-card">
                            <span class="ia-contact-info-icon" aria-hidden="true"><i class="bi bi-geo-alt-fill"></i></span>
                            <div>
                                <h2 class="ia-contact-info-label">Адрес</h2>
                                <p class="ia-contact-info-value ia-contact-info-value--text mb-0"><?= nl2br(ia_h($addr)) ?></p>
                            </div>
                        </article>
                        <?php endif; ?>
                    </div>

                    <ul class="ia-contact-trust list-unstyled mb-0">
                        <li><i class="bi bi-shield-check" aria-hidden="true"></i> Безопасная передача данных</li>
                        <li><i class="bi bi-clock" aria-hidden="true"></i> Ответ в рабочее время</li>
                        <li><i class="bi bi-chat-heart" aria-hidden="true"></i> Помощь покупателям и продавцам</li>
                    </ul>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="ia-contact-form-panel ia-form-surface">
                    <div class="ia-contact-form-head">
                        <h2 class="h5 mb-1">Написать сообщение</h2>
                        <p class="small text-secondary mb-0">Заполните форму — мы получим обращение в панели администратора.</p>
                    </div>

                    <?php if ($msg = ia_flash('pub_ok')): ?>
                        <div class="alert alert-success ia-contact-alert mb-0 mt-3"><?= ia_h((string) $msg) ?></div>
                    <?php endif; ?>
                    <?php if ($msg = ia_flash('pub_error')): ?>
                        <div class="alert alert-danger ia-contact-alert mb-0 mt-3"><?= ia_h((string) $msg) ?></div>
                    <?php endif; ?>

                    <form method="post" class="ia-contact-form mt-4">
                        <input type="hidden" name="_csrf" value="<?= ia_h(Csrf::token()) ?>">
                        <input type="hidden" name="action" value="contact">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="contactName">Ваше имя</label>
                                <input type="text" name="from_name" id="contactName" class="form-control" required maxlength="120" autocomplete="name" placeholder="Как к вам обращаться">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="contactEmail">Email <span class="text-secondary fw-normal">(необязательно)</span></label>
                                <input type="email" name="from_email" id="contactEmail" class="form-control" maxlength="255" autocomplete="email" placeholder="you@example.com">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="contactMessage">Сообщение</label>
                                <textarea name="message" id="contactMessage" class="form-control" rows="5" required placeholder="Опишите ваш вопрос или предложение…"></textarea>
                            </div>
                            <div class="col-12 d-grid d-sm-flex">
                                <button type="submit" class="btn ia-btn-accent ia-contact-submit px-4">
                                    <i class="bi bi-send me-2" aria-hidden="true"></i>Отправить
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require IA_ROOT . '/includes/partials/site-footer.php'; ?>
