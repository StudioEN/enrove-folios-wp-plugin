<?php
namespace Groove\Contents\Folio;

use Groove\Contents\BaseContent;

class Content extends BaseContent
{

  public function get_key()
  {
    return 'folio';
  }

  public function get_name()
  {
    return 'Folio';
  }

  public function create_posttype()
  {
    $labels = array(
      'name' => _x('Folios', 'post type general name', 'groove'),
      'singular_name' => _x('Folio', 'post type singular name', 'groove'),
      'menu_name' => _x('Folios', 'admin menu', 'groove'),
      'all_items' => __('All Folios', 'groove'),
      'add_new' => __('Add New', 'groove'),
      'add_new_item' => __('Add New Folio', 'groove'),
      'edit_item' => __('Edit Folio', 'groove'),
      'new_item' => __('New Folio', 'groove'),
      'view_item' => __('View Folio', 'groove'),
      'search_items' => __('Search Folios', 'groove'),
      'not_found' => __('No folios found', 'groove'),
      'not_found_in_trash' => __('No folios found in trash', 'groove'),
    );

    $args = array(
      'labels' => $labels,
      'public' => true,
      'exclude_from_search' => true,
      'publicly_queryable' => true,
      'show_ui' => true,
      'show_in_rest' => true,
      'show_in_menu' => false,
      'show_in_nav_menus' => false,
      'has_archive' => false,
      'supports' => array('title', 'editor', 'thumbnail', 'custom-fields', 'page-attributes'),
      'rewrite' => array('slug' => 'folio', 'with_front' => false),
      'capability_type' => 'post',
      'map_meta_cap' => true,
      'menu_position' => 1,
    );

    register_post_type('groove_' . $this->get_key(), $args);
  }

  public function __construct()
  {
    add_action('init', [$this, 'create_posttype']);
  }
}