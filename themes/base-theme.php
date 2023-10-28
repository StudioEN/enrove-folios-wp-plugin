<?php
namespace Groove\Themes;
use Groove\Modules\Assets;

abstract class Base_Theme extends Assets {
  public $id;
  public $post_type;
  public $title;
  public $content;
  public $feature_image;
  public $author;
  public $page;
  public $pages;
  public $theme_id;
  public $theme_name;
  public $theme_cover_url;
  public $theme_logo_url;

  public function __construct() {
    $this->post_type = isset($_REQUEST['post_type']) ? $_REQUEST['post_type'] : 'groove_folio';
    $this->id = isset($_REQUEST['p']) ? $_REQUEST['p'] : '';

    add_action( 'wp_enqueue_scripts', [$this, 'ensure_script'] );
  }

  public function ensure_script () {
    if (is_preview()) {
      echo '<script>window.GROOVE_IS_PREVIEW = ' . (is_preview() ? 'true' : 'false') . '; </script>';  
      // echo '<script>var html = document.getElementByTagName(\'html\')[0];html && html.style.marginTop = 0;</script>';
      show_admin_bar(false);
    }

    wp_enqueue_style( 'groove', $this->get_css_assets_url( 'groove-main', null, 'default', true ), [], GROOVE_VERSION);	
    wp_enqueue_script( 'groove', $this->get_js_assets_url( 'groove-main' ), ['jquery'], GROOVE_VERSION, true);
  }

  function get_the_wp_query ($args) {
    $wp_query = new \WP_Query($args);
    return $wp_query;
  }

  function get_page_data () {
    $wp_query = $this->get_the_wp_query(array(
      'post__in' => array($this->id),
      'post_status' => array('publish', 'draft', 'pending'),
      'post_type' => $this->post_type,
    ));

    $page = $wp_query->post;
    
    $this->title = $page->post_title;
    $this->content = $page->post_content;
    $this->author = get_the_author_meta('user_login', $page->post_author);
    $this->feature_image = get_the_post_thumbnail($page->ID);
    $this->page = $page;

    return $page;
  }

  function get_pages_data ($id) {
    $args = array(
      'post_type' => 'groove_folio_page',
      'orderby' => 'menu_order',
      'order' => 'ASC',
      'post_status' => array('publish', 'draft', 'pending'),
      'meta_query' => array(
        array(
          'key' => 'folio_id',
          'value' => $id,
          'compare' => '=',
          'type' => 'NUMERIC'
        )
      )
    );
    
    $q = $this->get_the_wp_query($args);
    $this->pages = $q->posts;
  }

  function get_theme_data () {
    $meta = get_post_meta($this->id);
    $theme_id = $meta['theme_id'][0];

    $default = new Default_Themes();
	  $data = $default->get_themes();
	  $theme = $data[$theme_id];

    $this->theme_id = $theme_id;
    $this->theme_name = $theme['name'];
    $this->theme_cover_url = $theme['cover_url'];
    $this->theme_logo_url = $theme['logo_url'];
  }

  function get_data () {
    $this->get_page_data();
    $this->get_pages_data($this->id);
    $this->get_theme_data(); 


  }

  function display_theme () {
    $this->get_data();

    if ($this->post_type !== 'groove_folio') {
      return '<h1>' . esc_html__( 'No matching template found' ) . '</h1>';
    }
  }
}

?>