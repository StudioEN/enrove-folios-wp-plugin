<?php
  namespace Groove\Pages;
  use Groove\List\Folio_List_Table;
  use Groove\Pages\Page;
  use Groove\Pages\Overview;
  use Groove\Menu\Menu_Manager;
  use Groove\Menu\All_Folios_Menu_Item;
  

  if ( ! defined( 'ABSPATH' ) ) {
  	exit; // Exit if accessed directly.
  }


  class All_Folios extends Page {
    const PAGE_ID = 'groove-all-folios';
    const POST_TYPE = 'groove_folio';

    public function get_title() {
      return 'All Folios';
    }

    public function create_tabs () {
      return array();
    }

    public function __construct() {
      $this->left_button_items = [array(
        'text' => esc_html__( 'Add New', 'groove' ),
        'type' => 'primary',
        'link' => admin_url( 'admin.php?page=groove-add-new&from=groove-all-folios' )
      )];

      add_action( 'groove/menu/register', function( Menu_Manager $menu ) {
				$menu->register( static::PAGE_ID, new All_Folios_Menu_Item($this) );
			}, Overview::MENU_PRIORITY + 20 );
    }

    public function display_content () {
      $post_type = static::POST_TYPE;
      $post_type_object = get_post_type_object($post_type);

      $table = new Folio_List_Table($this, $post_type);


      $table->prepare_items();
      $table->views();

      if ( $table->has_items() ) {
        $table->inline_edit();
      }
    ?>
<form id="pages-filter" method="get">
  <?php $table->search_box($post_type_object->labels->search_items, 'post' ); ?>

  <input type="hidden" name="post_status" class="post_status_page"
    value="<?php echo ! empty( $_REQUEST['post_status'] ) ? esc_attr( $_REQUEST['post_status'] ) : 'all'; ?>" />
  <input type="hidden" name="post_type" class="post_type_page" value="<?php echo $post_type; ?>" />

  <?php $table->display(); ?>
</form>
<?php
    }
  }
?>