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
  var InspectorControls = wp.blockEditor.InspectorControls;
  var PanelBody = wp.components.PanelBody;
  var SelectControl = wp.components.SelectControl;
  var TextControl = wp.components.TextControl;

  var STYLES = ['primary', 'subtle'];

  var STYLE_OPTIONS = [
    { value: 'primary', label: __('Primary (solid)', 'enrove-folios') },
    { value: 'subtle', label: __('Subtle (bordered)', 'enrove-folios') },
  ];

  // ── Inline styles (reliable in the editor) ─────────────────────────────

  function wrapStyle(style) {
    var base = {
      margin: '2rem 0',
      padding: '2.5rem 2rem',
      borderRadius: '0.5rem',
      textAlign: 'center',
    };
    if (style === 'subtle') {
      return Object.assign({}, base, {
        background: 'transparent',
        border: '1px solid #d6d5d0',
      });
    }
    return Object.assign({}, base, {
      background: 'rgba(39, 73, 140, 0.08)',
      border: 'none',
    });
  }

  var headingStyle = {
    fontFamily: "'Fraunces', Georgia, serif",
    fontSize: '1.75rem',
    fontWeight: '300',
    fontStyle: 'italic',
    letterSpacing: '-0.01em',
    color: '#1b1a18',
    margin: '0 0 0.75rem',
  };

  var bodyStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.9375rem',
    color: '#8a8781',
    lineHeight: '1.6',
    maxWidth: '32rem',
    margin: '0 auto 1.5rem',
  };

  function buttonStyle(style) {
    var base = {
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      padding: '0.75rem 1.75rem',
      borderRadius: '0.25rem',
      fontFamily: "'Inter', -apple-system, sans-serif",
      fontSize: '0.8125rem',
      fontWeight: '600',
      letterSpacing: '0.01em',
    };
    if (style === 'subtle') {
      return Object.assign({}, base, {
        background: 'transparent',
        color: '#27498c',
        border: '1px solid #27498c',
      });
    }
    return Object.assign({}, base, {
      background: '#27498c',
      color: '#fff',
      border: 'none',
    });
  }

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M16.01 11H4v2h12.01v3L20 12l-3.99-4v3z',
    })
  );

  registerBlockType('enrove-proposal/cta', {
    title: __('Closing CTA', 'enrove-folios'),
    description: __('A "ready to move forward" call-to-action for the end of a proposal.', 'enrove-folios'),
    icon: BLOCK_ICON,
    category: 'enrove-proposal',
    keywords: [__('cta', 'enrove-folios'), __('call to action', 'enrove-folios'), __('closing', 'enrove-folios'), __('next steps', 'enrove-folios'), __('button', 'enrove-folios')],
    attributes: {
      heading: {
        type: 'string',
        default: 'Ready to get started?',
      },
      body: {
        type: 'string',
        default: 'Let’s schedule a call to walk through next steps and answer any questions.',
      },
      buttonText: {
        type: 'string',
        default: 'Schedule a call',
      },
      buttonUrl: {
        type: 'string',
        default: '',
      },
      style: {
        type: 'string',
        default: 'primary',
      },
    },
    supports: {
      html: false,
      reusable: true,
    },

    edit: function (props) {
      var attrs = props.attributes;
      var currentStyle = STYLES.indexOf(attrs.style) !== -1 ? attrs.style : 'primary';
      var blockProps = useBlockProps();

      return el(wp.element.Fragment, null,
        el(InspectorControls, null,
          el(PanelBody, { title: __('Settings', 'enrove-folios'), initialOpen: true },
            el(SelectControl, {
              label: __('Style', 'enrove-folios'),
              value: currentStyle,
              options: STYLE_OPTIONS,
              onChange: function (val) {
                props.setAttributes({ style: val });
              },
            }),
            el(TextControl, {
              label: __('Button link', 'enrove-folios'),
              type: 'url',
              value: attrs.buttonUrl,
              placeholder: 'https://…',
              onChange: function (val) {
                props.setAttributes({ buttonUrl: val });
              },
            })
          )
        ),
        el('div', blockProps,
          el('div', { style: wrapStyle(currentStyle) },
            el(RichText, {
              tagName: 'div',
              style: headingStyle,
              value: attrs.heading,
              onChange: function (v) { props.setAttributes({ heading: v }); },
              placeholder: __('Ready to get started?', 'enrove-folios'),
              allowedFormats: [],
            }),
            el(RichText, {
              tagName: 'div',
              style: bodyStyle,
              value: attrs.body,
              onChange: function (v) { props.setAttributes({ body: v }); },
              placeholder: __('Add a short closing message…', 'enrove-folios'),
            }),
            el(RichText, {
              tagName: 'span',
              style: buttonStyle(currentStyle),
              value: attrs.buttonText,
              onChange: function (v) { props.setAttributes({ buttonText: v }); },
              placeholder: __('Schedule a call', 'enrove-folios'),
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
