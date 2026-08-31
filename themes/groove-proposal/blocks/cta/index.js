(function (wp) {
  'use strict';

  if (!wp || !wp.blocks || !wp.element || !wp.blockEditor) {
    return;
  }

  var el = wp.element.createElement;
  var registerBlockType = wp.blocks.registerBlockType;
  var RichText = wp.blockEditor.RichText;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var PanelBody = wp.components.PanelBody;
  var SelectControl = wp.components.SelectControl;
  var TextControl = wp.components.TextControl;

  var STYLES = ['primary', 'subtle'];

  var STYLE_OPTIONS = [
    { value: 'primary', label: 'Primary (solid)' },
    { value: 'subtle', label: 'Subtle (bordered)' },
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
        border: '1px solid #d4d2cb',
      });
    }
    return Object.assign({}, base, {
      background: 'rgba(43, 107, 120, 0.08)',
      border: 'none',
    });
  }

  var headingStyle = {
    fontFamily: "'Fraunces', Georgia, serif",
    fontSize: '1.75rem',
    fontWeight: '300',
    fontStyle: 'italic',
    letterSpacing: '-0.01em',
    color: '#181510',
    margin: '0 0 0.75rem',
  };

  var bodyStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.9375rem',
    color: '#999690',
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
        color: '#2b6b78',
        border: '1px solid #2b6b78',
      });
    }
    return Object.assign({}, base, {
      background: '#2b6b78',
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

  registerBlockType('groove-proposal/cta', {
    title: 'Closing CTA',
    description: 'A "ready to move forward" call-to-action for the end of a proposal.',
    icon: BLOCK_ICON,
    category: 'groove-proposal',
    keywords: ['cta', 'call to action', 'closing', 'next steps', 'button'],
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
          el(PanelBody, { title: 'Settings', initialOpen: true },
            el(SelectControl, {
              label: 'Style',
              value: currentStyle,
              options: STYLE_OPTIONS,
              onChange: function (val) {
                props.setAttributes({ style: val });
              },
            }),
            el(TextControl, {
              label: 'Button link',
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
              placeholder: 'Ready to get started?',
              allowedFormats: [],
            }),
            el(RichText, {
              tagName: 'div',
              style: bodyStyle,
              value: attrs.body,
              onChange: function (v) { props.setAttributes({ body: v }); },
              placeholder: 'Add a short closing message…',
            }),
            el(RichText, {
              tagName: 'span',
              style: buttonStyle(currentStyle),
              value: attrs.buttonText,
              onChange: function (v) { props.setAttributes({ buttonText: v }); },
              placeholder: 'Schedule a call',
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
