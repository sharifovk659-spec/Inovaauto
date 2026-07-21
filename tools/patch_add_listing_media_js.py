from pathlib import Path

path = Path(r'c:\xampp\htdocs\Auto 1\add-listing.php')
text = path.read_text(encoding='utf-8')
start = text.index('(function(){\n  var input = document.getElementById(\'fldListingMedia\');')
end = text.index('})();\n\n(function () {\n  var btn = document.getElementById(\'iaListingGeoBtn\');')
new_block = r"""(function(){
  var SLOT_COUNT = <?= (int) IA_LISTING_PHOTO_SLOT_COUNT ?>;
  var hiddenInput = document.getElementById('fldListingMedia');
  var slotGrid = document.getElementById('iaPhotoSlotGrid');
  var thumbGrid = document.getElementById('iaAddThumbGrid');
  var emptyHint = document.getElementById('iaAddPreviewEmpty');
  var primaryHint = document.getElementById('iaPrimaryHint');
  var primaryIndexInput = document.getElementById('iaPrimaryImageIndex');
  var videoBox = document.getElementById('iaAddVideoBox');
  var videoEl = document.getElementById('iaAddPreviewVideo');
  var videoField = document.getElementById('fldListingVideo');
  var videoFormShell = document.getElementById('iaAddVideoFormPreview');
  var videoFormEl = document.getElementById('iaAddPreviewVideoForm');
  var listingForm = document.querySelector('form.ia-add-listing-layout');
  if (!hiddenInput || !slotGrid || !thumbGrid || !emptyHint || !primaryHint || !primaryIndexInput || !videoBox || !videoEl) return;

  var MIN_SHORT = 480;
  var MAX_LONG = 4096;
  var MIN_ASPECT = 9 / 16;
  var MAX_ASPECT = 16 / 9;
  var slotFiles = new Array(SLOT_COUNT).fill(null);
  var slotPreviewUrls = new Array(SLOT_COUNT).fill('');
  var videoFile = null;
  var sideBlobUrls = [];
  var videoBlobUrl = '';

  function isImageFile(f) {
    if (!f) return false;
    return /^image\//.test(f.type || '') || /\.(jpe?g|png|webp)$/i.test(String(f.name || ''));
  }
  function isVideoFile(f) {
    if (!f) return false;
    return /^video\//.test(f.type || '') || /\.(mp4|webm)$/i.test(String(f.name || ''));
  }
  function clearNodes(node) {
    while (node.firstChild) node.removeChild(node.firstChild);
  }
  function revokeSideBlobs() {
    sideBlobUrls.forEach(function(u) { try { URL.revokeObjectURL(u); } catch (e) {} });
    sideBlobUrls = [];
    if (videoBlobUrl) {
      try { URL.revokeObjectURL(videoBlobUrl); } catch (e) {}
      videoBlobUrl = '';
    }
  }
  function revokeSlotUrl(idx) {
    if (slotPreviewUrls[idx]) {
      try { URL.revokeObjectURL(slotPreviewUrls[idx]); } catch (e) {}
      slotPreviewUrls[idx] = '';
    }
  }
  function slotRoot(idx) {
    return slotGrid.querySelector('.ia-photo-slot[data-slot="' + idx + '"]');
  }
  function slotErrorEl(idx) {
    return slotGrid.querySelector('.ia-photo-slot-error[data-slot="' + idx + '"]');
  }
  function setSlotError(idx, msg) {
    var el = slotErrorEl(idx);
    if (!el) return;
    el.textContent = msg || '';
    el.classList.toggle('d-none', !msg);
  }
  function updateSlotUi(idx) {
    var root = slotRoot(idx);
    if (!root) return;
    var btn = root.querySelector('.ia-photo-slot-btn');
    var preview = root.querySelector('.ia-photo-slot-preview');
    var img = preview ? preview.querySelector('img') : null;
    var file = slotFiles[idx];
    root.classList.toggle('is-filled', !!file);
    if (btn) btn.classList.toggle('d-none', !!file);
    if (preview) preview.classList.toggle('d-none', !file);
    if (file && img) {
      revokeSlotUrl(idx);
      slotPreviewUrls[idx] = URL.createObjectURL(file);
      img.src = slotPreviewUrls[idx];
      img.alt = root.querySelector('.ia-photo-slot-label') ? root.querySelector('.ia-photo-slot-label').textContent : '';
    } else if (img) {
      img.removeAttribute('src');
    }
  }
  function syncHiddenInput() {
    var dt = new DataTransfer();
    for (var i = 0; i < SLOT_COUNT; i++) {
      if (slotFiles[i]) dt.items.add(slotFiles[i]);
    }
    if (videoFile) dt.items.add(videoFile);
    hiddenInput.files = dt.files;
  }
  function validateCameraPhoto(file, done) {
    if (!isImageFile(file)) {
      done('Только JPEG, PNG или WebP.');
      return;
    }
    var url = URL.createObjectURL(file);
    var img = new Image();
    img.onload = function() {
      var w = img.naturalWidth || 0;
      var h = img.naturalHeight || 0;
      URL.revokeObjectURL(url);
      var shortEdge = Math.min(w, h);
      var longEdge = Math.max(w, h);
      var aspect = h > 0 ? w / h : 0;
      if (shortEdge < MIN_SHORT) {
        done('Кадр слишком маленький: короткая сторона должна быть не меньше ' + MIN_SHORT + ' px.');
      } else if (longEdge > MAX_LONG) {
        done('Кадр слишком большой: длинная сторона не больше ' + MAX_LONG + ' px.');
      } else if (aspect < MIN_ASPECT || aspect > MAX_ASPECT) {
        done('Пропорции не как у камеры телефона. Сделайте новый снимок.');
      } else {
        done(null);
      }
    };
    img.onerror = function() {
      URL.revokeObjectURL(url);
      done('Не удалось открыть изображение.');
    };
    img.src = url;
  }
  function renderSidePreview() {
    revokeSideBlobs();
    clearNodes(thumbGrid);
    videoEl.removeAttribute('src');
    if (videoFormEl) videoFormEl.removeAttribute('src');
    videoBox.classList.add('d-none');
    if (videoFormShell) videoFormShell.classList.add('d-none');
    primaryHint.classList.add('d-none');
    thumbGrid.classList.add('d-none');
    emptyHint.classList.remove('d-none');
    primaryIndexInput.value = '0';

    var filled = 0;
    for (var i = 0; i < SLOT_COUNT; i++) {
      if (slotFiles[i]) filled++;
    }
    if (!filled && !videoFile) {
      return;
    }
    emptyHint.classList.add('d-none');
    if (filled) {
      thumbGrid.classList.remove('d-none');
      if (slotFiles[0]) primaryHint.classList.remove('d-none');
      for (var s = 0; s < SLOT_COUNT; s++) {
        if (!slotFiles[s]) continue;
        var root = slotRoot(s);
        var caption = root && root.querySelector('.ia-photo-slot-label') ? root.querySelector('.ia-photo-slot-label').textContent : ('Ракурс ' + (s + 1));
        var u = URL.createObjectURL(slotFiles[s]);
        sideBlobUrls.push(u);
        var cell = document.createElement('div');
        cell.className = 'ia-add-preview-item';
        cell.setAttribute('role', 'listitem');
        var media = document.createElement('div');
        media.className = 'ia-add-preview-media';
        var im = document.createElement('img');
        im.src = u;
        im.alt = caption;
        media.appendChild(im);
        cell.appendChild(media);
        var actions = document.createElement('div');
        actions.className = 'ia-add-preview-actions';
        var meta = document.createElement('div');
        meta.className = 'small text-secondary';
        meta.textContent = (s + 1) + '. ' + caption;
        actions.appendChild(meta);
        cell.appendChild(actions);
        thumbGrid.appendChild(cell);
      }
    }
    if (videoFile) {
      videoBlobUrl = URL.createObjectURL(videoFile);
      videoEl.src = videoBlobUrl;
      videoBox.classList.remove('d-none');
      if (videoFormEl) {
        videoFormEl.src = videoBlobUrl;
        if (videoFormShell) videoFormShell.classList.remove('d-none');
      }
    }
  }
  function clearSlot(idx) {
    revokeSlotUrl(idx);
    slotFiles[idx] = null;
    setSlotError(idx, '');
    var input = slotGrid.querySelector('.ia-photo-slot-input[data-slot="' + idx + '"]');
    if (input) input.value = '';
    updateSlotUi(idx);
    syncHiddenInput();
    renderSidePreview();
  }
  function assignSlotFile(idx, file) {
    validateCameraPhoto(file, function(err) {
      if (err) {
        setSlotError(idx, err);
        var input = slotGrid.querySelector('.ia-photo-slot-input[data-slot="' + idx + '"]');
        if (input) input.value = '';
        return;
      }
      setSlotError(idx, '');
      slotFiles[idx] = file;
      updateSlotUi(idx);
      syncHiddenInput();
      renderSidePreview();
    });
  }
  slotGrid.querySelectorAll('.ia-photo-slot-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var idx = parseInt(btn.getAttribute('data-slot'), 10);
      var input = slotGrid.querySelector('.ia-photo-slot-input[data-slot="' + idx + '"]');
      if (input) input.click();
    });
  });
  slotGrid.querySelectorAll('.ia-photo-slot-input').forEach(function(input) {
    input.addEventListener('change', function() {
      var idx = parseInt(input.getAttribute('data-slot'), 10);
      var file = input.files && input.files[0] ? input.files[0] : null;
      if (!file) return;
      assignSlotFile(idx, file);
    });
  });
  slotGrid.querySelectorAll('.ia-photo-slot-remove').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var idx = parseInt(btn.getAttribute('data-slot'), 10);
      clearSlot(idx);
    });
  });
  if (videoField) {
    videoField.addEventListener('change', function() {
      var file = videoField.files && videoField.files[0] ? videoField.files[0] : null;
      if (file && !isVideoFile(file)) {
        videoField.value = '';
        videoFile = null;
      } else {
        videoFile = file;
      }
      syncHiddenInput();
      renderSidePreview();
    });
  }
  if (listingForm) {
    listingForm.addEventListener('submit', function() {
      syncHiddenInput();
    });
  }
  for (var init = 0; init < SLOT_COUNT; init++) {
    updateSlotUi(init);
  }
  renderSidePreview();
})();"""

path.write_text(text[:start] + new_block + text[end:], encoding='utf-8')
print('patched js')
