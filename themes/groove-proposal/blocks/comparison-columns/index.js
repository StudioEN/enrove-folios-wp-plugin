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
  var ToolbarGroup = wp.components.ToolbarGroup;
  var ToolbarButton = wp.components.ToolbarButton;

  var MIN_COLS = 2;
  var MAX_COLS = 3;
  var MIN_FEATURES = 1;
  var MAX_FEATURES = 12;

  var DEFAULT_COLUMNS = [
    {
      name: 'Essentials',
      subtitle: 'Core deliverables',
      features: [
        'Brand audit & competitive analysis',
        'Visual identity refresh',
        'Primary logo suite',
        'Brand guidelines (digital)',
      ],
    },
    {
      name: 'Professional',
      subtitle: 'Recommended for most teams',
      features: [
        'Everything in Essentials',
        'Full design system',
        'Interactive prototypes',
        'Developer handoff package',
      ],
    },
    {
      name: 'Enterprise',
      subtitle: 'End-to-end partnership',
      features: [
        'Everything in Professional',
        'Motion & interaction design',
        'Ongoing design support (3 mo)',
        'Quarterly design reviews',
      ],
    },
  ];

  // ── Inline styles ──────────────────────────────────────────────────────────

  var ACCENT = '#27498c';
  var ACTIVE_BG = '#ededeb';

  var tableStyle = {
    margin: '2.5rem 0 2rem',
    border: '1px solid #d6d5d0',
    borderRadius: '3px',
    overflow: 'visible',
    background: 'transparent',
  };

  var headerRowStyle = {
    display: 'grid',
    borderBottom: '1px solid #d6d5d0',
  };

  var headerCellStyle = {
    padding: '1rem 1.25rem',
    borderLeft: '1px solid #d6d5d0',
    textAlign: 'center',
    position: 'relative',
    background: '#e3e3e0',
  };

  var headerCellFirstStyle = {
    padding: '1rem 1.25rem',
    borderLeft: 'none',
    textAlign: 'center',
    position: 'relative',
    background: '#e3e3e0',
  };

  var nameStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.9375rem',
    fontWeight: '600',
    color: '#1b1a18',
    lineHeight: '1.35',
    width: '100%',
    textAlign: 'center',
  };

  var subtitleStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.75rem',
    fontWeight: '400',
    color: '#666259',
    lineHeight: '1.4',
    marginTop: '0.2rem',
    width: '100%',
    textAlign: 'center',
  };

  var recommendedBadgeStyle = {
    position: 'absolute',
    top: '-0.7rem',
    left: '50%',
    transform: 'translateX(-50%)',
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.625rem',
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: '0.1em',
    color: '#fff',
    background: ACCENT,
    padding: '0.2rem 0.5rem',
    borderRadius: '2px',
    lineHeight: '1',
    whiteSpace: 'nowrap',
    zIndex: '1',
  };

  var featureRowStyle = {
    display: 'grid',
    borderTop: '1px solid #d6d5d0',
  };

  var featureRowFirstStyle = {
    display: 'grid',
    borderTop: 'none',
  };

  var featureCellStyle = {
    padding: '0.6rem 1.25rem',
    borderLeft: '1px solid #d6d5d0',
  };

  var featureCellFirstStyle = {
    padding: '0.6rem 1.25rem',
    borderLeft: 'none',
  };

  var featureTextStyle = {
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize: '0.8125rem',
    color: '#4a4844',
    lineHeight: '1.55',
    width: '100%',
  };

  var recommendedHeaderStyle = {
    background: '#f7f7f5',
    boxShadow: 'inset 0 2px 0 ' + ACCENT,
  };

  var recommendedCellStyle = {
    background: '#f7f7f5',
  };

  var BLOCK_ICON = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
    el('path', {
      fill: 'currentColor',
      d: 'M10 18h5V5h-5v13zm-6 0h5V5H4v13zM16 5v13h5V5h-5z',
    })
  );

  // ── Block registration ──────────────────────────────────────────────────────

  registerBlockType('groove-proposal/comparison-columns', {
    title: 'Comparison Columns',
    description: 'Side-by-side columns comparing options or packages with feature rows.',
    icon: BLOCK_ICON,
    category: 'groove-proposal',
    keywords: ['comparison', 'columns', 'packages', 'options', 'plans', 'features', 'versus'],
    attributes: {
      columns: {
        type: 'array',
        default: DEFAULT_COLUMNS,
      },
      recommendedIndex: {
        type: 'number',
        default: 1,
      },
    },
    supports: {
      html: false,
      reusable: true,
    },

    edit: function (props) {
      var attrs = props.attributes;
      var columns = attrs.columns;
      var recommendedIndex = attrs.recommendedIndex;
      var colCount = columns.length;
      var blockProps = useBlockProps();

      var activeState = useState({ type: null, col: -1, row: -1 });
      var active = activeState[0];
      var setActive = activeState[1];

      var onFocus = useCallback(function (type, col, row) {
        setActive({ type: type, col: col, row: row || 0 });
      }, []);

      // Find max features across columns
      var maxFeatures = 0;
      for (var c = 0; c < colCount; c++) {
        if (columns[c].features.length > maxFeatures) {
          maxFeatures = columns[c].features.length;
        }
      }

      function updateColumn(colIdx, field, value) {
        var updated = columns.slice();
        updated[colIdx] = Object.assign({}, updated[colIdx]);
        updated[colIdx][field] = value;
        props.setAttributes({ columns: updated });
      }

      function updateFeature(colIdx, featIdx, value) {
        var updated = columns.slice();
        updated[colIdx] = Object.assign({}, updated[colIdx]);
        updated[colIdx].features = updated[colIdx].features.slice();
        updated[colIdx].features[featIdx] = value;
        props.setAttributes({ columns: updated });
      }

      function addColumn() {
        if (colCount >= MAX_COLS) return;
        var newCol = { name: '', subtitle: '', features: [] };
        // Match feature count
        for (var f = 0; f < maxFeatures; f++) {
          newCol.features.push('');
        }
        props.setAttributes({ columns: columns.concat([newCol]) });
      }

      function removeColumn() {
        if (colCount <= MIN_COLS) return;
        var removeIdx = active.col >= 0 && active.col < colCount ? active.col : colCount - 1;
        var updated = columns.slice();
        updated.splice(removeIdx, 1);
        // Adjust recommended index
        var newRec = recommendedIndex;
        if (removeIdx === recommendedIndex) {
          newRec = -1;
        } else if (removeIdx < recommendedIndex) {
          newRec = recommendedIndex - 1;
        }
        props.setAttributes({ columns: updated, recommendedIndex: newRec });
        if (active.col >= updated.length) {
          setActive({ type: active.type, col: updated.length - 1, row: active.row });
        }
      }

      function addFeatureRow() {
        if (maxFeatures >= MAX_FEATURES) return;
        var updated = columns.map(function (col) {
          var c = Object.assign({}, col);
          c.features = c.features.slice();
          c.features.push('');
          return c;
        });
        props.setAttributes({ columns: updated });
      }

      function removeFeatureRow() {
        if (maxFeatures <= MIN_FEATURES) return;
        var removeIdx = active.type === 'feature' && active.row >= 0 ? active.row : maxFeatures - 1;
        var updated = columns.map(function (col) {
          var c = Object.assign({}, col);
          c.features = c.features.slice();
          if (removeIdx < c.features.length) {
            c.features.splice(removeIdx, 1);
          }
          return c;
        });
        props.setAttributes({ columns: updated });
      }

      function toggleRecommended() {
        if (active.col >= 0 && active.col < colCount) {
          var newRec = active.col === recommendedIndex ? -1 : active.col;
          props.setAttributes({ recommendedIndex: newRec });
        }
      }

      var gridCols = 'repeat(' + colCount + ', 1fr)';

      // Build header cells
      var headerCells = columns.map(function (col, ci) {
        var isRec = ci === recommendedIndex;
        var isActiveCol = active.col === ci;
        var cellBase = ci === 0 ? headerCellFirstStyle : headerCellStyle;
        var cellSt = Object.assign({}, cellBase);
        if (isRec) {
          Object.assign(cellSt, recommendedHeaderStyle);
        }
        if (isActiveCol) {
          cellSt.background = ACTIVE_BG;
        }

        return el('div', {
          key: 'h-' + ci,
          style: cellSt,
          onMouseDownCapture: function () { onFocus('header', ci); },
          onFocusCapture: function () { onFocus('header', ci); },
        },
          isRec ? el('span', { style: recommendedBadgeStyle }, 'Recommended') : null,
          el(RichText, {
            tagName: 'div',
            style: nameStyle,
            value: col.name,
            onChange: function (v) { updateColumn(ci, 'name', v); },
            placeholder: 'Package name',
            allowedFormats: [],
          }),
          el(RichText, {
            tagName: 'div',
            style: subtitleStyle,
            value: col.subtitle,
            onChange: function (v) { updateColumn(ci, 'subtitle', v); },
            placeholder: 'Short description',
            allowedFormats: [],
          })
        );
      });

      // Build feature rows
      var featureRows = [];
      for (var fi = 0; fi < maxFeatures; fi++) {
        (function (rowIdx) {
          var cells = columns.map(function (col, ci) {
            var isRec = ci === recommendedIndex;
            var isActiveCell = active.type === 'feature' && active.col === ci && active.row === rowIdx;
            var cellBase = ci === 0 ? featureCellFirstStyle : featureCellStyle;
            var cellSt = Object.assign({}, cellBase);
            if (isRec) {
              Object.assign(cellSt, recommendedCellStyle);
            }
            if (isActiveCell) {
              cellSt.background = ACTIVE_BG;
            }

            var featureVal = col.features[rowIdx] !== undefined ? col.features[rowIdx] : '';

            return el('div', {
              key: 'f-' + ci + '-' + rowIdx,
              style: cellSt,
              onMouseDownCapture: function () { onFocus('feature', ci, rowIdx); },
              onFocusCapture: function () { onFocus('feature', ci, rowIdx); },
            },
              el(RichText, {
                tagName: 'div',
                style: featureTextStyle,
                value: featureVal,
                onChange: function (v) { updateFeature(ci, rowIdx, v); },
                placeholder: 'Feature\u2026',
                allowedFormats: [],
              })
            );
          });

          var rowSt = Object.assign({}, rowIdx === 0 ? featureRowFirstStyle : featureRowStyle, {
            gridTemplateColumns: gridCols,
          });

          featureRows.push(
            el('div', { key: 'row-' + rowIdx, style: rowSt }, cells)
          );
        })(fi);
      }

      var isActiveColValid = active.col >= 0 && active.col < colCount;
      var isActiveRec = isActiveColValid && active.col === recommendedIndex;

      return el(wp.element.Fragment, null,
        el(BlockControls, null,
          // Column controls
          el(ToolbarGroup, null,
            el(ToolbarButton, {
              icon: 'plus-alt2',
              label: 'Add column',
              onClick: addColumn,
              disabled: colCount >= MAX_COLS,
            }),
            el(ToolbarButton, {
              icon: 'minus',
              label: isActiveColValid ? 'Remove selected column' : 'Remove last column',
              onClick: removeColumn,
              disabled: colCount <= MIN_COLS,
            })
          ),
          // Feature row controls
          el(ToolbarGroup, null,
            el(ToolbarButton, {
              icon: 'table-row-after',
              label: 'Add feature row',
              onClick: addFeatureRow,
              disabled: maxFeatures >= MAX_FEATURES,
            }),
            el(ToolbarButton, {
              icon: 'table-row-delete',
              label: 'Remove feature row',
              onClick: removeFeatureRow,
              disabled: maxFeatures <= MIN_FEATURES,
            })
          ),
          // Recommended toggle
          el(ToolbarGroup, null,
            el(ToolbarButton, {
              icon: 'star-filled',
              label: isActiveColValid
                ? (isActiveRec ? 'Remove recommended badge' : 'Mark as recommended')
                : 'Select a column first',
              onClick: toggleRecommended,
              isPressed: isActiveRec,
              disabled: !isActiveColValid,
            })
          )
        ),
        el('div', blockProps,
          el('div', { style: tableStyle },
            // Header row
            el('div', { style: Object.assign({}, headerRowStyle, { gridTemplateColumns: gridCols }) },
              headerCells
            ),
            // Feature rows
            featureRows
          )
        )
      );
    },

    save: function () {
      return null;
    },
  });
})(window.wp);
