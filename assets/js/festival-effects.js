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

    var banner = document.createElement('section');
    banner.className = 'festival-banner festival-banner--' + style;
    banner.setAttribute('role', 'region');
    banner.setAttribute('aria-label', title);

    banner.innerHTML =
      '<div class="festival-banner-toran" aria-hidden="true">' + toranBeads(28, style) + '</div>' +
      '<div class="festival-banner-inner">' +
        '<div class="festival-banner-flag" aria-hidden="true"></div>' +
        '<span class="festival-banner-emoji" aria-hidden="true">' + emoji + '</span>' +
        '<div class="festival-banner-copy">' +
          '<strong class="festival-banner-title">' + title + '</strong>' +
          (tagline ? '<span class="festival-banner-tagline">' + tagline + '</span>' : '') +
        '</div>' +
        '<span class="festival-banner-emoji" aria-hidden="true">' + emoji + '</span>' +
        '<div class="festival-banner-flag festival-banner-flag-flip" aria-hidden="true"></div>' +
      '</div>' +
      '<div class="festival-banner-hang" aria-hidden="true">' +
        '<span></span><span></span><span></span><span></span><span></span><span></span><span></span>' +
      '</div>';

    // Place after ribbon if present, else at top of body
    var ribbon = document.querySelector('.festival-ribbon');
    if (ribbon && ribbon.parentNode) {
      ribbon.parentNode.insertBefore(banner, ribbon.nextSibling);
    } else {
      document.body.insertBefore(banner, document.body.firstChild);
    }
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

    var layer = document.createElement('div');
    layer.id = 'festival-fx-layer';
    layer.className = 'festival-fx-layer festival-fx-' + effect;
    layer.setAttribute('aria-hidden', 'true');

    var count = window.matchMedia('(max-width: 768px)').matches ? 16 : 32;
    var emoji = motifEmoji(motif);

    for (var i = 0; i < count; i++) {
      var p = document.createElement('span');
      p.className = 'festival-fx-particle';
      p.style.left = (Math.random() * 100).toFixed(2) + '%';
      p.style.animationDelay = (Math.random() * 8).toFixed(2) + 's';
      p.style.animationDuration = (6 + Math.random() * 8).toFixed(2) + 's';
      p.style.setProperty('--fx-drift', ((Math.random() * 80) - 40).toFixed(1) + 'px');
      p.style.setProperty('--fx-scale', (0.55 + Math.random() * 0.9).toFixed(2));
      p.style.setProperty('--fx-rot', (Math.random() * 360).toFixed(0) + 'deg');
      if (effect === 'tricolor') {
        var colors = ['#ff9933', '#ffffff', '#138808'];
        p.style.background = colors[i % 3];
        if (i % 3 === 1) p.style.border = '1px solid rgba(0,0,0,.08)';
      } else if (effect === 'lanterns' || effect === 'saffron' || i % 5 === 0) {
        p.textContent = emoji;
        p.classList.add('festival-fx-emoji');
      }
      layer.appendChild(p);
    }

    var corners = document.createElement('div');
    corners.className = 'festival-fx-corners';
    ['tl', 'tr'].forEach(function (pos, idx) {
      var badge = document.createElement('div');
      badge.className = 'festival-fx-corner festival-fx-corner-' + pos;
      badge.innerHTML = '<span class="festival-fx-gif">' + emoji + '</span>';
      badge.style.animationDelay = (idx * 0.4).toFixed(1) + 's';
      corners.appendChild(badge);
    });
    layer.appendChild(corners);

    var wash = document.createElement('div');
    wash.className = 'festival-fx-wash';
    layer.appendChild(wash);

    document.body.appendChild(layer);
    document.body.classList.add('has-festival-fx');
  }

  ready(createLayer);
})();
