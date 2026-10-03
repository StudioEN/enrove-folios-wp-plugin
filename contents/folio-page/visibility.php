<?php
namespace Enrove\Contents\FolioPage;

use Enrove\Utils\Utils;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Visibility
 *
 * A folio page is no more visible than its folio. The plugin's own addresses
 * check that (Utils::can_current_request_view_post()), but a folio page is
 * also a public post type, and core serves those at addresses of its own:
 * ?enrove_folio_page=<slug>, the REST API, the post search endpoint, feeds,
 * searches and the sitemap. A published page of a private, password-locked
 * or unpublished folio stays published (Publishing leaves a page's status
 * alone when its folio goes private), so each of those is told to leave it
 * out, and a page or folio core finds at one of its addresses is sent to the
 * plugin's, where the folio's theme and password gate apply.
 */
class Visibility
{
  const POST_TYPES = array('enrove_folio', 'enrove_folio_page');

  /** get_hidden_page_ids() results for this request, by mode. */
  private static $hidden_page_ids = array();

  public static function register()
  {
    add_action('pre_get_posts', array(static::class, 'hide_pages_from_main_query'));
    // After the plugin's own routes, which run at 5 and exit.
    add_action('template_redirect', array(static::class, 'send_to_folio_address'), 6);
    add_filter('rest_enrove_folio_page_query', array(static::class, 'hide_pages_from_rest_query'));
    add_filter('rest_request_before_callbacks', array(static::class, 'refuse_rest_read'), 10, 3);
    add_filter('rest_post_search_query', array(static::class, 'hide_pages_from_rest_query'));
    add_filter('wp_sitemaps_posts_query_args', array(static::class, 'hide_pages_from_sitemap'), 10, 2);
  }

  /**
   * The published and private pages whose folio the current request may not
   * see, less the ones the current user may edit. With $public, the pages
   * whose folio no visitor may see without signing in or a password,
   * whoever is asking (the sitemap is the same for everyone).
   *
   * @param bool $public
   * @return int[]
   */
  public static function get_hidden_page_ids($public = false)
  {
    $mode = $public ? 'public' : 'request';
    if (isset(self::$hidden_page_ids[$mode])) {
      return self::$hidden_page_ids[$mode];
    }

    $query = array(
      'post_type' => 'enrove_folio',
      'posts_per_page' => -1,
      'no_found_rows' => true,
      'update_post_meta_cache' => false,
      'update_post_term_cache' => false,
    );
    // Every folio a visitor could be refused: those not published, and the
    // published ones behind a password.
    $folios = array_merge(
      get_posts(array_merge($query, array('post_status' => array_values(array_diff(get_post_stati(), array('publish', 'inherit')))))),
      get_posts(array_merge($query, array('post_status' => 'publish', 'has_password' => true)))
    );

    $folio_ids = array();
    foreach ($folios as $folio) {
      if ($public || !Utils::can_current_request_view_post($folio)) {
        $folio_ids[] = (string) $folio->ID;
      }
    }

    $page_ids = array();
    if ($folio_ids) {
      $page_ids = get_posts(array(
        'post_type' => 'enrove_folio_page',
        'post_status' => array('publish', 'private'),
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- folio_id meta is the only link from a page to its folio; the query is bounded to the folios a visitor may not see.
          array(
            'key' => 'folio_id',
            'value' => $folio_ids,
            'compare' => 'IN',
          ),
        ),
      ));
      $page_ids = array_map('intval', $page_ids);
      if (!$public) {
        $page_ids = array_values(array_filter($page_ids, static function ($page_id) {
          return !current_user_can('edit_post', $page_id);
        }));
      }
    }

    self::$hidden_page_ids[$mode] = $page_ids;

    return $page_ids;
  }

  /**
   * Whether a folio page is hidden from the current request by its folio.
   *
   * @param \WP_Post $page
   * @return bool
   */
  public static function is_page_hidden($page)
  {
    return $page instanceof \WP_Post
      && $page->post_type === 'enrove_folio_page'
      && in_array((int) $page->ID, static::get_hidden_page_ids(), true);
  }

  /**
   * Leaves hidden pages out of a front-end main query that names folio pages:
   * ?enrove_folio_page=<slug> then 404s like any post the visitor may not
   * read, and a feed or search of folio pages lists only visible ones.
   *
   * @param \WP_Query $query
   */
  public static function hide_pages_from_main_query($query)
  {
    if (is_admin() || !$query->is_main_query()) {
      return;
    }
    if (!in_array('enrove_folio_page', (array) $query->get('post_type'), true)) {
      return;
    }

    $hidden = static::get_hidden_page_ids();
    if ($hidden) {
      $query->set('post__not_in', array_merge(array_map('intval', (array) $query->get('post__not_in')), $hidden));
    }
  }

  /**
   * A folio or folio page core found at one of its own addresses is shown at
   * the plugin's instead, where the folio's theme renders it and its password
   * gate applies, rather than through the site's WordPress theme.
   */
  public static function send_to_folio_address()
  {
    if (!is_singular(self::POST_TYPES) || is_feed()) {
      return;
    }

    $post = get_queried_object();
    $url = $post instanceof \WP_Post ? Utils::get_folio_permalink_by_id($post->ID) : '';
    if ($url !== '' && wp_safe_redirect($url)) {
      exit;
    }
  }

  /**
   * Leaves hidden pages out of the REST API's folio page collection and its
   * post search endpoint.
   *
   * @param array $args WP_Query arguments.
   * @return array
   */
  public static function hide_pages_from_rest_query($args)
  {
    $hidden = static::get_hidden_page_ids();
    if ($hidden) {
      $args['post__not_in'] = array_merge(array_map('intval', (array) ($args['post__not_in'] ?? array())), $hidden); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Excluding the few pages of folios a visitor may not see is the point; there is no inclusive form of it.
    }

    return $args;
  }

  /**
   * Refuses a REST request that names a hidden page, the way core refuses a
   * post the user may not read. It runs before the endpoint, because the
   * posts controller cannot take an error from its rest_prepare_* filter.
   * A hidden page is one the user may not edit, so only reads get this far.
   *
   * @param \WP_REST_Response|\WP_HTTP_Response|\WP_Error|mixed $response
   * @param array                                             $handler
   * @param \WP_REST_Request                                  $request
   * @return mixed
   */
  public static function refuse_rest_read($response, $handler, $request)
  {
    if (is_wp_error($response) || !$request instanceof \WP_REST_Request) {
      return $response;
    }
    // The page itself and anything under it (revisions, autosaves).
    if (!preg_match('#^/wp/v2/enrove_folio_page/(\d+)(?:/|$)#', $request->get_route(), $match)) {
      return $response;
    }
    if (!static::is_page_hidden(get_post((int) $match[1]))) {
      return $response;
    }

    return new \WP_Error(
      'rest_forbidden',
      __('Sorry, you are not allowed to do that.', 'enrove-folios'),
      array('status' => rest_authorization_required_code())
    );
  }

  /**
   * Leaves pages whose folio is not public out of the sitemap, which core
   * fills with every published post of a public post type.
   *
   * @param array  $args      WP_Query arguments.
   * @param string $post_type
   * @return array
   */
  public static function hide_pages_from_sitemap($args, $post_type)
  {
    if ($post_type !== 'enrove_folio_page') {
      return $args;
    }

    $hidden = static::get_hidden_page_ids(true);
    if ($hidden) {
      $args['post__not_in'] = array_merge(array_map('intval', (array) ($args['post__not_in'] ?? array())), $hidden); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Excluding the few pages of folios a visitor may not see is the point; there is no inclusive form of it.
    }

    return $args;
  }
}
