<?php
namespace Enrove\Contents\Folio;

use Enrove\Contents\BaseContent;

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
      'name' => _x('Folios', 'post type general name', 'enrove-folios'),
      'singular_name' => _x('Folio', 'post type singular name', 'enrove-folios'),
      'menu_name' => _x('Folios', 'admin menu', 'enrove-folios'),
      'all_items' => __('All Folios', 'enrove-folios'),
      'add_new' => __('Add New', 'enrove-folios'),
      'add_new_item' => __('Add New Folio', 'enrove-folios'),
      'edit_item' => __('Edit Folio', 'enrove-folios'),
      'new_item' => __('New Folio', 'enrove-folios'),
      'view_item' => __('View Folio', 'enrove-folios'),
      'search_items' => __('Search Folios', 'enrove-folios'),
      'not_found' => __('No folios found', 'enrove-folios'),
      'not_found_in_trash' => __('No folios found in trash', 'enrove-folios'),
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

    register_post_type('enrove_' . $this->get_key(), $args);

    $taxonomy_labels = array(
      'name' => _x('Collection Tags', 'taxonomy general name', 'enrove-folios'),
      'singular_name' => _x('Collection Tag', 'taxonomy singular name', 'enrove-folios'),
      'search_items' => __('Search Collection Tags', 'enrove-folios'),
      'popular_items' => __('Popular Collection Tags', 'enrove-folios'),
      'all_items' => __('All Collection Tags', 'enrove-folios'),
      'edit_item' => __('Edit Collection Tag', 'enrove-folios'),
      'update_item' => __('Update Collection Tag', 'enrove-folios'),
      'add_new_item' => __('Add New Collection Tag', 'enrove-folios'),
      'new_item_name' => __('New Collection Tag Name', 'enrove-folios'),
      'separate_items_with_commas' => __('Separate collection tags with commas', 'enrove-folios'),
      'add_or_remove_items' => __('Add or remove collection tags', 'enrove-folios'),
      'choose_from_most_used' => __('Choose from the most used collection tags', 'enrove-folios'),
      'not_found' => __('No collection tags found.', 'enrove-folios'),
      'menu_name' => __('Collection Tags', 'enrove-folios'),
    );

    register_taxonomy('enrove_collection_tag', array('enrove_folio'), array(
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
      // As core's post tags: anyone who writes may tag a folio, which
      // creates a new tag as it goes, but renaming and deleting tags (the
      // REST API's term routes) is for editors.
      'capabilities' => array(
        'manage_terms' => 'manage_categories',
        'edit_terms' => 'manage_categories',
        'delete_terms' => 'manage_categories',
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

    // Each value is sanitised as the folio editor sanitises it (Folio::save),
    // so a write through the REST API stores what the editor would, and the
    // editor's own writes, which pass through here too, are unchanged.
    // proposal_revision_log has none: it is JSON the plugin builds itself,
    // and a text sanitiser would break it.
    foreach (array(
      'theme_id' => 'sanitize_key',
      'subtitle' => 'sanitize_text_field',
      'copyright' => 'sanitize_text_field',
      'permission' => 'sanitize_text_field',
      'fonts' => 'sanitize_key',
      'header_font' => 'sanitize_key',
      'body_font' => 'sanitize_key',
      'show_byline' => 'sanitize_key',
      'proposal_version' => 'sanitize_text_field',
      'proposal_status' => 'sanitize_text_field',
      'proposal_prepared_for' => 'sanitize_text_field',
      'proposal_prepared_by' => 'sanitize_text_field',
      'proposal_contact_email' => 'sanitize_email',
      'proposal_contact_name' => 'sanitize_text_field',
      'proposal_contact_role' => 'sanitize_text_field',
      'proposal_contact_phone' => 'sanitize_text_field',
      'proposal_contact_linkedin' => 'esc_url_raw',
      'proposal_contacts' => 'sanitize_textarea_field',
      'proposal_client_name' => 'sanitize_text_field',
      'proposal_client_logo_url' => 'esc_url_raw',
      'proposal_date' => 'sanitize_text_field',
      'proposal_show_in_page_nav' => 'sanitize_key',
      'proposal_color_scheme' => 'sanitize_key',
      'proposal_revision_log' => null,
    ) as $meta_key => $sanitize) {
      $args = $meta_args;
      if ($sanitize !== null) {
        $args['sanitize_callback'] = static function ($value) use ($sanitize) {
          return call_user_func($sanitize, is_scalar($value) ? (string) $value : '');
        };
      }
      register_post_meta('enrove_folio', $meta_key, $args);
    }
  }

  public function __construct()
  {
    add_action('init', [$this, 'create_posttype']);
  }
}
