/**
 * Groove Magazine — Adaptive Color System
 *
 * Extracts the dominant color from each story's feature image and generates
 * an accessible, contrast-safe palette using the Leonardo algorithm.
 *
 * The generated palette is applied via CSS custom properties so decorative
 * elements (hero overlay, accent underlines, blockquote borders, nav highlights)
 * adapt to the image colour while the main content stays on a consistent
 * light background for readability.
 *
 * Works without any external dependencies — the contrast calculation is
 * inlined (based on Adobe Leonardo's approach) so there is nothing to npm-
 * install in the WordPress context.
 */
(function () {
    'use strict';

    // ── Utility: Relative luminance (WCAG 2.1) ───────────────────────────
    function sRGBtoLinear(c) {
        c = c / 255;
        return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    }

    function luminance(r, g, b) {
        return 0.2126 * sRGBtoLinear(r) + 0.7152 * sRGBtoLinear(g) + 0.0722 * sRGBtoLinear(b);
    }

    // ── Utility: Contrast ratio ───────────────────────────────────────────
    function contrastRatio(l1, l2) {
        var lighter = Math.max(l1, l2);
        var darker = Math.min(l1, l2);
        return (lighter + 0.05) / (darker + 0.05);
    }

    // ── Utility: HSL ↔ RGB ─────────────────────────────────────────────────
    function rgbToHsl(r, g, b) {
        r /= 255; g /= 255; b /= 255;
        var max = Math.max(r, g, b), min = Math.min(r, g, b);
        var h, s, l = (max + min) / 2;

        if (max === min) {
            h = s = 0;
        } else {
            var d = max - min;
            s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
            switch (max) {
                case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
                case g: h = ((b - r) / d + 2) / 6; break;
                case b: h = ((r - g) / d + 4) / 6; break;
            }
        }
        return [h * 360, s * 100, l * 100];
    }

    function hslToRgb(h, s, l) {
        h /= 360; s /= 100; l /= 100;
        var r, g, b;
        if (s === 0) {
            r = g = b = l;
        } else {
            function hue2rgb(p, q, t) {
                if (t < 0) t += 1;
                if (t > 1) t -= 1;
                if (t < 1 / 6) return p + (q - p) * 6 * t;
                if (t < 1 / 2) return q;
                if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
                return p;
            }
            var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
            var p = 2 * l - q;
            r = hue2rgb(p, q, h + 1 / 3);
            g = hue2rgb(p, q, h);
            b = hue2rgb(p, q, h - 1 / 3);
        }
        return [Math.round(r * 255), Math.round(g * 255), Math.round(b * 255)];
    }

    function rgbToHex(r, g, b) {
        return '#' + [r, g, b].map(function (c) {
            var hex = c.toString(16);
            return hex.length === 1 ? '0' + hex : hex;
        }).join('');
    }

    // ── Leonardo-inspired: adjust lightness to meet target contrast ────────
    function findLightnessForContrast(hue, sat, bgLuminance, targetRatio, preferLight) {
        var lo = preferLight ? 50 : 0;
        var hi = preferLight ? 100 : 50;
        var bestL = preferLight ? 90 : 30;

        for (var i = 0; i < 30; i++) {
            var mid = (lo + hi) / 2;
            var rgb = hslToRgb(hue, sat, mid);
            var lum = luminance(rgb[0], rgb[1], rgb[2]);
            var ratio = contrastRatio(lum, bgLuminance);

            if (ratio < targetRatio) {
                if (preferLight) { hi = mid; } else { lo = mid; }
            } else {
                bestL = mid;
                if (preferLight) { lo = mid; } else { hi = mid; }
            }
        }

        return bestL;
    }

    // Finds the closest tone to the background that still meets target contrast.
    function findClosestLightnessMeetingContrast(hue, sat, bgLuminance, targetRatio, preferLight, minLightness, maxLightness) {
        var lo = minLightness;
        var hi = maxLightness;
        var bestL = preferLight ? maxLightness : minLightness;

        for (var i = 0; i < 30; i++) {
            var mid = (lo + hi) / 2;
            var rgb = hslToRgb(hue, sat, mid);
            var lum = luminance(rgb[0], rgb[1], rgb[2]);
            var ratio = contrastRatio(lum, bgLuminance);

            if (ratio >= targetRatio) {
                bestL = mid;
                if (preferLight) { hi = mid; } else { lo = mid; }
            } else {
                if (preferLight) { lo = mid; } else { hi = mid; }
            }
        }

        return bestL;
    }

    // ── Generate palette from a single dominant color ──────────────────────
    function generatePalette(dominantR, dominantG, dominantB, isDarkMode) {
        var hsl = rgbToHsl(dominantR, dominantG, dominantB);
        var hue = hsl[0];
        var sat = Math.min(hsl[1], 70); // cap saturation for elegance

        // Background luminance
        var bgLum = isDarkMode ? luminance(15, 23, 42) : luminance(250, 250, 250);

        // Accent: high enough contrast for text (ratio ≥ 4.5)
        var accentL = findLightnessForContrast(hue, sat, bgLum, 4.5, isDarkMode);
        var accentRgb = hslToRgb(hue, sat, accentL);

        // Link default: lighter extracted hue, but still readable at ≥ 4.5.
        var linkDefaultSat = Math.max(Math.min(sat * 0.8, 62), 18);
        var linkDefaultL = findClosestLightnessMeetingContrast(
            hue,
            linkDefaultSat,
            bgLum,
            4.5,
            isDarkMode,
            isDarkMode ? 45 : 14,
            isDarkMode ? 96 : 72
        );
        var linkDefaultRgb = hslToRgb(hue, linkDefaultSat, linkDefaultL);

        // Muted: lower contrast for decorative use (ratio ≥ 2)
        var mutedSat = Math.max(sat * 0.5, 15);
        var mutedL = findLightnessForContrast(hue, mutedSat, bgLum, 2.0, isDarkMode);
        var mutedRgb = hslToRgb(hue, mutedSat, mutedL);

        // Subtle tint: very light for backgrounds (lightness 93-97)
        var subtleL = isDarkMode ? 12 : 95;
        var subtleRgb = hslToRgb(hue, Math.max(sat * 0.35, 10), subtleL);

        // Progress accent: extracted hue with guaranteed 2.0 contrast on the track.
        var progressTrackLum = isDarkMode ? luminance(51, 65, 85) : luminance(226, 232, 240); // --gm-border
        var progressSat = Math.max(Math.min(sat * 0.6, 55), 16);
        var progressL = findClosestLightnessMeetingContrast(
            hue,
            progressSat,
            progressTrackLum,
            2.0,
            isDarkMode,
            isDarkMode ? 40 : 10,
            isDarkMode ? 96 : 65
        );
        var progressRgb = hslToRgb(hue, progressSat, progressL);

        // Footer hover tint: extracted hue, intentionally subtle near page surface.
        var surfaceLum = isDarkMode ? luminance(15, 23, 42) : luminance(250, 250, 250); // --gm-surface
        var footerHoverSat = Math.max(Math.min(sat * 0.35, 28), 8);
        var footerHoverL = findClosestLightnessMeetingContrast(
            hue,
            footerHoverSat,
            surfaceLum,
            1.05,
            isDarkMode,
            isDarkMode ? 10 : 84,
            isDarkMode ? 34 : 99
        );
        var footerHoverRgb = hslToRgb(hue, footerHoverSat, footerHoverL);

        // Hero overlay: dark version of the dominant hue
        var overlayRgb = hslToRgb(hue, Math.min(sat, 40), 15);

        return {
            accent: rgbToHex(accentRgb[0], accentRgb[1], accentRgb[2]),
            accentLink: rgbToHex(linkDefaultRgb[0], linkDefaultRgb[1], linkDefaultRgb[2]),
            accentMuted: rgbToHex(mutedRgb[0], mutedRgb[1], mutedRgb[2]),
            accentSubtle: rgbToHex(subtleRgb[0], subtleRgb[1], subtleRgb[2]),
            accentProgress: rgbToHex(progressRgb[0], progressRgb[1], progressRgb[2]),
            accentFooterHover: rgbToHex(footerHoverRgb[0], footerHoverRgb[1], footerHoverRgb[2]),
            heroOverlay: 'rgba(' + overlayRgb[0] + ',' + overlayRgb[1] + ',' + overlayRgb[2] + ', 0.55)'
        };
    }

    var currentDominantColor = null;

    function applyCurrentColor() {
        if (!currentDominantColor) return;
        var isDarkMode = document.documentElement.classList.contains('gm-theme-dark') ||
            (!document.documentElement.classList.contains('gm-theme-light') && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);

        var palette = generatePalette(currentDominantColor.r, currentDominantColor.g, currentDominantColor.b, isDarkMode);
        applyPalette(palette);
    }

    // ── Extract dominant colour from an image via canvas ───────────────────
    function extractDominantColor(imgUrl, callback) {
        // First, try to use an existing <img> already rendered on the page
        // (avoids re-fetching and any CORS issues entirely).
        var existingImg = document.querySelector('.gm-page__hero-bg, .gm-cover__hero-bg');
        if (existingImg && existingImg.tagName === 'IMG') {
            // Won't happen — our heroes use background-image divs. Fall through.
        }

        var img = new Image();

        // Only set crossOrigin for external URLs to avoid tainted-canvas issues.
        // WordPress media is served from the same origin, so no CORS header is
        // needed — and adding the attribute actually *breaks* same-origin loads
        // on servers that don't echo back CORS headers.
        try {
            var pageOrigin = window.location.origin;
            if (imgUrl.indexOf(pageOrigin) !== 0 && imgUrl.indexOf('/') !== 0) {
                img.crossOrigin = 'anonymous';
            }
        } catch (e) {
            // If origin comparison fails, skip crossOrigin entirely.
        }

        img.onload = function () {
            try {
                var canvas = document.createElement('canvas');
                var size = 64;
                canvas.width = size;
                canvas.height = size;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, size, size);

                var data = ctx.getImageData(0, 0, size, size).data;
                var rTotal = 0, gTotal = 0, bTotal = 0, count = 0;

                for (var i = 0; i < data.length; i += 4) {
                    var r = data[i], g = data[i + 1], b = data[i + 2], a = data[i + 3];
                    if (a < 128) continue;

                    var max = Math.max(r, g, b), min = Math.min(r, g, b);
                    var saturation = max === 0 ? 0 : (max - min) / max;

                    var weight = 1 + saturation * 3;
                    rTotal += r * weight;
                    gTotal += g * weight;
                    bTotal += b * weight;
                    count += weight;
                }

                if (count === 0) {
                    callback(null);
                    return;
                }

                callback({
                    r: Math.round(rTotal / count),
                    g: Math.round(gTotal / count),
                    b: Math.round(bTotal / count)
                });
            } catch (err) {
                // Canvas security error (tainted) — fall back silently
                console.warn('Groove Magazine: Could not extract colors from image.', err.message);
                callback(null);
            }
        };

        img.onerror = function () {
            console.warn('Groove Magazine: Image failed to load for color extraction:', imgUrl);
            callback(null);
        };

        img.src = imgUrl;
    }

    // ── Apply palette to CSS custom properties ─────────────────────────────
    function applyPalette(palette) {
        var root = document.documentElement.style;
        root.setProperty('--gm-accent', palette.accent);
        root.setProperty('--gm-accent-link', palette.accentLink);
        root.setProperty('--gm-accent-muted', palette.accentMuted);
        root.setProperty('--gm-accent-subtle', palette.accentSubtle);
        root.setProperty('--gm-accent-progress', palette.accentProgress);
        root.setProperty('--gm-accent-footer-hover', palette.accentFooterHover);
        root.setProperty('--gm-hero-overlay', palette.heroOverlay);
    }

    function setupNavItemHoverPalette() {
        var navLinks = document.querySelectorAll('.gm-nav .gm-nav__item-link[data-gm-nav-image]');
        if (!navLinks.length) return;

        var colorCache = {};
        var hoverToken = 0;
        var hoverProps = [
            '--gm-nav-hover-accent',
            '--gm-nav-hover-muted',
            '--gm-nav-hover-subtle'
        ];

        function isDarkMode() {
            return document.documentElement.classList.contains('gm-theme-dark') ||
                (!document.documentElement.classList.contains('gm-theme-light') && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        }

        function applyHoverPalette(link, color) {
            if (!link || !color) return;
            var palette = generatePalette(color.r, color.g, color.b, isDarkMode());
            link.style.setProperty('--gm-nav-hover-accent', palette.accent);
            link.style.setProperty('--gm-nav-hover-muted', palette.accentMuted);
            link.style.setProperty('--gm-nav-hover-subtle', palette.accentSubtle);
        }

        function clearHoverPalette(link) {
            if (!link) return;
            for (var i = 0; i < hoverProps.length; i++) {
                link.style.removeProperty(hoverProps[i]);
            }
        }

        function isLinkInteractive(link) {
            return !!link && (link.matches(':hover') || link === document.activeElement || link.contains(document.activeElement));
        }

        function handleEnter(link) {
            var imgUrl = link.getAttribute('data-gm-nav-image');
            if (!imgUrl) return;

            hoverToken += 1;
            var token = hoverToken;

            if (colorCache[imgUrl]) {
                applyHoverPalette(link, colorCache[imgUrl]);
                return;
            }

            extractDominantColor(imgUrl, function (color) {
                if (!color || token !== hoverToken) return;
                colorCache[imgUrl] = color;
                if (isLinkInteractive(link)) {
                    applyHoverPalette(link, color);
                }
            });
        }

        function handleExit(link) {
            hoverToken += 1;
            clearHoverPalette(link);
        }

        for (var i = 0; i < navLinks.length; i++) {
            (function (link) {
                link.addEventListener('mouseenter', function () {
                    handleEnter(link);
                });
                link.addEventListener('focus', function () {
                    handleEnter(link);
                });
                link.addEventListener('mouseleave', function () {
                    handleExit(link);
                });
                link.addEventListener('blur', function () {
                    handleExit(link);
                });
            })(navLinks[i]);
        }
    }

    // ── Initialise ─────────────────────────────────────────────────────────
    function init() {
        // Always set up navigation — it must work even without a hero image
        setupNavToggle();
        setupMobileNav();
        setupBackToTop();
        setupSmoothScroll();
        setupScrollProgress();
        setupActiveCatalogIndicator();
        setupSlideshow();
        setupThemeToggle();
        setupNavItemHoverPalette();

        // Now try adaptive color extraction
        var heroEl = document.querySelector('[data-gm-feature-image]');
        if (!heroEl) {
            heroEl = document.querySelector('[data-gm-hero-image]');
        }

        if (!heroEl) return;

        var imageUrl = heroEl.getAttribute('data-gm-feature-image') ||
            heroEl.getAttribute('data-gm-hero-image');
        if (!imageUrl) return;

        extractDominantColor(imageUrl, function (color) {
            if (!color) return;
            currentDominantColor = color;
            applyCurrentColor();
        });
    }

    // ── Cover slideshow ──────────────────────────────────────────────────
    function setupSlideshow() {
        var slideshow = document.querySelector('.gm-cover__slideshow');
        if (!slideshow) return;

        var slides = slideshow.querySelectorAll('.gm-cover__slide');
        if (slides.length <= 1) return; // No rotation needed for a single slide

        var currentIndex = 0;
        var intervalMs = 5000;
        var timer = null;
        var isPaused = false;

        function showSlide(index) {
            for (var i = 0; i < slides.length; i++) {
                slides[i].classList.remove('gm-cover__slide--active');
            }
            slides[index].classList.add('gm-cover__slide--active');
            currentIndex = index;

            // Also update color extraction for the new image
            var imgUrl = slides[index].style.backgroundImage;
            var match = imgUrl.match(/url\(["']?([^"')]+)["']?\)/);
            if (match && match[1]) {
                extractDominantColor(match[1], function (color) {
                    if (!color) return;
                    currentDominantColor = color;
                    applyCurrentColor();
                });
            }
        }

        function nextSlide() {
            var next = (currentIndex + 1) % slides.length;
            showSlide(next);
        }

        function startAutoplay() {
            stopAutoplay();
            timer = setInterval(function () {
                if (!isPaused) nextSlide();
            }, intervalMs);
        }

        function stopAutoplay() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        }

        // Start auto-rotation
        startAutoplay();

        // ── Hover-to-show: story links control the slideshow ────────────
        var storyLinks = document.querySelectorAll('[data-gm-story-image]');
        var hoverSlide = null; // A dynamically-created slide for hover images

        for (var i = 0; i < storyLinks.length; i++) {
            (function (link) {
                link.addEventListener('mouseenter', function () {
                    var imgUrl = link.getAttribute('data-gm-story-image');
                    if (!imgUrl) return;

                    isPaused = true;

                    // Check if any existing slide already has this image
                    var found = false;
                    for (var j = 0; j < slides.length; j++) {
                        var bg = slides[j].style.backgroundImage || '';
                        if (bg.indexOf(imgUrl) > -1) {
                            showSlide(j);
                            found = true;
                            break;
                        }
                    }

                    if (!found) {
                        // Create (or reuse) a temporary hover slide
                        if (!hoverSlide) {
                            hoverSlide = document.createElement('div');
                            hoverSlide.className = 'gm-cover__slide';
                            slideshow.appendChild(hoverSlide);
                        }
                        hoverSlide.style.backgroundImage = 'url(' + imgUrl + ')';

                        // Deactivate all other slides
                        for (var k = 0; k < slides.length; k++) {
                            slides[k].classList.remove('gm-cover__slide--active');
                        }
                        hoverSlide.classList.add('gm-cover__slide--active');

                        // Run color extraction on the hovered image
                        extractDominantColor(imgUrl, function (color) {
                            if (!color) return;
                            currentDominantColor = color;
                            applyCurrentColor();
                        });
                    }
                });

                link.addEventListener('mouseleave', function () {
                    isPaused = false;

                    // Remove hover slide and restore the current rotation slide
                    if (hoverSlide) {
                        hoverSlide.classList.remove('gm-cover__slide--active');
                    }
                    showSlide(currentIndex);
                });
            })(storyLinks[i]);
        }
    }

    // ── Navigation interactions ────────────────────────────────────────────
    function setupNavToggle() {
        var nav = document.querySelector('.gm-nav');
        if (!nav) return;

        // Open buttons (may be on cover or page)
        var openers = document.querySelectorAll('.gm-cover__nav-toggle, .gm-page__nav-toggle');
        for (var i = 0; i < openers.length; i++) {
            openers[i].addEventListener('click', function (e) {
                e.stopPropagation();
                nav.classList.add('visible');
            });
        }

        // Close button
        var closer = nav.querySelector('.gm-nav__close');
        if (closer) {
            closer.addEventListener('click', function (e) {
                e.stopPropagation();
                nav.classList.remove('visible');
            });
        }

        // Close on outside click
        document.addEventListener('click', function (e) {
            if (!nav.classList.contains('visible')) return;
            if (nav.contains(e.target)) return;

            var isOpener = false;
            for (var j = 0; j < openers.length; j++) {
                if (openers[j].contains(e.target)) { isOpener = true; break; }
            }
            if (!isOpener) {
                nav.classList.remove('visible');
            }
        });

        // Close on Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && nav.classList.contains('visible')) {
                nav.classList.remove('visible');
            }
        });
    }

    function setupMobileNav() {
        var toggle = document.querySelector('.gm-page__navbar-progress-label');
        var mobileNav = document.querySelector('.gm-page__mobile-nav');
        if (!toggle || !mobileNav) return;

        toggle.style.cursor = 'pointer';
        toggle.addEventListener('click', function () {
            mobileNav.classList.toggle('visible');
        });

        // Close when clicking a link inside
        var links = mobileNav.querySelectorAll('a');
        for (var i = 0; i < links.length; i++) {
            links[i].addEventListener('click', function () {
                mobileNav.classList.remove('visible');
            });
        }
    }

    function setupBackToTop() {
        var btn = document.querySelector('.gm-page__mobile-nav-top');
        if (!btn) return;
        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            var mobileNav = document.querySelector('.gm-page__mobile-nav');
            if (mobileNav) mobileNav.classList.remove('visible');
        });
    }

    function setupScrollProgress() {
        var bar = document.querySelector('.gm-page__navbar-progress-bar');
        if (!bar) return;

        var ticking = false;

        function updateProgress() {
            var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            var docHeight = document.documentElement.scrollHeight - window.innerHeight;
            var percent = docHeight > 0 ? Math.min((scrollTop / docHeight) * 100, 100) : 0;
            bar.style.width = percent + '%';
            ticking = false;
        }

        window.addEventListener('scroll', function () {
            if (!ticking) {
                requestAnimationFrame(updateProgress);
                ticking = true;
            }
        }, { passive: true });

        // Set initial state
        updateProgress();
    }

    function setupActiveCatalogIndicator() {
        var links = document.querySelectorAll('.gm-page__catalog a[href^="#"]');
        if (!links.length) return;

        var items = [];
        for (var i = 0; i < links.length; i++) {
            var href = links[i].getAttribute('href');
            if (!href || href.length < 2) continue;

            var id = href.slice(1);
            try {
                id = decodeURIComponent(id);
            } catch (e) {
                // Keep raw id when decoding fails.
            }

            var target = document.getElementById(id);
            if (!target) continue;
            items.push({ link: links[i], target: target });
        }

        if (!items.length) return;

        var activeClass = 'gm-page__catalog-link--active';
        var activeLink = null;
        var ticking = false;

        function setActive(link) {
            if (!link || activeLink === link) return;
            if (activeLink) {
                activeLink.classList.remove(activeClass);
            }
            activeLink = link;
            activeLink.classList.add(activeClass);
        }

        function updateActive() {
            var activationOffset = 120;
            var current = null;

            for (var i = 0; i < items.length; i++) {
                var top = items[i].target.getBoundingClientRect().top - activationOffset;
                if (top <= 0) {
                    current = items[i].link;
                } else {
                    break;
                }
            }

            if (!current) {
                current = items[0].link;
            }

            setActive(current);
            ticking = false;
        }

        function requestUpdate() {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(updateActive);
        }

        window.addEventListener('scroll', requestUpdate, { passive: true });
        window.addEventListener('resize', requestUpdate);
        window.addEventListener('hashchange', requestUpdate);

        for (var j = 0; j < items.length; j++) {
            items[j].link.addEventListener('click', requestUpdate);
        }

        requestUpdate();
    }

    function setupSmoothScroll() {
        // Make all in-page anchor links scroll smoothly
        var anchors = document.querySelectorAll('a[href^="#"]');
        for (var i = 0; i < anchors.length; i++) {
            anchors[i].addEventListener('click', function (e) {
                var href = this.getAttribute('href');
                if (!href || href === '#') return;
                var target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        }
    }

    function setupThemeToggle() {
        var toggles = document.querySelectorAll('.gm-theme-toggle');
        if (!toggles.length) return;

        var storedTheme = localStorage.getItem('gm-theme');
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var currentTheme = storedTheme || (prefersDark ? 'dark' : 'light');

        function applyThemeMode(theme) {
            if (theme === 'dark') {
                document.documentElement.classList.add('gm-theme-dark');
                document.documentElement.classList.remove('gm-theme-light');
            } else {
                document.documentElement.classList.add('gm-theme-light');
                document.documentElement.classList.remove('gm-theme-dark');
            }
            updateIcons(theme);
            applyCurrentColor(); // Update generated palette for the new theme
        }

        function updateIcons(theme) {
            toggles.forEach(function (btn) {
                if (theme === 'dark') {
                    // Sun icon for switching to light mode
                    btn.innerHTML = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
                    btn.setAttribute('aria-label', 'Switch to light mode');
                    btn.setAttribute('data-tooltip', 'Switch to light mode');
                } else {
                    // Moon icon for switching to dark mode
                    btn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
                    btn.setAttribute('aria-label', 'Switch to dark mode');
                    btn.setAttribute('data-tooltip', 'Switch to dark mode');
                }
            });
        }

        applyThemeMode(currentTheme);

        toggles.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var isDark = document.documentElement.classList.contains('gm-theme-dark');
                var htmlHasNoClass = !document.documentElement.classList.contains('gm-theme-dark') && !document.documentElement.classList.contains('gm-theme-light');
                if (htmlHasNoClass) {
                    isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                }
                var newTheme = isDark ? 'light' : 'dark';

                localStorage.setItem('gm-theme', newTheme);
                applyThemeMode(newTheme);
            });
        });

        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
                if (!localStorage.getItem('gm-theme')) {
                    applyThemeMode(e.matches ? 'dark' : 'light');
                }
            });
        }
    }

    // ── Run ────────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
