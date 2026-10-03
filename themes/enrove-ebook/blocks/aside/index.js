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

  var FONT = "'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";

  var wrapStyle = {
    margin: '2rem 0',
    padding: '1.125rem 1.25rem',
    background: '#F6F7F7',
    borderRadius: '2px',
  };

  var labelStyle = {
    display: 'block',
    fontFamily: FONT,
    fontSize: '0.75rem',
    fontWeight: '600',
    letterSpacing: '0.08em',
    textTransform: 'uppercase',
    // Muted, not the theme's green: the green measures 2.8:1 on white and this
    // is small text. See the note in theme.css.
    color: '#3C434A',
    marginBottom: '0.5rem',
  };

  var bodyStyle = {
    fontFamily: FONT,
    fontSize: '0.875rem',
    lineHeight: '1.65',
    color: '#3C434A',
  };

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M4 5h16v2H4V5zm0 4h10v2H4V9zm0 4h10v2H4v-2zm0 4h16v2H4v-2zm12-8h4v6h-4V9z',
    })
  );

  registerBlockType('enrove-ebook/aside', {
    title: __('Aside', 'enrove-folios'),
    description: __('A short note set apart from the argument — a caveat, a digression, a definition.', 'enrove-folios'),
    icon: BLOCK_ICON,
    category: 'enrove-ebook',
    keywords: [__('aside', 'enrove-folios'), __('note', 'enrove-folios'), __('sidenote', 'enrove-folios'), __('digression', 'enrove-folios'), __('caveat', 'enrove-folios')],
    attributes: {
      label: {
        type: 'string',
        default: 'Note',
      },
      body: {
        type: 'string',
        default: 'A short remark that belongs beside the argument rather than inside it.',
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
        el('aside', { style: wrapStyle },
          el(RichText, {
            tagName: 'div',
            style: labelStyle,
            value: attrs.label,
            onChange: function (v) { props.setAttributes({ label: v }); },
            placeholder: __('Label (optional)', 'enrove-folios'),
            allowedFormats: [],
          }),
          el(RichText, {
            tagName: 'div',
            style: bodyStyle,
            value: attrs.body,
            onChange: function (v) { props.setAttributes({ body: v }); },
            placeholder: __('The note…', 'enrove-folios'),
            allowedFormats: ['core/bold', 'core/italic', 'core/link'],
          })
        )
      );
    },

    save: function () {
      return null;
    },
  });
})(window.wp);
