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
      'routing' => [
        'label' => esc_html__('Routing', 'groove'),
      ],
      'privacy' => [
        'label' => esc_html__('Privacy', 'groove'),
      ]
    ];
  }

  public function __construct()
  {
    $this->add_post_action('save_groove_settings', 'handle_save');

    add_action('groove/menu/register', function (Menu_Manager $menu) {
      $menu->register(static::PAGE_ID, new Settings_Menu_Item($this));
    }, Overview::MENU_PRIORITY + 20);
  }

  private function get_default_folio_title()
  {
    $title = get_option('groove_default_folio_title', '');
    if (!is_string($title) || $title === '') {
      return __('A new folio', 'groove');
    }

    return $title;
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

      $default_title = isset($_POST['default_folio_title']) ? sanitize_text_field(wp_unslash($_POST['default_folio_title'])) : '';
      if ($default_title === '') {
        $default_title = __('A new folio', 'groove');
      }
      update_option('groove_default_folio_title', $default_title);

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

  public function display_general_fields()
  {
    $themes = Themes_Manager::get_all_themes();
    $default_theme_id = $this->get_default_theme_id();
    $default_status = $this->get_default_folio_status();
    $default_title = $this->get_default_folio_title();
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
          <option value="<?php echo esc_attr($theme_id); ?>" <?php selected($default_theme_id, $theme_id); ?>>
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
        placeholder="<?php esc_attr_e('A new folio', 'groove'); ?>" />
    </div>

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
  <div class="notice notice-success inline">
    <p><?php esc_html_e('Settings saved.', 'groove'); ?></p>
  </div>
  <?php endif; ?>

  <?php if ('routing' === $tab_key): ?>
    <?php $this->display_tab_routing(); ?>
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
?>
