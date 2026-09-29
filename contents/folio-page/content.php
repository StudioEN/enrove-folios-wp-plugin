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

  /**
   * A page joins a folio only when the user may edit that folio. Publishing a
   * folio publishes its pages, so without this a Contributor could attach a
   * page to anyone's folio (REST meta, or ?folio_id= on a first save) and have
   * it go live with that folio, unreviewed. This covers add and update by key;
   * guard_folio_link_by_mid() covers core's writes by meta ID, and
   * guard_rest_folio_link() answers REST with a 403. With no user signed in
   * (WP-CLI, cron) the two meta filters step aside.
   *
   * @param null|bool $check      Null unless another filter has already decided.
   * @param int       $object_id
   * @param string    $meta_key
   * @param mixed     $meta_value
   * @return null|bool False blocks the write.
   */
  public function guard_folio_link($check, $object_id, $meta_key, $meta_value)
  {
    if ($check !== null || $meta_key !== 'folio_id' || !is_user_logged_in()) {
      return $check;
    }
    if (get_post_type($object_id) !== 'groove_folio_page') {
      return $check;
    }

    return $this->may_link_to_folio($meta_value) ? $check : false;
  }

  /**
   * guard_folio_link() for core's by-meta-ID writes (the Custom Fields box,
   * wp_ajax_add_meta, XML-RPC), which fire their own filter instead.
   *
   * @param null|bool $check
   * @param int       $meta_id
   * @param mixed     $meta_value
   * @param string    $meta_key
   * @return null|bool False blocks the write.
   */
  public function guard_folio_link_by_mid($check, $meta_id, $meta_value, $meta_key)
  {
    if ($check !== null || !is_user_logged_in()) {
      return $check;
    }
    // XML-RPC's custom_fields passes no key (false), and core fires this
    // filter before it fills the key in from the row, so read it from there.
    $meta = get_metadata_by_mid('post', $meta_id);
    if (!$meta) {
      return $check;
    }
    if ($meta_key === false || $meta_key === null || $meta_key === '') {
      $meta_key = $meta->meta_key;
    }
    if ($meta_key !== 'folio_id' || get_post_type((int) $meta->post_id) !== 'groove_folio_page') {
      return $check;
    }

    return $this->may_link_to_folio($meta_value) ? $check : false;
  }

  /**
   * The same rule for the REST API, checked before the page is inserted, so a
   * refused link is a clean 403 rather than a page saved without its meta.
   *
   * @param \stdClass|\WP_Error $prepared_post
   * @param \WP_REST_Request     $request
   * @return \stdClass|\WP_Error
   */
  public function guard_rest_folio_link($prepared_post, $request)
  {
    $meta = $request->get_param('meta');
    if (is_wp_error($prepared_post) || !is_array($meta) || !array_key_exists('folio_id', $meta)) {
      return $prepared_post;
    }

    // Resending the link a page already has is not a new link: a client that
    // sends every meta field back must not be refused for one it left alone.
    $page_id = (int) $request->get_param('id');
    if ($page_id > 0 && (int) $meta['folio_id'] === (int) get_post_meta($page_id, 'folio_id', true)) {
      return $prepared_post;
    }

    if (!$this->may_link_to_folio($meta['folio_id'])) {
      return new \WP_Error(
        'rest_cannot_edit',
        __('Sorry, you are not allowed to add a page to that folio.', 'groove-folios'),
        array('status' => rest_authorization_required_code())
      );
    }

    return $prepared_post;
  }

  /**
   * Whether the current user may point a page's folio_id at this value:
   * zero (no folio) always, a folio only when they may edit it.
   *
   * @param mixed $value
   * @return bool
   */
  private function may_link_to_folio($value)
  {
    $folio_id = (int) $value;

    return $folio_id <= 0 || current_user_can('edit_post', $folio_id);
  }

  /**
   * folio_id is plumbing, not a custom field to type into: protected, it
   * leaves the Custom Fields box and XML-RPC's custom_fields. REST keeps it
   * through its own auth_callback.
   *
   * @param bool   $protected
   * @param string $meta_key
   * @param string $meta_type
   * @return bool
   */
  public function protect_folio_link($protected, $meta_key, $meta_type)
  {
    return $meta_key === 'folio_id' && $meta_type === 'post' ? true : $protected;
  }

  public function __construct()
  {
    add_action('init', [$this, 'create_posttype']);
    add_filter('add_post_metadata', [$this, 'guard_folio_link'], 10, 4);
    add_filter('update_post_metadata', [$this, 'guard_folio_link'], 10, 4);
    add_filter('update_post_metadata_by_mid', [$this, 'guard_folio_link_by_mid'], 10, 4);
    add_filter('rest_pre_insert_groove_folio_page', [$this, 'guard_rest_folio_link'], 10, 2);
    add_filter('is_protected_meta', [$this, 'protect_folio_link'], 10, 3);
  }
}
?>