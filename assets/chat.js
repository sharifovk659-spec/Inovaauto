(function () {
  'use strict';

  function iaChatInit(opts) {
    var threadId = opts.threadId || 0;
    var lastId = opts.lastId || 0;
    var pollUrl = opts.pollUrl || '';

    var body = document.getElementById('iaChatBody');
    if (body) {
      body.scrollTop = body.scrollHeight;
    }

    var input = document.getElementById('iaChatInput');
    var form = document.getElementById('iaChatComposer');
    var composerWrap = document.getElementById('iaChatComposerWrap');

    if (input && form) {
      var resize = function () {
        input.style.height = 'auto';
        var h = Math.max(42, Math.min(160, input.scrollHeight || 42));
        input.style.height = h + 'px';
      };
      input.addEventListener('input', resize);
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          if ((input.value || '').trim() !== '') {
            form.submit();
          }
        }
        if (e.key === 'Escape') {
          closeAllPanels();
        }
      });
      resize();

      var mobileComposerMq = window.matchMedia ? window.matchMedia('(max-width: 991.98px)') : null;
      var resetComposerDock = function () {
        if (!composerWrap) return;
        composerWrap.style.bottom = '';
      };
      var applyComposerDock = function () {
        if (!composerWrap || !mobileComposerMq || !mobileComposerMq.matches || !window.visualViewport) {
          resetComposerDock();
          return;
        }
        var vv = window.visualViewport;
        var kbOffset = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
        composerWrap.style.bottom = kbOffset > 0 ? kbOffset + 'px' : '';
        if (body) {
          body.scrollTop = body.scrollHeight;
        }
      };
      if (mobileComposerMq && composerWrap && window.visualViewport) {
        ['resize', 'scroll'].forEach(function (ev) {
          window.visualViewport.addEventListener(ev, applyComposerDock);
        });
        input.addEventListener('focus', function () {
          window.setTimeout(applyComposerDock, 60);
          window.setTimeout(applyComposerDock, 220);
        });
        input.addEventListener('blur', function () {
          window.setTimeout(resetComposerDock, 120);
        });
      }
    }

    if (pollUrl && threadId > 0) {
      setInterval(function () {
        fetch(pollUrl + '?thread_id=' + encodeURIComponent(String(threadId)), { credentials: 'same-origin' })
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (data) {
            if (!data || typeof data.last_message_id === 'undefined') return;
            var incoming = Number(data.last_message_id || 0);
            if (incoming > lastId) {
              window.location.reload();
            }
          })
          .catch(function () {});
      }, 4000);
    }

    var emojiPanel = document.getElementById('iaChatEmojiPanel');
    var emojiToggle = document.getElementById('iaChatEmojiToggle');
    var toolsPanel = document.getElementById('iaChatToolsPanel');
    var toolsToggle = document.getElementById('iaChatToolsToggle');

    function setToolsOpen(open) {
      if (!toolsPanel || !toolsToggle) return;
      toolsPanel.hidden = !open;
      toolsPanel.classList.toggle('is-open', open);
      toolsToggle.classList.toggle('is-open', open);
      toolsToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (!open) {
        setEmojiOpen(false);
      }
    }

    function setEmojiOpen(open) {
      if (!emojiPanel) return;
      emojiPanel.hidden = !open;
      emojiPanel.classList.toggle('is-open', open);
    }

    function closeAllPanels() {
      setToolsOpen(false);
      setEmojiOpen(false);
    }

    function isToolsOpen() {
      return toolsPanel && !toolsPanel.hidden;
    }

    if (toolsToggle) {
      toolsToggle.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        setToolsOpen(!isToolsOpen());
      });
    }

    if (emojiToggle && emojiPanel && input) {
      emojiToggle.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var willOpen = emojiPanel.hidden;
        setEmojiOpen(willOpen);
      });
      emojiPanel.querySelectorAll('.ia-chat-emoji-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var emo = btn.getAttribute('data-emoji') || '';
          if (!emo) return;
          var start = input.selectionStart || input.value.length;
          var end = input.selectionEnd || input.value.length;
          input.value = input.value.slice(0, start) + emo + input.value.slice(end);
          input.focus();
          input.selectionStart = input.selectionEnd = start + emo.length;
          input.dispatchEvent(new Event('input'));
          setEmojiOpen(false);
        });
      });
    }

    document.addEventListener('click', function (e) {
      if (!composerWrap || composerWrap.contains(e.target)) return;
      closeAllPanels();
    });

    var mediaForm = document.getElementById('iaChatMediaForm');
    var mediaType = document.getElementById('iaChatMediaType');
    var mediaFile = document.getElementById('iaChatMediaFile');
    var mediaCaption = document.getElementById('iaChatMediaCaption');
    var mediaBusy = false;

    function submitMedia(kind, file) {
      if (!mediaForm || !mediaType || !mediaFile || !file || mediaBusy) return;
      mediaBusy = true;
      closeAllPanels();
      mediaType.value = kind;
      if (mediaCaption && input) {
        mediaCaption.value = (input.value || '').trim();
      }
      if (typeof DataTransfer !== 'undefined') {
        var dt = new DataTransfer();
        dt.items.add(file);
        mediaFile.files = dt.files;
        mediaForm.submit();
        return;
      }
      var fd = new FormData(mediaForm);
      fd.set('attachment', file, file.name || 'upload');
      fetch(mediaForm.action, { method: 'POST', body: fd, credentials: 'same-origin', redirect: 'follow' })
        .then(function (r) {
          window.location.href = r.url || mediaForm.action;
        })
        .catch(function () {
          mediaBusy = false;
          alert('Не удалось отправить файл. Обновите страницу.');
        });
    }

    function bindFileInput(el) {
      if (!el) return;
      el.addEventListener('change', function () {
        var file = el.files && el.files[0];
        var kind = el.getAttribute('data-media-kind') || 'file';
        if (file) {
          submitMedia(kind, file);
        }
        el.value = '';
      });
    }

    bindFileInput(document.getElementById('iaChatCameraInput'));
    bindFileInput(document.getElementById('iaChatGalleryInput'));
    bindFileInput(document.getElementById('iaChatFileInput'));

    var voiceBtn = document.getElementById('iaChatVoiceBtn');
    var voiceRec = document.getElementById('iaChatVoiceRec');
    var voiceCancel = document.getElementById('iaChatVoiceCancel');
    var recorder = null;
    var voiceStream = null;
    var chunks = [];
    var voiceActive = false;
    var voiceSent = false;

    function showVoiceRec(show) {
      if (!voiceRec) return;
      voiceRec.hidden = !show;
    }

    function cleanupVoice() {
      if (voiceStream) {
        voiceStream.getTracks().forEach(function (t) { t.stop(); });
        voiceStream = null;
      }
      recorder = null;
      chunks = [];
      voiceActive = false;
      showVoiceRec(false);
      if (voiceBtn) voiceBtn.classList.remove('is-recording');
    }

    function stopVoice(send) {
      if (!recorder || !voiceActive) return;
      voiceActive = false;
      if (recorder.state === 'recording') {
        voiceSent = !!send;
        recorder.stop();
      } else {
        cleanupVoice();
      }
    }

    function startVoice() {
      if (voiceActive || mediaBusy) return;
      if (!isToolsOpen()) {
        setToolsOpen(true);
      }
      if (!navigator.mediaDevices || typeof MediaRecorder === 'undefined') {
        alert('Голосовые сообщения не поддерживаются в этом браузере.');
        return;
      }
      navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
        voiceStream = stream;
        chunks = [];
        voiceSent = false;
        voiceActive = true;
        var mimeType = '';
        if (typeof MediaRecorder.isTypeSupported === 'function') {
          if (MediaRecorder.isTypeSupported('audio/mp4')) mimeType = 'audio/mp4';
          else if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) mimeType = 'audio/webm;codecs=opus';
          else if (MediaRecorder.isTypeSupported('audio/webm')) mimeType = 'audio/webm';
        }
        try {
          recorder = mimeType ? new MediaRecorder(stream, { mimeType: mimeType }) : new MediaRecorder(stream);
        } catch (err) {
          recorder = new MediaRecorder(stream);
        }
        recorder.ondataavailable = function (ev) {
          if (ev.data && ev.data.size > 0) chunks.push(ev.data);
        };
        recorder.onstop = function () {
          var type = recorder.mimeType || 'audio/webm';
          if (voiceSent && chunks.length) {
            var blob = new Blob(chunks, { type: type });
            var ext = type.indexOf('mp4') >= 0 ? 'm4a' : 'webm';
            var file = new File([blob], 'voice.' + ext, { type: type });
            submitMedia('voice', file);
          }
          cleanupVoice();
        };
        recorder.start();
        showVoiceRec(true);
        if (voiceBtn) voiceBtn.classList.add('is-recording');
      }).catch(function () {
        alert('Нет доступа к микрофону.');
        cleanupVoice();
      });
    }

    if (voiceBtn) {
      var onStart = function (e) {
        e.preventDefault();
        e.stopPropagation();
        startVoice();
      };
      var onEnd = function (e) {
        e.preventDefault();
        e.stopPropagation();
        stopVoice(true);
      };
      voiceBtn.addEventListener('pointerdown', onStart);
      voiceBtn.addEventListener('pointerup', onEnd);
      voiceBtn.addEventListener('pointercancel', onEnd);
      voiceBtn.addEventListener('touchstart', onStart, { passive: false });
      voiceBtn.addEventListener('touchend', onEnd, { passive: false });
      voiceBtn.addEventListener('touchcancel', onEnd, { passive: false });
      if (voiceCancel) {
        voiceCancel.addEventListener('click', function (e) {
          e.preventDefault();
          stopVoice(false);
          cleanupVoice();
        });
      }
    }

    closeAllPanels();
  }

  window.iaChatInit = iaChatInit;
})();
