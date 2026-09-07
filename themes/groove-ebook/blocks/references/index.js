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
  var InspectorControls = wp.blockEditor.InspectorControls;
  var ToolbarGroup = wp.components.ToolbarGroup;
  var ToolbarButton = wp.components.ToolbarButton;
  var PanelBody = wp.components.PanelBody;
  var TextControl = wp.components.TextControl;

  var FONT = "'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";

  var MIN_ENTRIES = 1;
  var MAX_ENTRIES = 40;

  var DEFAULT_ENTRIES = [
    { author: 'Sennett, Richard', work: 'The Craftsman', note: 'Yale University Press, 2008', url: '' },
    { author: 'Pye, David', work: 'The Nature and Art of Workmanship', note: 'Cambridge University Press, 1968', url: '' },
    { author: 'Sudjic, Deyan', work: 'The Language of Things', note: 'Penguin, 2008', url: '' },
  ];

  var wrapStyle = {
    margin: '2.5rem 0',
  };

  var titleStyle = {
    fontFamily: FONT,
    fontSize: '0.75rem',
    fontWeight: '600',
    letterSpacing: '0.08em',
    textTransform: 'uppercase',
    color: '#3C434A',
    margin: '0 0 0.875rem',
    paddingBottom: '0.5rem',
    borderBottom: '1px solid #E4E4E5',
  };

  var listStyle = {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.75rem',
  };

  var rowStyle = {
    display: 'flex',
    alignItems: 'baseline',
    gap: '0.75rem',
  };

  var ordinalStyle = {
    flexShrink: '0',
    fontFamily: FONT,
    fontSize: '0.75rem',
    fontVariantNumeric: 'tabular-nums',
    color: '#8C8F94',
    lineHeight: '1.6',
  };

  var entryStyle = {
    flex: '1',
    minWidth: '0',
  };

  var authorStyle = {
    fontFamily: FONT,
    fontSize: '0.875rem',
    fontWeight: '500',
    lineHeight: '1.5',
    color: '#101517',
  };

  var workStyle = {
    fontFamily: FONT,
    fontSize: '0.875rem',
    fontStyle: 'italic',
    lineHeight: '1.5',
    color: '#101517',
  };

  var noteStyle = {
    fontFamily: FONT,
    fontSize: '0.8125rem',
    lineHeight: '1.5',
    color: '#3C434A',
  };

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M5 3h9l5 5v13H5V3zm2 2v14h10V9h-4V5H7zm2 8h6v1.6H9V13zm0 3h6v1.6H9V16z',
    })
  );

  function EditEntry(props) {
    var entries = props.entries;
    var index = props.index;
    var onChange = props.onChange;
    var entry = entries[index] || {};

    function update(field, value) {
      var updated = entries.slice();
      updated[index] = Object.assign({}, entries[index]);
      updated[index][field] = value;
      onChange(updated);
    }

    return el('div', { style: rowStyle },
      el('span', { style: ordinalStyle }, String(index + 1) + '.'),
      el('div', { style: entryStyle },
        el(RichText, {
          tagName: 'div',
          style: authorStyle,
          value: entry.author || '',
          onChange: function (v) { update('author', v); },
          placeholder: 'Author',
          allowedFormats: [],
        }),
        el(RichText, {
          tagName: 'div',
          style: workStyle,
          value: entry.work || '',
          onChange: function (v) { update('work', v); },
          placeholder: 'Title of the work',
          allowedFormats: [],
        }),
        el(RichText, {
          tagName: 'div',
          style: noteStyle,
          value: entry.note || '',
          onChange: function (v) { update('note', v); },
          placeholder: 'Publisher, year, page…',
          allowedFormats: [],
        })
      )
    );
  }

  registerBlockType('groove-ebook/references', {
    title: 'References',
    description: 'Works cited or further reading, numbered — back matter for a chapter or a colophon.',
    icon: BLOCK_ICON,
    category: 'groove-ebook',
    keywords: ['references', 'bibliography', 'sources', 'further reading', 'citations'],
    attributes: {
      title: {
        type: 'string',
        default: 'Further reading',
      },
      entries: {
        type: 'array',
        default: DEFAULT_ENTRIES,
      },
    },
    supports: {
      html: false,
      reusable: true,
    },

    edit: function (props) {
      var attrs = props.attributes;
      var entries = attrs.entries || [];
      var blockProps = useBlockProps();

      function setEntries(updated) {
        props.setAttributes({ entries: updated });
      }

      return el(wp.element.Fragment, null,
        el(BlockControls, null,
          el(ToolbarGroup, null,
            el(ToolbarButton, {
              icon: 'plus-alt2',
              label: 'Add an entry',
              onClick: function () {
                if (entries.length >= MAX_ENTRIES) return;
                setEntries(entries.concat([{ author: '', work: '', note: '', url: '' }]));
              },
              disabled: entries.length >= MAX_ENTRIES,
            }),
            el(ToolbarButton, {
              icon: 'minus',
              label: 'Remove the last entry',
              onClick: function () {
                if (entries.length <= MIN_ENTRIES) return;
                setEntries(entries.slice(0, -1));
              },
              disabled: entries.length <= MIN_ENTRIES,
            })
          )
        ),
        // Links live in the sidebar rather than inline: a URL is a long string
        // that would wreck the canvas preview of a list meant to read as a
        // bibliography.
        el(InspectorControls, null,
          el(PanelBody, { title: 'Links', initialOpen: false },
            entries.map(function (entry, i) {
              return el(TextControl, {
                key: i,
                label: (entry.work || entry.author || 'Entry ' + (i + 1)),
                value: entry.url || '',
                type: 'url',
                placeholder: 'https://',
                onChange: function (v) {
                  var updated = entries.slice();
                  updated[i] = Object.assign({}, entries[i], { url: v });
                  setEntries(updated);
                },
              });
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
              placeholder: 'Heading (optional)',
              allowedFormats: [],
            }),
            el('div', { style: listStyle },
              entries.map(function (entry, i) {
                return el(EditEntry, {
                  key: i,
                  entries: entries,
                  index: i,
                  onChange: setEntries,
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
