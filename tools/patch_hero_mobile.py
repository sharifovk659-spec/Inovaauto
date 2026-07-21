from pathlib import Path

p = Path(__file__).resolve().parents[1] / "index.php"
t = p.read_text(encoding="utf-8")

start = t.find('<form class="ia-hero-search"')
end = t.find("</form>", start) + len("</form>")
if start < 0:
    raise SystemExit("form not found")

old = t[start:end]
if "ia-hero-search-grid" in old:
    print("Already patched")
    raise SystemExit(0)

new = """<form class="ia-hero-search" method="get" action="<?= ia_h($catalogUrl) ?>">
                    <div class="row g-2 ia-hero-search-grid align-items-end">
                        <motion class="col-6 col-md-6 col-xl-2 ia-hero-field ia-hero-field--brand">
                            <label class="form-label ia-hero-search-label" for="heroBrand">Бренд</label>
                            <select name="brand_id" id="heroBrand" class="form-select form-select-sm">
                                <option value="0">Все бренды</option>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?= (int) $b['id'] ?>"><?= ia_h((string) $b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-6 col-xl-2 ia-hero-field ia-hero-field--model">
                            <label class="form-label ia-hero-search-label" for="heroModel">Модель</label>
                            <select name="model_id" id="heroModel" class="form-select form-select-sm" disabled>
                                <option value="0">Все модели</option>
                            </select>
                        </div>
                        <div class="col-6 col-xl-2 ia-hero-field ia-hero-field--price-min">
                            <label class="form-label ia-hero-search-label" for="heroPmin">Цена от</label>
                            <input type="number" name="price_min" id="heroPmin" class="form-control form-control-sm" min="0" step="1000" placeholder="0" inputmode="numeric">
                        </div>
                        <div class="col-6 col-xl-2 ia-hero-field ia-hero-field--price-max">
                            <label class="form-label ia-hero-search-label" for="heroPmax">Цена до</label>
                            <input type="number" name="price_max" id="heroPmax" class="form-control form-control-sm" min="0" step="1000" placeholder="∞" inputmode="numeric">
                        </div>
                        <div class="col-6 col-md-6 col-xl-2 ia-hero-field ia-hero-field--year">
                            <label class="form-label ia-hero-search-label" for="heroYear">Год</label>
                            <input type="number" name="year" id="heroYear" class="form-control form-control-sm" min="1950" max="2100" placeholder="2020" inputmode="numeric">
                        </div>
                        <div class="col-6 col-md-6 col-xl-2 ia-hero-field ia-hero-field--city">
                            <label class="form-label ia-hero-search-label" for="heroCity">Город</label>
                            <input type="text" name="city" id="heroCity" class="form-control form-control-sm" maxlength="120" placeholder="Душанбе" autocomplete="address-level2">
                        </div>
                        <div class="col-12 col-md-6 col-xl-2 d-grid ia-hero-field ia-hero-field--submit">
                            <button type="submit" class="btn ia-btn-accent ia-hero-search-btn">Поиск</button>
                        </div>
                    </div>
                </form>"""

new = new.replace("<motion ", "<div ")

p.write_text(t[:start] + new + t[end:], encoding="utf-8", newline="\n")
print("OK: hero form updated")
