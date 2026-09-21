(function () {
  'use strict';

  var FALLBACK_SEED = '#2E5F7B';
  var MINUTES_PER_DAY = 24 * 60;
  var PAGE_TRANSITION_EXIT_DELAY_MS = 220;

  function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
  }

  function hexToRgb(hex) {
    if (typeof hex !== 'string') {
      return null;
    }

    var value = hex.trim().replace('#', '');
    if (value.length === 3) {
      value = value[0] + value[0] + value[1] + value[1] + value[2] + value[2];
    }

    if (!/^[0-9a-fA-F]{6}$/.test(value)) {
      return null;
    }

    return {
      r: parseInt(value.slice(0, 2), 16),
      g: parseInt(value.slice(2, 4), 16),
      b: parseInt(value.slice(4, 6), 16)
    };
  }

  function rgbToHex(rgb) {
    function toHex(value) {
      var normalized = clamp(Math.round(value), 0, 255);
      var hex = normalized.toString(16);
      return hex.length === 1 ? '0' + hex : hex;
    }

    return '#' + toHex(rgb.r) + toHex(rgb.g) + toHex(rgb.b);
  }

  function mixRgb(a, b, ratio) {
    var t = clamp(ratio, 0, 1);
    return {
      r: a.r + (b.r - a.r) * t,
      g: a.g + (b.g - a.g) * t,
      b: a.b + (b.b - a.b) * t
    };
  }

  function srgbToLinear(channel) {
    var c = channel / 255;
    return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
  }

  function luminance(rgb) {
    return (
      0.2126 * srgbToLinear(rgb.r) +
      0.7152 * srgbToLinear(rgb.g) +
      0.0722 * srgbToLinear(rgb.b)
    );
  }

  function contrastRatio(colorA, colorB) {
    var l1 = luminance(colorA);
    var l2 = luminance(colorB);
    var lighter = Math.max(l1, l2);
    var darker = Math.min(l1, l2);
    return (lighter + 0.05) / (darker + 0.05);
  }

  function rgbToHsl(rgb) {
    var r = rgb.r / 255;
    var g = rgb.g / 255;
    var b = rgb.b / 255;

    var max = Math.max(r, g, b);
    var min = Math.min(r, g, b);
    var h = 0;
    var s = 0;
    var l = (max + min) / 2;

    if (max !== min) {
      var d = max - min;
      s = l > 0.5 ? d / (2 - max - min) : d / (max + min);

      switch (max) {
        case r:
          h = (g - b) / d + (g < b ? 6 : 0);
          break;
        case g:
          h = (b - r) / d + 2;
          break;
        default:
          h = (r - g) / d + 4;
      }

      h /= 6;
    }

    return {
      h: h * 360,
      s: s * 100,
      l: l * 100
    };
  }

  function hslToRgb(hsl) {
    var h = hsl.h / 360;
    var s = hsl.s / 100;
    var l = hsl.l / 100;

    if (s === 0) {
      var gray = l * 255;
      return { r: gray, g: gray, b: gray };
    }

    function hueToRgb(p, q, t) {
      var x = t;
      if (x < 0) {
        x += 1;
      }
      if (x > 1) {
        x -= 1;
      }
      if (x < 1 / 6) {
        return p + (q - p) * 6 * x;
      }
      if (x < 1 / 2) {
        return q;
      }
      if (x < 2 / 3) {
        return p + (q - p) * (2 / 3 - x) * 6;
      }
      return p;
    }

    var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
    var p = 2 * l - q;

    return {
      r: hueToRgb(p, q, h + 1 / 3) * 255,
      g: hueToRgb(p, q, h) * 255,
      b: hueToRgb(p, q, h - 1 / 3) * 255
    };
  }

  function ensureContrast(candidateHex, backgroundHex, target) {
    var candidate = hexToRgb(candidateHex);
    var background = hexToRgb(backgroundHex);

    if (!candidate || !background) {
      return '#1B2430';
    }

    if (contrastRatio(candidate, background) >= target) {
      return rgbToHex(candidate);
    }

    var black = { r: 0, g: 0, b: 0 };
    if (contrastRatio(black, background) >= target) {
      return '#000000';
    }

    var white = { r: 255, g: 255, b: 255 };
    if (contrastRatio(white, background) >= target) {
      return '#FFFFFF';
    }

    var hsl = rgbToHsl(candidate);
    var best = candidate;

    for (var i = 0; i < 64; i++) {
      var amount = (i + 1) / 64;
      var darker = hslToRgb({ h: hsl.h, s: hsl.s, l: clamp(hsl.l - amount * 60, 0, 100) });
      if (contrastRatio(darker, background) >= target) {
        best = darker;
        break;
      }

      var lighter = hslToRgb({ h: hsl.h, s: hsl.s, l: clamp(hsl.l + amount * 60, 0, 100) });
      if (contrastRatio(lighter, background) >= target) {
        best = lighter;
        break;
      }
    }

    return rgbToHex(best);
  }

  function generateFallbackPalette(seedHex) {
    var seed = hexToRgb(seedHex) || hexToRgb(FALLBACK_SEED);
    var paper = { r: 255, g: 255, b: 255 };
    var bg = mixRgb(seed, paper, 0.88);
    var surface = mixRgb(seed, paper, 0.94);
    var elevated = mixRgb(seed, paper, 0.97);
    var text = ensureContrast('#1B2430', rgbToHex(surface), 4.5);

    var hsl = rgbToHsl(seed);
    var accentStrong = ensureContrast(rgbToHex(hslToRgb({ h: hsl.h, s: clamp(hsl.s, 32, 76), l: clamp(hsl.l - 26, 16, 42) })), rgbToHex(surface), 4.5);
    var accent = ensureContrast(rgbToHex(hslToRgb({ h: hsl.h, s: clamp(hsl.s, 28, 72), l: clamp(hsl.l - 14, 24, 52) })), rgbToHex(surface), 3);
    var accentSoft = rgbToHex(mixRgb(hexToRgb(accent), surface, 0.82));
    var coverLeftBg = rgbToHex(mixRgb(hexToRgb(accentSoft), surface, 0.42));
    var coverLeftText = ensureContrast(text, coverLeftBg, 4.5);
    var coverLeftTextRgb = hexToRgb(coverLeftText) || hexToRgb(text) || { r: 27, g: 36, b: 48 };
    var coverLeftBgRgb = hexToRgb(coverLeftBg) || surface;

    return {
      bg: rgbToHex(bg),
      surface: rgbToHex(surface),
      elevated: rgbToHex(elevated),
      text: text,
      textMuted: rgbToHex(mixRgb(hexToRgb(text), surface, 0.58)),
      textSubtle: rgbToHex(mixRgb(hexToRgb(text), surface, 0.7)),
      accent: accent,
      accentStrong: accentStrong,
      accentSoft: accentSoft,
      navHover: rgbToHex(mixRgb(hexToRgb(accent), surface, 0.9)),
      focus: accentStrong,
      coverScrim: 'linear-gradient(120deg, rgba(8, 13, 24, 0.78), rgba(8, 13, 24, 0.38))',
      coverLeftBg: coverLeftBg,
      coverLeftText: coverLeftText,
      coverLeftMuted: rgbToHex(mixRgb(coverLeftTextRgb, coverLeftBgRgb, 0.54)),
      coverLeftSubtle: rgbToHex(mixRgb(coverLeftTextRgb, coverLeftBgRgb, 0.68)),
      coverRightText: '#FBF8F2',
      coverRightMuted: 'rgba(247, 242, 233, 0.88)'
    };
  }

  var TIME_COLOR_STOPS = [
    { minute: 0, h: 238, s: 52, l: 28 },   // midnight indigo
    { minute: 300, h: 26, s: 84, l: 56 },  // dawn amber
    { minute: 540, h: 44, s: 70, l: 62 },  // morning gold
    { minute: 720, h: 205, s: 78, l: 52 }, // midday cerulean
    { minute: 1020, h: 32, s: 86, l: 56 }, // sunset orange
    { minute: 1260, h: 286, s: 48, l: 42 }, // twilight violet
    { minute: 1439, h: 238, s: 52, l: 28 } // late night indigo
  ];

  function normalizeMinutes(value) {
    var numeric = parseInt(value, 10);
    if (isNaN(numeric)) {
      return 0;
    }
    return clamp(numeric, 0, MINUTES_PER_DAY - 1);
  }

  function minutesSinceMidnight(date) {
    return (date.getHours() * 60) + date.getMinutes();
  }

  function interpolateHue(startHue, endHue, amount) {
    var delta = ((endHue - startHue + 540) % 360) - 180;
    return (startHue + delta * amount + 360) % 360;
  }

  function interpolateTimeStop(minutes) {
    var normalized = normalizeMinutes(minutes);
    var from = TIME_COLOR_STOPS[0];
    var to = TIME_COLOR_STOPS[TIME_COLOR_STOPS.length - 1];

    for (var i = 0; i < TIME_COLOR_STOPS.length - 1; i++) {
      var current = TIME_COLOR_STOPS[i];
      var next = TIME_COLOR_STOPS[i + 1];
      if (normalized >= current.minute && normalized <= next.minute) {
        from = current;
        to = next;
        break;
      }
    }

    var span = Math.max(1, to.minute - from.minute);
    var t = (normalized - from.minute) / span;
    return {
      h: interpolateHue(from.h, to.h, t),
      s: from.s + (to.s - from.s) * t,
      l: from.l + (to.l - from.l) * t
    };
  }

  function getTimeSeedColor(minutes) {
    var hsl = interpolateTimeStop(minutes);
    return rgbToHex(hslToRgb({
      h: hsl.h,
      s: clamp(hsl.s, 24, 88),
      l: clamp(hsl.l, 20, 72)
    }));
  }

  function applyPalette(node, palette) {
    if (!node || !palette) {
      return;
    }

    /* Contract slots. Every --gn-* name below aliases the slot it maps to in
       theme.css, so writing the slot repaints the private name with it — and
       --folio-rule / --folio-rule-strong, which mix from --folio-text and
       --folio-ground, track the repaint too. Write the slot, never the alias:
       set the alias instead and anything reading the contract goes stale. */
    node.style.setProperty('--folio-ground', palette.bg);
    node.style.setProperty('--folio-surface', palette.surface);
    node.style.setProperty('--folio-surface-alt', palette.elevated);
    node.style.setProperty('--folio-text', palette.text);
    node.style.setProperty('--folio-text-muted', palette.textMuted);
    node.style.setProperty('--folio-text-subtle', palette.textSubtle);
    node.style.setProperty('--folio-accent', palette.accent);
    node.style.setProperty('--folio-accent-hover', palette.accentStrong);
    node.style.setProperty('--folio-accent-soft', palette.accentSoft);
    /* theme.css aliases --gn-focus onto this slot, so setting the alias left
       --folio-focus on its stylesheet literal while the ring rendered in the
       live palette — the theme looked right and the contract lied. */
    node.style.setProperty('--folio-focus', palette.focus);

    /* Privates with no slot: theme.css holds a literal for each of these, so
       there is nothing to alias onto and the private name is the only handle. */
    node.style.setProperty('--gn-nav-hover', palette.navHover);
    node.style.setProperty('--gn-cover-scrim', palette.coverScrim);
    node.style.setProperty('--gn-cover-left-bg', palette.coverLeftBg || palette.surface);
    node.style.setProperty('--gn-cover-left-text', palette.coverLeftText || palette.text);
    node.style.setProperty('--gn-cover-left-muted', palette.coverLeftMuted || palette.textMuted);
    node.style.setProperty('--gn-cover-left-subtle', palette.coverLeftSubtle || palette.textSubtle);
    node.style.setProperty('--gn-cover-right-text', palette.coverRightText || '#FBF8F2');
    node.style.setProperty('--gn-cover-right-muted', palette.coverRightMuted || 'rgba(247, 242, 233, 0.88)');
  }

  function initNavigation() {
    var navs = document.querySelectorAll('.gn-cover .g-folio__theme-nav, .gn-page .g-folio__theme-page-nav');
    if (!navs.length) {
      return;
    }

    function getOpeners(nav) {
      if (nav.classList.contains('g-folio__theme-nav')) {
        return document.querySelectorAll('.gn-cover .g-folio__theme-nav-button');
      }

      return document.querySelectorAll('.gn-page .g-folio__theme-page-nav-button');
    }

    function setOpen(nav, isOpen) {
      var openers = getOpeners(nav);

      nav.classList.toggle('visible', isOpen);
      openers.forEach(function (opener) {
        opener.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });
    }

    function close(nav) {
      setOpen(nav, false);
    }

    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') {
        return;
      }

      navs.forEach(function (nav) {
        if (nav.classList.contains('visible')) {
          close(nav);
        }
      });
    });

    document.addEventListener('click', function (event) {
      navs.forEach(function (nav) {
        var content;
        var openers;
        var i;

        if (!nav.classList.contains('visible')) {
          return;
        }

        content = nav.querySelector('.g-folio__theme-nav-content, .g-folio__theme-page-nav-content');
        if (content && content.contains(event.target)) {
          return;
        }

        openers = getOpeners(nav);
        for (i = 0; i < openers.length; i++) {
          if (openers[i].contains(event.target)) {
            return;
          }
        }

        close(nav);
      });
    });

    navs.forEach(function (nav) {
      var openers = getOpeners(nav);
      var closers = nav.querySelectorAll('.g-folio__theme-nav-close, .g-folio__theme-page-nav-close');

      openers.forEach(function (opener) {
        opener.setAttribute('aria-expanded', 'false');
        opener.addEventListener('click', function () {
          setOpen(nav, !nav.classList.contains('visible'));
        });
      });

      closers.forEach(function (closer) {
        closer.addEventListener('click', function () {
          close(nav);
        });
      });
    });
  }

  function resolveScrollTarget(scope, selector) {
    if (!selector) {
      return null;
    }

    var root = scope || document;
    if (selector.charAt(0) === '#') {
      var id = selector.slice(1);
      if (!id) {
        return null;
      }

      var byId = document.getElementById(id);
      if (!byId) {
        return null;
      }

      return root === document || root.contains(byId) ? byId : null;
    }

    try {
      return root.querySelector(selector);
    } catch (_error) {
      return null;
    }
  }

  function scrollToTarget(selector, scope) {
    if (!selector) {
      return;
    }

    var target = resolveScrollTarget(scope || document, selector);
    if (!target) {
      return;
    }

    target.scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    });
  }

  function initPageUtilities() {
    var pages = document.querySelectorAll('.gn-page');
    if (!pages.length) {
      return;
    }

    pages.forEach(function (page) {
      var mobileToggle = page.querySelector('.g-folio__theme-page-nav-bar-toggle');
      var mobileNav = page.querySelector('.g-folio__theme-page-mobile-nav');
      var feature = page.querySelector('#gn-page-feature');
      var catalogLinks = Array.prototype.slice.call(page.querySelectorAll('.g-folio__theme-page-catalog-link'));
      var sectionAnchors = catalogLinks
        .map(function (link) {
          var selector = link.getAttribute('data-g-scroll-target');
          var target = resolveScrollTarget(page, selector);

          if (!selector || !target) {
            return null;
          }

          return {
            id: selector,
            target: target
          };
        })
        .filter(Boolean);
      var resizeHandler;
      var scrollHandler;

      function setMobileNavOpen(isOpen) {
        if (!mobileNav || !mobileToggle) {
          return;
        }

        mobileNav.classList.toggle('visible', isOpen);
        mobileToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      }

      function setActiveCatalogLink(selector) {
        if (!catalogLinks.length) {
          return;
        }

        catalogLinks.forEach(function (link) {
          var isActive = selector !== '' && link.getAttribute('data-g-scroll-target') === selector;
          link.classList.toggle('g-folio__theme-page-catalog-link--active', isActive);
          if (isActive) {
            link.setAttribute('aria-current', 'location');
          } else {
            link.removeAttribute('aria-current');
          }
        });
      }

      function syncActiveSection() {
        var activeSelector = '';

        if (!sectionAnchors.length) {
          return;
        }

        for (var i = 0; i < sectionAnchors.length; i++) {
          var rect = sectionAnchors[i].target.getBoundingClientRect();
          if (rect.top <= Math.max(160, window.innerHeight * 0.24)) {
            activeSelector = sectionAnchors[i].id;
          } else {
            break;
          }
        }

        if (!activeSelector) {
          activeSelector = sectionAnchors[0].id;
        }

        setActiveCatalogLink(activeSelector);
      }

      if (mobileToggle && mobileNav) {
        mobileToggle.setAttribute('aria-expanded', 'false');
        mobileToggle.addEventListener('click', function () {
          setMobileNavOpen(!mobileNav.classList.contains('visible'));
        });

        mobileNav.querySelectorAll('.g-folio__theme-page-mobile-nav-item-link').forEach(function (link) {
          link.addEventListener('click', function () {
            setMobileNavOpen(false);
          });
        });
      }

      page.querySelectorAll('[data-g-scroll-target]').forEach(function (button) {
        button.addEventListener('click', function (event) {
          var targetSelector = button.getAttribute('data-g-scroll-target');
          if (button.tagName === 'A') {
            event.preventDefault();
          }

          setMobileNavOpen(false);
          setActiveCatalogLink(targetSelector || '');
          scrollToTarget(targetSelector, page);
        });
      });

      function syncFeaturePeek() {
        if (!feature || !page.querySelector('.gn-page__feature-peek')) {
          return;
        }

        var rect = feature.getBoundingClientRect();
        var isFeatureOut = rect.bottom <= 0;
        page.classList.toggle('gn-page--feature-out', isFeatureOut);
      }

      resizeHandler = function () {
        syncFeaturePeek();
      };

      scrollHandler = function () {
        syncFeaturePeek();
        syncActiveSection();
      };

      syncFeaturePeek();
      syncActiveSection();
      window.addEventListener('resize', resizeHandler);
      window.addEventListener('scroll', scrollHandler, { passive: true });
    });
  }

  function initPageTransitions() {
    var wrappers = Array.prototype.slice.call(document.querySelectorAll('.gn-cover, .gn-page'));
    var reduceMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    var hasViewTransitions = typeof document.startViewTransition === 'function';
    var isNavigating = false;

    if (!wrappers.length) {
      return;
    }

    function clearTransitionClasses() {
      wrappers.forEach(function (wrapper) {
        wrapper.classList.remove('gn-is-entering');
        wrapper.classList.remove('gn-is-exiting');
      });
    }

    function animateEntry() {
      if (reduceMotionQuery.matches) {
        return;
      }

      wrappers.forEach(function (wrapper) {
        wrapper.classList.add('gn-is-entering');
      });

      window.requestAnimationFrame(function () {
        window.requestAnimationFrame(function () {
          wrappers.forEach(function (wrapper) {
            wrapper.classList.remove('gn-is-entering');
          });
        });
      });
    }

    function isEligibleLink(link, event) {
      var href = link.getAttribute('href');
      var url;

      if (
        !href ||
        href.charAt(0) === '#' ||
        link.hasAttribute('download') ||
        link.hasAttribute('data-g-scroll-target') ||
        (link.target && link.target !== '_self') ||
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
      ) {
        return false;
      }

      try {
        url = new URL(link.href, window.location.href);
      } catch (_error) {
        return false;
      }

      if (url.protocol !== 'http:' && url.protocol !== 'https:') {
        return false;
      }

      if (url.origin !== window.location.origin) {
        return false;
      }

      if (url.pathname === window.location.pathname && url.search === window.location.search) {
        return false;
      }

      return true;
    }

    clearTransitionClasses();
    animateEntry();

    window.addEventListener('pageshow', clearTransitionClasses);

    if (hasViewTransitions || reduceMotionQuery.matches) {
      return;
    }

    document.addEventListener('click', function (event) {
      var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;

      if (!link || isNavigating || !link.closest('.gn-cover, .gn-page') || !isEligibleLink(link, event)) {
        return;
      }

      isNavigating = true;
      event.preventDefault();
      wrappers.forEach(function (wrapper) {
        wrapper.classList.add('gn-is-exiting');
      });

      window.setTimeout(function () {
        window.location.assign(link.href);
      }, PAGE_TRANSITION_EXIT_DELAY_MS);
    }, true);
  }

  function initTimeColorSystem(wrappers) {
    var paletteCache = {};

    function applyThemeForMinutes(minutes) {
      var normalized = normalizeMinutes(minutes);
      var seed = getTimeSeedColor(normalized);
      var palette = paletteCache[normalized];

      if (!palette) {
        palette = generateFallbackPalette(seed);
        paletteCache[normalized] = palette;
      }

      wrappers.forEach(function (node) {
        applyPalette(node, palette);
      });
    }

    function syncLocalClockTheme() {
      applyThemeForMinutes(minutesSinceMidnight(new Date()));
    }

    syncLocalClockTheme();
    setInterval(syncLocalClockTheme, 30000);
  }

  function init() {
    var wrappers = document.querySelectorAll('.gn-cover, .gn-page');
    if (!wrappers.length) {
      return;
    }

    initPageTransitions();
    initNavigation();
    initPageUtilities();
    initTimeColorSystem(wrappers);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
