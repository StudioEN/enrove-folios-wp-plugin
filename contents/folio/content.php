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

    register_post_meta('groove_folio', 'theme_id', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'subtitle', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'copyright', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'permission', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'fonts', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'header_font', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'body_font', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'show_byline', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'proposal_version', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'proposal_status', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'proposal_prepared_for', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'proposal_prepared_by', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'proposal_contact_email', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'proposal_contact_name', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'proposal_contact_role', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'proposal_contact_phone', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'proposal_contact_linkedin', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'proposal_contacts', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'proposal_client_name', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'proposal_client_logo_url', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'proposal_date', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'proposal_show_in_page_nav', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'proposal_color_scheme', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
    register_post_meta('groove_folio', 'proposal_revision_log', array('show_in_rest' => true, 'single' => true, 'type' => 'string'));
  }

  public function __construct()
  {
    add_action('init', [$this, 'create_posttype']);
  }
}
