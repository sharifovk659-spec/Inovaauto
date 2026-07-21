from pathlib import Path

root = Path(__file__).resolve().parents[1]
m = "motion"

idx = root / "index.php"
ti = idx.read_text(encoding="utf-8")
ti = ti.replace("<" + m + ' class="row g-3 ia-why-grid">', '<motion class="row g-3 ia-why-grid">')
ti = ti.replace("<" + m + ' class="row g-3 ia-why-grid">', '<div class="row g-3 ia-why-grid">')
idx.write_text(ti, encoding="utf-8", newline="\n")

car = root / "car.php"
t = car.read_text(encoding="utf-8")
needle = '<motion class="col-lg-5">'
needle = '<div class="col-lg-5">\n                <div class="d-flex justify-content-between'
repl = '<div class="col-lg-5 ia-car-detail-panel">\n                <div class="d-flex justify-content-between'
if "ia-car-detail-panel" not in t and needle in t:
    t = t.replace(needle, repl, 1)
if "ia-car-title" not in t:
    t = t.replace('<h1 class="h3 mb-0">', '<h1 class="h3 mb-0 ia-car-title">', 1)
car.write_text(t, encoding="utf-8", newline="\n")
print("OK")
