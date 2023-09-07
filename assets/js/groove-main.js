jQuery(function () {
  const Groove = Object.create({
    screenId: window.GROOVE_SCREEN_ID,
    themeId: window.GROOVE_THEME_ID ?? null,

    isAddNewPage () {
      return this.screenId.indexOf('groove-add-new') > -1
    },

    isFolioPage () {
      return this.screenId.indexOf('groove-folio') > -1
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
    function getFileds () {
      var fields = {}

      jQuery('.g-folio__fields input').each(function (index, input) {
        fields[input.name] = input.value

        if (input.name === 'permission') {
          if (input.checked) {
            fields[input.name] = '2' 
          } else {
            fields[input.name] = '4' 
          }
        }

      })

      return fields
    }
    function ajax (status) {
      var fields = getFileds()

      jQuery.ajax({
        url: '/wp-admin/admin-post.php',
        method: 'post',
        data: Object.assign({
          action: status,
        }, fields),
        dataType: 'json',
        success (result) {
          if (result.code === 0) {
            location.reload()
          }
        }
      })
    }

    jQuery('button[value="save_groove_folio"]').click(function (e) {
      e.preventDefault();
      ajax('save_groove_folio')
    })

    jQuery('button[value="save_groove_folio_draft"]').click(function (e) {
      e.preventDefault()
      ajax('save_groove_folio_draft')
    })  
  }

  if (Groove.isAddNewPage()) {
    jQuery('.g-folio__theme').click(function () {
      if (!jQuery(this).hasClass('g-folio__theme_selected')) { 
        jQuery('.g-folio__theme').removeClass('g-folio__theme_selected')
        jQuery(this).addClass('g-folio__theme_selected')

        Groove.themeId = jQuery(this).data('theme-id');

        jQuery('#js-theme-select-button').attr('href', '/wp-admin/admin.php?page=groove-folio&theme-id=' + Groove.themeId)
        jQuery('[data-field-id="theme-id"]').attr('value', Groove.themeId)
      }
    });

    jQuery('.g-folio__theme[data-theme-id="' + Groove.themeId + '"]').addClass('g-folio__theme_selected');
  }
})