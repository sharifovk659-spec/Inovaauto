from pathlib import Path

path = Path(r'c:\xampp\htdocs\Auto 1\add-listing.php')
text = path.read_text(encoding='utf-8')

old = """                                <div id="iaPhotoSlotBanner" class="alert alert-warning d-none mb-3" role="alert"></motion>
                                <input type="file" name="listing_media[]" id="fldListingMedia" class="d-none" multiple accept="image/jpeg,image/png,image/webp">
                                <div class="ia-photo-slot-grid" id="iaPhotoSlotGrid">
                                    <?php foreach (ia_listing_photo_slot_labels_ru() as $slotIdx => $slotLabel):
                                        $slotHint = ia_listing_photo_slot_hints_ru()[$slotIdx] ?? '';
                                        ?>
                                        <div class="ia-photo-slot" data-slot="<?= (int) $slotIdx ?>">
                                            <div class="ia-photo-slot-head">
                                                <span class="ia-photo-slot-num"><?= (int) $slotIdx + 1 ?></span>
                                                <span class="ia-photo-slot-label"><?= ia_h($slotLabel) ?></span>
                                            </div>
                                            <p class="ia-photo-slot-hint small text-secondary mb-0"><?= ia_h($slotHint) ?></p>
                                            <motion class="ia-photo-slot-stage">
                                                <div class="ia-photo-slot-example" aria-hidden="true"><?= ia_listing_photo_slot_guide_svg((int) $slotIdx) ?></div>
                                                <button type="button" class="ia-photo-slot-btn" data-slot="<?= (int) $slotIdx ?>" aria-label="<?= ia_h('Снять фото: ' . $slotLabel) ?>">
                                                    <i class="bi bi-camera-fill" aria-hidden="true"></i>
                                                    <span>Снять</span>
                                                </button>
                                                <input type="file" class="ia-photo-slot-input d-none" data-slot="<?= (int) $slotIdx ?>" accept="image/jpeg,image/png,image/webp" capture="environment">
                                                <div class="ia-photo-slot-preview d-none" data-slot="<?= (int) $slotIdx ?>">
                                                    <img src="" alt="">
                                                    <button type="button" class="ia-photo-slot-remove" data-slot="<?= (int) $slotIdx ?>" aria-label="Удалить фото">×</button>
                                                </div>
                                            </div>
                                            <div class="ia-photo-slot-error small text-danger d-none" data-slot="<?= (int) $slotIdx ?>" role="alert"></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>"""

old = old.replace('<motion id="iaPhotoSlotBanner"', '<div id="iaPhotoSlotBanner"')
old = old.replace('role="alert"></motion>', 'role="alert"></div>')
old = old.replace('<motion class="ia-photo-slot-stage">', '<div class="ia-photo-slot-stage">')

new = """                                <div id="iaPhotoSlotBanner" class="alert alert-warning d-none mb-3" role="alert"></div>
                                <input type="file" name="listing_media[]" id="fldListingMedia" class="d-none" multiple accept="image/jpeg,image/png,image/webp">
                                <div class="ia-photo-workspace row g-4 align-items-start">
                                    <div class="col-lg-7 col-xl-8">
                                        <div class="ia-photo-slot-grid" id="iaPhotoSlotGrid">
                                            <?php foreach (ia_listing_photo_slot_labels_ru() as $slotIdx => $slotLabel):
                                                $slotHint = ia_listing_photo_slot_hints_ru()[$slotIdx] ?? '';
                                                ?>
                                                <article class="ia-photo-slot" data-slot="<?= (int) $slotIdx ?>">
                                                    <header class="ia-photo-slot-head">
                                                        <span class="ia-photo-slot-num"><?= (int) $slotIdx + 1 ?></span>
                                                        <motion class="ia-photo-slot-titles">
                                                            <h3 class="ia-photo-slot-label"><?= ia_h($slotLabel) ?></h3>
                                                            <p class="ia-photo-slot-hint mb-0"><?= ia_h($slotHint) ?></p>
                                                        </div>
                                                    </header>
                                                    <div class="ia-photo-slot-icon" aria-hidden="true"><?= ia_listing_photo_slot_guide_svg((int) $slotIdx) ?></div>
                                                    <div class="ia-photo-slot-stage">
                                                        <div class="ia-photo-slot-example" aria-hidden="true"><?= ia_listing_photo_slot_guide_svg((int) $slotIdx) ?></div>
                                                        <input type="file" class="ia-photo-slot-input d-none" data-slot="<?= (int) $slotIdx ?>" accept="image/jpeg,image/png,image/webp" capture="environment">
                                                        <div class="ia-photo-slot-preview d-none" data-slot="<?= (int) $slotIdx ?>">
                                                            <img src="" alt="">
                                                            <button type="button" class="ia-photo-slot-remove" data-slot="<?= (int) $slotIdx ?>" aria-label="Удалить фото">×</button>
                                                        </div>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-primary w-100 ia-photo-slot-btn" data-slot="<?= (int) $slotIdx ?>" aria-label="<?= ia_h('Снять фото: ' . $slotLabel) ?>">
                                                        <i class="bi bi-camera-fill" aria-hidden="true"></i>
                                                        <span>Снять фото</span>
                                                    </button>
                                                    <div class="ia-photo-slot-status" data-slot="<?= (int) $slotIdx ?>" data-status="pending">
                                                        <span class="ia-photo-slot-status-label">Ожидает</span>
                                                    </div>
                                                    <div class="ia-photo-slot-error small text-danger d-none" data-slot="<?= (int) $slotIdx ?>" role="alert"></div>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <aside class="col-lg-5 col-xl-4 ia-photo-preview-col">
                                        <div class="ia-photo-preview-panel ia-form-surface">
                                            <h3 class="h6 mb-2">Превью загруженных фото</h3>
                                            <p class="small text-secondary mb-2" id="iaAddPreviewEmpty">Снимите 10 ракурсов — здесь появятся миниатюры принятых фото.</p>
                                            <div class="small text-secondary mb-2 d-none" id="iaPrimaryHint">Главное фото — ракурс «Спереди».</div>
                                            <div class="ia-add-thumb-grid d-none" id="iaAddThumbGrid" role="list" aria-label="Миниатюры фото"></div>
                                        </div>
                                    </aside>
                                </div>"""

new = new.replace('<motion class="ia-photo-slot-titles">', '<div class="ia-photo-slot-titles">')

if old not in text:
    raise SystemExit('OLD block not found')

text = text.replace(old, new, 1)

sidebar_old = """                <div class="card p-3 ia-form-surface ia-add-side-card ia-add-preview-panel">
                    <h3 class="h6 mb-2">Превью</h3>
                    <p class="small text-secondary mb-2" id="iaAddPreviewEmpty">Снимите 10 ракурсов в блоке «Фото автомобиля» — здесь появятся миниатюры. Видео можно добавить отдельно.</p>
                    <div class="small text-secondary mb-2 d-none" id="iaPrimaryHint">Главное фото — ракурс «Спереди».</div>
                    <motion class="ia-add-thumb-grid d-none" id="iaAddThumbGrid" role="list" aria-label="Миниатюры фото"></div>
                    <div class="d-none mt-2" id="iaAddVideoBox">
                        <div class="small text-secondary mb-1">Видео</div>
                        <div class="ia-add-video-shell">
                            <video id="iaAddPreviewVideo" controls playsinline preload="metadata"></video>
                        </div>
                    </div>
                </div>"""

sidebar_old = sidebar_old.replace('<motion class="ia-add-thumb-grid', '<div class="ia-add-thumb-grid')

sidebar_new = """                <motion class="card p-3 ia-form-surface ia-add-side-card ia-add-preview-panel">
                    <h3 class="h6 mb-2">Видео</h3>
                    <p class="small text-secondary mb-2">Превью видео появится здесь после выбора файла в разделе «Видео».</p>
                    <div class="d-none mt-2" id="iaAddVideoBox">
                        <div class="ia-add-video-shell">
                            <video id="iaAddPreviewVideo" controls playsinline preload="metadata"></video>
                        </div>
                    </div>
                </div>"""

sidebar_new = sidebar_new.replace('<motion class="card', '<motion class="card').replace('<motion class="card', '<div class="card')

if sidebar_old in text:
    text = text.replace(sidebar_old, sidebar_new, 1)

path.write_text(text, encoding='utf-8')
print('html patched')
