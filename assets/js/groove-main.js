jQuery(function () {
  const settings = window.GROOVE_SETTINGS || {}

  const Groove = Object.create({
    screenId: settings.screenId || window.GROOVE_SCREEN_ID || '',
    themeId: settings.themeId || window.GROOVE_THEME_ID || null,
    adminPostUrl: settings.adminPostUrl || window.GROOVE_ADMIN_POST_URL || '/wp-admin/admin-post.php',

    isPostPage() {
      return this.screenId === 'groove_folio_page' && window.GROOVE_POST
    },

    isAddNewPage() {
      const page = new URLSearchParams(window.location.search).get('page')
      return page === 'groove-add-new'
    },

    isFolioPage() {
      const page = new URLSearchParams(window.location.search).get('page')
      return page === 'groove-folio'
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

  if (Groove.isFolioPage()) {
    const folioForm = jQuery('form[action*="admin-post.php"]')
      .has('input[name="folio_id"]')
      .has('input[name="groove_nonce"]')
      .first()

    createAdaptiveTooltip('#g-folio-preview-link')

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
        if (!name || name === 'groove_nonce' || name === 'folio_id' || name === 'action' || name === 'permalink') {
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

    function syncThemeSpecificCustomizationFields() {
      const activeThemeId = String(jQuery('#g-active-theme-id').val() || '')

      jQuery('[data-theme-target]').each(function () {
        const targetThemeId = String(jQuery(this).data('theme-target') || '')
        if (!targetThemeId) {
          return
        }

        if (activeThemeId === targetThemeId) {
          jQuery(this).removeClass('hidden')
        } else {
          jQuery(this).addClass('hidden')
        }
      })
    }

    syncThemeSpecificCustomizationFields()

    jQuery('#g-active-theme-id').on('change', function () {
      syncThemeSpecificCustomizationFields()
    })

    jQuery(document).on('click', '.g-folio__theme-option', function () {
      jQuery('.g-folio__theme-option')
        .removeClass('border-indigo-600 ring-1 ring-indigo-600')
        .addClass('border-gray-200')
      jQuery('.g-folio__theme-option .active-badge').addClass('hidden')

      jQuery(this)
        .removeClass('border-gray-200')
        .addClass('border-indigo-600 ring-1 ring-indigo-600')
      jQuery(this).find('.active-badge').removeClass('hidden')
      jQuery('#g-active-theme-id').val(jQuery(this).data('theme-id')).trigger('change')
    })
  }

  if (Groove.isAddNewPage()) {
    const themeCardsSelector = '.g-folio__theme-option'

    function selectAddNewTheme(card) {
      const selected = jQuery(card)
      const cards = jQuery(themeCardsSelector)
      const selectedName = selected.data('theme-name') || ''

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

      jQuery('[name="themeId"]').val(selected.data('theme-id'))
      jQuery('#g-folio-selected-theme-name').text(selectedName)
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
  }

  if (Groove.isPreview()) {
    function setNavBarBackgroundColor() {
      const navBars = jQuery('.g-folio__theme-page-nav-bar').filter(function () {
        return jQuery(this).closest('.gn-page').length === 0
      })

      if (window.scrollY > 96) {
        navBars.css('background-color', 'rgba(255, 255, 255, 1)')
      } else {
        navBars.css('background-color', 'rgba(255, 255, 255, 0.9)')
      }
    }

    setNavBarBackgroundColor()

    jQuery('.g-folio__theme-page-nav-bar-toggle').click(function () {
      if (jQuery('.g-folio__theme-page-mobile-nav').hasClass('visible')) {
        jQuery('.g-folio__theme-page-mobile-nav').removeClass('visible')
      } else {
        jQuery('.g-folio__theme-page-mobile-nav').addClass('visible')
      }
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
  }

  // Breadcrumbs on groove_folio_page editor screens are handled by
  // assets/js/groove-gutenberg-breadcrumb.js to avoid duplicate injection.
})
