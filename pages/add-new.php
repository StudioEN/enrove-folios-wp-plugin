<?php
namespace Groove\Pages;

use Groove\Pages\Page;
use Groove\Pages\Overview;
use Groove\Menu\Menu_Manager;
use Groove\Menu\Add_New_Menu_Item;

use Groove\Themes\Default_Themes;


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
    if (isset($_POST['action']) && $_POST['action'] === 'groove_create_folio') {
      check_admin_referer('groove_create_folio_action', 'groove_nonce');

      if (!current_user_can('edit_posts')) {
        wp_die(esc_html__('You do not have permission to create folios.', 'groove'));
      }

      $theme_id = sanitize_key($_POST['themeId']);
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
        wp_redirect($redirect_url);
        exit;
      }
      else {
        wp_die(esc_html($folio_id->get_error_message()));
      }
    }
  }

  public function get_title()
  {
    return 'Add New';
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
<p class="g-folio__themes-desc">Choose a Theme to get started</p>
<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
  <?php wp_nonce_field('groove_create_folio_action', 'groove_nonce'); ?>
  <input type="hidden" name="action" value="groove_create_folio" />

  <div class="g-folio__themes">
    <?php
    foreach ($themes as $id => $theme) {
      $is_first = ($id === $first_theme_id);
      echo '<div class="g-folio__theme ' . ($is_first ? 'is-active' : '') . '" 
                 data-theme-id="' . esc_attr($id) . '"
                 onclick="document.querySelectorAll(\'.g-folio__theme\').forEach(el => el.classList.remove(\'is-active\')); this.classList.add(\'is-active\'); document.querySelector(\'[name=themeId]\').value = \'' . esc_attr($id) . '\';">';
      echo '<div class="g-folio__theme-thumb"><img src="' . esc_url($theme['thumbnail_url']) . '" /></div>';
      echo '<div class="g-folio__theme-name">' . esc_html($theme['name']) . '</div>';
      echo '</div>';
    }
?>
  </div>
  <input type="hidden" name="themeId" value="<?php echo esc_attr($first_theme_id)?>" />
  <div class="g-folio__theme-button">
    <button type="submit" class="g-folio__button g-folio__button-primary">
      Continue
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
            THEMES
          </h2>
        </div>
        <div class="g-folio__postbox-header-right">
          <div class="g-folio__postbox-close">
            <a type="submit" class="g-folio__postbox-icon gicon-close"
              href="/wp-admin/admin.php?page=<?php echo (isset($_REQUEST['from']) ? $_REQUEST['from'] : 'groove-overview')?>"></a>
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