<?php
namespace Groove\Pages;

use Groove\Pages\Page;
use Groove\Pages\Overview;
use Groove\Menu\Menu_Manager;
use Groove\Menu\Add_New_Menu_Item;


if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}


class Add_New extends Page
{
  const PAGE_ID = 'groove-add-new';
  const POST_TYPE = 'groove_folio_page';

  public function __construct()
  {
    $this->add_post_action('groove_create_folio', 'create_folio');

    add_action('groove/menu/register', function (Menu_Manager $menu) {
      $menu->register(static::PAGE_ID, new Add_New_Menu_Item($this));
    }, Overview::MENU_PRIORITY + 20);
  }

  public function create_folio()
  {
    $action = isset($_POST['action']) ? sanitize_key(wp_unslash($_POST['action'])) : '';
    if ($action === 'groove_create_folio') {
      check_admin_referer('groove_create_folio_action', 'groove_nonce');

      if (!current_user_can('edit_posts')) {
        wp_die(esc_html__('You do not have permission to create folios.', 'groove'));
      }

      $themes = \Groove\Themes\Themes_Manager::get_all_themes();
      if (empty($themes)) {
        wp_die(esc_html__('No themes are available. Install a theme first.', 'groove'));
      }

      $default_theme_id = (string) get_option('groove_default_theme_id', '');
      if ($default_theme_id === '' || !isset($themes[$default_theme_id])) {
        $default_theme_id = (string) array_key_first($themes);
      }

      $theme_id = isset($_POST['themeId']) ? sanitize_key(wp_unslash($_POST['themeId'])) : $default_theme_id;
      if (empty($theme_id) || !isset($themes[$theme_id])) {
        wp_die(esc_html__('Invalid theme selection.', 'groove'));
      }

      $default_status = (string) get_option('groove_default_folio_status', 'draft');
      if (!in_array($default_status, ['draft', 'publish'], true)) {
        $default_status = 'draft';
      }

      $title = (string) get_option('groove_default_folio_title', '');
      if ($title === '') {
        $title = esc_html__('A new folio', 'groove');
      }

      $default_allow_download = (int) get_option('groove_default_allow_pdf_download', 1) === 1;
      $default_permission = $default_allow_download ? '2' : '4';

      $fields = array(
        'post_type' => 'groove_folio',
        'post_status' => $default_status,
        'post_title' => $title,
        'post_name' => sanitize_title($title),
        'post_content' => '',
        'meta_input' => array(
          'theme_id' => $theme_id,
          'subtitle' => '',
          'permission' => $default_permission,
          'copyright' => '',
        ),
      );

      $folio_id = wp_insert_post($fields);

      if (!is_wp_error($folio_id)) {
        $redirect_url = admin_url('admin.php?page=groove-folio&folio_id=' . $folio_id);
        wp_safe_redirect($redirect_url);
        exit;
      }
      else {
        wp_die(esc_html($folio_id->get_error_message()));
      }
    }
  }

  public function get_title()
  {
    return esc_html__('Add New', 'groove');
  }

  public function create_tabs()
  {
    return array();
  }

  public function display__themes()
  {
    $themes = \Groove\Themes\Themes_Manager::get_all_themes();
    $first_theme_id = '';
    $first_theme_name = '';
    if (!empty($themes)) {
      $saved_default = (string) get_option('groove_default_theme_id', '');
      if ($saved_default !== '' && isset($themes[$saved_default])) {
        $first_theme_id = $saved_default;
      } else {
        $first_theme_id = (string) array_key_first($themes);
      }

      if ($first_theme_id !== '' && isset($themes[$first_theme_id]['name'])) {
        $first_theme_name = (string) $themes[$first_theme_id]['name'];
      }
    }

    if (empty($themes)) {
      echo '<p>' . esc_html__('No themes available. Please install a theme first.', 'groove') . '</p>';
      return;
    }

    $theme_count = count($themes);
?>
<p class="g-folio__themes-desc">
  <?php
  /* translators: %s: number of available themes */
  $themes_help_text = sprintf(
    _n('Choose a theme to get started. %s theme available.', 'Choose a theme to get started. %s themes available.', $theme_count, 'groove'),
    number_format_i18n($theme_count)
  );
  echo esc_html($themes_help_text);
  ?>
</p>
<p class="g-folio__themes-selected">
  <?php echo esc_html__('Selected theme:', 'groove'); ?>
  <strong id="g-folio-selected-theme-name"><?php echo esc_html($first_theme_name); ?></strong>
</p>
<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
  <?php wp_nonce_field('groove_create_folio_action', 'groove_nonce'); ?>
  <input type="hidden" name="action" value="groove_create_folio" />

  <div class="g-folio__themes" role="radiogroup" aria-label="<?php echo esc_attr__('Available themes', 'groove'); ?>">
    <?php
    foreach ($themes as $id => $theme) {
      $is_first = ($id === $first_theme_id);
      $card_classes = 'g-folio__theme-option g-folio__theme-option--add-new relative cursor-pointer rounded-lg border-2 transition-all';
      $card_classes .= $is_first ? ' border-indigo-600 ring-1 ring-indigo-600' : ' border-gray-200 hover:border-gray-300';
      echo '<button type="button" class="' . esc_attr($card_classes) . '" 
                 data-theme-id="' . esc_attr($id) . '"
                 data-theme-name="' . esc_attr($theme['name']) . '"
                 role="radio"
                 aria-checked="' . ($is_first ? 'true' : 'false') . '"
                 tabindex="' . ($is_first ? '0' : '-1') . '">';
      echo '<div class="g-folio__theme-option-thumb aspect-w-16 aspect-h-9 overflow-hidden rounded-t-lg rounded-b-none border-b border-gray-200">';
      echo '<img src="' . esc_url($theme['thumbnail_url']) . '" alt="' . esc_attr($theme['name']) . '" class="object-cover w-full h-full" loading="lazy" />';
      echo '</div>';
      echo '<div class="g-folio__theme-option-name p-2 text-center text-sm font-medium text-gray-900 border-t border-gray-100 bg-gray-50/50 rounded-b-lg">';
      echo esc_html($theme['name']);
      echo '</div>';
      echo '<span class="active-badge absolute -top-2 -right-2 inline-flex items-center rounded-full bg-indigo-600 px-2.5 py-0.5 text-xs font-medium text-white shadow-sm ring-2 ring-white ' . ($is_first ? '' : 'hidden') . '">';
      echo esc_html__('Selected', 'groove');
      echo '</span>';
      echo '</button>';
    }
?>
  </div>
  <input type="hidden" id="g-add-new-theme-id" name="themeId" value="<?php echo esc_attr($first_theme_id)?>" />
  <div class="g-folio__theme-button">
    <button type="submit" class="button button-primary">
      <?php echo esc_html__('Continue', 'groove'); ?>
    </button>
  </div>
</form>
<?php
  }

  public function display_content()
  {
?>
<div class="g-folio__content g-folio__postbox-themes">
  <div class="g-folio__postbox postbox-container" style="width: 656px">
    <div class="postbox">
      <div class="g-folio__postbox-header">
        <div class="g-folio__postbox-header-left">
          <h2 class="g-folio__postbox-title">
            <?php echo esc_html__('Themes', 'groove'); ?>
          </h2>
        </div>
        <div class="g-folio__postbox-header-right">
          <div class="g-folio__postbox-close">
            <?php
            $from = isset($_GET['from']) ? sanitize_key(wp_unslash($_GET['from'])) : 'groove-overview';
            $allowed_from = array('groove-overview', 'groove-all-folios');
            if (!in_array($from, $allowed_from, true)) {
              $from = 'groove-overview';
            }
            ?>
            <a type="submit" class="g-folio__postbox-icon gicon-close"
              href="<?php echo esc_url(add_query_arg(array('page' => $from), admin_url('admin.php'))); ?>"></a>
          </div>
        </div>
      </div>
      <div class="g-folio__postbox-body">
        <?php $this->display__themes()?>
      </div>
    </div>
  </div>
</div>
<?php
  }
}
?>
