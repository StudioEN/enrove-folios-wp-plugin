<?php
  namespace Groove\Pages;
  use Groove\Pages\Page;
  use Groove\Pages\Overview;
  use Groove\Menu\Menu_Manager;
  use Groove\Menu\Support_Menu_Item;
  

  if ( ! defined( 'ABSPATH' ) ) {
  	exit; // Exit if accessed directly.
  }

  class Support extends Page {
    const PAGE_ID = 'groove-support';

    private $supportTypes;

    public function __construct() {
      $this->add_post_action('groove_get_support', 'get_support');

      $this->supportTypes = array(
        array(
          'value' => 0,
          'label' => 'Get a support'
        ),
        array(
          'value' => 1,
          'label' => 'Get a help'
        ),
      );

      add_action( 'groove/menu/register', function( Menu_Manager $menu ) {
				$menu->register( static::PAGE_ID, new Support_Menu_Item( $this ) );
			}, Overview::MENU_PRIORITY + 20 );
    }

    public function get_support () {
      if ($_POST['action'] == 'groove_get_support') {
        $to = '';
        $subject = '';
        $message = '';
        $headers = array();

        $result = wp_mail($to, $subject, $message, $headers);

        if ($result) {

        } else {

        }
      }
    }

    public function get_title() {
      return 'Support';
    }

    public function create_tabs () {
      return array();
    }


    public function display_support_fields () {
    ?>
      <form action="/wp-admin/admin-post.php" method="post">
        <div class="g-folio__support postbox-container">
          <div class="postbox">
            <div class="g-folio__fields-header">
              <h3 class="g-folio__fields-title">Send us a message</h3>
            </div>
            <div class="inside">
              <div class="g-row_field">
                <label for="type">I'D LIKE TO</label>
                <select name="type">
                  <?php
                    $supportTypes = $this->supportTypes;

                    foreach ($supportTypes as $type) {
                      ?>
                        <option value="<?php echo $type['value']; ?>"><?php echo $type['label'] ?></option>
                      <?php
                    }
                  ?>
                </select>
              </div>
              <div class="g-row_field">
                <label for="email">EMAIL*</label>
                <input type="text" name="email" value="" placeholder="YOUR EMAIL" />
              </div>
              <div class="g-row_field">
                <label for="title">TITLE</label>
                <input type="text" name="title" value="" placeholder="Optional" />
              </div>

              <div class="g-row_field">
                <label for="message">MESSAGE</label>
                <textarea type="text" name="message" value="" placeholder="Optional"></textarea>
              </div>
            </div>
            <div class="g-folio__fields-footer">
              <button type="submit" class="g-folio__button g-folio__button-primary" name="action" value="groove_get_support">SEND</button>
            </div>
          </div>
        </div>
      </form>
    <?php
    }

    public function display_support () {
    ?>
      <div class="g-folio__fields">
        <div class="g-folio__fields-left g-folio__fields-section">
          <?php $this->display_support_fields() ?>
        </div>
      </div>
    <?php
    }

    public function display_content () {
      $this->display_support();
    }
  }
?>