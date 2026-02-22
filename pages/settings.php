<?php
namespace Groove\Pages;

use Groove\Pages\Page;
use Groove\Pages\Overview;
use Groove\Menu\Menu_Manager;
use Groove\Menu\Settings_Menu_Item;


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
    $tabs = [
      'account' => [
        'label' => esc_html__('Account', 'groove'),
      ],
      'privacy' => [
        'label' => esc_html__('Privacy', 'groove'),
      ]
    ];

    return $tabs;
  }

  public function __construct()
  {
    $this->add_post_action('save_groove_settings', 'handle_save');

    add_action('groove/menu/register', function (Menu_Manager $menu) {
      $menu->register(static::PAGE_ID, new Settings_Menu_Item($this));
    }, Overview::MENU_PRIORITY + 20);
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

    $tab = isset($_POST['tab_key']) ? sanitize_key(wp_unslash($_POST['tab_key'])) : 'account';

    if ('account' === $tab) {
      $invitation_code = isset($_POST['invitationCode']) ? sanitize_text_field(wp_unslash($_POST['invitationCode'])) : '';
      update_option('groove_invitation_code', $invitation_code);
    }
    else {
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

  public function display_account_fields()
  {
?>
<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
  <?php wp_nonce_field('groove_save_settings', 'groove_nonce'); ?>
  <input type="hidden" name="action" value="save_groove_settings" />
  <input type="hidden" name="tab_key" value="account" />

  <div class="g-folio__setting-account postbox-container postbox-transparent">
    <div class="postbox">
      <div class="g-folio__fields-header">
        <h3 class="g-folio__fields-title">Account</h3>
      </div>
      <div class="inside">
        <div class="g-row_field g-row_field-invitation-code">
          <label for="type">
            INVITATION CODE
          </label>
          <input type="text" name="invitationCode"
            value="<?php echo esc_attr(get_option('groove_invitation_code', '')); ?>" />
        </div>
        <div class="g-row_field">
          <label for="">Status</label>
          <div class="g-folio__setting-account-status">
            <?php echo get_option('groove_invitation_code') ? 'Active' : 'Missing Code'; ?>
          </div>
        </div>
      </div>
      <div class="g-folio__fields-footer">
        <button type="submit" class="g-folio__button g-folio__button-primary">SAVE CHANGES</button>
      </div>
    </div>
  </div>
</form>
<?php
  }

  public function display_privacy_fields()
  {
?>
<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
  <?php wp_nonce_field('groove_save_settings', 'groove_nonce'); ?>
  <input type="hidden" name="action" value="save_groove_settings" />
  <input type="hidden" name="tab_key" value="privacy" />

  <div class="g-folio__setting-privacy postbox-container postbox-transparent">
    <div class="postbox">
      <div class="inside">
        <div class="g-row_field">
          <label for="type">
            Privacy Policy
          </label>
          <div><a href="https://groove.studio/privacy" target="_blank">Read our Privacy Policy</a></div>
        </div>
        <div class="g-row_field">
          <label for="">Usage analytics</label>
          <div class="g-folio__setting-usage">
            <input type="checkbox" name="usage_analytics" value="1" <?php checked(get_option('groove_usage_analytics'
              , 0), 1); ?> /> <span>Share anonymous usage analytics with us to improve product experience</span>
          </div>
        </div>
      </div>
      <div class="g-folio__fields-footer">
        <button type="submit" class="g-folio__button g-folio__button-primary">SAVE CHANGES</button>
      </div>
    </div>
  </div>
</form>
<?php
  }

  public function display_tab_account()
  {
?>
<div class="g-folio__fields">
  <div class="g-folio__fields-left g-folio__fields-section">
    <?php $this->display_account_fields()?>
  </div>
</div>
<?php
  }

  public function display_tab_privacy()
  {
?>
<div class="g-folio__fields">
  <div class="g-folio__fields-left g-folio__fields-section">
    <?php $this->display_privacy_fields()?>
  </div>
</div>
<?php
  }

  public function display_content()
  {
    $tabs = $this->get_tabs();
    $tab_key = isset($_GET['tab_key']) ? sanitize_key(wp_unslash($_GET['tab_key'])) : 'account';
?>
<div class="g-top-bar-tabs-content">
  <?php
    foreach ($tabs as $tab_id => $tab) {
      $style = 'display: none';

      if ($tab_key === $tab_id) {
        $style = 'display: block';
      }

      $sanitized_tab_id = esc_attr($tab_id);

      echo '<div data-tab-content-id="' . $sanitized_tab_id . '" style="' . $style . '">';
      if ('account' === $tab_id) {
        $this->display_tab_account();
      }
      else {
        $this->display_tab_privacy();
      }
      echo "</div>";
    }
?>
</div>
<?php
  }

  public function display_tabs()
  {
    $tabs = $this->get_tabs();
    $tab_key = isset($_GET['tab_key']) ? sanitize_key(wp_unslash($_GET['tab_key'])) : 'account';

    $q = $this->parse_query();
  ?>
<div class="g-top-bar-tabs">
  <?php
    foreach ($tabs as $tab_id => $tab) {
      $active_class = '';

      if ($tab_key === $tab_id) {
        $active_class = ' g-folio__nav-tab-active';
      }

      $q['tab_key'] = $tab_id;

      $sanitized_tab_label = esc_html($tab['label']);
      $tab_url = add_query_arg($q, admin_url('admin.php'));
      echo '<a href="' . esc_url($tab_url) . '" data-tab-id="' . esc_attr($tab_id) . '" class="g-folio__nav-tab' . $active_class . ' nav-tab">' . $sanitized_tab_label . '</a>';
    }
?>
</div>
<?php
  }
}
?>
