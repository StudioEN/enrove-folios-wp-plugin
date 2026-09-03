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
        'label' => esc_html__('General', 'groove'),
      ],
      'collections' => [
        'label' => esc_html__('Collection Tags', 'groove'),
      ],
      'routing' => [
        'label' => esc_html__('Routing', 'groove'),
      ],
      'imagery' => [
        'label' => esc_html__('Imagery', 'groove'),
      ],
      'privacy' => [
        'label' => esc_html__('Privacy', 'groove'),
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

  private function sanitize_base_slug($raw_slug)
  {
    $slug = sanitize_title($raw_slug);
    if ($slug === '') {
      return 'folio';
    }

    return $slug;
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

  private function redirect_to_collections_tab($args = array())
  {
    wp_safe_redirect($this->get_settings_tab_url('collections', $args));
    exit;
  }

  private function redirect_to_imagery_tab($args = array())
  {
    wp_safe_redirect($this->get_settings_tab_url('imagery', $args));
    exit;
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
        return __('GROOVE_PEXELS_API_KEY in wp-config.php', 'groove');
      case 'env':
        return __('PEXELS_API_KEY environment variable', 'groove');
      case 'file':
        return __('.pexels-key file in the plugin folder', 'groove');
      case 'option':
        return __('This settings field', 'groove');
    }

    return __('Not configured', 'groove');
  }

  /**
   * Stash a one-shot notice for the current user (avoids putting API results in the URL).
   */
  private function set_pexels_notice($status, $text)
  {
    set_transient('groove_pexels_notice_' . get_current_user_id(), array(
      'status' => $status,
      'text' => $text,
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
      wp_die(esc_html__('You do not have permission to modify settings.', 'groove'));
    }

    $tab = isset($_POST['tab_key']) ? sanitize_key(wp_unslash($_POST['tab_key'])) : 'general';

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
      $base_slug = isset($_POST['folio_base_slug']) ? $this->sanitize_base_slug(wp_unslash($_POST['folio_base_slug'])) : 'folio';
      update_option('groove_folio_base_slug', $base_slug);
    } else {
      $analytics = isset($_POST['usage_analytics']) ? 1 : 0;
      update_option('groove_usage_analytics', $analytics);
    }

    $redirect = add_query_arg([
      'page' => static::PAGE_ID,
      'tab_key' => $tab,
      'message' => 'settings_saved'
    ], admin_url('admin.php'));

    wp_safe_redirect($redirect);
    exit;
  }

  public function handle_collection_tag_save()
  {
    check_admin_referer('groove_save_collection_tag', 'groove_nonce');

    if (!$this->can_manage_collection_tags()) {
      wp_die(esc_html__('You do not have permission to manage collection tags.', 'groove'));
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
      wp_die(esc_html__('You do not have permission to manage collection tags.', 'groove'));
    }

    $term_id = isset($_POST['collection_tag_id']) ? (int) wp_unslash($_POST['collection_tag_id']) : 0;
    if ($term_id <= 0) {
      $this->redirect_to_collections_tab(array('message' => 'collection_tag_error'));
    }

    $result = wp_delete_term($term_id, 'groove_collection_tag');
    if (is_wp_error($result)) {
      $this->redirect_to_collections_tab(array(
        'message' => 'collection_tag_error',
        'error_code' => $result->get_error_code(),
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
<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="g-settings-form space-y-4">
  <?php wp_nonce_field('groove_save_settings', 'groove_nonce'); ?>
  <input type="hidden" name="action" value="save_groove_settings" />
  <input type="hidden" name="tab_key" value="general" />

  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Folio Defaults', 'groove'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600"><?php esc_html_e('These defaults are applied when you create a new folio.', 'groove'); ?></p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div>
        <label for="groove-default-theme-id" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
          <?php esc_html_e('Default Theme', 'groove'); ?>
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
          <?php esc_html_e('Default Publish Status', 'groove'); ?>
        </label>
        <select id="groove-default-folio-status" name="default_folio_status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
          <option value="draft" <?php selected($default_status, 'draft'); ?>><?php esc_html_e('Draft', 'groove'); ?></option>
          <option value="publish" <?php selected($default_status, 'publish'); ?>><?php esc_html_e('Published', 'groove'); ?></option>
        </select>
      </div>
    </div>

    <div>
      <label for="groove-default-folio-title" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
        <?php esc_html_e('Default Folio Title', 'groove'); ?>
      </label>
      <input
        id="groove-default-folio-title"
        type="text"
        name="default_folio_title"
        value="<?php echo esc_attr($default_title); ?>"
        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
        placeholder="<?php echo esc_attr($default_theme_title); ?>" />
      <p class="mt-2 mb-0 text-sm text-gray-600">
        <?php esc_html_e('Leave blank to name each folio after the theme it is created with. Anything you type here is used for every folio instead.', 'groove'); ?>
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
      <button type="submit" class="button button-primary"><?php esc_html_e('Save Changes', 'groove'); ?></button>
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
<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="g-settings-form space-y-4">
  <?php wp_nonce_field('groove_save_settings', 'groove_nonce'); ?>
  <input type="hidden" name="action" value="save_groove_settings" />
  <input type="hidden" name="tab_key" value="routing" />

  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Routing', 'groove'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600"><?php esc_html_e('Customize the public URL base for folios.', 'groove'); ?></p>
    </div>

    <div>
      <label for="groove-folio-base-slug" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
        <?php esc_html_e('Folio Base Slug', 'groove'); ?>
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
      <p class="m-0"><?php esc_html_e('Example folio URL:', 'groove'); ?> <code><?php echo esc_html($sample_folio); ?></code></p>
      <p class="mt-2 mb-0"><?php esc_html_e('Example page URL:', 'groove'); ?> <code><?php echo esc_html($sample_page); ?></code></p>
    </div>

    <div>
      <button type="submit" class="button button-primary"><?php esc_html_e('Save Changes', 'groove'); ?></button>
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
    $form_title = $is_editing ? __('Edit Collection Tag', 'groove') : __('Add Collection Tag', 'groove');
    $submit_label = $is_editing ? __('Update Tag', 'groove') : __('Add Tag', 'groove');
    ?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4 xl:col-span-1">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php echo esc_html($form_title); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600"><?php esc_html_e('Manage the tags used to group folios into collections.', 'groove'); ?></p>
    </div>

    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="space-y-4">
      <?php wp_nonce_field('groove_save_collection_tag', 'groove_nonce'); ?>
      <input type="hidden" name="action" value="save_groove_collection_tag" />
      <input type="hidden" name="collection_tag_id" value="<?php echo esc_attr($is_editing ? (string) $edit_term->term_id : '0'); ?>" />

      <div>
        <label for="groove-collection-tag-name" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
          <?php esc_html_e('Name', 'groove'); ?>
        </label>
        <input
          id="groove-collection-tag-name"
          type="text"
          name="collection_tag_name"
          value="<?php echo esc_attr($is_editing ? $edit_term->name : ''); ?>"
          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
          placeholder="<?php esc_attr_e('Magazine', 'groove'); ?>" />
      </div>

      <div>
        <label for="groove-collection-tag-slug" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
          <?php esc_html_e('Slug', 'groove'); ?>
        </label>
        <input
          id="groove-collection-tag-slug"
          type="text"
          name="collection_tag_slug"
          value="<?php echo esc_attr($is_editing ? $edit_term->slug : ''); ?>"
          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
          placeholder="<?php esc_attr_e('magazine', 'groove'); ?>" />
        <p class="mt-1 mb-0 text-xs text-gray-400"><?php esc_html_e('Optional. Leave blank to generate from the name.', 'groove'); ?></p>
      </div>

      <div class="flex items-center gap-2">
        <button type="submit" class="button button-primary"><?php echo esc_html($submit_label); ?></button>
        <?php if ($is_editing): ?>
          <a href="<?php echo esc_url($this->get_settings_tab_url('collections')); ?>" class="button button-secondary"><?php esc_html_e('Cancel', 'groove'); ?></a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 xl:col-span-2">
    <div class="mb-4">
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Existing Collection Tags', 'groove'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600"><?php esc_html_e('These tags can be assigned on folio setup screens and used to filter All Folios.', 'groove'); ?></p>
    </div>

    <?php if (empty($terms)): ?>
      <p class="m-0 text-sm text-gray-600"><?php esc_html_e('No collection tags yet.', 'groove'); ?></p>
    <?php else: ?>
      <table class="widefat striped">
        <thead>
          <tr>
            <th><?php esc_html_e('Name', 'groove'); ?></th>
            <th><?php esc_html_e('Slug', 'groove'); ?></th>
            <th><?php esc_html_e('Folios', 'groove'); ?></th>
            <th><?php esc_html_e('Actions', 'groove'); ?></th>
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
                    <?php esc_html_e('Edit', 'groove'); ?>
                  </a>
                  <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="inline-block ml-3" onsubmit="return window.confirm('<?php echo esc_js(__('Delete this collection tag?', 'groove')); ?>');">
                    <?php wp_nonce_field('groove_delete_collection_tag', 'groove_nonce'); ?>
                    <input type="hidden" name="action" value="delete_groove_collection_tag" />
                    <input type="hidden" name="collection_tag_id" value="<?php echo esc_attr((string) $term->term_id); ?>" />
                    <button type="submit" class="button-link delete"><?php esc_html_e('Delete', 'groove'); ?></button>
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
      wp_die(esc_html__('You do not have permission to modify settings.', 'groove'));
    }

    if ($this->pexels_is_available() && \Groove\Pexels\Key::is_locked_by_constant()) {
      $this->set_pexels_notice('warning', __('GROOVE_PEXELS_API_KEY is defined in wp-config.php, so the stored key was not changed.', 'groove'));
      $this->redirect_to_imagery_tab();
    }

    $submitted = isset($_POST['pexels_api_key']) ? sanitize_text_field(wp_unslash($_POST['pexels_api_key'])) : '';

    if ($submitted === '') {
      $this->set_pexels_notice('info', __('No key entered — the stored key was left unchanged.', 'groove'));
      $this->redirect_to_imagery_tab();
    }

    // update_option() cannot change the autoload flag of an existing option,
    // so delete first and re-add with autoload explicitly off.
    delete_option('groove_pexels_api_key');
    add_option('groove_pexels_api_key', $submitted, '', 'no');

    $this->set_pexels_notice('success', __('Pexels API key saved.', 'groove'));
    $this->redirect_to_imagery_tab();
  }

  /**
   * Clear the stored Pexels API key.
   */
  public function handle_pexels_key_delete()
  {
    check_admin_referer('groove_delete_pexels_key', 'groove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'groove'));
    }

    delete_option('groove_pexels_api_key');

    $this->set_pexels_notice('success', __('Stored Pexels API key removed.', 'groove'));
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
      wp_die(esc_html__('You do not have permission to modify settings.', 'groove'));
    }

    if (!$this->pexels_is_available()) {
      $this->set_pexels_notice('error', __('The Pexels client is unavailable.', 'groove'));
      $this->redirect_to_imagery_tab();
    }

    $client = new \Groove\Pexels\Client();

    if (!$client->has_key()) {
      $this->set_pexels_notice('error', __('No Pexels API key is configured.', 'groove'));
      $this->redirect_to_imagery_tab();
    }

    $result = $client->verify();

    if (is_wp_error($result)) {
      $this->set_pexels_notice('error', sprintf(
        /* translators: %s: error message returned by the Pexels API. */
        __('Pexels rejected the request: %s', 'groove'),
        $result->get_error_message()
      ));
      $this->redirect_to_imagery_tab();
    }

    $remaining = is_array($result) && isset($result['remaining']) ? (int) $result['remaining'] : 0;
    $limit = is_array($result) && isset($result['limit']) ? (int) $result['limit'] : 0;

    $this->set_pexels_notice('success', sprintf(
      /* translators: 1: remaining requests, 2: hourly request limit. */
      __('Connected to Pexels. %1$s of %2$s requests remaining this hour.', 'groove'),
      number_format_i18n($remaining),
      number_format_i18n($limit)
    ));
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

    $credits = array();
    $credits_exist = false;
    if (class_exists('\Groove\Pexels\Credits')) {
      $credits_exist = file_exists(\Groove\Pexels\Credits::path());
      $credits = \Groove\Pexels\Credits::all();
    }
    ?>
<div class="space-y-4">
  <?php if ($notice): ?>
  <div class="notice notice-<?php echo esc_attr($notice['status']); ?>">
    <p><?php echo esc_html($notice['text']); ?></p>
  </div>
  <?php endif; ?>

  <?php if (!$available): ?>
  <div class="notice notice-warning">
    <p><?php esc_html_e('The Pexels helper classes are not installed in this copy of the plugin.', 'groove'); ?></p>
  </div>
  <?php endif; ?>

  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Pexels API Key', 'groove'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600">
        <?php esc_html_e('Used only when curating imagery from the command line. Folios never call the Pexels API when they are viewed or edited.', 'groove'); ?>
      </p>
    </div>

    <?php if ($locked): ?>
    <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
      <p class="m-0">
        <?php
        printf(
          /* translators: %s: the wp-config.php constant name. */
          esc_html__('%s is defined in wp-config.php and takes precedence. The field below is disabled.', 'groove'),
          '<code>GROOVE_PEXELS_API_KEY</code>'
        );
        ?>
      </p>
    </div>
    <?php endif; ?>

    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="space-y-4" autocomplete="off">
      <?php wp_nonce_field('groove_save_pexels_key', 'groove_nonce'); ?>
      <input type="hidden" name="action" value="save_groove_pexels_key" />

      <div>
        <label for="groove-pexels-api-key" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
          <?php esc_html_e('API Key', 'groove'); ?>
        </label>
        <input
          id="groove-pexels-api-key"
          type="password"
          name="pexels_api_key"
          value=""
          autocomplete="new-password"
          spellcheck="false"
          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:text-gray-400"
          placeholder="<?php echo esc_attr($masked !== '' ? $masked : __('Paste your Pexels API key', 'groove')); ?>"
          <?php disabled($locked, true); ?> />
        <p class="mt-1 mb-0 text-xs text-gray-400">
          <?php esc_html_e('The stored key is never displayed. Leave this empty to keep the current key.', 'groove'); ?>
        </p>
      </div>

      <div>
        <button type="submit" class="button button-primary" <?php disabled($locked, true); ?>><?php esc_html_e('Save Key', 'groove'); ?></button>
      </div>
    </form>

    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3 text-sm text-gray-700 space-y-1">
      <p class="m-0">
        <strong><?php esc_html_e('Key source:', 'groove'); ?></strong>
        <?php echo esc_html($this->get_pexels_key_source_label($source)); ?>
        <?php if ($masked !== ''): ?>
        <code><?php echo esc_html($masked); ?></code>
        <?php endif; ?>
      </p>
      <p class="m-0 text-xs text-gray-500">
        <?php esc_html_e('Checked in order: wp-config.php constant, PEXELS_API_KEY environment variable, .pexels-key file, then this setting.', 'groove'); ?>
      </p>
    </div>

    <div class="flex items-center gap-2">
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <?php wp_nonce_field('groove_test_pexels_connection', 'groove_nonce'); ?>
        <input type="hidden" name="action" value="test_groove_pexels_connection" />
        <button type="submit" class="button button-secondary" <?php disabled(!$available || !$has_key, true); ?>>
          <?php esc_html_e('Test Connection', 'groove'); ?>
        </button>
      </form>

      <?php if ($has_option_key): ?>
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" onsubmit="return window.confirm('<?php echo esc_js(__('Remove the stored Pexels API key?', 'groove')); ?>');">
        <?php wp_nonce_field('groove_delete_pexels_key', 'groove_nonce'); ?>
        <input type="hidden" name="action" value="delete_groove_pexels_key" />
        <button type="submit" class="button-link delete"><?php esc_html_e('Remove Key', 'groove'); ?></button>
      </form>
      <?php endif; ?>
    </div>

    <p class="m-0 text-xs text-gray-500">
      <?php esc_html_e('Testing the connection is the only action on this screen that contacts Pexels, and it only happens when you press the button.', 'groove'); ?>
    </p>
  </section>

  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Curating Imagery', 'groove'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600">
        <?php esc_html_e('Theme covers and sample-content placeholders are downloaded once from the command line and committed with the plugin.', 'groove'); ?>
      </p>
    </div>
    <pre class="m-0 overflow-x-auto rounded-md border border-gray-200 bg-gray-50 p-3 text-xs text-gray-800">php bin/curate-pexels.php --help</pre>
  </section>

  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Image Credits', 'groove'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600">
        <?php esc_html_e('The Pexels licence requires a visible link to Pexels and credit to each photographer.', 'groove'); ?>
      </p>
    </div>

    <p class="m-0 text-sm">
      <a href="https://www.pexels.com" target="_blank" rel="noopener noreferrer" class="font-semibold text-indigo-600 hover:text-indigo-500">
        <?php esc_html_e('Photos provided by Pexels', 'groove'); ?>
      </a>
    </p>

    <?php if (empty($credits)): ?>
    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3 text-sm text-gray-700">
      <p class="m-0"><?php esc_html_e('No imagery has been curated yet, so there is nothing to credit.', 'groove'); ?></p>
      <p class="mt-2 mb-0">
        <?php esc_html_e('Run the curation script to download imagery and build the credits file:', 'groove'); ?>
        <code>php bin/curate-pexels.php</code>
      </p>
      <?php if ($credits_exist): ?>
      <p class="mt-2 mb-0 text-xs text-gray-500"><?php esc_html_e('A credits file exists but contains no entries.', 'groove'); ?></p>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <table class="widefat striped">
      <thead>
        <tr>
          <th><?php esc_html_e('Slot', 'groove'); ?></th>
          <th><?php esc_html_e('Photographer', 'groove'); ?></th>
          <th><?php esc_html_e('Photo', 'groove'); ?></th>
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
                  <?php echo esc_html($photo_id !== '' ? '#' . $photo_id : __('View on Pexels', 'groove')); ?>
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

  public function display_privacy_fields()
  {
    ?>
<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="g-settings-form space-y-4">
  <?php wp_nonce_field('groove_save_settings', 'groove_nonce'); ?>
  <input type="hidden" name="action" value="save_groove_settings" />
  <input type="hidden" name="tab_key" value="privacy" />

  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Privacy', 'groove'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600"><?php esc_html_e('Review policy details and control optional analytics.', 'groove'); ?></p>
    </div>

    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3">
      <p class="m-0 text-sm text-gray-700">
        <?php esc_html_e('Read our Privacy Policy:', 'groove'); ?>
        <a href="https://groove.studio/privacy" target="_blank" rel="noopener noreferrer" class="ml-1 text-indigo-600 hover:text-indigo-500">
          <?php esc_html_e('Open policy', 'groove'); ?>
        </a>
      </p>
    </div>

    <label class="flex items-center gap-2">
      <input
        type="checkbox"
        name="usage_analytics"
        value="1"
        class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
        <?php checked(get_option('groove_usage_analytics', 0), 1); ?> />
      <span class="text-sm text-gray-700">
        <?php esc_html_e('Share anonymous usage analytics with us to improve product experience.', 'groove'); ?>
      </span>
    </label>

    <p class="m-0 text-xs text-gray-500">
      <?php esc_html_e('This setting covers passive analytics only. Messages you send from the Feedback page are delivered to StudioEN whether or not this is enabled — they include the email address and message you type, plus your site URL and plugin version.', 'groove'); ?>
    </p>

    <div>
      <button type="submit" class="button button-primary"><?php esc_html_e('Save Changes', 'groove'); ?></button>
    </div>
  </section>
</form>
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

  public function display_content()
  {
    $tab_key = isset($_GET['tab_key']) ? sanitize_key(wp_unslash($_GET['tab_key'])) : 'general';
    $message = isset($_GET['message']) ? sanitize_key(wp_unslash($_GET['message'])) : '';
    ?>
<div class="space-y-4">
  <?php if ('settings_saved' === $message): ?>
  <div class="notice notice-success">
    <p><?php esc_html_e('Settings saved.', 'groove'); ?></p>
  </div>
  <?php elseif ('collection_tag_created' === $message): ?>
  <div class="notice notice-success">
    <p><?php esc_html_e('Collection tag created.', 'groove'); ?></p>
  </div>
  <?php elseif ('collection_tag_updated' === $message): ?>
  <div class="notice notice-success">
    <p><?php esc_html_e('Collection tag updated.', 'groove'); ?></p>
  </div>
  <?php elseif ('collection_tag_deleted' === $message): ?>
  <div class="notice notice-success">
    <p><?php esc_html_e('Collection tag deleted.', 'groove'); ?></p>
  </div>
  <?php elseif ('collection_tag_empty' === $message): ?>
  <div class="notice notice-error">
    <p><?php esc_html_e('Collection tag name is required.', 'groove'); ?></p>
  </div>
  <?php elseif ('collection_tag_error' === $message): ?>
  <div class="notice notice-error">
    <p><?php esc_html_e('Collection tag could not be saved.', 'groove'); ?></p>
  </div>
  <?php endif; ?>

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
<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e('Settings tabs', 'groove'); ?>">
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
