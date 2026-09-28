<?php
namespace Groove\Contents\Folio;

use Groove\Contents\BaseContent;

if (!defined('ABSPATH')) {
  exit;
}

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
      'name' => _x('Folios', 'post type general name', 'groove-folios'),
      'singular_name' => _x('Folio', 'post type singular name', 'groove-folios'),
      'menu_name' => _x('Folios', 'admin menu', 'groove-folios'),
      'all_items' => __('All Folios', 'groove-folios'),
      'add_new' => __('Add New', 'groove-folios'),
      'add_new_item' => __('Add New Folio', 'groove-folios'),
      'edit_item' => __('Edit Folio', 'groove-folios'),
      'new_item' => __('New Folio', 'groove-folios'),
      'view_item' => __('View Folio', 'groove-folios'),
      'search_items' => __('Search Folios', 'groove-folios'),
      'not_found' => __('No folios found', 'groove-folios'),
      'not_found_in_trash' => __('No folios found in trash', 'groove-folios'),
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

    $taxonomy_labels = array(
      'name' => _x('Collection Tags', 'taxonomy general name', 'groove-folios'),
      'singular_name' => _x('Collection Tag', 'taxonomy singular name', 'groove-folios'),
      'search_items' => __('Search Collection Tags', 'groove-folios'),
      'popular_items' => __('Popular Collection Tags', 'groove-folios'),
      'all_items' => __('All Collection Tags', 'groove-folios'),
      'edit_item' => __('Edit Collection Tag', 'groove-folios'),
      'update_item' => __('Update Collection Tag', 'groove-folios'),
      'add_new_item' => __('Add New Collection Tag', 'groove-folios'),
      'new_item_name' => __('New Collection Tag Name', 'groove-folios'),
      'separate_items_with_commas' => __('Separate collection tags with commas', 'groove-folios'),
      'add_or_remove_items' => __('Add or remove collection tags', 'groove-folios'),
      'choose_from_most_used' => __('Choose from the most used collection tags', 'groove-folios'),
      'not_found' => __('No collection tags found.', 'groove-folios'),
      'menu_name' => __('Collection Tags', 'groove-folios'),
    );

    register_taxonomy('groove_collection_tag', array('groove_folio'), array(
      'labels' => $taxonomy_labels,
      'public' => false,
      'publicly_queryable' => false,
      'show_ui' => true,
      'show_in_menu' => false,
      'show_admin_column' => false,
      'show_in_nav_menus' => false,
      'show_tagcloud' => false,
      'show_in_rest' => true,
      'hierarchical' => false,
      'capabilities' => array(
        'manage_terms' => 'edit_posts',
        'edit_terms' => 'edit_posts',
        'delete_terms' => 'edit_posts',
        'assign_terms' => 'edit_posts',
      ),
      'query_var' => true,
      'rewrite' => false,
    ));

    // Explicit, though core's default already asks for edit_post on the folio
    // before a REST request may write its meta.
    $meta_args = array(
      'show_in_rest' => true,
      'single' => true,
      'type' => 'string',
      'auth_callback' => static function ($allowed, $meta_key, $post_id) {
        return current_user_can('edit_post', (int) $post_id);
      },
    );

    foreach (array(
      'theme_id',
      'subtitle',
      'copyright',
      'permission',
      'fonts',
      'header_font',
      'body_font',
      'show_byline',
      'proposal_version',
      'proposal_status',
      'proposal_prepared_for',
      'proposal_prepared_by',
      'proposal_contact_email',
      'proposal_contact_name',
      'proposal_contact_role',
      'proposal_contact_phone',
      'proposal_contact_linkedin',
      'proposal_contacts',
      'proposal_client_name',
      'proposal_client_logo_url',
      'proposal_date',
      'proposal_show_in_page_nav',
      'proposal_color_scheme',
      'proposal_revision_log',
    ) as $meta_key) {
      register_post_meta('groove_folio', $meta_key, $meta_args);
    }
  }

  public function __construct()
  {
    add_action('init', [$this, 'create_posttype']);
  }
}
