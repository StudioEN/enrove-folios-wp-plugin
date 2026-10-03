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

  // ── Inline styles ──────────────────────────────────────────────────────

  var figureStyle = {
    margin: '1rem 0',
    padding: '1.25rem 1.5rem',
    border: '1px solid #d6d5d0',
    borderLeft: '3px solid #27498c',
    borderRadius: '0 0.5rem 0.5rem 0',
    background: '#f7f7f5',
  };

  var quoteStyle = {
    fontFamily: "'Fraunces', Georgia, serif",
    fontSize: '1.25rem',
    fontWeight: '300',
    fontStyle: 'italic',
    lineHeight: '1.55',
    color: '#1b1a18',
    margin: '0 0 0.75rem',
    padding: '0',
    border: 'none',
  };

  var citeStyle = {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.1rem',
  };

  var authorStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.8125rem',
    fontWeight: '500',
    fontStyle: 'normal',
    color: '#1b1a18',
    lineHeight: '1.3',
  };

  var roleStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.75rem',
    fontWeight: '400',
    fontStyle: 'normal',
    color: '#8a8781',
    lineHeight: '1.3',
  };

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M6 17h3l2-4V7H5v6h3zm8 0h3l2-4V7h-6v6h3z',
    })
  );

  registerBlockType('enrove-proposal/pull-quote', {
    title: __('Pull Quote / Testimonial', 'enrove-folios'),
    description: __('Large italic quote with author attribution.', 'enrove-folios'),
    icon: BLOCK_ICON,
    category: 'enrove-proposal',
    keywords: [__('quote', 'enrove-folios'), __('testimonial', 'enrove-folios'), __('blockquote', 'enrove-folios'), __('review', 'enrove-folios')],
    attributes: {
      text: {
        type: 'string',
        default: 'Working with this team transformed how we think about our product. The strategic clarity they brought was exactly what we needed.',
      },
      author: {
        type: 'string',
        default: 'Sarah Chen',
      },
      role: {
        type: 'string',
        default: 'VP of Product, Acme Inc.',
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
        el('figure', { style: figureStyle },
          el(RichText, {
            tagName: 'div',
            style: quoteStyle,
            value: attrs.text,
            onChange: function (v) { props.setAttributes({ text: v }); },
            placeholder: __('Enter quote\u2026', 'enrove-folios'),
            allowedFormats: [],
          }),
          el('figcaption', { style: citeStyle },
            el(RichText, {
              tagName: 'div',
              style: authorStyle,
              value: attrs.author,
              onChange: function (v) { props.setAttributes({ author: v }); },
              placeholder: __('Author name', 'enrove-folios'),
              allowedFormats: [],
            }),
            el(RichText, {
              tagName: 'div',
              style: roleStyle,
              value: attrs.role,
              onChange: function (v) { props.setAttributes({ role: v }); },
              placeholder: __('Role, Company', 'enrove-folios'),
              allowedFormats: [],
            })
          )
        )
      );
    },

    save: function () {
      return null;
    },
  });
})(window.wp);
