(function (wp) {
  'use strict';

  if (!wp || !wp.blocks || !wp.element || !wp.blockEditor) {
    return;
  }

  var el = wp.element.createElement;
  var useState = wp.element.useState;
  var useCallback = wp.element.useCallback;
  var registerBlockType = wp.blocks.registerBlockType;
  var RichText = wp.blockEditor.RichText;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var BlockControls = wp.blockEditor.BlockControls;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var ToolbarGroup = wp.components.ToolbarGroup;
  var ToolbarButton = wp.components.ToolbarButton;
  var PanelBody = wp.components.PanelBody;
  var SelectControl = wp.components.SelectControl;

  var DEFAULT_ITEMS = [
    { text: 'They took a vague brief and turned it into a roadmap we actually trusted.', author: 'Priya Anand', role: 'COO, Nordlight Group' },
    { text: 'Communication was clear at every step, with no surprises and no scope creep.', author: 'Diego Fernandez', role: 'Head of Marketing, Vale & Co.' },
    { text: 'The final result exceeded what we thought was possible on this timeline.', author: 'Emily Zhou', role: 'Founder, Zhou Studio' },
  ];

  var MIN_ITEMS = 2;
  var MAX_ITEMS = 6;

  // ── Inline styles ──────────────────────────────────────────────────────

  var wrapStyle = {
    display: 'grid',
    gap: '1.25rem',
    margin: '1rem 0',
  };

  var cardStyle = {
    margin: '0',
    padding: '1.125rem 1.25rem',
    border: '1px solid #d6d5d0',
    borderRadius: '3px',
    background: '#f7f7f5',
    cursor: 'pointer',
    transition: 'background 150ms ease, box-shadow 150ms ease',
  };

  var ACTIVE_BG = '#ededeb';

  var textStyle = {
    margin: '0 0 0.625rem',
    padding: '0',
    border: 'none',
    fontFamily: "'Fraunces', Georgia, serif",
    fontSize: '0.9375rem',
    fontWeight: '300',
    fontStyle: 'italic',
    lineHeight: '1.5',
    color: '#1b1a18',
  };

  var citeStyle = {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.1rem',
  };

  var authorStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.75rem',
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
      d: 'M4 5h6v6H4V5zm10 0h6v6h-6V5zM4 15h6v4H4v-4zm10 0h6v4h-6v-4z',
    })
  );

  // ── Card component ─────────────────────────────────────────────────────

  function EditCard(props) {
    var items = props.items;
    var index = props.index;
    var onChange = props.onChange;
    var isActive = props.isActive;
    var onFocusCard = props.onFocusCard;
    var item = items[index];

    function update(field, val) {
      var updated = items.slice();
      updated[index] = Object.assign({}, item);
      updated[index][field] = val;
      onChange(updated);
    }

    var currentCardStyle = Object.assign({}, cardStyle);
    if (isActive) {
      currentCardStyle.background = ACTIVE_BG;
      currentCardStyle.boxShadow = 'inset 0 3px 0 #27498c';
    }

    return el('figure', {
        style: currentCardStyle,
        onMouseDownCapture: function () { onFocusCard(index); },
        onFocusCapture: function () { onFocusCard(index); },
      },
      el(RichText, {
        tagName: 'blockquote',
        style: textStyle,
        value: item.text,
        onChange: function (v) { update('text', v); },
        placeholder: 'Enter testimonial…',
      }),
      el('figcaption', { style: citeStyle },
        el(RichText, {
          tagName: 'div',
          style: authorStyle,
          value: item.author,
          onChange: function (v) { update('author', v); },
          placeholder: 'Author name',
          allowedFormats: [],
        }),
        el(RichText, {
          tagName: 'div',
          style: roleStyle,
          value: item.role,
          onChange: function (v) { update('role', v); },
          placeholder: 'Role, Company',
          allowedFormats: [],
        })
      )
    );
  }

  // ── Block registration ────────────────────────────────────────────────

  registerBlockType('groove-proposal/testimonial-grid', {
    title: 'Testimonial Grid',
    description: 'A compact grid of short client quotes for social proof.',
    icon: BLOCK_ICON,
    category: 'groove-proposal',
    keywords: ['testimonials', 'quotes', 'reviews', 'social proof', 'clients'],
    attributes: {
      items: {
        type: 'array',
        default: DEFAULT_ITEMS,
      },
      columns: {
        type: 'number',
        default: 3,
      },
    },
    supports: {
      html: false,
      reusable: true,
    },

    edit: function (props) {
      var attrs = props.attributes;
      var items = attrs.items;
      var columns = attrs.columns;
      var blockProps = useBlockProps();

      var activeState = useState(-1);
      var activeIndex = activeState[0];
      var setActiveIndex = activeState[1];

      var onFocusCard = useCallback(function (i) {
        setActiveIndex(i);
      }, []);

      function setItems(updated) {
        props.setAttributes({ items: updated });
      }

      function addItem() {
        if (items.length >= MAX_ITEMS) return;
        var newItem = { text: '', author: '', role: '' };
        if (activeIndex >= 0 && activeIndex < items.length) {
          var updated = items.slice();
          updated.splice(activeIndex, 0, newItem);
          setItems(updated);
          setActiveIndex(activeIndex + 1);
        } else {
          setItems(items.concat([newItem]));
        }
      }

      function removeItem() {
        if (items.length <= MIN_ITEMS) return;
        if (activeIndex >= 0 && activeIndex < items.length) {
          var updated = items.slice();
          updated.splice(activeIndex, 1);
          setItems(updated);
          if (activeIndex >= updated.length) {
            setActiveIndex(updated.length > 0 ? updated.length - 1 : -1);
          }
        } else {
          setItems(items.slice(0, -1));
        }
      }

      var currentWrapStyle = Object.assign({}, wrapStyle, {
        gridTemplateColumns: 'repeat(' + columns + ', 1fr)',
      });

      return el(wp.element.Fragment, null,
        el(InspectorControls, null,
          el(PanelBody, { title: 'Layout', initialOpen: true },
            el(SelectControl, {
              label: 'Columns',
              value: String(columns),
              options: [
                { value: '2', label: '2 columns' },
                { value: '3', label: '3 columns' },
              ],
              onChange: function (val) {
                props.setAttributes({ columns: parseInt(val, 10) });
              },
            })
          )
        ),
        el(BlockControls, null,
          el(ToolbarGroup, null,
            el(ToolbarButton, {
              icon: 'plus-alt2',
              label: activeIndex >= 0 ? 'Insert testimonial before selected' : 'Add testimonial',
              onClick: addItem,
              disabled: items.length >= MAX_ITEMS,
            }),
            el(ToolbarButton, {
              icon: 'minus',
              label: activeIndex >= 0 ? 'Remove selected testimonial' : 'Remove last testimonial',
              onClick: removeItem,
              disabled: items.length <= MIN_ITEMS,
            })
          )
        ),
        el('div', blockProps,
          el('div', { style: currentWrapStyle },
            items.map(function (item, i) {
              return el(EditCard, {
                key: i,
                items: items,
                index: i,
                onChange: setItems,
                isActive: activeIndex === i,
                onFocusCard: onFocusCard,
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
