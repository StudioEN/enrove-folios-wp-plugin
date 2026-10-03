/**
 * Enrove block scope.
 *
 * Keeps other Enrove themes' blocks out of a folio page's inserter, and marks
 * any already on the page. Enqueued by \Enrove\Themes\Theme_Blocks only on a
 * enrove_folio_page whose folio's theme is known. window.ENROVE_BLOCK_SCOPE.notes
 * maps each other theme's ID to the sentence shown on its blocks, translated
 * in PHP.
 *
 * The blocks stay registered. supports.inserter = false takes them out of the
 * inserter, the slash menu and block search, while one already on the page
 * still renders and edits as before, under the note. The note is editor-only;
 * nothing is written into the saved content.
 *
 * Must run before the themes' block scripts: the registerBlockType filter only
 * sees blocks registered after it is added. Theme_Blocks enqueues it ahead of them.
 */
(function (wp, scope) {
  if (!wp || !wp.hooks || !wp.compose || !wp.element || !scope || !scope.notes) {
    return;
  }

  var notes = scope.notes;
  var el = wp.element.createElement;

  function noteFor(name) {
    var namespace = String(name).split('/')[0];
    return Object.prototype.hasOwnProperty.call(notes, namespace) ? notes[namespace] : '';
  }

  wp.hooks.addFilter(
    'blocks.registerBlockType',
    'enrove-folios/block-scope',
    function (settings, name) {
      if (!noteFor(name)) {
        return settings;
      }

      return Object.assign({}, settings, {
        supports: Object.assign({}, settings.supports, { inserter: false }),
      });
    }
  );

  // Inline styles, not a stylesheet: the canvas is an iframe, and this is the
  // only rule it would need. currentColor at reduced opacity reads on light
  // and dark editor canvases alike.
  var noteStyle = {
    display: 'flex',
    gap: '6px',
    alignItems: 'baseline',
    margin: '0 0 8px',
    font: '12px/1.4 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
    fontStyle: 'normal',
    fontWeight: 400,
    letterSpacing: 'normal',
    textTransform: 'none',
    color: 'currentColor',
    opacity: 0.65,
  };
  var dotStyle = {
    flex: 'none',
    width: '6px',
    height: '6px',
    borderRadius: '50%',
    background: 'currentColor',
    transform: 'translateY(-1px)',
  };

  wp.hooks.addFilter(
    'editor.BlockEdit',
    'enrove-folios/block-scope-note',
    wp.compose.createHigherOrderComponent(function (BlockEdit) {
      return function (props) {
        var note = noteFor(props.name);
        if (!note) {
          return el(BlockEdit, props);
        }

        return el(
          wp.element.Fragment,
          null,
          el(
            'div',
            { className: 'enrove-block-scope-note', role: 'note', contentEditable: false, style: noteStyle },
            el('span', { 'aria-hidden': 'true', style: dotStyle }),
            el('span', null, note)
          ),
          el(BlockEdit, props)
        );
      };
    }, 'withEnroveBlockScopeNote')
  );
})(window.wp, window.ENROVE_BLOCK_SCOPE);
