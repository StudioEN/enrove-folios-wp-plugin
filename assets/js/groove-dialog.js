// ── Groove dialog ──────────────────────────────────────────────────────────
// The behaviour every .g-theme-details-framed dialog shares: fade in and out,
// Escape to close, focus kept inside while open and returned on close, and
// the page scroll lock. The markup is always server-rendered; this only drives
// it.
//
//   var dialog = window.grooveDialog(modalEl, {
//     frame: '.g-theme-details__dialog',     // what takes focus on open
//     body: '.g-theme-details__body',        // scrolled to the top on open
//     canEscape: function () { return true }, // veto Escape (e.g. an overlay above)
//     onClose: function () {},
//   })
//   dialog.open(); dialog.close(); dialog.isOpen()
//
// The scroll lock is a class counted across dialogs, not an inline style: the
// live theme preview overlay stacks above these dialogs and clears its own
// inline lock on close, and two Groove dialogs can be open at once (setup over
// theme details), so neither may unlock the page under the other. The same
// stack decides which one Escape closes: only the topmost.
(function () {
  var stack = [];

  function lock(id) {
    stack.push(id);
    document.body.classList.add('g-modal-open');
  }

  function unlock(id) {
    var at = stack.lastIndexOf(id);
    if (at !== -1) stack.splice(at, 1);
    if (stack.length === 0) document.body.classList.remove('g-modal-open');
  }

  function isTop(id) {
    return stack.length > 0 && stack[stack.length - 1] === id;
  }

  var nextId = 0;

  window.grooveDialog = function (modal, options) {
    options = options || {};
    var frame = modal.querySelector(options.frame || '.g-theme-details__dialog') || modal;
    var body = options.body ? modal.querySelector(options.body) : modal.querySelector('.g-theme-details__body');
    var lastFocused = null;
    var hideTimer = null;
    var opened = false;
    var id = ++nextId;

    function isOpen() {
      return opened;
    }

    function open() {
      if (opened) return;
      opened = true;

      window.clearTimeout(hideTimer);
      lastFocused = document.activeElement;
      modal.hidden = false;
      lock(id);
      window.requestAnimationFrame(function () {
        modal.classList.add('is-open');
      });
      if (body) body.scrollTop = 0;
      // Focus the dialog itself: focusing the close button first paints a
      // ring on the one control you are least likely to want.
      frame.focus();
    }

    function close() {
      if (!opened) return;
      opened = false;

      modal.classList.remove('is-open');
      unlock(id);
      // Held in the DOM until the fade finishes; the timer also covers
      // reduced motion, where no transition fires at all.
      hideTimer = window.setTimeout(function () {
        modal.hidden = true;
      }, 260);

      if (lastFocused && typeof lastFocused.focus === 'function' && document.contains(lastFocused)) {
        lastFocused.focus();
      }
      if (typeof options.onClose === 'function') options.onClose();
    }

    // Everything visible and enabled inside the dialog, in document order.
    function focusables() {
      var found = modal.querySelectorAll('button, a[href], input:not([type="hidden"]), select, textarea');
      return Array.prototype.filter.call(found, function (el) {
        return el.offsetParent !== null && !el.disabled;
      });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape' || !opened || !isTop(id)) return;
      if (typeof options.canEscape === 'function' && !options.canEscape()) return;
      e.preventDefault();
      close();
    });

    modal.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab') return;
      var items = focusables();
      if (!items.length) return;

      var first = items[0];
      var last = items[items.length - 1];
      if (e.shiftKey && (document.activeElement === first || document.activeElement === frame)) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    });

    return { open: open, close: close, isOpen: isOpen };
  };
})();
