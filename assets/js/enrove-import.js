// ── Import existing posts ──────────────────────────────────────────────────
// Drives the Add existing dialog (Folio::render_import_dialog()): searches
// the posts and pages the user may edit (Contents\FolioPage\Import, over
// admin-ajax), keeps what they pick in the order they pick it, and on submit
// converts the classic ones to blocks in the hidden converter
// (window.enroveBlockConverter) before the form goes to admin-post.php.
//
// Two modes, from the dialog's data-mode. 'folio', in the folio editor: the
// dialog's own footer form copies the picks into that folio. 'new', on All
// Folios: the Add New picker opened it, so the picks go back to the picker,
// whose create form (data-enrove-import-target) makes them the new folio's
// pages when Continue is pressed.
//
// A conversion that fails, or a converter that never loads, costs nothing but
// the conversion: the post is sent without blocks and the server copies it
// with paragraphs added, to convert in the editor later. Every text the list
// shows arrives as data and is set as text, never as HTML.
(function () {
  var cfg = window.ENROVE_IMPORT;
  var modal = document.getElementById('g-import-modal');
  if (!cfg || !modal || typeof window.enroveDialog !== 'function') return;

  var __ = wp.i18n.__;
  var _n = wp.i18n._n;
  var sprintf = wp.i18n.sprintf;

  var mode = modal.getAttribute('data-mode') === 'new' ? 'new' : 'folio';
  var folioId = modal.getAttribute('data-folio-id') || '';
  var list = modal.querySelector('.g-import__list');
  var search = modal.querySelector('#g-import-search');
  var empty = modal.querySelector('[data-enrove-import-empty]');
  var more = modal.querySelector('[data-enrove-import-more]');
  var live = modal.querySelector('[data-enrove-import-live]');
  // The form the picks are submitted with: the dialog's own, or the picker's.
  var form = mode === 'folio'
    ? modal.querySelector('form.g-import__footer')
    : document.querySelector('form[data-enrove-import-target]');
  if (!form) return;
  var status = modal.querySelector('[data-enrove-import-status]');
  var submit = modal.querySelector('[data-enrove-import-submit]');
  var hint = status.textContent;
  var max = parseInt(cfg.maxSources, 10) || 50;

  var picked = [];   // IDs, in the order they were picked
  var items = {};    // ID → the item as the server described it
  var page = 1;
  var pages = 0;
  var seq = 0;       // only the newest search may fill the list
  var loaded = false;
  var busy = false;
  var searchTimer = null;

  var dialog = window.enroveDialog(modal, {
    canEscape: function () { return !busy; },
  });

  function post(data) {
    var body = new FormData();
    body.append('nonce', cfg.nonce);
    Object.keys(data).forEach(function (key) { body.append(key, data[key]); });

    return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
      .then(function (response) { return response.json(); })
      .then(function (json) {
        // admin-ajax answers a stale nonce with a bare -1.
        if (!json || json.success !== true) throw new Error((json && json.data && json.data.message) || 'request failed');
        return json.data;
      });
  }

  function currentType() {
    var checked = modal.querySelector('input[name="g-import-type"]:checked');
    return checked ? checked.value : '';
  }

  function announce(text) {
    live.textContent = text;
  }

  // ── The list ──

  function notesFor(item) {
    var notes = [];
    if (item.held) notes.push({ text: __('Already in this folio', 'enrove-folios'), settled: true });
    if (item.builder) notes.push({ text: __('Made with a page builder: its layout won’t come across', 'enrove-folios'), warn: true });
    if (item.shortcodes && item.shortcodes.length) {
      notes.push({
        text: sprintf(
          /* translators: %s: shortcode names, e.g. [contact-form-7], [button] */
          __('Shows %s as text', 'enrove-folios'),
          item.shortcodes.map(function (tag) { return '[' + tag + ']'; }).join(', ')
        ),
        warn: true,
      });
    }
    if (item.password) notes.push({ text: __('Its password isn’t copied', 'enrove-folios'), warn: true });
    if (item.classic) notes.push({ text: __('Classic editor: converted to blocks', 'enrove-folios') });
    return notes;
  }

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function renderRow(item) {
    var li = el('li', 'g-import__item');
    var label = el('label', 'g-import__row');
    var box = el('input', 'g-import__check');
    box.type = 'checkbox';
    box.value = String(item.id);
    box.checked = picked.indexOf(item.id) !== -1;

    var text = el('span', 'g-import__text');
    text.appendChild(el('span', 'g-import__title', item.title));
    var meta = [item.typeLabel];
    if (item.status !== 'publish') meta.push(item.statusLabel);
    meta.push(item.modified);
    text.appendChild(el('span', 'g-import__meta', meta.join(' · ')));

    var notes = notesFor(item);
    if (notes.length) {
      var ul = el('span', 'g-import__notes');
      notes.forEach(function (note) {
        ul.appendChild(el('span', 'g-import__note' + (note.warn ? ' is-warn' : '') + (note.settled ? ' is-settled' : ''), note.text));
      });
      text.appendChild(ul);
    }

    var order = el('span', 'g-import__order');
    order.setAttribute('aria-hidden', 'true');

    label.appendChild(box);
    label.appendChild(text);
    label.appendChild(order);
    li.appendChild(label);
    return li;
  }

  function load(nextPage) {
    var append = nextPage > 1;
    var mine = ++seq;
    if (!append) {
      empty.hidden = false;
      empty.textContent = __('Loading…', 'enrove-folios');
      more.hidden = true;
    }
    more.disabled = true;

    post({
      action: 'enrove_import_search',
      search: search.value.trim(),
      post_type: currentType(),
      paged: nextPage,
      folio_id: folioId,
    }).then(function (data) {
      if (mine !== seq) return;
      if (!append) list.textContent = '';
      data.items.forEach(function (item) {
        items[item.id] = item;
        list.appendChild(renderRow(item));
      });
      page = data.page;
      pages = data.pages;
      loaded = true;

      empty.hidden = list.children.length > 0;
      empty.textContent = search.value.trim() !== ''
        ? __('No posts or pages match that search.', 'enrove-folios')
        : __('There are no posts or pages you can add yet.', 'enrove-folios');
      more.hidden = page >= pages;
      more.disabled = false;
      refresh();
      if (!append) {
        announce(sprintf(
          /* translators: %s: number of posts and pages found */
          _n('%s post or page found.', '%s posts and pages found.', data.total, 'enrove-folios'),
          data.total
        ));
      }
    }).catch(function () {
      if (mine !== seq) return;
      empty.hidden = false;
      empty.textContent = __('The list could not be loaded. Reload the page and try again.', 'enrove-folios');
      more.hidden = true;
    });
  }

  // ── Picking ──

  // The order numbers, the count, and the limit, after any change.
  function refresh() {
    var full = picked.length >= max;
    Array.prototype.forEach.call(list.querySelectorAll('.g-import__check'), function (box) {
      var id = parseInt(box.value, 10);
      var at = picked.indexOf(id);
      box.checked = at !== -1;
      box.disabled = busy || (full && at === -1);
      box.closest('.g-import__item').classList.toggle('is-picked', at !== -1);
      box.closest('.g-import__row').querySelector('.g-import__order').textContent = at === -1 ? '' : String(at + 1);
    });

    if (busy) return;
    submit.disabled = picked.length === 0;
    submit.textContent = picked.length
      ? sprintf(
        /* translators: %s: number of pages to add */
        _n('Add %s page', 'Add %s pages', picked.length, 'enrove-folios'),
        picked.length
      )
      : __('Add pages', 'enrove-folios');
    status.textContent = full
      ? sprintf(
        /* translators: %s: the most posts one import takes */
        __('%s is the most you can add at once.', 'enrove-folios'),
        max
      )
      : hint;
  }

  list.addEventListener('change', function (e) {
    var box = e.target.closest('.g-import__check');
    if (!box) return;
    var id = parseInt(box.value, 10);
    var at = picked.indexOf(id);
    if (box.checked && at === -1) picked.push(id);
    if (!box.checked && at !== -1) picked.splice(at, 1);
    refresh();
  });

  search.addEventListener('input', function () {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(function () { load(1); }, 300);
  });
  // Enter in the search field searches now; it must not submit anything.
  search.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    window.clearTimeout(searchTimer);
    load(1);
  });
  modal.addEventListener('change', function (e) {
    if (e.target.name === 'g-import-type') load(1);
  });
  more.addEventListener('click', function () { load(page + 1); });

  // ── Submitting ──

  function setBusy(on, text) {
    busy = on;
    search.disabled = on;
    Array.prototype.forEach.call(modal.querySelectorAll('input[name="g-import-type"], [data-enrove-import-close], [data-enrove-import-more]'), function (control) {
      control.disabled = on;
    });
    submit.disabled = on;
    if (text) status.textContent = text;
    refresh();
  }

  function addField(name, value) {
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    // Marked, so a second submit after a failed one starts from a clean form.
    input.setAttribute('data-enrove-import-field', '');
    (form.querySelector('[data-enrove-import-fields]') || form).appendChild(input);
  }

  // The classic sources, fetched a few at a time, then converted together.
  function convertClassic(ids) {
    var converter = window.enroveBlockConverter;
    if (!ids.length || !converter) return Promise.resolve({});

    var sources = [];
    var queue = ids.slice();
    function next() {
      var id = queue.shift();
      if (id === undefined) return Promise.resolve();
      return post({ action: 'enrove_import_source', post_id: id })
        .then(function (source) {
          if (source.classic) sources.push({ id: source.id, content: source.content, attachments: source.attachments || {} });
        }, function () { /* this one goes unconverted */ })
        .then(next);
    }
    var workers = [];
    for (var i = 0; i < Math.min(4, ids.length); i++) workers.push(next());

    return Promise.all(workers)
      .then(function () { return sources.length ? converter.convert(sources) : {}; })
      .catch(function () { return {}; });
  }

  // Convert the classic picks, write every pick into the form (sources[] in
  // order, converted[<id>] for each one converted), and submit it.
  // report() is told what is happening, for whichever line shows it.
  function submitPicks(report) {
    var classic = picked.filter(function (id) { return items[id] && items[id].classic; });
    report(classic.length
      ? sprintf(
        /* translators: %s: number of classic-editor posts being converted */
        _n('Converting %s classic post to blocks…', 'Converting %s classic posts to blocks…', classic.length, 'enrove-folios'),
        classic.length
      )
      : __('Adding pages…', 'enrove-folios'));

    convertClassic(classic).then(function (converted) {
      Array.prototype.forEach.call(form.querySelectorAll('input[data-enrove-import-field]'), function (input) {
        input.parentNode.removeChild(input);
      });
      picked.forEach(function (id) {
        addField('sources[]', String(id));
        if (typeof converted[id] === 'string' && converted[id] !== '') addField('converted[' + id + ']', converted[id]);
      });
      report(__('Adding pages…', 'enrove-folios'));
      // HTMLFormElement.submit() skips the submit handlers, and the page navigates away.
      form.submit();
    });
  }

  if (mode === 'folio') {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (busy || !picked.length) return;
      setBusy(true);
      submitPicks(function (text) { status.textContent = text; });
    });
  }

  // ── A new folio: the picks go back to the Add New picker ──

  var row = form.querySelector('.g-folio__theme-button');
  var summary = form.querySelector('[data-enrove-import-summary]');
  var summaryText = form.querySelector('[data-enrove-import-summary-text]');
  var openNew = document.getElementById('g-add-new-import-open');

  function showPicks() {
    var count = picked.length;
    // The sample toggle steps aside while posts are picked: a folio starts
    // one way or the other, and create_folio() ignores the sample when both come.
    if (row) row.classList.toggle('is-importing', count > 0);
    if (openNew) openNew.hidden = count > 0;
    if (!summary) return;
    summary.hidden = count === 0;
    summaryText.textContent = describePicks();
  }

  // "2 posts picked", "1 page picked", "1 post and 2 pages picked": every
  // pick came from the list, so its type is known.
  function describePicks() {
    var pages = picked.filter(function (id) { return items[id] && items[id].type === 'page'; }).length;
    var posts = picked.length - pages;
    if (posts && pages) {
      return sprintf(
        /* translators: 1: "1 post" / "2 posts", 2: "1 page" / "2 pages" */
        __('%1$s and %2$s picked', 'enrove-folios'),
        /* translators: %s: number of posts */
        sprintf(_n('%s post', '%s posts', posts, 'enrove-folios'), posts),
        /* translators: %s: number of pages */
        sprintf(_n('%s page', '%s pages', pages, 'enrove-folios'), pages)
      );
    }
    return pages
      /* translators: %s: number of pages picked */
      ? sprintf(_n('%s page picked', '%s pages picked', pages, 'enrove-folios'), pages)
      /* translators: %s: number of posts picked */
      : sprintf(_n('%s post picked', '%s posts picked', posts, 'enrove-folios'), posts);
  }

  if (mode === 'new') {
    submit.addEventListener('click', function () {
      if (!picked.length) return;
      dialog.close();
      showPicks();
      form.querySelector('button[type="submit"]').focus();
    });

    form.addEventListener('submit', function (e) {
      if (!picked.length) return;
      e.preventDefault();
      if (busy) return;
      busy = true;
      Array.prototype.forEach.call(form.querySelectorAll('button'), function (button) { button.disabled = true; });
      submitPicks(function (text) { summaryText.textContent = text; });
    });

    form.addEventListener('click', function (e) {
      if (busy) return;
      if (e.target.closest('[data-enrove-import-change]')) open();
      if (e.target.closest('[data-enrove-import-clear]')) {
        picked = [];
        refresh();
        showPicks();
        if (openNew) openNew.focus();
      }
    });
  }

  // ── Opening and closing ──

  function open() {
    dialog.open();
    if (!loaded) load(1);
    // Start the converter loading now, so it is ready by the time a pick is made.
    if (window.enroveBlockConverter && typeof window.enroveBlockConverter.preload === 'function') {
      window.enroveBlockConverter.preload();
    }
    window.setTimeout(function () { search.focus(); }, 50);
  }

  modal.addEventListener('click', function (e) {
    if (!busy && e.target.closest('[data-enrove-import-close]')) dialog.close();
  });

  var opener = document.getElementById(mode === 'folio' ? 'g-folio-import-open' : 'g-add-new-import-open');
  if (opener) {
    opener.addEventListener('click', function (e) {
      e.preventDefault();
      open();
    });
  }
})();
