<?php
  namespace Groove\Pages;
  use Groove\Fields\FolioFields;
  use Groove\Pages\Page;
  use Groove\Pages\Overview;
  use Groove\Menu\Menu_Manager;
  use Groove\Menu\Folio_Menu_Item;
  use Groove\List\Folio_Page_List_Table;
  use Groove\Themes\Default_Themes;
  use Groove\Utils\Utils;

  

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
        'link' => '/wp-admin/post-new.php?post_type=groove_folio_page&folio_id='. Utils::get_groove_post_id()
      )];

      $this->right_button_items = [array(
        'text' => 'Preview',
        'type' => 'blank',
        'link' => Utils::get_folio_permalink_by_id(Utils::get_groove_post_id())
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

      add_action( 'wp_ajax_folio_inline_save', [$this, 'inline_save']);

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

    public function inline_save () {
      global $mode;

      check_ajax_referer( 'inlineeditnonce', '_inline_edit' );

      if ( ! isset( $_POST['post_ID'] ) || ! (int) $_POST['post_ID'] ) {
        wp_die();
      }

      $post_id = (int) $_POST['post_ID'];
      

      if ( 'page' === $_POST['post_type'] ) {
        if ( ! current_user_can( 'edit_page', $post_id ) ) {
          wp_die( __( 'Sorry, you are not allowed to edit this page.' ) );
        }
      } else {
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
          wp_die( __( 'Sorry, you are not allowed to edit this post.' ) );
        }
      }

      $last = wp_check_post_lock( $post_id );
      if ( $last ) {
        $last_user      = get_userdata( $last );
        $last_user_name = $last_user ? $last_user->display_name : __( 'Someone' );

        /* translators: %s: User's display name. */
        $msg_template = __( 'Saving is disabled: %s is currently editing this post.' );

        if ( 'page' === $_POST['post_type'] ) {
          /* translators: %s: User's display name. */
          $msg_template = __( 'Saving is disabled: %s is currently editing this page.' );
        }

        printf( $msg_template, esc_html( $last_user_name ) );
        wp_die();
      }

      $data = &$_POST;
      $post = get_post( $post_id, ARRAY_A );

      // Since it's coming from the database.
      $post = wp_slash( $post );

      $data['content'] = $post['post_content'];
      $data['excerpt'] = $post['post_excerpt'];

      // Rename.
      $data['user_ID'] = get_current_user_id();

      if ( isset( $data['post_parent'] ) ) {
        $data['parent_id'] = $data['post_parent'];
      }

      // Status.
      if ( isset( $data['keep_private'] ) && 'private' === $data['keep_private'] ) {
        $data['visibility']  = 'private';
        $data['post_status'] = 'private';
      } else {
        $data['post_status'] = $data['_status'];
      }

      if ( empty( $data['comment_status'] ) ) {
        $data['comment_status'] = 'closed';
      }

      if ( empty( $data['ping_status'] ) ) {
        $data['ping_status'] = 'closed';
      }

      // Exclude terms from taxonomies that are not supposed to appear in Quick Edit.
      if ( ! empty( $data['tax_input'] ) ) {
        foreach ( $data['tax_input'] as $taxonomy => $terms ) {
          $tax_object = get_taxonomy( $taxonomy );
          /** This filter is documented in wp-admin/includes/class-wp-posts-list-table.php */
          if ( ! apply_filters( 'quick_edit_show_taxonomy', $tax_object->show_in_quick_edit, $taxonomy, $post['post_type'] ) ) {
            unset( $data['tax_input'][ $taxonomy ] );
          }
        }
      }

      // Hack: wp_unique_post_slug() doesn't work for drafts, so we will fake that our post is published.
      if ( ! empty( $data['post_name'] ) && in_array( $post['post_status'], array( 'draft', 'pending' ), true ) ) {
        $post['post_status'] = 'publish';
        $data['post_name']   = wp_unique_post_slug( $data['post_name'], $post['ID'], $post['post_status'], $post['post_type'], $post['post_parent'] );
      }

      // Update the post.
      edit_post();

      
      $post_type = static::POST_TYPE;
      $table = new Folio_Page_List_Table($this, $post_type);

      $mode = 'excerpt' === $_POST['post_view'] ? 'excerpt' : 'list';

      $level = 0;
      if ( is_post_type_hierarchical( $table->screen->post_type ) ) {
        $request_post = array( get_post( $_POST['post_ID'] ) );
        $parent       = $request_post[0]->post_parent;

        while ( $parent > 0 ) {
          $parent_post = get_post( $parent );
          $parent      = $parent_post->post_parent;
          $level++;
        }
      }

      $table->ajax_rows( array( get_post( $_POST['post_ID'] ) ), $level );

      wp_die();
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
        
        $feature_image_id = isset($_POST['feature_image_id']) ? $_POST['feature_image_id'] : null;
    
        $fields = array(
          'ID'=> $id,
          'post_status' => $post_status,
          'post_password' => $password,
          'post_title' => $post_title,
          'post_author' => $post_author,
          'post_content' => '',
          'post_name' => sanitize_title($post_title),
          'meta_input' => array(
            'theme_id' => $theme_id,
            // 'fonts' => $fonts,
            'subtitle' => $subtitle ? $subtitle : '',
            'copyright' => $copyright ? $copyright : '',
            'permission' => $permission ? $permission : 2
          )
        );

        
        $folio_id = wp_update_post($fields);

        if ($feature_image_id == null) {
          delete_post_thumbnail($id);
        } else {
          set_post_thumbnail($id, $feature_image_id);
        }

  
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
      return $this->folio->post_title;
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
      $tab_key = isset($_REQUEST['tab_key']) ? $_REQUEST['tab_key'] : 'setup';
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
              <input type="password" placeholder="Enter your password" name="password" value="<?php echo $fields->password ?>" />
            </div>
            <div class="g-row_field">
              <label for="pdf">PERMALINK</label>
              <input disabled type="text" name="permalink" value="<?php echo $fields->name ?>" />
            </div>
          </div>
        </div>
      </div>
    <?php
    }

    public function display_essentials () {
      $fields = $this->get_fields();
      $selected_author = get_post_field('post_author', $fields->ID);

      $feature_image = isset($fields->feature_image) ? $fields->feature_image : '';

      $subtitle = isset($fields->subtitle) ? $fields->subtitle : '';
      $copyright = isset($fields->copyright) ? $fields->copyright : '';

      $default_theme = new Default_Themes();
      $themes = $default_theme->get_themes();
      $theme = $themes[$fields->theme_id];

      $theme_url = $theme["cover_url"];
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
            <div class="g-row_field">
              <label for="custom_field">FEATURE IMAGE</label>
              <input value="<?= $feature_image->ID ?>" type="hidden" name="feature_image_id" id="media_id" placeholder="Feature image">
              <img id="feature-preview" class="g-row_field-image" src="<?= $feature_image == null ? $theme_url : $feature_image->guid ?>" />
              <div class="g-row_field-actions">
                <div id="feature-image" class="g-folio__button">Replace image</div>
                <a data-default-url="<?= $theme_url ?>" id="use-default-image">Use default</a>
              </div>
            </div>
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
        echo '<a href="/wp-admin/admin.php?'. http_build_query($q) .'" data-tab-id="'. esc_attr( $tab_id ) .'" class="g-folio__nav-tab'. $active_class .' nav-tab">'. $sanitized_tab_label .'</a>';
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

      if ( $table->has_items() ) {
        $table->inline_edit();
      }
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