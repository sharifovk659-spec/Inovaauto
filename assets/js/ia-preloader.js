/**
 * InnovaAuto — premium session preloader (first visit per tab).
 * CSS transform/opacity + requestAnimationFrame; no video; max ~2s.
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'ia_preloader_done';
  var MIN_MS = 1500;
  var MAX_MS = 2000;
  var SAFETY_MS = 2500;
  var FADE_MS = 420;

  var root = document.documentElement;
  var el = document.getElementById('iaPreloader');
  if (!el) {
    root.classList.remove('ia-preloader-pending', 'ia-preloader-active');
    return;
  }

  try {
    if (sessionStorage.getItem(STORAGE_KEY) === '1') {
      discard(false);
      return;
    }
  } catch (e) {
    /* sessionStorage blocked — still show once */
  }

  var reduced = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  if (reduced) {
    MIN_MS = 400;
    MAX_MS = 800;
    SAFETY_MS = 1200;
    FADE_MS = 120;
  }

  var bar = el.querySelector('.ia-preloader__bar');
  var pctEl = el.querySelector('.ia-preloader__pct');
  var scrollY = 0;
  var finishing = false;
  var finished = false;
  var targetProgress = 0;
  var displayProgress = 0;
  var start = 0;
  var safetyTimer = 0;
  var fadeTimer = 0;
  var domReady = document.readyState !== 'loading';
  var loadReady = document.readyState === 'complete';

  function onDomReady() {
    domReady = true;
  }
  function onLoadReady() {
    loadReady = true;
  }

  if (!domReady) {
    document.addEventListener('DOMContentLoaded', onDomReady, { once: true });
  }
  if (!loadReady) {
    window.addEventListener('load', onLoadReady, { once: true });
  }

  function discard(markDone) {
    root.classList.remove('ia-preloader-pending', 'ia-preloader-active');
    unlockBody();
    if (markDone) {
      try {
        sessionStorage.setItem(STORAGE_KEY, '1');
      } catch (e) {}
    }
    if (el && el.parentNode) {
      el.parentNode.removeChild(el);
    }
  }

  function unlockBody() {
    if (!document.body) {
      return;
    }
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.left = '';
    document.body.style.right = '';
    document.body.style.width = '';
    document.body.style.overflow = '';
    try {
      window.scrollTo(0, scrollY || 0);
    } catch (e) {}
  }

  function lockScroll() {
    scrollY = window.scrollY || window.pageYOffset || 0;
    root.classList.add('ia-preloader-active');
    if (!document.body) {
      return;
    }
    document.body.style.position = 'fixed';
    document.body.style.top = '-' + scrollY + 'px';
    document.body.style.left = '0';
    document.body.style.right = '0';
    document.body.style.width = '100%';
    document.body.style.overflow = 'hidden';
  }

  function show() {
    el.hidden = false;
    el.removeAttribute('hidden');
    el.setAttribute('aria-hidden', 'false');
    el.setAttribute('aria-busy', 'true');
    el.style.pointerEvents = 'auto';
    lockScroll();
  }

  function computeTarget(elapsed) {
    var timePart = Math.min(elapsed / MAX_MS, 1) * 84;
    var part = timePart;
    if (domReady) {
      part += 8;
    }
    if (loadReady) {
      part += 6;
    }
    return Math.min(96, part);
  }

  function requestFinish() {
    if (finishing) {
      return;
    }
    finishing = true;
    targetProgress = 100;
  }

  function maybeFinish(elapsed) {
    if (finishing) {
      return;
    }
    if (elapsed >= MAX_MS) {
      requestFinish();
      return;
    }
    if (domReady && loadReady && elapsed >= MIN_MS) {
      requestFinish();
      return;
    }
    /* DOM ready + min time: do not wait forever for slow assets */
    if (domReady && elapsed >= MIN_MS + 400) {
      requestFinish();
    }
  }

  function setProgressVisual(value) {
    var ratio = Math.max(0, Math.min(1, value / 100));
    if (bar) {
      bar.style.transform = 'scaleX(' + ratio.toFixed(4) + ')';
    }
    if (pctEl) {
      pctEl.textContent = Math.round(value) + '%';
    }
  }

  function complete() {
    if (finished) {
      return;
    }
    finished = true;
    if (safetyTimer) {
      window.clearTimeout(safetyTimer);
      safetyTimer = 0;
    }
    el.setAttribute('aria-busy', 'false');
    el.setAttribute('aria-hidden', 'true');
    el.classList.add('ia-preloader--out');
    el.style.pointerEvents = 'none';

    fadeTimer = window.setTimeout(function () {
      try {
        sessionStorage.setItem(STORAGE_KEY, '1');
      } catch (e) {}
      root.classList.remove('ia-preloader-active', 'ia-preloader-pending');
      unlockBody();
      if (el && el.parentNode) {
        el.parentNode.removeChild(el);
      }
    }, FADE_MS);
  }

  function forceUnlock() {
    if (finished) {
      return;
    }
    finishing = true;
    displayProgress = 100;
    setProgressVisual(100);
    complete();
  }

  function tick(now) {
    if (finished) {
      return;
    }
    if (!start) {
      start = now;
    }
    var elapsed = now - start;
    maybeFinish(elapsed);

    if (finishing) {
      targetProgress = 100;
      var ease = reduced ? 0.45 : 0.18;
      displayProgress += (100 - displayProgress) * ease;
    } else {
      targetProgress = Math.max(targetProgress, computeTarget(elapsed));
      var drift = reduced ? 0.32 : 0.12;
      displayProgress += (targetProgress - displayProgress) * drift;
    }

    setProgressVisual(displayProgress);

    if (finishing && displayProgress >= 99.4) {
      setProgressVisual(100);
      complete();
      return;
    }

    window.requestAnimationFrame(tick);
  }

  /* BFCache / back-forward: never leave the page locked */
  window.addEventListener('pageshow', function (ev) {
    if (ev.persisted) {
      forceUnlock();
    }
  });

  window.addEventListener('pagehide', function () {
    if (fadeTimer) {
      window.clearTimeout(fadeTimer);
    }
    root.classList.remove('ia-preloader-active');
    unlockBody();
  });

  show();
  safetyTimer = window.setTimeout(forceUnlock, SAFETY_MS);
  window.requestAnimationFrame(tick);
})();
