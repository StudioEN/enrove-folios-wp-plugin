<?php
namespace Groove\Contents\Folio;
use Groove\Contents\BaseContent;

class Content extends BaseContent {

  public function get_key() {
		return 'folio';
	}

  public function get_name() {
		return 'Folio';
	}

  public function create_posttype () {
    $labels = array(
      'name' => 'Folios',
      'singular_name' => 'Folio Page',
      'menu_name' => 'Folio Page',
      'all_items' => 'All Folio Page',
      'add_new' => 'Add New',
      'add_new_item' => 'Add New Folio Page',
      'edit_item' => 'Edit Folio Page',
      'new_item' => 'New Folio Page',
      'view_item' => 'View Folio Page',
      'search_items' => 'Search Folio Page',
      'not_found' => 'No folio pages found',
      'not_found_in_trash' => 'No folio pages found in trash',
    );

    $args = array(
      'labels' => $labels,
      'public' => true,
      'exclude_from_search' => false,
      'publicly_queryable' => true,
      'show_ui' => true,
      'show_in_rest' => true,
      'show_in_menu' => false,
      'show_in_nav_menus' => false,

      'has_archive' => true,
      'supports' => array( 'title', 'editor', 'password','custom-fields'),
      'rewrite' => array('slug' => 'folio'),
      'menu_position' => 1, 
      'menu_order' => 1, 
    );

    register_post_type('groove_'. $this->get_key(), $args);
  }

  public function __construct() {
    add_action('init', [$this, 'create_posttype']);
  }
}

?>