/**
 * The print document (themes/folio-print.php): gets the folio ready for paper,
 * decides where its sheets break, numbers the contents page, and opens the print
 * window.
 *
 * CSS can number a page but cannot say which page a heading landed on
 * (target-counter() is not in any browser), and nothing tells a script where the
 * browser will break a page. Predicting it does not work: measured on screen, a
 * quote wrapped one line sooner than it did on paper, and every later number in
 * the folio was out by one. Media queries see a width of their own in print, too,
 * set by the print window rather than by @page. So this does not predict the
 * breaks, it sets them. The same document is loaded in a hidden frame one page
 * wide and its flow is laid out in columns one sheet tall, so a block too long for
 * a sheet splits as it will on paper. A block that ends within SLACK of the foot of
 * a sheet moves to the next, which is what lets a line or two of difference fit.
 * Every block that starts a sheet gets a forced page break in the real document,
 * so a section's column is its page: the column is what decided it. The rows of a
 * table count as blocks, so that a long one splits where it will on paper.
 */
(function () {
  'use strict';

  var MEASURE_PARAM = 'enrove_print_measure';
  var IMAGE_TIMEOUT = 15000;
  var MEASURE_TIMEOUT = 15000;
  var SLACK = 38; // px: 10mm.
  var SHORT_TABLE = 0.5; // Of a sheet. Up to this a table stays whole; longer ones split between rows.
  var SPACER = 'g-folio-print__spacer';

  // Firefox has no forced column break, so there a spacer does the job.
  var canForceColumn = window.CSS && CSS.supports('break-before', 'column');

  // Firefox has no margin boxes either, so from the version with zoom it gets a
  // footer of ours on each sheet, and leaves its own off (see folio-print.css).
  var needsFeet = !canForceColumn && CSS.supports('zoom', '1');

  var isMeasuring = new URLSearchParams(window.location.search).has(MEASURE_PARAM);

  // Closed <details> do not print, so their contents would be missing.
  function openDetails() {
    document.querySelectorAll('details').forEach(function (details) {
      details.open = true;
    });
  }

  // An embed prints as an empty box. A link at least says what was there.
  function replaceEmbeds() {
    document.querySelectorAll('.g-folio-print__flow iframe').forEach(function (frame) {
      var src = frame.getAttribute('src') || '';
      if (!/^https?:/i.test(src)) {
        return;
      }

      var link = document.createElement('a');
      link.href = src;
      link.textContent = frame.getAttribute('title') || src;

      var paragraph = document.createElement('p');
      paragraph.className = 'g-folio-print__embed';
      paragraph.appendChild(link);
      frame.replaceWith(paragraph);
    });
  }

  // A lazy image far down the document has not loaded when the print window
  // opens, and would print blank.
  function whenImagesLoaded() {
    var pending = [];

    document.querySelectorAll('img').forEach(function (image) {
      image.loading = 'eager';
      if (!image.complete) {
        pending.push(new Promise(function (resolve) {
          image.addEventListener('load', resolve, { once: true });
          image.addEventListener('error', resolve, { once: true });
        }));
      }
    });

    var timeout = new Promise(function (resolve) {
      window.setTimeout(resolve, IMAGE_TIMEOUT);
    });

    return Promise.race([Promise.all(pending), timeout]);
  }

  function whenReady() {
    openDetails();
    replaceEmbeds();

    return whenImagesLoaded().then(function () {
      if (!document.fonts) {
        return null;
      }

      // A browser asks for a web font only once layout needs it, so until then
      // fonts.ready has nothing to wait for and resolves at once. Measured in
      // the fallback font, the contents page's numbers come out a sheet short.
      void document.documentElement.offsetHeight;

      return document.fonts.ready;
    });
  }

  function getTableRows(table) {
    return table.querySelectorAll('tbody tr, tfoot tr');
  }

  // The blocks of a section that a page break can go before, in order: down
  // through plain block containers, and no further than a block that has to stay
  // whole or lays out its own children. A forced break inside a flex or grid item
  // would not reach the page. A table's blocks are its rows.
  function collectBlocks(element, blocks) {
    Array.prototype.forEach.call(element.children, function (child) {
      var style = window.getComputedStyle(child);
      if (style.display === 'none') {
        return;
      }

      if (child.tagName === 'TABLE') {
        blocks.push.apply(blocks, getTableRows(child));
        return;
      }

      var canDescend = (style.display === 'block' || style.display === 'flow-root') && style.breakInside !== 'avoid';
      var hasBlockChild = canDescend && Array.prototype.some.call(child.children, function (grandchild) {
        return window.getComputedStyle(grandchild).display !== 'inline';
      });

      if (hasBlockChild) {
        collectBlocks(child, blocks);
      } else {
        blocks.push(child);
      }
    });
  }

  function getHeight(element) {
    return Array.prototype.reduce.call(element.getClientRects(), function (height, rect) {
      return height + rect.height;
    }, 0);
  }

  // Where to break so that `block` starts a sheet. A heading directly above it has
  // to come too, as a forced break overrules its break-after, and a row brings the
  // table when the table is short or this is its first row, which would otherwise
  // leave the table's head behind.
  function findSheetStart(blocks, position, sheetHeight) {
    var block = blocks[position];
    var table = block.tagName === 'TR' ? block.closest('table') : null;

    if (table) {
      var rows = getTableRows(table);
      var row = Array.prototype.indexOf.call(rows, block);

      if (row === 0 || getHeight(table) <= sheetHeight * SHORT_TABLE) {
        position -= row;
      } else {
        return block;
      }
    }

    var target = position;

    while (target > 0 && /^H[1-6]$/.test(blocks[target - 1].tagName)) {
      target--;
    }

    return blocks[target].tagName === 'TR' ? blocks[target].closest('figure, table') : blocks[target];
  }

  // Fill what is left of a column above `element`, which moves it to the next one.
  function pushToNextColumn(element, box) {
    var top = element.getClientRects()[0].top - box.top;
    if (top <= 1) {
      return;
    }

    // A table takes a row and nothing else, and a spacer has to be one to hold its place.
    var isRow = element.tagName === 'TR';
    var spacer = document.createElement(isRow ? 'tr' : 'div');
    var filler = spacer;

    spacer.className = SPACER;
    if (isRow) {
      filler = spacer.appendChild(document.createElement('td'));
      filler.colSpan = element.cells.length || 1;
      filler.style.border = '0';
      filler.style.padding = '0';
    }

    element.parentNode.insertBefore(spacer, element);

    // The section's zoom scales the spacer too.
    var height = box.height - top;
    filler.style.height = height + 'px';
    var drawn = filler.getBoundingClientRect().height;
    if (drawn > 0) {
      filler.style.height = (height * height / drawn) + 'px';
    }
  }

  // An element's place in `root`, so the other document can find it. That document
  // has no spacers, so they cannot count.
  function getPosition(root, element) {
    return Array.prototype.filter.call(root.getElementsByTagName('*'), function (candidate) {
      return !candidate.closest('.' + SPACER);
    }).indexOf(element);
  }

  // A flex or grid box would give a foot a share of its row, and a box that clips
  // would hide it. A contents line is flex, and its foot takes up no room there.
  function canHoldFoot(element) {
    var style = window.getComputedStyle(element);

    return style.overflowY === 'visible' && (
      /^(block|flow-root|list-item|table-cell)$/.test(style.display) ||
      element.classList.contains('g-folio-print__contents-item')
    );
  }

  // One block on each sheet, to hold its footer, and where a foot put in it would
  // start. A block that runs on to another sheet would take it to the wrong one, so
  // a sheet with nothing else on it has no footer.
  function findFeet(roots, box, columnOf) {
    var feet = {};

    roots.forEach(function (root) {
      root.blocks.forEach(function (block) {
        var rects = block.getClientRects();
        var sheet = columnOf(rects[0]);
        var host = block.tagName === 'TR' ? block.cells[block.cells.length - 1] : block;

        if (feet[sheet] || sheet !== columnOf(rects[rects.length - 1]) || !host || !canHoldFoot(host)) {
          return;
        }

        var probe = document.createElement('div');
        probe.style.cssText = 'flex:none;height:0;width:0';
        host.appendChild(probe);
        var rect = probe.getClientRects()[0];
        probe.remove();

        // An image or a rule takes a child and draws no box for it.
        if (rect) {
          feet[sheet] = {
            root: root.selector,
            index: getPosition(root.element, host),
            sheet: sheet,
            left: rect.left - box.left - sheet * box.width,
            top: rect.top - box.top
          };
        }
      });
    });

    return Object.keys(feet).map(function (sheet) {
      return feet[sheet];
    });
  }

  // In the hidden frame: report each section's first sheet, and the blocks in it
  // that start a sheet, by position so the other document can find the same ones.
  function layOut() {
    var flow = document.querySelector('.g-folio-print__flow');
    var pages = {};
    var breaks = {};
    var feet = [];
    var sheets = 0;

    if (flow) {
      var box = flow.getBoundingClientRect();
      var columnOf = function (rect) {
        return Math.floor((rect.left - box.left + 1) / box.width);
      };

      // The last sheet in use, so that Firefox pushes only what is still on it.
      var contents = flow.querySelector('.g-folio-print__contents');
      var contentsRects = contents ? contents.getClientRects() : [];
      var lastSheet = contentsRects.length ? columnOf(contentsRects[contentsRects.length - 1]) : -1;
      var roots = [];

      if (contents) {
        var contentsBlocks = [];
        collectBlocks(contents, contentsBlocks);
        roots.push({ selector: '.g-folio-print__contents', element: contents, blocks: contentsBlocks });
      }

      // Section by section, in order; a contents page that runs to a second sheet
      // moves every later section along with it.
      flow.querySelectorAll('.g-folio-print__page').forEach(function (section) {
        if (!section.getClientRects().length) {
          return;
        }

        if (!canForceColumn && columnOf(section.getClientRects()[0]) <= lastSheet) {
          pushToNextColumn(section, box);
        }

        var blocks = [];
        var column = columnOf(section.getClientRects()[0]);
        var forced = [];

        collectBlocks(section, blocks);
        blocks = blocks.filter(function (block) {
          return block.getClientRects().length > 0;
        });

        var startSheet = function (block) {
          if (canForceColumn) {
            block.style.breakBefore = 'column';
          } else if (columnOf(block.getClientRects()[0]) <= column) {
            pushToNextColumn(block, box);
          }
          if (forced.indexOf(block) === -1) {
            forced.push(block);
          }
        };

        // In Firefox a section can open with an empty scrap at the foot of a column,
        // its first block already on the next.
        if (blocks.length) {
          column = columnOf(blocks[0].getClientRects()[0]);
        }

        pages[section.id] = column;

        blocks.forEach(function (block, position) {
          var rects = block.getClientRects();
          var first = rects[0];
          var last = rects[rects.length - 1];

          if (columnOf(first) > column) {
            startSheet(findSheetStart(blocks, position, box.height));
          } else if (
            columnOf(first) === columnOf(last) &&
            first.top - box.top > 1 &&
            last.bottom - box.top > box.height - SLACK
          ) {
            var target = findSheetStart(blocks, position, box.height);
            if (target.getClientRects()[0].top - box.top > 1) {
              startSheet(target);
            }
          }

          // Read again: a block moved just now has changed what comes after it.
          rects = block.getClientRects();
          column = Math.max(column, columnOf(rects[rects.length - 1]));
        });

        breaks[section.id] = forced.map(function (block) {
          return getPosition(section, block);
        });

        roots.push({ selector: '#' + section.id, element: section, blocks: blocks });
        lastSheet = column;

        // The next section's first sheet follows this one's last, breaks included.
        pages[section.id] = columnOf((blocks[0] || section).getClientRects()[0]);
      });

      sheets = lastSheet + 1;

      if (needsFeet) {
        feet = findFeet(roots, box, columnOf);
      }
    }

    return { pages: pages, breaks: breaks, feet: feet, sheets: sheets };
  }

  function measure() {
    document.documentElement.classList.add('g-folio-print--measure');

    whenReady().then(function () {
      // A timer, not an animation frame: a hidden frame may never be given one.
      window.setTimeout(function () {
        window.parent.postMessage({ enrovePrintLayout: layOut() }, window.location.origin);
      }, 50);
    });
  }

  // Inside a block on the sheet, and moved down from there. It cannot be absolute: a
  // box that passes the foot of the text area moves to the next sheet, and the foot of
  // the sheet is further down than that. A relative offset moves only the drawing.
  function addFoot(foot, title, total, firstSheet) {
    var element = document.createElement('div');
    var row = element.appendChild(document.createElement('div'));

    element.className = 'g-folio-print__foot';
    element.setAttribute('aria-hidden', 'true');
    element.style.setProperty('--g-folio-print-foot-left', foot.left + 'px');
    element.style.setProperty('--g-folio-print-foot-top', foot.top + 'px');
    row.appendChild(document.createElement('span')).textContent = title;
    row.appendChild(document.createElement('span')).textContent = (foot.sheet + firstSheet) + ' / ' + total;
    foot.host.appendChild(element);
  }

  function applyLayout(layout) {
    var firstSheet = document.querySelector('.g-folio-print__cover') ? 2 : 1;
    var flow = document.querySelector('.g-folio-print__flow');

    Object.keys(layout.pages).forEach(function (id) {
      var link = document.querySelector('.g-folio-print__contents a[href="#' + id + '"]');
      if (link) {
        link.parentNode.setAttribute('data-page', layout.pages[id] + firstSheet);
      }

      var section = document.getElementById(id);
      if (section) {
        var elements = section.getElementsByTagName('*');
        layout.breaks[id].forEach(function (index) {
          if (elements[index]) {
            elements[index].style.breakBefore = 'page';
          }
        });
      }
    });

    // All found before any is added, as a foot moves every position after it.
    layout.feet.forEach(function (foot) {
      var root = document.querySelector(foot.root);
      foot.host = root && root.getElementsByTagName('*')[foot.index];
    });

    layout.feet.forEach(function (foot) {
      if (foot.host) {
        addFoot(foot, flow.getAttribute('data-enrove-print-title'), layout.sheets + firstSheet - 1, firstSheet);
      }
    });
  }

  // Resolves when the layout is applied or given up on. Without it (a site that
  // refuses to be framed, say) the browser breaks the pages itself, and the
  // contents page has links and no numbers.
  function requestLayout() {
    if (!document.querySelector('.g-folio-print__flow')) {
      return Promise.resolve();
    }

    return new Promise(function (resolve) {
      var url = new URL(window.location.href);
      url.searchParams.set(MEASURE_PARAM, '1');
      url.hash = '';

      var frame = document.createElement('iframe');
      frame.src = url.toString();
      frame.setAttribute('aria-hidden', 'true');
      frame.setAttribute('tabindex', '-1');
      frame.style.cssText = 'border:0;height:var(--g-folio-print-height);left:-10000px;position:absolute;top:0;width:var(--g-folio-print-width)';

      function finish() {
        window.removeEventListener('message', onMessage);
        window.clearTimeout(timer);
        frame.remove();
        resolve();
      }

      function onMessage(event) {
        if (event.origin === window.location.origin && event.source === frame.contentWindow && event.data && event.data.enrovePrintLayout) {
          applyLayout(event.data.enrovePrintLayout);
          finish();
        }
      }

      var timer = window.setTimeout(finish, MEASURE_TIMEOUT);
      window.addEventListener('message', onMessage);
      document.body.appendChild(frame);
    });
  }

  if (isMeasuring) {
    window.addEventListener('load', measure);
    return;
  }

  document.querySelectorAll('[data-enrove-print]').forEach(function (button) {
    button.addEventListener('click', function () {
      window.print();
    });
  });

  window.addEventListener('load', function () {
    Promise.all([whenReady(), requestLayout()]).then(function () {
      window.print();
    });
  });
})();
