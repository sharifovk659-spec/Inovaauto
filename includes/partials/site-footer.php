<?php

declare(strict_types=1);

$pdo = ia_db();
$companyText = ia_site_setting_get($pdo, 'footer_company_text', 'InnovaAuto — маркетплейс автомобилей в Таджикистане.');
$social = [
    'Instagram' => ia_site_setting_get($pdo, 'social_instagram', ''),
    'Telegram' => ia_site_setting_get($pdo, 'social_telegram', ''),
    'Facebook' => ia_site_setting_get($pdo, 'social_facebook', ''),
    'YouTube' => ia_site_setting_get($pdo, 'social_youtube', ''),
];
$chatUnread = 0;
$cu = ia_platform_current_user();
if ($cu !== null) {
    $layoutState = ia_pub_layout_state(ia_db(), $cu);
    $chatUnread = (int) ($layoutState['chat_unread'] ?? 0);
}
?>
</main>
<?php
$socialIconMap = [
    'Instagram' => [
        'slug' => 'instagram',
        'filled' => true,
        'svg' => '<defs><linearGradient id="ia-footer-ig" x1="0%" y1="100%" x2="100%" y2="0%"><stop offset="0%" stop-color="#f09433"/><stop offset="25%" stop-color="#e6683c"/><stop offset="50%" stop-color="#dc2743"/><stop offset="75%" stop-color="#cc2366"/><stop offset="100%" stop-color="#bc1888"/></linearGradient></defs><path fill="url(#ia-footer-ig)" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/>',
    ],
    'Telegram' => [
        'slug' => 'telegram',
        'filled' => true,
        'svg' => '<path fill="currentColor" d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>',
    ],
    'Facebook' => [
        'slug' => 'facebook',
        'svg' => '<path d="M14 21v-7.5h2.5l.4-3H14V8.4c0-.85.24-1.43 1.45-1.43H16.9V4.2c-.26-.04-1.16-.12-2.2-.12-2.2 0-3.7 1.34-3.7 3.8V10.5H8.5v3h2.5V21H14Z"/>',
    ],
    'YouTube' => [
        'slug' => 'youtube',
        'svg' => '<path d="M20.8 8.2a2.2 2.2 0 0 0-1.55-1.55C17.5 6.3 12 6.3 12 6.3s-5.5 0-7.25.35A2.2 2.2 0 0 0 3.2 8.2 23 23 0 0 0 3 12a23 23 0 0 0 .2 3.8 2.2 2.2 0 0 0 1.55 1.55C6.5 17.7 12 17.7 12 17.7s5.5 0 7.25-.35a2.2 2.2 0 0 0 1.55-1.55A23 23 0 0 0 21 12a23 23 0 0 0-.2-3.8Z"/><path d="m10.5 15.1 5.5-3.1-5.5-3.1v6.2Z"/>',
    ],
];

$footerBrandTitle = trim((string) ia_site_setting_get($pdo, 'footer_brand_title', 'Innovaauto.com'));
if ($footerBrandTitle === '') {
    $footerBrandTitle = 'Innovaauto.com';
}

$contactEmail = trim((string) ia_site_setting_get($pdo, 'contact_email', ''));
$contactAddress = trim((string) ia_site_setting_get($pdo, 'contact_address', ''));
if ($contactEmail === '') {
    $contactEmail = 'innovaautoofficial@gmail.com';
}
if ($contactAddress === '') {
    $contactAddress = 'Душанбе, Тоҷикистон';
}
$contactAddressShort = $contactAddress;
if (str_contains($contactAddress, ',')) {
    $contactAddressShort = trim(explode(',', $contactAddress, 2)[0]);
}
?>
<footer class="ia-footer ia-footer-pro mt-auto">
    <div class="container ia-container ia-footer-main">
        <div class="ia-footer-grid">
            <div class="ia-footer-col ia-footer-col--brand ia-footer-col--brand-desktop">
                <div class="ia-footer-brand-card">
                    <a class="ia-footer-brand-link" href="<?= ia_h(ia_public_url('index.php')) ?>">
                        <span class="ia-footer-brand-mark">IA</span>
                        <span class="ia-footer-brand-title"><?= ia_h($footerBrandTitle) ?></span>
                    </a>
                    <p class="ia-footer-text"><?= ia_h($companyText) ?></p>
                </div>
            </div>

            <div class="ia-footer-mobile-panel">
            <div class="ia-footer-links-row">
            <div class="ia-footer-col ia-footer-col--company">
                <div class="ia-footer-heading">О КОМПАНИИ</div>
                <ul class="ia-footer-list">
                    <li><a href="<?= ia_h(ia_public_url('about.php')) ?>">О нас</a></li>
                    <li><a href="<?= ia_h(ia_public_url('contact.php')) ?>">Поддержка</a></li>
                    <li><a href="<?= ia_h(ia_public_url('blog.php')) ?>">Блог</a></li>
                    <li><a href="<?= ia_h(ia_public_url('contact.php')) ?>">Контакты</a></li>
                </ul>
            </div>

            <div class="ia-footer-col ia-footer-col--contacts">
                <div class="ia-footer-heading">КОНТАКТЫ</div>
                <ul class="ia-footer-contact-list">
                    <li>
                        <span class="ia-footer-contact-ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        </span>
                        <a class="ia-footer-contact-email" href="mailto:<?= ia_h($contactEmail) ?>" title="<?= ia_h($contactEmail) ?>">
                            <span class="ia-footer-email-full"><?= ia_h($contactEmail) ?></span>
                            <span class="ia-footer-email-short">Email</span>
                        </a>
                    </li>
                    <li>
                        <span class="ia-footer-contact-ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-4.35 7-11a7 7 0 1 0-14 0c0 6.65 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                        </span>
                        <span class="ia-footer-contact-addr ia-footer-contact-addr--full"><?= ia_h($contactAddress) ?></span>
                        <span class="ia-footer-contact-addr ia-footer-contact-addr--short"><?= ia_h($contactAddressShort) ?></span>
                    </li>
                </ul>
            </div>
            </div>

            <div class="ia-footer-col ia-footer-col--social">
                <div class="ia-footer-heading">МЫ В СОЦСЕТЯХ</div>
                <div class="ia-footer-social" role="list" aria-label="Социальные сети">
                    <?php foreach ($social as $label => $url): ?>
                        <?php
                        if (!in_array($label, ['Instagram', 'Telegram'], true)) {
                            continue;
                        }
                        $url = trim((string) $url);
                        $icon = $socialIconMap[$label] ?? null;
                        $slug = is_array($icon) ? (string) ($icon['slug'] ?? '') : '';
                        $svgInner = is_array($icon) ? (string) ($icon['svg'] ?? '') : '';
                        $btnClass = 'ia-footer-social-btn';
                        if ($slug !== '') {
                            $btnClass .= ' ia-footer-social-btn--' . $slug;
                        }
                        $iconFilled = is_array($icon) && !empty($icon['filled']);
                        $svgAttrs = $iconFilled
                            ? 'fill="currentColor"'
                            : 'fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"';
                        ?>
                        <?php if ($url !== ''): ?>
                            <a class="<?= ia_h($btnClass) ?>" role="listitem" href="<?= ia_h($url) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= ia_h($label) ?>" title="<?= ia_h($label) ?>">
                                <?php if ($svgInner !== ''): ?>
                                    <svg class="ia-footer-social-ico" viewBox="0 0 24 24" width="22" height="22" <?= $svgAttrs ?> aria-hidden="true"><?= $svgInner ?></svg>
                                <?php else: ?>
                                    <span class="ia-footer-social-letter"><?= ia_h(mb_substr($label, 0, 1)) ?></span>
                                <?php endif; ?>
                                <span class="ia-footer-social-label"><?= ia_h($label) ?></span>
                            </a>
                        <?php else: ?>
                            <span class="<?= ia_h($btnClass) ?> ia-footer-social-btn--static" role="listitem" aria-label="<?= ia_h($label) ?>" title="<?= ia_h($label) ?>">
                                <?php if ($svgInner !== ''): ?>
                                    <svg class="ia-footer-social-ico" viewBox="0 0 24 24" width="22" height="22" <?= $svgAttrs ?> aria-hidden="true"><?= $svgInner ?></svg>
                                <?php else: ?>
                                    <span class="ia-footer-social-letter"><?= ia_h(mb_substr($label, 0, 1)) ?></span>
                                <?php endif; ?>
                                <span class="ia-footer-social-label"><?= ia_h($label) ?></span>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            </div>
        </div>

        <div class="ia-footer-divider" aria-hidden="true"></div>

        <div class="ia-footer-bottom">
            <div class="ia-footer-bottom-inner">
                <p class="ia-footer-extra">
                    <a class="ia-footer-extra-link" href="<?= ia_h(ia_public_url('privacy.php')) ?>">Политика конфиденциальности</a>
                </p>
                <p class="ia-footer-credit">
                    <a class="ia-footer-extra-link" href="https://komron.inovaauto.com" target="_blank" rel="noopener noreferrer">Разработка сайта — komron.inovaauto.com</a>
                </p>
            </div>
        </div>
    </div>
</footer>
<?php require IA_ROOT . '/includes/partials/mobile-tab-bar.php'; ?>
<?php if ($cu !== null): ?>
    <?php require IA_ROOT . '/includes/partials/chat-fab.php'; ?>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous" defer></script>
<script defer>
(function () {
  var KEY = 'ia_theme_pref';
  var VALID = { light: 1, dark: 1, sepia: 1, system: 1 };
  var root = document.documentElement;

  function getPref() {
    try {
      var p = localStorage.getItem(KEY);
      if (VALID[p]) return p;
    } catch (e) {}
    return 'system';
  }

  function palette(p) {
    if (p === 'sepia') return 'sepia';
    if (p === 'light') return 'light';
    if (p === 'dark') return 'dark';
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  function bsTheme(p) {
    return palette(p) === 'dark' ? 'dark' : 'light';
  }

  function apply() {
    var p = getPref();
    var pal = palette(p);
    root.setAttribute('data-ia-theme-pref', p);
    root.setAttribute('data-ia-palette', pal);
    root.setAttribute('data-bs-theme', bsTheme(p));

    var tc = pal === 'dark' ? '#050b18' : (pal === 'sepia' ? '#f3ebd9' : '#f8fafc');
    document.querySelectorAll('meta[name="theme-color"]').forEach(function (m) {
      m.setAttribute('content', tc);
    });

    document.querySelectorAll('[data-ia-theme]').forEach(function (el) {
      el.classList.toggle('active', el.getAttribute('data-ia-theme') === p);
    });

    var labels = {
      light: 'Тема: светлая (нажмите для следующей)',
      dark: 'Тема: тёмная (нажмите для следующей)',
      sepia: 'Тема: тёплая сепия (нажмите для следующей)',
      system: 'Тема: как в системе (нажмите для следующей)'
    };
    document.querySelectorAll('.ia-theme-cycle-btn').forEach(function (btn) {
      btn.setAttribute('aria-label', labels[p] || labels.system);
      btn.title = labels[p] || labels.system;
    });
  }

  var THEME_ORDER = ['light', 'dark', 'sepia', 'system'];

  function cycleTheme() {
    var cur = getPref();
    var i = THEME_ORDER.indexOf(cur);
    if (i < 0) i = THEME_ORDER.length - 1;
    var next = THEME_ORDER[(i + 1) % THEME_ORDER.length];
    window.iaSetTheme(next);
  }

  window.iaSetTheme = function (pref) {
    if (!VALID[pref]) return;
    try {
      localStorage.setItem(KEY, pref);
    } catch (e) {}
    apply();
  };

  document.querySelectorAll('.ia-theme-cycle-btn').forEach(function (cycleBtn) {
    cycleBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      cycleTheme();
    });
  });

  document.addEventListener('click', function (e) {
    var t = e.target && e.target.closest ? e.target.closest('[data-ia-theme]') : null;
    if (!t || t.disabled) return;
    var val = t.getAttribute('data-ia-theme');
    if (!VALID[val]) return;
    e.preventDefault();
    window.iaSetTheme(val);
  });

  var mq = window.matchMedia('(prefers-color-scheme: dark)');
  function onOsTheme() {
    if (getPref() === 'system') apply();
  }
  if (mq.addEventListener) mq.addEventListener('change', onOsTheme);
  else if (mq.addListener) mq.addListener(onOsTheme);

  apply();
})();
</script>
<?php if ($cu !== null): ?>
<script defer>
(function () {
  var fab = document.getElementById('iaChatFab');
  var badge = document.getElementById('iaChatFabBadge');
  if (!fab || !badge) return;

  function setVisible(show, count) {
    fab.classList.toggle('ia-chat-fab--visible', show);
    fab.classList.toggle('ia-chat-fab--unread', show);
    fab.hidden = !show;
    badge.textContent = String(count);
    badge.classList.toggle('d-none', !show);
    fab.setAttribute('aria-label', show ? ('Сообщения (' + count + ' новых)') : 'Сообщения');
  }

  function tick() {
    fetch('<?= ia_h(ia_public_url('chat-poll.php')) ?>', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data || typeof data.unread_count === 'undefined') return;
        var n = Number(data.unread_count || 0);
        setVisible(n > 0, n);
      })
      .catch(function () {});
  }

  tick();
  setInterval(tick, 8000);
})();
</script>
<?php endif; ?>
<script defer>
(function () {
  var SUPPORTS_HOVER = window.matchMedia ? window.matchMedia('(hover: hover)').matches : true;
  if (!SUPPORTS_HOVER) return;

  function setupCard(card) {
    if (card.dataset.iaHoverInit === '1') return;
    var raw = card.getAttribute('data-thumbs');
    if (!raw) return;
    var thumbs;
    try { thumbs = JSON.parse(raw); } catch (e) { return; }
    if (!Array.isArray(thumbs) || thumbs.length < 2) return;
    var img = card.querySelector('.ia-listing-card-img');
    if (!img) return;
    var dots = card.querySelectorAll('.ia-card-hover-dot');
    var originalSrc = img.getAttribute('src') || thumbs[0];

    // Preload all alternative photos once on first hover.
    var preloaded = false;
    function preload() {
      if (preloaded) return;
      preloaded = true;
      thumbs.forEach(function (src) {
        var im = new Image();
        im.decoding = 'async';
        im.src = src;
      });
    }

    var current = 0;
    function setIndex(i) {
      if (i === current) return;
      var n = thumbs.length;
      i = ((i % n) + n) % n;
      current = i;
      img.src = thumbs[i];
      if (dots && dots.length) {
        dots.forEach(function (d, di) { d.classList.toggle('is-active', di === i); });
      }
    }

    function pickByPointer(clientX) {
      var rect = card.getBoundingClientRect();
      if (rect.width <= 0) return;
      var x = clientX - rect.left;
      if (x < 0) x = 0;
      if (x > rect.width) x = rect.width;
      var n = thumbs.length;
      var idx = Math.floor((x / rect.width) * n);
      if (idx >= n) idx = n - 1;
      setIndex(idx);
    }

    var autoTimer = null;
    function startAuto() {
      stopAuto();
      autoTimer = setInterval(function () { setIndex(current + 1); }, 1100);
    }
    function stopAuto() {
      if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
    }

    card.addEventListener('mouseenter', function () {
      preload();
      startAuto();
    });
    card.addEventListener('mousemove', function (ev) {
      stopAuto();
      pickByPointer(ev.clientX);
    });
    card.addEventListener('mouseleave', function () {
      stopAuto();
      setIndex(0);
      img.src = originalSrc;
    });
    card.addEventListener('focusin', function () { preload(); startAuto(); });
    card.addEventListener('focusout', function () { stopAuto(); setIndex(0); img.src = originalSrc; });

    card.dataset.iaHoverInit = '1';
  }

  function init(root) {
    (root || document).querySelectorAll('.ia-card-hover.has-hover-thumbs[data-thumbs]').forEach(setupCard);
  }
  init(document);
  // Re-init after dynamic insertions (defensive)
  document.addEventListener('ia:rescan-cards', function () { init(document); });
})();
</script>
</body>
</html>
