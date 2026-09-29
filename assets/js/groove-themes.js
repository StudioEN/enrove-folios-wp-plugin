/**
 * Groove → Themes.
 *
 * Two independent enhancements, each a no-op when its markup is absent: the
 * theme details dialog on the Themes tab, and the contents rail's
 * current-section marker on the Spec and Playbook tabs. Both used to be
 * inline <script> blocks in pages/themes.php; enqueued only on that screen by
 * modules/groove-main/module.php.
 */

// ── Contents rail ──────────────────────────────────────────────────────────
// Marks the section being read. Everything it adds is an enhancement: with no
// JavaScript the rail is still a list of links to anchors that exist, which is
// the part that makes the document navigable.
(function () {
  var toc = document.querySelector('.g-docs__toc');
  if (!toc || !window.IntersectionObserver) return;

  var links = Array.prototype.slice.call(toc.querySelectorAll('.g-docs__toc-link'));
  var headings = [];
  var linkFor = {};

  links.forEach(function (link) {
    var id = decodeURIComponent(link.hash.slice(1));
    var heading = id && document.getElementById(id);
    if (!heading) return;
    linkFor[id] = link;
    headings.push(heading);
  });

  if (!headings.length) return;

  var current = null;

  // Raised while a click is scrolling the page. The observer keeps its
  // bookkeeping up to date but stops marking, so the rail does not strobe
  // through every section the scroll passes on its way down.
  var travelling = false;
  var settle = null;

  function arrive() {
    window.clearTimeout(settle);
    settle = window.setTimeout(function () {
      travelling = false;
    }, 120);
  }

  function mark(heading) {
    if (current === heading) return;
    current = heading;

    links.forEach(function (link) {
      link.classList.remove('is-current');
      link.removeAttribute('aria-current');
    });

    var link = linkFor[heading.id];
    if (!link) return;

    link.classList.add('is-current');
    link.setAttribute('aria-current', 'true');

    // Keep the marked entry inside the rail's own scroll box. Never
    // scrollIntoView(): that scrolls the page as well, which would
    // fight the scrolling that triggered this in the first place.
    var entry = link.getBoundingClientRect();
    var frame = toc.getBoundingClientRect();
    if (entry.top < frame.top || entry.bottom > frame.bottom) {
      toc.scrollTop += (entry.top - frame.top) - (frame.height / 3);
    }
  }

  // A band across the top of the viewport. The section being read is the
  // topmost heading inside it; when the band is empty — which is most of
  // a long section — the last mark stands rather than clearing.
  var inBand = [];
  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (record) {
      var at = inBand.indexOf(record.target);
      if (record.isIntersecting && at === -1) {
        inBand.push(record.target);
      } else if (!record.isIntersecting && at !== -1) {
        inBand.splice(at, 1);
      }
    });

    if (travelling || !inBand.length) return;

    inBand.sort(function (a, b) {
      return headings.indexOf(a) - headings.indexOf(b);
    });

    mark(inBand[0]);
  }, { rootMargin: '-52px 0px -72% 0px' });

  headings.forEach(function (heading) {
    observer.observe(heading);
  });

  // Before the first heading crosses the band, the reader is in the
  // opening section, so say so rather than showing nothing marked.
  mark(headings[0]);

  // Glide to the section instead of cutting to it, so the reader keeps
  // their bearings in a document this long. Delegated from the section
  // rather than bound to the rail: a cross-reference in the prose is the
  // same gesture and should not behave differently. Only bare '#anchor'
  // hrefs are taken — a link to the other tab carries a query string and
  // must navigate normally.
  var docs = document.querySelector('.g-docs');
  if (!docs || !document.body.closest) return;

  docs.addEventListener('click', function (event) {
    if (event.defaultPrevented || event.button !== 0) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    var link = event.target.closest('a');
    if (!link || !docs.contains(link)) return;

    var href = link.getAttribute('href') || '';
    if (href.charAt(0) !== '#' || href === '#') return;

    var target = document.getElementById(decodeURIComponent(href.slice(1)));
    if (!target) return;

    event.preventDefault();

    // Honoured live rather than read once, so turning the system setting
    // on takes effect without a reload.
    var still = window.matchMedia &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    travelling = true;
    arrive();
    mark(target);

    target.scrollIntoView({ behavior: still ? 'auto' : 'smooth', block: 'start' });

    // preventDefault() dropped the browser's own hash update along with
    // its jump, and the address bar is how a reader copies a link to a
    // section. replaceState, not pushState: Back should leave the
    // document, not walk every section the reader visited inside it.
    if (window.history && history.replaceState) {
      history.replaceState(null, '', href);
    }

    // The jump moved the viewport but not the keyboard, which would
    // otherwise resume from the rail. Headings are not focusable on
    // their own, hence the tabindex.
    if (!target.hasAttribute('tabindex')) {
      target.setAttribute('tabindex', '-1');
    }
    target.focus({ preventScroll: true });
  });

  window.addEventListener('scroll', function () {
    if (travelling) arrive();
  }, true);
})();

// ── Theme details dialog ───────────────────────────────────────────────────
// Every panel is rendered server-side and hidden; opening a card reveals its
// panel rather than rebuilding one in JS, so nonces, copy and per-theme forms
// all stay in PHP.
(function () {
  var modal = document.getElementById('g-theme-details-modal');
  if (!modal || typeof window.grooveDialog !== 'function') return;

  var titleEl = document.getElementById('g-theme-details-title');
  var badgeEl = modal.querySelector('[data-groove-theme-badge]');
  var defaultEl = modal.querySelector('[data-groove-theme-default]');
  var panels = modal.querySelectorAll('[data-groove-theme-panel]');

  var dialog = window.grooveDialog(modal, {
    // The live preview overlay stacks above this dialog and owns Escape
    // while it is open.
    canEscape: function () {
      var preview = document.getElementById('g-tpp-overlay');
      return !(preview && !preview.hidden);
    },
  });

  // The card carries the theme's name and badges, so the header can be
  // filled from the thing that was clicked rather than from a second copy
  // of the same facts held in JS.
  function open(card) {
    var themeId = card.getAttribute('data-groove-theme-open');
    var matched = null;
    Array.prototype.forEach.call(panels, function (panel) {
      var mine = panel.getAttribute('data-groove-theme-panel') === themeId;
      panel.hidden = !mine;
      if (mine) matched = panel;
    });
    if (!matched) return;

    titleEl.textContent = card.getAttribute('data-theme-name') || '';
    badgeEl.textContent = card.getAttribute('data-theme-badge') || '';
    badgeEl.className = 'g-themes-tag g-themes-tag--builtin';
    badgeEl.hidden = badgeEl.textContent === '';
    defaultEl.hidden = card.getAttribute('data-theme-default') !== '1';

    dialog.open();
  }

  document.addEventListener('click', function (e) {
    var target = e.target instanceof Element ? e.target : null;
    if (!target) return;

    var card = target.closest('[data-groove-theme-open]');
    if (card) {
      open(card);
      return;
    }
    if (target.closest('[data-groove-theme-close]')) {
      dialog.close();
    }
  });
})();
