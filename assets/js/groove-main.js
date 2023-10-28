jQuery(function () {
  const Groove = Object.create({
    screenId: window.GROOVE_SCREEN_ID || '',
    themeId: window.GROOVE_THEME_ID ||  null,

    isPostPage () {
      return this.screenId === 'groove_folio_page' && window.GROOVE_POST
    },

    isAddNewPage () {
      return this.screenId.indexOf('groove-add-new') > -1
    },

    isFolioPage () {
      return this.screenId.indexOf('groove-folio') > -1
    },

    isPreview () {
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

  if (Groove.isPreview()) {
    function setNavBarBackgroundColor () {
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

  if (Groove.isPostPage()) {
    const content = jQuery(document.getElementById('wpbody-content'))
    const nav = jQuery(document.createElement('nav'))
    
    nav.addClass('g-top-bar-post-nav')

    content.prepend(nav.html(`
      <div class="g-top-bar-post-type">
        <a class="g-top-bar-crumb" href="javascript: history.back()">${window.GROOVE_POST_TYPE === 'edit' ? 'Edit Folio' : 'New Folio' }</a>
        <i class="g-top-bar-crumb-arrow"> /</i>
        <a class="g-top-bar-crumb">Page</a>
      </div>
    `))

    jQuery('#editor').css('top', '24px')
  }
})

