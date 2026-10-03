// ── First-run setup dialog ─────────────────────────────────────────────────
// Asks, once, whether this site should download the theme fonts and the
// sample photos, then runs the downloads as a series of short requests so the
// progress is visible and a slow host never times out. The markup, and each
// item's starting state, come from \Enrove\Setup\First_Run::render_dialog().
//
// Closing the dialog never cancels anything. A download already running keeps
// going on this page, and the "Finish setup" entries ([data-enrove-setup-open])
// bring the dialog back until every item is settled. A declined item shows its
// choice again, "no" selected, so the dialog is also the way to download after
// all; left as it is, it needs nothing doing.
(function () {
  var cfg = window.ENROVE_SETUP;
  var modal = document.getElementById('g-setup-modal');
  if (!cfg || !modal || typeof window.enroveDialog !== 'function') return;

  var t = cfg.i18n || {};
  var items = Array.prototype.slice.call(modal.querySelectorAll('[data-enrove-setup-item]'));
  var runBtn = modal.querySelector('[data-enrove-setup-run]');
  var dismissBtn = modal.querySelector('[data-enrove-setup-dismiss]');
  var live = modal.querySelector('[data-enrove-setup-live]');
  var running = false;

  var dialog = window.enroveDialog(modal);

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

  // On this site: there is nothing left to choose.
  function isReady(item) {
    return item.getAttribute('data-state') === 'ready';
  }

  function choiceOf(item) {
    var checked = item.querySelector('input[type="radio"]:checked');
    return checked ? checked.value : 'download';
  }

  // Needs nothing doing: on this site, or declined and still left at "no".
  function isSettled(item) {
    return isReady(item) || (item.getAttribute('data-state') === 'declined' && choiceOf(item) === 'decline');
  }

  // Declined before this page asked anything: a download that fails puts the
  // "no" back rather than leaving the site half-way and asking again.
  function wasDeclined(item) {
    return item.getAttribute('data-was-declined') === '1';
  }

  // Already on this site, so "download" only switches back to them.
  function isOnSite(item) {
    var total = parseInt(item.getAttribute('data-total'), 10) || 0;
    var present = parseInt(item.getAttribute('data-present'), 10) || 0;
    return total > 0 && present >= total;
  }

  function openItems() {
    return items.filter(function (item) {
      return !isSettled(item);
    });
  }

  function itemName(item) {
    return item.getAttribute('data-enrove-setup-item');
  }

  // ── Rendering ──────────────────────────────────────────────────────────

  function setProgress(item, present, total) {
    var bar = item.querySelector('.g-setup__bar');
    var fill = item.querySelector('.g-setup__bar-fill');
    var status = item.querySelector('[data-enrove-setup-status]');
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
        input.disabled = disabled || isReady(item);
      });
    });
  }

  // A settled item keeps its title and says how it ended; the choice it no
  // longer needs goes away.
  function settle(item, state) {
    var choices = item.querySelector('.g-choices');
    var bar = item.querySelector('.g-setup__bar');
    var status = item.querySelector('[data-enrove-setup-status]');

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
    var status = item.querySelector('[data-enrove-setup-status]');
    item.classList.add('is-error');
    item.setAttribute('data-state', 'partial');
    showProgress(item);
    if (status) status.textContent = format(t.failed, message);
  }

  // The primary button always says what pressing it will do.
  function refreshActions() {
    dismissBtn.hidden = false;

    if (running) {
      runBtn.textContent = t.downloading;
      runBtn.disabled = true;
      dismissBtn.textContent = t.close;
      return;
    }

    runBtn.disabled = false;
    dismissBtn.textContent = t.notNow;

    var open = openItems();

    // Nothing to run: every item is on this site or left at "no" — after a
    // run, or opened to revisit a "no" and left as it was.
    if (!open.length) {
      runBtn.textContent = t.done;
      dismissBtn.hidden = true;
      return;
    }

    // "Try again" only while it is still a download that failed; a failed
    // item switched to its "no" choice is just a choice to save.
    if (open.some(function (item) { return item.classList.contains('is-error') && choiceOf(item) === 'download'; })) {
      runBtn.textContent = t.retry;
      return;
    }

    // Only what will really be fetched; switching back to files already on
    // this site is just a choice to save.
    var wanted = open.filter(function (item) {
      return choiceOf(item) === 'download' && !isOnSite(item);
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
    var status = item.querySelector('[data-enrove-setup-status]');
    var total = parseInt(item.getAttribute('data-total'), 10) || 0;
    var present = parseInt(item.getAttribute('data-present'), 10) || 0;
    // Set only when the server says the download failed, not when a request
    // is cut off: leaving the page mid-download aborts one, and the server
    // may well finish that step.
    var serverFailed = false;
    setProgress(item, present, total);
    if (present === 0 && status) status.textContent = t.preparing;

    function step(before) {
      return post('enrove_first_run_step', item).then(function (data) {
        setProgress(item, data.present, data.total);

        if (data.state === 'ready') {
          settle(item, 'ready');
          return;
        }
        if (data.failed > 0) {
          serverFailed = true;
          fail(item, data.error);
          return;
        }
        // Only ask again when the last step got somewhere: a file that
        // "downloads" but never counts as present would otherwise loop.
        if (data.remaining > 0 && data.present > before) {
          return step(data.present);
        }
        serverFailed = true;
        fail(item, t.stalled);
      });
    }

    return step(present).catch(function (error) {
      fail(item, error.message);
    }).then(function () {
      // The first step recorded "download" as the choice. If the server could
      // not finish it, a site that had said no goes back to no, with the
      // reason still shown and Try again still offered.
      if (!serverFailed || !wasDeclined(item)) return;
      return post('enrove_first_run_decline', item).then(function () {
        item.setAttribute('data-state', 'declined');
        var status = item.querySelector('[data-enrove-setup-status]');
        if (status && t.keptChoice) status.textContent += ' ' + t.keptChoice;
      }, function () {});
    });
  }

  function decline(item) {
    return post('enrove_first_run_decline', item).then(function () {
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

    var queue = openItems();

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
      var finished = !openItems().length;
      setChoicesDisabled(false);
      refreshActions();

      if (!finished) {
        var failed = modal.querySelector('.g-setup__item.is-error [data-enrove-setup-status]');
        var reason = failed ? failed.textContent : t.network;
        if (dialog.isOpen()) {
          announce(reason);
        } else if (typeof window.enroveShowToast === 'function') {
          // Closed mid-download: the dialog's own message would go unseen.
          window.enroveShowToast(reason, 'error');
        }
        return;
      }

      // "Full fonts and photos" only when nothing was left at "no".
      var message = items.every(isReady) ? t.finished : t.finishedDeclined;

      // Nothing is left to finish, so the header buttons go. With an item
      // still at "no", the panel stays: it is the way back to this dialog.
      var gone = items.every(isReady) ? '[data-enrove-setup-entry]' : '.g-setup-entry[data-enrove-setup-entry]';
      Array.prototype.forEach.call(document.querySelectorAll(gone), function (entry) {
        entry.remove();
      });

      if (dialog.isOpen()) {
        announce(message);
        runBtn.focus();
      } else if (typeof window.enroveShowToast === 'function') {
        window.enroveShowToast(message, 'success');
      }
    });
  }

  // ── Wiring ─────────────────────────────────────────────────────────────

  document.addEventListener('click', function (e) {
    var target = e.target instanceof Element ? e.target : null;
    if (!target) return;

    if (target.closest('[data-enrove-setup-open]')) {
      e.preventDefault();
      dialog.open();
      return;
    }
    if (modal.contains(target) && target.closest('[data-enrove-setup-close]')) {
      dialog.close();
    }
  });

  runBtn.addEventListener('click', function () {
    if (!openItems().length) {
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
