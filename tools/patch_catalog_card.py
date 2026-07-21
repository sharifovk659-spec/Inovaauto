from pathlib import Path

p = Path(__file__).resolve().parents[1] / "catalog.php"
t = p.read_text(encoding="utf-8")

old = """                                    <motion class="p-3 flex-grow-1 d-flex flex-column">
                                        <a class="text-decoration-none ia-listing-title-link fw-semibold d-block mb-1 ia-card-title-clamp" href="<?= ia_h(ia_public_url('car.php?id=' . (int) $row['id'])) ?>"><?= ia_h((string) $row['brand'] . ' ' . (string) $row['model']) ?></a>
                                        <?php $rowAvail = ia_listing_availability_normalize((string) ($row['availability'] ?? '')); ?>
                                        <div class="mb-2">
                                            <span class="ia-badge-availability <?= $rowAvail === 'on_order' ? 'ia-badge-availability--on-order' : 'ia-badge-availability--in-stock' ?>"><?= ia_h(ia_listing_availability_label_ru($rowAvail)) ?></span>
                                        </div>
                                        <div class="small text-secondary mb-1">№ <?= (int) $row['id'] ?> · <?= ia_h(ia_listing_pub_date_label((string) ($row['created_at'] ?? ''))) ?></div>
                                        <div class="ia-price mb-2"><?= ia_h(ia_listing_format_price((float) $row['price'], (string) ($row['currency'] ?? 'TJS'))) ?></div>
                                        <div class="small text-secondary">Год: <?= (int) ($row['model_year'] ?? 0) >= 1950 ? (int) $row['model_year'] : '—' ?></div>
                                        <div class="small text-secondary">Пробег: <?= ia_h(ia_listing_mileage_label_ru($row['mileage_km'] ?? 0)) ?></div>
                                        <?php $rowTr = ia_listing_transmission_label_ru((string) ($row['transmission'] ?? '')); ?>
                                        <div class="small text-secondary">Коробка: <?= ia_h($rowTr !== '' ? $rowTr : '—') ?></div>
                                        <div class="small text-secondary mt-auto">Город: <?= ia_h(trim((string) ($row['city'] ?? '')) !== '' ? (string) $row['city'] : '—') ?></motion>
                                    </div>"""

old = old.replace("<motion ", "<div ").replace("</motion>", "</div>")

new = """                                    <div class="p-3 flex-grow-1 d-flex flex-column ia-listing-card-body">
                                        <a class="text-decoration-none ia-listing-title-link fw-semibold d-block mb-1 ia-card-title-clamp" href="<?= ia_h(ia_public_url('car.php?id=' . (int) $row['id'])) ?>"><?= ia_h((string) $row['brand'] . ' ' . (string) $row['model']) ?></a>
                                        <?php $rowAvail = ia_listing_availability_normalize((string) ($row['availability'] ?? '')); ?>
                                        <div class="ia-listing-card-meta mb-2">
                                            <span class="ia-badge-availability <?= $rowAvail === 'on_order' ? 'ia-badge-availability--on-order' : 'ia-badge-availability--in-stock' ?>"><?= ia_h(ia_listing_availability_label_ru($rowAvail)) ?></span>
                                            <span class="ia-price ia-price-card"><?= ia_h(ia_listing_format_price((float) $row['price'], (string) ($row['currency'] ?? 'TJS'))) ?></span>
                                        </div>
                                        <motion class="ia-listing-card-id small text-secondary mb-2">№ <?= (int) $row['id'] ?> · <?= ia_h(ia_listing_pub_date_label((string) ($row['created_at'] ?? ''))) ?></div>
                                        <?php
                                        $rowY = isset($row['model_year']) ? (int) $row['model_year'] : 0;
                                        $rowYOk = $rowY >= 1950;
                                        $rowC = trim((string) ($row['city'] ?? ''));
                                        $rowTr = ia_listing_transmission_label_ru((string) ($row['transmission'] ?? ''));
                                        ?>
                                        <dl class="row ia-listing-specs-dl small mb-0 gx-0 gy-1">
                                            <dt class="col-5 text-secondary">Год</dt>
                                            <dd class="col-7 mb-0"><?= ia_h($rowYOk ? (string) $rowY : '—') ?></dd>
                                            <dt class="col-5 text-secondary">Пробег</dt>
                                            <dd class="col-7 mb-0"><?= ia_h(ia_listing_mileage_label_ru($row['mileage_km'] ?? 0)) ?></dd>
                                            <dt class="col-5 text-secondary">Коробка</dt>
                                            <dd class="col-7 mb-0"><?= ia_h($rowTr !== '' ? $rowTr : '—') ?></dd>
                                            <dt class="col-5 text-secondary">Город</dt>
                                            <dd class="col-7 mb-0"><?= ia_h($rowC !== '' ? $rowC : '—') ?></dd>
                                        </dl>
                                        <?php require IA_ROOT . '/includes/partials/listing-views-footer.php'; ?>
                                    </div>"""

new = new.replace("<motion ", "<motion ").replace("<motion class=\"ia-listing-card-id", "<div class=\"ia-listing-card-id")

if old not in t:
    # try exact from file
    start = t.find('                                    <div class="p-3 flex-grow-1 d-flex flex-column">')
    end = t.find('                                </article>', start)
    if start < 0:
        raise SystemExit("card body not found")
    old = t[start:end]
    if "listing-views-footer" in old:
        print("Already patched")
        raise SystemExit(0)

t2 = t.replace(old, new, 1) if old in t else t[:start] + new + t[end:]
if t2 == t and old not in t:
    t2 = t[:start] + new + t[end:]

p.write_text(t2, encoding="utf-8", newline="\n")
print("OK")
