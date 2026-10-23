// ── Enrove block converter (admin page side) ───────────────────────────────
// Owns the hidden iframe that converts classic-editor HTML to blocks (see
// Block_Converter in PHP for why it is an iframe). Loads nothing until asked.
//
//   enroveBlockConverter.preload()         start loading the iframe; idempotent
//   enroveBlockConverter.convert(sources)  sources: [{ id, content, attachments }]
//                                          → Promise<{ [id]: string|null }>
//
// A source that errored or timed out maps to null, for the caller to let the
// server fall back. The promise rejects only when the converter never becomes
// ready (a 403 page, a script error, a slow network); the caller then goes on
// without conversions. Same origin only, both ways.
(function () {
  var config = window.ENROVE_BLOCK_CONVERTER || {};
  var origin = window.location.origin;
  var LOAD_TIMEOUT = 20000;
  var REQUEST_TIMEOUT = 15000;
  // After the iframe's load event the ready message follows within a tick; a
  // page that loaded but stays silent is a refusal, so do not wait it out.
  var LOAD_GRACE = 3000;

  var frame = null;
  var ready = null; // Promise, shared by every caller
  var pending = {}; // request id → { resolve, timer }
  var nextRequest = 1;

  function reset() {
    if (frame && frame.parentNode) frame.parentNode.removeChild(frame);
    frame = null;
    ready = null;
  }

  function onMessage(event) {
    var data = event.data;
    if (!frame || event.origin !== origin || event.source !== frame.contentWindow) return;
    if (!data || data.type !== 'enrove-converted') return;
    var request = pending[data.id];
    if (!request) return;
    delete pending[data.id];
    clearTimeout(request.timer);
    request.resolve(typeof data.blocks === 'string' && !data.error ? data.blocks : null);
  }

  function start() {
    if (ready) return ready;
    ready = new Promise(function (resolve, reject) {
      var done = false;
      var timer, graceTimer;

      function finish(error) {
        if (done) return;
        done = true;
        clearTimeout(timer);
        clearTimeout(graceTimer);
        window.removeEventListener('message', onReady);
        if (error) {
          reset();
          reject(new Error(error));
        } else {
          resolve();
        }
      }

      function onReady(event) {
        if (frame && event.origin === origin && event.source === frame.contentWindow &&
            event.data && event.data.type === 'enrove-converter-ready') {
          finish();
        }
      }

      if (!config.url) {
        finish('Converter address missing');
        return;
      }
      window.addEventListener('message', onReady);
      timer = setTimeout(function () { finish('Converter did not load'); }, LOAD_TIMEOUT);

      frame = document.createElement('iframe');
      frame.hidden = true;
      frame.setAttribute('aria-hidden', 'true');
      frame.tabIndex = -1;
      frame.title = 'Block converter';
      frame.addEventListener('load', function () {
        graceTimer = setTimeout(function () { finish('Converter did not answer'); }, LOAD_GRACE);
      });
      frame.addEventListener('error', function () { finish('Converter failed to load'); });
      frame.src = config.url;
      document.body.appendChild(frame);
    });
    // preload() has no one to hear a rejection; convert() re-attaches its own.
    ready.catch(function () {});
    return ready;
  }

  function request(source) {
    return new Promise(function (resolve) {
      var id = nextRequest++;
      var timer = setTimeout(function () {
        delete pending[id];
        resolve(null);
      }, REQUEST_TIMEOUT);
      pending[id] = { resolve: resolve, timer: timer };
      frame.contentWindow.postMessage({
        type: 'enrove-convert',
        id: id,
        content: String(source.content || ''),
        attachments: source.attachments || {}
      }, origin);
    });
  }

  window.addEventListener('message', onMessage);

  window.enroveBlockConverter = {
    preload: function () {
      start();
    },
    convert: function (sources) {
      sources = sources || [];
      return start().then(function () {
        return Promise.all(sources.map(function (source) {
          return request(source).then(function (blocks) {
            return { id: source.id, blocks: blocks };
          });
        }));
      }).then(function (results) {
        var map = {};
        results.forEach(function (r) { map[r.id] = r.blocks; });
        return map;
      });
    }
  };
})();
