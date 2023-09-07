<?php
  namespace Groove\Pages;
  use Groove\Pages\Page;
  use Groove\Pages\Overview;
  use Groove\Menu\Menu_Manager;
  use Groove\Menu\Settings_Menu_Item;
  

  if ( ! defined( 'ABSPATH' ) ) {
  	exit; // Exit if accessed directly.
  }

  class Settings extends Page {
    const PAGE_ID = 'groove-settings';

    public function get_title() {
      return 'Settings';
    }

    public function create_tabs () {
      $tabs = [
        'account' => [
          'label' => esc_html__( 'Account', 'groove' ),
        ],
        'privacy' => [
          'label' => esc_html__( 'Privacy', 'groove' ),
        ]
      ];

      return $tabs;
    }

    public function __construct() {
      add_action( 'groove/menu/register', function( Menu_Manager $menu ) {
				$menu->register(static::PAGE_ID, new Settings_Menu_Item($this));
			}, Overview::MENU_PRIORITY + 20 );
    }

    public function display_account_fields () {
      ?>
      <form action="/wp-admin/admin-post.php" method="post">
        <div class="g-folio__setting-account postbox-container postbox-transparent">
          <div class="postbox">
            <div class="g-folio__fields-header"><h3 class="g-folio__fields-title">Account</h3></div>
            <div class="inside">
              <div class="g-row_field g-row_field-invitation-code">
                <label for="type">
                  INVITATION CODE
                </label>
                <input type="text" name="invitationCode" value="120394" />
              </div>
              <div class="g-row_field">
                <label for="">Status</label>
                <div class="g-folio__setting-account-status">
                  Active
                </div>
              </div>
            </div>
            <div class="g-folio__fields-footer">
              <button type="submit" class="g-folio__button" name="action" value="groove_support">DISCONNECT</button>
            </div>
          </div>
        </div>
      </form>
    <?php
    }

    public function display_privacy_fields () {
    ?>
      <form action="/wp-admin/admin-post.php" method="post">
        <div class="g-folio__setting-privacy postbox-container postbox-transparent">
          <div class="postbox">
            <div class="inside">
              <div class="g-row_field">
                <label for="type">
                  Privacy Policy
                </label>
                <div><a class="">Read our Privacy Policy</a></div>
              </div>
              <div class="g-row_field">
                <label for="">Usage analytics</label>
                <div class="g-folio__setting-usage">
                  <input type="checkbox" name="" value="" /> <span>Share anonymous usage analytics with us to improve product experience</span>
                </div>
              </div>
            </div>
            <div class="g-folio__fields-footer">
              <button type="submit" class="g-folio__button g-folio__button-primary" name="action" value="groove_support">SAVE CHANGES</button>
            </div>
          </div>
        </div>
      </form>
    <?php
    }

    public function display_tab_account () {
    ?>
      <div class="g-folio__fields">
        <div class="g-folio__fields-left g-folio__fields-section">
          <?php $this->display_account_fields() ?>
        </div>
      </div>
    <?php
    }

    public function display_tab_privacy () {
    ?>
      <div class="g-folio__fields">
        <div class="g-folio__fields-left g-folio__fields-section">
          <?php $this->display_privacy_fields() ?>
        </div>
      </div>
    <?php
    }

    public function display_content () {
      $tabs = $this->get_tabs();
      $tab_key = isset($_REQUEST['tab_key']) ? $_REQUEST['tab_key'] : 'account';
    ?>
      <div class="g-top-bar-tabs-content">
    <?php    
      foreach ($tabs as $tab_id => $tab) {
        $style = 'display: none';

        if ( $tab_key === $tab_id ) {
          $style = 'display: block';
        }

        $sanitized_tab_id = esc_attr( $tab_id );

        echo '<div data-tab-content-id="'. $sanitized_tab_id. '" style="'. $style . '">';
        if ( 'account' === $tab_id ) {
          $this->display_tab_account();
        } else {
          $this->display_tab_privacy();
        }
        echo "</div>";
      }
      ?>
      </div>
      <?php
    }

    public function display_tabs () {
      $tabs = $this->get_tabs();
      $tab_key = isset($_REQUEST['tab_key']) ? $_REQUEST['tab_key'] : 'account';

      $q = $this->parse_query()
    ?>
      <div class="g-top-bar-tabs">
    <?php    
      foreach ($tabs as $tab_id => $tab) {
        $active_class = '';
  
        if ( $tab_key === $tab_id ) {
          $active_class = ' g-folio__nav-tab-active';
        }
  
        $sanitized_tab_label = esc_html( $tab['label'] );
        echo '<a data-tab-id="'. esc_attr( $tab_id ) .'" class="g-folio__nav-tab'. $active_class .' nav-tab">'. $sanitized_tab_label .'</a>';
      }
    ?>
      </div>
    <?php
    }
  }
?>