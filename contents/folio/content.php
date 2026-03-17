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
