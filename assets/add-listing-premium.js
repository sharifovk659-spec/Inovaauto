/**
 * Legacy demo UI (iaPremiumAccordion). Not loaded by add-listing.php — logic lives inline there.
 */
(function () {
  'use strict';

  var accordion = document.getElementById('iaPremiumAccordion');
  var sections = accordion ? accordion.querySelectorAll('.ia-premium-section') : [];
  var stepCur = document.getElementById('iaPremiumStepCur');
  var stepPct = document.getElementById('iaPremiumStepPct');
  var progressSegs = document.querySelectorAll('.ia-premium-progress-seg');
  var toastWrap = document.getElementById('iaPremiumToastWrap');
  var desc = document.getElementById('iaPremiumDesc');
  var charCount = document.getElementById('iaPremiumCharCount');
  var tagInput = document.getElementById('iaPremiumTagInput');
  var tagAddBtn = document.getElementById('iaPremiumTagAdd');
  var tagsWrap = document.getElementById('iaPremiumTags');
  var publishBtn = document.getElementById('iaPremiumPublish');
  var draftBtn = document.getElementById('iaPremiumDraft');
  var photoSlots = document.querySelectorAll('.ia-premium-photo-slot');
  var videoDrop = document.getElementById('iaPremiumVideoDrop');
  var tariffCards = document.querySelectorAll('.ia-premium-tariff');

  var unsplashPool = [
    'https://images.unsplash.com/photo-1555215695-3004980adade?w=600&q=80',
    'https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?w=600&q=80',
    'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=600&q=80',
    'https://images.unsplash.com/photo-1583121274602-3e2820c50d29?w=600&q=80',
    'https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?w=600&q=80'
  ];
  var unsplashIdx = 0;

  function toast(msg) {
    if (!toastWrap) return;
    var el = document.createElement('div');
    el.className = 'ia-premium-toast';
    el.textContent = msg;
    toastWrap.appendChild(el);
    requestAnimationFrame(function () {
      el.classList.add('is-visible');
    });
    setTimeout(function () {
      el.classList.remove('is-visible');
      setTimeout(function () { el.remove(); }, 400);
    }, 2800);
  }

  function updateProgress() {
    var step = 1;
    sections.forEach(function (sec, i) {
      if (sec.open) step = i + 1;
    });
    var pct = Math.max(17, Math.round((step / sections.length) * 100));
    if (stepCur) stepCur.textContent = String(step);
    if (stepPct) stepPct.textContent = pct + '%';
    progressSegs.forEach(function (seg, i) {
      seg.classList.remove('is-active', 'is-done');
      if (i + 1 < step) seg.classList.add('is-done');
      else if (i + 1 === step) seg.classList.add('is-active');
    });
  }

  if (accordion) {
    sections.forEach(function (sec) {
      sec.addEventListener('toggle', function () {
        if (sec.open) {
          sections.forEach(function (other) {
            if (other !== sec) other.open = false;
          });
        }
        updateProgress();
      });
    });
    updateProgress();
  }

  if (desc && charCount) {
    function syncChars() {
      var len = desc.value.length;
      charCount.textContent = len + ' / 3000 символов';
    }
    desc.addEventListener('input', syncChars);
    syncChars();
  }

  function addTag(text) {
    text = (text || '').trim();
    if (!text || !tagsWrap) return;
    var existing = tagsWrap.querySelectorAll('.ia-premium-tag');
    if (existing.length >= 8) return;
    var tag = document.createElement('span');
    tag.className = 'ia-premium-tag';
    tag.innerHTML = text + ' <button type="button" class="ia-premium-tag-remove" aria-label="Удалить">&times;</button>';
    tag.querySelector('.ia-premium-tag-remove').addEventListener('click', function () {
      tag.remove();
    });
    tagsWrap.appendChild(tag);
  }

  if (tagAddBtn && tagInput) {
    tagAddBtn.addEventListener('click', function () {
      addTag(tagInput.value);
      tagInput.value = '';
    });
    tagInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        addTag(tagInput.value);
        tagInput.value = '';
      }
    });
    tagsWrap.querySelectorAll('.ia-premium-tag-remove').forEach(function (btn) {
      btn.addEventListener('click', function () {
        btn.closest('.ia-premium-tag').remove();
      });
    });
  }

  photoSlots.forEach(function (slot) {
    slot.addEventListener('click', function (e) {
      if (e.target.closest('.ia-premium-photo-slot-del')) return;
      if (slot.classList.contains('has-image')) return;
      var url = unsplashPool[unsplashIdx % unsplashPool.length];
      unsplashIdx++;
      slot.classList.add('has-image');
      slot.style.setProperty('--slot-bg', 'url("' + url + '")');
      slot.querySelector(':before') || slot.style.setProperty('background-image', 'url("' + url + '")');
      var styleEl = slot.querySelector('.ia-premium-photo-slot-bg');
      if (!styleEl) {
        slot.style.backgroundImage = 'url("' + url + '")';
        slot.style.backgroundSize = 'cover';
        slot.style.backgroundPosition = 'center';
      }
    });
    var del = slot.querySelector('.ia-premium-photo-slot-del');
    if (del) {
      del.addEventListener('click', function (e) {
        e.stopPropagation();
        slot.classList.remove('has-image');
        slot.style.backgroundImage = '';
      });
    }
  });

  if (videoDrop) {
    videoDrop.addEventListener('click', function () {
      videoDrop.classList.add('has-upload');
      var title = videoDrop.querySelector('.ia-premium-video-title');
      var descEl = videoDrop.querySelector('.ia-premium-video-desc');
      if (title) title.textContent = 'Видео загружено';
      if (descEl) descEl.textContent = 'demo-video.mp4 · 24 сек';
      toast('Видео добавлено (демо)');
    });
  }

  tariffCards.forEach(function (card) {
    card.addEventListener('click', function () {
      tariffCards.forEach(function (c) { c.classList.remove('is-selected'); });
      card.classList.add('is-selected');
      var input = card.querySelector('input[type="radio"]');
      if (input) input.checked = true;
    });
  });

  if (draftBtn) {
    draftBtn.addEventListener('click', function () {
      toast('Черновик сохранён');
    });
  }

  if (publishBtn) {
    publishBtn.addEventListener('click', function () {
      if (publishBtn.classList.contains('is-loading')) return;
      publishBtn.classList.add('is-loading');
      setTimeout(function () {
        publishBtn.classList.remove('is-loading');
        publishBtn.classList.add('is-success');
        var text = publishBtn.querySelector('.ia-premium-btn-text');
        if (text) text.textContent = 'Отправлено!';
        toast('Отправлено на модерацию');
      }, 1800);
    });
  }
})();
