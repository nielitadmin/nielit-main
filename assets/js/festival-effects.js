/**
 * Festival background FX + realistic banners + floating decorations.
 * Activated when <html data-festival> is present.
 */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function motifEmoji(motif) {
    var map = {
      sparkles: '✨',
      tricolor: '🇮🇳',
      petals: '🌸',
      lotus: '🪷',
      cross: '✝️',
      crescent: '🌙',
      rath: '🛕',
      peacock: '🦚',
      charkha: '🕊️',
      diya: '🪔',
      khanda: '☬',
      lights: '🎄'
    };
    return map[motif] || '✨';
  }

  function effectForMotif(motif, pattern) {
    if (pattern === 'petals' || motif === 'petals' || motif === 'diya') return 'petals';
    if (pattern === 'sparkles' || motif === 'sparkles' || motif === 'lights') return 'sparkles';
    if (motif === 'tricolor') return 'tricolor';
    if (motif === 'crescent') return 'lanterns';
    if (motif === 'rath') return 'saffron';
    if (motif === 'cross' || motif === 'charkha') return 'softglow';
    if (motif === 'peacock') return 'sparkles';
    if (motif === 'lotus') return 'petals';
    return 'sparkles';
  }

  function toranBeads(count, style) {
    var html = '';
    for (var i = 0; i < count; i++) {
      html += '<span class="festival-toran-bead festival-toran-bead-' + ((i % 5) + 1) + '" style="animation-delay:' + (i * 0.05).toFixed(2) + 's"></span>';
    }
    return html;
  }

  function createBanner() {
    var root = document.documentElement;
    if (root.getAttribute('data-festival-banner') !== '1') return;
    if (document.querySelector('.festival-banner')) return;
    if (!document.body) return;

    var style = root.getAttribute('data-festival-banner-style') || 'sparkle';
    var title = root.getAttribute('data-festival-banner-title') || root.getAttribute('data-festival-label') || 'Festival';
    var tagline = root.getAttribute('data-festival-banner-tagline') || '';
    var emoji = root.getAttribute('data-festival-banner-emoji') || '✨';

    var photo = root.getAttribute('data-festival-banner-photo') || '';
    var sketch = root.getAttribute('data-festival-sketch') || 'sparkles';
    var sketchBase = root.getAttribute('data-festival-asset-base') || '';
    var sketchUrl = sketchBase ? (sketchBase.replace(/\/$/, '') + '/' + sketch + '.svg') : '';

    var banner = document.createElement('section');
    banner.className = 'festival-banner festival-banner--' + style + ' festival-banner--sided' + (photo ? ' festival-banner--photo' : '');
    banner.setAttribute('role', 'region');
    banner.setAttribute('aria-label', title);
    if (photo) {
      banner.style.setProperty('--festival-banner-photo', 'url("' + photo + '")');
    }
    if (sketchUrl) {
      banner.style.setProperty('--festival-banner-sketch', 'url("' + sketchUrl + '")');
    }

    // Three-part banner: left art | greeting | right mirrored art
    var leftArt = photo
      ? '<span class="festival-banner-portrait" style="background-image:url(\'' + photo.replace(/'/g, '%27') + '\')"></span>'
      : (sketchUrl ? '<span class="festival-banner-sketch" aria-hidden="true"></span>' : '');
    var rightArt = sketchUrl
      ? '<span class="festival-banner-sketch festival-banner-sketch-mirror" aria-hidden="true"></span>'
      : (photo ? '<span class="festival-banner-portrait festival-banner-portrait-soft" style="background-image:url(\'' + photo.replace(/'/g, '%27') + '\')"></span>' : '');

    banner.innerHTML =
      '<div class="festival-banner-toran" aria-hidden="true">' + toranBeads(12, style) + '</div>' +
      '<div class="festival-banner-inner">' +
        '<div class="festival-banner-side festival-banner-side-left" aria-hidden="true">' + leftArt + '</div>' +
        '<div class="festival-banner-center">' +
          '<span class="festival-banner-emoji" aria-hidden="true">' + emoji + '</span>' +
          '<div class="festival-banner-copy">' +
            '<strong class="festival-banner-title">' + title + '</strong>' +
            (tagline ? '<span class="festival-banner-tagline">' + tagline + '</span>' : '') +
          '</div>' +
          '<span class="festival-banner-emoji" aria-hidden="true">' + emoji + '</span>' +
        '</div>' +
        '<div class="festival-banner-side festival-banner-side-right" aria-hidden="true">' + rightArt + '</div>' +
      '</div>';

    // Prefer banner over ribbon — avoid double header strip
    var ribbon = document.querySelector('.festival-ribbon');
    if (ribbon && ribbon.parentNode) {
      ribbon.parentNode.replaceChild(banner, ribbon);
    } else {
      document.body.insertBefore(banner, document.body.firstChild);
    }
  }

  function createSketchBackground() {
    // Fixed watermark above section backgrounds (z-index 40 in CSS).
    if (document.getElementById('festival-sketch-bg')) return;
    var root = document.documentElement;
    var sketch = root.getAttribute('data-festival-sketch') || 'sparkles';
    var base = root.getAttribute('data-festival-asset-base') || '';
    if (!base) return;

    var url = base.replace(/\/$/, '') + '/' + sketch + '.svg';
    var cssUrl = 'url("' + url + '")';
    root.style.setProperty('--festival-sketch-url', cssUrl);

    var bg = document.createElement('div');
    bg.id = 'festival-sketch-bg';
    bg.className = 'festival-sketch-bg festival-sketch-' + sketch;
    bg.setAttribute('aria-hidden', 'true');
    bg.style.setProperty('--festival-sketch-url', cssUrl);
    bg.style.backgroundImage = cssUrl;
    document.body.appendChild(bg);
    document.body.classList.add('has-festival-sketch');
  }

  function createLayer() {
    if (document.getElementById('festival-fx-layer')) return;
    var root = document.documentElement;
    if (!root.getAttribute('data-festival')) return;

    var motif = root.getAttribute('data-festival-motif') || 'sparkles';
    var pattern = root.getAttribute('data-festival-pattern') || 'none';
    var effect = root.getAttribute('data-festival-effect') || effectForMotif(motif, pattern);
    root.setAttribute('data-festival-effect', effect);

    createBanner();
    createSketchBackground();

    var layer = document.createElement('div');
    layer.id = 'festival-fx-layer';
    layer.className = 'festival-fx-layer festival-fx-' + effect;
    layer.setAttribute('aria-hidden', 'true');

    // Fewer particles — avoid busy overlays on content
    var count = window.matchMedia('(max-width: 768px)').matches ? 4 : 7;
    var emoji = motifEmoji(motif);

    for (var i = 0; i < count; i++) {
      var p = document.createElement('span');
      p.className = 'festival-fx-particle';
      p.style.left = (8 + Math.random() * 84).toFixed(2) + '%';
      p.style.animationDelay = (Math.random() * 8).toFixed(2) + 's';
      p.style.animationDuration = (8 + Math.random() * 10).toFixed(2) + 's';
      p.style.setProperty('--fx-drift', ((Math.random() * 40) - 20).toFixed(1) + 'px');
      p.style.setProperty('--fx-scale', (0.45 + Math.random() * 0.5).toFixed(2));
      p.style.setProperty('--fx-rot', (Math.random() * 360).toFixed(0) + 'deg');
      p.style.opacity = '0.35';
      if (effect === 'tricolor') {
        var colors = ['#ff9933', '#ffffff', '#138808'];
        p.style.background = colors[i % 3];
        if (i % 3 === 1) p.style.border = '1px solid rgba(0,0,0,.08)';
      } else if (effect === 'lanterns' || effect === 'saffron') {
        if (i % 3 === 0) {
          p.textContent = emoji;
          p.classList.add('festival-fx-emoji');
        }
      }
      layer.appendChild(p);
    }
    // Corner badges skipped on public homepage (CSS); keep none here for cleanliness.

    document.body.appendChild(layer);
    document.body.classList.add('has-festival-fx');
  }

  ready(createLayer);
})();
