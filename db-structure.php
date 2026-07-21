<?php
declare(strict_types=1);

define('IA_ROOT', __DIR__);
require_once IA_ROOT . '/includes/bootstrap.php';

$pdo = ia_db();
$st = $pdo->prepare(
    "SELECT table_name
     FROM information_schema.tables
     WHERE table_schema = current_schema()
     ORDER BY table_name ASC"
);
$st->execute();
$tables = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
$schemaSt = $pdo->prepare('SELECT current_schema()');
$schemaSt->execute();
$schemaName = (string) ($schemaSt->fetchColumn() ?: 'public');
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>DB Structure (PostgreSQL)</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; color: #111; background: #fff; }
    h1 { margin: 0 0 16px; }
    h2 { margin: 24px 0 8px; font-size: 18px; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 12px; }
    th, td { border: 1px solid #d9d9d9; padding: 6px 8px; text-align: left; font-size: 13px; }
    th { background: #f5f7fb; }
    .muted { color: #666; }
  </style>
</head>
<body>
  <h1>PostgreSQL: структура базы</h1>
  <div class="muted">Schema: <?= htmlspecialchars($schemaName) ?></div>
  <?php foreach ($tables as $table): ?>
    <?php
    $table = (string) $table;
    if (!ia_db_sql_ident_ok($table)) {
        continue;
    }
    ?>
    <h2><?= htmlspecialchars($table) ?></h2>
    <?php
    $colSt = $pdo->prepare(
        "SELECT column_name, data_type, is_nullable, column_default
         FROM information_schema.columns
         WHERE table_schema = current_schema() AND table_name = ?
         ORDER BY ordinal_position"
    );
    $colSt->execute([$table]);
    $cols = $colSt->fetchAll() ?: [];
    ?>
    <table>
      <thead>
        <tr>
          <th>Column</th>
          <th>Type</th>
          <th>Nullable</th>
          <th>Default</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($cols as $c): ?>
        <tr>
          <td><?= htmlspecialchars((string) ($c['column_name'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($c['data_type'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($c['is_nullable'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string) ($c['column_default'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endforeach; ?>
</body>
</html>
