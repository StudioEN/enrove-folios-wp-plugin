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

      $theme_id = isset($_POST['themeId']) ? sanitize_key(wp_unslash($_POST['themeId'])) : '';
      $themes = \Groove\Themes\Themes_Manager::get_all_themes();
      if (empty($theme_id) || !isset($themes[$theme_id])) {
        wp_die(esc_html__('Invalid theme selection.', 'groove'));
      }
      $title = esc_html__('A new folio', 'groove');

      $fields = array(
        'post_type' => 'groove_folio',
        'post_status' => 'draft',
        'post_title' => $title,
        'post_name' => sanitize_title($title),
        'post_content' => '',
        'meta_input' => array(
          'theme_id' => $theme_id,
          'subtitle' => '',
          'permission' => '2',
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
    $first_theme_id = !empty($themes) ? array_key_first($themes) : '';

    if (empty($themes)) {
      echo '<p>' . esc_html__('No themes available. Please install a theme first.', 'groove') . '</p>';
      return;
    }
?>
<p class="g-folio__themes-desc"><?php echo esc_html__('Choose a theme to get started', 'groove'); ?></p>
<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
  <?php wp_nonce_field('groove_create_folio_action', 'groove_nonce'); ?>
  <input type="hidden" name="action" value="groove_create_folio" />

  <div class="g-folio__themes">
    <?php
    foreach ($themes as $id => $theme) {
      $is_first = ($id === $first_theme_id);
      echo '<div class="g-folio__theme ' . ($is_first ? 'is-active' : '') . '" 
                 data-theme-id="' . esc_attr($id) . '">';
      echo '<div class="g-folio__theme-thumb"><img src="' . esc_url($theme['thumbnail_url']) . '" /></div>';
      echo '<div class="g-folio__theme-name">' . esc_html($theme['name']) . '</div>';
      echo '</div>';
    }
?>
  </div>
  <input type="hidden" name="themeId" value="<?php echo esc_attr($first_theme_id)?>" />
  <div class="g-folio__theme-button">
    <button type="submit" class="g-folio__button g-folio__button-primary">
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
