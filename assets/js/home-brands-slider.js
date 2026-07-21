/**
 * InnovaAuto — popular brands continuous marquee (PC + mobile).
 * CSS animation drives motion; JS only pauses on drag / hover / reduced-motion.
 */
(function () {
  'use strict';

  var USER_PAUSE_MS = 2800;
  var DRAG_THRESHOLD = 5;

  function initBrandsSlider(section) {
    var viewport = section.querySelector('.ia-brands-slider');
    var inner = section.querySelector('.ia-brands-slider__inner');
    if (!viewport || !inner) {
      return;
    }

    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var resumeTimer = null;
    var dragging = false;
    var dragStartX = 0;
    var dragBaseX = 0;
    var pointerId = null;
    var touchPending = false;
    var currentX = 0;

    function firstTrack() {
      return inner.querySelector('.ia-brands-slider__track');
    }

    function trackWidth() {
      var track = firstTrack();
      return track ? track.getBoundingClientRect().width : 0;
    }

    function ensureDuplicateTracks() {
      var track = firstTrack();
      if (!track) {
        return;
      }
      var tracks = inner.querySelectorAll('.ia-brands-slider__track');
      // Exactly 2 equal tracks so CSS translate3d(-50%) loops seamlessly
      while (tracks.length < 2) {
        var clone = track.cloneNode(true);
        clone.setAttribute('aria-hidden', 'true');
        inner.appendChild(clone);
        tracks = inner.querySelectorAll('.ia-brands-slider__track');
      }
      // Remove extras that would break -50% marquee
      while (tracks.length > 2) {
        inner.removeChild(tracks[tracks.length - 1]);
        tracks = inner.querySelectorAll('.ia-brands-slider__track');
      }
    }

    function setPaused(paused) {
      if (paused) {
        viewport.classList.add('is-paused');
        inner.style.animationPlayState = 'paused';
      } else {
        viewport.classList.remove('is-paused');
        if (!reducedMotion) {
          inner.style.animationPlayState = 'running';
        }
      }
    }

    function pauseForUser(ms) {
      setPaused(true);
      if (resumeTimer) {
        window.clearTimeout(resumeTimer);
      }
      resumeTimer = window.setTimeout(function () {
        if (!dragging) {
          // Clear manual transform so CSS marquee continues cleanly
          inner.style.transform = '';
          setPaused(false);
        }
      }, ms);
    }

    function readCurrentTranslateX() {
      var style = window.getComputedStyle(inner);
      var t = style.transform;
      if (!t || t === 'none') {
        return 0;
      }
      var m = t.match(/matrix\(([^)]+)\)/);
      if (m) {
        var parts = m[1].split(',');
        return parseFloat(parts[4]) || 0;
      }
      var m3 = t.match(/matrix3d\(([^)]+)\)/);
      if (m3) {
        var p3 = m3[1].split(',');
        return parseFloat(p3[12]) || 0;
      }
      return 0;
    }

    function applyDragX(x) {
      var half = trackWidth();
      if (half > 0) {
        while (x <= -half) {
          x += half;
        }
        while (x > 0) {
          x -= half;
        }
      }
      currentX = x;
      inner.style.transform = 'translate3d(' + x + 'px,0,0)';
    }

    function endDrag() {
      if (!dragging && !touchPending) {
        return;
      }
      dragging = false;
      touchPending = false;
      pointerId = null;
      viewport.classList.remove('is-dragging');
      pauseForUser(USER_PAUSE_MS);
    }

    viewport.addEventListener('mouseenter', function () {
      if (!reducedMotion) {
        setPaused(true);
      }
    });
    viewport.addEventListener('mouseleave', function () {
      if (!dragging && !reducedMotion) {
        pauseForUser(600);
      }
    });

    viewport.addEventListener('touchstart', function (e) {
      if (!e.touches.length) {
        return;
      }
      touchPending = true;
      dragStartX = e.touches[0].clientX;
      dragBaseX = readCurrentTranslateX();
      setPaused(true);
    }, { passive: true });

    viewport.addEventListener('touchmove', function (e) {
      if (!e.touches.length || (!touchPending && !dragging)) {
        return;
      }
      var dx = e.touches[0].clientX - dragStartX;
      if (touchPending && Math.abs(dx) >= DRAG_THRESHOLD) {
        dragging = true;
        touchPending = false;
        viewport.classList.add('is-dragging');
      }
      if (!dragging) {
        return;
      }
      applyDragX(dragBaseX + dx);
    }, { passive: true });

    viewport.addEventListener('touchend', endDrag, { passive: true });
    viewport.addEventListener('touchcancel', endDrag, { passive: true });

    viewport.addEventListener('pointerdown', function (e) {
      if (e.pointerType === 'touch') {
        return;
      }
      if (pointerId !== null) {
        return;
      }
      pointerId = e.pointerId;
      dragStartX = e.clientX;
      dragBaseX = readCurrentTranslateX();
      setPaused(true);
      if (viewport.setPointerCapture) {
        try {
          viewport.setPointerCapture(e.pointerId);
        } catch (err) {}
      }
    });

    viewport.addEventListener('pointermove', function (e) {
      if (e.pointerId !== pointerId) {
        return;
      }
      var dx = e.clientX - dragStartX;
      if (!dragging && Math.abs(dx) >= DRAG_THRESHOLD) {
        dragging = true;
        viewport.classList.add('is-dragging');
      }
      if (!dragging) {
        return;
      }
      applyDragX(dragBaseX + dx);
    });

    viewport.addEventListener('pointerup', function (e) {
      if (e.pointerId !== pointerId) {
        return;
      }
      endDrag();
    });

    viewport.addEventListener('pointercancel', function (e) {
      if (e.pointerId !== pointerId) {
        return;
      }
      endDrag();
    });

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        setPaused(true);
      } else if (!dragging && !reducedMotion) {
        setPaused(false);
      }
    });

    window.addEventListener('resize', function () {
      ensureDuplicateTracks();
    });

    function boot() {
      ensureDuplicateTracks();
      inner.classList.add('is-ready');
      if (reducedMotion) {
        setPaused(true);
        return;
      }
      setPaused(false);
    }

    if (document.readyState === 'complete') {
      boot();
    } else {
      window.addEventListener('load', boot);
      // Start ASAP so motion is visible before full load
      window.setTimeout(boot, 120);
    }
  }

  function bootAll() {
    document.querySelectorAll('.ia-brands-section[data-ia-brands-slider]').forEach(initBrandsSlider);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootAll);
  } else {
    bootAll();
  }
})();
