from pathlib import Path

p = Path(__file__).resolve().parents[1] / "catalog.php"
t = p.read_text(encoding="utf-8")

replacements = [
    (
        '<section class="py-5 ia-page-section">',
        '<section class="py-4 py-lg-5 ia-page-section ia-catalog-page-section">',
    ),
    (
        '<h1 class="h3 mb-4">Каталог</h1>\n        <motion class="row g-4 align-items-start">',
        '<h1 class="h3 mb-3 mb-lg-4 ia-catalog-page-title">Каталог</h1>\n        <div class="row g-3 g-lg-4 align-items-start ia-catalog-layout">',
    ),
    (
        '<aside class="col-lg-3">',
        '<aside class="col-lg-3 ia-catalog-aside">',
    ),
    (
        '<form class="card p-3 p-md-4 ia-form-surface" method="get" action="">',
        '<form class="card p-3 p-md-4 ia-form-surface ia-catalog-filters" method="get" action="">',
    ),
    (
        '<h2 class="h6 text-uppercase text-secondary mb-3">Фильтры</h2>',
        '<h2 class="h6 text-uppercase text-secondary mb-0 ia-catalog-filters-title">Фильтры</h2>',
    ),
]

field_map = [
    ('<div class="mb-3">\n                        <label class="form-label">Бренд</label>', 'ia-catalog-field--full', 'Бренд'),
    ('<motion class="mb-3">\n                        <label class="form-label">Модель</label>', 'ia-catalog-field--full', 'Модель'),
    ('<div class="mb-3">\n                        <label class="form-label">Модель</label>', 'ia-catalog-field--full', 'Модель'),
    ('<div class="mb-3">\n                        <label class="form-label">Цена</label>', 'ia-catalog-field--full', 'Цена'),
    ('<div class="mb-3">\n                        <label class="form-label">Год</label>', 'ia-catalog-field--half', 'Год'),
    ('<div class="mb-3">\n                        <label class="form-label">Пробег</label>', 'ia-catalog-field--half', 'Пробег'),
    ('<motion class="mb-3">\n                        <label class="form-label">Тип топлива</label>', 'ia-catalog-field--half', 'Тип топлива'),
    ('<div class="mb-3">\n                        <label class="form-label">Тип топлива</label>', 'ia-catalog-field--half', 'Тип топлива'),
    ('<div class="mb-3">\n                        <label class="form-label">Коробка</label>', 'ia-catalog-field--half', 'Коробка'),
    ('<div class="mb-3">\n                        <label class="form-label">Город</label>', 'ia-catalog-field--full', 'Город'),
    ('<div class="mb-3">\n                        <label class="form-label">Наличие</label>', 'ia-catalog-field--half', 'Наличие'),
    ('<div class="mb-3">\n                        <label class="form-label">Сортировка</label>', 'ia-catalog-field--half', 'Сортировка'),
    ('<div class="mb-3">\n                        <label class="form-label">Поиск</label>', 'ia-catalog-field--full', 'Поиск'),
]

for old, mod, label in field_map:
    old = old.replace('<motion ', '<motion ').replace('<motion ', '<div ')
    new = old.replace('<div class="mb-3">', f'<div class="mb-3 ia-catalog-field {mod}">').replace(
        '<label class="form-label">', '<label class="form-label ia-catalog-label">'
    )
    if old in t and new not in t:
        t = t.replace(old, new, 1)

for old, new in replacements:
    old = old.replace('<motion class="row', '<motion class="row').replace('<motion class="row', '<div class="row')
    new = new.replace('<motion ', '<div ') if '<motion' in new else new
    t = t.replace(old, new, 1)

t = t.replace(
    '<div class="d-grid gap-2">',
    '<div class="d-grid gap-2 ia-catalog-field ia-catalog-field--full ia-catalog-filters-actions">',
    1,
)

p.write_text(t, encoding='utf-8', newline='\n')
print('OK')
