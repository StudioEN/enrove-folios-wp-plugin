// ── Folio page publishing notice (block editor) ───────────────────────────
// Enqueued by Contents\FolioPage\Publishing only while the page's folio is
// unpublished. A page goes live with its folio, so its Publish would fail;
// this says so before anyone presses it, and links to the folio.
(function () {
  var settings = window.ENROVE_PAGE_PUBLISHING
  if (!settings || !settings.message || !window.wp || !wp.data || !wp.domReady) {
    return
  }

  wp.domReady(function () {
    var actions = []
    if (settings.actionUrl && settings.actionLabel) {
      actions.push({ label: settings.actionLabel, url: settings.actionUrl })
    }

    wp.data.dispatch('core/notices').createNotice('warning', settings.message, {
      id: 'enrove-folio-unpublished',
      isDismissible: false,
      actions: actions
    })
  })
})()
