jQuery(function () {
  const settings = window.GROOVE_SETTINGS || {}

  // A form that asks before it submits; the question is its data-groove-confirm.
  jQuery(document).on('submit', 'form[data-groove-confirm]', function (e) {
    if (!window.confirm(String(jQuery(this).attr('data-groove-confirm') || ''))) {
      e.preventDefault()
    }
  })

  // The folio's revision log: Show all / Show less for the older rows.
  jQuery(document).on('click', '[data-groove-revisions-toggle]', function () {
    const toggle = jQuery(this)
    const expanded = toggle.attr('aria-expanded') === 'true'
    jQuery('.g-revision-row-hidden').toggleClass('hidden', expanded)
    toggle
      .attr('aria-expanded', expanded ? 'false' : 'true')
      .text(String(toggle.attr(expanded ? 'data-show-all' : 'data-show-less') || ''))
  })

  function setThemeSectionVisibility(section, isVisible) {
    const $section = jQuery(section)
    const shouldDisableHiddenFields = String($section.data('disable-hidden-fields') || '') === '1'

    if (isVisible) {
      $section.removeClass('hidden')
      if (shouldDisableHiddenFields) {
        $section.find('input, select, textarea, button').prop('disabled', false)
      }
      return
    }

    $section.addClass('hidden')
    if (shouldDisableHiddenFields) {
      $section.find('input, select, textarea, button').prop('disabled', true)
    }
  }

  // A .g-add-new-modal shell fades out, then takes [hidden] back. Until it
  // does, it still covers the page invisibly, so with reduced motion — no
  // transition, so no transitionend — it has to be hidden straight away.
  function hideAfterFade(modalEl) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      modalEl.setAttribute('hidden', '')
      return
    }

    // transitionend bubbles, so a button's own hover fade inside the dialog
    // would end this early. Only the dialog's fade counts.
    const dialogEl = modalEl.querySelector('.g-add-new-modal__dialog')
    modalEl.addEventListener('transitionend', function handler(event) {
      if (event.target !== dialogEl || event.propertyName !== 'opacity') {
        return
      }
      modalEl.removeEventListener('transitionend', handler)
      if (!modalEl.classList.contains('is-open')) {
        modalEl.setAttribute('hidden', '')
      }
    })
  }

  // Theme cards (Add_New::display_theme_card()) are one radiogroup per
  // .g-folio__themes, used by the Add New picker and the folio editor's Change
  // theme dialog. Moving the selection is shared; what it means is not, so
  // each page passes its own onSelect.
  const themeCardSelector = '.g-folio__themes .g-folio__theme-option'

  function markThemeCardSelected(card, moveFocus) {
    const selected = jQuery(card)
    const cards = selected.closest('.g-folio__themes').find('.g-folio__theme-option')

    // hover:border-gray-300 rides along with the resting border colour. It
    // used to be set in the markup only, so the card the page opened on lost
    // its hover state for good once the selection moved off it.
    cards
      .removeClass('border-indigo-600 ring-1 ring-indigo-600')
      .addClass('border-gray-200 hover:border-gray-300')
      .attr('aria-checked', 'false')
      .attr('tabindex', '-1')
    cards.find('.active-badge').addClass('hidden')

    selected
      .removeClass('border-gray-200 hover:border-gray-300')
      .addClass('border-indigo-600 ring-1 ring-indigo-600')
      .attr('aria-checked', 'true')
      .attr('tabindex', '0')
    selected.find('.active-badge').removeClass('hidden')

    if (moveFocus) {
      selected.trigger('focus')
    }
  }

  function bindThemeCards(onSelect) {
    function select(card) {
      markThemeCardSelected(card, true)
      onSelect(jQuery(card))
    }

    jQuery(document).on('click', themeCardSelector, function () {
      select(this)
    })

    // Arrow keys move the selection, as in any radiogroup.
    jQuery(document).on('keydown', themeCardSelector, function (event) {
      const keys = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown']
      if (keys.indexOf(event.key) === -1) {
        return
      }

      event.preventDefault()

      const cards = jQuery(this).closest('.g-folio__themes').find('.g-folio__theme-option')
      const currentIndex = cards.index(this)
      if (currentIndex === -1) {
        return
      }

      const isBack = event.key === 'ArrowLeft' || event.key === 'ArrowUp'
      const nextIndex = (currentIndex + (isBack ? -1 : 1) + cards.length) % cards.length
      const nextCard = cards.get(nextIndex)

      if (nextCard) {
        select(nextCard)
      }
    })
  }

  // Hover/focus tooltip for icon-only controls. Lives at the top of the file,
  // not inside a page branch: the folio editor's font resets and copy-link were
  // the first callers, the Add New sample-content hint is the next, and any
  // screen that needs one should be able to reach it.
  function createAdaptiveTooltip(buttonInput, defaultText) {
    const tooltipBtn = buttonInput && buttonInput.jquery ? buttonInput : jQuery(buttonInput)
    if (!tooltipBtn.length) {
      return null
    }

    let tooltipResetTimer = null
    let tooltipBubble = tooltipBtn.find('.g-tooltip-bubble')
    let tooltipArrow = tooltipBtn.find('.g-tooltip-arrow')

    if (!tooltipBubble.length) {
      tooltipBubble = jQuery('<span class="g-tooltip-bubble" aria-hidden="true"></span>')
      tooltipBtn.append(tooltipBubble)
    }

    if (!tooltipArrow.length) {
      tooltipArrow = jQuery('<span class="g-tooltip-arrow" aria-hidden="true"></span>')
      tooltipBtn.append(tooltipArrow)
    }

    // The tooltip text doubles as the accessible name, unless the control
    // opts out with data-tooltip-keeps-label: then the tooltip can be a word
    // for the eye while aria-label stays the full name.
    const keepsLabel = tooltipBtn.is('[data-tooltip-keeps-label]')

    function setTooltipText(text) {
      const normalizedText = String(text || '')
      tooltipBubble.text(normalizedText)
      tooltipBtn.attr('data-tooltip-text', normalizedText)
      if (normalizedText && !keepsLabel) {
        tooltipBtn.attr('aria-label', normalizedText)
      }
    }

    function clamp(value, min, max) {
      if (max < min) {
        return min
      }
      return Math.max(min, Math.min(value, max))
    }

    function updateTooltipPlacement() {
      const buttonNode = tooltipBtn.get(0)
      const tooltipNode = tooltipBubble.get(0)
      if (!buttonNode || !tooltipNode) {
        return
      }

      const buttonRect = buttonNode.getBoundingClientRect()
      const tooltipRect = tooltipNode.getBoundingClientRect()
      if (!tooltipRect.width || !tooltipRect.height) {
        return
      }

      const viewportWidth = window.innerWidth || document.documentElement.clientWidth
      const viewportHeight = window.innerHeight || document.documentElement.clientHeight
      const adminBar = document.getElementById('wpadminbar')
      let topInset = 0
      if (adminBar) {
        const adminBarRect = adminBar.getBoundingClientRect()
        if (adminBarRect.bottom > 0) {
          topInset = adminBarRect.bottom
        }
      }

      const viewportPadding = 8
      const tooltipGap = 8
      const tooltipArrowSize = 5
      const safeTop = Math.max(viewportPadding, topInset + viewportPadding)

      const requiredVerticalSpace = tooltipRect.height + tooltipGap + tooltipArrowSize
      const requiredHorizontalSpace = tooltipRect.width + tooltipGap + tooltipArrowSize

      const availableTop = buttonRect.top - safeTop
      const availableBottom = viewportHeight - buttonRect.bottom - viewportPadding
      const availableLeft = buttonRect.left - viewportPadding
      const availableRight = viewportWidth - buttonRect.right - viewportPadding

      let placement = 'right'
      if (availableTop >= requiredVerticalSpace) {
        placement = 'top'
      } else if (availableBottom >= requiredVerticalSpace) {
        placement = 'bottom'
      } else if (availableLeft >= requiredHorizontalSpace) {
        placement = 'left'
      } else if (availableRight >= requiredHorizontalSpace) {
        placement = 'right'
      }

      let shiftX = 0
      let shiftY = 0
      if (placement === 'top' || placement === 'bottom') {
        const anchorCenterX = buttonRect.left + (buttonRect.width / 2)
        const minCenterX = viewportPadding + (tooltipRect.width / 2)
        const maxCenterX = viewportWidth - viewportPadding - (tooltipRect.width / 2)
        const clampedCenterX = clamp(anchorCenterX, minCenterX, maxCenterX)
        shiftX = clampedCenterX - anchorCenterX
      } else {
        const anchorCenterY = buttonRect.top + (buttonRect.height / 2)
        const minCenterY = safeTop + (tooltipRect.height / 2)
        const maxCenterY = viewportHeight - viewportPadding - (tooltipRect.height / 2)
        const clampedCenterY = clamp(anchorCenterY, minCenterY, maxCenterY)
        shiftY = clampedCenterY - anchorCenterY
      }

      tooltipBtn.attr('data-tooltip-placement', placement)
      buttonNode.style.setProperty('--g-tooltip-shift-x', shiftX + 'px')
      buttonNode.style.setProperty('--g-tooltip-shift-y', shiftY + 'px')
    }

    function showTooltip(text, autoHideMs = 0, resetText = '') {
      if (typeof text !== 'undefined') {
        setTooltipText(text)
      }
      updateTooltipPlacement()
      tooltipBtn.addClass('is-tooltip-visible')
      clearTimeout(tooltipResetTimer)
      if (autoHideMs > 0) {
        tooltipResetTimer = setTimeout(function () {
          if (resetText !== '') {
            setTooltipText(resetText)
          }
          tooltipBtn.removeClass('is-tooltip-visible')
        }, autoHideMs)
      }
    }

    const resolvedDefaultText = String(defaultText || tooltipBtn.data('tooltip-text') || tooltipBtn.attr('aria-label') || '')
    setTooltipText(resolvedDefaultText)
    tooltipBtn.attr('data-tooltip-placement', 'top')

    tooltipBtn.on('mouseenter focus', function () {
      updateTooltipPlacement()
    })

    jQuery(window).on('resize scroll', function () {
      if (tooltipBtn.hasClass('is-tooltip-visible') || tooltipBtn.is(':hover') || tooltipBtn.is(':focus')) {
        updateTooltipPlacement()
      }
    })

    return {
      show: showTooltip,
    }
  }

  const Groove = Object.create({
    screenId: settings.screenId || window.GROOVE_SCREEN_ID || '',
    themeId: settings.themeId || window.GROOVE_THEME_ID || null,
    adminPostUrl: settings.adminPostUrl || window.GROOVE_ADMIN_POST_URL || '/wp-admin/admin-post.php',

    isPostPage() {
      return this.screenId === 'groove_folio_page' && window.GROOVE_POST
    },

    isAddNewPage() {
      const page = new URLSearchParams(window.location.search).get('page')
      return page === 'groove-add-new' || page === 'groove-all-folios'
    },

    isFolioPage() {
      const page = new URLSearchParams(window.location.search).get('page')
      return page === 'groove-folio'
    },

    isThemesPage() {
      const page = new URLSearchParams(window.location.search).get('page')
      return page === 'groove-themes'
    },

    isPreview() {
      return !!window.GROOVE_IS_PREVIEW
    }
  })

  jQuery('.g-top-bar-tabs .g-folio__nav-tab').click(function () {
    if (!jQuery(this).hasClass('g-folio__nav-tab-active')) {
      jQuery('.g-top-bar-tabs .g-folio__nav-tab').removeClass('g-folio__nav-tab-active')
      jQuery(this).addClass('g-folio__nav-tab-active')

      const tabId = jQuery(this).data('tab-id');

      jQuery('[data-tab-content-id]').css('display', 'none');
      jQuery('[data-tab-content-id="' + tabId + '"]').css('display', 'block');
    }
  })

  function initCollectionTagsCombobox(inputEl) {
    const allTags = JSON.parse(inputEl.dataset.tags || '[]')

    // Wrap input in a relative-positioned container so the dropdown anchors to it
    const wrapper = document.createElement('div')
    wrapper.style.position = 'relative'
    inputEl.parentNode.insertBefore(wrapper, inputEl)
    wrapper.appendChild(inputEl)

    const dropdown = document.createElement('ul')
    dropdown.className = 'g-tag-dropdown'
    wrapper.appendChild(dropdown)

    let activeIndex = -1

    function getCurrentToken() {
      const val = inputEl.value
      const lastComma = val.lastIndexOf(',')
      return lastComma === -1 ? val.trim() : val.slice(lastComma + 1).trim()
    }

    function getAssignedTags() {
      return inputEl.value.split(',').map(t => t.trim().toLowerCase()).filter(Boolean)
    }

    function renderDropdown(token) {
      const assigned = getAssignedTags()
      const lowerToken = token.toLowerCase()

      const matches = allTags.filter(tag => {
        const lowerTag = tag.toLowerCase()
        return lowerTag.includes(lowerToken) && !assigned.includes(lowerTag)
      })

      const hasExactMatch = allTags.some(tag => tag.toLowerCase() === lowerToken)
      const showCreate = token.length > 0 && !hasExactMatch

      dropdown.innerHTML = ''
      activeIndex = -1

      matches.forEach(tag => {
        const li = document.createElement('li')
        li.textContent = tag
        li.addEventListener('mousedown', e => { e.preventDefault(); selectTag(tag) })
        dropdown.appendChild(li)
      })

      if (showCreate) {
        const li = document.createElement('li')
        li.className = 'g-tag-dropdown__create'
        li.textContent = 'Create "' + token + '"'
        li.addEventListener('mousedown', e => { e.preventDefault(); selectTag(token) })
        dropdown.appendChild(li)
      }

      dropdown.classList.toggle('is-open', dropdown.children.length > 0)
    }

    function selectTag(tag) {
      const val = inputEl.value
      const lastComma = val.lastIndexOf(',')
      inputEl.value = (lastComma === -1 ? '' : val.slice(0, lastComma + 1) + ' ') + tag + ', '
      closeDropdown()
      inputEl.focus()
      inputEl.dispatchEvent(new Event('input', { bubbles: true }))
    }

    function closeDropdown() {
      dropdown.classList.remove('is-open')
      activeIndex = -1
    }

    function updateActiveItem() {
      Array.from(dropdown.children).forEach((li, i) => {
        li.classList.toggle('is-active', i === activeIndex)
      })
    }

    inputEl.addEventListener('input', () => renderDropdown(getCurrentToken()))
    inputEl.addEventListener('focus', () => renderDropdown(getCurrentToken()))
    inputEl.addEventListener('blur', () => setTimeout(closeDropdown, 150))

    inputEl.addEventListener('keydown', e => {
      const items = dropdown.querySelectorAll('li')
      if (!items.length || !dropdown.classList.contains('is-open')) return

      if (e.key === 'ArrowDown') {
        e.preventDefault()
        activeIndex = Math.min(activeIndex + 1, items.length - 1)
        updateActiveItem()
      } else if (e.key === 'ArrowUp') {
        e.preventDefault()
        activeIndex = Math.max(activeIndex - 1, 0)
        updateActiveItem()
      } else if (e.key === 'Enter' && activeIndex >= 0) {
        e.preventDefault()
        const text = items[activeIndex].textContent
        const createMatch = text.match(/^Create "(.+)"$/)
        selectTag(createMatch ? createMatch[1] : text)
      } else if (e.key === 'Escape') {
        closeDropdown()
      }
    })
  }

  window.grooveInitCollectionTagsCombobox = initCollectionTagsCombobox

  if (Groove.isFolioPage()) {
    const folioForm = jQuery('form[action*="admin-post.php"]')
      .has('input[name="folio_id"]')
      .has('input[name="groove_nonce"]')
      .first()

    createAdaptiveTooltip('#g-folio-preview-link')

    const collectionTagsInput = document.getElementById('collection_tags')
    if (collectionTagsInput && collectionTagsInput.dataset.tags !== undefined) {
      initCollectionTagsCombobox(collectionTagsInput)
    }

    if (!folioForm.length) {
      return
    }

    const nonFolioSaveScopeSelector = '#pages-filter, [data-tab-content-id="pages"]'

    function isWithinNonFolioSaveScope(element) {
      return jQuery(element).closest(nonFolioSaveScopeSelector).length > 0
    }

    let autosaveTimer = null
    let isManualSave = false
    let lastAutosaveHash = ''
    const saveIndicatorAutoHideMs = 3000
    // Saving, then Saved or the failure, in the one toast stack every Groove
    // notification uses: one pill, rewritten in place as the save runs.
    const saveStatus = typeof window.grooveStatusToast === 'function' ? window.grooveStatusToast() : null
    const saveStatusTypes = { saving: 'info', saved: 'success', error: 'error' }

    function setSaveStatus(state, message, autoHideMs = 0) {
      if (!saveStatus) {
        return
      }

      saveStatus.set(message, saveStatusTypes[state] || 'info', autoHideMs)
    }

    function getFields() {
      const fields = {}

      if (!folioForm.length) {
        return fields
      }

      folioForm.find('input, select, textarea').each(function () {
        const field = jQuery(this)
        const name = String(field.attr('name') || '')
        if (!name || name === 'action' || isWithinNonFolioSaveScope(field)) {
          return
        }

        if (field.is(':disabled')) {
          return
        }

        const tagName = (this.tagName || '').toLowerCase()
        if (tagName === 'input') {
          const type = String(field.attr('type') || 'text').toLowerCase()
          if (['submit', 'button', 'image', 'reset', 'file'].indexOf(type) !== -1) {
            return
          }

          if ((type === 'checkbox' || type === 'radio') && !field.is(':checked')) {
            return
          }
        }

        fields[name] = field.val()
      })

      if (!fields.folio_id) {
        const urlParams = new URLSearchParams(window.location.search)
        const folioId = urlParams.get('folio_id')
        if (folioId) {
          fields.folio_id = folioId
        }
      }

      return fields
    }

    // Save is live only while the form differs from what the server last
    // stored — by an autosave, a manual save or a publish — the same rule as
    // the Settings forms (groove-form-state.js). The revision note is left out:
    // Save does not store it, only Publish reads it.
    const saveButton = jQuery('button[value="save_groove_folio_manual"]').first()
    let isSaveBusy = false

    function savedStateOf(fields) {
      const state = Object.assign({}, fields)
      delete state.groove_nonce
      delete state.proposal_revision_note
      return JSON.stringify(state)
    }

    let lastSavedState = ''

    function refreshSaveButton() {
      if (!saveButton.length) {
        return
      }

      const idle = isSaveBusy || savedStateOf(getFields()) === lastSavedState
      saveButton.toggleClass('disabled', idle)
      saveButton.attr('aria-disabled', idle ? 'true' : 'false')
    }

    function ajax(status, options = {}) {
      const shouldReload = !!options.reload
      const reloadDelayMs = Number(options.reloadDelayMs || 0)
      const shouldUpdatePermalink = !!options.updatePermalink
      const silent = !!options.silent
      const savingText = options.savingText || 'Saving...'
      const savedText = options.savedText || 'Saved'
      const errorText = options.errorText || 'Save failed'
      const fields = getFields()
      const sentState = savedStateOf(fields)
      const nonce = fields.groove_nonce || folioForm.find('input[name="groove_nonce"]').first().val()

      if (!fields.folio_id || !nonce) {
        setSaveStatus('error', 'Save failed', saveIndicatorAutoHideMs)
        if (!silent) {
          // eslint-disable-next-line no-console
          console.error('Groove save failed: missing folio_id or nonce.')
        }
        return jQuery.Deferred().reject().promise()
      }

      setSaveStatus('saving', savingText)

      return jQuery.ajax({
        url: Groove.adminPostUrl,
        method: 'post',
        data: Object.assign({
          action: status,
          groove_nonce: nonce
        }, fields),
        dataType: 'json',
      }).done(function (result) {
        if (result && result.code === 0) {
          setSaveStatus('saved', savedText, saveIndicatorAutoHideMs)
          lastSavedState = sentState
          refreshSaveButton()

          if (shouldUpdatePermalink && result.slug) {
            jQuery('#permalink').val(result.slug)
          }

          if (result.copy_link) {
            jQuery('#g-copy-folio-link').data('copy-link', result.copy_link)
          }

          if (shouldReload) {
            if (reloadDelayMs > 0) {
              setTimeout(function () {
                location.reload()
              }, reloadDelayMs)
            } else {
              location.reload()
            }
          }
        } else if (!silent) {
          setSaveStatus('error', errorText, saveIndicatorAutoHideMs)
          // eslint-disable-next-line no-console
          console.error('Groove save failed.', result)
        } else {
          setSaveStatus('error', errorText, saveIndicatorAutoHideMs)
        }
      }).fail(function (xhr) {
        setSaveStatus('error', errorText, saveIndicatorAutoHideMs)
        if (!silent) {
          // eslint-disable-next-line no-console
          console.error('Groove save request failed.', xhr)
        }
      })
    }

    function scheduleAutoSave(reason) {
      if (!folioForm.length || isManualSave) {
        return
      }

      clearTimeout(autosaveTimer)

      const delay = reason === 'title-blur' ? 120 : 800
      autosaveTimer = setTimeout(function () {
        const fields = getFields()
        const payloadHash = JSON.stringify(fields)

        if (payloadHash === lastAutosaveHash && reason !== 'title-blur') {
          return
        }

        ajax('auto_save_groove_folio', {
          reload: false,
          updatePermalink: reason === 'title-blur',
          silent: true,
          savingText: 'Saving draft...',
          savedText: 'Draft saved',
          errorText: 'Autosave failed'
        }).done(function (result) {
          if (result && result.code === 0) {
            lastAutosaveHash = payloadHash
            if (result.slug && reason === 'title-blur') {
              jQuery('#permalink').val(result.slug)
            }
          }
        })
      }, delay)
    }

    jQuery('#title').on('blur', function () {
      // Save automatically and refresh permalink after title changes.
      scheduleAutoSave('title-blur')
    })

    if (folioForm.length) {
      folioForm.on('input change', 'input, select, textarea', function () {
        if (window.grooveHideToggletip && !isWithinNonFolioSaveScope(this)) {
          // They are editing again; whatever the last press was told has
          // served its purpose.
          window.grooveHideToggletip()
        }
        refreshSaveButton()

        const name = this.name || ''
        if (!name || name === 'groove_nonce' || name === 'folio_id' || name === 'action' || name === 'permalink' || name === 'proposal_revision_note') {
          return
        }

        if (isWithinNonFolioSaveScope(this)) {
          return
        }

        if (name === 'title') {
          return
        }

        scheduleAutoSave('field-change')
      })
    }

    // Publish and Unpublish change every page's status too, so the header
    // button asks first (Folio::display_publish_dialog()); the dialog's
    // confirm runs the save the button would have.
    const publishDialogEl = document.getElementById('g-publish-modal')
    let publishDialog = null
    let pendingPublishBtn = null

    if (publishDialogEl) {
      publishDialogEl.addEventListener('click', function (e) {
        const target = e.target instanceof Element ? e.target : null
        if (!target || !publishDialog) {
          return
        }
        if (target.closest('[data-groove-publish-close]')) {
          pendingPublishBtn = null
          publishDialog.close()
        } else if (target.closest('[data-groove-publish-go]')) {
          const btn = pendingPublishBtn
          pendingPublishBtn = null
          publishDialog.close()
          if (btn) {
            runPublish(btn)
          }
        }
      })
    }

    jQuery('button[value="save_groove_folio"], button[value="save_groove_folio_unpublish"]').click(function (e) {
      e.preventDefault()
      const publishBtn = jQuery(this)

      if (publishDialogEl && publishBtn.is('[data-groove-publish-confirm]') && typeof window.grooveDialog === 'function') {
        publishDialog = publishDialog || window.grooveDialog(publishDialogEl, {
          onClose: function () {
            pendingPublishBtn = null
          }
        })
        pendingPublishBtn = publishBtn
        publishDialog.open()
        return
      }

      runPublish(publishBtn)
    })

    function runPublish(publishBtn) {
      const action = publishBtn.val()
      const isUnpublish = action === 'save_groove_folio_unpublish'
      const originalLabel = publishBtn.text()
      // A user who cannot publish gets Submit for Review, with its own labels.
      const savingLabel = publishBtn.data('saving-text') || (isUnpublish ? 'Unpublishing...' : 'Publishing...')
      const savedLabel = publishBtn.data('saved-text') || (isUnpublish ? 'Unpublished' : 'Published')
      const errorLabel = publishBtn.data('error-text') || (isUnpublish ? 'Unpublish failed' : 'Publish failed')

      isManualSave = true
      clearTimeout(autosaveTimer)
      publishBtn.text(savingLabel)
      ajax(action, {
        reload: true,
        reloadDelayMs: saveIndicatorAutoHideMs,
        updatePermalink: true,
        savingText: savingLabel,
        savedText: savedLabel,
        errorText: errorLabel
      }).always(function () {
        isManualSave = false
        publishBtn.text(originalLabel)
      })
    }

    jQuery('button[value="save_groove_folio_manual"], button[value="save_groove_folio_draft"]').click(function (e) {
      const saveBtn = jQuery(this)
      const action = saveBtn.val()
      const originalLabel = saveBtn.text()

      e.preventDefault()

      if (saveBtn.attr('aria-disabled') === 'true') {
        if (!isSaveBusy && window.grooveShowToggletip) {
          window.grooveShowToggletip(this, {
            message: String(saveBtn.data('groove-save-idle') || ''),
            type: 'info',
            duration: 4000
          })
        }
        return
      }

      isManualSave = true
      clearTimeout(autosaveTimer)
      saveBtn.text('Saving...')
      // Switched off while in flight, so a double press cannot save twice.
      isSaveBusy = true
      refreshSaveButton()
      ajax(action, {
        reload: false,
        updatePermalink: true,
        savingText: 'Saving...',
        savedText: 'Saved',
        errorText: 'Save failed'
      }).always(function () {
        isManualSave = false
        isSaveBusy = false
        saveBtn.text(originalLabel)
        refreshSaveButton()
      })
    })

    // The baseline is taken once the rest of this handler has run, so a field
    // an initialiser below adjusts on load does not count as an edit.
    setTimeout(function () {
      lastSavedState = savedStateOf(getFields())
      refreshSaveButton()
    }, 0)

    // Coming back to the editor from the browser's cache: the fields may hold
    // values the server never saw, so the state has to be worked out again.
    window.addEventListener('pageshow', function (event) {
      if (event.persisted) {
        isSaveBusy = false
        refreshSaveButton()
      }
    })

    createAdaptiveTooltip('#g-reset-header-font', 'Reset font')
    createAdaptiveTooltip('#g-reset-body-font', 'Reset font')

    const copyLinkBtn = jQuery('#g-copy-folio-link')
    if (copyLinkBtn.length) {
      const copyTooltipText = String(copyLinkBtn.data('copy-text') || 'Copy link')
      const copiedTooltipText = String(copyLinkBtn.data('copied-text') || 'Copied')
      const copyTooltip = createAdaptiveTooltip(copyLinkBtn, copyTooltipText)

      function fallbackCopy(text) {
        const textArea = document.createElement('textarea')
        textArea.value = text
        textArea.setAttribute('readonly', '')
        textArea.style.position = 'absolute'
        textArea.style.left = '-9999px'

        document.body.appendChild(textArea)
        textArea.select()

        try {
          return document.execCommand('copy')
        } catch (error) {
          return false
        } finally {
          document.body.removeChild(textArea)
        }
      }

      function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
          return navigator.clipboard.writeText(text)
        }

        if (fallbackCopy(text)) {
          return Promise.resolve()
        }

        return Promise.reject(new Error('copy_failed'))
      }

      copyLinkBtn.on('click', function (event) {
        event.preventDefault()

        const linkToCopy = String(copyLinkBtn.data('copy-link') || '').trim()
        if (!linkToCopy) {
          return
        }

        copyText(linkToCopy).then(function () {
          if (copyTooltip) {
            copyTooltip.show(copiedTooltipText, 1800, copyTooltipText)
          }
        }).catch(function () {
          if (copyTooltip) {
            copyTooltip.show(copyTooltipText, 1200, copyTooltipText)
          }
        })
      })
    }

    function bindMediaSelector(options) {
      const openButton = jQuery(options.openButton)
      if (!openButton.length) {
        return
      }

      openButton.click(function (event) {
        event.preventDefault()
        const customUploader = wp.media({
          title: 'Select',
          button: {
            text: 'Select'
          },
          multiple: false
        })

        customUploader.on('select', function () {
          const attachment = customUploader.state().get('selection').first().toJSON()
          jQuery(options.hiddenInput).val(attachment.id).trigger('change')
          jQuery(options.previewImage).attr('src', attachment.url)
        })

        customUploader.open()
      })

      const defaultButton = jQuery(options.defaultButton)
      if (!defaultButton.length) {
        return
      }

      defaultButton.click(function () {
        jQuery(options.hiddenInput).val('').trigger('change')
        const defaultUrl = jQuery(this).data('default-url')
        jQuery(options.previewImage).attr('src', defaultUrl)
      })
    }

    bindMediaSelector({
      openButton: '#feature-image',
      hiddenInput: '#feature-media-id',
      previewImage: '#feature-preview',
      defaultButton: '#use-default-image'
    })

    bindMediaSelector({
      openButton: '#logo-image',
      hiddenInput: '#logo-media-id',
      previewImage: '#logo-preview',
      defaultButton: '#use-default-logo'
    })

    // Client logo: stores URL instead of attachment ID.
    ;(function () {
      const selectBtn = jQuery('#g-client-logo-select')
      if (!selectBtn.length) {
        return
      }

      const hiddenInput = jQuery('#proposal_client_logo_url')
      const previewImg = jQuery('#g-client-logo-preview')
      const previewFrame = jQuery('#g-client-logo-preview-frame')
      const defaultBtn = jQuery('#g-client-logo-default')
      const removeBtn = jQuery('#g-client-logo-remove')

      function showPreview(url) {
        previewImg.attr('src', url)
        previewFrame.removeClass('hidden')
        defaultBtn.removeClass('hidden')
        removeBtn.removeClass('hidden')
        selectBtn.text('Replace logo')
      }

      function clearPreview() {
        hiddenInput.val('').trigger('change')
        previewFrame.addClass('hidden')
        defaultBtn.addClass('hidden')
        removeBtn.addClass('hidden')
        selectBtn.text('Select logo')
      }

      selectBtn.on('click', function (e) {
        e.preventDefault()
        var uploader = wp.media({
          title: 'Select Client Logo',
          button: { text: 'Use this logo' },
          multiple: false,
          library: { type: 'image' }
        })

        uploader.on('select', function () {
          var attachment = uploader.state().get('selection').first().toJSON()
          hiddenInput.val(attachment.url).trigger('change')
          showPreview(attachment.url)
        })

        uploader.open()
      })

      defaultBtn.on('click', function () {
        var defaultUrl = jQuery(this).data('default-url')
        hiddenInput.val(defaultUrl).trigger('change')
        showPreview(defaultUrl)
      })

      removeBtn.on('click', function () {
        clearPreview()
      })
    })()

    function resetFontSelect(selectId) {
      const fontSelect = jQuery(selectId)
      if (!fontSelect.length) {
        return
      }

      fontSelect.val('').trigger('change')
    }

    jQuery('#g-reset-header-font').click(function () {
      resetFontSelect('#g-header-font')
    })

    jQuery('#g-reset-body-font').click(function () {
      resetFontSelect('#g-body-font')
    })

    function bindProposalInformationModal() {
      const modal = jQuery('#g-proposal-info-modal')
      if (!modal.length) {
        return {
          close: function () {},
          syncSummary: function () {}
        }
      }

      const openButtons = jQuery('[data-g-proposal-info-open]')
      const closeButtons = jQuery('[data-g-proposal-info-close]')
      const body = jQuery('body')
      let previousBodyOverflow = ''
      let activeTrigger = null

      function syncProposalSummary() {
        jQuery('[data-g-proposal-summary-source]').each(function () {
          const summaryItem = jQuery(this)
          const sourceSelector = String(summaryItem.data('g-proposal-summary-source') || '')
          if (!sourceSelector) {
            return
          }

          const sourceField = jQuery(sourceSelector).first()
          if (!sourceField.length) {
            return
          }

          const summaryType = String(summaryItem.data('summary-type') || '')
          let value = ''

          if (summaryType === 'boolean') {
            const trueLabel = String(summaryItem.data('true-label') || 'Yes')
            const falseLabel = String(summaryItem.data('false-label') || 'No')
            value = sourceField.is(':checked') ? trueLabel : falseLabel
          } else {
            value = String(sourceField.val() || '').trim()
            if (!value) {
              value = String(summaryItem.data('empty-label') || 'Not set')
            }
          }

          summaryItem.text(value)
        })
      }

      function openModal() {
        if (!modal.hasClass('hidden')) {
          return
        }

        activeTrigger = jQuery(document.activeElement)
        previousBodyOverflow = body.css('overflow')
        body.css('overflow', 'hidden')
        modal.removeClass('hidden').css('display', 'flex').attr('aria-hidden', 'false')

        window.requestAnimationFrame(function () {
          const firstFocusable = modal.find('input:not([type="hidden"]), select, textarea, button').filter(':visible').first()
          if (firstFocusable.length) {
            firstFocusable.trigger('focus')
          }
        })
      }

      function closeModal() {
        if (modal.hasClass('hidden')) {
          return
        }

        modal.addClass('hidden').css('display', 'none').attr('aria-hidden', 'true')
        body.css('overflow', previousBodyOverflow)
        if (activeTrigger && activeTrigger.length) {
          activeTrigger.trigger('focus')
        }
      }

      openButtons.on('click', function (event) {
        event.preventDefault()
        openModal()
      })

      closeButtons.on('click', function (event) {
        event.preventDefault()
        closeModal()
      })

      jQuery(document).on('keydown.gProposalInfo', function (event) {
        if (event.key !== 'Escape' || modal.hasClass('hidden')) {
          return
        }

        event.preventDefault()
        closeModal()
      })

      jQuery(document).on('input.gProposalSummary change.gProposalSummary', '#g-proposal-info-modal input, #g-proposal-info-modal select, #g-proposal-info-modal textarea', function () {
        syncProposalSummary()
      })

      syncProposalSummary()

      return {
        close: closeModal,
        syncSummary: syncProposalSummary
      }
    }

    const proposalInformationModal = bindProposalInformationModal()

    function bindVersionHistoryModal() {
      const modal = jQuery('#g-version-history-modal')
      if (!modal.length) {
        return {
          close() {}
        }
      }

      let lastFocusedElement = null

      function openModal() {
        if (!modal.hasClass('hidden')) {
          return
        }

        lastFocusedElement = document.activeElement
        modal.removeClass('hidden').attr('aria-hidden', 'false')

        window.requestAnimationFrame(function () {
          const firstFocusable = modal.find('button').filter(':visible').first()
          if (firstFocusable.length) {
            firstFocusable.trigger('focus')
          }
        })
      }

      function closeModal() {
        if (modal.hasClass('hidden')) {
          return
        }

        modal.addClass('hidden').attr('aria-hidden', 'true')

        if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
          lastFocusedElement.focus()
        }
      }

      jQuery(document).on('click.gVersionHistoryOpen', '[data-version-history-open]', function (event) {
        event.preventDefault()
        openModal()
      })

      jQuery(document).on('click.gVersionHistoryClose', '[data-version-history-close]', function (event) {
        event.preventDefault()
        closeModal()
      })

      jQuery(document).on('keydown.gVersionHistory', function (event) {
        if (event.key !== 'Escape' || modal.hasClass('hidden')) {
          return
        }

        event.preventDefault()
        closeModal()
      })

      return {
        close: closeModal
      }
    }

    const versionHistoryModal = bindVersionHistoryModal()

    function bindThemePickerModal() {
      const modal = jQuery('#g-theme-picker-modal')
      if (!modal.length) {
        return {
          open() {},
          close() {},
          stage() {}
        }
      }

      const modalEl = modal[0]
      const activeInput = jQuery('#g-active-theme-id')
      const pickScreen = modal.find('[data-theme-switch-pick]')
      const applyButton = modal.find('[data-theme-switch-apply]')
      const confirmBox = modal.find('[data-theme-switch-confirm]')
      const title = modal.find('#g-theme-picker-modal-title')
      const pickTitle = title.text().trim()
      let lastFocusedElement = null
      let stagedThemeId = ''

      // Pages whose blocks belong to each theme, as finished sentences keyed
      // by the theme being left (Folio::get_theme_switch_impact()).
      let blockImpact = {}
      try {
        blockImpact = JSON.parse(modal.attr('data-theme-switch-impact') || '{}') || {}
      } catch (error) {
        blockImpact = {}
      }

      function currentThemeId() {
        return String(activeInput.val() || '')
      }

      function findCard(themeId) {
        return modal.find('.g-folio__theme-option').filter(function () {
          return String(jQuery(this).data('theme-id')) === themeId
        }).first()
      }

      function themeName(themeId) {
        return String(findCard(themeId).data('theme-name') || themeId)
      }

      function fillName(attribute, themeId) {
        return String(confirmBox.attr(attribute) || '').replace(/%(1\$)?s/, themeName(themeId))
      }

      // The Proposal tab's fields when the tab is on the page — they hold what
      // was typed or cleared since load, and autosave keeps it — otherwise
      // what was stored. The tab is only rendered for a folio that loaded on
      // Groove Proposal.
      function hasProposalDetails() {
        const names = String(confirmBox.attr('data-proposal-detail-fields') || '').split(',')
        const fields = activeInput.closest('form').find(names.map(function (name) {
          return '[name="' + name + '"]'
        }).join(','))

        if (!fields.length) {
          return confirmBox.attr('data-proposal-details-stored') === '1'
        }

        return fields.toArray().some(function (field) {
          return !field.disabled && String(jQuery(field).val() || '').trim() !== ''
        })
      }

      // What leaving themeId takes off the folio, one sentence per line.
      function impactOfLeaving(themeId) {
        const items = Array.isArray(blockImpact[themeId]) ? blockImpact[themeId].slice() : []
        if (themeId === 'groove-proposal' && hasProposalDetails()) {
          items.push(String(confirmBox.attr('data-proposal-details') || ''))
        }
        return items
      }

      // Each screen slides in from the side it sits on: the confirm from the
      // right, the grid back from the left. Restarted by class, so it runs
      // every time rather than only on the first show.
      function enterScreen(screen, className) {
        const el = screen[0]
        el.classList.remove('is-entering-forward', 'is-entering-back')
        void el.offsetWidth
        el.classList.add(className)
      }

      function showPickScreen(animate) {
        confirmBox.prop('hidden', true).css('min-height', '')
        pickScreen.prop('hidden', false)
        title.text(pickTitle)
        if (animate) {
          enterScreen(pickScreen, 'is-entering-back')
        }
      }

      // The confirm screen stands where the grid was and takes its height, so
      // the dialog neither jumps nor grows to ask.
      function showConfirmScreen(leaving, items) {
        const pickHeight = pickScreen.outerHeight()

        confirmBox.find('[data-theme-switch-from-thumb]').attr('src', String(findCard(leaving).data('theme-thumbnail-url') || ''))
        confirmBox.find('[data-theme-switch-from-name]').text(themeName(leaving))
        confirmBox.find('[data-theme-switch-to-thumb]').attr('src', String(findCard(stagedThemeId).data('theme-thumbnail-url') || ''))
        confirmBox.find('[data-theme-switch-to-name]').text(themeName(stagedThemeId))

        confirmBox.find('[data-theme-switch-confirm-lead]').text(fillName('data-lead', leaving))
        const list = confirmBox.find('[data-theme-switch-confirm-list]').empty()
        items.forEach(function (item) {
          list.append(jQuery('<li>').text(item))
        })
        confirmBox.find('[data-theme-switch-confirm-note]').text(fillName('data-note', leaving))
        title.text(fillName('data-title', stagedThemeId))

        pickScreen.prop('hidden', true)
        confirmBox.css('min-height', pickHeight ? pickHeight + 'px' : '').prop('hidden', false)
        enterScreen(confirmBox, 'is-entering-forward')
        confirmBox.find('[data-theme-switch-back]').trigger('focus')
      }

      // Back keeps the staged card, so the choice can be changed rather than
      // made again.
      function backToPick() {
        showPickScreen(true)
        const stagedCard = findCard(stagedThemeId)
        if (stagedCard.length) {
          stagedCard.trigger('focus')
        }
      }

      // Picking a card only stages it. theme_id autosaves the moment it
      // changes, so nothing reaches it until Switch theme, and the confirm.
      function stage(themeId) {
        stagedThemeId = themeId
        applyButton.prop('disabled', themeId === currentThemeId())
        modal.find('[data-theme-staged-name]').text(themeName(themeId))
        showPickScreen()
      }

      function apply() {
        const themeId = stagedThemeId
        closeModal()
        if (themeId && themeId !== currentThemeId()) {
          activeInput.val(themeId).trigger('change')
        }
      }

      function requestApply() {
        const leaving = currentThemeId()
        if (!stagedThemeId || stagedThemeId === leaving) {
          closeModal()
          return
        }

        const items = impactOfLeaving(leaving)
        if (!items.length) {
          apply()
          return
        }

        showConfirmScreen(leaving, items)
      }

      // Same open/close as the Add New dialog, whose shell this one borrows:
      // [hidden] keeps it out of the tab order, .is-open runs the fade.
      function openModal() {
        if (modalEl.classList.contains('is-open')) {
          return
        }

        lastFocusedElement = document.activeElement
        const current = currentThemeId()
        const currentCard = findCard(current)
        if (currentCard.length) {
          markThemeCardSelected(currentCard, false)
        }
        stage(current)

        modalEl.removeAttribute('hidden')
        window.requestAnimationFrame(function () {
          modalEl.classList.add('is-open')
          const firstFocusable = currentCard.length ? currentCard : modal.find('.g-add-new-modal__close')
          firstFocusable.trigger('focus')
        })
      }

      function closeModal() {
        if (!modalEl.classList.contains('is-open')) {
          return
        }

        modalEl.classList.remove('is-open')
        hideAfterFade(modalEl)

        if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
          lastFocusedElement.focus()
        }
      }

      jQuery(document).on('click.gThemePickerOpen', '[data-theme-picker-open]', function () {
        openModal()
      })

      jQuery(document).on('click.gThemePickerClose', '[data-theme-picker-close]', function () {
        closeModal()
      })

      applyButton.on('click', requestApply)
      confirmBox.on('click', '[data-theme-switch-confirm-apply]', apply)
      confirmBox.on('click', '[data-theme-switch-back]', backToPick)

      // Escape belongs to the theme preview while it is open over this dialog.
      jQuery(document).on('keydown.gThemePicker', function (event) {
        if (event.key !== 'Escape' || !modalEl.classList.contains('is-open')) {
          return
        }
        if (jQuery('.g-tpp-overlay.is-open').length) {
          return
        }

        event.preventDefault()
        closeModal()
      })

      return {
        open: openModal,
        close: closeModal,
        stage: stage
      }
    }

    const themePickerModal = bindThemePickerModal()

    function syncSelectedThemeSummary() {
      const activeThemeId = String(jQuery('#g-active-theme-id').val() || '')
      if (!activeThemeId) {
        return
      }

      const selectedOption = jQuery('#g-theme-picker-modal .g-folio__theme-option[data-theme-id="' + activeThemeId + '"]').first()
      if (!selectedOption.length) {
        return
      }

      markThemeCardSelected(selectedOption, false)

      jQuery('[data-theme-summary-name]').text(String(selectedOption.data('theme-name') || ''))
      jQuery('[data-theme-summary-description]').text(String(selectedOption.data('theme-description') || ''))
      jQuery('[data-theme-summary-thumbnail]')
        .attr('src', String(selectedOption.data('theme-thumbnail-url') || ''))
        .attr('alt', String(selectedOption.data('theme-name') || ''))
    }

    function syncThemeSpecificCustomizationFields() {
      const activeThemeId = String(jQuery('#g-active-theme-id').val() || '')

      jQuery('[data-theme-target]').each(function () {
        const targetThemeId = String(jQuery(this).data('theme-target') || '')
        if (!targetThemeId) {
          return
        }

        setThemeSectionVisibility(this, activeThemeId === targetThemeId)
      })

      jQuery('[data-theme-hide-on]').each(function () {
        const hideThemeId = String(jQuery(this).data('theme-hide-on') || '')
        if (!hideThemeId) {
          return
        }

        setThemeSectionVisibility(this, activeThemeId !== hideThemeId)
      })

      if (activeThemeId !== 'groove-proposal') {
        proposalInformationModal.close()
        versionHistoryModal.close()
      } else {
        proposalInformationModal.syncSummary()
      }
    }

    syncThemeSpecificCustomizationFields()
    syncSelectedThemeSummary()

    jQuery('#g-active-theme-id').on('change', function () {
      syncThemeSpecificCustomizationFields()
      syncSelectedThemeSummary()
    })

    bindThemeCards(function (card) {
      themePickerModal.stage(String(card.data('theme-id') || ''))
    })
  }

  if (Groove.isAddNewPage()) {
    function syncAddNewThemeSpecificFields(themeId) {
      const activeThemeId = String(themeId || '')

      jQuery('[data-add-new-theme-target]').each(function () {
        const targetThemeId = String(jQuery(this).data('add-new-theme-target') || '')
        if (!targetThemeId) {
          return
        }

        setThemeSectionVisibility(this, activeThemeId === targetThemeId)
      })
    }

    bindThemeCards(function (card) {
      const selectedThemeId = card.data('theme-id')

      jQuery('[name="themeId"]').val(selectedThemeId)
      jQuery('#g-folio-selected-theme-name').text(card.data('theme-name') || '')
      syncAddNewThemeSpecificFields(selectedThemeId)
    })

    const initialSelectedCard = jQuery(themeCardSelector + '[aria-checked="true"]').first()
    if (initialSelectedCard.length) {
      syncAddNewThemeSpecificFields(initialSelectedCard.data('theme-id'))
    } else {
      syncAddNewThemeSpecificFields(jQuery('[name="themeId"]').val())
    }
  }

  if (Groove.isPreview()) {
    // Past the first screenful the top bar goes opaque. This used to write two
    // literal rgba() values as inline styles, which beat the stylesheet on the
    // element itself: the bar ignored --folio-overlay and no theme could
    // restyle either state. It now toggles a class and the colours live in the
    // theme CSS.
    //
    // A theme whose own JS paints the bar opts out on the bar itself with
    //
    //   data-groove-navbar="own"
    //
    // This used to be a hardcoded `.closest('.gn-page, .gp-page')` filter —
    // shared code naming two themes' private class prefixes, which a sixth
    // theme could neither join nor leave without editing this file.
    function setNavBarBackgroundColor() {
      jQuery('.g-folio__theme-page-nav-bar')
        .not('[data-groove-navbar="own"]')
        .toggleClass('is-scrolled', window.scrollY > 96)
    }

    setNavBarBackgroundColor()

    // The same opt-out, for a sharper reason: a theme that binds this button in
    // its own JS and toggles `.visible` from its current state would, with both
    // handlers bound, toggle twice per click and never open the panel. Such a
    // theme declares
    //
    //   data-groove-nav-toggle="own"
    //
    // on the button. Opting out is only correct if the theme actually OPENS the
    // panel itself — groove-proposal was excluded by the old prefix filter while
    // shipping only a closer, so its "On this page" panel could never open at
    // all. Silence here means the shared handler does the work.
    jQuery('.g-folio__theme-page-nav-bar-toggle')
      .not('[data-groove-nav-toggle="own"]')
      .click(function () {
        // Class only. aria-expanded is the drawer controller's job — see below.
        jQuery('.g-folio__theme-page-mobile-nav').toggleClass('visible')
      })

    jQuery('.g-folio__theme-page-mobile-nav-back').click(function () {
      window.scrollTo({
        top: 0
      })
    })

    jQuery(window).scroll(function () {
      setNavBarBackgroundColor()
    })

    jQuery('.g-folio__theme-nav-button').click(function () {
      jQuery('.g-folio__theme-nav').addClass('visible')
    })

    jQuery('.g-folio__theme-nav-close').click(function () {
      jQuery('.g-folio__theme-nav').removeClass('visible')
    })

    jQuery('.g-folio__theme-page-nav-button').click(function () {
      jQuery('.g-folio__theme-page-nav').addClass('visible')
    })

    jQuery('.g-folio__theme-page-nav-close').click(function () {
      jQuery('.g-folio__theme-page-nav').removeClass('visible')
    })

    // ── Drawer behaviour ───────────────────────────────────────────────────
    // The handlers above are the whole of open and close: they toggle
    // `.visible` and nothing else. Everything a bare addClass cannot give you —
    // ARIA state, Escape, click-outside dismissal, scroll lock and focus
    // handling — is layered on here for any pane that opts in with
    //
    //   data-groove-drawer="<selector of its trigger>"
    //
    // Opt-in rather than automatic because a theme that ships its own drawer
    // controller would double-handle every event. A theme that wants the shared
    // behaviour declares one attribute; a theme that owns its drawers says
    // nothing. Add data-groove-drawer-lock="off" for a dropdown-style panel
    // that should not lock the page behind it.
    //
    // This was groove-ebook's two missing dismissals and folio-starter's
    // private copy of the same 139 lines. See themes/README.md.
    const drawers = Array.prototype.slice
      .call(document.querySelectorAll('[data-groove-drawer]'))
      .map(function (pane) {
        return {
          pane: pane,
          trigger: document.querySelector(pane.getAttribute('data-groove-drawer')),
          lock: pane.getAttribute('data-groove-drawer-lock') !== 'off',
          lastFocused: null
        }
      })

    if (drawers.length) {
      const isDrawerOpen = function (drawer) {
        return drawer.pane.classList.contains('visible')
      }

      const closeDrawer = function (drawer) {
        drawer.pane.classList.remove('visible')
      }

      const syncScrollLock = function () {
        const locked = drawers.some(function (drawer) {
          return drawer.lock && isDrawerOpen(drawer)
        })

        document.body.style.overflow = locked ? 'hidden' : ''
      }

      // A pane is visibility:hidden until it has slid in, and an element that
      // computes to hidden silently refuses focus — so wait for the transition
      // rather than guessing a delay. The timer covers a transition that never
      // fires: reduced motion, or a panel that does not animate at all.
      const focusWhenReady = function (pane, target) {
        let timer = null

        const attempt = function (event) {
          // transitionend bubbles, and the things inside a pane transition too —
          // a link tinting under the cursor would otherwise count as the pane
          // having arrived. Only the pane's own transition ends the wait.
          if (event && event.target !== pane) {
            return
          }

          pane.removeEventListener('transitionend', attempt)
          clearTimeout(timer)

          if (pane.classList.contains('visible')) {
            // A pane that is out of flow is already where the reader is looking;
            // one that is not would be scrolled to, which is not this function's
            // job — it moves focus, not the viewport.
            target.focus({ preventScroll: true })
          }
        }

        pane.addEventListener('transitionend', attempt)
        timer = setTimeout(attempt, 350)
      }

      const onDrawerToggle = function (drawer) {
        const open = isDrawerOpen(drawer)

        if (drawer.trigger) {
          drawer.trigger.setAttribute('aria-expanded', open ? 'true' : 'false')
        }

        syncScrollLock()

        if (open) {
          drawer.lastFocused = document.activeElement

          // The close button first, so Escape and Tab both start somewhere sane.
          const first = drawer.pane.querySelector(
            '.g-folio__theme-nav-close, .g-folio__theme-page-nav-close, a, button'
          )

          if (first) {
            focusWhenReady(drawer.pane, first)
          }
        } else if (drawer.lastFocused && typeof drawer.lastFocused.focus === 'function') {
          drawer.lastFocused.focus()
          drawer.lastFocused = null
        }
      }

      drawers.forEach(function (drawer) {
        new MutationObserver(function () {
          onDrawerToggle(drawer)
        }).observe(drawer.pane, { attributes: true, attributeFilter: ['class'] })
      })

      document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' && event.key !== 'Esc') {
          return
        }

        drawers.forEach(function (drawer) {
          if (isDrawerOpen(drawer)) {
            closeDrawer(drawer)
          }
        })
      })

      document.addEventListener('click', function (event) {
        drawers.forEach(function (drawer) {
          if (!isDrawerOpen(drawer)) {
            return
          }
          // The click that opened it bubbles to here too.
          if (drawer.pane.contains(event.target)) {
            return
          }
          if (drawer.trigger && drawer.trigger.contains(event.target)) {
            return
          }

          closeDrawer(drawer)
        })
      })
    }
  }

  // Breadcrumbs on groove_folio_page editor screens are handled by
  // assets/js/groove-gutenberg-breadcrumb.js to avoid duplicate injection.

  // ── Add New Folio Modal ──────────────────────────────────────────────────────

  const $addNewModal = jQuery('#g-add-new-modal')
  if ($addNewModal.length) {
    const addNewModalEl  = $addNewModal[0]
    const $backdrop      = $addNewModal.find('.g-add-new-modal__backdrop')

    function openAddNewModal() {
      addNewModalEl.removeAttribute('hidden')
      requestAnimationFrame(function () {
        addNewModalEl.classList.add('is-open')
      })
      document.body.style.overflow = 'hidden'
      $addNewModal.find('.g-add-new-modal__close').trigger('focus')
    }

    function closeAddNewModal() {
      addNewModalEl.classList.remove('is-open')
      document.body.style.overflow = ''
      hideAfterFade(addNewModalEl)
    }

    jQuery(document).on('click', '[data-groove-open-add-new]', openAddNewModal)
    $addNewModal.on('click', '.g-add-new-modal__close', closeAddNewModal)
    $backdrop.on('click', closeAddNewModal)

    jQuery(document).on('keydown', function (e) {
      if (e.key === 'Escape' && addNewModalEl.classList.contains('is-open')) {
        closeAddNewModal()
      }
    })

    // open_add_new is a one-shot instruction, so it is spent the moment it is
    // read. Left in the address bar it becomes the referrer for everything the
    // operator does next, and the row actions hand that referrer to core's
    // post.php, which sends them straight back to it — so trashing a folio
    // returned to ?open_add_new=1 and reopened the theme picker over the list.
    // Refreshing had the same effect.
    function consumeAddNewFlag() {
      if (!window.history || !window.history.replaceState) {
        return
      }

      const url = new URL(window.location.href)
      if (!url.searchParams.has('open_add_new')) {
        return
      }

      url.searchParams.delete('open_add_new')
      window.history.replaceState(window.history.state, '', url.toString())
    }

    // Auto-open when redirected from groove-add-new nav link.
    if (settings.openAddNewModal) {
      openAddNewModal()
      consumeAddNewFlag()
    }

    // Intercept the sidebar "Add New Folio" nav link so it opens the modal
    // in-place when already on the All Folios page.
    jQuery('a[href*="page=groove-add-new"]').on('click', function (e) {
      e.preventDefault()
      openAddNewModal()
    })
  }

  // What the sample content actually seeds is a sentence too long to sit beside
  // the submit, so it hangs off an info button instead. Initialised page-wide:
  // the Add New picker and the Themes screen's details dialog both render one
  // per theme, and only the visible one is ever on screen.
  jQuery('.g-folio__sample-info').each(function () {
    createAdaptiveTooltip(this)
  })

  // Themes → Spec / Playbook: the icon-only download beside the file's name.
  jQuery('.g-docs__download').each(function () {
    createAdaptiveTooltip(this)
  })

  // ── Theme Picker Preview Overlay ────────────────────────────────────────────

  // Shared by the Add New theme picker, the folio editor's Change theme dialog
  // and the Themes screen's details dialog — all offer Preview on a theme, so
  // all get the same overlay.
  if (Groove.isAddNewPage() || Groove.isFolioPage() || Groove.isThemesPage()) {
    const previewBaseUrl = (settings.themePreviewBaseUrl || '').replace(/\?.*$/, '')
    const previewNonce   = settings.themePreviewNonce || ''

    if (previewBaseUrl && previewNonce) {

      // Build the overlay DOM once and append to body.
      const $overlay = jQuery(
        '<div id="g-tpp-overlay" class="g-tpp-overlay" role="dialog" aria-modal="true" aria-label="Theme preview" hidden>' +
          '<div class="g-tpp-header">' +
            '<div class="g-tpp-tabs" role="tablist">' +
              '<button class="g-tpp-tab is-active" role="tab" aria-selected="true"  data-view="cover" data-page-index="0">Cover</button>' +
              '<button class="g-tpp-tab"            role="tab" aria-selected="false" data-view="page"  data-page-index="0">Page 1</button>' +
              '<button class="g-tpp-tab"            role="tab" aria-selected="false" data-view="page"  data-page-index="1">Page 2</button>' +
            '</div>' +
            '<button class="g-tpp-close" aria-label="Close preview">' +
              '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">' +
                '<path d="M2 2l12 12M14 2L2 14" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>' +
              '</svg>' +
            '</button>' +
          '</div>' +
          '<div id="g-tpp-iframe-wrap" class="g-tpp-iframe-wrap">' +
            '<div class="g-tpp-loader" aria-hidden="true"><span></span><span></span><span></span></div>' +
            '<iframe id="g-tpp-iframe" class="g-tpp-iframe" src="about:blank" title="Theme preview" sandbox="allow-scripts allow-same-origin"></iframe>' +
          '</div>' +
        '</div>'
      )
      jQuery('body').append($overlay)

      const overlayEl   = $overlay[0]
      const iframeEl    = document.getElementById('g-tpp-iframe')
      const iframeWrap  = document.getElementById('g-tpp-iframe-wrap')
      let   currentThemeId = null

      function buildPreviewUrl(themeId, view, pageIndex) {
        return previewBaseUrl +
          '?groove_theme_preview=' + encodeURIComponent(themeId) +
          '&groove_preview_view='  + encodeURIComponent(view) +
          '&groove_preview_page='  + encodeURIComponent(pageIndex) +
          '&_wpnonce='             + encodeURIComponent(previewNonce)
      }

      function setActiveTab(tabEl) {
        $overlay.find('.g-tpp-tab')
          .removeClass('is-active')
          .attr('aria-selected', 'false')
        jQuery(tabEl)
          .addClass('is-active')
          .attr('aria-selected', 'true')
      }

      function loadIframe(view, pageIndex) {
        const url = buildPreviewUrl(currentThemeId, view, pageIndex)
        iframeWrap.classList.add('is-loading')
        iframeEl.src = url
        iframeEl.addEventListener('load', function handler() {
          iframeEl.removeEventListener('load', handler)
          iframeWrap.classList.remove('is-loading')
        }, { once: true })
      }

      // Open overlay when a Preview button is clicked.
      jQuery(document).on('click', '.g-theme-preview-btn', function (e) {
        e.stopPropagation()
        currentThemeId = String(jQuery(this).data('theme-id') || '')
        if (!currentThemeId) return

        // Reset to Cover tab.
        $overlay.find('.g-tpp-tab').removeClass('is-active').attr('aria-selected', 'false')
        $overlay.find('.g-tpp-tab[data-view="cover"]').addClass('is-active').attr('aria-selected', 'true')

        // Show overlay (remove hidden attr so CSS transition fires).
        overlayEl.removeAttribute('hidden')
        requestAnimationFrame(function () {
          overlayEl.classList.add('is-open')
        })
        document.body.style.overflow = 'hidden'

        loadIframe('cover', 0)
        $overlay.find('.g-tpp-close').trigger('focus')
      })

      // Tab switching with View Transition for the active indicator.
      $overlay.on('click', '.g-tpp-tab', function () {
        if (this.classList.contains('is-active')) return
        const view      = String(jQuery(this).data('view') || 'cover')
        const pageIndex = parseInt(jQuery(this).data('page-index') || 0, 10)
        const tabEl     = this

        if ('startViewTransition' in document) {
          document.startViewTransition(function () {
            setActiveTab(tabEl)
          })
        } else {
          setActiveTab(tabEl)
        }

        loadIframe(view, pageIndex)
      })

      // Close helpers.
      function closeOverlay() {
        overlayEl.classList.remove('is-open')
        document.body.style.overflow = ''
        overlayEl.addEventListener('transitionend', function handler() {
          overlayEl.removeEventListener('transitionend', handler)
          overlayEl.setAttribute('hidden', '')
          iframeEl.src = 'about:blank'
          iframeWrap.classList.remove('is-loading')
        }, { once: true })
      }

      $overlay.on('click', '.g-tpp-close', closeOverlay)

      // Close on backdrop click.
      $overlay.on('click', function (e) {
        if (e.target === overlayEl) closeOverlay()
      })

      // Escape key.
      jQuery(document).on('keydown', function (e) {
        if (e.key === 'Escape' && overlayEl.classList.contains('is-open')) {
          closeOverlay()
        }
      })
    }
  }

  // Default folio title placeholder (Settings → General). Keep the placeholder
  // honest: it previews the title a folio would get from the currently
  // selected default theme, so an empty field is not a mystery. Purely
  // cosmetic — the value is resolved server side on create.
  ;(function () {
    const themes = document.getElementById('groove-default-theme-id')
    const title = document.getElementById('groove-default-folio-title')
    if (!themes || !title) {
      return
    }
    themes.addEventListener('change', function () {
      const option = themes.options[themes.selectedIndex]
      title.placeholder = (option && option.getAttribute('data-default-title')) || title.placeholder
    })
  })()
})
