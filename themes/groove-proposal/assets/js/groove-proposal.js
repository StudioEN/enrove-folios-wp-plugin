(function () {
  'use strict';

  var ROOT = document.documentElement;
  var STORAGE_KEY = 'gp-theme';
  // The dynamic palette writes the CONTRACT slots, not this theme's private
  // names, because every private below aliases its slot in theme.css. Writing
  // the slot repaints the private with it AND keeps --folio-* telling the truth
  // about what the page is painting; writing the private would repaint the
  // theme correctly while leaving the contract on the stylesheet's static value.
  // --gp-accent-mid has no slot, so it stays private. This list must stay in
  // step with applyDynamicPalette() — clearDynamicPalette() removes exactly
  // these, and a name written but not listed would survive leaving dynamic mode.
  var DYNAMIC_ACCENT_PROPERTIES = [
    '--folio-ground',
    '--folio-surface',
    '--folio-surface-alt',
    '--folio-rule',
    '--folio-rule-strong',
    '--folio-text-muted',
    '--folio-text-subtle',
    '--folio-overlay',
    '--folio-accent',
    '--folio-accent-hover',
    '--folio-accent-soft',
    '--gp-accent-mid',
  ];
  var currentDominantColor = null;

  // ─── Adaptive Accent Palette (feature-image driven) ───────────────────────

  function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
  }

  function sRGBtoLinear(channel) {
    var c = channel / 255;
    return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
  }

  function luminance(r, g, b) {
    return 0.2126 * sRGBtoLinear(r) + 0.7152 * sRGBtoLinear(g) + 0.0722 * sRGBtoLinear(b);
  }

  function contrastRatio(l1, l2) {
    var lighter = Math.max(l1, l2);
    var darker = Math.min(l1, l2);
    return (lighter + 0.05) / (darker + 0.05);
  }

  function rgbToHsl(r, g, b) {
    var rn = r / 255;
    var gn = g / 255;
    var bn = b / 255;
    var max = Math.max(rn, gn, bn);
    var min = Math.min(rn, gn, bn);
    var h = 0;
    var s = 0;
    var l = (max + min) / 2;

    if (max !== min) {
      var d = max - min;
      s = l > 0.5 ? d / (2 - max - min) : d / (max + min);

      switch (max) {
        case rn:
          h = (gn - bn) / d + (gn < bn ? 6 : 0);
          break;
        case gn:
          h = (bn - rn) / d + 2;
          break;
        default:
          h = (rn - gn) / d + 4;
          break;
      }

      h /= 6;
    }

    return [h * 360, s * 100, l * 100];
  }

  function hslToRgb(h, s, l) {
    var hn = h / 360;
    var sn = s / 100;
    var ln = l / 100;
    var r;
    var g;
    var b;

    if (sn === 0) {
      r = ln;
      g = ln;
      b = ln;
    } else {
      function hue2rgb(p, q, t) {
        if (t < 0) t += 1;
        if (t > 1) t -= 1;
        if (t < 1 / 6) return p + (q - p) * 6 * t;
        if (t < 1 / 2) return q;
        if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
        return p;
      }

      var q = ln < 0.5 ? ln * (1 + sn) : ln + sn - ln * sn;
      var p = 2 * ln - q;

      r = hue2rgb(p, q, hn + 1 / 3);
      g = hue2rgb(p, q, hn);
      b = hue2rgb(p, q, hn - 1 / 3);
    }

    return [Math.round(r * 255), Math.round(g * 255), Math.round(b * 255)];
  }

  function rgbToHex(r, g, b) {
    return (
      '#' +
      [r, g, b]
        .map(function (value) {
          var hex = value.toString(16);
          return hex.length === 1 ? '0' + hex : hex;
        })
        .join('')
    );
  }

  function rgbaFromRgb(r, g, b, alpha) {
    return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha.toFixed(2) + ')';
  }

  function findLightnessForContrast(hue, saturation, bgLuminance, targetContrast, preferLight) {
    var lo = preferLight ? 50 : 0;
    var hi = preferLight ? 100 : 50;
    var best = preferLight ? 90 : 25;

    for (var i = 0; i < 30; i++) {
      var mid = (lo + hi) / 2;
      var rgb = hslToRgb(hue, saturation, mid);
      var ratio = contrastRatio(luminance(rgb[0], rgb[1], rgb[2]), bgLuminance);

      if (ratio < targetContrast) {
        if (preferLight) {
          hi = mid;
        } else {
          lo = mid;
        }
      } else {
        best = mid;
        if (preferLight) {
          lo = mid;
        } else {
          hi = mid;
        }
      }
    }

    return best;
  }

  function ensureLightnessForContrast(hue, saturation, desiredLightness, bgLuminance, targetContrast, preferLight) {
    var clampedLightness = clamp(desiredLightness, 0, 100);
    var desiredRgb = hslToRgb(hue, saturation, clampedLightness);
    var desiredContrast = contrastRatio(luminance(desiredRgb[0], desiredRgb[1], desiredRgb[2]), bgLuminance);

    if (desiredContrast >= targetContrast) {
      return clampedLightness;
    }

    return findLightnessForContrast(hue, saturation, bgLuminance, targetContrast, preferLight);
  }

  function isDarkModeActive() {
    return (
      ROOT.classList.contains('gp-theme-dark') ||
      (!ROOT.classList.contains('gp-theme-light') && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
    );
  }

  function generateDynamicPalette(color, isDarkMode) {
    if (!color) {
      return null;
    }

    var hsl = rgbToHsl(color.r, color.g, color.b);
    var hue = hsl[0];
    var minContrast = 4.5;
    var saturation = clamp(hsl[1], 24, 78);
    var baseSat = clamp(saturation * (isDarkMode ? 0.72 : 0.52), isDarkMode ? 22 : 16, isDarkMode ? 48 : 40);
    var surfaceSat = clamp(baseSat * 1.1, isDarkMode ? 24 : 18, isDarkMode ? 52 : 44);
    var surfaceMutedSat = clamp(baseSat * 1.24, isDarkMode ? 26 : 20, isDarkMode ? 56 : 46);
    var coreTextRgb = isDarkMode ? [238, 231, 219] : [24, 21, 16];
    var coreTextLum = luminance(coreTextRgb[0], coreTextRgb[1], coreTextRgb[2]);
    var preferLightSurface = !isDarkMode;

    var bgL = ensureLightnessForContrast(hue, baseSat, isDarkMode ? 10 : 91, coreTextLum, minContrast, preferLightSurface);
    var surfaceL = ensureLightnessForContrast(
      hue,
      surfaceSat,
      bgL + (isDarkMode ? 4 : 4),
      coreTextLum,
      minContrast,
      preferLightSurface
    );
    var surfaceMutedL = ensureLightnessForContrast(
      hue,
      surfaceMutedSat,
      bgL + (isDarkMode ? 8 : -3),
      coreTextLum,
      minContrast,
      preferLightSurface
    );

    var bgRgb = hslToRgb(hue, baseSat, bgL);
    var surfaceRgb = hslToRgb(hue, surfaceSat, surfaceL);
    var surfaceMutedRgb = hslToRgb(hue, surfaceMutedSat, surfaceMutedL);

    var bgLum = luminance(bgRgb[0], bgRgb[1], bgRgb[2]);
    var surfaceLum = luminance(surfaceRgb[0], surfaceRgb[1], surfaceRgb[2]);
    var surfaceMutedLum = luminance(surfaceMutedRgb[0], surfaceMutedRgb[1], surfaceMutedRgb[2]);
    var hardestTextBgLum = isDarkMode
      ? Math.max(bgLum, surfaceLum, surfaceMutedLum)
      : Math.min(bgLum, surfaceLum, surfaceMutedLum);

    var borderRgb = hslToRgb(hue, clamp(baseSat * 0.9, 14, 34), isDarkMode ? 52 : 44);
    var borderStrongRgb = hslToRgb(hue, clamp(baseSat * 1.05, 16, 38), isDarkMode ? 62 : 36);

    var textMutedSat = clamp(baseSat * 0.72, 12, 30);
    var textMutedL = findLightnessForContrast(hue, textMutedSat, hardestTextBgLum, minContrast, isDarkMode);
    var textMutedRgb = hslToRgb(hue, textMutedSat, textMutedL);

    var textSubtleSat = clamp(baseSat * 0.58, 10, 26);
    var textSubtleL = findLightnessForContrast(hue, textSubtleSat, hardestTextBgLum, minContrast, isDarkMode);
    var textSubtleRgb = hslToRgb(hue, textSubtleSat, textSubtleL);

    var accentL = findLightnessForContrast(hue, saturation, bgLum, Math.max(minContrast, isDarkMode ? 4.8 : 4.6), isDarkMode);
    var accentRgb = hslToRgb(hue, saturation, accentL);

    var hoverSaturation = clamp(saturation * (isDarkMode ? 0.98 : 0.92), 18, 78);
    var hoverL = findLightnessForContrast(hue, hoverSaturation, bgLum, isDarkMode ? 5.4 : 5.2, isDarkMode);
    var hoverRgb = hslToRgb(hue, hoverSaturation, hoverL);

    return {
      bg: rgbToHex(bgRgb[0], bgRgb[1], bgRgb[2]),
      surface: rgbToHex(surfaceRgb[0], surfaceRgb[1], surfaceRgb[2]),
      surfaceMuted: rgbToHex(surfaceMutedRgb[0], surfaceMutedRgb[1], surfaceMutedRgb[2]),
      border: rgbaFromRgb(borderRgb[0], borderRgb[1], borderRgb[2], isDarkMode ? 0.32 : 0.24),
      borderStrong: rgbaFromRgb(borderStrongRgb[0], borderStrongRgb[1], borderStrongRgb[2], isDarkMode ? 0.5 : 0.36),
      textMuted: rgbToHex(textMutedRgb[0], textMutedRgb[1], textMutedRgb[2]),
      textSubtle: rgbToHex(textSubtleRgb[0], textSubtleRgb[1], textSubtleRgb[2]),
      overlay: rgbaFromRgb(bgRgb[0], bgRgb[1], bgRgb[2], isDarkMode ? 0.82 : 0.66),
      accent: rgbToHex(accentRgb[0], accentRgb[1], accentRgb[2]),
      accentHover: rgbToHex(hoverRgb[0], hoverRgb[1], hoverRgb[2]),
      accentSoft: rgbaFromRgb(accentRgb[0], accentRgb[1], accentRgb[2], isDarkMode ? 0.28 : 0.2),
      accentMid: rgbaFromRgb(accentRgb[0], accentRgb[1], accentRgb[2], isDarkMode ? 0.52 : 0.38),
    };
  }

  function applyDynamicPalette(palette) {
    if (!palette) {
      return;
    }

    var style = ROOT.style;
    style.setProperty('--folio-ground', palette.bg);
    style.setProperty('--folio-surface', palette.surface);
    style.setProperty('--folio-surface-alt', palette.surfaceMuted);
    style.setProperty('--folio-rule', palette.border);
    style.setProperty('--folio-rule-strong', palette.borderStrong);
    style.setProperty('--folio-text-muted', palette.textMuted);
    style.setProperty('--folio-text-subtle', palette.textSubtle);
    style.setProperty('--folio-overlay', palette.overlay);
    style.setProperty('--folio-accent', palette.accent);
    style.setProperty('--folio-accent-hover', palette.accentHover);
    style.setProperty('--folio-accent-soft', palette.accentSoft);
    style.setProperty('--gp-accent-mid', palette.accentMid);
  }

  function clearDynamicPalette() {
    var style = ROOT.style;
    DYNAMIC_ACCENT_PROPERTIES.forEach(function (propertyName) {
      style.removeProperty(propertyName);
    });
  }

  function applyCurrentDynamicPalette() {
    if (!currentDominantColor) {
      clearDynamicPalette();
      return;
    }

    var palette = generateDynamicPalette(currentDominantColor, isDarkModeActive());
    applyDynamicPalette(palette);
  }

  function sampleDominantColorFromImage(image) {
    if (!image || image.naturalWidth <= 0 || image.naturalHeight <= 0) {
      return null;
    }

    try {
      var canvas = document.createElement('canvas');
      var size = 64;
      canvas.width = size;
      canvas.height = size;

      var context = canvas.getContext('2d');
      if (!context) {
        return null;
      }

      context.drawImage(image, 0, 0, size, size);
      var data = context.getImageData(0, 0, size, size).data;
      var rTotal = 0;
      var gTotal = 0;
      var bTotal = 0;
      var weightTotal = 0;

      for (var i = 0; i < data.length; i += 4) {
        var r = data[i];
        var g = data[i + 1];
        var b = data[i + 2];
        var a = data[i + 3];
        if (a < 128) {
          continue;
        }

        var max = Math.max(r, g, b);
        var min = Math.min(r, g, b);
        var saturation = max === 0 ? 0 : (max - min) / max;
        var weight = 1 + saturation * 3;

        rTotal += r * weight;
        gTotal += g * weight;
        bTotal += b * weight;
        weightTotal += weight;
      }

      if (weightTotal === 0) {
        return null;
      }

      return {
        r: Math.round(rTotal / weightTotal),
        g: Math.round(gTotal / weightTotal),
        b: Math.round(bTotal / weightTotal),
      };
    } catch (error) {
      return null;
    }
  }

  function extractDominantColorFromUrl(imageUrl, callback) {
    if (!imageUrl) {
      callback(null);
      return;
    }

    var image = new Image();

    try {
      var parsedUrl = new URL(imageUrl, window.location.href);
      if (parsedUrl.origin !== window.location.origin) {
        image.crossOrigin = 'anonymous';
      }
    } catch (error) {
      // noop
    }

    image.onload = function () {
      callback(sampleDominantColorFromImage(image));
    };

    image.onerror = function () {
      callback(null);
    };

    image.src = imageUrl;
  }

  function initDynamicAccentPalette() {
    var paletteRoot = document.querySelector('.gp-cover, .gp-page');
    if (!paletteRoot) {
      clearDynamicPalette();
      currentDominantColor = null;
      return;
    }

    var colorSchemeMode = String(paletteRoot.getAttribute('data-gp-color-scheme') || 'default').toLowerCase();
    if (colorSchemeMode !== 'dynamic') {
      clearDynamicPalette();
      currentDominantColor = null;
      return;
    }

    function commit(color) {
      if (!color) {
        clearDynamicPalette();
        currentDominantColor = null;
        return;
      }

      currentDominantColor = color;
      applyCurrentDynamicPalette();
    }

    var paletteSourceUrl = String(paletteRoot.getAttribute('data-gp-palette-source-url') || '');
    if (paletteSourceUrl !== '') {
      extractDominantColorFromUrl(paletteSourceUrl, commit);
      return;
    }

    var sourceImage = document.querySelector('.gp-cover__hero-image, .gp-page__feature img');
    if (!sourceImage) {
      clearDynamicPalette();
      currentDominantColor = null;
      return;
    }

    function resolveFromSource() {
      var sampled = sampleDominantColorFromImage(sourceImage);
      if (sampled) {
        commit(sampled);
        return;
      }

      var imageUrl = sourceImage.currentSrc || sourceImage.src || '';
      extractDominantColorFromUrl(imageUrl, commit);
    }

    if (sourceImage.complete && sourceImage.naturalWidth > 0) {
      resolveFromSource();
      return;
    }

    sourceImage.addEventListener('load', resolveFromSource, { once: true });
    sourceImage.addEventListener(
      'error',
      function () {
        clearDynamicPalette();
      },
      { once: true }
    );
  }

  // ─── Theme Toggle ──────────────────────────────────────────────────────────

  function getInitialMode() {
    try {
      var saved = localStorage.getItem(STORAGE_KEY);
      if (saved === 'dark' || saved === 'light') {
        return saved;
      }
    } catch (error) {
      // noop
    }

    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      return 'dark';
    }

    return 'light';
  }

  function applyMode(mode, persist) {
    var normalized = mode === 'dark' ? 'dark' : 'light';

    ROOT.classList.remove('gp-theme-light', 'gp-theme-dark');
    ROOT.classList.add(normalized === 'dark' ? 'gp-theme-dark' : 'gp-theme-light');

    if (persist) {
      try {
        localStorage.setItem(STORAGE_KEY, normalized);
      } catch (error) {
        // noop
      }
    }

    syncThemeToggleButtons(normalized);
    applyCurrentDynamicPalette();
  }

  function syncThemeToggleButtons(mode) {
    var isDark = mode === 'dark';
    document.querySelectorAll('[data-gp-theme-toggle]').forEach(function (button) {
      button.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
      button.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
    });
  }

  function getCurrentMode() {
    return ROOT.classList.contains('gp-theme-dark') ? 'dark' : 'light';
  }

  function initThemeToggle() {
    applyMode(getInitialMode(), false);

    document.querySelectorAll('[data-gp-theme-toggle]').forEach(function (button) {
      button.addEventListener('click', function () {
        applyMode(getCurrentMode() === 'dark' ? 'light' : 'dark', true);
      });
    });
  }

  // ─── Nav Panel: backdrop click-to-close ────────────────────────────────────

  function initNavPanelBackdrop() {
    // Only applies on mobile where the nav is a fixed overlay with a backdrop.
    // On desktop the nav is a permanent sidebar and .visible is never set.
    document.querySelectorAll('.gp .g-folio__theme-page-nav').forEach(function (nav) {
      nav.addEventListener('click', function (event) {
        var content = nav.querySelector('.g-folio__theme-page-nav-content');
        if (content && !content.contains(event.target)) {
          nav.classList.remove('visible');
        }
      });
    });
  }

  // ─── Nav Details Accordion ────────────────────────────────────────────────

  function initNavDetailsPanel() {
    document.querySelectorAll('[data-gp-nav-info]').forEach(function (container) {
      var trigger = container.querySelector('[data-gp-nav-info-toggle]');
      var panel = container.querySelector('.gp-nav__info-panel');
      var collapsedLabel = container.querySelector('[data-gp-nav-info-label-collapsed]');
      var expandedLabel = container.querySelector('[data-gp-nav-info-label-expanded]');
      if (!trigger || !panel) {
        return;
      }

      function setOpen(isOpen, instant) {
        if (instant) {
          panel.style.transition = 'none';
        }

        panel.hidden = false;

        if (isOpen) {
          container.setAttribute('data-gp-nav-info-open', '');
          panel.style.maxHeight = panel.scrollHeight + 'px';
          panel.style.opacity = '1';

          if (instant) {
            panel.style.maxHeight = 'none';
          } else {
            var handleExpandEnd = function (event) {
              if (event.propertyName !== 'max-height') {
                return;
              }
              panel.style.maxHeight = 'none';
              panel.removeEventListener('transitionend', handleExpandEnd);
            };

            panel.addEventListener('transitionend', handleExpandEnd);
          }
        } else {
          container.removeAttribute('data-gp-nav-info-open');
          panel.style.maxHeight = panel.scrollHeight + 'px';

          if (!instant) {
            panel.offsetHeight;
          }

          panel.style.maxHeight = '0px';
          panel.style.opacity = '0';
        }

        trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

        if (collapsedLabel) {
          collapsedLabel.hidden = isOpen;
        }

        if (expandedLabel) {
          expandedLabel.hidden = !isOpen;
        }

        if (instant) {
          panel.offsetHeight;
          panel.style.removeProperty('transition');
        }
      }

      function syncExpandedHeight() {
        if (container.hasAttribute('data-gp-nav-info-open')) {
          if (panel.style.maxHeight !== 'none') {
            panel.style.maxHeight = panel.scrollHeight + 'px';
          }
        }
      }

      var isInitiallyOpen = trigger.getAttribute('aria-expanded') === 'true' || !panel.hidden;
      setOpen(isInitiallyOpen, true);

      trigger.addEventListener('click', function () {
        setOpen(!container.hasAttribute('data-gp-nav-info-open'), false);
      });

      window.addEventListener('resize', syncExpandedHeight);
    });
  }

  // ─── Smooth Scroll ─────────────────────────────────────────────────────────

  function resolveScrollTarget(scope, selector) {
    if (!selector) {
      return null;
    }

    if (selector.charAt(0) === '#') {
      var id = selector.slice(1);
      if (!id) {
        return null;
      }
      var byId = document.getElementById(id);
      if (!byId) {
        return null;
      }
      return scope && scope !== document && !scope.contains(byId) ? null : byId;
    }

    try {
      return (scope || document).querySelector(selector);
    } catch (error) {
      return null;
    }
  }

  function initSmoothScroll() {
    document.querySelectorAll('[data-g-scroll-target]').forEach(function (element) {
      element.addEventListener('click', function (event) {
        var selector = element.getAttribute('data-g-scroll-target');
        var scope = element.closest('.gp-page') || document;
        var target = resolveScrollTarget(scope, selector);

        if (!target) {
          return;
        }

        if (element.tagName === 'A') {
          event.preventDefault();
        }

        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  }

  // ─── Scroll Progress Bar ───────────────────────────────────────────────────

  function initPageProgress() {
    var pages = Array.prototype.slice.call(document.querySelectorAll('.gp-page'));
    if (!pages.length) {
      return;
    }

    function sync() {
      var doc = document.documentElement;
      var maxScroll = Math.max(1, doc.scrollHeight - window.innerHeight);
      var progress = Math.min(100, Math.max(0, (window.scrollY / maxScroll) * 100));

      pages.forEach(function (page) {
        var bar = page.querySelector('.gp-page__scroll-progress-bar');
        if (bar) {
          bar.style.width = progress + '%';
        }
      });
    }

    sync();
    window.addEventListener('scroll', sync, { passive: true });
    window.addEventListener('resize', sync);
  }

  // ─── Active Sidebar Section ────────────────────────────────────────────────

  function initActiveSectionState() {
    var pages = Array.prototype.slice.call(document.querySelectorAll('.gp-page'));
    if (!pages.length) {
      return;
    }

    pages.forEach(function (page) {
      var links = Array.prototype.slice.call(
        page.querySelectorAll('.g-folio__theme-page-catalog-link, .g-folio__theme-page-mobile-nav-item-link')
      );
      if (!links.length) {
        return;
      }

      var anchors = links
        .map(function (link) {
          var selector = link.getAttribute('data-g-scroll-target');
          var target = resolveScrollTarget(page, selector);
          if (!selector || !target) {
            return null;
          }
          return { link: link, selector: selector, target: target };
        })
        .filter(Boolean);

      if (!anchors.length) {
        return;
      }

      function syncLinkState(link, isActive) {
        link.classList.toggle('g-folio__theme-page-catalog-link--active', isActive);
        link.classList.toggle('g-folio__theme-page-mobile-nav-item-link--active', isActive);
        if (isActive) {
          link.setAttribute('aria-current', 'location');
        } else {
          link.removeAttribute('aria-current');
        }
      }

      function syncCurrentSection() {
        var activeSelector = anchors[0].selector;

        anchors.forEach(function (anchor) {
          var rect = anchor.target.getBoundingClientRect();
          if (rect.top <= Math.max(150, window.innerHeight * 0.22)) {
            activeSelector = anchor.selector;
          }
        });

        anchors.forEach(function (anchor) {
          syncLinkState(anchor.link, anchor.selector === activeSelector);
        });
      }

      syncCurrentSection();
      window.addEventListener('scroll', syncCurrentSection, { passive: true });
      window.addEventListener('resize', syncCurrentSection);
    });
  }

  // ─── Mobile In-Page Nav ────────────────────────────────────────────────────

  function initInPageNavPanel() {
    document.querySelectorAll('.gp-page').forEach(function (page) {
      var panel = page.querySelector('.g-folio__theme-page-mobile-nav');
      if (!panel) {
        return;
      }

      panel.querySelectorAll('.g-folio__theme-page-mobile-nav-item-link').forEach(function (link) {
        link.addEventListener('click', function () {
          panel.classList.remove('visible');
        });
      });

      var backButton = panel.querySelector('.g-folio__theme-page-mobile-nav-back');
      if (backButton) {
        backButton.addEventListener('click', function () {
          panel.classList.remove('visible');
        });
      }
    });
  }

  // ─── Hero Image Contrast Detection ────────────────────────────────────────

  function initHeroContrast() {
    var heroes = document.querySelectorAll('[data-gp-hero-contrast]');
    if (!heroes.length) {
      return;
    }

    heroes.forEach(function (hero) {
      var img = hero.querySelector('.gp-cover__hero-image');
      if (!img) {
        return;
      }

      function analyzeImage() {
        try {
          var canvas = document.createElement('canvas');
          var ctx = canvas.getContext('2d');
          if (!ctx) {
            return;
          }

          // Sample a small version for performance
          var sampleW = Math.min(img.naturalWidth, 64);
          var sampleH = Math.min(img.naturalHeight, 64);
          canvas.width = sampleW;
          canvas.height = sampleH;
          ctx.drawImage(img, 0, 0, sampleW, sampleH);

          // Sample the bottom-right quarter where the credit text sits
          var startX = Math.floor(sampleW * 0.5);
          var startY = Math.floor(sampleH * 0.5);
          var regionW = sampleW - startX;
          var regionH = sampleH - startY;
          var data = ctx.getImageData(startX, startY, regionW, regionH).data;

          // Calculate relative luminance (ITU-R BT.709)
          var totalLuminance = 0;
          var pixelCount = data.length / 4;
          for (var i = 0; i < data.length; i += 4) {
            totalLuminance += 0.2126 * data[i] + 0.7152 * data[i + 1] + 0.0722 * data[i + 2];
          }

          var avgLuminance = totalLuminance / pixelCount;
          // Threshold ~140/255: if the image area is light, use dark text
          if (avgLuminance > 140) {
            hero.classList.add('gp-cover__hero--light-image');
          } else {
            hero.classList.remove('gp-cover__hero--light-image');
          }
        } catch (error) {
          // Canvas tainted or CORS issue — keep default (light text on assumed dark)
        }
      }

      if (img.complete && img.naturalWidth > 0) {
        analyzeImage();
      } else {
        img.addEventListener('load', analyzeImage);
      }
    });
  }

  // ─── Init ──────────────────────────────────────────────────────────────────

  function init() {
    if (!document.querySelector('.gp-cover, .gp-page')) {
      return;
    }

    initThemeToggle();
    initDynamicAccentPalette();
    initNavPanelBackdrop();
    initNavDetailsPanel();
    initSmoothScroll();
    initPageProgress();
    initActiveSectionState();
    initInPageNavPanel();
    initHeroContrast();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
