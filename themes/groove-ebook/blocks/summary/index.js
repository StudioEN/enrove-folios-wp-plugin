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

  var MIN_POINTS = 1;
  var MAX_POINTS = 8;

  var DEFAULT_POINTS = [
    'Skill moves out of the head and into the hands, and that takes repetition.',
    'Speed is not the opposite of care; hurry is.',
    'A deadline is a constraint, not a verdict on the work.',
  ];

  var wrapStyle = {
    margin: '2.5rem 0',
    padding: '1.375rem 1.5rem',
    borderTop: '1px solid #101517',
    borderBottom: '1px solid #E4E4E5',
  };

  var titleStyle = {
    fontFamily: FONT,
    fontSize: '0.75rem',
    fontWeight: '600',
    letterSpacing: '0.08em',
    textTransform: 'uppercase',
    color: '#3C434A',
    margin: '0 0 0.875rem',
  };

  var listStyle = {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.625rem',
  };

  var rowStyle = {
    display: 'flex',
    alignItems: 'baseline',
    gap: '0.75rem',
  };

  // A dash, not a number. Numbering these would claim an order the points do
  // not have — see the same decision in theme.css.
  var markerStyle = {
    flexShrink: '0',
    fontFamily: FONT,
    fontSize: '0.9375rem',
    color: '#8C8F94',
    lineHeight: '1.65',
  };

  var pointStyle = {
    flex: '1',
    fontFamily: FONT,
    fontSize: '0.9375rem',
    lineHeight: '1.65',
    color: '#101517',
  };

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M4 4h16v2H4V4zm2 5h12v1.6H6V9zm0 4h12v1.6H6V13zm0 4h8v1.6H6V17z',
    })
  );

  function EditPoint(props) {
    var points = props.points;
    var index = props.index;
    var onChange = props.onChange;

    return el('div', { style: rowStyle },
      el('span', { style: markerStyle, 'aria-hidden': 'true' }, '\u2014'),
      el(RichText, {
        tagName: 'div',
        style: pointStyle,
        value: points[index],
        onChange: function (v) {
          var updated = points.slice();
          updated[index] = v;
          onChange(updated);
        },
        placeholder: __('What the reader should leave with…', 'groove-folios'),
        allowedFormats: ['core/bold', 'core/italic'],
      })
    );
  }

  registerBlockType('groove-ebook/summary', {
    title: __('Chapter Summary', 'groove-folios'),
    description: __('The handful of things a chapter leaves the reader with.', 'groove-folios'),
    icon: BLOCK_ICON,
    category: 'groove-ebook',
    keywords: [__('summary', 'groove-folios'), __('takeaway', 'groove-folios'), __('recap', 'groove-folios'), __('chapter', 'groove-folios'), __('key points', 'groove-folios')],
    attributes: {
      title: {
        type: 'string',
        default: 'What this chapter argued',
      },
      points: {
        type: 'array',
        default: DEFAULT_POINTS,
      },
    },
    supports: {
      html: false,
      reusable: true,
    },

    edit: function (props) {
      var attrs = props.attributes;
      var points = attrs.points || [];
      var blockProps = useBlockProps();

      function setPoints(updated) {
        props.setAttributes({ points: updated });
      }

      return el(wp.element.Fragment, null,
        el(BlockControls, null,
          el(ToolbarGroup, null,
            el(ToolbarButton, {
              icon: 'plus-alt2',
              label: __('Add a point', 'groove-folios'),
              onClick: function () {
                if (points.length >= MAX_POINTS) return;
                setPoints(points.concat(['']));
              },
              disabled: points.length >= MAX_POINTS,
            }),
            el(ToolbarButton, {
              icon: 'minus',
              label: __('Remove the last point', 'groove-folios'),
              onClick: function () {
                if (points.length <= MIN_POINTS) return;
                setPoints(points.slice(0, -1));
              },
              disabled: points.length <= MIN_POINTS,
            })
          )
        ),
        el('div', blockProps,
          el('section', { style: wrapStyle },
            el(RichText, {
              tagName: 'div',
              style: titleStyle,
              value: attrs.title,
              onChange: function (v) { props.setAttributes({ title: v }); },
              placeholder: __('Heading (optional)', 'groove-folios'),
              allowedFormats: [],
            }),
            el('div', { style: listStyle },
              points.map(function (point, i) {
                return el(EditPoint, {
                  key: i,
                  points: points,
                  index: i,
                  onChange: setPoints,
                });
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
