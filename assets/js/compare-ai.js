(function () {
  'use strict';

  var root = document.getElementById('ia-compare-ai-root');
  if (!root) {
    return;
  }

  var STORAGE_OPEN = 'inovaauto_compare_ai_open';
  var STORAGE_KEY = 'inovaauto_compare_ai_key';
  var panel = document.getElementById('ia-compare-ai-panel');
  var loading = document.getElementById('ia-compare-ai-loading');
  var content = document.getElementById('ia-compare-ai-content');
  var loadingSteps = document.getElementById('ia-compare-ai-loading-steps');
  var tooltip = document.getElementById('ia-compare-ai-bot-tooltip');
  var toggles = root.querySelectorAll('[data-ia-compare-ai-toggle]');
  var compareKey = root.getAttribute('data-compare-key') || '';
  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var loadingTimer = null;
  var stepTimer = null;
  var tooltipTimer = null;
  var hasShownContent = false;

  function storedKey() {
    try {
      return sessionStorage.getItem(STORAGE_KEY) || '';
    } catch (e) {
      return '';
    }
  }

  function setStoredKey(key) {
    try {
      sessionStorage.setItem(STORAGE_KEY, key);
    } catch (e) {}
  }

  function isStoredOpen() {
    try {
      return sessionStorage.getItem(STORAGE_OPEN) === '1';
    } catch (e) {
      return false;
    }
  }

  function setStoredOpen(open) {
    try {
      sessionStorage.setItem(STORAGE_OPEN, open ? '1' : '0');
    } catch (e) {}
  }

  if (storedKey() !== compareKey) {
    setStoredKey(compareKey);
    setStoredOpen(false);
  }

  function headerOffset() {
    var header = document.querySelector('.ia-mobile-header, .ia-site-header, header.sticky-top');
    return header ? header.offsetHeight + 12 : 72;
  }

  function triggerActivation(btn) {
    if (!btn || reducedMotion) {
      return;
    }
    btn.classList.remove('is-activating');
    void btn.offsetWidth;
    btn.classList.add('is-activating');
    window.setTimeout(function () {
      btn.classList.remove('is-activating');
    }, 680);
  }

  function updateToggleUi(open) {
    root.classList.toggle('is-open', open);
    toggles.forEach(function (btn) {
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    root.querySelectorAll('.ia-compare-ai-toggle-label--open').forEach(function (el) {
      el.hidden = open;
    });
    root.querySelectorAll('.ia-compare-ai-toggle-label--close').forEach(function (el) {
      el.hidden = !open;
    });
    root.querySelectorAll('.ia-compare-ai-cta-icon--open, .ia-compare-ai-bot-fab-icon--open').forEach(function (el) {
      el.hidden = open;
    });
    root.querySelectorAll('.ia-compare-ai-cta-icon--close, .ia-compare-ai-bot-fab-icon--close').forEach(function (el) {
      el.hidden = !open;
    });

    var fab = document.getElementById('ia-compare-ai-bot-fab');
    if (fab) {
      fab.setAttribute('aria-label', open ? 'Скрыть AI-анализ' : 'Получить AI-анализ');
    }
  }

  function animateScoreRings() {
    root.querySelectorAll('.ia-compare-ai-score-ring[data-score]').forEach(function (ring) {
      var score = parseInt(ring.getAttribute('data-score') || '0', 10);
      if (reducedMotion) {
        ring.style.setProperty('--ia-score-pct', String(score));
        return;
      }
      ring.style.setProperty('--ia-score-pct', '0');
      requestAnimationFrame(function () {
        ring.style.setProperty('--ia-score-pct', String(score));
      });
    });
  }

  function clearTimers() {
    if (loadingTimer) {
      clearTimeout(loadingTimer);
      loadingTimer = null;
    }
    if (stepTimer) {
      clearInterval(stepTimer);
      stepTimer = null;
    }
  }

  function runLoadingSteps() {
    if (!loadingSteps) {
      return;
    }
    var items = loadingSteps.querySelectorAll('.ia-compare-ai-loading-step');
    var idx = 0;
    items.forEach(function (el, i) {
      el.classList.toggle('is-active', i === 0);
    });
    stepTimer = window.setInterval(function () {
      items[idx].classList.remove('is-active');
      idx += 1;
      if (idx >= items.length) {
        clearInterval(stepTimer);
        stepTimer = null;
        return;
      }
      items[idx].classList.add('is-active');
    }, reducedMotion ? 0 : 280);
  }

  function revealContent(skipLoading) {
    if (!panel || !content) {
      return;
    }

    panel.hidden = false;
    panel.setAttribute('aria-hidden', 'false');

    if (skipLoading || hasShownContent) {
      if (loading) {
        loading.hidden = true;
      }
      content.hidden = false;
      content.classList.add('is-visible');
      animateScoreRings();
      return;
    }

    if (loading) {
      loading.hidden = false;
    }
    content.hidden = true;
    content.classList.remove('is-visible');
    runLoadingSteps();

    var duration = reducedMotion ? 120 : 900 + Math.floor(Math.random() * 300);
    loadingTimer = window.setTimeout(function () {
      if (loading) {
        loading.hidden = true;
      }
      content.hidden = false;
      requestAnimationFrame(function () {
        content.classList.add('is-visible');
        animateScoreRings();
        scrollToAnalysis();
      });
      hasShownContent = true;
      clearTimers();
    }, duration);
  }

  function hideContent() {
    clearTimers();
    if (!panel || !content) {
      return;
    }
    panel.hidden = true;
    panel.setAttribute('aria-hidden', 'true');
    if (loading) {
      loading.hidden = true;
    }
    content.hidden = true;
    content.classList.remove('is-visible');
  }

  function scrollToAnalysis() {
    var title = document.getElementById('ia-compare-ai-title');
    var target = title || panel;
    if (!target) {
      return;
    }
    var top = target.getBoundingClientRect().top + window.scrollY - headerOffset();
    window.scrollTo({ top: Math.max(0, top), behavior: reducedMotion ? 'auto' : 'smooth' });
    window.setTimeout(function () {
      if (title) {
        title.focus({ preventScroll: true });
      }
    }, reducedMotion ? 0 : 380);
  }

  function setOpen(open, skipLoading) {
    updateToggleUi(open);
    setStoredOpen(open);
    if (open) {
      revealContent(!!skipLoading);
      scrollToAnalysis();
    } else {
      hideContent();
    }
  }

  toggles.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var willOpen = !root.classList.contains('is-open');
      triggerActivation(btn);
      if (willOpen) {
        var cta = document.getElementById('ia-compare-ai-cta-desktop');
        var fab = document.getElementById('ia-compare-ai-bot-fab');
        if (btn !== cta && cta) {
          triggerActivation(cta);
        }
        if (btn !== fab && fab) {
          triggerActivation(fab);
        }
      }
      setOpen(willOpen, !willOpen || hasShownContent);
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && root.classList.contains('is-open')) {
      setOpen(false, true);
    }
  });

  if (tooltip) {
    tooltipTimer = window.setTimeout(function () {
      tooltip.classList.add('is-hidden');
    }, 4200);
  }

  if (isStoredOpen()) {
    setOpen(true, true);
  }
})();
