<?php
namespace Enrove\Pages;

use Enrove\Menu\Menu_Manager;
use Enrove\Menu\Settings_Menu_Item;
use Enrove\Pages\Overview;
use Enrove\Pages\Page;
use Enrove\Themes\Themes_Manager;
use Enrove\Utils\Request;
use Enrove\Utils\Utils;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

class Settings extends Page
{
  const PAGE_ID = 'enrove-settings';

  public function get_title()
  {
    return esc_html__('Settings', 'enrove-folios');
  }

  public function create_tabs()
  {
    return [
      'general' => [
        'label' => esc_html__('General', 'enrove-folios'),
      ],
      'collections' => [
        'label' => esc_html__('Collection Tags', 'enrove-folios'),
      ],
      'routing' => [
        'label' => esc_html__('Routing', 'enrove-folios'),
      ],
      'imagery' => [
        'label' => esc_html__('Imagery', 'enrove-folios'),
      ],
      'fonts' => [
        'label' => esc_html__('Fonts', 'enrove-folios'),
      ],
      'privacy' => [
        'label' => esc_html__('Privacy', 'enrove-folios'),
      ],
      'reset' => [
        'label' => esc_html__('Reset', 'enrove-folios'),
      ],
    ];
  }

  public function __construct()
  {
    $this->add_post_action('save_enrove_settings', 'handle_save');
    $this->add_post_action('save_enrove_collection_tag', 'handle_collection_tag_save');
    $this->add_post_action('delete_enrove_collection_tag', 'handle_collection_tag_delete');
    // The API key only serves the curation script, which the WordPress.org
    // package leaves out with the key and API client classes; without them
    // these handlers are not registered, so the released plugin has no way
    // to reach api.pexels.com.
    if ($this->pexels_curation_available()) {
      $this->add_post_action('save_enrove_pexels_key', 'handle_pexels_key_save');
      $this->add_post_action('delete_enrove_pexels_key', 'handle_pexels_key_delete');
      $this->add_post_action('test_enrove_pexels_connection', 'handle_pexels_connection_test');
    }
    $this->add_post_action('download_enrove_sample_photos', 'handle_sample_photos_download');
    $this->add_post_action('download_enrove_fonts', 'handle_fonts_download');
    $this->add_post_action('set_enrove_font_source', 'handle_font_source');
    $this->add_post_action('remove_enrove_fonts', 'handle_fonts_remove');
    $this->add_post_action('remove_enrove_sample_photos', 'handle_sample_photos_remove');
    $this->add_post_action('reset_enrove_folios', 'handle_plugin_reset');

    add_action('enrove/menu/register', function (Menu_Manager $menu) {
      $menu->register(static::PAGE_ID, new Settings_Menu_Item($this));
    }, Overview::MENU_PRIORITY + 20);
  }

  /**
   * The site's explicit default folio title, if it has one.
   *
   * An empty string is a real setting, not a missing one: it means new folios
   * take their title from the theme they are created with. Callers must not
   * substitute a literal title here, or that choice becomes unexpressible.
   *
   * @return string
   */
  private function get_default_folio_title()
  {
    $title = get_option('enrove_default_folio_title', '');

    return is_string($title) ? trim($title) : '';
  }

  private function get_default_folio_status()
  {
    $status = get_option('enrove_default_folio_status', 'draft');
    if (!in_array($status, ['draft', 'publish'], true)) {
      return 'draft';
    }

    return $status;
  }

  private function get_default_theme_id()
  {
    $themes = Themes_Manager::get_all_themes();
    if (empty($themes)) {
      return '';
    }

    $saved = (string) get_option('enrove_default_theme_id', '');
    if ($saved !== '' && isset($themes[$saved])) {
      return $saved;
    }

    return (string) array_key_first($themes);
  }

  private function get_settings_tab_url($tab, $args = array())
  {
    return Request::admin_url(static::PAGE_ID, array_merge(array('tab_key' => $tab), $args));
  }

  /**
   * The open tab: one this screen has, or General.
   *
   * @return string
   */
  private function current_tab()
  {
    return Request::choice('tab_key', array_keys((array) $this->get_tabs()), 'general');
  }

  private function can_manage_collection_tags()
  {
    return current_user_can('manage_options');
  }

  private function get_collection_tag_terms()
  {
    $terms = get_terms(array(
      'taxonomy' => 'enrove_collection_tag',
      'hide_empty' => false,
      'orderby' => 'name',
      'order' => 'ASC',
    ));

    return is_wp_error($terms) ? array() : (array) $terms;
  }

  private function get_collection_tag_edit_term()
  {
    $term_id = Request::int('edit_collection_tag');
    if ($term_id <= 0) {
      return null;
    }

    $term = get_term($term_id, 'enrove_collection_tag');
    if (!$term instanceof \WP_Term || is_wp_error($term)) {
      return null;
    }

    return $term;
  }

  private function redirect_to_settings_tab($tab, $args = array())
  {
    wp_safe_redirect($this->get_settings_tab_url($tab, $args));
    exit;
  }

  private function redirect_to_collections_tab($args = array())
  {
    $this->redirect_to_settings_tab('collections', $args);
  }

  private function redirect_to_imagery_tab($args = array())
  {
    $this->redirect_to_settings_tab('imagery', $args);
  }

  private function redirect_to_fonts_tab($args = array())
  {
    $this->redirect_to_settings_tab('fonts', $args);
  }

  /**
   * The Pexels helpers live in pexels/ and are autoloaded on demand.
   * Guard on them so the settings screen still renders if they are absent.
   * These two download the sample photos, and ship.
   */
  private function pexels_is_available()
  {
    return class_exists('\\Enrove\\Pexels\\Library') && class_exists('\\Enrove\\Pexels\\Credits');
  }

  /**
   * The API key, the API client and the curation script that uses them exist
   * only in a development checkout: the WordPress.org package leaves all
   * three out.
   */
  private function pexels_curation_available()
  {
    return is_readable(ENROVE_PATH . 'bin/curate-pexels.php')
      && class_exists('\\Enrove\\Pexels\\Key')
      && class_exists('\\Enrove\\Pexels\\Client');
  }

  private function get_pexels_key_source_label($source)
  {
    switch ($source) {
      case 'constant':
        return __('ENROVE_PEXELS_API_KEY in wp-config.php', 'enrove-folios');
      case 'env':
        return __('PEXELS_API_KEY environment variable', 'enrove-folios');
      case 'file':
        return __('.pexels-key file in the plugin folder', 'enrove-folios');
      case 'option':
        return __('This settings field', 'enrove-folios');
    }

    return __('Not configured', 'enrove-folios');
  }

  /**
   * Stash a one-shot notice for the current user (avoids putting API results in the URL).
   *
   * A failure carries the next step and the button it belongs to as well, so
   * the Imagery or Fonts tab can pin the explanation where the operator just pressed.
   *
   * @param string $status success|error|warning|info.
   * @param string $text   What happened.
   * @param string $hint   What to do about it. Failures only.
   * @param string $anchor CSS selector for the control to point at.
   */
  private function set_tab_notice($status, $text, $hint = '', $anchor = '')
  {
    set_transient('enrove_settings_notice_' . get_current_user_id(), array(
      'status' => $status,
      'text' => $text,
      'hint' => $hint,
      'anchor' => $anchor,
    ), MINUTE_IN_SECONDS);
  }

  /**
   * Report a notice taken with take_tab_notice() as a toast.
   *
   * @param array|null $notice
   */
  private function toast_tab_notice($notice)
  {
    if (!$notice) {
      return;
    }

    $notice_anchor = isset($notice['anchor']) ? (string) $notice['anchor'] : '';

    if ($notice_anchor !== '') {
      \Enrove\Toast::failure(
        $notice['text'],
        isset($notice['hint']) ? (string) $notice['hint'] : '',
        $notice_anchor,
        array(),
        $notice['status']
      );
    } else {
      \Enrove\Toast::add($notice['text'], $notice['status']);
    }
  }

  private function take_tab_notice()
  {
    $key = 'enrove_settings_notice_' . get_current_user_id();
    $notice = get_transient($key);
    if (!is_array($notice) || empty($notice['text'])) {
      return null;
    }

    delete_transient($key);

    return $notice;
  }

  /**
   * Handle saving of plugin settings.
   */
  public function handle_save()
  {
    check_admin_referer('enrove_save_settings', 'enrove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'enrove-folios'));
    }

    $tab = isset($_POST['tab_key']) ? sanitize_key(wp_unslash($_POST['tab_key'])) : 'general';

    // Each tab writes only its own options. Every tab used to fall through to
    // one of two branches, so saving General reset the routing slug to "folio"
    // — a tab quietly undoing a setting the operator had made on another one.
    if ('general' === $tab) {
      $themes = Themes_Manager::get_all_themes();
      $default_theme = isset($_POST['default_theme_id']) ? sanitize_key(wp_unslash($_POST['default_theme_id'])) : '';
      if ($default_theme === '' || !isset($themes[$default_theme])) {
        $default_theme = !empty($themes) ? (string) array_key_first($themes) : '';
      }
      if ($default_theme !== '') {
        update_option('enrove_default_theme_id', $default_theme);
      }

      $default_status = isset($_POST['default_folio_status']) ? sanitize_key(wp_unslash($_POST['default_folio_status'])) : 'draft';
      if (!in_array($default_status, ['draft', 'publish'], true)) {
        $default_status = 'draft';
      }
      update_option('enrove_default_folio_status', $default_status);

      // Blank is stored as blank: it is how the user asks for theme-derived
      // titles. Backfilling a literal here would pin every folio to one name.
      $default_title = isset($_POST['default_folio_title']) ? sanitize_text_field(wp_unslash($_POST['default_folio_title'])) : '';
      update_option('enrove_default_folio_title', trim($default_title));

      delete_option('enrove_default_allow_pdf_download');
    } elseif ('routing' === $tab) {
      // sanitize_title() keeps the %-encoded octets of a non-ASCII slug, which
      // is how the field shows one; sanitize_text_field() would strip them.
      $base_slug = isset($_POST['folio_base_slug']) ? sanitize_title(wp_unslash($_POST['folio_base_slug'])) : '';

      // Reported rather than corrected. Silently substituting "folio" would
      // send every published folio to a different URL than the operator asked
      // for, and the screen would show the substitution as if it were theirs.
      // Blank and unusable get different messages.
      if ($base_slug === '') {
        $typed = isset($_POST['folio_base_slug']) ? trim(sanitize_text_field(wp_unslash($_POST['folio_base_slug']))) : '';
        $this->redirect_to_settings_tab('routing', array('message' => $typed === '' ? 'base_slug_empty' : 'base_slug_invalid'));
      }

      update_option('enrove_folio_base_slug', $base_slug);
    } else {
      // Only reachable from a stale or hand-edited form; nothing was written,
      // so say so rather than confirming a save that did not happen.
      $this->redirect_to_settings_tab('general', array('message' => 'unknown_tab'));
    }

    $this->redirect_to_settings_tab($tab, array('message' => 'settings_saved'));
  }

  public function handle_collection_tag_save()
  {
    check_admin_referer('enrove_save_collection_tag', 'enrove_nonce');

    if (!$this->can_manage_collection_tags()) {
      wp_die(esc_html__('You do not have permission to manage collection tags.', 'enrove-folios'));
    }

    $term_name = isset($_POST['collection_tag_name']) ? sanitize_text_field(wp_unslash($_POST['collection_tag_name'])) : '';
    $term_slug = isset($_POST['collection_tag_slug']) ? sanitize_title(wp_unslash($_POST['collection_tag_slug'])) : '';
    $term_id = isset($_POST['collection_tag_id']) ? intval(wp_unslash($_POST['collection_tag_id'])) : 0;

    if ($term_name === '') {
      $redirect_args = array('message' => 'collection_tag_empty');
      if ($term_id > 0) {
        $redirect_args['edit_collection_tag'] = $term_id;
      }
      $this->redirect_to_collections_tab($redirect_args);
    }

    $term_args = array();
    if ($term_slug !== '') {
      $term_args['slug'] = $term_slug;
    }

    if ($term_id > 0) {
      $result = wp_update_term($term_id, 'enrove_collection_tag', array_merge($term_args, array(
        'name' => $term_name,
      )));

      if (is_wp_error($result)) {
        $this->redirect_to_collections_tab(array(
          'message' => 'collection_tag_error',
          'error_code' => $result->get_error_code(),
          'edit_collection_tag' => $term_id,
        ));
      }

      $this->redirect_to_collections_tab(array('message' => 'collection_tag_updated'));
    }

    $result = wp_insert_term($term_name, 'enrove_collection_tag', $term_args);

    if (is_wp_error($result)) {
      $this->redirect_to_collections_tab(array(
        'message' => 'collection_tag_error',
        'error_code' => $result->get_error_code(),
      ));
    }

    $this->redirect_to_collections_tab(array('message' => 'collection_tag_created'));
  }

  public function handle_collection_tag_delete()
  {
    check_admin_referer('enrove_delete_collection_tag', 'enrove_nonce');

    if (!$this->can_manage_collection_tags()) {
      wp_die(esc_html__('You do not have permission to manage collection tags.', 'enrove-folios'));
    }

    $term_id = isset($_POST['collection_tag_id']) ? intval(wp_unslash($_POST['collection_tag_id'])) : 0;
    if ($term_id <= 0) {
      $this->redirect_to_collections_tab(array('message' => 'collection_tag_error'));
    }

    $result = wp_delete_term($term_id, 'enrove_collection_tag');
    if (true !== $result) {
      $this->redirect_to_collections_tab(array(
        'message' => 'collection_tag_delete_error',
        'error_code' => is_wp_error($result) ? $result->get_error_code() : 'not_found',
      ));
    }

    $this->redirect_to_collections_tab(array('message' => 'collection_tag_deleted'));
  }

  public function display_general_fields()
  {
    $themes = Themes_Manager::get_all_themes();
    $default_theme_id = $this->get_default_theme_id();
    $default_status = $this->get_default_folio_status();
    $default_title = $this->get_default_folio_title();
    $default_theme_title = Themes_Manager::get_default_folio_title($default_theme_id);
    ?>
<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="g-settings-form space-y-4" data-enrove-track-changes>
  <?php wp_nonce_field('enrove_save_settings', 'enrove_nonce'); ?>
  <input type="hidden" name="action" value="save_enrove_settings" />
  <input type="hidden" name="tab_key" value="general" />

  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Folio Defaults', 'enrove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600"><?php esc_html_e('These defaults are applied when you create a new folio.', 'enrove-folios'); ?></p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div>
        <label for="enrove-default-theme-id" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
          <?php esc_html_e('Default Theme', 'enrove-folios'); ?>
        </label>
        <select id="enrove-default-theme-id" name="default_theme_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
          <?php foreach ($themes as $theme_id => $theme): ?>
          <option value="<?php echo esc_attr($theme_id); ?>" <?php selected($default_theme_id, $theme_id); ?>
            data-default-title="<?php echo esc_attr(Themes_Manager::get_default_folio_title($theme_id)); ?>">
            <?php echo esc_html($theme['name']); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label for="enrove-default-folio-status" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
          <?php esc_html_e('Default Publish Status', 'enrove-folios'); ?>
        </label>
        <select id="enrove-default-folio-status" name="default_folio_status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
          <option value="draft" <?php selected($default_status, 'draft'); ?>><?php esc_html_e('Draft', 'enrove-folios'); ?></option>
          <option value="publish" <?php selected($default_status, 'publish'); ?>><?php esc_html_e('Published', 'enrove-folios'); ?></option>
        </select>
      </div>
    </div>

    <div>
      <label for="enrove-default-folio-title" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
        <?php esc_html_e('Default Folio Title', 'enrove-folios'); ?>
      </label>
      <input
        id="enrove-default-folio-title"
        type="text"
        name="default_folio_title"
        value="<?php echo esc_attr($default_title); ?>"
        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
        placeholder="<?php echo esc_attr($default_theme_title); ?>" />
      <p class="mt-2 mb-0 text-sm text-gray-600">
        <?php esc_html_e('Leave blank to name each folio after the theme it is created with. Anything you type here is used for every folio instead.', 'enrove-folios'); ?>
      </p>
    </div>

    <?php /* The placeholder follows the Default Theme select; see "Default folio
             title placeholder" in assets/js/enrove-main.js. */ ?>

    <div>
      <button
        type="submit"
        id="enrove-save-general"
        class="button button-primary"
        data-enrove-save
        data-enrove-save-idle="<?php esc_attr_e('Nothing to save — these settings already match what is stored.', 'enrove-folios'); ?>"><?php esc_html_e('Save Changes', 'enrove-folios'); ?></button>
    </div>
  </section>
</form>
<?php
  }

  public function display_routing_fields()
  {
    $base_slug = Utils::get_folio_base_slug();
    $sample_folio = home_url('/' . $base_slug . '/my-folio');
    $sample_page = home_url('/' . $base_slug . '/my-folio/page/about');
    ?>
<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="g-settings-form space-y-4" data-enrove-track-changes>
  <?php wp_nonce_field('enrove_save_settings', 'enrove_nonce'); ?>
  <input type="hidden" name="action" value="save_enrove_settings" />
  <input type="hidden" name="tab_key" value="routing" />

  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Routing', 'enrove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600"><?php esc_html_e('Customize the public URL base for folios.', 'enrove-folios'); ?></p>
    </div>

    <div>
      <label for="enrove-folio-base-slug" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
        <?php esc_html_e('Folio Base Slug', 'enrove-folios'); ?>
      </label>
      <div class="mt-1 flex rounded-md shadow-sm">
        <span class="inline-flex items-center rounded-l-md border border-r-0 border-gray-300 bg-gray-50 px-3 text-gray-500 sm:text-sm">
          <?php echo esc_html(home_url('/')); ?>
        </span>
        <input
          id="enrove-folio-base-slug"
          type="text"
          name="folio_base_slug"
          value="<?php echo esc_attr($base_slug); ?>"
          class="block w-full min-w-0 flex-1 rounded-none rounded-r-md border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
          placeholder="folio" />
      </div>
    </div>

    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3 text-sm text-gray-700">
      <p class="m-0"><?php esc_html_e('Example folio URL:', 'enrove-folios'); ?> <code><?php echo esc_html($sample_folio); ?></code></p>
      <p class="mt-2 mb-0"><?php esc_html_e('Example page URL:', 'enrove-folios'); ?> <code><?php echo esc_html($sample_page); ?></code></p>
    </div>

    <div>
      <button
        type="submit"
        id="enrove-save-routing"
        class="button button-primary"
        data-enrove-save
        data-enrove-save-idle="<?php esc_attr_e('Nothing to save — these settings already match what is stored.', 'enrove-folios'); ?>"><?php esc_html_e('Save Changes', 'enrove-folios'); ?></button>
    </div>
  </section>
</form>
<?php
  }

  public function display_collections_fields()
  {
    $terms = $this->get_collection_tag_terms();
    $edit_term = $this->get_collection_tag_edit_term();
    $is_editing = $edit_term instanceof \WP_Term;
    $form_title = $is_editing ? __('Edit Collection Tag', 'enrove-folios') : __('Add Collection Tag', 'enrove-folios');
    $submit_label = $is_editing ? __('Update Tag', 'enrove-folios') : __('Add Tag', 'enrove-folios');
    $submit_idle = $is_editing
      ? __('Nothing to save — this tag already matches what is stored.', 'enrove-folios')
      : __('Give the tag a name first — the slug is optional.', 'enrove-folios');
    ?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4 xl:col-span-1">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php echo esc_html($form_title); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600"><?php esc_html_e('Manage the tags used to group folios into collections.', 'enrove-folios'); ?></p>
    </div>

    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="space-y-4" data-enrove-track-changes>
      <?php wp_nonce_field('enrove_save_collection_tag', 'enrove_nonce'); ?>
      <input type="hidden" name="action" value="save_enrove_collection_tag" />
      <input type="hidden" name="collection_tag_id" value="<?php echo esc_attr($is_editing ? (string) $edit_term->term_id : '0'); ?>" />

      <div>
        <label for="enrove-collection-tag-name" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
          <?php esc_html_e('Name', 'enrove-folios'); ?>
        </label>
        <input
          id="enrove-collection-tag-name"
          type="text"
          name="collection_tag_name"
          value="<?php echo esc_attr($is_editing ? $edit_term->name : ''); ?>"
          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
          placeholder="<?php esc_attr_e('Magazine', 'enrove-folios'); ?>" />
      </div>

      <div>
        <label for="enrove-collection-tag-slug" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
          <?php esc_html_e('Slug', 'enrove-folios'); ?>
        </label>
        <input
          id="enrove-collection-tag-slug"
          type="text"
          name="collection_tag_slug"
          value="<?php echo esc_attr($is_editing ? $edit_term->slug : ''); ?>"
          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
          placeholder="<?php esc_attr_e('magazine', 'enrove-folios'); ?>" />
        <p class="mt-1 mb-0 text-xs text-gray-400"><?php esc_html_e('Optional. Leave blank to generate from the name.', 'enrove-folios'); ?></p>
      </div>

      <div class="flex items-center gap-2">
        <button
          type="submit"
          id="enrove-save-collection-tag"
          class="button button-primary"
          data-enrove-save
          data-enrove-save-idle="<?php echo esc_attr($submit_idle); ?>"><?php echo esc_html($submit_label); ?></button>
        <?php if ($is_editing): ?>
          <a href="<?php echo esc_url($this->get_settings_tab_url('collections')); ?>" class="button button-secondary"><?php esc_html_e('Cancel', 'enrove-folios'); ?></a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 xl:col-span-2">
    <div class="mb-4">
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Existing Collection Tags', 'enrove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600"><?php esc_html_e('These tags can be assigned on folio setup screens and used to filter All Folios.', 'enrove-folios'); ?></p>
    </div>

    <?php if (empty($terms)): ?>
      <p class="m-0 text-sm text-gray-600"><?php esc_html_e('No collection tags yet.', 'enrove-folios'); ?></p>
    <?php else: ?>
      <table class="widefat striped">
        <thead>
          <tr>
            <th><?php esc_html_e('Name', 'enrove-folios'); ?></th>
            <th><?php esc_html_e('Slug', 'enrove-folios'); ?></th>
            <th><?php esc_html_e('Folios', 'enrove-folios'); ?></th>
            <th><?php esc_html_e('Actions', 'enrove-folios'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($terms as $term): ?>
            <?php if ($term instanceof \WP_Term): ?>
              <tr>
                <td><?php echo esc_html($term->name); ?></td>
                <td><code><?php echo esc_html($term->slug); ?></code></td>
                <td><?php echo esc_html(number_format_i18n((int) $term->count)); ?></td>
                <td>
                  <a href="<?php echo esc_url($this->get_settings_tab_url('collections', array('edit_collection_tag' => (int) $term->term_id))); ?>">
                    <?php esc_html_e('Edit', 'enrove-folios'); ?>
                  </a>
                  <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="inline-block ml-3" data-enrove-confirm="<?php esc_attr_e('Delete this collection tag?', 'enrove-folios'); ?>">
                    <?php wp_nonce_field('enrove_delete_collection_tag', 'enrove_nonce'); ?>
                    <input type="hidden" name="action" value="delete_enrove_collection_tag" />
                    <input type="hidden" name="collection_tag_id" value="<?php echo esc_attr((string) $term->term_id); ?>" />
                    <button type="submit" class="button-link delete"><?php esc_html_e('Delete', 'enrove-folios'); ?></button>
                  </form>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>
<?php
  }

  /**
   * Save the Pexels API key. An empty submission leaves the stored key alone.
   */
  public function handle_pexels_key_save()
  {
    check_admin_referer('enrove_save_pexels_key', 'enrove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'enrove-folios'));
    }

    if ($this->pexels_curation_available() && \Enrove\Pexels\Key::is_locked_by_constant()) {
      $this->set_tab_notice(
        'warning',
        __('ENROVE_PEXELS_API_KEY is defined in wp-config.php, so the stored key was not changed.', 'enrove-folios'),
        __('The constant wins over anything saved here. Remove it from wp-config.php if you want to manage the key from this screen.', 'enrove-folios'),
        '#enrove-save-pexels-key'
      );
      $this->redirect_to_imagery_tab();
    }

    $submitted = isset($_POST['pexels_api_key']) ? sanitize_text_field(wp_unslash($_POST['pexels_api_key'])) : '';

    if ($submitted === '') {
      $this->set_tab_notice('info', __('No key entered — the stored key was left unchanged.', 'enrove-folios'));
      $this->redirect_to_imagery_tab();
    }

    // update_option() cannot change the autoload flag of an existing option,
    // so delete first and re-add with autoload explicitly off.
    delete_option('enrove_pexels_api_key');
    add_option('enrove_pexels_api_key', $submitted, '', 'no');

    $this->set_tab_notice('success', __('Pexels API key saved.', 'enrove-folios'));
    $this->redirect_to_imagery_tab();
  }

  /**
   * Clear the stored Pexels API key.
   */
  public function handle_pexels_key_delete()
  {
    check_admin_referer('enrove_delete_pexels_key', 'enrove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'enrove-folios'));
    }

    delete_option('enrove_pexels_api_key');

    $this->set_tab_notice('success', __('Stored Pexels API key removed.', 'enrove-folios'));
    $this->redirect_to_imagery_tab();
  }

  /**
   * Probe the Pexels API. Only ever runs from an explicit button press —
   * never on a page load.
   */
  public function handle_pexels_connection_test()
  {
    check_admin_referer('enrove_test_pexels_connection', 'enrove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'enrove-folios'));
    }

    if (!$this->pexels_curation_available()) {
      $this->set_tab_notice(
        'error',
        __('The Pexels client is unavailable.', 'enrove-folios'),
        __('The pexels/ helpers are missing from this copy of the plugin. Reinstall or update Enrove Folios to restore them.', 'enrove-folios'),
        '#enrove-test-pexels-connection'
      );
      $this->redirect_to_imagery_tab();
    }

    $client = new \Enrove\Pexels\Client();

    if (!$client->has_key()) {
      $this->set_tab_notice(
        'error',
        __('No Pexels API key is configured.', 'enrove-folios'),
        __('Paste a key into the field above and save it, then test the connection again.', 'enrove-folios'),
        '#enrove-test-pexels-connection'
      );
      $this->redirect_to_imagery_tab();
    }

    $result = $client->verify();

    if (is_wp_error($result)) {
      $this->set_tab_notice(
        'error',
        sprintf(
          /* translators: %s: error message returned by the Pexels API. */
          __('Pexels rejected the request: %s', 'enrove-folios'),
          $result->get_error_message()
        ),
        __('Check the key is still active in your Pexels account and paste it again. A key created moments ago can take a few minutes to work.', 'enrove-folios'),
        '#enrove-test-pexels-connection'
      );
      $this->redirect_to_imagery_tab();
    }

    $remaining = is_array($result) && isset($result['remaining']) ? (int) $result['remaining'] : 0;
    $limit = is_array($result) && isset($result['limit']) ? (int) $result['limit'] : 0;

    $this->set_tab_notice('success', sprintf(
      /* translators: 1: remaining requests, 2: hourly request limit. */
      __('Connected to Pexels. %1$s of %2$s requests remaining this hour.', 'enrove-folios'),
      number_format_i18n($remaining),
      number_format_i18n($limit)
    ));
    $this->redirect_to_imagery_tab();
  }

  /**
   * Download the sample photos this site does not have yet. Only ever runs from
   * an explicit button press; see \Enrove\Pexels\Library for why the plugin
   * does not simply carry them.
   */
  public function handle_sample_photos_download()
  {
    check_admin_referer('enrove_download_sample_photos', 'enrove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'enrove-folios'));
    }

    \Enrove\Pexels\Library::set_source(\Enrove\Pexels\Library::SOURCE_PEXELS);
    $result = \Enrove\Pexels\Library::download_missing();
    $failed = count($result['failed']);

    if ($failed === 0 && $result['remaining'] === 0) {
      $this->set_tab_notice('success', sprintf(
        /* translators: %s: number of photos downloaded. */
        _n('Downloaded %s photo.', 'Downloaded %s photos.', $result['downloaded'], 'enrove-folios'),
        number_format_i18n($result['downloaded'])
      ));
      $this->redirect_to_imagery_tab();
    }

    if ($failed === 0) {
      $this->set_tab_notice(
        'warning',
        sprintf(
          /* translators: 1: photos downloaded, 2: photos still to fetch. */
          _n('Downloaded %1$s photos; %2$s still to fetch.', 'Downloaded %1$s photos; %2$s still to fetch.', $result['remaining'], 'enrove-folios'),
          number_format_i18n($result['downloaded']),
          number_format_i18n($result['remaining'])
        ),
        __('This server took a while, so the download stopped before it could time out. Press Download Photos again to fetch the rest.', 'enrove-folios'),
        '#enrove-download-sample-photos'
      );
      $this->redirect_to_imagery_tab();
    }

    $this->set_tab_notice(
      'error',
      sprintf(
        /* translators: 1: photos downloaded, 2: photos that failed. */
        __('Downloaded %1$s photos; %2$s could not be fetched.', 'enrove-folios'),
        number_format_i18n($result['downloaded']),
        number_format_i18n($failed)
      ),
      sprintf(
        /* translators: %s: the first error message. */
        __('First error: %s Press Download again to retry the ones that are missing.', 'enrove-folios'),
        (string) reset($result['failed'])
      ),
      '#enrove-download-sample-photos'
    );
    $this->redirect_to_imagery_tab();
  }

  /**
   * Download the folio fonts this site does not have yet. Only ever runs from
   * an explicit button press; see \Enrove\Themes\Font_Library for why folios
   * do not load them from Google.
   */
  public function handle_fonts_download()
  {
    check_admin_referer('enrove_download_fonts', 'enrove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'enrove-folios'));
    }

    // Downloading is choosing Google's fonts, including over an earlier
    // choice of system fonts.
    \Enrove\Themes\Font_Library::set_source(\Enrove\Themes\Font_Library::SOURCE_GOOGLE);
    $result = \Enrove\Themes\Font_Library::download_missing();
    $failed = count($result['failed']);

    if ($failed === 0 && $result['remaining'] === 0) {
      $this->set_tab_notice('success', sprintf(
        /* translators: %s: number of font families downloaded. */
        _n('Downloaded %s font family.', 'Downloaded %s font families.', $result['downloaded'], 'enrove-folios'),
        number_format_i18n($result['downloaded'])
      ));
      $this->redirect_to_fonts_tab();
    }

    if ($failed === 0) {
      $this->set_tab_notice(
        'warning',
        sprintf(
          /* translators: 1: font families downloaded, 2: font families still to fetch. */
          __('Downloaded %1$s font families; %2$s still to fetch.', 'enrove-folios'),
          number_format_i18n($result['downloaded']),
          number_format_i18n($result['remaining'])
        ),
        __('This server took a while, so the download stopped before it could time out. Press Download Fonts again to fetch the rest.', 'enrove-folios'),
        '#enrove-download-fonts'
      );
      $this->redirect_to_fonts_tab();
    }

    $this->set_tab_notice(
      'error',
      sprintf(
        /* translators: 1: font families downloaded, 2: font families that failed. */
        __('Downloaded %1$s font families; %2$s could not be fetched.', 'enrove-folios'),
        number_format_i18n($result['downloaded']),
        number_format_i18n($failed)
      ),
      sprintf(
        /* translators: %s: the first error message. */
        __('First error: %s Press Download Fonts again to retry the ones that are missing.', 'enrove-folios'),
        (string) reset($result['failed'])
      ),
      '#enrove-download-fonts'
    );
    $this->redirect_to_fonts_tab();
  }

  /**
   * Switch between the downloaded fonts and system fonts. Nothing is fetched
   * or deleted: choosing system fonts leaves any downloaded files in place, so
   * switching back is instant.
   */
  public function handle_font_source()
  {
    check_admin_referer('enrove_set_font_source', 'enrove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'enrove-folios'));
    }

    $source = isset($_POST['font_source']) ? sanitize_key(wp_unslash($_POST['font_source'])) : '';
    if (!in_array($source, array(\Enrove\Themes\Font_Library::SOURCE_GOOGLE, \Enrove\Themes\Font_Library::SOURCE_SYSTEM), true)) {
      wp_die(esc_html__('Unknown font source.', 'enrove-folios'));
    }
    \Enrove\Themes\Font_Library::set_source($source);

    $this->set_tab_notice('success', $source === \Enrove\Themes\Font_Library::SOURCE_SYSTEM
      ? __('Folios now use system fonts.', 'enrove-folios')
      : __('Folios now use their theme fonts.', 'enrove-folios'));
    $this->redirect_to_fonts_tab();
  }

  /**
   * Delete the downloaded fonts and forget the font choice. Cheap to undo — a
   * press of Download Fonts brings them back — so there is no confirmation
   * step; the button's own note says what happens.
   */
  public function handle_fonts_remove()
  {
    check_admin_referer('enrove_remove_fonts', 'enrove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'enrove-folios'));
    }

    $removed = \Enrove\Themes\Font_Library::remove_downloaded();

    $this->set_tab_notice('success', sprintf(
      /* translators: %s: number of font families removed. */
      _n('Removed %s font family. Folios use system fonts until you download them again.', 'Removed %s font families. Folios use system fonts until you download them again.', $removed, 'enrove-folios'),
      number_format_i18n($removed)
    ));
    $this->redirect_to_fonts_tab();
  }

  /**
   * Delete the downloaded photos and forget the photo choice; see
   * handle_fonts_remove() for why it does not ask first.
   */
  public function handle_sample_photos_remove()
  {
    check_admin_referer('enrove_remove_sample_photos', 'enrove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'enrove-folios'));
    }

    $removed = \Enrove\Pexels\Library::remove_downloaded();

    $this->set_tab_notice('success', sprintf(
      /* translators: %s: number of photos removed. */
      _n('Removed %s photo.', 'Removed %s photos.', $removed, 'enrove-folios'),
      number_format_i18n($removed)
    ));
    $this->redirect_to_imagery_tab();
  }

  /**
   * Reset the plugin (\Enrove\Setup\Reset), keeping the folios unless the
   * confirmation dialog's answer was to delete them. Lands on Overview, where a
   * fresh install starts, so the setup dialog greets the administrator again.
   */
  public function handle_plugin_reset()
  {
    check_admin_referer('enrove_reset_plugin', 'enrove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'enrove-folios'));
    }

    // Anything but an explicit "delete" keeps the content.
    $content = isset($_POST['enrove_reset_content']) ? sanitize_key(wp_unslash($_POST['enrove_reset_content'])) : 'keep';
    $keep = $content !== 'delete';

    // With nothing to keep or delete, the outcome says neither.
    $had_content = array_sum(\Enrove\Setup\Reset::content_counts()) > 0;
    $deleted = \Enrove\Setup\Reset::run($keep);

    $outcome = 'none';
    if ($had_content) {
      $outcome = $keep ? 'kept' : 'deleted';
    }

    wp_safe_redirect(Request::admin_url(
      Overview::PAGE_ID,
      array(
        'enrove_reset' => $outcome,
        'enrove_reset_folios' => $deleted['folios'],
        'enrove_reset_pages' => $deleted['pages'],
        'enrove_reset_tags' => $deleted['tags'],
      )
    ));
    exit;
  }

  public function display_fonts_fields()
  {
    $fonts = \Enrove\Themes\Font_Library::status();
    $system = \Enrove\Themes\Font_Library::source() === \Enrove\Themes\Font_Library::SOURCE_SYSTEM;
    $this->toast_tab_notice($this->take_tab_notice());
    ?>
<div class="space-y-4">
  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Folio Fonts', 'enrove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600">
        <?php esc_html_e('Folio themes are typeset in open-licence fonts from Google Fonts. Folios load them from your own site, never from Google, so your readers’ browsers do not contact Google. Download them once here, or use system fonts instead; until the fonts are here, folios use system fonts.', 'enrove-folios'); ?>
      </p>
    </div>

    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3 text-sm text-gray-700">
      <p class="m-0">
        <?php
        if ($system) {
          esc_html_e('Folios use system fonts — the ones the WordPress dashboard uses.', 'enrove-folios');
          echo ' ';
        }
        printf(
          /* translators: 1: font families on this site, 2: font families in total. */
          esc_html__('%1$s of %2$s font families are on this site.', 'enrove-folios'),
          esc_html(number_format_i18n($fonts['present'])),
          esc_html(number_format_i18n($fonts['total']))
        );
        ?>
      </p>
    </div>

    <div class="g-settings-actions">
      <?php if (!empty($fonts['missing'])): ?>
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <?php wp_nonce_field('enrove_download_fonts', 'enrove_nonce'); ?>
        <input type="hidden" name="action" value="download_enrove_fonts" />
        <button type="submit" id="enrove-download-fonts" class="button button-primary">
          <?php esc_html_e('Download Fonts', 'enrove-folios'); ?>
        </button>
      </form>
      <?php elseif ($system): ?>
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <?php wp_nonce_field('enrove_set_font_source', 'enrove_nonce'); ?>
        <input type="hidden" name="action" value="set_enrove_font_source" />
        <input type="hidden" name="font_source" value="<?php echo esc_attr(\Enrove\Themes\Font_Library::SOURCE_GOOGLE); ?>" />
        <button type="submit" class="button button-primary">
          <?php esc_html_e('Use Theme Fonts', 'enrove-folios'); ?>
        </button>
      </form>
      <?php endif; ?>

      <?php if (!$system): ?>
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <?php wp_nonce_field('enrove_set_font_source', 'enrove_nonce'); ?>
        <input type="hidden" name="action" value="set_enrove_font_source" />
        <input type="hidden" name="font_source" value="<?php echo esc_attr(\Enrove\Themes\Font_Library::SOURCE_SYSTEM); ?>" />
        <button type="submit" class="button button-secondary">
          <?php esc_html_e('Use System Fonts', 'enrove-folios'); ?>
        </button>
      </form>
      <?php endif; ?>
    </div>

    <?php if (!empty($fonts['missing'])): ?>
    <p class="m-0 text-xs text-gray-500">
      <?php esc_html_e('Downloads about 4 MB from fonts.googleapis.com and fonts.gstatic.com into your uploads folder. Google sees your server’s IP address for those requests, never a reader’s, and nothing is fetched until you press the button.', 'enrove-folios'); ?>
    </p>
    <?php endif; ?>

    <?php if ($fonts['present'] > 0): ?>
    <div class="g-settings-remove">
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <?php wp_nonce_field('enrove_remove_fonts', 'enrove_nonce'); ?>
        <input type="hidden" name="action" value="remove_enrove_fonts" />
        <button type="submit" class="button button-secondary g-settings-remove__button">
          <?php esc_html_e('Remove Downloaded Fonts', 'enrove-folios'); ?>
        </button>
      </form>
      <p class="g-settings-remove__note">
        <?php esc_html_e('Deletes the font files from your uploads folder and forgets your font choice, so Enrove Folios asks again. Folios use system fonts until the fonts are downloaded again.', 'enrove-folios'); ?>
      </p>
    </div>
    <?php endif; ?>
  </section>
</div>
<?php
  }

  public function display_imagery_fields()
  {
    $available = $this->pexels_is_available();
    // The API key and the curation script only matter in a development checkout.
    // The WordPress.org package leaves them out, so neither section is shown.
    $can_curate = $this->pexels_curation_available();
    $locked = $can_curate ? \Enrove\Pexels\Key::is_locked_by_constant() : false;
    $source = $can_curate ? \Enrove\Pexels\Key::source() : '';
    $masked = $can_curate ? \Enrove\Pexels\Key::masked() : '';
    $has_key = $source !== '';
    $has_option_key = get_option('enrove_pexels_api_key', '') !== '';
    $notice = $this->take_tab_notice();

    $photos = $available ? \Enrove\Pexels\Library::status() : array('total' => 0, 'present' => 0, 'missing' => array());
    $photos_downloaded = $available ? \Enrove\Pexels\Library::downloaded_count() : 0;

    $credits = array();
    $credits_exist = false;
    if (class_exists('\Enrove\Pexels\Credits')) {
      $credits_exist = file_exists(\Enrove\Pexels\Credits::path());
      $credits = \Enrove\Pexels\Credits::all();
    }

    // The outcome of saving, clearing or probing the key is a toast, and a
    // failure also leaves its reason pinned to the button that produced it.
    // The "helpers are missing" warning below stays inline: it describes the
    // standing state of this screen, not something the operator just did.
    $this->toast_tab_notice($notice);
    ?>
<div class="space-y-4">
  <?php if (!$available): ?>
  <div class="notice notice-warning">
    <p><?php esc_html_e('The Pexels helper classes are not installed in this copy of the plugin.', 'enrove-folios'); ?></p>
  </div>
  <?php endif; ?>

  <?php if ($photos['total'] > 0): ?>
  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Sample Photos', 'enrove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600">
        <?php esc_html_e('Theme covers and the photos in sample content come from Pexels. The Pexels licence does not allow them to be packaged with the plugin, so this site fetches its own copy when you ask.', 'enrove-folios'); ?>
      </p>
    </div>

    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3 text-sm text-gray-700">
      <p class="m-0">
        <?php
        printf(
          /* translators: 1: photos on this site, 2: photos in total. */
          esc_html__('%1$s of %2$s photos are on this site.', 'enrove-folios'),
          esc_html(number_format_i18n($photos['present'])),
          esc_html(number_format_i18n($photos['total']))
        );
        // A development checkout carries the photos in the plugin, so there
        // is nothing downloaded to remove — say so, or the missing Remove
        // button reads as a fault.
        if ($photos['present'] > 0 && $photos_downloaded === 0) {
          echo ' ';
          esc_html_e('They come with this copy of the plugin, so there is nothing to download or remove.', 'enrove-folios');
        }
        ?>
      </p>
    </div>

    <?php if (!empty($photos['missing'])): ?>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
      <?php wp_nonce_field('enrove_download_sample_photos', 'enrove_nonce'); ?>
      <input type="hidden" name="action" value="download_enrove_sample_photos" />
      <button type="submit" id="enrove-download-sample-photos" class="button button-primary">
        <?php esc_html_e('Download Photos', 'enrove-folios'); ?>
      </button>
    </form>
    <?php endif; ?>

    <?php if (!empty($photos['missing'])): ?>
    <p class="m-0 text-xs text-gray-500">
      <?php esc_html_e('Downloads about 4 MB from images.pexels.com into your uploads folder. Nothing is sent to Pexels beyond the requests for the photos, and nothing is fetched until you press the button. Folios seeded before the download pick the photos up once they are here.', 'enrove-folios'); ?>
    </p>
    <?php endif; ?>

    <?php if ($photos_downloaded > 0): ?>
    <div class="g-settings-remove">
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <?php wp_nonce_field('enrove_remove_sample_photos', 'enrove_nonce'); ?>
        <input type="hidden" name="action" value="remove_enrove_sample_photos" />
        <button type="submit" class="button button-secondary g-settings-remove__button">
          <?php esc_html_e('Remove Downloaded Photos', 'enrove-folios'); ?>
        </button>
      </form>
      <p class="g-settings-remove__note">
        <?php esc_html_e('Deletes the downloaded photos from your uploads folder and forgets your photo choice, so Enrove Folios asks again. Covers show gradients, and pictures inside sample folios go missing, until the photos are downloaded again. Featured images already in the Media Library stay.', 'enrove-folios'); ?>
      </p>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($can_curate): ?>
  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Pexels API Key', 'enrove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600">
        <?php
        printf(
          /* translators: %s: the curation command. */
          esc_html__('Used only by the imagery curation script in a development checkout (%s), which picks the covers and sample photos and records where each can be downloaded from. Folios never call the Pexels API when they are viewed or edited.', 'enrove-folios'),
          '<code>php bin/curate-pexels.php --help</code>'
        );
        ?>
      </p>
    </div>

    <?php if ($locked): ?>
    <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
      <p class="m-0">
        <?php
        printf(
          /* translators: %s: the wp-config.php constant name. */
          esc_html__('%s is defined in wp-config.php and takes precedence. The field below is disabled.', 'enrove-folios'),
          '<code>ENROVE_PEXELS_API_KEY</code>'
        );
        ?>
      </p>
    </div>
    <?php endif; ?>

    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="space-y-4" autocomplete="off" data-enrove-track-changes>
      <?php wp_nonce_field('enrove_save_pexels_key', 'enrove_nonce'); ?>
      <input type="hidden" name="action" value="save_enrove_pexels_key" />

      <div>
        <label for="enrove-pexels-api-key" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
          <?php esc_html_e('API Key', 'enrove-folios'); ?>
        </label>
        <input
          id="enrove-pexels-api-key"
          type="password"
          name="pexels_api_key"
          value=""
          autocomplete="new-password"
          spellcheck="false"
          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:text-gray-400"
          placeholder="<?php echo esc_attr($masked !== '' ? $masked : __('Paste your Pexels API key', 'enrove-folios')); ?>"
          <?php disabled($locked, true); ?> />
        <p class="mt-1 mb-0 text-xs text-gray-400">
          <?php esc_html_e('The stored key is never displayed. Leave this empty to keep the current key.', 'enrove-folios'); ?>
        </p>
      </div>

      <div>
        <button
          type="submit"
          id="enrove-save-pexels-key"
          class="button button-primary"
          data-enrove-save
          data-enrove-save-idle="<?php echo esc_attr__('Paste a key into the field above to save it.', 'enrove-folios'); ?>"
          <?php disabled($locked, true); ?>><?php esc_html_e('Save Key', 'enrove-folios'); ?></button>
      </div>
    </form>

    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3 text-sm text-gray-700 space-y-1">
      <p class="m-0">
        <strong><?php esc_html_e('Key source:', 'enrove-folios'); ?></strong>
        <?php echo esc_html($this->get_pexels_key_source_label($source)); ?>
        <?php if ($masked !== ''): ?>
        <code><?php echo esc_html($masked); ?></code>
        <?php endif; ?>
      </p>
      <p class="m-0 text-xs text-gray-500">
        <?php esc_html_e('Checked in order: wp-config.php constant, PEXELS_API_KEY environment variable, .pexels-key file, then this setting.', 'enrove-folios'); ?>
      </p>
    </div>

    <div class="flex items-center gap-2">
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <?php wp_nonce_field('enrove_test_pexels_connection', 'enrove_nonce'); ?>
        <input type="hidden" name="action" value="test_enrove_pexels_connection" />
        <button type="submit" id="enrove-test-pexels-connection" class="button button-secondary" <?php disabled(!$has_key, true); ?>>
          <?php esc_html_e('Test Connection', 'enrove-folios'); ?>
        </button>
      </form>

      <?php if ($has_option_key): ?>
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" data-enrove-confirm="<?php esc_attr_e('Remove the stored Pexels API key?', 'enrove-folios'); ?>">
        <?php wp_nonce_field('enrove_delete_pexels_key', 'enrove_nonce'); ?>
        <input type="hidden" name="action" value="delete_enrove_pexels_key" />
        <button type="submit" class="button-link delete"><?php esc_html_e('Remove Key', 'enrove-folios'); ?></button>
      </form>
      <?php endif; ?>
    </div>

    <p class="m-0 text-xs text-gray-500">
      <?php esc_html_e('Testing the connection is the only action on this screen that contacts Pexels, and it only happens when you press the button.', 'enrove-folios'); ?>
    </p>
  </section>
  <?php endif; ?>

  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Image Credits', 'enrove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600">
        <?php esc_html_e('The Pexels licence requires a visible link to Pexels and credit to each photographer.', 'enrove-folios'); ?>
      </p>
    </div>

    <p class="m-0 text-sm">
      <a href="https://www.pexels.com" target="_blank" rel="noopener noreferrer" class="font-semibold text-indigo-600 hover:text-indigo-500">
        <?php esc_html_e('Photos provided by Pexels', 'enrove-folios'); ?>
      </a>
    </p>

    <?php if (empty($credits)): ?>
    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3 text-sm text-gray-700">
      <p class="m-0"><?php esc_html_e('No imagery has been curated yet, so there is nothing to credit.', 'enrove-folios'); ?></p>
      <?php if ($can_curate): ?>
      <p class="mt-2 mb-0">
        <?php esc_html_e('Run the curation script to download imagery and build the credits file:', 'enrove-folios'); ?>
        <code>php bin/curate-pexels.php</code>
      </p>
      <?php endif; ?>
      <?php if ($credits_exist): ?>
      <p class="mt-2 mb-0 text-xs text-gray-500"><?php esc_html_e('A credits file exists but contains no entries.', 'enrove-folios'); ?></p>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <table class="widefat striped">
      <thead>
        <tr>
          <th><?php esc_html_e('Slot', 'enrove-folios'); ?></th>
          <th><?php esc_html_e('Photographer', 'enrove-folios'); ?></th>
          <th><?php esc_html_e('Photo', 'enrove-folios'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($credits as $slug => $credit): ?>
          <?php
          if (!is_array($credit)) {
            continue;
          }
          $credit_slug = isset($credit['slug']) ? (string) $credit['slug'] : (string) $slug;
          $photographer = isset($credit['photographer']) ? (string) $credit['photographer'] : '';
          $photographer_url = isset($credit['photographer_url']) ? (string) $credit['photographer_url'] : '';
          $photo_url = isset($credit['pexels_url']) ? (string) $credit['pexels_url'] : '';
          $photo_id = isset($credit['pexels_id']) ? (string) $credit['pexels_id'] : '';
          ?>
          <tr>
            <td><code><?php echo esc_html($credit_slug); ?></code></td>
            <td>
              <?php if ($photographer !== '' && $photographer_url !== ''): ?>
                <a href="<?php echo esc_url($photographer_url); ?>" target="_blank" rel="noopener noreferrer">
                  <?php echo esc_html($photographer); ?>
                </a>
              <?php elseif ($photographer !== ''): ?>
                <?php echo esc_html($photographer); ?>
              <?php else: ?>
                <span class="text-gray-400">&mdash;</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($photo_url !== ''): ?>
                <a href="<?php echo esc_url($photo_url); ?>" target="_blank" rel="noopener noreferrer">
                  <?php echo esc_html($photo_id !== '' ? '#' . $photo_id : __('View on Pexels', 'enrove-folios')); ?>
                </a>
              <?php else: ?>
                <span class="text-gray-400">&mdash;</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>
</div>
<?php
  }

  /**
   * Privacy tab.
   *
   * A statement, not a form. The plugin collects nothing, so there is no
   * preference to store — and a Save button over a panel with no inputs would
   * imply there is. What it does list is every request the plugin actually
   * makes, including the one that reaches a third party without the reader
   * necessarily realising it: a folio's typefaces are fetched by the visitor's
   * own browser, so Google sees their IP address. Saying "we collect nothing"
   * and stopping there would be true about us and misleading about them.
   *
   * There is deliberately no link out to a privacy policy. This panel is the
   * disclosure, and it stays accurate because it sits beside the code it
   * describes; a policy on a website does not. The one on studioen.us covers
   * that site — its comments, cookies and embeds — and not this plugin, so
   * linking it would answer a question nobody asked here. Add a link back only
   * when the plugin sends something this panel cannot account for.
   */
  public function display_privacy_fields()
  {
    ?>
<div class="space-y-4">
  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Privacy', 'enrove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600"><?php esc_html_e('What this plugin sends, and where it goes.', 'enrove-folios'); ?></p>
    </div>

    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3 space-y-2">
      <p class="m-0 text-sm font-medium text-gray-800">
        <?php esc_html_e('Enrove Folios collects nothing about you or your site.', 'enrove-folios'); ?>
      </p>
      <p class="m-0 text-sm text-gray-600">
        <?php esc_html_e('No analytics, no usage tracking, no telemetry — so there is nothing here to switch on or off. The plugin never reports back to StudioEN, and your folios, collection tags and settings stay in your own WordPress database.', 'enrove-folios'); ?>
      </p>
    </div>

    <div class="space-y-2">
      <h4 class="m-0 text-xs font-semibold text-gray-500 uppercase tracking-wide">
        <?php esc_html_e('Requests the plugin does make', 'enrove-folios'); ?>
      </h4>
      <ul class="m-0 pl-5 list-disc space-y-1 text-sm text-gray-600">
        <li>
          <?php esc_html_e('Google Fonts — only when an administrator chooses to download them, in the setup dialog or on the Fonts tab. The fonts are fetched once from fonts.googleapis.com and fonts.gstatic.com into your uploads folder, and Google sees your server’s IP address. Folios then load them from your own site, so readers never contact Google.', 'enrove-folios'); ?>
        </li>
        <li>
          <?php esc_html_e('Pexels images — only when an administrator chooses to download them, in the setup dialog or on the Imagery tab. The photos are fetched once from images.pexels.com into your uploads folder, and Pexels sees your server’s IP address. After that they are served from your own site.', 'enrove-folios'); ?>
        </li>
        <?php if ($this->pexels_curation_available()): ?>
        <li>
          <?php esc_html_e('Pexels API — only if you add your own API key on the Imagery tab, and only when you run the image curation script or press the connection test yourself.', 'enrove-folios'); ?>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </section>
</div>
<?php
  }

  public function display_tab_general()
  {
    ?>
<div>
  <?php $this->display_general_fields(); ?>
</div>
<?php
  }

  public function display_tab_routing()
  {
    ?>
<div>
  <?php $this->display_routing_fields(); ?>
</div>
<?php
  }

  public function display_tab_collections()
  {
    ?>
<div>
  <?php $this->display_collections_fields(); ?>
</div>
<?php
  }

  public function display_tab_imagery()
  {
    ?>
<div>
  <?php $this->display_imagery_fields(); ?>
</div>
<?php
  }

  public function display_tab_fonts()
  {
    ?>
<div>
  <?php $this->display_fonts_fields(); ?>
</div>
<?php
  }

  /**
   * Reset tab: what a reset does, and the button that asks first.
   */
  public function display_tab_reset()
  {
    $counts = \Enrove\Setup\Reset::content_counts();
    $has_content = $counts['folios'] > 0 || $counts['pages'] > 0 || $counts['tags'] > 0;
    $has_stored_key = get_option('enrove_pexels_api_key', '') !== '';
    ?>
<div class="space-y-4">
  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Reset Enrove Folios', 'enrove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600">
        <?php esc_html_e('Puts the plugin back the way it was when it was first installed. Useful for troubleshooting, or to go through the first-run setup again.', 'enrove-folios'); ?>
      </p>
    </div>

    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3 space-y-2">
      <p class="m-0 text-sm font-medium text-gray-800"><?php esc_html_e('A reset:', 'enrove-folios'); ?></p>
      <ul class="g-reset-list">
        <?php $this->display_reset_effects($has_stored_key); ?>
      </ul>
      <p class="m-0 text-sm text-gray-600">
        <?php
        if ($has_content) {
          echo esc_html($this->describe_reset_content($counts));
          echo ' ';
          esc_html_e('You choose whether to keep them before anything happens.', 'enrove-folios');
        } else {
          esc_html_e('There are no folios on this site, so no content is affected.', 'enrove-folios');
        }
        ?>
      </p>
    </div>

    <div>
      <button type="button" class="button button-secondary g-settings-remove__button" data-enrove-reset-open aria-haspopup="dialog">
        <?php esc_html_e('Reset Enrove Folios…', 'enrove-folios'); ?>
      </button>
    </div>
  </section>
</div>
<?php
    $this->display_reset_dialog($counts, $has_content, $has_stored_key);
  }

  /**
   * The list of what a reset clears, shared by the tab and its dialog so the
   * two cannot drift apart.
   *
   * @param bool $has_stored_key Whether a Pexels key is stored in the plugin's own setting.
   */
  private function display_reset_effects($has_stored_key)
  {
    ?>
        <li>
          <?php
          if ($has_stored_key) {
            esc_html_e('Returns every setting on these tabs to its default, including the stored Pexels API key.', 'enrove-folios');
          } else {
            esc_html_e('Returns every setting on these tabs to its default.', 'enrove-folios');
          }
          ?>
        </li>
        <li><?php esc_html_e('Deletes the downloaded fonts and photos from your uploads folder and forgets your choices about them.', 'enrove-folios'); ?></li>
        <li><?php esc_html_e('Shows the setup dialog to every administrator again.', 'enrove-folios'); ?></li>
<?php
  }

  /**
   * "3 folios, 12 pages and 4 collection tags are on this site."
   *
   * @param array{folios: int, pages: int, tags: int} $counts
   * @return string
   */
  private function describe_reset_content($counts)
  {
    return sprintf(
      /* translators: 1: number of folios, 2: number of folio pages, 3: number of collection tags. */
      __('This site has %1$s, %2$s and %3$s.', 'enrove-folios'),
      /* translators: %s: number of folios. */
      sprintf(_n('%s folio', '%s folios', $counts['folios'], 'enrove-folios'), number_format_i18n($counts['folios'])),
      /* translators: %s: number of folio pages. */
      sprintf(_n('%s page', '%s pages', $counts['pages'], 'enrove-folios'), number_format_i18n($counts['pages'])),
      /* translators: %s: number of collection tags. */
      sprintf(_n('%s collection tag', '%s collection tags', $counts['tags'], 'enrove-folios'), number_format_i18n($counts['tags']))
    );
  }

  /**
   * The confirmation. A reset cannot be undone, so it asks, names what goes,
   * and — when there are folios — makes keeping them the default answer.
   *
   * @param array{folios: int, pages: int, tags: int} $counts
   * @param bool $has_content
   * @param bool $has_stored_key
   */
  private function display_reset_dialog($counts, $has_content, $has_stored_key)
  {
    ?>
<div id="g-reset-modal" class="g-theme-details g-reset" role="dialog" aria-modal="true"
  aria-labelledby="g-reset-title" hidden>
  <div class="g-theme-details__backdrop" data-enrove-reset-close></div>
  <div class="g-theme-details__dialog g-reset__dialog" tabindex="-1">
    <form class="g-dialog-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" data-enrove-reset-form>
      <?php wp_nonce_field('enrove_reset_plugin', 'enrove_nonce'); ?>
      <input type="hidden" name="action" value="reset_enrove_folios" />
      <div class="g-theme-details__header">
        <h2 id="g-reset-title" class="g-theme-details__title"><?php esc_html_e('Reset Enrove Folios?', 'enrove-folios'); ?></h2>
        <button type="button" class="g-theme-details__close" data-enrove-reset-close
          aria-label="<?php esc_attr_e('Close', 'enrove-folios'); ?>">
          <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
        </button>
      </div>
      <div class="g-theme-details__body g-dialog-confirm g-reset__body">
        <p class="g-dialog-confirm__lead"><?php esc_html_e('The plugin goes back to the way it was when it was first installed. This:', 'enrove-folios'); ?></p>
        <ul class="g-dialog-confirm__list">
          <?php $this->display_reset_effects($has_stored_key); ?>
        </ul>

        <?php if ($has_content): ?>
        <fieldset class="g-reset__content">
          <legend class="g-reset__legend"><?php esc_html_e('Your folios', 'enrove-folios'); ?></legend>
          <p class="g-reset__desc"><?php echo esc_html($this->describe_reset_content($counts)); ?></p>
          <div class="g-choices">
            <label class="g-choice">
              <input type="radio" name="enrove_reset_content" value="keep" checked />
              <span class="g-choice__text">
                <span class="g-choice__label"><?php esc_html_e('Keep them', 'enrove-folios'); ?></span>
                <span class="g-choice__hint"><?php esc_html_e('Folios, pages and collection tags stay exactly as they are.', 'enrove-folios'); ?></span>
              </span>
            </label>
            <label class="g-choice g-choice--danger">
              <input type="radio" name="enrove_reset_content" value="delete" />
              <span class="g-choice__text">
                <span class="g-choice__label"><?php esc_html_e('Delete them too', 'enrove-folios'); ?></span>
                <span class="g-choice__hint"><?php esc_html_e('Permanently, trash included. Images in the Media Library stay.', 'enrove-folios'); ?></span>
              </span>
            </label>
          </div>
        </fieldset>
        <?php endif; ?>

        <div class="g-dialog-confirm__notice g-dialog-confirm__notice--danger" data-enrove-reset-warning<?php echo $has_content ? ' hidden' : ''; ?>>
          <p><?php echo $has_content ? esc_html__('The folios, their pages and collection tags are deleted for good. This can’t be undone.', 'enrove-folios') : esc_html__('The settings and downloads are gone for good. This can’t be undone.', 'enrove-folios'); ?></p>
        </div>
      </div>
      <div class="g-theme-details__footer">
        <button type="button" class="button button-secondary" data-enrove-reset-close><?php esc_html_e('Cancel', 'enrove-folios'); ?></button>
        <button type="submit" class="button g-dialog-confirm__destroy" data-enrove-reset-submit
          data-label-keep="<?php esc_attr_e('Reset Enrove Folios', 'enrove-folios'); ?>"
          data-label-delete="<?php esc_attr_e('Reset and delete folios', 'enrove-folios'); ?>"
          data-label-busy="<?php esc_attr_e('Resetting…', 'enrove-folios'); ?>"><?php esc_html_e('Reset Enrove Folios', 'enrove-folios'); ?></button>
      </div>
    </form>
  </div>
</div>
<?php
  }

  public function display_tab_privacy()
  {
    ?>
<div>
  <?php $this->display_privacy_fields(); ?>
</div>
<?php
  }

  /**
   * Why a collection tag would not save, in the terms the operator can act on.
   *
   * wp_insert_term() and wp_update_term() report a handful of distinct
   * failures under one generic message. Naming the actual one is the
   * difference between "try again" and knowing which field to change.
   *
   * @param string $error_code Error code carried back from the redirect.
   * @return array{0: string, 1: string} Message and next step.
   */
  private function get_collection_tag_error($error_code)
  {
    switch ($error_code) {
      case 'duplicate_term_slug':
        return array(
          __('Another collection tag already uses that slug.', 'enrove-folios'),
          __('Pick a different slug, or clear the field and one will be generated from the name.', 'enrove-folios'),
        );
      case 'term_exists':
        return array(
          __('A collection tag with that name already exists.', 'enrove-folios'),
          __('Edit the existing tag from the list instead, or choose a different name.', 'enrove-folios'),
        );
      case 'invalid_taxonomy':
        return array(
          __('The collection tag taxonomy is not registered.', 'enrove-folios'),
          __('Deactivate and reactivate Enrove Folios so the taxonomy is registered again, then retry.', 'enrove-folios'),
        );
    }

    return array(
      __('The collection tag could not be saved.', 'enrove-folios'),
      __('Reload this screen and try again. If it keeps failing, check the site error log for the database error behind it.', 'enrove-folios'),
    );
  }

  /**
   * Turn a `?message=` redirect key into a toast, and a failure into a
   * toggletip pinned to the button that produced it.
   *
   * A toast alone reports a failure and then takes the reason away with it. An
   * anchored toggletip leaves the reason — and the next step — at the control
   * the operator pressed, which is where they are already looking.
   *
   * The args are consumed once shown, so reloading the settings screen does
   * not replay an outcome from a save that already happened.
   *
   * @param string $message    Message key from the redirect.
   * @param string $error_code Error code from the redirect, where there is one.
   */
  private function toast_message($message, $error_code = '')
  {
    if ($message === '') {
      return;
    }

    $consumed = array('message', 'error_code');

    $success = array(
      'settings_saved' => __('Settings saved.', 'enrove-folios'),
      'collection_tag_created' => __('Collection tag created.', 'enrove-folios'),
      'collection_tag_updated' => __('Collection tag updated.', 'enrove-folios'),
      'collection_tag_deleted' => __('Collection tag deleted.', 'enrove-folios'),
    );

    if (isset($success[$message])) {
      \Enrove\Toast::success($success[$message], $consumed);
      return;
    }

    switch ($message) {
      case 'base_slug_empty':
        \Enrove\Toast::failure(
          __('A folio base slug is required.', 'enrove-folios'),
          __('Enter the word you want in folio URLs — "folio" gives /folio/my-folio. The previous slug is still in place.', 'enrove-folios'),
          '#enrove-save-routing',
          $consumed
        );
        return;

      case 'base_slug_invalid':
        \Enrove\Toast::failure(
          __('That base slug cannot be used in a URL.', 'enrove-folios'),
          __('Use letters, numbers and hyphens, such as "folio" or "case-studies". The previous slug is still in place.', 'enrove-folios'),
          '#enrove-save-routing',
          $consumed
        );
        return;

      case 'collection_tag_empty':
        \Enrove\Toast::failure(
          __('A collection tag needs a name.', 'enrove-folios'),
          __('Type a name in the Name field above. The slug can be left blank — it is generated from the name.', 'enrove-folios'),
          '#enrove-save-collection-tag',
          $consumed
        );
        return;

      case 'collection_tag_error':
        list($tag_message, $tag_hint) = $this->get_collection_tag_error($error_code);
        \Enrove\Toast::failure($tag_message, $tag_hint, '#enrove-save-collection-tag', $consumed);
        return;

      case 'collection_tag_delete_error':
        // No save button to point at — the delete button that failed belongs
        // to a row that may no longer be on the screen.
        \Enrove\Toast::error(
          'not_found' === $error_code
            ? __('That collection tag was already gone, so nothing was removed.', 'enrove-folios')
            : __('That collection tag could not be removed. Reload the screen and try again.', 'enrove-folios'),
          $consumed
        );
        return;

      case 'unknown_tab':
        \Enrove\Toast::failure(
          __('Nothing was saved — that settings tab was not recognised.', 'enrove-folios'),
          __('This usually means the page had been open long enough to go stale. Reload the settings screen and make the change again.', 'enrove-folios'),
          '#enrove-save-general',
          $consumed
        );
        return;
    }
  }

  public function display_content()
  {
    $tab_key = $this->current_tab();
    // Outcome codes from this screen's own signed redirect after a save.
    $message = Request::key('message');
    $error_code = Request::key('error_code');

    $this->toast_message($message, $error_code);
    ?>
<div class="space-y-4">
  <?php if ('collections' === $tab_key): ?>
    <?php $this->display_tab_collections(); ?>
  <?php elseif ('routing' === $tab_key): ?>
    <?php $this->display_tab_routing(); ?>
  <?php elseif ('imagery' === $tab_key): ?>
    <?php $this->display_tab_imagery(); ?>
  <?php elseif ('fonts' === $tab_key): ?>
    <?php $this->display_tab_fonts(); ?>
  <?php elseif ('privacy' === $tab_key): ?>
    <?php $this->display_tab_privacy(); ?>
  <?php elseif ('reset' === $tab_key && current_user_can('manage_options')): ?>
    <?php $this->display_tab_reset(); ?>
  <?php else: ?>
    <?php $this->display_tab_general(); ?>
  <?php endif; ?>
</div>
<?php
  }

  public function display_tabs()
  {
    $tabs = $this->get_tabs();
    $tab_key = $this->current_tab();
    ?>
<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e('Settings tabs', 'enrove-folios'); ?>">
  <?php
    foreach ($tabs as $tab_id => $tab) {
      $active_class = $tab_key === $tab_id ? ' nav-tab-active' : '';
      $tab_url = $this->get_settings_tab_url($tab_id);
      echo '<a href="' . esc_url($tab_url) . '" class="nav-tab' . esc_attr($active_class) . '">' . esc_html($tab['label']) . '</a>';
    }
    ?>
</nav>
<?php
  }
}
