<?php

declare(strict_types=1);

$iaAdminShell = $iaAdminShell ?? true;
if (!$iaAdminShell) {
    echo '</div>'; // ia-login-shell
} else {
    echo '</div></div>'; // ia-workspace, ia-admin-root
}
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
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
    root.setAttribute('data-ia-theme-pref', p);
    root.setAttribute('data-ia-palette', palette(p));
    root.setAttribute('data-bs-theme', bsTheme(p));

    document.querySelectorAll('[data-ia-theme]').forEach(function (el) {
      el.classList.toggle('active', el.getAttribute('data-ia-theme') === p);
    });

    var btn = document.getElementById('iaThemeCycleBtn');
    if (btn) {
      var labels = {
        light: 'Тема: светлая (нажмите для следующей)',
        dark: 'Тема: тёмная (нажмите для следующей)',
        sepia: 'Тема: тёплая сепия (нажмите для следующей)',
        system: 'Тема: как в системе (нажмите для следующей)'
      };
      btn.setAttribute('aria-label', labels[p] || labels.system);
      btn.title = labels[p] || labels.system;
    }
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

  var cycleBtn = document.getElementById('iaThemeCycleBtn');
  if (cycleBtn) {
    cycleBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      cycleTheme();
    });
  }

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
<?php if ($iaAdminShell): ?>
<script>
(function () {
  var sb = document.getElementById('iaSidebar');
  var bd = document.getElementById('iaSidebarBackdrop');
  if (!sb || !bd) return;
  function close() {
    sb.classList.remove('is-open');
    bd.classList.remove('show');
    document.body.style.overflow = '';
  }
  function open() {
    sb.classList.add('is-open');
    bd.classList.add('show');
    document.body.style.overflow = 'hidden';
  }
  document.querySelectorAll('[data-ia-sidebar-toggle]').forEach(function (el) {
    el.addEventListener('click', function () {
      if (sb.classList.contains('is-open')) close(); else open();
    });
  });
  bd.addEventListener('click', close);
  window.addEventListener('resize', function () {
    if (window.matchMedia('(min-width: 992px)').matches) close();
  });
})();
</script>
<?php endif; ?>
</body>
</html>
