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
  var MediaUpload = wp.blockEditor.MediaUpload;
  var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
  var BlockControls = wp.blockEditor.BlockControls;
  var ToolbarGroup = wp.components.ToolbarGroup;
  var ToolbarButton = wp.components.ToolbarButton;
  var Button = wp.components.Button;

  var FONT = "'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";

  var wrapStyle = {
    margin: '2.5rem 0',
  };

  var imageStyle = {
    display: 'block',
    width: '100%',
    height: 'auto',
    cursor: 'pointer',
  };

  var placeholderStyle = {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: '11rem',
    background: '#F6F7F7',
    border: '1px dashed #C9CACC',
    borderRadius: '2px',
  };

  var captionRowStyle = {
    display: 'flex',
    flexWrap: 'wrap',
    alignItems: 'baseline',
    gap: '0.5rem',
    marginTop: '0.625rem',
  };

  var labelStyle = {
    fontFamily: FONT,
    fontSize: '0.75rem',
    fontWeight: '600',
    letterSpacing: '0.06em',
    textTransform: 'uppercase',
    color: '#3C434A',
    whiteSpace: 'nowrap',
  };

  var captionStyle = {
    flex: '1 1 12rem',
    fontFamily: FONT,
    fontSize: '0.8125rem',
    lineHeight: '1.55',
    color: '#3C434A',
  };

  var creditStyle = {
    fontFamily: FONT,
    fontSize: '0.75rem',
    lineHeight: '1.55',
    color: '#3C434A',
    fontStyle: 'italic',
    marginTop: '0.25rem',
    width: '100%',
  };

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M3 4h18v12H3V4zm2 2v8h14V6H5zm-2 12h18v2H3v-2z',
    })
  );

  registerBlockType('enrove-ebook/plate', {
    title: __('Plate', 'enrove-folios'),
    description: __('A numbered figure with a caption and a credit line.', 'enrove-folios'),
    icon: BLOCK_ICON,
    category: 'enrove-ebook',
    keywords: [__('plate', 'enrove-folios'), __('figure', 'enrove-folios'), __('image', 'enrove-folios'), __('illustration', 'enrove-folios'), __('caption', 'enrove-folios'), __('credit', 'enrove-folios')],
    attributes: {
      url: { type: 'string', default: '' },
      id: { type: 'number' },
      alt: { type: 'string', default: '' },
      label: { type: 'string', default: 'Fig. 1' },
      caption: { type: 'string', default: '' },
      credit: { type: 'string', default: '' },
      bleed: { type: 'boolean', default: false },
    },
    supports: {
      html: false,
      reusable: false,
    },

    edit: function (props) {
      var attrs = props.attributes;
      var blockProps = useBlockProps();

      function onSelect(media) {
        if (!media || !media.url) {
          return;
        }
        props.setAttributes({
          url: media.url,
          id: media.id,
          // Only adopt the library's alt text when the block has none of its
          // own, so a hand-written description survives a picture swap.
          alt: attrs.alt || media.alt || '',
        });
      }

      var picker = el(MediaUploadCheck, null,
        el(MediaUpload, {
          onSelect: onSelect,
          allowedTypes: ['image'],
          value: attrs.id,
          render: function (renderProps) {
            if (attrs.url) {
              return el('img', {
                src: attrs.url,
                alt: attrs.alt || '',
                style: imageStyle,
                onClick: renderProps.open,
              });
            }
            return el('div', { style: placeholderStyle },
              el(Button, { variant: 'secondary', onClick: renderProps.open }, __('Choose a picture', 'enrove-folios'))
            );
          },
        })
      );

      return el(wp.element.Fragment, null,
        el(BlockControls, null,
          el(ToolbarGroup, null,
            el(ToolbarButton, {
              icon: 'align-full-width',
              label: __('Bleed to the edge of the page', 'enrove-folios'),
              isPressed: !!attrs.bleed,
              onClick: function () { props.setAttributes({ bleed: !attrs.bleed }); },
            })
          )
        ),
        el('div', blockProps,
          el('figure', { style: wrapStyle },
            picker,
            el('div', { style: captionRowStyle },
              el(RichText, {
                tagName: 'span',
                style: labelStyle,
                value: attrs.label,
                onChange: function (v) { props.setAttributes({ label: v }); },
                placeholder: __('Fig. 1', 'enrove-folios'),
                allowedFormats: [],
              }),
              el(RichText, {
                tagName: 'span',
                style: captionStyle,
                value: attrs.caption,
                onChange: function (v) { props.setAttributes({ caption: v }); },
                placeholder: __('What the picture shows…', 'enrove-folios'),
                allowedFormats: ['core/italic', 'core/link'],
              }),
              el(RichText, {
                tagName: 'div',
                style: creditStyle,
                value: attrs.credit,
                onChange: function (v) { props.setAttributes({ credit: v }); },
                placeholder: __('Credit (optional)', 'enrove-folios'),
                allowedFormats: ['core/link'],
              })
            )
          )
        )
      );
    },

    save: function () {
      return null;
    },
  });
})(window.wp);
