from pathlib import Path

path = Path(r"c:\xampp\htdocs\Auto 1\add-listing.php")
text = path.read_text(encoding="utf-8")

old = """                            <div class="ia-add-section-body row g-3">
                                <div class="col-12">
                                    <input type="file" name="listing_media[]" id="fldListingMedia" class="form-control" multiple accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm">
                                    <motion class="form-text">Фото: JPEG, PNG, WebP, GIF до 5 МБ. Видео: MP4 или WebM до 100 МБ. Можно выбрать несколько файлов сразу.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">URL главного фото (необязательно)</label>
                                    <input type="url" name="photo_url" class="form-control" placeholder="https://">
                                    <div class="form-text">Если не загрузили фото, можно указать ссылку на обложку.</div>
                                </div>
                            </div>"""

new = """                            <div class="ia-add-section-body">
                                <p class="small text-secondary mb-3">Снимайте с камеры телефона: 10 ракурсов для обзора 360°. Главное фото — «Спереди автомобиля». Принимаются только кадры с разрешением камеры (не слишком маленькие и не слишком большие).</p>
                                <input type="file" name="listing_media[]" id="fldListingMedia" class="d-none" multiple accept="image/jpeg,image/png,image/webp">
                                <div class="ia-photo-slot-grid" id="iaPhotoSlotGrid">
                                    <?php foreach (ia_listing_photo_slot_labels_ru() as $slotIdx => $slotLabel): ?>
                                        <div class="ia-photo-slot" data-slot="<?= (int) $slotIdx ?>">
                                            <div class="ia-photo-slot-head">
                                                <span class="ia-photo-slot-num"><?= (int) $slotIdx + 1 ?></span>
                                                <span class="ia-photo-slot-label"><?= ia_h($slotLabel) ?></span>
                                            </div>
                                            <button type="button" class="ia-photo-slot-btn" data-slot="<?= (int) $slotIdx ?>" aria-label="<?= ia_h('Снять фото: ' . $slotLabel) ?>">
                                                <i class="bi bi-camera-fill" aria-hidden="true"></i>
                                                <span>Снять</span>
                                            </button>
                                            <input type="file" class="ia-photo-slot-input d-none" data-slot="<?= (int) $slotIdx ?>" accept="image/jpeg,image/png,image/webp" capture="environment">
                                            <motion class="ia-photo-slot-preview d-none" data-slot="<?= (int) $slotIdx ?>">
                                                <img src="" alt="">
                                                <button type="button" class="ia-photo-slot-remove" data-slot="<?= (int) $slotIdx ?>" aria-label="Удалить фото">×</button>
                                            </div>
                                            <div class="ia-photo-slot-error small text-danger d-none" data-slot="<?= (int) $slotIdx ?>"></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>"""

# Fix typos in strings above - use proper div tags
old = old.replace('motion class="form-text"', 'div class="form-text"')
new = new.replace('<motion class="ia-photo-slot-preview', '<div class="ia-photo-slot-preview')

if old not in text:
    raise SystemExit('OLD block not found')

text = text.replace(old, new, 1)

insert_after = """                        </details>

                        <details class="ia-add-section">
                            <summary class="ia-add-section-head">
                                <span class="ia-add-section-num">4</span>
                                <span class="ia-add-section-title">Описание</span>"""

video_block = """                        </details>

                        <details class="ia-add-section">
                            <summary class="ia-add-section-head">
                                <span class="ia-add-section-num">4</span>
                                <span class="ia-add-section-title">Видео</span>
                                <span class="ia-add-section-meta">Необязательно</span>
                                <span class="ia-add-section-arrow" aria-hidden="true">▾</span>
                            </summary>
                            <div class="ia-add-section-body">
                                <label class="form-label" for="fldListingVideo">Видеообзор</label>
                                <input type="file" id="fldListingVideo" class="form-control" accept="video/mp4,video/webm">
                                <div class="form-text">MP4 или WebM до 100 МБ. Видео добавляется отдельно от фото.</div>
                                <div class="ia-add-video-shell d-none mt-3" id="iaAddVideoFormPreview">
                                    <video id="iaAddPreviewVideoForm" controls playsinline preload="metadata"></video>
                                </div>
                            </div>
                        </details>

                        <details class="ia-add-section">
                            <summary class="ia-add-section-head">
                                <span class="ia-add-section-num">5</span>
                                <span class="ia-add-section-title">Описание</span>"""

if insert_after not in text:
    raise SystemExit('INSERT anchor not found')

text = text.replace(insert_after, video_block, 1)
path.write_text(text, encoding="utf-8")
print('patched')
