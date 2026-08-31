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
    'Discovery workshop and stakeholder interviews',
    'Complete visual identity and brand guidelines',
    'Responsive website design and development',
    '30 days of post-launch support',
  ];

  var MIN_ITEMS = 1;
  var MAX_ITEMS = 12;

  // ── Inline styles ──────────────────────────────────────────────────────

  var wrapStyle = {
    margin: '1rem 0',
  };

  var headingStyle = {
    fontFamily: "'Fraunces', Georgia, serif",
    fontSize: '1.125rem',
    fontWeight: '400',
    fontStyle: 'normal',
    color: '#181510',
    lineHeight: '1.35',
    margin: '0 0 0.75rem',
  };

  var listStyle = {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.625rem',
  };

  var rowStyle = {
    display: 'flex',
    alignItems: 'flex-start',
    gap: '0.625rem',
  };

  var checkStyle = {
    flexShrink: '0',
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.8125rem',
    fontWeight: '700',
    color: '#2b6b78',
    lineHeight: '1.55',
  };

  var itemTextStyle = {
    flex: '1',
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.875rem',
    color: '#4f483e',
    lineHeight: '1.55',
  };

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M3 5.5l2 2 3.5-3.5L7.5 3 5 5.5 4 4.5 3 5.5zM10 5h11v2H10V5zm-7 7.5l2 2 3.5-3.5L7.5 10 5 12.5l-1-1-1 1zM10 12h11v2H10v-2zm-7 6.5l2 2 3.5-3.5L7.5 16 5 18.5l-1-1-1 1zM10 19h11v2H10v-2z',
    })
  );

  // ── Item row component ─────────────────────────────────────────────────

  function EditItemRow(props) {
    var items = props.items;
    var index = props.index;
    var onChange = props.onChange;
    var item = items[index];

    return el('div', { style: rowStyle },
      el('span', { style: checkStyle }, '✓'),
      el(RichText, {
        tagName: 'div',
        style: itemTextStyle,
        value: item,
        onChange: function (v) {
          var updated = items.slice();
          updated[index] = v;
          onChange(updated);
        },
        placeholder: 'Deliverable…',
      })
    );
  }

  // ── Block registration ────────────────────────────────────────────────

  registerBlockType('groove-proposal/deliverables', {
    title: 'Deliverables Checklist',
    description: 'A checklist of what’s included in this engagement.',
    icon: BLOCK_ICON,
    category: 'groove-proposal',
    keywords: ['deliverables', 'checklist', 'scope', 'included', 'list'],
    attributes: {
      heading: {
        type: 'string',
        default: '',
      },
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
      var attrs = props.attributes;
      var heading = attrs.heading;
      var items = attrs.items;
      var blockProps = useBlockProps();

      function setItems(updated) {
        props.setAttributes({ items: updated });
      }

      function addItem() {
        if (items.length >= MAX_ITEMS) return;
        setItems(items.concat(['']));
      }

      function removeItem() {
        if (items.length <= MIN_ITEMS) return;
        setItems(items.slice(0, -1));
      }

      return el(wp.element.Fragment, null,
        el(BlockControls, null,
          el(ToolbarGroup, null,
            el(ToolbarButton, {
              icon: 'plus-alt2',
              label: 'Add item',
              onClick: addItem,
              disabled: items.length >= MAX_ITEMS,
            }),
            el(ToolbarButton, {
              icon: 'minus',
              label: 'Remove item',
              onClick: removeItem,
              disabled: items.length <= MIN_ITEMS,
            })
          )
        ),
        el('div', blockProps,
          el('div', { style: wrapStyle },
            el(RichText, {
              tagName: 'div',
              style: headingStyle,
              value: heading,
              onChange: function (v) { props.setAttributes({ heading: v }); },
              placeholder: 'Heading (optional)',
              allowedFormats: [],
            }),
            el('div', { style: listStyle },
              items.map(function (item, i) {
                return el(EditItemRow, {
                  key: i,
                  items: items,
                  index: i,
                  onChange: setItems,
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
