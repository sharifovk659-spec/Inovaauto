/**
 * InnovaAuto — popular brands continuous slider (PC + mobile).
 * Always moves via requestAnimationFrame. No hover pause.
 */
(function () {
  'use strict';

  var SPEED_MOBILE = 0.85;
  var SPEED_DESKTOP = 0.75;
  var DRAG_PAUSE_MS = 2200;
  var DRAG_THRESHOLD = 6;

  function initBrandsSlider(section) {
    var viewport = section.querySelector('.ia-brands-slider');
    var inner = section.querySelector('.ia-brands-slider__inner');
    if (!viewport || !inner) {
      return;
    }

    // Kill CSS animation — JS owns transform
    inner.style.animation = 'none';
    inner.classList.add('is-js-marquee');

    var mobileMq = window.matchMedia('(max-width: 991.98px)');
    var speed = mobileMq.matches ? SPEED_MOBILE : SPEED_DESKTOP;
    var offset = 0;
    var dragging = false;
    var paused = false;
    var tabHidden = false;
    var resumeTimer = null;
    var dragStartX = 0;
    var dragStartOffset = 0;
    var pointerId = null;
    var started = false;

    function firstTrack() {
      return inner.querySelector('.ia-brands-slider__track');
    }

    function loopWidth() {
      var track = firstTrack();
      if (!track) {
        return 0;
      }
      return track.offsetWidth || track.getBoundingClientRect().width || 0;
    }

    function ensureTwoTracks() {
      var track = firstTrack();
      if (!track) {
        return;
      }
      var tracks = inner.querySelectorAll('.ia-brands-slider__track');
      while (tracks.length < 2) {
        var clone = track.cloneNode(true);
        clone.setAttribute('aria-hidden', 'true');
        inner.appendChild(clone);
        tracks = inner.querySelectorAll('.ia-brands-slider__track');
      }
      while (tracks.length > 2) {
        inner.removeChild(tracks[tracks.length - 1]);
        tracks = inner.querySelectorAll('.ia-brands-slider__track');
      }
    }

    function normalize() {
      var w = loopWidth();
      if (w <= 0) {
        return;
      }
      offset = offset % w;
      if (offset < 0) {
        offset += w;
      }
    }

    function paint() {
      inner.style.transform = 'translate3d(' + (-offset) + 'px,0,0)';
    }

    function tick() {
      window.requestAnimationFrame(tick);
      if (tabHidden || paused || dragging) {
        return;
      }
      offset += speed;
      normalize();
      paint();
    }

    function pauseBriefly() {
      paused = true;
      if (resumeTimer) {
        window.clearTimeout(resumeTimer);
      }
      resumeTimer = window.setTimeout(function () {
        paused = false;
      }, DRAG_PAUSE_MS);
    }

    function onPointerDown(e) {
      if (e.pointerType === 'touch') {
        return;
      }
      if (pointerId !== null) {
        return;
      }
      pointerId = e.pointerId;
      dragStartX = e.clientX;
      dragStartOffset = offset;
      try {
        viewport.setPointerCapture(e.pointerId);
      } catch (err) {}
    }

    function onPointerMove(e) {
      if (e.pointerId !== pointerId) {
        return;
      }
      var dx = dragStartX - e.clientX;
      if (!dragging && Math.abs(dx) < DRAG_THRESHOLD) {
        return;
      }
      dragging = true;
      viewport.classList.add('is-dragging');
      offset = dragStartOffset + dx;
      normalize();
      paint();
    }

    function onPointerUp(e) {
      if (pointerId !== null && e.pointerId !== pointerId) {
        return;
      }
      var wasDragging = dragging;
      dragging = false;
      pointerId = null;
      viewport.classList.remove('is-dragging');
      if (wasDragging) {
        pauseBriefly();
      }
    }

    viewport.addEventListener('pointerdown', onPointerDown);
    viewport.addEventListener('pointermove', onPointerMove);
    viewport.addEventListener('pointerup', onPointerUp);
    viewport.addEventListener('pointercancel', onPointerUp);

    viewport.addEventListener('touchstart', function (e) {
      if (!e.touches.length) {
        return;
      }
      dragStartX = e.touches[0].clientX;
      dragStartOffset = offset;
      pointerId = -1;
    }, { passive: true });

    viewport.addEventListener('touchmove', function (e) {
      if (!e.touches.length || pointerId === null) {
        return;
      }
      var dx = dragStartX - e.touches[0].clientX;
      if (!dragging && Math.abs(dx) < DRAG_THRESHOLD) {
        return;
      }
      dragging = true;
      viewport.classList.add('is-dragging');
      offset = dragStartOffset + dx;
      normalize();
      paint();
    }, { passive: true });

    viewport.addEventListener('touchend', function () {
      var wasDragging = dragging;
      dragging = false;
      pointerId = null;
      viewport.classList.remove('is-dragging');
      if (wasDragging) {
        pauseBriefly();
      }
    }, { passive: true });

    viewport.addEventListener('touchcancel', function () {
      dragging = false;
      pointerId = null;
      viewport.classList.remove('is-dragging');
    }, { passive: true });

    document.addEventListener('visibilitychange', function () {
      tabHidden = document.hidden;
    });

    if (typeof mobileMq.addEventListener === 'function') {
      mobileMq.addEventListener('change', function () {
        speed = mobileMq.matches ? SPEED_MOBILE : SPEED_DESKTOP;
      });
    }

    window.addEventListener('resize', function () {
      ensureTwoTracks();
      normalize();
      paint();
    });

    function start() {
      if (started) {
        return;
      }
      ensureTwoTracks();
      if (loopWidth() <= 0) {
        window.setTimeout(start, 80);
        return;
      }
      started = true;
      offset = 0;
      paint();
      window.requestAnimationFrame(tick);
    }

    start();
    window.setTimeout(start, 200);
    window.addEventListener('load', start);
  }

  function boot() {
    document.querySelectorAll('.ia-brands-section[data-ia-brands-slider]').forEach(initBrandsSlider);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
