<?php
namespace Groove\Pages;

use Groove\Pages\Page;


if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

class Overview extends Page
{
  const PAGE_ID = 'groove-overview';
  const MENU_PRIORITY = 10;

  public function get_title()
  {
    return 'Overview';
  }

  public function create_tabs()
  {
    return array();
  }

  public function register_admin_menu()
  {
    add_menu_page(
      esc_html__('Groove', 'groove'),
      esc_html__('Groove Folios', 'groove'),
      'manage_options',
      self::PAGE_ID,
    [$this, 'display_page'],
      $this->get_images_assets_url('logo.svg'),
      '58.5'
    );

    add_action('admin_menu', [$this, 'change_overview_menu_name'], 200);
  }

  public function change_overview_menu_name()
  {
    global $submenu;

    $menu_slug = 'groove-overview';
    $new_label = esc_html__('Overview', 'groove');

    if (isset($submenu[$menu_slug])) {
      $submenu[$menu_slug][0][0] = $new_label;
    }
  }

  public function __construct()
  {
    add_action('admin_menu', [$this, 'register_admin_menu'], 20);
  }

  public function display_glance()
  {
?>
<div class="g-folio__glance postbox-container g-folio__postbox">
  <div class="postbox">
    <div class="g-folio__fields-header">
      <h3 class="g-folio__fields-title">At a glance</h3>
    </div>
    <div class="inside">
      <?php
    $folio_count = wp_count_posts('groove_folio')->publish;
    $page_count = wp_count_posts('groove_folio_page')->publish;
?>
      <div class="g-row_field">
        <i class="gicon gicon-folio"></i>
        <label>
          <a href="/wp-admin/admin.php?page=groove-all-folios">
            <?php printf(_n('%s Folio', '%s Folios', $folio_count, 'groove'), number_format_i18n($folio_count)); ?>
          </a>
        </label>
      </div>
      <div class="g-row_field">
        <i class="gicon gicon-page"></i>
        <label>
          <a>
            <?php printf(_n('%s Page', '%s Pages', $page_count, 'groove'), number_format_i18n($page_count)); ?>
          </a>
        </label>
      </div>
      <div class="g-row_field">
        <i class="gicon gicon-collection"></i>
        <label>
          <a>
            <?php printf(_n('%s Collection', '%s Collections', $folio_count, 'groove'), number_format_i18n($folio_count)); ?>
          </a>
        </label>
      </div>
    </div>
    <div class="g-folio__fields-footer">
      <a class="g-folio__button g-folio__button-primary"
        href="/wp-admin/admin.php?page=groove-add-new&from=groove-overview">Add New Folio</a>
    </div>
  </div>
</div>
<?php
  }

  public function display_activity()
  {
?>
<div class="g-folio__activity postbox-container g-folio__postbox">
  <div class="postbox">
    <div class="g-folio__fields-header">
      <h3 class="g-folio__fields-title">Activity</h3>
    </div>
    <div class="inside">
      <div class="g-row_field">
        <label>Recently updated</label>
        <div class="g-folio__activities">
          <?php
    $recent_posts = get_posts(array(
      'post_type' => 'groove_folio',
      'post_status' => 'any',
      'posts_per_page' => 3,
      'orderby' => 'modified',
      'order' => 'DESC'
    ));

    if (!empty($recent_posts)) {
      foreach ($recent_posts as $post) {
        $date = get_the_modified_date('M jS, g:i a', $post);
        $link = admin_url('admin.php?page=groove-folio&folio_id=' . $post->ID);
?>
          <div class="g-row__activity">
            <label>
              <?php echo esc_html($date); ?>
            </label>
            <a href="<?php echo esc_url($link); ?>">
              <?php echo esc_html(get_the_title($post)); ?>
            </a>
          </div>
          <?php
      }
    }
    else {
      echo '<p>' . esc_html__('No recent activity found.', 'groove') . '</p>';
    }
?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
  }

  public function display_content()
  {
?>
<div class="g-folio__fields">
  <div class="g-folio__fields-left g-folio__fields-section">
    <?php $this->display_glance()?>
  </div>

  <div class="g-folio__fields-right g-folio__fields-section">
    <?php $this->display_activity()?>
  </div>
</div>
<?php
  }
}
?>