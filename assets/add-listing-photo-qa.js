(function (global) {
  'use strict';

  var MSG = {
    car: 'Автомобиль не найден',
    blur: 'Фото размыто',
    dark: 'Недостаточно света',
    crop: 'Автомобиль должен быть полностью в кадре',
    angle: 'Неверный ракурс',
    retake: ' Переснимите фото.'
  };

  var MIN_SHORT = 480;
  var MAX_LONG = 4096;
  var MIN_ASPECT = 9 / 16;
  var MAX_ASPECT = 16 / 9;
  var ANALYZE_MAX = 240;
  var OPENCV_URL = 'https://docs.opencv.org/4.9.0/opencv.js';

  var cvLoading = false;
  var cvReady = false;
  var cvQueue = [];

  function withRetake(code) {
    return (MSG[code] || 'Фото не подходит') + MSG.retake;
  }

  function isImageFile(f) {
    if (!f) return false;
    var t = String(f.type || '');
    var n = String(f.name || '');
    if (/^image\//.test(t)) return true;
    if (/\.(jpe?g|png|webp|gif|heic|heif)$/i.test(n)) return true;
    return t === '' && (f.size || 0) > 0;
  }

  function isHeicFile(f) {
    if (!f) return false;
    var t = String(f.type || '').toLowerCase();
    var n = String(f.name || '').toLowerCase();
    return t === 'image/heic' || t === 'image/heif' || /\.heic$/.test(n) || /\.heif$/.test(n);
  }

  function flushCvQueue() {
    var queue = cvQueue.slice();
    cvQueue = [];
    queue.forEach(function (fn) { fn(); });
  }

  function ensureOpenCv(callback) {
    if (cvReady && global.cv && global.cv.Mat) {
      callback(true);
      return;
    }
    cvQueue.push(function () { callback(cvReady && global.cv && global.cv.Mat); });
    if (cvLoading) return;
    cvLoading = true;
    var script = document.createElement('script');
    script.async = true;
    script.src = OPENCV_URL;
    script.onload = function () {
      var wait = function () {
        if (global.cv && global.cv.Mat) {
          cvReady = true;
          flushCvQueue();
          return;
        }
        global.setTimeout(wait, 40);
      };
      wait();
    };
    script.onerror = function () {
      cvLoading = false;
      flushCvQueue();
    };
    document.head.appendChild(script);
  }

  function toGray(data, w, h) {
    var gray = new Float32Array(w * h);
    for (var y = 0; y < h; y++) {
      for (var x = 0; x < w; x++) {
        var i = (y * w + x) * 4;
        gray[y * w + x] = data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114;
      }
    }
    return gray;
  }

  function meanBrightness(gray) {
    var sum = 0;
    for (var i = 0; i < gray.length; i++) sum += gray[i];
    return gray.length ? sum / gray.length : 0;
  }

  function laplacianVariance(gray, w, h) {
    var sum = 0;
    var sumSq = 0;
    var n = 0;
    for (var y = 1; y < h - 1; y++) {
      for (var x = 1; x < w - 1; x++) {
        var i = y * w + x;
        var lap = -4 * gray[i] + gray[i - 1] + gray[i + 1] + gray[i - w] + gray[i + w];
        sum += lap;
        sumSq += lap * lap;
        n++;
      }
    }
    if (!n) return 0;
    var mean = sum / n;
    return sumSq / n - mean * mean;
  }

  function edgeMetrics(gray, w, h) {
    var edges = 0;
    var total = 0;
    var minX = w;
    var minY = h;
    var maxX = 0;
    var maxY = 0;
    var left = 0;
    var right = 0;
    var top = 0;
    var bottom = 0;
    var centerEdges = 0;
    var centerTotal = 0;
    var cx0 = Math.floor(w * 0.15);
    var cx1 = Math.ceil(w * 0.85);
    var cy0 = Math.floor(h * 0.15);
    var cy1 = Math.ceil(h * 0.85);

    for (var y = 1; y < h - 1; y++) {
      for (var x = 1; x < w - 1; x++) {
        var i = y * w + x;
        var gx = gray[i + 1] - gray[i - 1];
        var gy = gray[i + w] - gray[i - w];
        var mag = Math.sqrt(gx * gx + gy * gy);
        total++;
        if (mag < 12) continue;
        edges++;
        if (x < minX) minX = x;
        if (y < minY) minY = y;
        if (x > maxX) maxX = x;
        if (y > maxY) maxY = y;
        if (x < w * 0.5) left += mag; else right += mag;
        if (y < h * 0.5) top += mag; else bottom += mag;
        if (x >= cx0 && x <= cx1 && y >= cy0 && y <= cy1) centerEdges++;
      }
    }

    for (var cy = cy0; cy <= cy1; cy++) {
      for (var cx = cx0; cx <= cx1; cx++) centerTotal++;
    }

    var bboxW = edges > 0 ? Math.max(1, maxX - minX + 1) : 0;
    var bboxH = edges > 0 ? Math.max(1, maxY - minY + 1) : 0;

    return {
      edge_ratio: total ? edges / total : 0,
      center_ratio: centerTotal ? centerEdges / centerTotal : 0,
      bbox_w_ratio: w ? bboxW / w : 0,
      bbox_h_ratio: h ? bboxH / h : 0,
      left: left,
      right: right,
      top: top,
      bottom: bottom
    };
  }

  /**
   * Ракурс: эвристики по краям кадра. Слот 0 («Спереди») — мягче: фон может быть
   * асимметричным; достаточно заметного объекта по центру или умеренной L/R-симметрии.
   */
  function angleOk(slotIdx, metrics) {
    if (slotIdx === 0) {
      var lrMax = Math.max(metrics.left, metrics.right);
      var lrMin = Math.min(metrics.left, metrics.right);
      var lrRatio = lrMax > 0 ? lrMin / lrMax : 0;
      var symmetricFront = lrRatio >= 0.38;
      var centeredObject =
        metrics.center_ratio >= 0.058 &&
        metrics.bbox_w_ratio >= 0.34 &&
        metrics.bbox_h_ratio >= 0.26;
      var wideAndBalanced =
        lrRatio >= 0.28 &&
        metrics.bbox_w_ratio >= 0.4 &&
        metrics.bbox_h_ratio >= 0.28;
      return symmetricFront || centeredObject || wideAndBalanced;
    }
    if (slotIdx === 1) {
      var lrMax1 = Math.max(metrics.left, metrics.right);
      var lrMin1 = Math.min(metrics.left, metrics.right);
      var lrRatio1 = lrMax1 > 0 ? lrMin1 / lrMax1 : 0;
      var symmetricRear = lrRatio1 >= 0.34;
      var centeredRear =
        metrics.center_ratio >= 0.052 &&
        metrics.bbox_w_ratio >= 0.32 &&
        metrics.bbox_h_ratio >= 0.24;
      var wideRear =
        lrRatio1 >= 0.22 &&
        metrics.bbox_w_ratio >= 0.36 &&
        metrics.bbox_h_ratio >= 0.26;
      return symmetricRear || centeredRear || wideRear;
    }
    if (slotIdx === 2 || slotIdx === 3) {
      return (
        (metrics.bbox_w_ratio >= 0.44 && metrics.bbox_w_ratio >= metrics.bbox_h_ratio * 1.05) ||
        (metrics.center_ratio >= 0.055 && metrics.bbox_w_ratio >= 0.38 && metrics.bbox_h_ratio >= 0.3)
      );
    }
    if (slotIdx === 4) return metrics.left >= metrics.right * 0.85;
    if (slotIdx === 5) return metrics.right >= metrics.left * 0.85;
    if (slotIdx >= 6 && slotIdx <= 8) return metrics.bottom >= metrics.top * 0.75;
    return metrics.center_ratio >= 0.08;
  }

  function evaluatePixels(data, w, h, slotIdx, overrides) {
    overrides = overrides || {};
    var gray = toGray(data, w, h);
    var brightness = typeof overrides.brightness === 'number' ? overrides.brightness : meanBrightness(gray);
    var blurScore = typeof overrides.blurScore === 'number' ? overrides.blurScore : laplacianVariance(gray, w, h);
    var metrics = edgeMetrics(gray, w, h);
    var lightingOk = brightness >= 48;
    var clarityOk = blurScore >= 75;
    var carOk = metrics.edge_ratio >= 0.022 && metrics.center_ratio >= 0.04;
    var cropOk =
      slotIdx === 0 || slotIdx === 1
        ? metrics.bbox_w_ratio >= 0.33 && metrics.bbox_h_ratio >= 0.24
        : metrics.bbox_w_ratio >= 0.38 && metrics.bbox_h_ratio >= 0.3;
    var angleGeometryOk = angleOk(slotIdx, metrics);
    var angleOkFlag = carOk && angleGeometryOk;
    var checks = {
      quality: lightingOk && clarityOk && carOk,
      angle: angleGeometryOk,
      lighting: lightingOk,
      crop: cropOk,
      clarity: clarityOk
    };
    var error = null;
    if (!lightingOk) error = withRetake('dark');
    else if (!clarityOk) error = withRetake('blur');
    else if (!carOk) error = withRetake('car');
    else if (!cropOk) error = withRetake('crop');
    else if (!angleOkFlag) error = withRetake('angle');
    return { error: error, checks: checks };
  }

  function openCvMetrics(canvas) {
    var cv = global.cv;
    var src = cv.imread(canvas);
    var gray = new cv.Mat();
    cv.cvtColor(src, gray, cv.COLOR_RGBA2GRAY);
    var lap = new cv.Mat();
    cv.Laplacian(gray, lap, cv.CV_64F);
    var mean = new cv.Mat();
    var stddev = new cv.Mat();
    cv.meanStdDev(lap, mean, stddev);
    var blurScore = stddev.data64F[0] * stddev.data64F[0];
    var meanGray = new cv.Mat();
    var stdGray = new cv.Mat();
    cv.meanStdDev(gray, meanGray, stdGray);
    var brightness = meanGray.data64F[0];
    src.delete();
    gray.delete();
    lap.delete();
    mean.delete();
    stddev.delete();
    meanGray.delete();
    stdGray.delete();
    return { brightness: brightness, blurScore: blurScore };
  }

  function analyzeCanvas(canvas, slotIdx, done) {
    var ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) {
      done(null, null);
      return;
    }
    var data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
    ensureOpenCv(function (ready) {
      if (ready) {
        try {
          var metrics = openCvMetrics(canvas);
          var result = evaluatePixels(data, canvas.width, canvas.height, slotIdx, metrics);
          done(result.error, result.checks);
          return;
        } catch (e) {}
      }
      var fallback = evaluatePixels(data, canvas.width, canvas.height, slotIdx);
      done(fallback.error, fallback.checks);
    });
  }

  function validate(file, slotIdx, done) {
    if (!isImageFile(file)) {
      done(withRetake('angle'), { quality: false, angle: false, lighting: false, crop: false, clarity: false });
      return;
    }
    if (isHeicFile(file)) {
      done(null, null);
      return;
    }
    var url = URL.createObjectURL(file);
    var img = new Image();
    img.onload = function () {
      var w = img.naturalWidth || 0;
      var h = img.naturalHeight || 0;
      var shortEdge = Math.min(w, h);
      var longEdge = Math.max(w, h);
      var aspect = h > 0 ? w / h : 0;
      if (shortEdge < MIN_SHORT || longEdge > MAX_LONG || aspect < MIN_ASPECT || aspect > MAX_ASPECT) {
        URL.revokeObjectURL(url);
        done(withRetake('angle'), { quality: false, angle: false, lighting: true, crop: false, clarity: true });
        return;
      }

      var scale = Math.min(1, ANALYZE_MAX / Math.max(w, h));
      var cw = Math.max(1, Math.round(w * scale));
      var ch = Math.max(1, Math.round(h * scale));
      var canvas = document.createElement('canvas');
      canvas.width = cw;
      canvas.height = ch;
      var ctx = canvas.getContext('2d', { willReadFrequently: true });
      if (!ctx) {
        URL.revokeObjectURL(url);
        done(null, null);
        return;
      }
      ctx.drawImage(img, 0, 0, cw, ch);
      URL.revokeObjectURL(url);
      analyzeCanvas(canvas, slotIdx, done);
    };
    img.onerror = function () {
      URL.revokeObjectURL(url);
      done(withRetake('car'), { quality: false, angle: false, lighting: false, crop: false, clarity: false });
    };
    img.src = url;
  }

  global.iaListingPhotoQa = { validate: validate, ensureOpenCv: ensureOpenCv };
})(window);
