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
    const folioForm = jQuery('form').has('input[name="folio_id"]').first()
    if (!folioForm.length) {
      return
    }

    let autosaveTimer = null
    let isManualSave = false
    let lastAutosaveHash = ''
    let saveIndicatorResetTimer = null
    let saveIndicator = jQuery('#g-folio-save-indicator')

    if (!saveIndicator.length) {
      saveIndicator = jQuery('<div id="g-folio-save-indicator" class="is-idle" aria-live="polite">All changes saved</div>')
      jQuery('body').append(saveIndicator)
    }

    function setSaveStatus(state, message) {
      if (!saveIndicator.length) {
        return
      }

      clearTimeout(saveIndicatorResetTimer)
      saveIndicator
        .removeClass('is-idle is-saving is-saved is-error')
        .addClass('is-' + state)
        .text(message)

      if (state === 'saved') {
        saveIndicatorResetTimer = setTimeout(function () {
          setSaveStatus('idle', 'All changes saved')
        }, 1800)
      }
    }

    function getFields() {
      const fields = {}

      if (!folioForm.length) {
        return fields
      }

      folioForm.serializeArray().forEach(function (item) {
        if (item.name !== 'action') {
          fields[item.name] = item.value
        }
      })

      const permissionInput = folioForm.find('input[name="permission"]')
      if (permissionInput.length) {
        fields.permission = permissionInput.is(':checked') ? '2' : '4'
      }

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
      const shouldUpdatePermalink = !!options.updatePermalink
      const silent = !!options.silent
      const savingText = options.savingText || 'Saving...'
      const savedText = options.savedText || 'Saved'
      const errorText = options.errorText || 'Save failed'
      const fields = getFields()
      const nonce = fields.groove_nonce || folioForm.find('input[name="groove_nonce"]').first().val()

      if (!fields.folio_id || !nonce) {
        setSaveStatus('error', 'Save failed')
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
          setSaveStatus('saved', savedText)

          if (shouldUpdatePermalink && result.slug) {
            jQuery('#permalink').val(result.slug)
          }

          if (shouldReload) {
            location.reload()
          }
        } else if (!silent) {
          setSaveStatus('error', errorText)
          // eslint-disable-next-line no-console
          console.error('Groove save failed.', result)
        } else {
          setSaveStatus('error', errorText)
        }
      }).fail(function (xhr) {
        setSaveStatus('error', errorText)
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

        if (name === 'title') {
          return
        }

        scheduleAutoSave('field-change')
      })
    }

    const publishBtn = jQuery('button[value="save_groove_folio"]')
    const publishOriginalLabel = publishBtn.text()
    jQuery('button[value="save_groove_folio"]').click(function (e) {
      e.preventDefault();
      isManualSave = true
      clearTimeout(autosaveTimer)
      jQuery(this).text('Publishing...');
      ajax('save_groove_folio', {
        reload: true,
        updatePermalink: true,
        savingText: 'Publishing...',
        savedText: 'Published',
        errorText: 'Publish failed'
      }).always(function () {
        isManualSave = false
        publishBtn.text(publishOriginalLabel)
      })
    })

    const draftBtn = jQuery('button[value="save_groove_folio_draft"]')
    const draftOriginalLabel = draftBtn.text()
    jQuery('button[value="save_groove_folio_draft"]').click(function (e) {
      e.preventDefault()
      isManualSave = true
      clearTimeout(autosaveTimer)
      jQuery(this).text('Saving...');
      ajax('save_groove_folio_draft', {
        reload: false,
        updatePermalink: true,
        savingText: 'Saving draft...',
        savedText: 'Draft saved',
        errorText: 'Draft save failed'
      }).always(function () {
        isManualSave = false
        draftBtn.text(draftOriginalLabel)
      })
    })

    jQuery('#feature-image').click(function (event) {
      event.preventDefault();
      var custom_uploader = wp.media({
        title: 'Select',
        button: {
          text: 'Select'
        },
        multiple: false
      });

      custom_uploader.on('select', function () {
        var attachment = custom_uploader.state().get('selection').first().toJSON()
        jQuery('#media_id').val(attachment.id).trigger('change')
        jQuery('#feature-preview').attr('src', attachment.url)
      });

      custom_uploader.open();
    });

    jQuery('#use-default-image').click(function () {
      jQuery('#media_id').val('').trigger('change')
      var defaultUrl = jQuery('#use-default-image').data('default-url')
      jQuery('#feature-preview').attr('src', defaultUrl)
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
    jQuery(document).on('click', '.g-folio__theme', function () {
      jQuery('.g-folio__theme').removeClass('is-active')
      jQuery(this).addClass('is-active')
      jQuery('[name="themeId"]').val(jQuery(this).data('theme-id'))
    });
  }

  if (Groove.isPreview()) {
    function setNavBarBackgroundColor() {
      if (window.scrollY > 96) {
        jQuery('.g-folio__theme-page-nav-bar').css('background-color', 'rgba(255, 255, 255, 1)')
      } else {
        jQuery('.g-folio__theme-page-nav-bar').css('background-color', 'rgba(255, 255, 255, 0.9)')
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
