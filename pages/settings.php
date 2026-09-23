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
    return 'Settings';
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
      'privacy' => [
        'label' => esc_html__('Privacy', 'groove-folios'),
      ]
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
    $term_id = isset($_GET['edit_collection_tag']) ? (int) wp_unslash($_GET['edit_collection_tag']) : 0;
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
   * the imagery tab can pin the explanation where the operator just pressed.
   *
   * @param string $status success|error|warning|info.
   * @param string $text   What happened.
   * @param string $hint   What to do about it. Failures only.
   * @param string $anchor CSS selector for the control to point at.
   */
  private function set_pexels_notice($status, $text, $hint = '', $anchor = '')
  {
    set_transient('groove_pexels_notice_' . get_current_user_id(), array(
      'status' => $status,
      'text' => $text,
      'hint' => $hint,
      'anchor' => $anchor,
    ), MINUTE_IN_SECONDS);
  }

  private function take_pexels_notice()
  {
    $key = 'groove_pexels_notice_' . get_current_user_id();
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
      $raw_slug = isset($_POST['folio_base_slug']) ? trim((string) wp_unslash($_POST['folio_base_slug'])) : '';

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
    $term_id = isset($_POST['collection_tag_id']) ? (int) wp_unslash($_POST['collection_tag_id']) : 0;

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

    $term_id = isset($_POST['collection_tag_id']) ? (int) wp_unslash($_POST['collection_tag_id']) : 0;
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

    <script>
      // Keep the placeholder honest: it previews the title a folio would get
      // from the currently selected default theme, so an empty field is not a
      // mystery. Purely cosmetic — the value is resolved server side on create.
      (function () {
        var themes = document.getElementById('groove-default-theme-id');
        var title = document.getElementById('groove-default-folio-title');
        if (!themes || !title) {
          return;
        }
        themes.addEventListener('change', function () {
          var option = themes.options[themes.selectedIndex];
          title.placeholder = (option && option.getAttribute('data-default-title')) || title.placeholder;
        });
      })();
    </script>

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
      $this->set_pexels_notice(
        'warning',
        __('GROOVE_PEXELS_API_KEY is defined in wp-config.php, so the stored key was not changed.', 'groove-folios'),
        __('The constant wins over anything saved here. Remove it from wp-config.php if you want to manage the key from this screen.', 'groove-folios'),
        '#groove-save-pexels-key'
      );
      $this->redirect_to_imagery_tab();
    }

    $submitted = isset($_POST['pexels_api_key']) ? sanitize_text_field(wp_unslash($_POST['pexels_api_key'])) : '';

    if ($submitted === '') {
      $this->set_pexels_notice('info', __('No key entered — the stored key was left unchanged.', 'groove-folios'));
      $this->redirect_to_imagery_tab();
    }

    // update_option() cannot change the autoload flag of an existing option,
    // so delete first and re-add with autoload explicitly off.
    delete_option('groove_pexels_api_key');
    add_option('groove_pexels_api_key', $submitted, '', 'no');

    $this->set_pexels_notice('success', __('Pexels API key saved.', 'groove-folios'));
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

    $this->set_pexels_notice('success', __('Stored Pexels API key removed.', 'groove-folios'));
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
      $this->set_pexels_notice(
        'error',
        __('The Pexels client is unavailable.', 'groove-folios'),
        __('The pexels/ helpers are missing from this copy of the plugin. Reinstall or update Groove Folios to restore them.', 'groove-folios'),
        '#groove-test-pexels-connection'
      );
      $this->redirect_to_imagery_tab();
    }

    $client = new \Groove\Pexels\Client();

    if (!$client->has_key()) {
      $this->set_pexels_notice(
        'error',
        __('No Pexels API key is configured.', 'groove-folios'),
        __('Paste a key into the field above and save it, then test the connection again.', 'groove-folios'),
        '#groove-test-pexels-connection'
      );
      $this->redirect_to_imagery_tab();
    }

    $result = $client->verify();

    if (is_wp_error($result)) {
      $this->set_pexels_notice(
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

    $this->set_pexels_notice('success', sprintf(
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

    $result = \Groove\Pexels\Library::download_missing();
    $failed = count($result['failed']);

    if ($failed === 0) {
      $this->set_pexels_notice('success', sprintf(
        /* translators: %s: number of photos downloaded. */
        _n('Downloaded %s photo.', 'Downloaded %s photos.', $result['downloaded'], 'groove-folios'),
        number_format_i18n($result['downloaded'])
      ));
      $this->redirect_to_imagery_tab();
    }

    $this->set_pexels_notice(
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

  public function display_imagery_fields()
  {
    $available = $this->pexels_is_available();
    $locked = $available ? \Groove\Pexels\Key::is_locked_by_constant() : false;
    $source = $available ? \Groove\Pexels\Key::source() : '';
    $masked = $available ? \Groove\Pexels\Key::masked() : '';
    $has_key = $source !== '';
    $has_option_key = get_option('groove_pexels_api_key', '') !== '';
    $notice = $this->take_pexels_notice();

    // The API key and the curation script only matter in a development checkout.
    // The WordPress.org package leaves bin/curate-pexels.php out, and without it
    // the key has nothing to do, so neither section is shown.
    $can_curate = is_readable(GROOVE_PATH . 'bin/curate-pexels.php');
    $photos = $available ? \Groove\Pexels\Library::status() : array('total' => 0, 'present' => 0, 'missing' => array());

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
    if ($notice) {
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

    <p class="m-0 text-xs text-gray-500">
      <?php esc_html_e('Downloads about 4 MB from images.pexels.com into your uploads folder. Nothing is sent to Pexels beyond the requests for the photos, and nothing is fetched until you press the button. Folios seeded before the download pick the photos up once they are here.', 'groove-folios'); ?>
    </p>
  </section>
  <?php endif; ?>

  <?php if ($can_curate): ?>
  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Pexels API Key', 'groove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600">
        <?php esc_html_e('Used only when curating imagery from the command line. Folios never call the Pexels API when they are viewed or edited.', 'groove-folios'); ?>
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

  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Curating Imagery', 'groove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600">
        <?php esc_html_e('Theme covers and sample-content placeholders are chosen and downloaded from the command line in a development checkout, which also records where each one can be fetched from.', 'groove-folios'); ?>
      </p>
    </div>
    <pre class="m-0 overflow-x-auto rounded-md border border-gray-200 bg-gray-50 p-3 text-xs text-gray-800">php bin/curate-pexels.php --help</pre>
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
          <?php esc_html_e('Google Fonts — when a folio is rendered, the reader’s browser fetches the theme’s typefaces from fonts.googleapis.com, which means Google sees the reader’s IP address. This happens on your published folios, not in the admin.', 'groove-folios'); ?>
        </li>
        <li>
          <?php esc_html_e('Pexels images — only when an administrator presses Download Photos on the Imagery tab. The photos are fetched once from images.pexels.com into your uploads folder, and Pexels sees your server’s IP address. After that they are served from your own site.', 'groove-folios'); ?>
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
    $tab_key = isset($_GET['tab_key']) ? sanitize_key(wp_unslash($_GET['tab_key'])) : 'general';
    $message = isset($_GET['message']) ? sanitize_key(wp_unslash($_GET['message'])) : '';
    $error_code = isset($_GET['error_code']) ? sanitize_key(wp_unslash($_GET['error_code'])) : '';

    $this->toast_message($message, $error_code);
    ?>
<div class="space-y-4">
  <?php if ('collections' === $tab_key): ?>
    <?php $this->display_tab_collections(); ?>
  <?php elseif ('routing' === $tab_key): ?>
    <?php $this->display_tab_routing(); ?>
  <?php elseif ('imagery' === $tab_key): ?>
    <?php $this->display_tab_imagery(); ?>
  <?php elseif ('privacy' === $tab_key): ?>
    <?php $this->display_tab_privacy(); ?>
  <?php else: ?>
    <?php $this->display_tab_general(); ?>
  <?php endif; ?>
</div>
<?php
  }

  public function display_tabs()
  {
    $tabs = $this->get_tabs();
    $tab_key = isset($_GET['tab_key']) ? sanitize_key(wp_unslash($_GET['tab_key'])) : 'general';
    $q = $this->parse_query();
    ?>
<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e('Settings tabs', 'groove-folios'); ?>">
  <?php
    foreach ($tabs as $tab_id => $tab) {
      $active_class = $tab_key === $tab_id ? ' nav-tab-active' : '';
      $q['tab_key'] = $tab_id;
      $sanitized_tab_label = esc_html($tab['label']);
      $tab_url = add_query_arg($q, admin_url('admin.php'));
      echo '<a href="' . esc_url($tab_url) . '" class="nav-tab' . $active_class . '">' . $sanitized_tab_label . '</a>';
    }
    ?>
</nav>
<?php
  }
}
