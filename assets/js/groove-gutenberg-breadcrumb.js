(function (wp) {
  if (!wp || !wp.plugins || !wp.element || !wp.data) {
    return;
  }

  const { registerPlugin } = wp.plugins;
  const { useEffect } = wp.element;
  const { useSelect, dispatch, select } = wp.data;

  function getFolioName() {
    return window.GROOVE_FOLIO_NAME || (window.GROOVE_POST_TYPE === 'edit' ? 'Edit Folio' : 'New Folio');
  }

  function getFolioUrl() {
    if (window.GROOVE_FOLIO_SETUP_URL) {
      return window.GROOVE_FOLIO_SETUP_URL;
    }

    return '#';
  }

  function ensureNativeAdminUiVisible() {
    const editPostStore = select('core/edit-post');
    if (!editPostStore || typeof editPostStore.isFeatureActive !== 'function') {
      return;
    }

    if (editPostStore.isFeatureActive('fullscreenMode')) {
      const editPostDispatch = dispatch('core/edit-post');
      if (editPostDispatch && typeof editPostDispatch.toggleFeature === 'function') {
        editPostDispatch.toggleFeature('fullscreenMode');
      }
    }
  }

  function upsertBreadcrumb() {
    const bodyContent = document.querySelector('#wpbody-content');
    if (!bodyContent) {
      return;
    }

    let nav = bodyContent.querySelector('.g-top-bar-post-nav');
    if (!nav) {
      nav = document.createElement('nav');
      nav.className = 'g-top-bar-post-nav';
      nav.innerHTML =
        '<div class="g-top-bar-post-type">' +
        '<a class="g-top-bar-crumb g-top-bar-crumb-parent"></a>' +
        '<i class="g-top-bar-crumb-arrow"> /</i>' +
        '<a class="g-top-bar-crumb">Page</a>' +
        '</div>';
      bodyContent.prepend(nav);
    }

    const parentLink = nav.querySelector('.g-top-bar-crumb-parent');
    if (parentLink) {
      parentLink.textContent = getFolioName();
      parentLink.setAttribute('href', getFolioUrl());
    }
  }

  const GrooveBreadcrumb = function () {
    if (!window.GROOVE_POST) {
      return null;
    }

    const postType = useSelect(function (wpSelect) {
      const editorStore = wpSelect('core/editor');
      return editorStore ? editorStore.getCurrentPostType() : null;
    }, []);

    useEffect(function () {
      if (postType !== 'groove_folio_page') {
        return undefined;
      }

      ensureNativeAdminUiVisible();
      upsertBreadcrumb();

      const observer = new MutationObserver(function () {
        upsertBreadcrumb();
      });
      const bodyContent = document.querySelector('#wpbody-content');

      if (bodyContent) {
        observer.observe(bodyContent, { childList: true });
      }

      return function () {
        observer.disconnect();
      };
    }, [postType]);

    return null;
  };

  registerPlugin('groove-breadcrumb', {
    render: GrooveBreadcrumb
  });
})(window.wp);
