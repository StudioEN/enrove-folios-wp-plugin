(function (wp) {
  if (!wp || !wp.plugins || !wp.element || !wp.data) {
    return;
  }

  const { registerPlugin } = wp.plugins;
  const { useEffect } = wp.element;
  const { useSelect, dispatch, select } = wp.data;
  const FALLBACK_SEPARATOR_COLOR = '#1D4ED8';

  function getFolioName() {
    return window.ENROVE_FOLIO_NAME || (window.ENROVE_POST_TYPE === 'edit' ? 'Edit Folio' : 'New Folio');
  }

  function getFolioUrl() {
    if (window.ENROVE_FOLIO_SETUP_URL) {
      return window.ENROVE_FOLIO_SETUP_URL;
    }

    return '#';
  }

  function ensureNativeAdminUiVisible() {
    const editPostStore = select('core/edit-post');
    if (!editPostStore || typeof editPostStore.isFeatureActive !== 'function') {
      return;
    }

    if (editPostStore.isFeatureActive('fullscreenMode')) {
      const editPostDispatch = dispatch('core/edit-post');
      if (editPostDispatch && typeof editPostDispatch.toggleFeature === 'function') {
        editPostDispatch.toggleFeature('fullscreenMode');
      }
    }
  }

  function upsertBreadcrumb() {
    const mountPoint = document.querySelector('.interface-interface-skeleton__header')
      || document.querySelector('#wpbody-content');
    if (!mountPoint) {
      return;
    }

    let nav = document.querySelector('.g-top-bar-post-nav');
    if (!nav) {
      nav = document.createElement('nav');
      nav.className = 'g-top-bar-post-nav';
      nav.innerHTML =
        '<div class="g-top-bar-post-type">' +
        '<a class="g-top-bar-crumb g-top-bar-crumb-parent"></a>' +
        '<i class="g-top-bar-crumb-arrow"> /</i>' +
        '<a class="g-top-bar-crumb">Page</a>' +
        '</div>';
    }

    if (nav.parentNode !== mountPoint) {
      mountPoint.prepend(nav);
    }

    const parentLink = nav.querySelector('.g-top-bar-crumb-parent');
    if (parentLink) {
      parentLink.textContent = getFolioName();
      parentLink.setAttribute('href', getFolioUrl());
    }

    if (document.body && document.body.classList.contains('block-editor-page')) {
      document.documentElement.style.setProperty(
        '--g-editor-breadcrumb-height',
        Math.ceil(nav.getBoundingClientRect().height) + 'px'
      );
    }

    return nav;
  }

  function sRGBtoLinear(channel) {
    const c = channel / 255;
    return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
  }

  function luminance(r, g, b) {
    return 0.2126 * sRGBtoLinear(r) + 0.7152 * sRGBtoLinear(g) + 0.0722 * sRGBtoLinear(b);
  }

  function contrastRatio(l1, l2) {
    const lighter = Math.max(l1, l2);
    const darker = Math.min(l1, l2);
    return (lighter + 0.05) / (darker + 0.05);
  }

  function rgbToHsl(r, g, b) {
    r /= 255;
    g /= 255;
    b /= 255;

    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    let h = 0;
    let s = 0;
    const l = (max + min) / 2;

    if (max !== min) {
      const d = max - min;
      s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
      switch (max) {
        case r:
          h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
          break;
        case g:
          h = ((b - r) / d + 2) / 6;
          break;
        case b:
          h = ((r - g) / d + 4) / 6;
          break;
        default:
          h = 0;
      }
    }

    return [h * 360, s * 100, l * 100];
  }

  function hslToRgb(h, s, l) {
    h /= 360;
    s /= 100;
    l /= 100;

    let r;
    let g;
    let b;

    if (s === 0) {
      r = l;
      g = l;
      b = l;
    } else {
      function hue2rgb(p, q, t) {
        if (t < 0) t += 1;
        if (t > 1) t -= 1;
        if (t < 1 / 6) return p + (q - p) * 6 * t;
        if (t < 1 / 2) return q;
        if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
        return p;
      }

      const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
      const p = 2 * l - q;
      r = hue2rgb(p, q, h + 1 / 3);
      g = hue2rgb(p, q, h);
      b = hue2rgb(p, q, h - 1 / 3);
    }

    return [Math.round(r * 255), Math.round(g * 255), Math.round(b * 255)];
  }

  function rgbToHex(r, g, b) {
    return '#' + [r, g, b].map(function (channel) {
      const hex = channel.toString(16);
      return hex.length === 1 ? '0' + hex : hex;
    }).join('');
  }

  function findClosestLightnessMeetingContrast(hue, sat, bgLuminance, targetRatio, preferLight, minLightness, maxLightness) {
    let lo = minLightness;
    let hi = maxLightness;
    let bestL = preferLight ? maxLightness : minLightness;

    for (let i = 0; i < 30; i++) {
      const mid = (lo + hi) / 2;
      const rgb = hslToRgb(hue, sat, mid);
      const lum = luminance(rgb[0], rgb[1], rgb[2]);
      const ratio = contrastRatio(lum, bgLuminance);

      if (ratio >= targetRatio) {
        bestL = mid;
        if (preferLight) {
          hi = mid;
        } else {
          lo = mid;
        }
      } else if (preferLight) {
        lo = mid;
      } else {
        hi = mid;
      }
    }

    return bestL;
  }

  function generateProgressColor(dominantR, dominantG, dominantB, isDarkMode) {
    const hsl = rgbToHsl(dominantR, dominantG, dominantB);
    const hue = hsl[0];
    const sat = Math.min(hsl[1], 70);
    const progressTrackLum = isDarkMode ? luminance(51, 65, 85) : luminance(226, 232, 240);
    const progressSat = Math.max(Math.min(sat * 0.6, 55), 16);

    const progressL = findClosestLightnessMeetingContrast(
      hue,
      progressSat,
      progressTrackLum,
      2.0,
      isDarkMode,
      isDarkMode ? 40 : 10,
      isDarkMode ? 96 : 65
    );

    const progressRgb = hslToRgb(hue, progressSat, progressL);
    return rgbToHex(progressRgb[0], progressRgb[1], progressRgb[2]);
  }

  function extractDominantColor(imgUrl, callback) {
    if (!imgUrl) {
      callback(null);
      return;
    }

    const img = new Image();
    try {
      const pageOrigin = window.location.origin;
      if (imgUrl.indexOf(pageOrigin) !== 0 && imgUrl.indexOf('/') !== 0) {
        img.crossOrigin = 'anonymous';
      }
    } catch (e) {
      // Ignore origin parsing failures and continue.
    }

    img.onload = function () {
      try {
        const canvas = document.createElement('canvas');
        const size = 64;
        canvas.width = size;
        canvas.height = size;
        const context = canvas.getContext('2d');
        context.drawImage(img, 0, 0, size, size);

        const data = context.getImageData(0, 0, size, size).data;
        let rTotal = 0;
        let gTotal = 0;
        let bTotal = 0;
        let count = 0;

        for (let i = 0; i < data.length; i += 4) {
          const r = data[i];
          const g = data[i + 1];
          const b = data[i + 2];
          const a = data[i + 3];
          if (a < 128) {
            continue;
          }

          const max = Math.max(r, g, b);
          const min = Math.min(r, g, b);
          const saturation = max === 0 ? 0 : (max - min) / max;
          const weight = 1 + saturation * 3;

          rTotal += r * weight;
          gTotal += g * weight;
          bTotal += b * weight;
          count += weight;
        }

        if (count <= 0) {
          callback(null);
          return;
        }

        callback({
          r: Math.round(rTotal / count),
          g: Math.round(gTotal / count),
          b: Math.round(bTotal / count)
        });
      } catch (e) {
        callback(null);
      }
    };

    img.onerror = function () {
      callback(null);
    };

    img.src = imgUrl;
  }

  function getEditorMediaById(mediaId) {
    const coreStore = select('core');
    if (!coreStore || !mediaId || mediaId <= 0) {
      return null;
    }

    if (typeof coreStore.getMedia === 'function') {
      return coreStore.getMedia(mediaId);
    }

    if (typeof coreStore.getEntityRecord === 'function') {
      return coreStore.getEntityRecord('root', 'media', mediaId);
    }

    return null;
  }

  function getEditorPaletteSourceUrl() {
    const editorStore = select('core/editor');
    const fallbackUrl = window.ENROVE_EDITOR_THEME_COLOR_SOURCE_URL || '';

    if (!editorStore || typeof editorStore.getEditedPostAttribute !== 'function') {
      return fallbackUrl;
    }

    const featuredMediaId = Number(editorStore.getEditedPostAttribute('featured_media') || 0);
    if (featuredMediaId > 0) {
      const media = getEditorMediaById(featuredMediaId);
      if (media && media.source_url) {
        return media.source_url;
      }
    }

    return fallbackUrl;
  }

  function setSeparatorDefaultColorForNewBlocks(defaultColor) {
    const blockEditorStore = select('core/block-editor');
    const blockEditorDispatch = dispatch('core/block-editor');
    if (
      !blockEditorStore ||
      !blockEditorDispatch ||
      typeof blockEditorStore.getBlocks !== 'function' ||
      typeof blockEditorDispatch.updateBlockAttributes !== 'function'
    ) {
      return;
    }

    const initialized = setSeparatorDefaultColorForNewBlocks._initialized || new Set();
    setSeparatorDefaultColorForNewBlocks._initialized = initialized;

    function visit(blocks) {
      for (let i = 0; i < blocks.length; i++) {
        const block = blocks[i];
        if (!block || !block.clientId) {
          continue;
        }

        if (block.name === 'core/separator') {
          const attrs = block.attributes || {};
          const hasExplicitSlug = typeof attrs.backgroundColor === 'string' && attrs.backgroundColor !== '';
          const currentBackground = attrs.style && attrs.style.color ? attrs.style.color.background : '';
          const currentText = attrs.style && attrs.style.color ? attrs.style.color.text : '';
          const hasExplicitStyle = !!(currentBackground || currentText);

          if (!initialized.has(block.clientId)) {
            initialized.add(block.clientId);

            if (!hasExplicitSlug && !hasExplicitStyle) {
              const nextStyle = Object.assign({}, attrs.style || {});
              nextStyle.color = Object.assign({}, nextStyle.color || {}, {
                background: defaultColor,
                text: defaultColor
              });
              blockEditorDispatch.updateBlockAttributes(block.clientId, { style: nextStyle });
            } else if (!hasExplicitSlug && currentBackground && !currentText) {
              const nextStyle = Object.assign({}, attrs.style || {});
              nextStyle.color = Object.assign({}, nextStyle.color || {}, {
                background: currentBackground,
                text: currentBackground
              });
              blockEditorDispatch.updateBlockAttributes(block.clientId, { style: nextStyle });
            } else if (!hasExplicitSlug && !currentBackground && currentText) {
              const nextStyle = Object.assign({}, attrs.style || {});
              nextStyle.color = Object.assign({}, nextStyle.color || {}, {
                background: currentText,
                text: currentText
              });
              blockEditorDispatch.updateBlockAttributes(block.clientId, { style: nextStyle });
            }
          }
        }

        if (Array.isArray(block.innerBlocks) && block.innerBlocks.length > 0) {
          visit(block.innerBlocks);
        }
      }
    }

    visit(blockEditorStore.getBlocks() || []);
  }

  function initSeparatorDefaultColorSync(postType) {
    if (postType !== 'enrove_folio_page') {
      return null;
    }

    let separatorDefaultColor = FALLBACK_SEPARATOR_COLOR;
    let inFlightSourceUrl = '';
    let lastProcessedSourceUrl = '';

    function syncDefaultColorFromImage() {
      const sourceUrl = getEditorPaletteSourceUrl();
      if (!sourceUrl || sourceUrl === inFlightSourceUrl || sourceUrl === lastProcessedSourceUrl) {
        return;
      }

      inFlightSourceUrl = sourceUrl;
      extractDominantColor(sourceUrl, function (dominantColor) {
        inFlightSourceUrl = '';
        if (!dominantColor) {
          return;
        }

        const isDarkMode = !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        separatorDefaultColor = generateProgressColor(dominantColor.r, dominantColor.g, dominantColor.b, isDarkMode);
        lastProcessedSourceUrl = sourceUrl;
        setSeparatorDefaultColorForNewBlocks(separatorDefaultColor);
      });
    }

    setSeparatorDefaultColorForNewBlocks(separatorDefaultColor);
    syncDefaultColorFromImage();

    return wp.data.subscribe(function () {
      syncDefaultColorFromImage();
      setSeparatorDefaultColorForNewBlocks(separatorDefaultColor);
    });
  }

  const EnroveBreadcrumb = function () {
    if (!window.ENROVE_POST) {
      return null;
    }

    const postType = useSelect(function (wpSelect) {
      const editorStore = wpSelect('core/editor');
      return editorStore ? editorStore.getCurrentPostType() : null;
    }, []);

    useEffect(function () {
      if (postType !== 'enrove_folio_page') {
        return undefined;
      }

      ensureNativeAdminUiVisible();
      const nav = upsertBreadcrumb();

      const observer = new MutationObserver(function () {
        upsertBreadcrumb();
      });
      const unsubscribeSeparatorSync = initSeparatorDefaultColorSync(postType);
      const bodyContent = document.querySelector('#wpbody-content');
      let resizeObserver;
      const handleResize = function () {
        upsertBreadcrumb();
      };

      if (bodyContent) {
        observer.observe(bodyContent, { childList: true });
      }

      if (nav && typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver(handleResize);
        resizeObserver.observe(nav);
      }

      window.addEventListener('resize', handleResize);

      return function () {
        observer.disconnect();
        if (resizeObserver) {
          resizeObserver.disconnect();
        }
        window.removeEventListener('resize', handleResize);
        document.documentElement.style.removeProperty('--g-editor-breadcrumb-height');
        if (typeof unsubscribeSeparatorSync === 'function') {
          unsubscribeSeparatorSync();
        }
      };
    }, [postType]);

    return null;
  };

  registerPlugin('enrove-breadcrumb', {
    render: EnroveBreadcrumb
  });
})(window.wp);
