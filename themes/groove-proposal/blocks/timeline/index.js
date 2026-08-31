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

  var DEFAULT_PHASES = [
    { date: 'Weeks 1-2', title: 'Discovery', desc: 'Stakeholder interviews, competitive audit, and user research to establish the project foundation.' },
    { date: 'Weeks 3-5', title: 'Strategy & architecture', desc: 'Define the roadmap, information architecture, and content strategy based on research findings.' },
    { date: 'Weeks 6-10', title: 'Design & prototyping', desc: 'Visual design, interactive prototyping, and iterative review cycles with your team.' },
    { date: 'Weeks 11-12', title: 'Handoff & launch support', desc: 'Design system documentation, developer handoff, and launch QA.' },
  ];

  var MIN_PHASES = 2;
  var MAX_PHASES = 8;

  // ── Inline styles ──────────────────────────────────────────────────────

  var wrapStyle = {
    display: 'flex',
    flexDirection: 'column',
    margin: '1rem 0',
    padding: '1.5rem',
    border: '1px solid #d6d5d0',
    borderRadius: '0.5rem',
    background: '#f7f7f5',
  };

  var phaseStyle = {
    display: 'grid',
    gridTemplateColumns: '2.5rem 1fr',
    gap: '0 1rem',
  };

  var markerStyle = {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
  };

  var ordinalStyle = {
    fontFamily: "'Fraunces', Georgia, serif",
    fontSize: '0.8125rem',
    fontWeight: '400',
    fontStyle: 'italic',
    color: '#8a8781',
    lineHeight: '1',
    paddingTop: '0.15rem',
  };

  var lineStyle = {
    flex: '1',
    width: '1px',
    background: '#b3b0a9',
    minHeight: '1rem',
    marginTop: '0.5rem',
  };

  var bodyStyle = {
    paddingBottom: '1.75rem',
  };

  var bodyLastStyle = {
    paddingBottom: '0',
  };

  var dateStyle = {
    display: 'block',
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.75rem',
    fontWeight: '600',
    color: '#8a8781',
    textTransform: 'uppercase',
    letterSpacing: '0.08em',
    marginBottom: '0.15rem',
  };

  var titleStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.9375rem',
    fontWeight: '600',
    color: '#1b1a18',
    lineHeight: '1.35',
    margin: '0 0 0.35rem',
  };

  var descStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.8125rem',
    color: '#666259',
    lineHeight: '1.6',
    margin: '0',
  };

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z',
    })
  );

  function pad(n) {
    return n < 10 ? '0' + n : '' + n;
  }

  function EditPhase(props) {
    var phases = props.phases;
    var index = props.index;
    var onChange = props.onChange;
    var phase = phases[index];
    var isLast = index === phases.length - 1;

    function update(field, val) {
      var updated = phases.slice();
      updated[index] = Object.assign({}, phase);
      updated[index][field] = val;
      onChange(updated);
    }

    return el('div', { style: phaseStyle },
      // Marker column
      el('div', { style: markerStyle },
        el('span', { style: ordinalStyle }, pad(index + 1)),
        !isLast ? el('span', { style: lineStyle }) : null
      ),
      // Body column
      el('div', { style: isLast ? bodyLastStyle : bodyStyle },
        el(RichText, {
          tagName: 'div',
          style: dateStyle,
          value: phase.date,
          onChange: function (v) { update('date', v); },
          placeholder: 'Timeframe',
          allowedFormats: [],
        }),
        el(RichText, {
          tagName: 'div',
          style: titleStyle,
          value: phase.title,
          onChange: function (v) { update('title', v); },
          placeholder: 'Phase title',
          allowedFormats: [],
        }),
        el(RichText, {
          tagName: 'div',
          style: descStyle,
          value: phase.desc,
          onChange: function (v) { update('desc', v); },
          placeholder: 'Phase description\u2026',
          allowedFormats: [],
        })
      )
    );
  }

  registerBlockType('groove-proposal/timeline', {
    title: 'Timeline / Phases',
    description: 'Vertical phase timeline with ordinals, dates, and descriptions.',
    icon: BLOCK_ICON,
    category: 'groove-proposal',
    keywords: ['timeline', 'phases', 'schedule', 'milestones', 'roadmap'],
    attributes: {
      phases: {
        type: 'array',
        default: DEFAULT_PHASES,
      },
    },
    supports: {
      html: false,
      reusable: true,
    },

    edit: function (props) {
      var phases = props.attributes.phases;
      var blockProps = useBlockProps();

      function setPhases(updated) {
        props.setAttributes({ phases: updated });
      }

      function addPhase() {
        if (phases.length >= MAX_PHASES) return;
        setPhases(phases.concat([{ date: '', title: '', desc: '' }]));
      }

      function removePhase() {
        if (phases.length <= MIN_PHASES) return;
        setPhases(phases.slice(0, -1));
      }

      return el(wp.element.Fragment, null,
        el(BlockControls, null,
          el(ToolbarGroup, null,
            el(ToolbarButton, {
              icon: 'plus-alt2',
              label: 'Add phase',
              onClick: addPhase,
              disabled: phases.length >= MAX_PHASES,
            }),
            el(ToolbarButton, {
              icon: 'minus',
              label: 'Remove phase',
              onClick: removePhase,
              disabled: phases.length <= MIN_PHASES,
            })
          )
        ),
        el('div', blockProps,
          el('div', { style: wrapStyle },
            phases.map(function (phase, i) {
              return el(EditPhase, {
                key: i,
                phases: phases,
                index: i,
                onChange: setPhases,
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
