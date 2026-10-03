// ── Reset confirmation (Settings → Reset) ─────────────────────────────────
// Opens the server-rendered dialog, keeps its destructive button honest about
// what it will do, and shows the "can't be undone" warning only when the
// answer is to delete folios. The reset itself is a plain form post.
(function () {
  var modal = document.getElementById('g-reset-modal');
  if (!modal || typeof window.enroveDialog !== 'function') return;

  var form = modal.querySelector('[data-enrove-reset-form]');
  var submit = modal.querySelector('[data-enrove-reset-submit]');
  var warning = modal.querySelector('[data-enrove-reset-warning]');
  var hasChoice = !!modal.querySelector('input[name="enrove_reset_content"]');
  var dialog = window.enroveDialog(modal);

  function deleting() {
    var checked = modal.querySelector('input[name="enrove_reset_content"]:checked');
    return !!checked && checked.value === 'delete';
  }

  function refresh() {
    submit.textContent = submit.getAttribute(deleting() ? 'data-label-delete' : 'data-label-keep');
    // With folios to choose about, the warning belongs to the delete answer;
    // with none, it is the only thing the dialog has to say and stays shown.
    if (hasChoice && warning) warning.hidden = !deleting();
  }

  document.addEventListener('click', function (e) {
    var target = e.target instanceof Element ? e.target : null;
    if (!target) return;

    if (target.closest('[data-enrove-reset-open]')) {
      // Every opening starts from the safe answer.
      var keep = modal.querySelector('input[name="enrove_reset_content"][value="keep"]');
      if (keep) keep.checked = true;
      refresh();
      dialog.open();
      return;
    }
    if (modal.contains(target) && target.closest('[data-enrove-reset-close]')) {
      dialog.close();
    }
  });

  modal.addEventListener('change', refresh);

  form.addEventListener('submit', function (e) {
    if (submit.disabled) {
      e.preventDefault();
      return;
    }
    // One press, one reset.
    submit.disabled = true;
    submit.textContent = submit.getAttribute('data-label-busy');
  });
})();
