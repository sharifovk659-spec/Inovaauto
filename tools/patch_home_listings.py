from pathlib import Path

p = Path(__file__).resolve().parents[1] / "index.php"
t = p.read_text(encoding="utf-8")

marker_start = '                            <div class="p-3 flex-grow-1 d-flex flex-column">\n                                <a class="text-decoration-none ia-listing-title-link'
marker_end = '                            </motion>\n                        </article>'

# Use exact file content between latest listings body start and article close
start = t.find('        <?php else: ?>\n            <div class="row g-4">')
if start < 0:
    raise SystemExit("row g-4 not found")
chunk = t[start:]
idx = chunk.find('                            <div class="p-3 flex-grow-1 d-flex flex-column">')
if idx < 0:
    raise SystemExit("listing body not found")
chunk2 = chunk[idx:]
end_rel = chunk2.find('                        </article>')
if end_rel < 0:
    raise SystemExit("article end not found")
old = chunk2[:end_rel]

new = """                            <div class="p-3 flex-grow-1 d-flex flex-column ia-listing-card-body">
                                <a class="text-decoration-none ia-listing-title-link fw-semibold d-block mb-1 ia-card-title-clamp" href="<?= ia_h(ia_public_url('car.php?id=' . (int) $row['id'])) ?>"><?= ia_h((string) $row['brand'] . ' ' . (string) $row['model']) ?></a>
                                <?php $latAvail = ia_listing_availability_normalize((string) ($row['availability'] ?? '')); ?>
                                <div class="ia-listing-card-meta mb-2">
                                    <span class="ia-badge-availability <?= $latAvail === 'on_order' ? 'ia-badge-availability--on-order' : 'ia-badge-availability--in-stock' ?>"><?= ia_h(ia_listing_availability_label_ru($latAvail)) ?></span>
                                    <span class="ia-price ia-price-card"><?= ia_h(ia_listing_format_price((float) $row['price'], (string) ($row['currency'] ?? 'TJS'))) ?></span>
                                </div>
                                <?php
                                $ly = isset($row['model_year']) ? (int) $row['model_year'] : 0;
                                $lyOk = $ly >= 1950;
                                $lc = trim((string) ($row['city'] ?? ''));
                                $latTr = ia_listing_transmission_label_ru((string) ($row['transmission'] ?? ''));
                                ?>
                                <dl class="row ia-listing-specs-dl small mb-0 mt-auto gx-0 gy-1">
                                    <dt class="col-5 text-secondary">Год</dt>
                                    <dd class="col-7 mb-0"><?= ia_h($lyOk ? (string) $ly : '—') ?></dd>
                                    <dt class="col-5 text-secondary">Пробег</dt>
                                    <dd class="col-7 mb-0"><?= ia_h(ia_listing_mileage_label_ru($row['mileage_km'] ?? 0)) ?></dd>
                                    <dt class="col-5 text-secondary">Коробка</dt>
                                    <dd class="col-7 mb-0"><?= ia_h($latTr !== '' ? $latTr : '—') ?></dd>
                                    <dt class="col-5 text-secondary">Город</dt>
                                    <dd class="col-7 mb-0"><?= ia_h($lc !== '' ? $lc : '—') ?></dd>
                                    <dt class="col-5 text-secondary">Дата</dt>
                                    <dd class="col-7 mb-0"><?= ia_h(ia_listing_pub_date_label((string) ($row['created_at'] ?? ''))) ?></dd>
                                </dl>
                            </div>
"""

if "ia-listing-card-body" in old:
    print("Already patched")
    raise SystemExit(0)

t2 = t.replace(old, new, 1)
if t2 == t:
    raise SystemExit("No replacement made; old len=%d" % len(old))
p.write_text(t2, encoding="utf-8", newline="\n")
print("OK: latest listings card body updated")
