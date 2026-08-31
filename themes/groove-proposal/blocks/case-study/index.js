(function (wp) {
  'use strict';

  if (!wp || !wp.blocks || !wp.element || !wp.blockEditor) {
    return;
  }

  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var useState = wp.element.useState;
  var registerBlockType = wp.blocks.registerBlockType;
  var RichText = wp.blockEditor.RichText;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var BlockControls = wp.blockEditor.BlockControls;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var MediaUpload = wp.blockEditor.MediaUpload;
  var InnerBlocks = wp.blockEditor.InnerBlocks;
  var PanelBody = wp.components.PanelBody;
  var SelectControl = wp.components.SelectControl;
  var TextControl = wp.components.TextControl;
  var ToolbarGroup = wp.components.ToolbarGroup;
  var ToolbarButton = wp.components.ToolbarButton;

  var MIN_STATS = 0;
  var MAX_STATS = 3;

  var TEMPLATE = [
    ['core/heading', { level: 3, content: 'The Challenge' }],
    ['core/paragraph', { placeholder: 'Describe the challenge the client faced…' }],
    ['core/heading', { level: 3, content: 'Our Approach' }],
    ['core/paragraph', { placeholder: 'Describe the approach you took…' }],
    ['core/heading', { level: 3, content: 'The Results' }],
    ['core/paragraph', { placeholder: 'Describe the results achieved…' }],
  ];

  var ALLOWED_BLOCKS = [
    'core/heading',
    'core/paragraph',
    'core/list',
    'core/image',
    'core/gallery',
    'core/quote',
    'groove-proposal/pull-quote',
    'groove-proposal/key-metrics',
  ];

  // ── Helpers ────────────────────────────────────────────────────────────

  function parseTags(text) {
    if (!text) return [];
    return text
      .split(',')
      .map(function (t) { return t.trim(); })
      .filter(function (t) { return t.length > 0; });
  }

  // ── Inline styles ──────────────────────────────────────────────────────

  var chromeWrapStyle = {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.75rem',
    margin: '1rem 0',
    padding: '1.25rem 1.5rem',
    border: '1px solid #d6d5d0',
    borderRadius: '0.5rem',
    background: '#f7f7f5',
  };

  var headerRowStyle = {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
  };

  var clientNameStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.8125rem',
    fontWeight: '600',
    color: '#1b1a18',
    textTransform: 'uppercase',
    letterSpacing: '0.06em',
    flex: '1',
    minWidth: '0',
  };

  var tagsInputStyle = {
    width: '100%',
    boxSizing: 'border-box',
    padding: '0.4rem 0.6rem',
    border: '1px solid #d6d5d0',
    borderRadius: '3px',
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.75rem',
    color: '#1b1a18',
    background: '#ffffff',
  };

  var tagsRowStyle = {
    display: 'flex',
    flexWrap: 'wrap',
    gap: '0.375rem',
  };

  var tagPillStyle = {
    display: 'inline-block',
    padding: '0.15rem 0.55rem',
    borderRadius: '999px',
    border: '1px solid #d6d5d0',
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.625rem',
    fontWeight: '600',
    textTransform: 'uppercase',
    letterSpacing: '0.05em',
    color: '#855c22',
    background: 'rgba(133, 92, 34, 0.1)',
  };

  var titleStyle = {
    fontFamily: "'Fraunces', Georgia, serif",
    fontSize: '1.5rem',
    fontWeight: '400',
    fontStyle: 'italic',
    lineHeight: '1.25',
    letterSpacing: '-0.01em',
    color: '#1b1a18',
    margin: '0',
  };

  var statsGridBaseStyle = {
    display: 'grid',
    gap: '1.25rem',
    padding: '1rem 0 0',
    borderTop: '1px solid #d6d5d0',
  };

  var statItemStyle = {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.25rem',
    textAlign: 'center',
  };

  var statValueStyle = {
    fontFamily: "'Fraunces', Georgia, serif",
    fontSize: '1.75rem',
    fontWeight: '300',
    fontStyle: 'italic',
    lineHeight: '1.1',
    letterSpacing: '-0.02em',
    color: '#1b1a18',
    textAlign: 'center',
  };

  var statLabelStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.75rem',
    fontWeight: '500',
    color: '#8a8781',
    textTransform: 'uppercase',
    letterSpacing: '0.06em',
    textAlign: 'center',
  };

  var bodyWrapStyle = {
    paddingTop: '0.25rem',
  };

  var rectWrapStyle = {
    position: 'relative',
  };

  var rectImgStyle = {
    width: '100%',
    height: '100%',
    objectFit: 'cover',
    display: 'block',
  };

  var rectPlaceholderStyle = {
    width: '100%',
    height: '100%',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.75rem',
    color: '#8a8781',
    background: '#e3e3e0',
  };

  var rectOverlayStyle = {
    position: 'absolute',
    inset: '0',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    background: 'rgba(0,0,0,0.45)',
    color: '#fff',
    fontSize: '0.6875rem',
    fontWeight: '600',
    textTransform: 'uppercase',
    letterSpacing: '0.06em',
    opacity: '0',
    transition: 'opacity 150ms ease',
  };

  var rectOverlayVisibleStyle = Object.assign({}, rectOverlayStyle, {
    opacity: '1',
  });

  var rectRemoveButtonStyle = {
    position: 'absolute',
    top: '0.375rem',
    right: '0.375rem',
    width: '20px',
    height: '20px',
    borderRadius: '50%',
    background: 'rgba(0,0,0,0.6)',
    color: '#fff',
    border: 'none',
    cursor: 'pointer',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '0.75rem',
    lineHeight: '1',
    padding: '0',
    zIndex: '2',
  };

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z',
    })
  );

  // ── Rectangular image editor (logo / hero) ──────────────────────────────

  function RectImageEditor(props) {
    var image = props.image;
    var onSelect = props.onSelect;
    var onRemove = props.onRemove;
    var width = props.width;
    var height = props.height;
    var placeholder = props.placeholder;
    var borderRadius = props.borderRadius || '4px';

    var hoverState = useState(false);
    var hovering = hoverState[0];
    var setHovering = hoverState[1];

    var currentWrapStyle = Object.assign({}, rectWrapStyle, {
      width: width,
    });

    var boxStyle = {
      width: width,
      height: height,
      borderRadius: borderRadius,
      overflow: 'hidden',
      border: '1px solid #d6d5d0',
      cursor: 'pointer',
      flexShrink: '0',
    };

    return el('div', { style: currentWrapStyle },
      el(MediaUpload, {
        onSelect: function (media) {
          if (media && media.url) { onSelect(media.url); }
        },
        allowedTypes: ['image'],
        render: function (renderProps) {
          return el('div', {
              style: boxStyle,
              onClick: renderProps.open,
              onMouseEnter: function () { setHovering(true); },
              onMouseLeave: function () { setHovering(false); },
            },
            image
              ? el('img', { src: image, alt: '', style: rectImgStyle })
              : el('div', { style: rectPlaceholderStyle }, placeholder),
            el('div', { style: hovering ? rectOverlayVisibleStyle : rectOverlayStyle }, image ? 'Change' : 'Upload')
          );
        },
      }),
      image ? el('button', {
        type: 'button',
        style: rectRemoveButtonStyle,
        onClick: function (e) { e.stopPropagation(); onRemove(); },
        'aria-label': 'Remove image',
      }, '×') : null
    );
  }

  // ── Stat item ────────────────────────────────────────────────────────────

  function EditStatItem(props) {
    var items = props.items;
    var index = props.index;
    var onChange = props.onChange;
    var item = items[index];

    return el('div', { style: statItemStyle },
      el(RichText, {
        tagName: 'div',
        style: statValueStyle,
        value: item.value,
        onChange: function (v) {
          var updated = items.slice();
          updated[index] = Object.assign({}, item, { value: v });
          onChange(updated);
        },
        placeholder: '0',
        allowedFormats: [],
      }),
      el(RichText, {
        tagName: 'div',
        style: statLabelStyle,
        value: item.label,
        onChange: function (v) {
          var updated = items.slice();
          updated[index] = Object.assign({}, item, { label: v });
          onChange(updated);
        },
        placeholder: 'Label',
        allowedFormats: [],
      })
    );
  }

  // ── Block registration ────────────────────────────────────────────────

  registerBlockType('groove-proposal/case-study', {
    title: 'Case Study',
    description: 'An elaborate client story with challenge, approach, and results sections, plus flexible rich content.',
    icon: BLOCK_ICON,
    category: 'groove-proposal',
    keywords: ['case study', 'portfolio', 'proof', 'client story'],
    attributes: {
      clientName: { type: 'string', default: '' },
      clientLogo: { type: 'string', default: '' },
      projectTitle: { type: 'string', default: '' },
      heroImage: { type: 'string', default: '' },
      tagsText: { type: 'string', default: '' },
      stats: { type: 'array', default: [] },
      link: { type: 'string', default: '' },
      layout: { type: 'string', default: 'spotlight' },
    },
    supports: {
      html: false,
      reusable: true,
    },

    edit: function (props) {
      var attrs = props.attributes;
      var setAttributes = props.setAttributes;
      var blockProps = useBlockProps();
      var stats = attrs.stats || [];
      var tags = parseTags(attrs.tagsText);

      function setStats(updated) {
        setAttributes({ stats: updated });
      }

      function addStat() {
        if (stats.length >= MAX_STATS) return;
        setStats(stats.concat([{ value: '0', label: 'Label' }]));
      }

      function removeStat() {
        if (stats.length <= MIN_STATS) return;
        setStats(stats.slice(0, -1));
      }

      var statsGridStyle = Object.assign({}, statsGridBaseStyle, {
        gridTemplateColumns: 'repeat(' + Math.max(stats.length, 1) + ', 1fr)',
      });

      return el(Fragment, null,
        el(InspectorControls, null,
          el(PanelBody, { title: 'Case study settings', initialOpen: true },
            el(SelectControl, {
              label: 'Layout',
              value: attrs.layout,
              options: [
                { label: 'Spotlight', value: 'spotlight' },
                { label: 'Compact', value: 'compact' },
              ],
              onChange: function (val) { setAttributes({ layout: val }); },
            }),
            el(TextControl, {
              label: 'View live project URL',
              type: 'url',
              value: attrs.link,
              placeholder: 'https://…',
              onChange: function (val) { setAttributes({ link: val }); },
            })
          )
        ),
        el(BlockControls, null,
          el(ToolbarGroup, null,
            el(ToolbarButton, {
              icon: 'plus-alt2',
              label: 'Add stat',
              onClick: addStat,
              disabled: stats.length >= MAX_STATS,
            }),
            el(ToolbarButton, {
              icon: 'minus',
              label: 'Remove stat',
              onClick: removeStat,
              disabled: stats.length <= MIN_STATS,
            })
          )
        ),
        el('div', blockProps,
          el('div', { style: chromeWrapStyle },
            el('div', { style: headerRowStyle },
              el(RectImageEditor, {
                image: attrs.clientLogo,
                onSelect: function (url) { setAttributes({ clientLogo: url }); },
                onRemove: function () { setAttributes({ clientLogo: '' }); },
                width: '64px',
                height: '40px',
                placeholder: 'Logo',
              }),
              el(RichText, {
                tagName: 'div',
                style: clientNameStyle,
                value: attrs.clientName,
                onChange: function (v) { setAttributes({ clientName: v }); },
                placeholder: 'Client name',
                allowedFormats: [],
              })
            ),
            el('input', {
              type: 'text',
              style: tagsInputStyle,
              value: attrs.tagsText,
              placeholder: 'Tags, comma separated (e.g. Brand, Web, B2B)',
              onChange: function (e) { setAttributes({ tagsText: e.target.value }); },
            }),
            tags.length > 0 ? el('div', { style: tagsRowStyle }, tags.map(function (tag, i) {
              return el('span', { key: i, style: tagPillStyle }, tag);
            })) : null,
            el(RichText, {
              tagName: 'h3',
              style: titleStyle,
              value: attrs.projectTitle,
              onChange: function (v) { setAttributes({ projectTitle: v }); },
              placeholder: 'Project title',
              allowedFormats: [],
            }),
            el(RectImageEditor, {
              image: attrs.heroImage,
              onSelect: function (url) { setAttributes({ heroImage: url }); },
              onRemove: function () { setAttributes({ heroImage: '' }); },
              width: '100%',
              height: '220px',
              placeholder: 'Hero image',
              borderRadius: '0.375rem',
            }),
            stats.length > 0 ? el('div', { style: statsGridStyle }, stats.map(function (stat, i) {
              return el(EditStatItem, { key: i, items: stats, index: i, onChange: setStats });
            })) : null,
            el('div', { style: bodyWrapStyle },
              el(InnerBlocks, {
                template: TEMPLATE,
                allowedBlocks: ALLOWED_BLOCKS,
                templateLock: false,
              })
            )
          )
        )
      );
    },

    save: function () {
      return el(InnerBlocks.Content);
    },
  });
})(window.wp);
