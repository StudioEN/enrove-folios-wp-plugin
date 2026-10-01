(function (wp) {
  'use strict';

  if (!wp || !wp.blocks || !wp.element || !wp.blockEditor || !wp.i18n) {
    return;
  }

  var el = wp.element.createElement;
  var __ = wp.i18n.__;
  var registerBlockType = wp.blocks.registerBlockType;
  var RichText = wp.blockEditor.RichText;
  var useBlockProps = wp.blockEditor.useBlockProps;

  // The canvas approximates theme.css rather than importing it: the editor is
  // a different document with a different width, and a preview that lies about
  // the width is worse than one that is honestly a sketch.
  var FONT = "'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
  var INK = '#101517';
  var MUTED = '#3C434A';

  var wrapStyle = {
    margin: '2rem 0 2.5rem',
    paddingLeft: '1.5rem',
    borderLeft: '1px solid #E4E4E5',
    maxWidth: '34rem',
  };

  var textStyle = {
    fontFamily: FONT,
    fontSize: '1rem',
    fontStyle: 'italic',
    fontWeight: '400',
    lineHeight: '1.7',
    color: MUTED,
    margin: '0',
    padding: '0',
    border: 'none',
  };

  var attributionStyle = {
    fontFamily: FONT,
    fontSize: '0.8125rem',
    fontStyle: 'normal',
    fontWeight: '500',
    lineHeight: '1.4',
    color: INK,
    marginTop: '0.625rem',
  };

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M5 4h2v16H5V4zm5 3h9v2h-9V7zm0 4h9v2h-9v-2zm0 4h6v2h-6v-2z',
    })
  );

  registerBlockType('groove-ebook/epigraph', {
    title: __('Epigraph', 'groove-folios'),
    description: __('A borrowed line set under a chapter title, with its attribution.', 'groove-folios'),
    icon: BLOCK_ICON,
    category: 'groove-ebook',
    keywords: [__('epigraph', 'groove-folios'), __('quote', 'groove-folios'), __('chapter', 'groove-folios'), __('opening', 'groove-folios'), __('motto', 'groove-folios')],
    attributes: {
      text: {
        type: 'string',
        default: 'We are what we repeatedly do. Excellence, then, is not an act but a habit.',
      },
      attribution: {
        type: 'string',
        default: 'Will Durant',
      },
    },
    supports: {
      html: false,
      reusable: true,
    },

    edit: function (props) {
      var attrs = props.attributes;
      var blockProps = useBlockProps();

      return el('div', blockProps,
        el('figure', { style: wrapStyle },
          el(RichText, {
            tagName: 'div',
            style: textStyle,
            value: attrs.text,
            onChange: function (v) { props.setAttributes({ text: v }); },
            placeholder: __('The quoted line…', 'groove-folios'),
            allowedFormats: ['core/italic', 'core/bold'],
          }),
          el(RichText, {
            tagName: 'div',
            // The dash is drawn by CSS on the front end, and shown here so the
            // canvas matches. It is never stored in the attribute.
            style: attributionStyle,
            value: attrs.attribution,
            onChange: function (v) { props.setAttributes({ attribution: v }); },
            placeholder: __('Attribution', 'groove-folios'),
            allowedFormats: [],
          })
        )
      );
    },

    save: function () {
      return null;
    },
  });
})(window.wp);
