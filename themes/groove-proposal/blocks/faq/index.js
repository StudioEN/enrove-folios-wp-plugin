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

  var DEFAULT_ITEMS = [
    {
      question: 'What are the payment terms?',
      answer: 'We require a 50% deposit to begin work, with the remaining balance due upon project completion. For larger engagements we can arrange milestone-based payments instead.',
    },
    {
      question: 'Is the timeline flexible if our needs change?',
      answer: 'Yes. The schedule outlined in this proposal reflects the current scope, and if priorities shift once we’re underway, we’ll revisit the timeline together and adjust accordingly.',
    },
    {
      question: 'What happens after we sign?',
      answer: 'Once the agreement is signed, we’ll schedule a kickoff call within three business days to align on goals, gather assets, and confirm the project timeline.',
    },
  ];

  var MIN_ITEMS = 1;
  var MAX_ITEMS = 8;

  // ── Inline styles (reliable in the editor) ─────────────────────────────

  var wrapStyle = {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.75rem',
  };

  var itemStyle = {
    border: '1px solid #d6d5d0',
    borderRadius: '0.375rem',
    padding: '1rem 1.25rem',
    background: '#f7f7f5',
  };

  var questionRowStyle = {
    display: 'flex',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: '0.75rem',
  };

  var questionStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.9375rem',
    fontWeight: '600',
    color: '#1b1a18',
    lineHeight: '1.4',
    flex: '1',
  };

  var markerStyle = {
    flexShrink: '0',
    fontSize: '1.125rem',
    lineHeight: '1',
    color: '#8a8781',
    marginTop: '0.125rem',
  };

  var answerStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.8125rem',
    color: '#8a8781',
    lineHeight: '1.65',
    marginTop: '0.5rem',
    paddingTop: '0.5rem',
    borderTop: '1px solid #d6d5d0',
  };

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z',
    })
  );

  function EditFaqItem(props) {
    var items = props.items;
    var index = props.index;
    var onChange = props.onChange;
    var item = items[index];

    return el('div', { style: itemStyle },
      el('div', { style: questionRowStyle },
        el(RichText, {
          tagName: 'div',
          style: questionStyle,
          value: item.question,
          onChange: function (newValue) {
            var updated = items.slice();
            updated[index] = Object.assign({}, item, { question: newValue });
            onChange(updated);
          },
          placeholder: __('Question', 'groove-folios'),
          allowedFormats: [],
        }),
        el('span', { style: markerStyle }, '+')
      ),
      el(RichText, {
        tagName: 'div',
        style: answerStyle,
        value: item.answer,
        onChange: function (newValue) {
          var updated = items.slice();
          updated[index] = Object.assign({}, item, { answer: newValue });
          onChange(updated);
        },
        placeholder: __('Answer…', 'groove-folios'),
      })
    );
  }

  registerBlockType('groove-proposal/faq', {
    title: __('FAQ Accordion', 'groove-folios'),
    description: __('Expandable question-and-answer list for handling objections around pricing, timeline, or scope.', 'groove-folios'),
    icon: BLOCK_ICON,
    category: 'groove-proposal',
    keywords: [__('faq', 'groove-folios'), __('questions', 'groove-folios'), __('accordion', 'groove-folios'), __('objections', 'groove-folios')],
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
        setItems(items.concat([{ question: '', answer: '' }]));
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
              label: __('Add question', 'groove-folios'),
              onClick: addItem,
              disabled: items.length >= MAX_ITEMS,
            }),
            el(ToolbarButton, {
              icon: 'minus',
              label: __('Remove question', 'groove-folios'),
              onClick: removeItem,
              disabled: items.length <= MIN_ITEMS,
            })
          )
        ),
        el('div', blockProps,
          el('div', { style: wrapStyle },
            items.map(function (item, i) {
              return el(EditFaqItem, {
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
