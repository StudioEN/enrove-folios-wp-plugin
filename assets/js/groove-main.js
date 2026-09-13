jQuery(function () {
  const settings = window.GROOVE_SETTINGS || {}

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

    function setTooltipText(text) {
      const normalizedText = String(text || '')
      tooltipBubble.text(normalizedText)
      tooltipBtn.attr('data-tooltip-text', normalizedText)
      if (normalizedText) {
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
    let saveIndicatorResetTimer = null
    let saveIndicator = jQuery('#g-folio-save-indicator')

    if (!saveIndicator.length) {
      saveIndicator = jQuery('<div id="g-folio-save-indicator" aria-live="polite"></div>')
      jQuery('body').append(saveIndicator)
    }

    function hideSaveStatus() {
      if (!saveIndicator.length) {
        return
      }

      clearTimeout(saveIndicatorResetTimer)
      saveIndicator
        .removeClass('is-visible is-saving is-saved is-error')
        .text('')
    }

    function setSaveStatus(state, message, autoHideMs = 0) {
      if (!saveIndicator.length) {
        return
      }

      clearTimeout(saveIndicatorResetTimer)
      saveIndicator
        .removeClass('is-saving is-saved is-error')
        .addClass('is-visible')
        .addClass('is-' + state)
        .text(message)

      if (autoHideMs > 0) {
        saveIndicatorResetTimer = setTimeout(function () {
          hideSaveStatus()
        }, autoHideMs)
      }
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

    function ajax(status, options = {}) {
      const shouldReload = !!options.reload
      const reloadDelayMs = Number(options.reloadDelayMs || 0)
      const shouldUpdatePermalink = !!options.updatePermalink
      const silent = !!options.silent
      const savingText = options.savingText || 'Saving...'
      const savedText = options.savedText || 'Saved'
      const errorText = options.errorText || 'Save failed'
      const fields = getFields()
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

          if (shouldUpdatePermalink && result.slug) {
            jQuery('#permalink').val(result.slug)
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

    jQuery('button[value="save_groove_folio"], button[value="save_groove_folio_unpublish"]').click(function (e) {
      const publishBtn = jQuery(this)
      const action = publishBtn.val()
      const isUnpublish = action === 'save_groove_folio_unpublish'
      const originalLabel = publishBtn.text()
      const savingLabel = isUnpublish ? 'Unpublishing...' : 'Publishing...'
      const savedLabel = isUnpublish ? 'Unpublished' : 'Published'
      const errorLabel = isUnpublish ? 'Unpublish failed' : 'Publish failed'

      e.preventDefault()
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
    })

    jQuery('button[value="save_groove_folio_manual"], button[value="save_groove_folio_draft"]').click(function (e) {
      const saveBtn = jQuery(this)
      const action = saveBtn.val()
      const originalLabel = saveBtn.text()

      e.preventDefault()
      isManualSave = true
      clearTimeout(autosaveTimer)
      saveBtn.text('Saving...')
      ajax(action, {
        reload: false,
        updatePermalink: true,
        savingText: 'Saving...',
        savedText: 'Saved',
        errorText: 'Save failed'
      }).always(function () {
        isManualSave = false
        saveBtn.text(originalLabel)
      })
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
          const selectedCard = modal.find('.g-folio__theme-option[aria-pressed="true"]').first()
          const firstFocusable = selectedCard.length ? selectedCard : modal.find('button').filter(':visible').first()
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

      jQuery(document).on('click.gThemePickerOpen', '[data-theme-picker-open]', function () {
        openModal()
      })

      jQuery(document).on('click.gThemePickerClose', '[data-theme-picker-close]', function () {
        closeModal()
      })

      jQuery(document).on('keydown.gThemePicker', function (event) {
        if (event.key !== 'Escape' || modal.hasClass('hidden')) {
          return
        }

        event.preventDefault()
        closeModal()
      })

      return {
        open: openModal,
        close: closeModal
      }
    }

    bindThemePickerModal()

    function setActiveThemeOption(option) {
      const selected = jQuery(option)
      const options = jQuery('#g-theme-picker-modal .g-folio__theme-option')

      options
        .removeClass('is-active')
        .attr('aria-pressed', 'false')

      options.find('.active-badge').addClass('is-hidden')

      selected
        .addClass('is-active')
        .attr('aria-pressed', 'true')

      selected.find('.active-badge').removeClass('is-hidden')
    }

    function syncSelectedThemeSummary() {
      const activeThemeId = String(jQuery('#g-active-theme-id').val() || '')
      if (!activeThemeId) {
        return
      }

      const selectedOption = jQuery('#g-theme-picker-modal .g-folio__theme-option[data-theme-id="' + activeThemeId + '"]').first()
      if (!selectedOption.length) {
        return
      }

      setActiveThemeOption(selectedOption)

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

    jQuery(document).on('click', '#g-theme-picker-modal .g-folio__theme-option', function () {
      setActiveThemeOption(this)
      jQuery('#g-active-theme-id').val(jQuery(this).data('theme-id')).trigger('change')
    })
  }

  if (Groove.isAddNewPage()) {
    const themeCardsSelector = '.g-folio__theme-option'

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

    function selectAddNewTheme(card) {
      const selected = jQuery(card)
      const cards = jQuery(themeCardsSelector)
      const selectedName = selected.data('theme-name') || ''
      const selectedThemeId = selected.data('theme-id')

      cards
        .removeClass('border-indigo-600 ring-1 ring-indigo-600')
        .addClass('border-gray-200')
        .attr('aria-checked', 'false')
        .attr('tabindex', '-1')
      cards.find('.active-badge').addClass('hidden')

      selected
        .removeClass('border-gray-200')
        .addClass('border-indigo-600 ring-1 ring-indigo-600')
        .attr('aria-checked', 'true')
        .attr('tabindex', '0')
        .focus()
      selected.find('.active-badge').removeClass('hidden')

      jQuery('[name="themeId"]').val(selectedThemeId)
      jQuery('#g-folio-selected-theme-name').text(selectedName)
      syncAddNewThemeSpecificFields(selectedThemeId)
    }

    jQuery(document).on('click', themeCardsSelector, function () {
      selectAddNewTheme(this)
    })

    jQuery(document).on('keydown', themeCardsSelector, function (event) {
      const horizontalKeys = ['ArrowLeft', 'ArrowRight']
      const verticalKeys = ['ArrowUp', 'ArrowDown']
      const allKeys = horizontalKeys.concat(verticalKeys)

      if (allKeys.indexOf(event.key) === -1) {
        return
      }

      event.preventDefault()

      const cards = jQuery(themeCardsSelector)
      const currentIndex = cards.index(this)
      if (currentIndex === -1) {
        return
      }

      const isBack = event.key === 'ArrowLeft' || event.key === 'ArrowUp'
      const delta = isBack ? -1 : 1
      const nextIndex = (currentIndex + delta + cards.length) % cards.length
      const nextCard = cards.get(nextIndex)

      if (nextCard) {
        selectAddNewTheme(nextCard)
      }
    })

    const initialSelectedCard = jQuery(themeCardsSelector + '[aria-checked="true"]').first()
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
      addNewModalEl.addEventListener('transitionend', function handler() {
        addNewModalEl.removeEventListener('transitionend', handler)
        addNewModalEl.setAttribute('hidden', '')
      }, { once: true })
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

  // ── Theme Picker Preview Overlay ────────────────────────────────────────────

  // Shared by the Add New theme picker and the Themes screen's details dialog —
  // both offer Preview on a theme, so both get the same overlay.
  if (Groove.isAddNewPage() || Groove.isThemesPage()) {
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
})
