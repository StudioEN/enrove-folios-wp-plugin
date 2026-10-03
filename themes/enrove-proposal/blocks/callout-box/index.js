(function (wp) {
  'use strict';

  if (!wp || !wp.blocks || !wp.element || !wp.blockEditor || !wp.i18n) {
    return;
  }

  var el = wp.element.createElement;
  var __ = wp.i18n.__;
  var useState = wp.element.useState;
  var useRef = wp.element.useRef;
  var useEffect = wp.element.useEffect;
  var registerBlockType = wp.blocks.registerBlockType;
  var RichText = wp.blockEditor.RichText;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var BlockControls = wp.blockEditor.BlockControls;
  var ToolbarGroup = wp.components.ToolbarGroup;
  var ToolbarButton = wp.components.ToolbarButton;

  var STYLES = ['note', 'tip', 'important', 'warning'];

  var STYLE_META = {
    note:      { label: __('Note', 'enrove-folios'),      color: '#27498c', icon: 'info-outline' },
    tip:       { label: __('Tip', 'enrove-folios'),       color: '#3a7d44', icon: 'lightbulb' },
    important: { label: __('Important', 'enrove-folios'), color: '#855c22', icon: 'star-filled' },
    warning:   { label: __('Warning', 'enrove-folios'),   color: '#c0392b', icon: 'warning' },
  };

  // ── Inline styles ──────────────────────────────────────────────────────────

  function wrapStyle(color) {
    return {
      margin: '2rem 0',
      border: '1px solid #d6d5d0',
      borderLeft: '3px solid ' + color,
      borderRadius: '3px',
      background: '#f7f7f5',
      padding: '1rem 1.25rem',
    };
  }

  var labelRowStyle = {
    display: 'flex',
    alignItems: 'center',
    gap: '0.35rem',
    marginBottom: '0.5rem',
    position: 'relative',
  };

  var labelTextStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.625rem',
    fontWeight: '600',
    textTransform: 'uppercase',
    letterSpacing: '0.09em',
    lineHeight: '1',
    cursor: 'pointer',
    userSelect: 'none',
  };

  var labelChevronStyle = {
    fontSize: '0.625rem',
    lineHeight: '1',
    cursor: 'pointer',
    userSelect: 'none',
    opacity: '0.6',
  };

  var dropdownStyle = {
    position: 'absolute',
    top: '100%',
    left: '0',
    marginTop: '0.35rem',
    background: '#fff',
    border: '1px solid #d6d5d0',
    borderRadius: '3px',
    boxShadow: '0 2px 8px rgba(0,0,0,0.1)',
    zIndex: '10',
    minWidth: '130px',
    overflow: 'hidden',
  };

  var dropdownItemStyle = {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    padding: '0.5rem 0.75rem',
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.75rem',
    fontWeight: '500',
    cursor: 'pointer',
    border: 'none',
    background: 'transparent',
    width: '100%',
    textAlign: 'left',
    transition: 'background 100ms ease',
  };

  var dropdownDotStyle = {
    width: '8px',
    height: '8px',
    borderRadius: '50%',
    flexShrink: '0',
  };

  var titleStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.9375rem',
    fontWeight: '600',
    color: '#1b1a18',
    lineHeight: '1.35',
    marginBottom: '0.35rem',
  };

  var bodyStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.8125rem',
    color: '#4a4844',
    lineHeight: '1.65',
  };

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z',
    })
  );

  // ── Inline dropdown component ──────────────────────────────────────────────

  function StyleDropdown(props) {
    var currentStyle = props.currentStyle;
    var onChange = props.onChange;
    var openState = useState(false);
    var isOpen = openState[0];
    var setIsOpen = openState[1];
    var ref = useRef(null);
    var meta = STYLE_META[currentStyle];

    // Close on outside click
    useEffect(function () {
      if (!isOpen) return;
      function handleClick(e) {
        if (ref.current && !ref.current.contains(e.target)) {
          setIsOpen(false);
        }
      }
      document.addEventListener('mousedown', handleClick);
      return function () {
        document.removeEventListener('mousedown', handleClick);
      };
    }, [isOpen]);

    return el('div', { style: labelRowStyle, ref: ref },
      el('span', {
        style: Object.assign({}, labelTextStyle, { color: meta.color }),
        onClick: function () { setIsOpen(!isOpen); },
      }, meta.label),
      el('span', {
        style: Object.assign({}, labelChevronStyle, { color: meta.color }),
        onClick: function () { setIsOpen(!isOpen); },
      }, isOpen ? '\u25B2' : '\u25BC'),
      isOpen ? el('div', { style: dropdownStyle },
        STYLES.map(function (key) {
          var m = STYLE_META[key];
          var isActive = key === currentStyle;
          return el('button', {
            key: key,
            style: Object.assign({}, dropdownItemStyle, {
              background: isActive ? '#ededeb' : 'transparent',
              fontWeight: isActive ? '600' : '500',
              color: '#1b1a18',
            }),
            onMouseEnter: function (e) {
              if (!isActive) e.currentTarget.style.background = '#f7f7f5';
            },
            onMouseLeave: function (e) {
              e.currentTarget.style.background = isActive ? '#ededeb' : 'transparent';
            },
            onClick: function () {
              onChange(key);
              setIsOpen(false);
            },
          },
            el('span', { style: Object.assign({}, dropdownDotStyle, { background: m.color }) }),
            m.label
          );
        })
      ) : null
    );
  }

  // ── Block registration ──────────────────────────────────────────────────────

  registerBlockType('enrove-proposal/callout-box', {
    title: __('Callout Box', 'enrove-folios'),
    description: __('Bordered highlight for assumptions, key takeaways, or important notes.', 'enrove-folios'),
    icon: BLOCK_ICON,
    category: 'enrove-proposal',
    keywords: [__('callout', 'enrove-folios'), __('note', 'enrove-folios'), __('tip', 'enrove-folios'), __('important', 'enrove-folios'), __('warning', 'enrove-folios'), __('highlight', 'enrove-folios'), __('assumption', 'enrove-folios')],
    attributes: {
      title: {
        type: 'string',
        default: '',
      },
      body: {
        type: 'string',
        default: 'Add your callout content here.',
      },
      style: {
        type: 'string',
        default: 'note',
      },
    },
    supports: {
      html: false,
      reusable: true,
    },

    edit: function (props) {
      var attrs = props.attributes;
      var currentStyle = attrs.style || 'note';
      var meta = STYLE_META[currentStyle];
      var blockProps = useBlockProps();

      function setStyle(key) {
        props.setAttributes({ style: key });
      }

      return el(wp.element.Fragment, null,
        // Toolbar dropdown
        el(BlockControls, null,
          el(ToolbarGroup, null,
            STYLES.map(function (key) {
              var m = STYLE_META[key];
              var isActive = key === currentStyle;
              return el(ToolbarButton, {
                key: key,
                icon: m.icon,
                label: m.label,
                isPressed: isActive,
                onClick: function () { setStyle(key); },
              });
            })
          )
        ),
        // Block content
        el('div', blockProps,
          el('div', { style: wrapStyle(meta.color) },
            // Inline style selector
            el(StyleDropdown, {
              currentStyle: currentStyle,
              onChange: setStyle,
            }),
            el(RichText, {
              tagName: 'div',
              style: titleStyle,
              value: attrs.title,
              onChange: function (v) { props.setAttributes({ title: v }); },
              placeholder: __('Callout title (optional)', 'enrove-folios'),
              allowedFormats: [],
            }),
            el(RichText, {
              tagName: 'div',
              style: bodyStyle,
              value: attrs.body,
              onChange: function (v) { props.setAttributes({ body: v }); },
              placeholder: __('Callout content\u2026', 'enrove-folios'),
              allowedFormats: ['core/bold', 'core/italic'],
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
