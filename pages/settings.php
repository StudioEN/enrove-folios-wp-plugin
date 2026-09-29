<?php
namespace Groove\Pages;

use Groove\Menu\Menu_Manager;
use Groove\Menu\Settings_Menu_Item;
use Groove\Pages\Overview;
use Groove\Pages\Page;
use Groove\Themes\Themes_Manager;
use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

class Settings extends Page
{
  const PAGE_ID = 'groove-settings';

  public function get_title()
  {
    return esc_html__('Settings', 'groove-folios');
  }

  public function create_tabs()
  {
    return [
      'general' => [
        'label' => esc_html__('General', 'groove-folios'),
      ],
      'collections' => [
        'label' => esc_html__('Collection Tags', 'groove-folios'),
      ],
      'routing' => [
        'label' => esc_html__('Routing', 'groove-folios'),
      ],
      'imagery' => [
        'label' => esc_html__('Imagery', 'groove-folios'),
      ],
      'fonts' => [
        'label' => esc_html__('Fonts', 'groove-folios'),
      ],
      'privacy' => [
        'label' => esc_html__('Privacy', 'groove-folios'),
      ],
      'reset' => [
        'label' => esc_html__('Reset', 'groove-folios'),
      ],
    ];
  }

  public function __construct()
  {
    $this->add_post_action('save_groove_settings', 'handle_save');
    $this->add_post_action('save_groove_collection_tag', 'handle_collection_tag_save');
    $this->add_post_action('delete_groove_collection_tag', 'handle_collection_tag_delete');
    $this->add_post_action('save_groove_pexels_key', 'handle_pexels_key_save');
    $this->add_post_action('delete_groove_pexels_key', 'handle_pexels_key_delete');
    $this->add_post_action('test_groove_pexels_connection', 'handle_pexels_connection_test');
    $this->add_post_action('download_groove_sample_photos', 'handle_sample_photos_download');
    $this->add_post_action('download_groove_fonts', 'handle_fonts_download');
    $this->add_post_action('set_groove_font_source', 'handle_font_source');
    $this->add_post_action('remove_groove_fonts', 'handle_fonts_remove');
    $this->add_post_action('remove_groove_sample_photos', 'handle_sample_photos_remove');
    $this->add_post_action('reset_groove_folios', 'handle_plugin_reset');

    add_action('groove/menu/register', function (Menu_Manager $menu) {
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
    $title = get_option('groove_default_folio_title', '');

    return is_string($title) ? trim($title) : '';
  }

  private function get_default_folio_status()
  {
    $status = get_option('groove_default_folio_status', 'draft');
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

    $saved = (string) get_option('groove_default_theme_id', '');
    if ($saved !== '' && isset($themes[$saved])) {
      return $saved;
    }

    return (string) array_key_first($themes);
  }

  private function get_settings_tab_url($tab, $args = array())
  {
    return add_query_arg(
      array_merge(
        array(
          'page' => static::PAGE_ID,
          'tab_key' => $tab,
        ),
        $args
      ),
      admin_url('admin.php')
    );
  }

  private function can_manage_collection_tags()
  {
    return current_user_can('manage_options');
  }

  private function get_collection_tag_terms()
  {
    $terms = get_terms(array(
      'taxonomy' => 'groove_collection_tag',
      'hide_empty' => false,
      'orderby' => 'name',
      'order' => 'ASC',
    ));

    return is_wp_error($terms) ? array() : (array) $terms;
  }

  private function get_collection_tag_edit_term()
  {
    $term_id = isset($_GET['edit_collection_tag']) ? intval(wp_unslash($_GET['edit_collection_tag'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view parameter: which tag to open in the edit form; saving it is nonce-checked in handle_collection_tag_save().
    if ($term_id <= 0) {
      return null;
    }

    $term = get_term($term_id, 'groove_collection_tag');
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
   */
  private function pexels_is_available()
  {
    return class_exists('\\Groove\\Pexels\\Key') && class_exists('\\Groove\\Pexels\\Client');
  }

  private function get_pexels_key_source_label($source)
  {
    switch ($source) {
      case 'constant':
        return __('GROOVE_PEXELS_API_KEY in wp-config.php', 'groove-folios');
      case 'env':
        return __('PEXELS_API_KEY environment variable', 'groove-folios');
      case 'file':
        return __('.pexels-key file in the plugin folder', 'groove-folios');
      case 'option':
        return __('This settings field', 'groove-folios');
    }

    return __('Not configured', 'groove-folios');
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
    set_transient('groove_settings_notice_' . get_current_user_id(), array(
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
      \Groove\Toast::failure(
        $notice['text'],
        isset($notice['hint']) ? (string) $notice['hint'] : '',
        $notice_anchor,
        array(),
        $notice['status']
      );
    } else {
      \Groove\Toast::add($notice['text'], $notice['status']);
    }
  }

  private function take_tab_notice()
  {
    $key = 'groove_settings_notice_' . get_current_user_id();
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
    check_admin_referer('groove_save_settings', 'groove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'groove-folios'));
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
        update_option('groove_default_theme_id', $default_theme);
      }

      $default_status = isset($_POST['default_folio_status']) ? sanitize_key(wp_unslash($_POST['default_folio_status'])) : 'draft';
      if (!in_array($default_status, ['draft', 'publish'], true)) {
        $default_status = 'draft';
      }
      update_option('groove_default_folio_status', $default_status);

      // Blank is stored as blank: it is how the user asks for theme-derived
      // titles. Backfilling a literal here would pin every folio to one name.
      $default_title = isset($_POST['default_folio_title']) ? sanitize_text_field(wp_unslash($_POST['default_folio_title'])) : '';
      update_option('groove_default_folio_title', trim($default_title));

      delete_option('groove_default_allow_pdf_download');
    } elseif ('routing' === $tab) {
      $raw_slug = isset($_POST['folio_base_slug']) ? trim((string) wp_unslash($_POST['folio_base_slug'])) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only tested for emptiness here; sanitize_title() below sanitizes it before anything is stored, and the two checks give different messages.

      // Reported rather than corrected. Silently substituting "folio" would
      // send every published folio to a different URL than the operator asked
      // for, and the screen would show the substitution as if it were theirs.
      if ($raw_slug === '') {
        $this->redirect_to_settings_tab('routing', array('message' => 'base_slug_empty'));
      }

      $base_slug = sanitize_title($raw_slug);
      if ($base_slug === '') {
        $this->redirect_to_settings_tab('routing', array('message' => 'base_slug_invalid'));
      }

      update_option('groove_folio_base_slug', $base_slug);
    } else {
      // Only reachable from a stale or hand-edited form; nothing was written,
      // so say so rather than confirming a save that did not happen.
      $this->redirect_to_settings_tab('general', array('message' => 'unknown_tab'));
    }

    $this->redirect_to_settings_tab($tab, array('message' => 'settings_saved'));
  }

  public function handle_collection_tag_save()
  {
    check_admin_referer('groove_save_collection_tag', 'groove_nonce');

    if (!$this->can_manage_collection_tags()) {
      wp_die(esc_html__('You do not have permission to manage collection tags.', 'groove-folios'));
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
      $result = wp_update_term($term_id, 'groove_collection_tag', array_merge($term_args, array(
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

    $result = wp_insert_term($term_name, 'groove_collection_tag', $term_args);

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
    check_admin_referer('groove_delete_collection_tag', 'groove_nonce');

    if (!$this->can_manage_collection_tags()) {
      wp_die(esc_html__('You do not have permission to manage collection tags.', 'groove-folios'));
    }

    $term_id = isset($_POST['collection_tag_id']) ? intval(wp_unslash($_POST['collection_tag_id'])) : 0;
    if ($term_id <= 0) {
      $this->redirect_to_collections_tab(array('message' => 'collection_tag_error'));
    }

    $result = wp_delete_term($term_id, 'groove_collection_tag');
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
<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="g-settings-form space-y-4" data-groove-track-changes>
  <?php wp_nonce_field('groove_save_settings', 'groove_nonce'); ?>
  <input type="hidden" name="action" value="save_groove_settings" />
  <input type="hidden" name="tab_key" value="general" />

  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Folio Defaults', 'groove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600"><?php esc_html_e('These defaults are applied when you create a new folio.', 'groove-folios'); ?></p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div>
        <label for="groove-default-theme-id" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
          <?php esc_html_e('Default Theme', 'groove-folios'); ?>
        </label>
        <select id="groove-default-theme-id" name="default_theme_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
          <?php foreach ($themes as $theme_id => $theme): ?>
          <option value="<?php echo esc_attr($theme_id); ?>" <?php selected($default_theme_id, $theme_id); ?>
            data-default-title="<?php echo esc_attr(Themes_Manager::get_default_folio_title($theme_id)); ?>">
            <?php echo esc_html($theme['name']); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label for="groove-default-folio-status" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
          <?php esc_html_e('Default Publish Status', 'groove-folios'); ?>
        </label>
        <select id="groove-default-folio-status" name="default_folio_status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
          <option value="draft" <?php selected($default_status, 'draft'); ?>><?php esc_html_e('Draft', 'groove-folios'); ?></option>
          <option value="publish" <?php selected($default_status, 'publish'); ?>><?php esc_html_e('Published', 'groove-folios'); ?></option>
        </select>
      </div>
    </div>

    <div>
      <label for="groove-default-folio-title" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
        <?php esc_html_e('Default Folio Title', 'groove-folios'); ?>
      </label>
      <input
        id="groove-default-folio-title"
        type="text"
        name="default_folio_title"
        value="<?php echo esc_attr($default_title); ?>"
        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
        placeholder="<?php echo esc_attr($default_theme_title); ?>" />
      <p class="mt-2 mb-0 text-sm text-gray-600">
        <?php esc_html_e('Leave blank to name each folio after the theme it is created with. Anything you type here is used for every folio instead.', 'groove-folios'); ?>
      </p>
    </div>

    <?php /* The placeholder follows the Default Theme select; see "Default folio
             title placeholder" in assets/js/groove-main.js. */ ?>

    <div>
      <button
        type="submit"
        id="groove-save-general"
        class="button button-primary"
        data-groove-save
        data-groove-save-idle="<?php echo esc_attr(__('Nothing to save — these settings already match what is stored.', 'groove-folios')); ?>"><?php esc_html_e('Save Changes', 'groove-folios'); ?></button>
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
<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="g-settings-form space-y-4" data-groove-track-changes>
  <?php wp_nonce_field('groove_save_settings', 'groove_nonce'); ?>
  <input type="hidden" name="action" value="save_groove_settings" />
  <input type="hidden" name="tab_key" value="routing" />

  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Routing', 'groove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600"><?php esc_html_e('Customize the public URL base for folios.', 'groove-folios'); ?></p>
    </div>

    <div>
      <label for="groove-folio-base-slug" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
        <?php esc_html_e('Folio Base Slug', 'groove-folios'); ?>
      </label>
      <div class="mt-1 flex rounded-md shadow-sm">
        <span class="inline-flex items-center rounded-l-md border border-r-0 border-gray-300 bg-gray-50 px-3 text-gray-500 sm:text-sm">
          <?php echo esc_html(home_url('/')); ?>
        </span>
        <input
          id="groove-folio-base-slug"
          type="text"
          name="folio_base_slug"
          value="<?php echo esc_attr($base_slug); ?>"
          class="block w-full min-w-0 flex-1 rounded-none rounded-r-md border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
          placeholder="folio" />
      </div>
    </div>

    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3 text-sm text-gray-700">
      <p class="m-0"><?php esc_html_e('Example folio URL:', 'groove-folios'); ?> <code><?php echo esc_html($sample_folio); ?></code></p>
      <p class="mt-2 mb-0"><?php esc_html_e('Example page URL:', 'groove-folios'); ?> <code><?php echo esc_html($sample_page); ?></code></p>
    </div>

    <div>
      <button
        type="submit"
        id="groove-save-routing"
        class="button button-primary"
        data-groove-save
        data-groove-save-idle="<?php echo esc_attr(__('Nothing to save — these settings already match what is stored.', 'groove-folios')); ?>"><?php esc_html_e('Save Changes', 'groove-folios'); ?></button>
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
    $form_title = $is_editing ? __('Edit Collection Tag', 'groove-folios') : __('Add Collection Tag', 'groove-folios');
    $submit_label = $is_editing ? __('Update Tag', 'groove-folios') : __('Add Tag', 'groove-folios');
    $submit_idle = $is_editing
      ? __('Nothing to save — this tag already matches what is stored.', 'groove-folios')
      : __('Give the tag a name first — the slug is optional.', 'groove-folios');
    ?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4 xl:col-span-1">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php echo esc_html($form_title); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600"><?php esc_html_e('Manage the tags used to group folios into collections.', 'groove-folios'); ?></p>
    </div>

    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="space-y-4" data-groove-track-changes>
      <?php wp_nonce_field('groove_save_collection_tag', 'groove_nonce'); ?>
      <input type="hidden" name="action" value="save_groove_collection_tag" />
      <input type="hidden" name="collection_tag_id" value="<?php echo esc_attr($is_editing ? (string) $edit_term->term_id : '0'); ?>" />

      <div>
        <label for="groove-collection-tag-name" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
          <?php esc_html_e('Name', 'groove-folios'); ?>
        </label>
        <input
          id="groove-collection-tag-name"
          type="text"
          name="collection_tag_name"
          value="<?php echo esc_attr($is_editing ? $edit_term->name : ''); ?>"
          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
          placeholder="<?php esc_attr_e('Magazine', 'groove-folios'); ?>" />
      </div>

      <div>
        <label for="groove-collection-tag-slug" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
          <?php esc_html_e('Slug', 'groove-folios'); ?>
        </label>
        <input
          id="groove-collection-tag-slug"
          type="text"
          name="collection_tag_slug"
          value="<?php echo esc_attr($is_editing ? $edit_term->slug : ''); ?>"
          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
          placeholder="<?php esc_attr_e('magazine', 'groove-folios'); ?>" />
        <p class="mt-1 mb-0 text-xs text-gray-400"><?php esc_html_e('Optional. Leave blank to generate from the name.', 'groove-folios'); ?></p>
      </div>

      <div class="flex items-center gap-2">
        <button
          type="submit"
          id="groove-save-collection-tag"
          class="button button-primary"
          data-groove-save
          data-groove-save-idle="<?php echo esc_attr($submit_idle); ?>"><?php echo esc_html($submit_label); ?></button>
        <?php if ($is_editing): ?>
          <a href="<?php echo esc_url($this->get_settings_tab_url('collections')); ?>" class="button button-secondary"><?php esc_html_e('Cancel', 'groove-folios'); ?></a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 xl:col-span-2">
    <div class="mb-4">
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Existing Collection Tags', 'groove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600"><?php esc_html_e('These tags can be assigned on folio setup screens and used to filter All Folios.', 'groove-folios'); ?></p>
    </div>

    <?php if (empty($terms)): ?>
      <p class="m-0 text-sm text-gray-600"><?php esc_html_e('No collection tags yet.', 'groove-folios'); ?></p>
    <?php else: ?>
      <table class="widefat striped">
        <thead>
          <tr>
            <th><?php esc_html_e('Name', 'groove-folios'); ?></th>
            <th><?php esc_html_e('Slug', 'groove-folios'); ?></th>
            <th><?php esc_html_e('Folios', 'groove-folios'); ?></th>
            <th><?php esc_html_e('Actions', 'groove-folios'); ?></th>
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
                    <?php esc_html_e('Edit', 'groove-folios'); ?>
                  </a>
                  <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="inline-block ml-3" onsubmit="return window.confirm('<?php echo esc_js(__('Delete this collection tag?', 'groove-folios')); ?>');">
                    <?php wp_nonce_field('groove_delete_collection_tag', 'groove_nonce'); ?>
                    <input type="hidden" name="action" value="delete_groove_collection_tag" />
                    <input type="hidden" name="collection_tag_id" value="<?php echo esc_attr((string) $term->term_id); ?>" />
                    <button type="submit" class="button-link delete"><?php esc_html_e('Delete', 'groove-folios'); ?></button>
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
    check_admin_referer('groove_save_pexels_key', 'groove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'groove-folios'));
    }

    if ($this->pexels_is_available() && \Groove\Pexels\Key::is_locked_by_constant()) {
      $this->set_tab_notice(
        'warning',
        __('GROOVE_PEXELS_API_KEY is defined in wp-config.php, so the stored key was not changed.', 'groove-folios'),
        __('The constant wins over anything saved here. Remove it from wp-config.php if you want to manage the key from this screen.', 'groove-folios'),
        '#groove-save-pexels-key'
      );
      $this->redirect_to_imagery_tab();
    }

    $submitted = isset($_POST['pexels_api_key']) ? sanitize_text_field(wp_unslash($_POST['pexels_api_key'])) : '';

    if ($submitted === '') {
      $this->set_tab_notice('info', __('No key entered — the stored key was left unchanged.', 'groove-folios'));
      $this->redirect_to_imagery_tab();
    }

    // update_option() cannot change the autoload flag of an existing option,
    // so delete first and re-add with autoload explicitly off.
    delete_option('groove_pexels_api_key');
    add_option('groove_pexels_api_key', $submitted, '', 'no');

    $this->set_tab_notice('success', __('Pexels API key saved.', 'groove-folios'));
    $this->redirect_to_imagery_tab();
  }

  /**
   * Clear the stored Pexels API key.
   */
  public function handle_pexels_key_delete()
  {
    check_admin_referer('groove_delete_pexels_key', 'groove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'groove-folios'));
    }

    delete_option('groove_pexels_api_key');

    $this->set_tab_notice('success', __('Stored Pexels API key removed.', 'groove-folios'));
    $this->redirect_to_imagery_tab();
  }

  /**
   * Probe the Pexels API. Only ever runs from an explicit button press —
   * never on a page load.
   */
  public function handle_pexels_connection_test()
  {
    check_admin_referer('groove_test_pexels_connection', 'groove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'groove-folios'));
    }

    if (!$this->pexels_is_available()) {
      $this->set_tab_notice(
        'error',
        __('The Pexels client is unavailable.', 'groove-folios'),
        __('The pexels/ helpers are missing from this copy of the plugin. Reinstall or update Groove Folios to restore them.', 'groove-folios'),
        '#groove-test-pexels-connection'
      );
      $this->redirect_to_imagery_tab();
    }

    $client = new \Groove\Pexels\Client();

    if (!$client->has_key()) {
      $this->set_tab_notice(
        'error',
        __('No Pexels API key is configured.', 'groove-folios'),
        __('Paste a key into the field above and save it, then test the connection again.', 'groove-folios'),
        '#groove-test-pexels-connection'
      );
      $this->redirect_to_imagery_tab();
    }

    $result = $client->verify();

    if (is_wp_error($result)) {
      $this->set_tab_notice(
        'error',
        sprintf(
          /* translators: %s: error message returned by the Pexels API. */
          __('Pexels rejected the request: %s', 'groove-folios'),
          $result->get_error_message()
        ),
        __('Check the key is still active in your Pexels account and paste it again. A key created moments ago can take a few minutes to work.', 'groove-folios'),
        '#groove-test-pexels-connection'
      );
      $this->redirect_to_imagery_tab();
    }

    $remaining = is_array($result) && isset($result['remaining']) ? (int) $result['remaining'] : 0;
    $limit = is_array($result) && isset($result['limit']) ? (int) $result['limit'] : 0;

    $this->set_tab_notice('success', sprintf(
      /* translators: 1: remaining requests, 2: hourly request limit. */
      __('Connected to Pexels. %1$s of %2$s requests remaining this hour.', 'groove-folios'),
      number_format_i18n($remaining),
      number_format_i18n($limit)
    ));
    $this->redirect_to_imagery_tab();
  }

  /**
   * Download the sample photos this site does not have yet. Only ever runs from
   * an explicit button press; see \Groove\Pexels\Library for why the plugin
   * does not simply carry them.
   */
  public function handle_sample_photos_download()
  {
    check_admin_referer('groove_download_sample_photos', 'groove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'groove-folios'));
    }

    \Groove\Pexels\Library::set_source(\Groove\Pexels\Library::SOURCE_PEXELS);
    $result = \Groove\Pexels\Library::download_missing();
    $failed = count($result['failed']);

    if ($failed === 0 && $result['remaining'] === 0) {
      $this->set_tab_notice('success', sprintf(
        /* translators: %s: number of photos downloaded. */
        _n('Downloaded %s photo.', 'Downloaded %s photos.', $result['downloaded'], 'groove-folios'),
        number_format_i18n($result['downloaded'])
      ));
      $this->redirect_to_imagery_tab();
    }

    if ($failed === 0) {
      $this->set_tab_notice(
        'warning',
        sprintf(
          /* translators: 1: photos downloaded, 2: photos still to fetch. */
          _n('Downloaded %1$s photos; %2$s still to fetch.', 'Downloaded %1$s photos; %2$s still to fetch.', $result['remaining'], 'groove-folios'),
          number_format_i18n($result['downloaded']),
          number_format_i18n($result['remaining'])
        ),
        __('This server took a while, so the download stopped before it could time out. Press Download Photos again to fetch the rest.', 'groove-folios'),
        '#groove-download-sample-photos'
      );
      $this->redirect_to_imagery_tab();
    }

    $this->set_tab_notice(
      'error',
      sprintf(
        /* translators: 1: photos downloaded, 2: photos that failed. */
        __('Downloaded %1$s photos; %2$s could not be fetched.', 'groove-folios'),
        number_format_i18n($result['downloaded']),
        number_format_i18n($failed)
      ),
      sprintf(
        /* translators: %s: the first error message. */
        __('First error: %s Press Download again to retry the ones that are missing.', 'groove-folios'),
        (string) reset($result['failed'])
      ),
      '#groove-download-sample-photos'
    );
    $this->redirect_to_imagery_tab();
  }

  /**
   * Download the folio fonts this site does not have yet. Only ever runs from
   * an explicit button press; see \Groove\Themes\Font_Library for why folios
   * do not load them from Google.
   */
  public function handle_fonts_download()
  {
    check_admin_referer('groove_download_fonts', 'groove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'groove-folios'));
    }

    // Downloading is choosing Google's fonts, including over an earlier
    // choice of system fonts.
    \Groove\Themes\Font_Library::set_source(\Groove\Themes\Font_Library::SOURCE_GOOGLE);
    $result = \Groove\Themes\Font_Library::download_missing();
    $failed = count($result['failed']);

    if ($failed === 0 && $result['remaining'] === 0) {
      $this->set_tab_notice('success', sprintf(
        /* translators: %s: number of font families downloaded. */
        _n('Downloaded %s font family.', 'Downloaded %s font families.', $result['downloaded'], 'groove-folios'),
        number_format_i18n($result['downloaded'])
      ));
      $this->redirect_to_fonts_tab();
    }

    if ($failed === 0) {
      $this->set_tab_notice(
        'warning',
        sprintf(
          /* translators: 1: font families downloaded, 2: font families still to fetch. */
          __('Downloaded %1$s font families; %2$s still to fetch.', 'groove-folios'),
          number_format_i18n($result['downloaded']),
          number_format_i18n($result['remaining'])
        ),
        __('This server took a while, so the download stopped before it could time out. Press Download Fonts again to fetch the rest.', 'groove-folios'),
        '#groove-download-fonts'
      );
      $this->redirect_to_fonts_tab();
    }

    $this->set_tab_notice(
      'error',
      sprintf(
        /* translators: 1: font families downloaded, 2: font families that failed. */
        __('Downloaded %1$s font families; %2$s could not be fetched.', 'groove-folios'),
        number_format_i18n($result['downloaded']),
        number_format_i18n($failed)
      ),
      sprintf(
        /* translators: %s: the first error message. */
        __('First error: %s Press Download Fonts again to retry the ones that are missing.', 'groove-folios'),
        (string) reset($result['failed'])
      ),
      '#groove-download-fonts'
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
    check_admin_referer('groove_set_font_source', 'groove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'groove-folios'));
    }

    $source = isset($_POST['font_source']) ? sanitize_key(wp_unslash($_POST['font_source'])) : '';
    if (!in_array($source, array(\Groove\Themes\Font_Library::SOURCE_GOOGLE, \Groove\Themes\Font_Library::SOURCE_SYSTEM), true)) {
      wp_die(esc_html__('Unknown font source.', 'groove-folios'));
    }
    \Groove\Themes\Font_Library::set_source($source);

    $this->set_tab_notice('success', $source === \Groove\Themes\Font_Library::SOURCE_SYSTEM
      ? __('Folios now use system fonts.', 'groove-folios')
      : __('Folios now use their theme fonts.', 'groove-folios'));
    $this->redirect_to_fonts_tab();
  }

  /**
   * Delete the downloaded fonts and forget the font choice. Cheap to undo — a
   * press of Download Fonts brings them back — so there is no confirmation
   * step; the button's own note says what happens.
   */
  public function handle_fonts_remove()
  {
    check_admin_referer('groove_remove_fonts', 'groove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'groove-folios'));
    }

    $removed = \Groove\Themes\Font_Library::remove_downloaded();

    $this->set_tab_notice('success', sprintf(
      /* translators: %s: number of font families removed. */
      _n('Removed %s font family. Folios use system fonts until you download them again.', 'Removed %s font families. Folios use system fonts until you download them again.', $removed, 'groove-folios'),
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
    check_admin_referer('groove_remove_sample_photos', 'groove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'groove-folios'));
    }

    $removed = \Groove\Pexels\Library::remove_downloaded();

    $this->set_tab_notice('success', sprintf(
      /* translators: %s: number of photos removed. */
      _n('Removed %s photo.', 'Removed %s photos.', $removed, 'groove-folios'),
      number_format_i18n($removed)
    ));
    $this->redirect_to_imagery_tab();
  }

  /**
   * Reset the plugin (\Groove\Setup\Reset), keeping the folios unless the
   * confirmation dialog's answer was to delete them. Lands on Overview, where a
   * fresh install starts, so the setup dialog greets the administrator again.
   */
  public function handle_plugin_reset()
  {
    check_admin_referer('groove_reset_plugin', 'groove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'groove-folios'));
    }

    // Anything but an explicit "delete" keeps the content.
    $content = isset($_POST['groove_reset_content']) ? sanitize_key(wp_unslash($_POST['groove_reset_content'])) : 'keep';
    $keep = $content !== 'delete';

    // With nothing to keep or delete, the outcome says neither.
    $had_content = array_sum(\Groove\Setup\Reset::content_counts()) > 0;
    $deleted = \Groove\Setup\Reset::run($keep);

    $outcome = 'none';
    if ($had_content) {
      $outcome = $keep ? 'kept' : 'deleted';
    }

    wp_safe_redirect(add_query_arg(
      array(
        'page' => Overview::PAGE_ID,
        'groove_reset' => $outcome,
        'groove_reset_folios' => $deleted['folios'],
        'groove_reset_pages' => $deleted['pages'],
        'groove_reset_tags' => $deleted['tags'],
      ),
      admin_url('admin.php')
    ));
    exit;
  }

  public function display_fonts_fields()
  {
    $fonts = \Groove\Themes\Font_Library::status();
    $system = \Groove\Themes\Font_Library::source() === \Groove\Themes\Font_Library::SOURCE_SYSTEM;
    $this->toast_tab_notice($this->take_tab_notice());
    ?>
<div class="space-y-4">
  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Folio Fonts', 'groove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600">
        <?php esc_html_e('Folio themes are typeset in open-licence fonts from Google Fonts. Folios load them from your own site, never from Google, so your readers’ browsers do not contact Google. Download them once here, or use system fonts instead; until the fonts are here, folios use system fonts.', 'groove-folios'); ?>
      </p>
    </div>

    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3 text-sm text-gray-700">
      <p class="m-0">
        <?php
        if ($system) {
          esc_html_e('Folios use system fonts — the ones the WordPress dashboard uses.', 'groove-folios');
          echo ' ';
        }
        printf(
          /* translators: 1: font families on this site, 2: font families in total. */
          esc_html__('%1$s of %2$s font families are on this site.', 'groove-folios'),
          esc_html(number_format_i18n($fonts['present'])),
          esc_html(number_format_i18n($fonts['total']))
        );
        ?>
      </p>
    </div>

    <div class="g-settings-actions">
      <?php if (!empty($fonts['missing'])): ?>
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <?php wp_nonce_field('groove_download_fonts', 'groove_nonce'); ?>
        <input type="hidden" name="action" value="download_groove_fonts" />
        <button type="submit" id="groove-download-fonts" class="button button-primary">
          <?php esc_html_e('Download Fonts', 'groove-folios'); ?>
        </button>
      </form>
      <?php elseif ($system): ?>
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <?php wp_nonce_field('groove_set_font_source', 'groove_nonce'); ?>
        <input type="hidden" name="action" value="set_groove_font_source" />
        <input type="hidden" name="font_source" value="<?php echo esc_attr(\Groove\Themes\Font_Library::SOURCE_GOOGLE); ?>" />
        <button type="submit" class="button button-primary">
          <?php esc_html_e('Use Theme Fonts', 'groove-folios'); ?>
        </button>
      </form>
      <?php endif; ?>

      <?php if (!$system): ?>
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <?php wp_nonce_field('groove_set_font_source', 'groove_nonce'); ?>
        <input type="hidden" name="action" value="set_groove_font_source" />
        <input type="hidden" name="font_source" value="<?php echo esc_attr(\Groove\Themes\Font_Library::SOURCE_SYSTEM); ?>" />
        <button type="submit" class="button button-secondary">
          <?php esc_html_e('Use System Fonts', 'groove-folios'); ?>
        </button>
      </form>
      <?php endif; ?>
    </div>

    <?php if (!empty($fonts['missing'])): ?>
    <p class="m-0 text-xs text-gray-500">
      <?php esc_html_e('Downloads about 4 MB from fonts.googleapis.com and fonts.gstatic.com into your uploads folder. Google sees your server’s IP address for those requests, never a reader’s, and nothing is fetched until you press the button.', 'groove-folios'); ?>
    </p>
    <?php endif; ?>

    <?php if ($fonts['present'] > 0): ?>
    <div class="g-settings-remove">
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <?php wp_nonce_field('groove_remove_fonts', 'groove_nonce'); ?>
        <input type="hidden" name="action" value="remove_groove_fonts" />
        <button type="submit" class="button button-secondary g-settings-remove__button">
          <?php esc_html_e('Remove Downloaded Fonts', 'groove-folios'); ?>
        </button>
      </form>
      <p class="g-settings-remove__note">
        <?php esc_html_e('Deletes the font files from your uploads folder and forgets your font choice, so Groove Folios asks again. Folios use system fonts until the fonts are downloaded again.', 'groove-folios'); ?>
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
    $locked = $available ? \Groove\Pexels\Key::is_locked_by_constant() : false;
    $source = $available ? \Groove\Pexels\Key::source() : '';
    $masked = $available ? \Groove\Pexels\Key::masked() : '';
    $has_key = $source !== '';
    $has_option_key = get_option('groove_pexels_api_key', '') !== '';
    $notice = $this->take_tab_notice();

    // The API key and the curation script only matter in a development checkout.
    // The WordPress.org package leaves bin/curate-pexels.php out, and without it
    // the key has nothing to do, so neither section is shown.
    $can_curate = is_readable(GROOVE_PATH . 'bin/curate-pexels.php');
    $photos = $available ? \Groove\Pexels\Library::status() : array('total' => 0, 'present' => 0, 'missing' => array());
    $photos_downloaded = $available ? \Groove\Pexels\Library::downloaded_count() : 0;

    $credits = array();
    $credits_exist = false;
    if (class_exists('\Groove\Pexels\Credits')) {
      $credits_exist = file_exists(\Groove\Pexels\Credits::path());
      $credits = \Groove\Pexels\Credits::all();
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
    <p><?php esc_html_e('The Pexels helper classes are not installed in this copy of the plugin.', 'groove-folios'); ?></p>
  </div>
  <?php endif; ?>

  <?php if ($photos['total'] > 0): ?>
  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Sample Photos', 'groove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600">
        <?php esc_html_e('Theme covers and the photos in sample content come from Pexels. The Pexels licence does not allow them to be packaged with the plugin, so this site fetches its own copy when you ask.', 'groove-folios'); ?>
      </p>
    </div>

    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3 text-sm text-gray-700">
      <p class="m-0">
        <?php
        printf(
          /* translators: 1: photos on this site, 2: photos in total. */
          esc_html__('%1$s of %2$s photos are on this site.', 'groove-folios'),
          esc_html(number_format_i18n($photos['present'])),
          esc_html(number_format_i18n($photos['total']))
        );
        // A development checkout carries the photos in the plugin, so there
        // is nothing downloaded to remove — say so, or the missing Remove
        // button reads as a fault.
        if ($photos['present'] > 0 && $photos_downloaded === 0) {
          echo ' ';
          esc_html_e('They come with this copy of the plugin, so there is nothing to download or remove.', 'groove-folios');
        }
        ?>
      </p>
    </div>

    <?php if (!empty($photos['missing'])): ?>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
      <?php wp_nonce_field('groove_download_sample_photos', 'groove_nonce'); ?>
      <input type="hidden" name="action" value="download_groove_sample_photos" />
      <button type="submit" id="groove-download-sample-photos" class="button button-primary">
        <?php esc_html_e('Download Photos', 'groove-folios'); ?>
      </button>
    </form>
    <?php endif; ?>

    <?php if (!empty($photos['missing'])): ?>
    <p class="m-0 text-xs text-gray-500">
      <?php esc_html_e('Downloads about 4 MB from images.pexels.com into your uploads folder. Nothing is sent to Pexels beyond the requests for the photos, and nothing is fetched until you press the button. Folios seeded before the download pick the photos up once they are here.', 'groove-folios'); ?>
    </p>
    <?php endif; ?>

    <?php if ($photos_downloaded > 0): ?>
    <div class="g-settings-remove">
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <?php wp_nonce_field('groove_remove_sample_photos', 'groove_nonce'); ?>
        <input type="hidden" name="action" value="remove_groove_sample_photos" />
        <button type="submit" class="button button-secondary g-settings-remove__button">
          <?php esc_html_e('Remove Downloaded Photos', 'groove-folios'); ?>
        </button>
      </form>
      <p class="g-settings-remove__note">
        <?php esc_html_e('Deletes the downloaded photos from your uploads folder and forgets your photo choice, so Groove Folios asks again. Covers show gradients, and pictures inside sample folios go missing, until the photos are downloaded again. Featured images already in the Media Library stay.', 'groove-folios'); ?>
      </p>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($can_curate): ?>
  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Pexels API Key', 'groove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600">
        <?php
        printf(
          /* translators: %s: the curation command. */
          esc_html__('Used only by the imagery curation script in a development checkout (%s), which picks the covers and sample photos and records where each can be downloaded from. Folios never call the Pexels API when they are viewed or edited.', 'groove-folios'),
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
          esc_html__('%s is defined in wp-config.php and takes precedence. The field below is disabled.', 'groove-folios'),
          '<code>GROOVE_PEXELS_API_KEY</code>'
        );
        ?>
      </p>
    </div>
    <?php endif; ?>

    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="space-y-4" autocomplete="off" data-groove-track-changes>
      <?php wp_nonce_field('groove_save_pexels_key', 'groove_nonce'); ?>
      <input type="hidden" name="action" value="save_groove_pexels_key" />

      <div>
        <label for="groove-pexels-api-key" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
          <?php esc_html_e('API Key', 'groove-folios'); ?>
        </label>
        <input
          id="groove-pexels-api-key"
          type="password"
          name="pexels_api_key"
          value=""
          autocomplete="new-password"
          spellcheck="false"
          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:text-gray-400"
          placeholder="<?php echo esc_attr($masked !== '' ? $masked : __('Paste your Pexels API key', 'groove-folios')); ?>"
          <?php disabled($locked, true); ?> />
        <p class="mt-1 mb-0 text-xs text-gray-400">
          <?php esc_html_e('The stored key is never displayed. Leave this empty to keep the current key.', 'groove-folios'); ?>
        </p>
      </div>

      <div>
        <button
          type="submit"
          id="groove-save-pexels-key"
          class="button button-primary"
          data-groove-save
          data-groove-save-idle="<?php echo esc_attr__('Paste a key into the field above to save it.', 'groove-folios'); ?>"
          <?php disabled($locked, true); ?>><?php esc_html_e('Save Key', 'groove-folios'); ?></button>
      </div>
    </form>

    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3 text-sm text-gray-700 space-y-1">
      <p class="m-0">
        <strong><?php esc_html_e('Key source:', 'groove-folios'); ?></strong>
        <?php echo esc_html($this->get_pexels_key_source_label($source)); ?>
        <?php if ($masked !== ''): ?>
        <code><?php echo esc_html($masked); ?></code>
        <?php endif; ?>
      </p>
      <p class="m-0 text-xs text-gray-500">
        <?php esc_html_e('Checked in order: wp-config.php constant, PEXELS_API_KEY environment variable, .pexels-key file, then this setting.', 'groove-folios'); ?>
      </p>
    </div>

    <div class="flex items-center gap-2">
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <?php wp_nonce_field('groove_test_pexels_connection', 'groove_nonce'); ?>
        <input type="hidden" name="action" value="test_groove_pexels_connection" />
        <button type="submit" id="groove-test-pexels-connection" class="button button-secondary" <?php disabled(!$available || !$has_key, true); ?>>
          <?php esc_html_e('Test Connection', 'groove-folios'); ?>
        </button>
      </form>

      <?php if ($has_option_key): ?>
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" onsubmit="return window.confirm('<?php echo esc_js(__('Remove the stored Pexels API key?', 'groove-folios')); ?>');">
        <?php wp_nonce_field('groove_delete_pexels_key', 'groove_nonce'); ?>
        <input type="hidden" name="action" value="delete_groove_pexels_key" />
        <button type="submit" class="button-link delete"><?php esc_html_e('Remove Key', 'groove-folios'); ?></button>
      </form>
      <?php endif; ?>
    </div>

    <p class="m-0 text-xs text-gray-500">
      <?php esc_html_e('Testing the connection is the only action on this screen that contacts Pexels, and it only happens when you press the button.', 'groove-folios'); ?>
    </p>
  </section>
  <?php endif; ?>

  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Image Credits', 'groove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600">
        <?php esc_html_e('The Pexels licence requires a visible link to Pexels and credit to each photographer.', 'groove-folios'); ?>
      </p>
    </div>

    <p class="m-0 text-sm">
      <a href="https://www.pexels.com" target="_blank" rel="noopener noreferrer" class="font-semibold text-indigo-600 hover:text-indigo-500">
        <?php esc_html_e('Photos provided by Pexels', 'groove-folios'); ?>
      </a>
    </p>

    <?php if (empty($credits)): ?>
    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3 text-sm text-gray-700">
      <p class="m-0"><?php esc_html_e('No imagery has been curated yet, so there is nothing to credit.', 'groove-folios'); ?></p>
      <?php if ($can_curate): ?>
      <p class="mt-2 mb-0">
        <?php esc_html_e('Run the curation script to download imagery and build the credits file:', 'groove-folios'); ?>
        <code>php bin/curate-pexels.php</code>
      </p>
      <?php endif; ?>
      <?php if ($credits_exist): ?>
      <p class="mt-2 mb-0 text-xs text-gray-500"><?php esc_html_e('A credits file exists but contains no entries.', 'groove-folios'); ?></p>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <table class="widefat striped">
      <thead>
        <tr>
          <th><?php esc_html_e('Slot', 'groove-folios'); ?></th>
          <th><?php esc_html_e('Photographer', 'groove-folios'); ?></th>
          <th><?php esc_html_e('Photo', 'groove-folios'); ?></th>
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
                  <?php echo esc_html($photo_id !== '' ? '#' . $photo_id : __('View on Pexels', 'groove-folios')); ?>
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
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Privacy', 'groove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600"><?php esc_html_e('What this plugin sends, and where it goes.', 'groove-folios'); ?></p>
    </div>

    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3 space-y-2">
      <p class="m-0 text-sm font-medium text-gray-800">
        <?php esc_html_e('Groove Folios collects nothing about you or your site.', 'groove-folios'); ?>
      </p>
      <p class="m-0 text-sm text-gray-600">
        <?php esc_html_e('No analytics, no usage tracking, no telemetry — so there is nothing here to switch on or off. The plugin never reports back to StudioEN, and your folios, collection tags and settings stay in your own WordPress database.', 'groove-folios'); ?>
      </p>
    </div>

    <div class="space-y-2">
      <h4 class="m-0 text-xs font-semibold text-gray-500 uppercase tracking-wide">
        <?php esc_html_e('Requests the plugin does make', 'groove-folios'); ?>
      </h4>
      <ul class="m-0 pl-5 list-disc space-y-1 text-sm text-gray-600">
        <li>
          <?php esc_html_e('Google Fonts — only when an administrator chooses to download them, in the setup dialog or on the Fonts tab. The fonts are fetched once from fonts.googleapis.com and fonts.gstatic.com into your uploads folder, and Google sees your server’s IP address. Folios then load them from your own site, so readers never contact Google.', 'groove-folios'); ?>
        </li>
        <li>
          <?php esc_html_e('Pexels images — only when an administrator chooses to download them, in the setup dialog or on the Imagery tab. The photos are fetched once from images.pexels.com into your uploads folder, and Pexels sees your server’s IP address. After that they are served from your own site.', 'groove-folios'); ?>
        </li>
        <?php if (is_readable(GROOVE_PATH . 'bin/curate-pexels.php')): ?>
        <li>
          <?php esc_html_e('Pexels API — only if you add your own API key on the Imagery tab, and only when you run the image curation script or press the connection test yourself.', 'groove-folios'); ?>
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
    $counts = \Groove\Setup\Reset::content_counts();
    $has_content = $counts['folios'] > 0 || $counts['pages'] > 0 || $counts['tags'] > 0;
    $has_stored_key = get_option('groove_pexels_api_key', '') !== '';
    ?>
<div class="space-y-4">
  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Reset Groove Folios', 'groove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600">
        <?php esc_html_e('Puts the plugin back the way it was when it was first installed. Useful for troubleshooting, or to go through the first-run setup again.', 'groove-folios'); ?>
      </p>
    </div>

    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3 space-y-2">
      <p class="m-0 text-sm font-medium text-gray-800"><?php esc_html_e('A reset:', 'groove-folios'); ?></p>
      <ul class="g-reset-list">
        <?php $this->display_reset_effects($has_stored_key); ?>
      </ul>
      <p class="m-0 text-sm text-gray-600">
        <?php
        if ($has_content) {
          echo esc_html($this->describe_reset_content($counts));
          echo ' ';
          esc_html_e('You choose whether to keep them before anything happens.', 'groove-folios');
        } else {
          esc_html_e('There are no folios on this site, so no content is affected.', 'groove-folios');
        }
        ?>
      </p>
    </div>

    <div>
      <button type="button" class="button button-secondary g-settings-remove__button" data-groove-reset-open aria-haspopup="dialog">
        <?php esc_html_e('Reset Groove Folios…', 'groove-folios'); ?>
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
            esc_html_e('Returns every setting on these tabs to its default, including the stored Pexels API key.', 'groove-folios');
          } else {
            esc_html_e('Returns every setting on these tabs to its default.', 'groove-folios');
          }
          ?>
        </li>
        <li><?php esc_html_e('Deletes the downloaded fonts and photos from your uploads folder and forgets your choices about them.', 'groove-folios'); ?></li>
        <li><?php esc_html_e('Shows the setup dialog to every administrator again.', 'groove-folios'); ?></li>
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
      __('This site has %1$s, %2$s and %3$s.', 'groove-folios'),
      /* translators: %s: number of folios. */
      sprintf(_n('%s folio', '%s folios', $counts['folios'], 'groove-folios'), number_format_i18n($counts['folios'])),
      /* translators: %s: number of folio pages. */
      sprintf(_n('%s page', '%s pages', $counts['pages'], 'groove-folios'), number_format_i18n($counts['pages'])),
      /* translators: %s: number of collection tags. */
      sprintf(_n('%s collection tag', '%s collection tags', $counts['tags'], 'groove-folios'), number_format_i18n($counts['tags']))
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
  <div class="g-theme-details__backdrop" data-groove-reset-close></div>
  <div class="g-theme-details__dialog g-reset__dialog" tabindex="-1">
    <form class="g-dialog-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" data-groove-reset-form>
      <?php wp_nonce_field('groove_reset_plugin', 'groove_nonce'); ?>
      <input type="hidden" name="action" value="reset_groove_folios" />
      <div class="g-theme-details__header">
        <h2 id="g-reset-title" class="g-theme-details__title"><?php esc_html_e('Reset Groove Folios?', 'groove-folios'); ?></h2>
        <button type="button" class="g-theme-details__close" data-groove-reset-close
          aria-label="<?php esc_attr_e('Close', 'groove-folios'); ?>">
          <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
        </button>
      </div>
      <div class="g-theme-details__body g-dialog-confirm g-reset__body">
        <p class="g-dialog-confirm__lead"><?php esc_html_e('The plugin goes back to the way it was when it was first installed. This:', 'groove-folios'); ?></p>
        <ul class="g-dialog-confirm__list">
          <?php $this->display_reset_effects($has_stored_key); ?>
        </ul>

        <?php if ($has_content): ?>
        <fieldset class="g-reset__content">
          <legend class="g-reset__legend"><?php esc_html_e('Your folios', 'groove-folios'); ?></legend>
          <p class="g-reset__desc"><?php echo esc_html($this->describe_reset_content($counts)); ?></p>
          <div class="g-choices">
            <label class="g-choice">
              <input type="radio" name="groove_reset_content" value="keep" checked />
              <span class="g-choice__text">
                <span class="g-choice__label"><?php esc_html_e('Keep them', 'groove-folios'); ?></span>
                <span class="g-choice__hint"><?php esc_html_e('Folios, pages and collection tags stay exactly as they are.', 'groove-folios'); ?></span>
              </span>
            </label>
            <label class="g-choice g-choice--danger">
              <input type="radio" name="groove_reset_content" value="delete" />
              <span class="g-choice__text">
                <span class="g-choice__label"><?php esc_html_e('Delete them too', 'groove-folios'); ?></span>
                <span class="g-choice__hint"><?php esc_html_e('Permanently, trash included. Images in the Media Library stay.', 'groove-folios'); ?></span>
              </span>
            </label>
          </div>
        </fieldset>
        <?php endif; ?>

        <div class="g-dialog-confirm__notice g-dialog-confirm__notice--danger" data-groove-reset-warning<?php echo $has_content ? ' hidden' : ''; ?>>
          <p><?php echo $has_content ? esc_html__('The folios, their pages and collection tags are deleted for good. This can’t be undone.', 'groove-folios') : esc_html__('The settings and downloads are gone for good. This can’t be undone.', 'groove-folios'); ?></p>
        </div>
      </div>
      <div class="g-theme-details__footer">
        <button type="button" class="button button-secondary" data-groove-reset-close><?php esc_html_e('Cancel', 'groove-folios'); ?></button>
        <button type="submit" class="button g-dialog-confirm__destroy" data-groove-reset-submit
          data-label-keep="<?php esc_attr_e('Reset Groove Folios', 'groove-folios'); ?>"
          data-label-delete="<?php esc_attr_e('Reset and delete folios', 'groove-folios'); ?>"
          data-label-busy="<?php esc_attr_e('Resetting…', 'groove-folios'); ?>"><?php esc_html_e('Reset Groove Folios', 'groove-folios'); ?></button>
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
          __('Another collection tag already uses that slug.', 'groove-folios'),
          __('Pick a different slug, or clear the field and one will be generated from the name.', 'groove-folios'),
        );
      case 'term_exists':
        return array(
          __('A collection tag with that name already exists.', 'groove-folios'),
          __('Edit the existing tag from the list instead, or choose a different name.', 'groove-folios'),
        );
      case 'invalid_taxonomy':
        return array(
          __('The collection tag taxonomy is not registered.', 'groove-folios'),
          __('Deactivate and reactivate Groove Folios so the taxonomy is registered again, then retry.', 'groove-folios'),
        );
    }

    return array(
      __('The collection tag could not be saved.', 'groove-folios'),
      __('Reload this screen and try again. If it keeps failing, check the site error log for the database error behind it.', 'groove-folios'),
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
      'settings_saved' => __('Settings saved.', 'groove-folios'),
      'collection_tag_created' => __('Collection tag created.', 'groove-folios'),
      'collection_tag_updated' => __('Collection tag updated.', 'groove-folios'),
      'collection_tag_deleted' => __('Collection tag deleted.', 'groove-folios'),
    );

    if (isset($success[$message])) {
      \Groove\Toast::success($success[$message], $consumed);
      return;
    }

    switch ($message) {
      case 'base_slug_empty':
        \Groove\Toast::failure(
          __('A folio base slug is required.', 'groove-folios'),
          __('Enter the word you want in folio URLs — "folio" gives /folio/my-folio. The previous slug is still in place.', 'groove-folios'),
          '#groove-save-routing',
          $consumed
        );
        return;

      case 'base_slug_invalid':
        \Groove\Toast::failure(
          __('That base slug cannot be used in a URL.', 'groove-folios'),
          __('Use letters, numbers and hyphens, such as "folio" or "case-studies". The previous slug is still in place.', 'groove-folios'),
          '#groove-save-routing',
          $consumed
        );
        return;

      case 'collection_tag_empty':
        \Groove\Toast::failure(
          __('A collection tag needs a name.', 'groove-folios'),
          __('Type a name in the Name field above. The slug can be left blank — it is generated from the name.', 'groove-folios'),
          '#groove-save-collection-tag',
          $consumed
        );
        return;

      case 'collection_tag_error':
        list($tag_message, $tag_hint) = $this->get_collection_tag_error($error_code);
        \Groove\Toast::failure($tag_message, $tag_hint, '#groove-save-collection-tag', $consumed);
        return;

      case 'collection_tag_delete_error':
        // No save button to point at — the delete button that failed belongs
        // to a row that may no longer be on the screen.
        \Groove\Toast::error(
          'not_found' === $error_code
            ? __('That collection tag was already gone, so nothing was removed.', 'groove-folios')
            : __('That collection tag could not be removed. Reload the screen and try again.', 'groove-folios'),
          $consumed
        );
        return;

      case 'unknown_tab':
        \Groove\Toast::failure(
          __('Nothing was saved — that settings tab was not recognised.', 'groove-folios'),
          __('This usually means the page had been open long enough to go stale. Reload the settings screen and make the change again.', 'groove-folios'),
          '#groove-save-general',
          $consumed
        );
        return;
    }
  }

  public function display_content()
  {
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view parameters: the tab to show and the outcome code this page's own redirect set after a nonce-checked save.
    $tab_key = isset($_GET['tab_key']) ? sanitize_key(wp_unslash($_GET['tab_key'])) : 'general';
    $message = isset($_GET['message']) ? sanitize_key(wp_unslash($_GET['message'])) : '';
    $error_code = isset($_GET['error_code']) ? sanitize_key(wp_unslash($_GET['error_code'])) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

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
    $tab_key = isset($_GET['tab_key']) ? sanitize_key(wp_unslash($_GET['tab_key'])) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view parameter: which tab to highlight.
    $q = $this->parse_query();
    ?>
<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e('Settings tabs', 'groove-folios'); ?>">
  <?php
    foreach ($tabs as $tab_id => $tab) {
      $active_class = $tab_key === $tab_id ? ' nav-tab-active' : '';
      $q['tab_key'] = $tab_id;
      $tab_url = add_query_arg($q, admin_url('admin.php'));
      echo '<a href="' . esc_url($tab_url) . '" class="nav-tab' . esc_attr($active_class) . '">' . esc_html($tab['label']) . '</a>';
    }
    ?>
</nav>
<?php
  }
}
