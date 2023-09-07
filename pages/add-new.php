<?php
  namespace Groove\Pages;
  use Groove\Pages\Page;
  use Groove\Pages\Overview;
  use Groove\Menu\Menu_Manager;
  use Groove\Menu\Add_New_Menu_Item;
  

  if ( ! defined( 'ABSPATH' ) ) {
  	exit; // Exit if accessed directly.
  }


  class Add_New extends Page {
    const PAGE_ID = 'groove-add-new';
    const POST_TYPE = 'groove_folio_page';

    public function __construct() {
      $this->add_post_action('groove_create_folio', 'create_folio');

      add_action( 'groove/menu/register', function( Menu_Manager $menu ) {
				$menu->register( static::PAGE_ID, new Add_New_Menu_Item($this) );
			}, Overview::MENU_PRIORITY + 20 );
    }

    public function create_folio () {
      
      if (isset($_POST['action'])) {
        if ($_POST['action'] == 'groove_create_folio') {
          $theme_id = sanitize_text_field($_POST['themeId']);

          $fields = array(
            'post_type' => 'groove_folio', 
            'post_status' => 'draft',
            'post_title' => 'A new folio',
            'post_content' => '',
            'meta_input' => array(
              'theme_id' => $theme_id,
              'subtitle' => '',
              'permission' => '2', // 2 allowed 4 not allowed
              'copyright' => '版权信息',
              'fonts' => ['Arial', 'Helvetica'],
              'permalink' => '',
            ),
          );
    
          $folio_id = wp_insert_post($fields);
    
          if (!is_wp_error($folio_id)) {
            $redirect_url = '/wp-admin/admin.php?page=groove-folio&folio_id=' . $folio_id;
            wp_redirect($redirect_url);
          } else {
            echo 'Something wrong: ' . $folio_id->get_error_message();
          }
        }
      }
    }

    public function get_title() {
      return 'Add New';
    }

    public function create_tabs () {
      return array();
    }

    public function display__themes () {
      $themes = [
        array(
          'ID' => 'theme-1',
          'coverURL' => $this->get_images_assets_url('theme-cover-01.png'),
          'name' => 'Folio Starter'
        ),
        array(
          'ID' => 'theme-2',
          'coverURL' => $this->get_images_assets_url('theme-cover-02.png'),
          'name' => 'Groove eBook'
        ),
      ];

      echo '<script>window.GROOVE_THEME_ID = "'. $themes[0]['ID'] .'";</script>'

      ?>
        <p class="g-folio__themes-desc">Choose a Theme to get started</p>
        <form action="/wp-admin/admin-post.php" method="post">
          <div class="g-folio__themes">
            <?php
              foreach ($themes as $theme) {
                echo '<div class="g-folio__theme" data-theme-id="'. $theme['ID'] .'">';
                echo '<div class="g-folio__theme-cover"><img src="'. $theme['coverURL'] .'" /></div>';
                echo '<div class="g-folio__theme-name">'. $theme['name'] .'</div>';
                echo '</div>';
              }
            ?>
          </div>
          <input data-field-id="theme-id" hidden name="themeId" value="<?php echo $themes[0]['ID'] ?>" />
          <div class="g-folio__theme-button">
            <button type="submit" value="groove_create_folio" name="action" class="g-folio__button g-folio__button-primary">
              Continue
            </button>
          </div>
        </form>
      <?php
    }

    public function display_content () {
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
                  <a type="submit" class="g-folio__postbox-icon gicon-close" href="/wp-admin/admin.php?page=<?php echo (isset($_REQUEST['from']) ? $_REQUEST['from'] : 'groove-overview') ?>"></a>
                </div>
              </div>
            </div>
              <div class="g-folio__postbox-body">
                <?php $this->display__themes() ?>
              </div>
            </div>
          </div>
        </form>
      </div>
    <?php
    }
  }
?>