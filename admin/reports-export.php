<?php

declare(strict_types=1);

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/bootstrap.php';
require_once IA_ROOT . '/includes/report_export.php';

ia_require_section('reports');

$type = (string) ($_GET['type'] ?? '');
$format = (string) ($_GET['format'] ?? '');
$types = ['users', 'listings', 'revenue', 'vip'];
$formats = ['csv', 'html'];
if (!in_array($type, $types, true) || !in_array($format, $formats, true)) {
    ia_flash('reports_error', 'Некорректный тип отчёта.');
    ia_redirect(ia_admin_url('reports.php'));
}

ia_report_run_export($type, $format);
