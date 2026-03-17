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

  var DEFAULT_ROWS = [
    { name: 'Discovery & research', desc: 'Stakeholder interviews, competitive audit, user research', price: 12000, optional: false },
    { name: 'Strategy & planning', desc: 'Roadmap, information architecture, content strategy', price: 18000, optional: false },
    { name: 'Design & prototyping', desc: 'Visual design, interactive prototype, design system', price: 24000, optional: false },
    { name: 'Development support', desc: 'Front-end implementation oversight and QA', price: 8000, optional: true },
  ];

  var MIN_ROWS = 1;
  var MAX_ROWS = 12;

  var CURRENCY_OPTIONS = [
    { value: 'USD', label: 'USD — US Dollar ($)', locale: 'en-US' },
    { value: 'AUD', label: 'AUD — Australian Dollar (A$)', locale: 'en-AU' },
    { value: 'BRL', label: 'BRL — Brazilian Real (R$)', locale: 'pt-BR' },
    { value: 'CAD', label: 'CAD — Canadian Dollar (CA$)', locale: 'en-CA' },
    { value: 'CHF', label: 'CHF — Swiss Franc (CHF)', locale: 'de-CH' },
    { value: 'CNY', label: 'CNY — Chinese Yuan (¥)', locale: 'zh-CN' },
    { value: 'DKK', label: 'DKK — Danish Krone (kr)', locale: 'da-DK' },
    { value: 'EUR', label: 'EUR — Euro (€)', locale: 'de-DE' },
    { value: 'GBP', label: 'GBP — British Pound (£)', locale: 'en-GB' },
    { value: 'HKD', label: 'HKD — Hong Kong Dollar (HK$)', locale: 'en-HK' },
    { value: 'INR', label: 'INR — Indian Rupee (₹)', locale: 'en-IN' },
    { value: 'JPY', label: 'JPY — Japanese Yen (¥)', locale: 'ja-JP' },
    { value: 'MXN', label: 'MXN — Mexican Peso (MX$)', locale: 'es-MX' },
    { value: 'NOK', label: 'NOK — Norwegian Krone (kr)', locale: 'nb-NO' },
    { value: 'NZD', label: 'NZD — New Zealand Dollar (NZ$)', locale: 'en-NZ' },
    { value: 'SEK', label: 'SEK — Swedish Krona (kr)', locale: 'sv-SE' },
    { value: 'SGD', label: 'SGD — Singapore Dollar (S$)', locale: 'en-SG' },
  ];

  function localeForCurrency(code) {
    for (var i = 0; i < CURRENCY_OPTIONS.length; i++) {
      if (CURRENCY_OPTIONS[i].value === code) return CURRENCY_OPTIONS[i].locale;
    }
    return 'en-US';
  }

  // ── Helpers ────────────────────────────────────────────────────────────

  function formatCurrency(cents, currency, locale) {
    if (typeof Intl !== 'undefined' && Intl.NumberFormat) {
      try {
        return new Intl.NumberFormat(locale || 'en-US', {
          style: 'currency',
          currency: currency || 'USD',
          minimumFractionDigits: 0,
          maximumFractionDigits: 0,
        }).format(cents);
      } catch (e) { /* fall through */ }
    }
    return '$' + cents.toLocaleString();
  }

  function computeTotal(rows, includeOptional) {
    var sum = 0;
    for (var i = 0; i < rows.length; i++) {
      if (!rows[i].optional || includeOptional) {
        sum += (rows[i].price || 0);
      }
    }
    return sum;
  }

  // ── Inline styles ──────────────────────────────────────────────────────

  var wrapStyle = {
    margin: '1rem 0',
    border: '1px solid #d4d2cb',
    borderRadius: '3px',
    overflow: 'hidden',
    background: '#fff',
  };

  var headerStyle = {
    display: 'flex',
    justifyContent: 'space-between',
    padding: '0.6rem 1rem',
    background: '#f0efeb',
  };

  var colLabelStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.5625rem',
    fontWeight: '600',
    color: '#6b6860',
    textTransform: 'uppercase',
    letterSpacing: '0.09em',
  };

  var rowBaseStyle = {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'baseline',
    gap: '1.5rem',
    padding: '0.875rem 1rem',
    borderTop: '1px solid #d4d2cb',
    cursor: 'pointer',
    transition: 'background 150ms ease, box-shadow 150ms ease',
  };

  var ACTIVE_BG = '#f5f4f0';

  var itemStyle = {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.15rem',
    minWidth: '0',
    flex: '1',
  };

  var nameStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.875rem',
    fontWeight: '500',
    color: '#181510',
    lineHeight: '1.35',
  };

  var descStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.75rem',
    color: '#999690',
    lineHeight: '1.45',
  };

  var priceWrapStyle = {
    display: 'flex',
    alignItems: 'baseline',
    gap: '0.5rem',
    flexShrink: '0',
  };

  var priceInputStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.875rem',
    fontWeight: '500',
    color: '#181510',
    whiteSpace: 'nowrap',
    width: '6rem',
    textAlign: 'right',
    border: '1px solid transparent',
    borderRadius: '3px',
    padding: '0.15rem 0.35rem',
    background: 'transparent',
    outline: 'none',
    cursor: 'pointer',
  };

  var priceInputFocusStyle = Object.assign({}, priceInputStyle, {
    borderColor: '#d4d2cb',
    background: '#fafaf8',
    cursor: 'text',
  });

  var optionalBtnStyle = {
    fontSize: '0.625rem',
    color: '#999690',
    background: 'none',
    border: '1px solid #d4d2cb',
    borderRadius: '3px',
    padding: '0.1rem 0.35rem',
    cursor: 'pointer',
    lineHeight: '1.4',
    whiteSpace: 'nowrap',
  };

  var totalRowStyle = {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'baseline',
    padding: '0.875rem 1rem',
    borderTop: '2px solid #181510',
    background: '#f0efeb',
  };

  var totalLabelStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.75rem',
    fontWeight: '600',
    color: '#181510',
    textTransform: 'uppercase',
    letterSpacing: '0.06em',
  };

  var totalValueStyle = {
    fontFamily: "'Fraunces', Georgia, serif",
    fontSize: '1.25rem',
    fontWeight: '400',
    fontStyle: 'italic',
    color: '#181510',
    letterSpacing: '-0.01em',
  };

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V6h16v12zM6 10h2v2H6zm0 4h2v2H6zm4-4h8v2h-8zm0 4h8v2h-8z',
    })
  );

  // ── Price input component ──────────────────────────────────────────────

  function PriceInput(props) {
    var value = props.value;
    var onCommit = props.onCommit;
    var currency = props.currency;
    var locale = props.locale;

    var editState = useState({ editing: false, draft: '' });
    var state = editState[0];
    var setState = editState[1];

    if (state.editing) {
      return el('input', {
        type: 'text',
        style: priceInputFocusStyle,
        value: state.draft,
        autoFocus: true,
        onChange: function (e) {
          setState({ editing: true, draft: e.target.value });
        },
        onBlur: function () {
          var raw = state.draft.replace(/[^0-9.-]/g, '');
          var num = parseInt(raw, 10);
          onCommit(isNaN(num) ? 0 : num);
          setState({ editing: false, draft: '' });
        },
        onKeyDown: function (e) {
          if (e.key === 'Enter') { e.target.blur(); }
          if (e.key === 'Escape') { setState({ editing: false, draft: '' }); }
        },
      });
    }

    return el('span', {
      style: priceInputStyle,
      role: 'button',
      tabIndex: 0,
      onClick: function () { setState({ editing: true, draft: String(value) }); },
      onKeyDown: function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          setState({ editing: true, draft: String(value) });
        }
      },
    }, formatCurrency(value, currency, locale));
  }

  // ── Row component ─────────────────────────────────────────────────────

  function EditRow(props) {
    var rows = props.rows;
    var index = props.index;
    var onChange = props.onChange;
    var currency = props.currency;
    var locale = props.locale;
    var isActive = props.isActive;
    var onFocusRow = props.onFocusRow;
    var row = rows[index];

    function update(field, val) {
      var updated = rows.slice();
      updated[index] = Object.assign({}, row);
      updated[index][field] = val;
      onChange(updated);
    }

    var currentRowStyle = Object.assign({}, rowBaseStyle);
    if (row.optional) {
      currentRowStyle.opacity = '0.6';
      currentRowStyle.borderTopStyle = 'dashed';
    }
    if (isActive) {
      currentRowStyle.background = ACTIVE_BG;
      currentRowStyle.boxShadow = 'inset 3px 0 0 #2b6b78';
    }

    return el('div', {
        style: currentRowStyle,
        onMouseDownCapture: function () { onFocusRow(index); },
        onFocusCapture: function () { onFocusRow(index); },
      },
      el('div', { style: itemStyle },
        el(RichText, {
          tagName: 'div',
          style: nameStyle,
          value: row.name,
          onChange: function (v) { update('name', v); },
          placeholder: 'Line item',
          allowedFormats: [],
        }),
        el(RichText, {
          tagName: 'div',
          style: descStyle,
          value: row.desc,
          onChange: function (v) { update('desc', v); },
          placeholder: 'Description',
          allowedFormats: [],
        })
      ),
      el('div', { style: priceWrapStyle },
        el(PriceInput, {
          value: row.price,
          onCommit: function (v) { update('price', v); },
          currency: currency,
          locale: locale,
        }),
        el('button', {
          type: 'button',
          style: Object.assign({}, optionalBtnStyle, row.optional ? { color: '#2b6b78', borderColor: '#2b6b78' } : {}),
          onClick: function (e) {
            e.stopPropagation();
            update('optional', !row.optional);
          },
          title: row.optional ? 'Mark as required' : 'Mark as optional',
        }, row.optional ? 'OPT' : 'REQ')
      )
    );
  }

  // ── Block registration ────────────────────────────────────────────────

  registerBlockType('groove-proposal/pricing-table', {
    title: 'Pricing / Investment Table',
    description: 'Scope and investment breakdown with auto-calculated total.',
    icon: BLOCK_ICON,
    category: 'groove-proposal',
    keywords: ['pricing', 'investment', 'table', 'cost', 'budget', 'proposal'],
    attributes: {
      rows: {
        type: 'array',
        default: DEFAULT_ROWS,
      },
      includeOptional: {
        type: 'boolean',
        default: true,
      },
      currency: {
        type: 'string',
        default: 'USD',
      },
      locale: {
        type: 'string',
        default: 'en-US',
      },
    },
    supports: {
      html: false,
      reusable: true,
    },

    edit: function (props) {
      var attrs = props.attributes;
      var rows = attrs.rows;
      var includeOptional = attrs.includeOptional;
      var currency = attrs.currency;
      var locale = attrs.locale;
      var blockProps = useBlockProps();
      var total = computeTotal(rows, includeOptional);

      var activeState = useState(-1);
      var activeIndex = activeState[0];
      var setActiveIndex = activeState[1];

      var onFocusRow = useCallback(function (i) {
        setActiveIndex(i);
      }, []);

      function setRows(updated) {
        props.setAttributes({ rows: updated });
      }

      function addRow() {
        if (rows.length >= MAX_ROWS) return;
        var newRow = { name: '', desc: '', price: 0, optional: false };
        if (activeIndex >= 0 && activeIndex < rows.length) {
          // Insert before the active row
          var updated = rows.slice();
          updated.splice(activeIndex, 0, newRow);
          setRows(updated);
          // Shift active index to keep the same row selected
          setActiveIndex(activeIndex + 1);
        } else {
          setRows(rows.concat([newRow]));
        }
      }

      function removeRow() {
        if (rows.length <= MIN_ROWS) return;
        if (activeIndex >= 0 && activeIndex < rows.length) {
          // Remove the active row
          var updated = rows.slice();
          updated.splice(activeIndex, 1);
          setRows(updated);
          // Adjust active index
          if (activeIndex >= updated.length) {
            setActiveIndex(updated.length > 0 ? updated.length - 1 : -1);
          }
        } else {
          // No selection — remove last row
          setRows(rows.slice(0, -1));
        }
      }

      return el(wp.element.Fragment, null,
        el(InspectorControls, null,
          el(PanelBody, { title: 'Currency', initialOpen: true },
            el(SelectControl, {
              label: 'Currency',
              value: currency,
              options: CURRENCY_OPTIONS,
              onChange: function (val) {
                props.setAttributes({
                  currency: val,
                  locale: localeForCurrency(val),
                });
              },
            })
          )
        ),
        el(BlockControls, null,
          el(ToolbarGroup, null,
            el(ToolbarButton, {
              icon: 'plus-alt2',
              label: activeIndex >= 0 ? 'Insert row before selected' : 'Add row',
              onClick: addRow,
              disabled: rows.length >= MAX_ROWS,
            }),
            el(ToolbarButton, {
              icon: 'minus',
              label: activeIndex >= 0 ? 'Remove selected row' : 'Remove last row',
              onClick: removeRow,
              disabled: rows.length <= MIN_ROWS,
            })
          )
        ),
        el('div', blockProps,
          el('div', { style: wrapStyle },
            // Header
            el('div', { style: headerStyle },
              el('span', { style: colLabelStyle }, 'Scope'),
              el('span', { style: Object.assign({}, colLabelStyle, { textAlign: 'right' }) }, 'Investment')
            ),
            // Rows
            rows.map(function (row, i) {
              return el(EditRow, {
                key: i,
                rows: rows,
                index: i,
                onChange: setRows,
                currency: currency,
                locale: locale,
                isActive: activeIndex === i,
                onFocusRow: onFocusRow,
              });
            }),
            // Total
            el('div', { style: totalRowStyle },
              el('span', { style: totalLabelStyle }, 'Total'),
              el('span', { style: totalValueStyle }, formatCurrency(total, currency, locale))
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
