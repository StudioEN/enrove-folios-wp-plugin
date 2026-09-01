/**
 * Folio Starter — drawer behaviour.
 *
 * groove-main.js owns opening and closing (it toggles `.visible` on the panes).
 * This file does not duplicate that: it observes the class and layers on the
 * parts a bare addClass/removeClass cannot provide — ARIA state, Escape and
 * click-outside dismissal, scroll lock, and focus handling.
 */
(function () {
  'use strict';

  var PANES = [
    { pane: '.g-folio__theme-nav', trigger: '.g-folio__theme-nav-button' },
    { pane: '.g-folio__theme-page-nav', trigger: '.g-folio__theme-page-nav-button' }
  ];

  function ready(fn) {
    if (document.readyState !== 'loading') {
      fn();
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  ready(function () {
    var root = document.querySelector('.g-folio__theme-1, .g-folio__theme-1-page');
    if (!root) {
      return;
    }

    var entries = PANES.map(function (config) {
      return {
        config: config,
        pane: document.querySelector(config.pane),
        trigger: document.querySelector(config.trigger),
        lastFocused: null
      };
    }).filter(function (entry) {
      return entry.pane;
    });

    if (!entries.length) {
      return;
    }

    function isOpen(entry) {
      return entry.pane.classList.contains('visible');
    }

    function close(entry) {
      entry.pane.classList.remove('visible');
    }

    function syncScrollLock() {
      document.body.style.overflow = entries.some(isOpen) ? 'hidden' : '';
    }

    function focusWhenReady(pane, target) {
      var timer = null;

      var cleanup = function () {
        pane.removeEventListener('transitionend', attempt);
        clearTimeout(timer);
      };

      var attempt = function () {
        cleanup();
        // Closed again before it finished opening — nothing to focus.
        if (!pane.classList.contains('visible')) {
          return;
        }
        target.focus();
      };

      pane.addEventListener('transitionend', attempt);
      timer = setTimeout(attempt, 350);
    }

    function onToggle(entry) {
      var open = isOpen(entry);

      if (entry.trigger) {
        entry.trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
      }

      syncScrollLock();

      if (open) {
        entry.lastFocused = document.activeElement;
        // Prefer the close button so Escape and Tab both start somewhere sane.
        var first = entry.pane.querySelector(
          '.g-folio__theme-nav-close, .g-folio__theme-page-nav-close, a, button'
        );
        if (first) {
          // The pane is visibility:hidden until it has slid in, and an element
          // that computes to hidden silently refuses focus. Wait for the slide
          // to finish rather than guessing at a delay; the timer is a fallback
          // for when the transition is suppressed (reduced motion, no support).
          focusWhenReady(entry.pane, first);
        }
      } else if (entry.lastFocused && typeof entry.lastFocused.focus === 'function') {
        entry.lastFocused.focus();
        entry.lastFocused = null;
      }
    }

    entries.forEach(function (entry) {
      new MutationObserver(function () {
        onToggle(entry);
      }).observe(entry.pane, { attributes: true, attributeFilter: ['class'] });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape' && event.key !== 'Esc') {
        return;
      }
      entries.forEach(function (entry) {
        if (isOpen(entry)) {
          close(entry);
        }
      });
    });

    document.addEventListener('click', function (event) {
      entries.forEach(function (entry) {
        if (!isOpen(entry)) {
          return;
        }
        if (entry.pane.contains(event.target)) {
          return;
        }
        if (entry.trigger && entry.trigger.contains(event.target)) {
          return;
        }
        close(entry);
      });
    });
  });
})();
