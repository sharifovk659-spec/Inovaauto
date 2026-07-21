<?php

declare(strict_types=1);

/** @var string $pageTitle */
$title = $pageTitle ?? 'InnovaAuto Admin';
$iaAdminShell = $iaAdminShell ?? true;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <script>
(function () {
  try {
    var KEY = 'ia_theme_pref';
    var pref = localStorage.getItem(KEY);
    if (pref !== 'light' && pref !== 'dark' && pref !== 'sepia' && pref !== 'system') pref = 'system';
    function palette(p) {
      if (p === 'sepia') return 'sepia';
      if (p === 'light') return 'light';
      if (p === 'dark') return 'dark';
      return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    function bsTheme(p) {
      return palette(p) === 'dark' ? 'dark' : 'light';
    }
    var root = document.documentElement;
    root.setAttribute('data-ia-theme-pref', pref);
    root.setAttribute('data-ia-palette', palette(pref));
    root.setAttribute('data-bs-theme', bsTheme(pref));
  } catch (e) {}
})();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= ia_h($title) ?> — InnovaAuto</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= ia_h(function_exists('ia_admin_css_href') ? ia_admin_css_href() : ia_admin_asset_url('assets/admin.css') . '?v=18') ?>">
</head>
<body class="<?= $iaAdminShell ? 'ia-admin-body' : 'ia-login-body' ?>">
<?php if (!$iaAdminShell): ?>
<div class="ia-login-shell d-flex flex-column min-vh-100">
<?php endif; ?>
