<?php
namespace Groove\Contents\FolioPage;
use Groove\Contents\BaseContent;

if (!defined('ABSPATH')) {
  exit;
}

class Content extends BaseContent
{

  public function get_key()
  {
    return 'folio_page';
  }

  public function get_name()
  {
    return 'Folio Page';
  }

  public function create_posttype()
  {
    $labels = array(
      'name' => _x('Folio Pages', 'post type general name', 'groove-folios'),
      'singular_name' => _x('Folio Page', 'post type singular name', 'groove-folios'),
      'menu_name' => _x('Folio Pages', 'admin menu', 'groove-folios'),
      'all_items' => __('All Folio Pages', 'groove-folios'),
      'add_new' => __('Add New', 'groove-folios'),
      'add_new_item' => __('Add New Folio Page', 'groove-folios'),
      'edit_item' => __('Edit Folio Page', 'groove-folios'),
      'new_item' => __('New Folio Page', 'groove-folios'),
      'view_item' => __('View Folio Page', 'groove-folios'),
      'search_items' => __('Search Folio Pages', 'groove-folios'),
      'not_found' => __('No folio pages found', 'groove-folios'),
      'not_found_in_trash' => __('No folio pages found in trash', 'groove-folios'),
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
      // page-attributes gives us menu_order for drag-and-drop ordering within a folio.
      'supports' => array('title', 'editor', 'thumbnail', 'custom-fields', 'excerpt', 'page-attributes', 'revisions'),
      // Slug was 'folio pages' (with a space) — fixed to 'folio-page'.
      'rewrite' => array('slug' => 'folio-page', 'with_front' => false),
      'capability_type' => 'post',
      'map_meta_cap' => true,
      'menu_position' => 1,
    );

    register_post_type('groove_' . $this->get_key(), $args);

    register_post_meta('groove_folio_page', 'folio_id', array(
      'show_in_rest' => true,
      'single' => true,
      'type' => 'integer',
      'auth_callback' => static function ($allowed, $meta_key, $post_id) {
        return current_user_can('edit_post', (int) $post_id);
      },
    ));
  }

  public function __construct()
  {
    add_action('init', [$this, 'create_posttype']);
  }
}
?>