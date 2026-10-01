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
  var BlockControls = wp.blockEditor.BlockControls;
  var ToolbarGroup = wp.components.ToolbarGroup;
  var ToolbarButton = wp.components.ToolbarButton;

  var FONT = "'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";

  function wrapStyle(align) {
    return {
      margin: align === 'inset' ? '2rem 0 2rem 2rem' : '2.5rem 0',
      maxWidth: align === 'inset' ? '22rem' : 'none',
      paddingTop: '1.25rem',
      borderTop: '2px solid #52B107',
    };
  }

  var textStyle = {
    fontFamily: FONT,
    fontSize: '1.5rem',
    fontWeight: '500',
    lineHeight: '1.35',
    letterSpacing: '-0.01em',
    color: '#101517',
    margin: '0',
    padding: '0',
    border: 'none',
  };

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M3 5h18v2H3V5zm2 5h14v3H5v-3zm0 5h14v3H5v-3z',
    })
  );

  registerBlockType('groove-ebook/pull-quote', {
    title: __('Pull Quote', 'groove-folios'),
    description: __('A line lifted out of your own prose to break a long column.', 'groove-folios'),
    icon: BLOCK_ICON,
    category: 'groove-ebook',
    keywords: [__('pull', 'groove-folios'), __('quote', 'groove-folios'), __('lift', 'groove-folios'), __('display', 'groove-folios'), __('break', 'groove-folios')],
    attributes: {
      text: {
        type: 'string',
        default: 'Almost nothing I am proud of was made quickly.',
      },
      align: {
        type: 'string',
        default: 'full',
      },
    },
    supports: {
      html: false,
      reusable: true,
    },

    edit: function (props) {
      var attrs = props.attributes;
      var align = attrs.align === 'inset' ? 'inset' : 'full';
      var blockProps = useBlockProps();

      return el(wp.element.Fragment, null,
        el(BlockControls, null,
          el(ToolbarGroup, null,
            el(ToolbarButton, {
              icon: 'editor-justify',
              label: __('Full column width', 'groove-folios'),
              isPressed: align === 'full',
              onClick: function () { props.setAttributes({ align: 'full' }); },
            }),
            el(ToolbarButton, {
              icon: 'align-pull-right',
              label: __('Inset, beside the text', 'groove-folios'),
              isPressed: align === 'inset',
              onClick: function () { props.setAttributes({ align: 'inset' }); },
            })
          )
        ),
        el('div', blockProps,
          el('figure', { style: wrapStyle(align) },
            el(RichText, {
              tagName: 'div',
              style: textStyle,
              value: attrs.text,
              onChange: function (v) { props.setAttributes({ text: v }); },
              placeholder: __('The line worth repeating…', 'groove-folios'),
              allowedFormats: ['core/italic'],
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
