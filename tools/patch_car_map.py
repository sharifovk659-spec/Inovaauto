# -*- coding: utf-8 -*-
from pathlib import Path

p = Path(__file__).resolve().parents[1] / 'car.php'
t = p.read_text(encoding='utf-8')

old_map = (
    '                <div id="iaCarGeoMapCanvas" '
    'class="ia-car-nearby-map-el rounded border"></motion>'
)
old_map = old_map.replace('</motion>', '</div>')
new_map = (
    '                <div class="ia-car-map-wrap rounded border overflow-hidden">\n'
    '                    <div id="iaCarGeoMapCanvas" class="ia-car-nearby-map-el"></div>\n'
    '                </div>'
)

if old_map not in t:
    i = t.find('iaCarGeoMapCanvas')
    raise SystemExit('map html not found: ' + repr(t[i - 60 : i + 90]))
t = t.replace(old_map, new_map, 1)

old_js_start = """  function pinSvg(fill, stroke) {
    return '<svg xmlns="http://www.w3.org/2000/svg" width="30" height="38" viewBox="0 0 30 38" focusable="false" aria-hidden="true">'
      + '<path fill="' + fill + '" stroke="' + stroke + '" stroke-width="1.25" d="M15 1C8.4 1 3 6.1 3 12.4c0 8.2 12 23.6 12 23.6S27 20.6 27 12.4C27 6.1 21.6 1 15 1z"/>'
      + '<circle cx="15" cy="12" r="4" fill="#fff" opacity="0.95"/></svg>';
  }
  function pinIcon(fill, stroke) {
    return L.divIcon({
      className: 'ia-leaflet-car-pin',
      html: pinSvg(fill, stroke),
      iconSize: [30, 38],
      iconAnchor: [15, 38],
      popupAnchor: [0, -34]
    });
  }
  var iconThis = pinIcon('#2563eb', '#1e3a8a');
  var iconNear = pinIcon('#dc2626', '#7f1d1d');"""

new_js_icons = """  function carIcon(fill, stroke) {
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" focusable="false" aria-hidden="true">'
      + '<circle cx="12" cy="12" r="11" fill="#fff" stroke="' + stroke + '" stroke-width="1.2"/>'
      + '<path fill="' + fill + '" d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.22.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm12 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/>'
      + '</svg>';
    return L.divIcon({
      className: 'ia-leaflet-car-pin',
      html: svg,
      iconSize: [40, 40],
      iconAnchor: [20, 20],
      popupAnchor: [0, -18]
    });
  }
  var iconThis = carIcon('#dc2626', '#991b1b');
  var iconNear = carIcon('#2563eb', '#1e3a8a');"""

if old_js_start not in t:
    raise SystemExit('js icons not found')
t = t.replace(old_js_start, new_js_icons, 1)

t = t.replace(
    "    t.push('Синяя метка — это объявление; красные — другие авто в радиусе ' + Math.round(radiusM) + ' м.');",
    "    t.push('Красная иконка — это объявление; синие — другие авто в радиусе ' + Math.round(radiusM) + ' м.');",
    1,
)

old_init = """  function initMap() {
    var first = raw.pins[0];
    if (!map) {
      map = L.map(el, { scrollWheelZoom: true }).setView([first.lat, first.lng], 14);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
      }).addTo(map);

      var circle = L.circle([first.lat, first.lng], {
        radius: radiusM,
        color: '#2563eb',
        weight: 1.5,
        dashArray: '6 8',
        fillColor: '#3b82f6',
        fillOpacity: 0.06
      }).addTo(map);

      var layers = [circle];
      raw.pins.forEach(function (p) {
        var ic = p.home ? iconThis : iconNear;
        var m = L.marker([p.lat, p.lng], { icon: ic }).bindPopup(p.popupHtml || '');
        m.addTo(map);
        layers.push(m);
      });

      try {
        var fg = L.featureGroup(layers);
        map.fitBounds(fg.getBounds().pad(0.12));
      } catch (e) {
        map.setView([first.lat, first.lng], 14);
      }

      if (osmA) {
        osmA.href = osmHref(first.lat, first.lng);
      }
    }
    setTimeout(function () {
      if (map) {
        map.invalidateSize();
      }
    }, 280);
  }"""

new_init = """  function homePin() {
    for (var i = 0; i < raw.pins.length; i++) {
      if (raw.pins[i].home) return raw.pins[i];
    }
    return raw.pins[0];
  }
  function refreshMapView() {
    if (!map) return;
    var home = homePin();
    map.invalidateSize({ animate: false });
    var bounds = L.circle([home.lat, home.lng], { radius: radiusM }).getBounds();
    map.fitBounds(bounds, { padding: [28, 28], maxZoom: 16 });
  }
  function initMap() {
    var home = homePin();
    if (!map) {
      map = L.map(el, { scrollWheelZoom: true });
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
      }).addTo(map);

      L.circle([home.lat, home.lng], {
        radius: radiusM,
        color: '#dc2626',
        weight: 2,
        dashArray: '7 9',
        fillColor: '#ef4444',
        fillOpacity: 0.08
      }).addTo(map);

      raw.pins.forEach(function (p) {
        var ic = p.home ? iconThis : iconNear;
        L.marker([p.lat, p.lng], { icon: ic }).addTo(map).bindPopup(p.popupHtml || '');
      });

      if (osmA) {
        osmA.href = osmHref(home.lat, home.lng);
      }
    }
    refreshMapView();
    setTimeout(refreshMapView, 80);
    setTimeout(refreshMapView, 320);
  }"""

if old_init not in t:
    raise SystemExit('initMap not found')
t = t.replace(old_init, new_init, 1)

p.write_text(t, encoding='utf-8')
print('patched car.php OK')
