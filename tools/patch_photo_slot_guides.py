from pathlib import Path

path = Path(r'c:\xampp\htdocs\Auto 1\add-listing.php')
text = path.read_text(encoding='utf-8')
old = """                                <input type="file" name="listing_media[]" id="fldListingMedia" class="d-none" multiple accept="image/jpeg,image/png,image/webp">
                                <div class="ia-photo-slot-grid" id="iaPhotoSlotGrid">
                                    <?php foreach (ia_listing_photo_slot_labels_ru() as $slotIdx => $slotLabel): ?>
                                        <motion class="ia-photo-slot" data-slot="<?= (int) $slotIdx ?>">
                                            <div class="ia-photo-slot-head">
                                                <span class="ia-photo-slot-num"><?= (int) $slotIdx + 1 ?></span>
                                                <span class="ia-photo-slot-label"><?= ia_h($slotLabel) ?></span>
                                            </div>
                                            <button type="button" class="ia-photo-slot-btn" data-slot="<?= (int) $slotIdx ?>" aria-label="<?= ia_h('Снять фото: ' . $slotLabel) ?>">
                                                <i class="bi bi-camera-fill" aria-hidden="true"></i>
                                                <span>Снять</span>
                                            </button>
                                            <input type="file" class="ia-photo-slot-input d-none" data-slot="<?= (int) $slotIdx ?>" accept="image/jpeg,image/png,image/webp" capture="environment">
                                            <div class="ia-photo-slot-preview d-none" data-slot="<?= (int) $slotIdx ?>">
                                                <img src="" alt="">
                                                <button type="button" class="ia-photo-slot-remove" data-slot="<?= (int) $slotIdx ?>" aria-label="Удалить фото">×</button>
                                            </div>
                                            <div class="ia-photo-slot-error small text-danger d-none" data-slot="<?= (int) $slotIdx ?>"></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>"""

old = old.replace('<motion class="ia-photo-slot"', '<div class="ia-photo-slot"')

new = """                                <motion id="iaPhotoSlotBanner" class="alert alert-warning d-none mb-3" role="alert"></div>
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
                                            <div class="ia-photo-slot-stage">
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
                                            </motion>
                                            <div class="ia-photo-slot-error small text-danger d-none" data-slot="<?= (int) $slotIdx ?>" role="alert"></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>"""

new = new.replace('<motion id="iaPhotoSlotBanner"', '<div id="iaPhotoSlotBanner"')
new = new.replace('</motion>', '</div>', 1)

if old not in text:
    raise SystemExit('OLD html block not found')

text = text.replace(old, new, 1)
text = text.replace('})();})();', '})();', 1)

js_old = """  var listingForm = document.querySelector('form.ia-add-listing-layout');
  if (!hiddenInput || !slotGrid || !thumbGrid || !emptyHint || !primaryHint || !primaryIndexInput || !videoBox || !videoEl) return;
"""

js_new = """  var listingForm = document.querySelector('form.ia-add-listing-layout');
  var accordion = document.getElementById('iaAddAccordion');
  var photoBanner = document.getElementById('iaPhotoSlotBanner');
  var PHOTO_SECTION_IDX = 2;
  if (!hiddenInput || !slotGrid || !thumbGrid || !emptyHint || !primaryHint || !primaryIndexInput || !videoBox || !videoEl) return;
"""

if js_old not in text:
    raise SystemExit('JS header block not found')
text = text.replace(js_old, js_new, 1)

insert_after = """  function setSlotError(idx, msg) {
    var el = slotErrorEl(idx);
    if (!el) return;
    el.textContent = msg || '';
    el.classList.toggle('d-none', !msg);
  }
"""

insert_block = """  function photosComplete() {
    for (var i = 0; i < SLOT_COUNT; i++) {
      if (!slotFiles[i]) return false;
    }
    return true;
  }
  function hasPhotoErrors() {
    return !!slotGrid.querySelector('.ia-photo-slot-error:not(.d-none)');
  }
  function canAdvancePastPhotos() {
    return photosComplete() && !hasPhotoErrors();
  }
  function firstMissingPhotoSlot() {
    for (var i = 0; i < SLOT_COUNT; i++) {
      if (!slotFiles[i]) return i;
    }
    return -1;
  }
  function showPhotoBanner(text) {
    if (!photoBanner) return;
    photoBanner.textContent = text;
    photoBanner.classList.remove('d-none');
  }
  function hidePhotoBanner() {
    if (!photoBanner) return;
    photoBanner.textContent = '';
    photoBanner.classList.add('d-none');
  }
  function refreshPhotoSectionLocks() {
    if (!accordion) return;
    var sections = accordion.querySelectorAll('.ia-add-section');
    var locked = !canAdvancePastPhotos();
    sections.forEach(function (sec, idx) {
      if (idx <= PHOTO_SECTION_IDX) return;
      sec.classList.toggle('ia-add-section--photo-locked', locked);
      var summary = sec.querySelector('summary');
      if (summary) {
        if (locked) {
          summary.setAttribute('aria-disabled', 'true');
        } else {
          summary.removeAttribute('aria-disabled');
        }
      }
      if (locked && sec.open) {
        sec.open = false;
      }
    });
    if (canAdvancePastPhotos()) {
      hidePhotoBanner();
    }
  }
  function blockPhotoAdvance(focusSlot) {
    var missing = firstMissingPhotoSlot();
    var slotIdx = typeof focusSlot === 'number' ? focusSlot : missing;
    if (missing >= 0) {
      showPhotoBanner('Снимите все 10 ракурсов с камеры. Осталось заполнить: ' + (missing + 1) + '.');
    } else if (hasPhotoErrors()) {
      showPhotoBanner('Исправьте ошибки в кадрах — к следующему шагу можно перейти только после успешной проверки.');
    }
    if (!accordion) return;
    var sections = accordion.querySelectorAll('.ia-add-section');
    var photoSec = sections[PHOTO_SECTION_IDX];
    if (photoSec) {
      photoSec.open = true;
      photoSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    if (slotIdx >= 0) {
      var root = slotRoot(slotIdx);
      if (root) {
        root.classList.add('ia-photo-slot--attention');
        root.scrollIntoView({ behavior: 'smooth', block: 'center' });
        window.setTimeout(function () {
          root.classList.remove('ia-photo-slot--attention');
        }, 1800);
      }
    }
    refreshPhotoSectionLocks();
  }
"""

if insert_after not in text:
    raise SystemExit('setSlotError block not found')
text = text.replace(insert_after, insert_after + insert_block, 1)

set_slot_old = """  function setSlotError(idx, msg) {
    var el = slotErrorEl(idx);
    if (!el) return;
    el.textContent = msg || '';
    el.classList.toggle('d-none', !msg);
  }
"""

set_slot_new = """  function setSlotError(idx, msg) {
    var el = slotErrorEl(idx);
    var root = slotRoot(idx);
    if (!el) return;
    el.textContent = msg || '';
    el.classList.toggle('d-none', !msg);
    if (root) {
      root.classList.toggle('ia-photo-slot--error', !!msg);
    }
    refreshPhotoSectionLocks();
    if (msg) {
      blockPhotoAdvance(idx);
    }
  }
"""

text = text.replace(set_slot_old, set_slot_new, 1)

update_ui_old = """  function updateSlotUi(idx) {
    var root = slotRoot(idx);
    if (!root) return;
    var btn = root.querySelector('.ia-photo-slot-btn');
    var preview = root.querySelector('.ia-photo-slot-preview');
    var img = preview ? preview.querySelector('img') : null;
    var file = slotFiles[idx];
    root.classList.toggle('is-filled', !!file);
    if (btn) btn.classList.toggle('d-none', !!file);
    if (preview) preview.classList.toggle('d-none', !file);
"""

update_ui_new = """  function updateSlotUi(idx) {
    var root = slotRoot(idx);
    if (!root) return;
    var btn = root.querySelector('.ia-photo-slot-btn');
    var preview = root.querySelector('.ia-photo-slot-preview');
    var example = root.querySelector('.ia-photo-slot-example');
    var img = preview ? preview.querySelector('img') : null;
    var file = slotFiles[idx];
    root.classList.toggle('is-filled', !!file);
    if (btn) btn.classList.toggle('d-none', !!file);
    if (example) example.classList.toggle('d-none', !!file);
    if (preview) preview.classList.toggle('d-none', !file);
"""

text = text.replace(update_ui_old, update_ui_new, 1)

clear_slot_old = """    updateSlotUi(idx);
    syncHiddenInput();
    renderSidePreview();
  }
  function assignSlotFile(idx, file) {
"""

clear_slot_new = """    updateSlotUi(idx);
    syncHiddenInput();
    renderSidePreview();
    refreshPhotoSectionLocks();
  }
  function assignSlotFile(idx, file) {
"""

text = text.replace(clear_slot_old, clear_slot_new, 1)

assign_ok_old = """      slotFiles[idx] = file;
      updateSlotUi(idx);
      syncHiddenInput();
      renderSidePreview();
    });
  }
"""

assign_ok_new = """      slotFiles[idx] = file;
      updateSlotUi(idx);
      syncHiddenInput();
      renderSidePreview();
      refreshPhotoSectionLocks();
    });
  }
"""

text = text.replace(assign_ok_old, assign_ok_new, 1)

submit_old = """  if (listingForm) {
    listingForm.addEventListener('submit', function() {
      syncHiddenInput();
    });
  }
"""

submit_new = """  if (listingForm) {
    listingForm.addEventListener('submit', function(e) {
      syncHiddenInput();
      if (!canAdvancePastPhotos()) {
        e.preventDefault();
        blockPhotoAdvance();
      }
    });
  }
  if (accordion) {
    var sections = accordion.querySelectorAll('.ia-add-section');
    sections.forEach(function (sec, idx) {
      if (idx <= PHOTO_SECTION_IDX) return;
      var summary = sec.querySelector('summary');
      if (summary) {
        summary.addEventListener('click', function (e) {
          if (canAdvancePastPhotos()) return;
          e.preventDefault();
          blockPhotoAdvance();
        });
      }
      sec.addEventListener('toggle', function () {
        if (!sec.open || canAdvancePastPhotos()) return;
        sec.open = false;
        blockPhotoAdvance();
      });
    });
    refreshPhotoSectionLocks();
  }
"""

text = text.replace(submit_old, submit_new, 1)

path.write_text(text, encoding='utf-8')
print('patched guides and locks')
