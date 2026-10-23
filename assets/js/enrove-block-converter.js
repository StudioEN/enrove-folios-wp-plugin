// ── Enrove block converter (iframe side) ───────────────────────────────────
// Runs inside the hidden iframe that Block_Converter::render() serves, the one
// place wp-block-library (several MB) is loaded. Turns classic-editor HTML into
// serialized blocks with core's raw handler, the same code the editor's
// Convert to blocks runs, and answers the admin page that owns the iframe
// (enrove-block-converter-client.js). Same origin only, both ways.
//
//   iframe → parent  { type: 'enrove-converter-ready' }
//   parent → iframe  { type: 'enrove-convert', id, content, attachments: { <id>: { url, alt } } }
//   iframe → parent  { type: 'enrove-converted', id, blocks }   or   { …, id, error }
(function () {
  var origin = window.location.origin;

  // The raw handler leaves several things that the editor's user would fix by
  // hand; the first three are what the spike found in real classic content.
  function isEmptyParagraph(content) {
    return !String(content || '')
      .replace(/<br\s*\/?>/gi, '')
      .replace(/&nbsp;| /gi, '')
      .trim();
  }

  // autop leaves a <br> at the edge of a paragraph after <!--nextpage-->.
  function trimBreaks(content) {
    return String(content || '')
      .replace(/^(?:\s|<br\s*\/?>)+/i, '')
      .replace(/(?:\s|<br\s*\/?>)+$/i, '');
  }

  // [gallery] becomes image blocks holding only an attachment ID. Fill url
  // and alt from the map the parent sends, at any depth.
  function tidy(blocks, media) {
    var out = [];
    blocks.forEach(function (block) {
      var attrs = block.attributes || {};
      if (block.name === 'core/paragraph') {
        attrs.content = trimBreaks(attrs.content);
        if (isEmptyParagraph(attrs.content)) return;
      }
      if (block.name === 'core/image' && attrs.id && !attrs.url && media[attrs.id]) {
        attrs.url = media[attrs.id].url;
        if (!attrs.alt) attrs.alt = media[attrs.id].alt || '';
      }
      if (block.innerBlocks && block.innerBlocks.length) {
        block.innerBlocks = tidy(block.innerBlocks, media);
      }
      out.push(block);
    });
    return out;
  }

  function convert(content, media) {
    content = String(content || '');
    // Already blocks: autop would break the markup, and there is nothing to do.
    if (content.indexOf('<!-- wp:') !== -1) return content;

    // Scripts and styles never reach a folio page's front end; drop them whole
    // so their text does not become paragraphs.
    content = content.replace(/<(script|style)\b[^>]*>[\s\S]*?<\/\1\s*>/gi, '');

    var html = wp.autop.autop(content);
    var blocks = tidy(wp.blocks.rawHandler({ HTML: html }), media || {});
    var serialized = wp.blocks.serialize(blocks);
    if (!serialized.trim()) throw new Error('Nothing to convert');
    return serialized;
  }

  function onMessage(event) {
    var data = event.data;
    if (event.origin !== origin || event.source !== window.parent) return;
    if (!data || data.type !== 'enrove-convert') return;
    var reply = { type: 'enrove-converted', id: data.id };
    try {
      reply.blocks = convert(data.content, data.attachments);
    } catch (e) {
      reply.error = String((e && e.message) || e);
    }
    window.parent.postMessage(reply, origin);
  }

  wp.domReady(function () {
    try {
      // Registers core/freeform too, which the raw handler needs for what it
      // cannot convert.
      wp.blockLibrary.registerCoreBlocks();
    } catch (e) {
      // Stay silent: the parent's load timeout reports a converter that never answers.
      return;
    }
    window.addEventListener('message', onMessage);
    window.parent.postMessage({ type: 'enrove-converter-ready' }, origin);
  });
})();
