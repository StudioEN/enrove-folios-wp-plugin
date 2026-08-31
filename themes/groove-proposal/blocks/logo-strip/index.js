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
  var MediaUpload = wp.blockEditor.MediaUpload;
  var ToolbarGroup = wp.components.ToolbarGroup;
  var ToolbarButton = wp.components.ToolbarButton;

  var DEFAULT_LOGOS = [
    { url: '', name: '', link: '' },
    { url: '', name: '', link: '' },
    { url: '', name: '', link: '' },
    { url: '', name: '', link: '' },
  ];

  var MIN_LOGOS = 1;
  var MAX_LOGOS = 8;

  // ── Inline styles ──────────────────────────────────────────────────────

  var headingStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.6875rem',
    fontWeight: '600',
    color: '#999690',
    textTransform: 'uppercase',
    letterSpacing: '0.09em',
    textAlign: 'center',
    margin: '0 0 1.25rem',
  };

  var rowStyle = {
    display: 'flex',
    flexWrap: 'wrap',
    alignItems: 'flex-start',
    justifyContent: 'center',
    gap: '1.5rem',
    margin: '1rem 0',
  };

  var itemWrapStyle = {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    gap: '0.375rem',
    padding: '0.5rem',
    borderRadius: '3px',
    cursor: 'pointer',
    transition: 'background 150ms ease, box-shadow 150ms ease',
  };

  var ACTIVE_BG = '#f5f4f0';

  var logoBoxStyle = {
    position: 'relative',
    width: '104px',
    height: '48px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    border: '1px solid #d4d2cb',
    borderRadius: '3px',
    background: '#fbfaf7',
    overflow: 'hidden',
    cursor: 'pointer',
  };

  var logoBoxEmptyStyle = Object.assign({}, logoBoxStyle, {
    border: '1px dashed #d4d2cb',
    background: '#fafaf8',
  });

  var logoImgStyle = {
    maxWidth: '100%',
    maxHeight: '100%',
    objectFit: 'contain',
    display: 'block',
  };

  var placeholderTextStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.625rem',
    fontWeight: '500',
    color: '#999690',
    textTransform: 'uppercase',
    letterSpacing: '0.06em',
  };

  var overlayStyle = {
    position: 'absolute',
    inset: '0',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    background: 'rgba(0,0,0,0.45)',
    color: '#fff',
    fontSize: '0.625rem',
    fontWeight: '600',
    textTransform: 'uppercase',
    letterSpacing: '0.08em',
    opacity: '0',
    transition: 'opacity 150ms ease',
  };

  var overlayVisibleStyle = Object.assign({}, overlayStyle, {
    opacity: '1',
  });

  var fieldInputStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.6875rem',
    color: '#181510',
    width: '104px',
    border: '1px solid transparent',
    borderRadius: '3px',
    padding: '0.2rem 0.35rem',
    background: 'transparent',
    outline: 'none',
    textAlign: 'center',
  };

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M2 9h5v6H2V9zm7-3h5v9H9V6zm7 2h5v7h-5V8z',
    })
  );

  // ── Logo box component ─────────────────────────────────────────────────

  function LogoBoxEditor(props) {
    var url = props.url;
    var onSelect = props.onSelect;

    var hoverState = useState(false);
    var hovering = hoverState[0];
    var setHovering = hoverState[1];

    var content;
    if (url) {
      content = el('img', { src: url, alt: '', style: logoImgStyle });
    } else {
      content = el('span', { style: placeholderTextStyle }, 'Add logo');
    }

    return el(MediaUpload, {
      onSelect: function (media) {
        if (media && media.url) { onSelect(media.url); }
      },
      allowedTypes: ['image'],
      render: function (renderProps) {
        return el('div', {
            style: url ? logoBoxStyle : logoBoxEmptyStyle,
            onClick: renderProps.open,
            onMouseEnter: function () { setHovering(true); },
            onMouseLeave: function () { setHovering(false); },
          },
          content,
          url ? el('span', {
            style: hovering ? overlayVisibleStyle : overlayStyle,
          }, 'Change') : null
        );
      },
    });
  }

  // ── Item component ─────────────────────────────────────────────────────

  function EditLogoItem(props) {
    var logos = props.logos;
    var index = props.index;
    var onChange = props.onChange;
    var isActive = props.isActive;
    var onFocusItem = props.onFocusItem;
    var logo = logos[index];

    function update(field, val) {
      var updated = logos.slice();
      updated[index] = Object.assign({}, logo);
      updated[index][field] = val;
      onChange(updated);
    }

    var currentItemStyle = Object.assign({}, itemWrapStyle);
    if (isActive) {
      currentItemStyle.background = ACTIVE_BG;
      currentItemStyle.boxShadow = 'inset 0 0 0 1px #2b6b78';
    }

    return el('div', {
        style: currentItemStyle,
        onMouseDownCapture: function () { onFocusItem(index); },
        onFocusCapture: function () { onFocusItem(index); },
      },
      el(LogoBoxEditor, {
        url: logo.url,
        onSelect: function (url) { update('url', url); },
      }),
      el('input', {
        type: 'text',
        style: fieldInputStyle,
        value: logo.name,
        placeholder: 'Name (alt text)',
        onChange: function (e) { update('name', e.target.value); },
      }),
      el('input', {
        type: 'text',
        style: fieldInputStyle,
        value: logo.link,
        placeholder: 'Link URL (optional)',
        onChange: function (e) { update('link', e.target.value); },
      })
    );
  }

  // ── Block registration ────────────────────────────────────────────────

  registerBlockType('groove-proposal/logo-strip', {
    title: 'Trusted-by Logo Strip',
    description: 'A row of client or partner logos for social proof.',
    icon: BLOCK_ICON,
    category: 'groove-proposal',
    keywords: ['logos', 'clients', 'partners', 'trusted by', 'social proof'],
    attributes: {
      heading: {
        type: 'string',
        default: 'Trusted by teams like yours',
      },
      logos: {
        type: 'array',
        default: DEFAULT_LOGOS,
      },
    },
    supports: {
      html: false,
      reusable: true,
    },

    edit: function (props) {
      var attrs = props.attributes;
      var heading = attrs.heading;
      var logos = attrs.logos;
      var blockProps = useBlockProps();

      var activeState = useState(-1);
      var activeIndex = activeState[0];
      var setActiveIndex = activeState[1];

      var onFocusItem = useCallback(function (i) {
        setActiveIndex(i);
      }, []);

      function setLogos(updated) {
        props.setAttributes({ logos: updated });
      }

      function addLogo() {
        if (logos.length >= MAX_LOGOS) return;
        var newLogo = { url: '', name: '', link: '' };
        if (activeIndex >= 0 && activeIndex < logos.length) {
          var updated = logos.slice();
          updated.splice(activeIndex, 0, newLogo);
          setLogos(updated);
          setActiveIndex(activeIndex + 1);
        } else {
          setLogos(logos.concat([newLogo]));
        }
      }

      function removeLogo() {
        if (logos.length <= MIN_LOGOS) return;
        if (activeIndex >= 0 && activeIndex < logos.length) {
          var updated = logos.slice();
          updated.splice(activeIndex, 1);
          setLogos(updated);
          if (activeIndex >= updated.length) {
            setActiveIndex(updated.length > 0 ? updated.length - 1 : -1);
          }
        } else {
          setLogos(logos.slice(0, -1));
        }
      }

      return el(wp.element.Fragment, null,
        el(BlockControls, null,
          el(ToolbarGroup, null,
            el(ToolbarButton, {
              icon: 'plus-alt2',
              label: activeIndex >= 0 ? 'Insert logo before selected' : 'Add logo',
              onClick: addLogo,
              disabled: logos.length >= MAX_LOGOS,
            }),
            el(ToolbarButton, {
              icon: 'minus',
              label: activeIndex >= 0 ? 'Remove selected logo' : 'Remove last logo',
              onClick: removeLogo,
              disabled: logos.length <= MIN_LOGOS,
            })
          )
        ),
        el('div', blockProps,
          el(RichText, {
            tagName: 'p',
            style: headingStyle,
            value: heading,
            onChange: function (v) { props.setAttributes({ heading: v }); },
            placeholder: 'Trusted by teams like yours',
            allowedFormats: [],
          }),
          el('div', { style: rowStyle },
            logos.map(function (logo, i) {
              return el(EditLogoItem, {
                key: i,
                logos: logos,
                index: i,
                onChange: setLogos,
                isActive: activeIndex === i,
                onFocusItem: onFocusItem,
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
