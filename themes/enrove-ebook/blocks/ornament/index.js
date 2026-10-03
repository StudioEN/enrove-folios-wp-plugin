(function (wp) {
  'use strict';

  if (!wp || !wp.blocks || !wp.element || !wp.blockEditor || !wp.i18n) {
    return;
  }

  var el = wp.element.createElement;
  var __ = wp.i18n.__;
  var registerBlockType = wp.blocks.registerBlockType;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var BlockControls = wp.blockEditor.BlockControls;
  var ToolbarGroup = wp.components.ToolbarGroup;
  var ToolbarButton = wp.components.ToolbarButton;

  // Must stay in step with ENROVE_EBOOK_ORNAMENTS in blocks.php.
  var MARKS = [
    { key: 'asterism', label: __('Asterism', 'enrove-folios'), glyph: '⁂', icon: 'star-filled' },
    { key: 'stars', label: __('Three stars', 'enrove-folios'), glyph: '* * *', icon: 'star-empty' },
    { key: 'dots', label: __('Three dots', 'enrove-folios'), glyph: '· · ·', icon: 'ellipsis' },
    { key: 'rule', label: __('Short rule', 'enrove-folios'), glyph: '', icon: 'minus' },
    { key: 'blank', label: __('Blank line', 'enrove-folios'), glyph: '', icon: 'editor-paragraph' },
  ];

  function markFor(key) {
    for (var i = 0; i < MARKS.length; i++) {
      if (MARKS[i].key === key) {
        return MARKS[i];
      }
    }
    return MARKS[0];
  }

  var wrapStyle = {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    margin: '2.5rem 0',
    minHeight: '1.5rem',
  };

  var glyphStyle = {
    fontFamily: "'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
    fontSize: '1.125rem',
    letterSpacing: '0.15em',
    color: '#8C8F94',
    lineHeight: '1',
  };

  var ruleStyle = {
    width: '3rem',
    height: '1px',
    background: '#C9CACC',
  };

  var blankStyle = {
    width: '100%',
    height: '1px',
    background: 'transparent',
    borderTop: '1px dashed #E4E4E5',
  };

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M12 4l1.5 3H16l-2 2.2.8 3.3L12 10.8 9.2 12.5l.8-3.3L8 7h2.5L12 4zM4 16h16v1.5H4V16z',
    })
  );

  registerBlockType('enrove-ebook/ornament', {
    title: __('Section Break', 'enrove-folios'),
    description: __('The space between two scenes — an ornament, a short rule, or nothing at all.', 'enrove-folios'),
    icon: BLOCK_ICON,
    category: 'enrove-ebook',
    keywords: [__('break', 'enrove-folios'), __('ornament', 'enrove-folios'), __('asterism', 'enrove-folios'), __('divider', 'enrove-folios'), __('scene', 'enrove-folios'), __('separator', 'enrove-folios')],
    attributes: {
      mark: {
        type: 'string',
        default: 'asterism',
      },
    },
    supports: {
      html: false,
      reusable: false,
    },

    edit: function (props) {
      var mark = markFor(props.attributes.mark);
      var blockProps = useBlockProps();

      var body;
      if (mark.key === 'rule') {
        body = el('span', { style: ruleStyle });
      } else if (mark.key === 'blank') {
        // The front end renders empty space. The editor shows a hairline so the
        // block can be selected and is not mistaken for a stray blank line.
        body = el('span', { style: blankStyle });
      } else {
        body = el('span', { style: glyphStyle }, mark.glyph);
      }

      return el(wp.element.Fragment, null,
        el(BlockControls, null,
          el(ToolbarGroup, null,
            MARKS.map(function (m) {
              return el(ToolbarButton, {
                key: m.key,
                icon: m.icon,
                label: m.label,
                isPressed: m.key === mark.key,
                onClick: function () { props.setAttributes({ mark: m.key }); },
              });
            })
          )
        ),
        el('div', blockProps,
          el('div', { style: wrapStyle }, body)
        )
      );
    },

    save: function () {
      return null;
    },
  });
})(window.wp);
