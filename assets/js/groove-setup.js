// ── First-run setup dialog ─────────────────────────────────────────────────
// Asks, once, whether this site should download the theme fonts and the
// sample photos, then runs the downloads as a series of short requests so the
// progress is visible and a slow host never times out. The markup, and each
// item's starting state, come from \Groove\Setup\First_Run::render_dialog().
//
// Closing the dialog never cancels anything. A download already running keeps
// going on this page, and the "Finish setup" entries ([data-groove-setup-open])
// bring the dialog back until every item is settled.
(function () {
  var cfg = window.GROOVE_SETUP;
  var modal = document.getElementById('g-setup-modal');
  if (!cfg || !modal || typeof window.grooveDialog !== 'function') return;

  var t = cfg.i18n || {};
  var items = Array.prototype.slice.call(modal.querySelectorAll('[data-groove-setup-item]'));
  var runBtn = modal.querySelector('[data-groove-setup-run]');
  var dismissBtn = modal.querySelector('[data-groove-setup-dismiss]');
  var live = modal.querySelector('[data-groove-setup-live]');
  var running = false;
  var finished = false;

  var dialog = window.grooveDialog(modal);

  function format(template) {
    var args = Array.prototype.slice.call(arguments, 1);
    return String(template).replace(/%(\d+)\$s|%s/g, function (match, index) {
      var value = index ? args[index - 1] : args.shift();
      return value === undefined ? '' : String(value);
    });
  }

  function announce(message) {
    if (!live) return;
    // Cleared first, so the same sentence twice is still read out.
    live.textContent = '';
    window.setTimeout(function () {
      live.textContent = message;
    }, 50);
  }

  function isSettled(item) {
    var state = item.getAttribute('data-state');
    return state === 'ready' || state === 'declined';
  }

  function choiceOf(item) {
    var checked = item.querySelector('input[type="radio"]:checked');
    return checked ? checked.value : 'download';
  }

  function itemName(item) {
    return item.getAttribute('data-groove-setup-item');
  }

  // ── Rendering ──────────────────────────────────────────────────────────

  function setProgress(item, present, total) {
    var bar = item.querySelector('.g-setup__bar');
    var fill = item.querySelector('.g-setup__bar-fill');
    var status = item.querySelector('[data-groove-setup-status]');
    var pct = total > 0 ? Math.round((present / total) * 100) : 0;

    item.setAttribute('data-present', String(present));
    if (bar) {
      bar.hidden = false;
      bar.setAttribute('aria-valuenow', String(present));
      bar.setAttribute('aria-valuemax', String(total));
    }
    if (fill) fill.style.width = pct + '%';
    if (status) status.textContent = format(t.progress, present, total);
  }

  function showProgress(item) {
    var progress = item.querySelector('.g-setup__progress');
    if (progress) progress.hidden = false;
  }

  function setChoicesDisabled(disabled) {
    items.forEach(function (item) {
      Array.prototype.forEach.call(item.querySelectorAll('input'), function (input) {
        input.disabled = disabled || isSettled(item);
      });
    });
  }

  // A settled item keeps its title and says how it ended; the choice it no
  // longer needs goes away.
  function settle(item, state) {
    var choices = item.querySelector('.g-choices');
    var bar = item.querySelector('.g-setup__bar');
    var status = item.querySelector('[data-groove-setup-status]');

    item.setAttribute('data-state', state);
    item.classList.remove('is-error');
    if (choices) choices.hidden = true;
    showProgress(item);

    if (state === 'declined') {
      if (bar) bar.hidden = true;
    } else {
      var total = parseInt(item.getAttribute('data-total'), 10) || 0;
      setProgress(item, total, total);
    }
    // The same sentence the server renders for a settled item.
    if (status) status.textContent = item.getAttribute('data-' + state + '-text') || '';
  }

  function fail(item, message) {
    var status = item.querySelector('[data-groove-setup-status]');
    item.classList.add('is-error');
    item.setAttribute('data-state', 'partial');
    showProgress(item);
    if (status) status.textContent = format(t.failed, message);
  }

  // The primary button always says what pressing it will do.
  function refreshActions() {
    if (finished) {
      runBtn.textContent = t.done;
      runBtn.disabled = false;
      dismissBtn.hidden = true;
      return;
    }

    dismissBtn.hidden = false;

    if (running) {
      runBtn.textContent = t.downloading;
      runBtn.disabled = true;
      dismissBtn.textContent = t.close;
      return;
    }

    runBtn.disabled = false;
    dismissBtn.textContent = t.notNow;

    var open = items.filter(function (item) {
      return !isSettled(item);
    });

    // "Try again" only while it is still a download that failed; a failed
    // item switched to its "no" choice is just a choice to save.
    if (open.some(function (item) { return item.classList.contains('is-error') && choiceOf(item) === 'download'; })) {
      runBtn.textContent = t.retry;
      return;
    }

    var wanted = open.filter(function (item) {
      return choiceOf(item) === 'download';
    }).map(itemName);

    if (wanted.length === 2) {
      runBtn.textContent = t.downloadBoth;
    } else if (wanted[0] === 'fonts') {
      runBtn.textContent = t.downloadFonts;
    } else if (wanted[0] === 'photos') {
      runBtn.textContent = t.downloadPhotos;
    } else {
      runBtn.textContent = t.saveChoices;
    }
  }

  // ── Requests ───────────────────────────────────────────────────────────

  function post(action, item) {
    var body = new FormData();
    body.append('action', action);
    body.append('nonce', cfg.nonce);
    body.append('item', itemName(item));

    return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
      .then(function (response) {
        return response.json().catch(function () {
          return { success: false, data: { message: t.network } };
        });
      })
      .then(function (json) {
        // admin-ajax answers a stale nonce with a bare -1.
        return json === -1 ? { success: false, data: { message: t.expired } } : json;
      })
      .then(function (json) {
        if (!json || !json.success) {
          throw new Error((json && json.data && json.data.message) || t.network);
        }
        return json.data;
      }, function (error) {
        if (error && error.message && error.message !== 'Failed to fetch') throw error;
        throw new Error(t.network);
      });
  }

  // One step at a time until the item is on disk, the server reports a
  // failure, or a step makes no progress.
  function download(item) {
    item.classList.remove('is-error');
    showProgress(item);
    var status = item.querySelector('[data-groove-setup-status]');
    var total = parseInt(item.getAttribute('data-total'), 10) || 0;
    var present = parseInt(item.getAttribute('data-present'), 10) || 0;
    setProgress(item, present, total);
    if (present === 0 && status) status.textContent = t.preparing;

    function step(before) {
      return post('groove_first_run_step', item).then(function (data) {
        setProgress(item, data.present, data.total);

        if (data.state === 'ready') {
          settle(item, 'ready');
          return;
        }
        if (data.failed > 0) {
          fail(item, data.error);
          return;
        }
        // Only ask again when the last step got somewhere: a file that
        // "downloads" but never counts as present would otherwise loop.
        if (data.remaining > 0 && data.present > before) {
          return step(data.present);
        }
        fail(item, t.stalled);
      });
    }

    return step(present).catch(function (error) {
      fail(item, error.message);
    });
  }

  function decline(item) {
    return post('groove_first_run_decline', item).then(function () {
      settle(item, 'declined');
    }, function (error) {
      fail(item, error.message);
    });
  }

  function run() {
    if (running) return;
    running = true;
    setChoicesDisabled(true);
    refreshActions();

    var queue = items.filter(function (item) {
      return !isSettled(item);
    });

    // In order, not at once: two long downloads side by side would each be
    // half as likely to finish inside the server's time limit.
    var chain = Promise.resolve();
    queue.forEach(function (item) {
      chain = chain.then(function () {
        return choiceOf(item) === 'download' ? download(item) : decline(item);
      });
    });

    chain.then(function () {
      running = false;
      finished = items.every(isSettled);
      setChoicesDisabled(false);
      refreshActions();

      if (!finished) {
        var failed = modal.querySelector('.g-setup__item.is-error [data-groove-setup-status]');
        var reason = failed ? failed.textContent : t.network;
        if (dialog.isOpen()) {
          announce(reason);
        } else if (typeof window.grooveShowToast === 'function') {
          // Closed mid-download: the dialog's own message would go unseen.
          window.grooveShowToast(reason, 'error');
        }
        return;
      }

      var downloaded = items.some(function (item) {
        return item.getAttribute('data-state') === 'ready';
      });
      var message = downloaded ? t.finished : t.finishedDeclined;

      // Nothing is left to finish, so nothing should offer to.
      Array.prototype.forEach.call(document.querySelectorAll('[data-groove-setup-entry]'), function (entry) {
        entry.remove();
      });

      if (dialog.isOpen()) {
        announce(message);
        runBtn.focus();
      } else if (typeof window.grooveShowToast === 'function') {
        window.grooveShowToast(message, 'success');
      }
    });
  }

  // ── Wiring ─────────────────────────────────────────────────────────────

  document.addEventListener('click', function (e) {
    var target = e.target instanceof Element ? e.target : null;
    if (!target) return;

    if (target.closest('[data-groove-setup-open]')) {
      e.preventDefault();
      dialog.open();
      return;
    }
    if (modal.contains(target) && target.closest('[data-groove-setup-close]')) {
      dialog.close();
    }
  });

  runBtn.addEventListener('click', function () {
    if (finished) {
      dialog.close();
      return;
    }
    run();
  });

  modal.addEventListener('change', function (e) {
    if (e.target instanceof HTMLInputElement && e.target.type === 'radio') refreshActions();
  });

  // Leaving mid-download stops it between files; nothing half-written is
  // kept, but the rest waits for another press. Worth one question.
  window.addEventListener('beforeunload', function (e) {
    if (!running) return;
    e.preventDefault();
    e.returnValue = t.leaveWarning;
    return t.leaveWarning;
  });

  // A partial download from an earlier visit shows where it stopped.
  items.forEach(function (item) {
    if (item.getAttribute('data-state') === 'partial') {
      showProgress(item);
      setProgress(item, parseInt(item.getAttribute('data-present'), 10) || 0, parseInt(item.getAttribute('data-total'), 10) || 0);
    }
  });
  refreshActions();

  if (cfg.autoOpen) {
    // A beat after the screen paints, so it reads as the screen asking rather
    // than as something in the way of it.
    window.setTimeout(dialog.open, 400);
  }
})();
