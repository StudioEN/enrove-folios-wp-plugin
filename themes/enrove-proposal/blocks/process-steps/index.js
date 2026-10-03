(function (wp) {
  'use strict';

  if (!wp || !wp.blocks || !wp.element || !wp.blockEditor || !wp.i18n) {
    return;
  }

  var el = wp.element.createElement;
  var __ = wp.i18n.__;
  var useState = wp.element.useState;
  var useCallback = wp.element.useCallback;
  var registerBlockType = wp.blocks.registerBlockType;
  var RichText = wp.blockEditor.RichText;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var BlockControls = wp.blockEditor.BlockControls;
  var ToolbarGroup = wp.components.ToolbarGroup;
  var ToolbarButton = wp.components.ToolbarButton;

  var DEFAULT_STEPS = [
    { title: 'Discover', desc: 'Deep dive into user needs, stakeholder goals, and the competitive landscape.' },
    { title: 'Define',   desc: 'Synthesize research into a clear strategy, roadmap, and success criteria.' },
    { title: 'Design',   desc: 'Explore concepts, refine the visual direction, and prototype key interactions.' },
    { title: 'Deliver',  desc: 'Build, test, and launch with thorough QA and handoff documentation.' },
  ];

  var MIN_STEPS = 2;
  var MAX_STEPS = 8;

  // ── Helpers ────────────────────────────────────────────────────────────

  function pad(n) {
    return n < 10 ? '0' + n : '' + n;
  }

  // ── Shared inline styles ───────────────────────────────────────────────

  var ACTIVE_BG = '#ededeb';

  var badgeStyle = {
    width: '40px',
    height: '40px',
    borderRadius: '50%',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    background: '#27498c',
    color: '#ffffff',
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.75rem',
    fontWeight: '700',
    letterSpacing: '0.02em',
    lineHeight: '1',
    flexShrink: '0',
  };

  var titleStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.9375rem',
    fontWeight: '600',
    color: '#1b1a18',
    lineHeight: '1.35',
    width: '100%',
  };

  var descStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.8125rem',
    color: '#666259',
    lineHeight: '1.6',
    marginTop: '0.25rem',
    width: '100%',
  };

  // ── Horizontal layout styles ───────────────────────────────────────────

  var hWrapStyle = {
    display: 'flex',
    flexDirection: 'row',
    gap: '0',
    margin: '1rem 0',
    alignItems: 'stretch',
  };

  var hStepStyle = {
    flex: '1',
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    textAlign: 'center',
    padding: '0 0.75rem',
    cursor: 'pointer',
    transition: 'background 150ms ease',
    borderRadius: '3px',
  };

  var hBadgeRowStyle = {
    display: 'flex',
    alignItems: 'center',
    width: '100%',
    marginBottom: '0.75rem',
  };

  var hConnectorStyle = {
    flex: '1',
    height: '1px',
    background: '#d6d5d0',
  };

  var hConnectorInvisibleStyle = {
    flex: '1',
    height: '1px',
    background: 'transparent',
  };

  var hBadgeCenterStyle = {
    display: 'flex',
    justifyContent: 'center',
    flexShrink: '0',
  };

  // ── Vertical layout styles ─────────────────────────────────────────────

  var vWrapStyle = {
    display: 'flex',
    flexDirection: 'column',
    gap: '0',
    margin: '1rem 0',
    padding: '1.5rem',
    border: '1px solid #d6d5d0',
    borderRadius: '3px',
    background: '#f7f7f5',
  };

  var vStepStyle = {
    display: 'grid',
    gridTemplateColumns: '40px 1fr',
    gap: '0 1rem',
    cursor: 'pointer',
    transition: 'background 150ms ease',
  };

  var vMarkerStyle = {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
  };

  var vConnectorStyle = {
    flex: '1',
    width: '1px',
    background: '#d6d5d0',
    minHeight: '1rem',
    marginTop: '0.5rem',
  };

  var vCopyStyle = {
    paddingBottom: '1.75rem',
    paddingTop: '0.5rem',
  };

  var vCopyLastStyle = {
    paddingBottom: '0',
    paddingTop: '0.5rem',
  };

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M4 15h16v-2H4v2zm0 4h16v-2H4v2zm0-8h16V9H4v2zm0-6v2h16V5H4z',
    })
  );

  // ── Horizontal step component ──────────────────────────────────────────

  function EditStepHorizontal(props) {
    var steps = props.steps;
    var index = props.index;
    var onChange = props.onChange;
    var isActive = props.isActive;
    var onFocusStep = props.onFocusStep;
    var step = steps[index];
    var isFirst = index === 0;
    var isLast = index === steps.length - 1;

    function update(field, val) {
      var updated = steps.slice();
      updated[index] = Object.assign({}, step);
      updated[index][field] = val;
      onChange(updated);
    }

    var currentStyle = Object.assign({}, hStepStyle);
    if (isActive) {
      currentStyle.background = ACTIVE_BG;
    }

    return el('div', {
        style: currentStyle,
        onMouseDownCapture: function () { onFocusStep(index); },
        onFocusCapture: function () { onFocusStep(index); },
      },
      // Badge row with connectors
      el('div', { style: hBadgeRowStyle },
        el('div', { style: isFirst ? hConnectorInvisibleStyle : hConnectorStyle }),
        el('div', { style: hBadgeCenterStyle },
          el('span', { style: badgeStyle }, pad(index + 1))
        ),
        el('div', { style: isLast ? hConnectorInvisibleStyle : hConnectorStyle })
      ),
      // Text
      el(RichText, {
        tagName: 'div',
        style: titleStyle,
        value: step.title,
        onChange: function (v) { update('title', v); },
        placeholder: __('Step title', 'enrove-folios'),
        allowedFormats: [],
      }),
      el(RichText, {
        tagName: 'div',
        style: descStyle,
        value: step.desc,
        onChange: function (v) { update('desc', v); },
        placeholder: __('Step description…', 'enrove-folios'),
        allowedFormats: [],
      })
    );
  }

  // ── Vertical step component ────────────────────────────────────────────

  function EditStepVertical(props) {
    var steps = props.steps;
    var index = props.index;
    var onChange = props.onChange;
    var isActive = props.isActive;
    var onFocusStep = props.onFocusStep;
    var step = steps[index];
    var isLast = index === steps.length - 1;

    function update(field, val) {
      var updated = steps.slice();
      updated[index] = Object.assign({}, step);
      updated[index][field] = val;
      onChange(updated);
    }

    var currentStyle = Object.assign({}, vStepStyle);
    if (isActive) {
      currentStyle.background = ACTIVE_BG;
      currentStyle.borderRadius = '3px';
    }

    return el('div', {
        style: currentStyle,
        onMouseDownCapture: function () { onFocusStep(index); },
        onFocusCapture: function () { onFocusStep(index); },
      },
      // Marker column
      el('div', { style: vMarkerStyle },
        el('span', { style: badgeStyle }, pad(index + 1)),
        !isLast ? el('span', { style: vConnectorStyle }) : null
      ),
      // Copy column
      el('div', { style: isLast ? vCopyLastStyle : vCopyStyle },
        el(RichText, {
          tagName: 'div',
          style: titleStyle,
          value: step.title,
          onChange: function (v) { update('title', v); },
          placeholder: __('Step title', 'enrove-folios'),
          allowedFormats: [],
        }),
        el(RichText, {
          tagName: 'div',
          style: descStyle,
          value: step.desc,
          onChange: function (v) { update('desc', v); },
          placeholder: __('Step description…', 'enrove-folios'),
          allowedFormats: [],
        })
      )
    );
  }

  // ── Block registration ────────────────────────────────────────────────

  registerBlockType('enrove-proposal/process-steps', {
    title: __('Process Steps', 'enrove-folios'),
    description: __('Numbered process steps with prominent badges. Horizontal or vertical layout.', 'enrove-folios'),
    icon: BLOCK_ICON,
    category: 'enrove-proposal',
    keywords: [__('process', 'enrove-folios'), __('steps', 'enrove-folios'), __('workflow', 'enrove-folios'), __('method', 'enrove-folios'), __('approach', 'enrove-folios'), __('how', 'enrove-folios')],
    attributes: {
      steps: {
        type: 'array',
        default: DEFAULT_STEPS,
      },
      layout: {
        type: 'string',
        default: 'horizontal',
      },
    },
    supports: {
      html: false,
      reusable: true,
    },

    edit: function (props) {
      var attrs = props.attributes;
      var steps = attrs.steps;
      var layout = attrs.layout;
      var blockProps = useBlockProps();
      var isVertical = layout === 'vertical';

      var activeState = useState(-1);
      var activeIndex = activeState[0];
      var setActiveIndex = activeState[1];

      var onFocusStep = useCallback(function (i) {
        setActiveIndex(i);
      }, []);

      function setSteps(updated) {
        props.setAttributes({ steps: updated });
      }

      function addStep() {
        if (steps.length >= MAX_STEPS) return;
        var newStep = { title: '', desc: '' };
        if (activeIndex >= 0 && activeIndex < steps.length) {
          var updated = steps.slice();
          updated.splice(activeIndex, 0, newStep);
          setSteps(updated);
          setActiveIndex(activeIndex + 1);
        } else {
          setSteps(steps.concat([newStep]));
        }
      }

      function removeStep() {
        if (steps.length <= MIN_STEPS) return;
        if (activeIndex >= 0 && activeIndex < steps.length) {
          var updated = steps.slice();
          updated.splice(activeIndex, 1);
          setSteps(updated);
          if (activeIndex >= updated.length) {
            setActiveIndex(updated.length > 0 ? updated.length - 1 : -1);
          }
        } else {
          setSteps(steps.slice(0, -1));
        }
      }

      function toggleLayout() {
        props.setAttributes({ layout: isVertical ? 'horizontal' : 'vertical' });
      }

      var EditStep = isVertical ? EditStepVertical : EditStepHorizontal;
      var wrapStyle = isVertical ? vWrapStyle : hWrapStyle;

      return el(wp.element.Fragment, null,
        el(BlockControls, null,
          el(ToolbarGroup, null,
            el(ToolbarButton, {
              icon: 'plus-alt2',
              label: activeIndex >= 0 ? __('Insert step before selected', 'enrove-folios') : __('Add step', 'enrove-folios'),
              onClick: addStep,
              disabled: steps.length >= MAX_STEPS,
            }),
            el(ToolbarButton, {
              icon: 'minus',
              label: activeIndex >= 0 ? __('Remove selected step', 'enrove-folios') : __('Remove last step', 'enrove-folios'),
              onClick: removeStep,
              disabled: steps.length <= MIN_STEPS,
            })
          ),
          el(ToolbarGroup, null,
            el(ToolbarButton, {
              icon: isVertical ? 'columns' : 'list-view',
              label: isVertical ? __('Switch to horizontal', 'enrove-folios') : __('Switch to vertical', 'enrove-folios'),
              onClick: toggleLayout,
            })
          )
        ),
        el('div', blockProps,
          el('div', { style: wrapStyle },
            steps.map(function (step, i) {
              return el(EditStep, {
                key: i,
                steps: steps,
                index: i,
                onChange: setSteps,
                isActive: activeIndex === i,
                onFocusStep: onFocusStep,
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
