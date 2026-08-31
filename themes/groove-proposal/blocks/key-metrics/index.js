(function (wp) {
  'use strict';

  if (!wp || !wp.blocks || !wp.element || !wp.blockEditor) {
    return;
  }

  var el = wp.element.createElement;
  var registerBlockType = wp.blocks.registerBlockType;
  var RichText = wp.blockEditor.RichText;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var BlockControls = wp.blockEditor.BlockControls;
  var ToolbarGroup = wp.components.ToolbarGroup;
  var ToolbarButton = wp.components.ToolbarButton;

  var DEFAULT_ITEMS = [
    { value: '150+', label: 'Projects delivered' },
    { value: '98%', label: 'Client satisfaction' },
    { value: '12', label: 'Years in market' },
  ];

  var MIN_ITEMS = 2;
  var MAX_ITEMS = 4;

  // ── Inline styles (reliable in the editor) ─────────────────────────────

  var gridStyle = {
    display: 'grid',
    gap: '2rem',
    padding: '1.75rem 1.5rem',
    border: '1px solid #d4d2cb',
    borderRadius: '0.5rem',
    background: '#fafaf8',
  };

  var itemStyle = {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.375rem',
    textAlign: 'center',
  };

  var valueStyle = {
    fontFamily: "'Fraunces', Georgia, serif",
    fontSize: '2.25rem',
    fontWeight: '300',
    fontStyle: 'italic',
    lineHeight: '1.1',
    letterSpacing: '-0.02em',
    color: '#181510',
    textAlign: 'center',
  };

  var labelStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.6875rem',
    fontWeight: '500',
    color: '#999690',
    textTransform: 'uppercase',
    letterSpacing: '0.06em',
    lineHeight: '1.4',
    textAlign: 'center',
  };

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M3 3h5v8H3V3zm7 0h4v5h-4V3zm6 0h5v6h-5V3zM3 13h5v8H3v-8zm7 7h4v-5h-4v5zm6-8h5v8h-5v-8z',
    })
  );

  function EditMetricItem(props) {
    var items = props.items;
    var index = props.index;
    var onChange = props.onChange;
    var item = items[index];

    return el('div', { style: itemStyle },
      el(RichText, {
        tagName: 'div',
        style: valueStyle,
        value: item.value,
        onChange: function (newValue) {
          var updated = items.slice();
          updated[index] = Object.assign({}, item, { value: newValue });
          onChange(updated);
        },
        placeholder: '0',
        allowedFormats: [],
      }),
      el(RichText, {
        tagName: 'div',
        style: labelStyle,
        value: item.label,
        onChange: function (newLabel) {
          var updated = items.slice();
          updated[index] = Object.assign({}, item, { label: newLabel });
          onChange(updated);
        },
        placeholder: 'Label',
        allowedFormats: [],
      })
    );
  }

  registerBlockType('groove-proposal/key-metrics', {
    title: 'Key Metrics',
    description: 'Large stat values with labels. Supports 2 to 4 items.',
    icon: BLOCK_ICON,
    category: 'groove-proposal',
    keywords: ['metrics', 'stats', 'numbers', 'kpi'],
    attributes: {
      items: {
        type: 'array',
        default: DEFAULT_ITEMS,
      },
    },
    supports: {
      html: false,
      reusable: true,
    },

    edit: function (props) {
      var items = props.attributes.items;
      var blockProps = useBlockProps();

      function setItems(updated) {
        props.setAttributes({ items: updated });
      }

      function addItem() {
        if (items.length >= MAX_ITEMS) return;
        setItems(items.concat([{ value: '0', label: 'Label' }]));
      }

      function removeItem() {
        if (items.length <= MIN_ITEMS) return;
        setItems(items.slice(0, -1));
      }

      var columns = 'repeat(' + items.length + ', 1fr)';
      var currentGridStyle = Object.assign({}, gridStyle, {
        gridTemplateColumns: columns,
      });

      return el(wp.element.Fragment, null,
        el(BlockControls, null,
          el(ToolbarGroup, null,
            el(ToolbarButton, {
              icon: 'plus-alt2',
              label: 'Add metric',
              onClick: addItem,
              disabled: items.length >= MAX_ITEMS,
            }),
            el(ToolbarButton, {
              icon: 'minus',
              label: 'Remove metric',
              onClick: removeItem,
              disabled: items.length <= MIN_ITEMS,
            })
          )
        ),
        el('div', blockProps,
          el('div', { style: currentGridStyle },
            items.map(function (item, i) {
              return el(EditMetricItem, {
                key: i,
                items: items,
                index: i,
                onChange: setItems,
              });
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
