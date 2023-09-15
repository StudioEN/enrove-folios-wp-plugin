<?php
  namespace Groove\Pages;
  use Groove\Fields\FolioFields;
  use Groove\Pages\Page;
  use Groove\Pages\Overview;
  use Groove\Menu\Menu_Manager;
  use Groove\Menu\Folio_Menu_Item;
  use Groove\List\Folio_Page_List_Table;

  

  if ( ! defined( 'ABSPATH' ) ) {
  	exit; // Exit if accessed directly.
  }

  class Folio extends Page {
    const PAGE_ID = 'groove-folio';
    const POST_TYPE = 'groove_folio_page';

    private $folio;
    private $fields;

    public function __construct() {
      $this->add_post_action('save_groove_folio_draft', 'save_folio_draft');
      $this->add_post_action('save_groove_folio', 'save_folio');
      
      $this->left_button_items = [array(
        'text' => 'Add Page',
        'type' => '',
        'link' => '/wp-admin/post-new.php?post_type=groove_folio_page&folio_id='. (isset($_REQUEST['folio_id']) ? $_REQUEST['folio_id'] : '')
      )];

      $this->right_button_items = [array(
        'text' => 'Preview',
        'type' => 'blank',
        'link' => '/?theme_id='. (isset($_REQUEST['theme_id']) ? $_REQUEST['theme_id'] : '') .'&post_type=groove_folio&preview=true&p=' . (isset($_REQUEST['folio_id']) ? $_REQUEST['folio_id'] : '')
      ), array(
        'text' => 'Save Draft',
        'type' => 'secondary',
        'action' => 'save_groove_folio_draft'
      ), array(
        'text' => 'Publish',
        'type' => 'secondary',
        'action' => 'save_groove_folio'
      )];

      add_action( 'save_post', [$this, 'save_post']);

      add_action( 'groove/menu/register', function( Menu_Manager $menu ) {
				$menu->register( static::PAGE_ID, new Folio_Menu_Item($this) );
			}, Overview::MENU_PRIORITY + 20 );

      add_action('current_screen', function () {
        $screen_id = $this->get_scrren_id();

        if ($screen_id == 'groove-folios_page_groove-folio') {
          if (!isset($_REQUEST['folio_id'])) {
            $this->redirect_to_all_folios();
          } else if (!$_REQUEST['folio_id']) {
            $this->redirect_to_all_folios();
          } else {
            $this->get_folio_fields();
          }
        }
      });
    }

    public function save_post ($post_id) {
      if ( 'groove_folio_page' === get_post_type( $post_id ) ) {
        $folio_id = get_post_meta( $post_id, 'folio_id', true );
        if (!$folio_id) {
          $folio_id = $_REQUEST['folio_id'];
        }
        update_post_meta( $post_id, 'folio_id', $folio_id );
      }
    }

    public function get_fields () {
      return $this->get_folio_fields();
    }

    public function get_folio_fields () {
      if ($this->folio == null) {
        if (isset($_REQUEST['folio_id'])) {
          $id = $_REQUEST['folio_id'];
          $args = array(
            'posts_per_page' => 1, // 获取一条数据
            'post__in' => array($id),
            'post_type' => 'groove_folio'
          );
          $wp_query = new \WP_Query($args);

          if (!$wp_query->have_posts()) {
            $this->redirect_to_all_folios();
          } else {
            $this->folio = $wp_query->post;
            $this->fields = new FolioFields($this->folio);
          }
        }
      }

      return $this->fields;
    }

    public function redirect_to_all_folios () {
      $redirect_url = '/wp-admin/admin.php?page=groove-all-folios';
      wp_redirect($redirect_url);
    }

    public function save_folio_draft () {
      $this->save_folio('draft');
    }

    public function save_folio ($post_status) {
      if (!isset($post_status)) {
        $post_status = 'publish';
      }

      if (!isset($_POST['folio_id']) || (isset($_POST['folio_id'])) && $_POST['folio_id'] == '') {
        echo json_encode(array(
          'code' => 400,
          'message' => 'Bad Request'
        ));
      } else {
        $fields = $this->get_fields();

        $id = isset($_POST['folio_id']) ? $_POST['folio_id'] : '';
        $post_title = isset($_POST['title']) ? $_POST['title'] : $fields->title;
        $post_author = isset($_POST['author']) ? $_POST['author'] : $fields->author;
        $subtitle = isset($_POST['subtitle']) ? $_POST['subtitle'] : $fields->subtitle;
        $password = isset($_POST['password']) ? $_POST['password'] : $fields->password;
        $copyright = isset($_POST['copyright']) ? $_POST['copyright'] : $fields->copyright;
        $permalink = isset($_POST['permalink']) ? $_POST['permalink'] : $fields->permalink;
        $permission = isset($_POST['permission']) ? $_POST['permission'] : $fields->permission;
        $fonts = isset($_POST['fonts']) ? $_POST['fonts'] : $fields->fonts;
        $theme_id = isset($_POST['theme_id']) ? $_POST['theme_id'] : $fields->theme_id;
    
        $fields = array(
          'ID'=> $id,
          'post_status' => $post_status,
          'post_password' => $password,
          'post_title' => $post_title,
          'post_author' => $post_author,
          'post_content' => '',
          'meta_input' => array(
            'theme_id' => $theme_id,
            // 'fonts' => $fonts,
            'subtitle' => $subtitle ? $subtitle : '',
            'copyright' => $copyright ? $copyright : '',
            'permalink' => $permalink ? $permalink : '',
            'permission' => $permission ? $permission : 2
          )
        );
  
        $folio_id = wp_update_post($fields);
  
        if (!is_wp_error($folio_id)) {
          echo json_encode(array(
            'code' => 0,
            'message' => 'success',
            'data' => array(
              'folio_id' => $folio_id
            )
          ));
        } else {
          echo json_encode(array(
            'code' => 500,
            'message' => 'Something wrong.'
          ));
        }
      }

    }

    public function get_title() {
      return 'Folio';
    }

    public function create_tabs () {
      $tabs = [
        'setup' => [
          'label' => esc_html__( 'Setup', 'groove' ),
        ],
        'pages' => [
          'label' => esc_html__( 'Pages', 'groove' ),
        ]
      ];

      return $tabs;
    }

    public function display_content () {
      $tabs = $this->get_tabs();
      $tab_key = isset($_REQUEST['tab_key']) ? $_REQUEST['tab_key'] : 'setup'
    ?>
      <div class="g-top-bar-tabs-content">
    <?php    
      $style = 'display: none';
      foreach ($tabs as $tab_id => $tab) {

        if ( $tab_key === $tab_id ) {
          $style = 'display: block';
        }

        $sanitized_tab_id = esc_attr( $tab_id );

        echo '<div data-tab-content-id="'. $sanitized_tab_id. '" style="'. $style . '">';

        if ( 'setup' === $tab_id ) {
          $this->display_tab_fields();
        } else {
          $this->display_tab_pages();
        }
        echo "</div>";
      }
      ?>
      </div>
      <?php
    }

    public function display_tab_fields () {
      $fields = $this->get_folio_fields();
    ?>
      <div class="g-folio__fields">
        <input hidden name="folio_id" value="<?php echo $fields->ID ?>" />
        <div class="g-folio__fields-left g-folio__fields-section">
          <?php $this->display_essentials() ?>
        </div>

        <div class="g-folio__fields-right g-folio__fields-section">
          <?php $this->display_customization() ?>
          <?php $this->display_publishing() ?>
        </div>
      </div>
    <?php
    }

    public function display_customization () {
      $fields = $this->get_fields();

      // $fonts = $fields->fonts;
    ?>
      <div class="postbox-container g-folio__postbox">
        <div class="postbox">
          <div class="g-folio__fields-header"><h3 class="g-folio__fields-title">Customization</h3></div>
          <div class="inside">
            <div class="g-row_field">
              <label for="font">PRIMARY FONT</label>
              <select name="fonts">'
                <option value="Arial">Arial</option>
                <option value="PingFong">PingFong</option>
                </select><br>
            </div>
          </div>
        </div>
      </div>
    <?php
    }

    public function display_publishing () {
      $fields = $this->get_fields();

      $permission = $fields->permission;
      $is_allowed_download = $permission == '2';

      $permalink = isset($fields->permalink) ? $fields->permalink : '';
    ?>
      <div class="postbox-container g-folio__postbox">
        <div class="postbox">
          <div class="g-folio__fields-header">
            <h3 class="g-folio__fields-title">Publishing</h3>
          </div>
          <div class="inside">
            <div class="g-row_field">
              <label for="pdf">PDF DOWNLOADS</label>
              <input type="checkbox" name="permission" <?php echo checked($is_allowed_download, 1, false) ?> />
            </div>
            <div class="g-row_field">
              <label for="pdf">PASSWORD</label>
              <input type="password" name="password" value="<?php echo $fields->password ?>" />
            </div>
            <div class="g-row_field">
              <label for="pdf">PERMALINK</label>
              <input type="text" name="permalink" value="<?php echo $permalink ?>" />
            </div>
          </div>
        </div>
      </div>
    <?php
    }

    public function display_essentials () {
      $fields = $this->get_fields();

      $selected_author = get_post_field('post_author', $fields->ID);

      $subtitle = isset($fields->subtitle) ? $fields->subtitle : '';
      $copyright = isset($fields->copyright) ? $fields->copyright : '';
    ?>
      <div class="postbox-container g-folio__postbox">
        <div class="postbox">
          <div class="g-folio__fields-header"><h3 class="g-folio__fields-title">Essentials</h3></div>
          <div class="inside">
            <div class="g-row_field">
              <label for="custom_field">TITLE</label>
              <input type="text" name="title" value="<?php echo $fields->title ?>" />
            </div>
            <div class="g-row_field">
              <label for="custom_field">SUBTITLE</label>
              <input placeholder="Option" type="text" name="subtitle" value="<?php echo $subtitle ?>" />
            </div>
            <!-- <div class="g-row_field">
              <label for="custom_field">FEATURE</label>
              <input type="text" name="feature" id="feature" value="" />
            </div> -->
            <div class="g-row_field">
              <label for="authro">AUTHOR</label>
              <?php wp_dropdown_users(array('name' => 'author', 'selected' => $selected_author)) ?>
            </div>
            <div class="g-row_field">
              <label for="copyright">COPYRIGHT</label>
              <input placeholder="Option" type="text" name="copyright" value="<?php echo $copyright ?>" />
            </div>
          </div>
        </div>
      </div>
    <?php
    }

    public function display_tabs () {
      $tabs = $this->get_tabs();
      $tab_key = isset($_REQUEST['tab_key']) ? $_REQUEST['tab_key'] : 'setup';

      $q = $this->parse_query()
    ?>
      <div class="g-top-bar-tabs">
    <?php    
      foreach ($tabs as $tab_id => $tab) {
        $active_class = '';
  
        if ( $tab_key === $tab_id ) {
          $active_class = ' g-folio__nav-tab-active';
        }

        $q['tab_key'] = $tab_id;
  
        $sanitized_tab_label = esc_html( $tab['label'] );
        echo '<a data-tab-id="'. esc_attr( $tab_id ) .'" class="g-folio__nav-tab'. $active_class .' nav-tab">'. $sanitized_tab_label .'</a>';
      }
    ?>
      </div>
    <?php
    }

    public function display_tab_pages () {
      $post_type = static::POST_TYPE;
      $post_type_object = get_post_type_object($post_type);

      $table = new Folio_Page_List_Table($this, $post_type);

      $table->prepare_items();
      $table->views();
    ?>
      <form id="pages-filter" method="get">
        <?php $table->search_box($post_type_object->labels->search_items, 'post' ); ?>

        <input type="hidden" name="post_status" class="post_status_page" value="<?php echo ! empty( $_REQUEST['post_status'] ) ? esc_attr( $_REQUEST['post_status'] ) : 'all'; ?>" />
        <input type="hidden" name="post_type" class="post_type_page" value="<?php echo $post_type; ?>" />

        <?php $table->display(); ?>
      </form>
    <?php
    }

    public function display_page () {
      $folio = $this->get_folio_fields();
    ?>
      <form
        action="/wp-admin/admin-post.php"
        method="post"
      >
        <?php parent::display_page() ?>
      </form>
    <?php
    }
  }
?>