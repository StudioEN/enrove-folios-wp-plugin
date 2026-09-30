<?php
namespace Groove\Contents\FolioPage;

use Groove\Pages\Folio;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Publishing
 *
 * A folio page goes live with its folio, never ahead of it:
 *
 *   - while its folio is unpublished, a page cannot be published, scheduled
 *     or made private, whichever way it is saved (block editor, Quick Edit,
 *     Bulk Edit, WP-CLI, an import);
 *   - when a published folio is unpublished, its published pages go back to
 *     draft with it, whatever unpublished it (the folio editor, Quick Edit,
 *     Bulk Edit, the trash).
 *
 * Publishing a folio from its editor publishes its pages
 * (Folio::save_folio()); from then on each page can be taken down or put
 * back on its own. The rules are enforced where a post is written
 * (wp_insert_post_data, transition_post_status), so no save path can skip
 * them. The block editor and the REST API are told why, rather than having a
 * Publish quietly come back as a draft.
 */
class Publishing
{
  /** A folio in one of these is live, and its pages may be too. */
  const LIVE_FOLIO_STATUSES = array('publish', 'private');

  /** A page in one of these is live, or will be on its date. */
  const LIVE_PAGE_STATUSES = array('publish', 'private', 'future');

  const EDITOR_SCRIPT_HANDLE = 'groove-page-publishing';

  /** Set once migrate_existing_pages() has run. */
  const MIGRATION_OPTION = 'groove_page_publishing_migration_v1';

  public static function register()
  {
    add_filter('wp_insert_post_data', array(static::class, 'hold_page_for_folio'), 10, 2);
    // After Content::guard_rest_folio_link() (10) has refused a folio the
    // user may not link to.
    add_filter('rest_pre_insert_groove_folio_page', array(static::class, 'refuse_rest_publish'), 20, 2);
    add_filter('rest_prepare_groove_folio_page', array(static::class, 'withhold_publish_action'), 10, 2);
    add_action('transition_post_status', array(static::class, 'unpublish_pages_with_folio'), 10, 3);
    add_action('transition_post_status', array(static::class, 'hold_published_page'), 10, 3);
    add_action('enqueue_block_editor_assets', array(static::class, 'enqueue_editor_notice'));
    // After the post types are registered (init, 10).
    add_action('init', array(static::class, 'migrate_existing_pages'), 20);
  }

  /**
   * Once per site: before these rules, a folio could be unpublished with its
   * pages left published, so a site upgrading from 0.5.1 or earlier can hold
   * live pages of an unpublished folio. They go back to draft, as they would
   * have with the rules in place.
   */
  public static function migrate_existing_pages()
  {
    if (get_option(self::MIGRATION_OPTION)) {
      return;
    }
    // Marked first, so a request that dies part way does not start over on
    // every request after it; the rules hold for anything it missed.
    update_option(self::MIGRATION_OPTION, time());

    $page_ids = get_posts(array(
      'post_type' => 'groove_folio_page',
      'post_status' => self::LIVE_PAGE_STATUSES,
      'posts_per_page' => -1,
      'fields' => 'ids',
      'no_found_rows' => true,
    ));

    foreach ($page_ids as $page_id) {
      if (!static::is_folio_live((int) get_post_meta((int) $page_id, 'folio_id', true))) {
        wp_update_post(array(
          'ID' => (int) $page_id,
          'post_status' => 'draft',
        ));
      }
    }
  }

  /**
   * @param int $folio_id
   * @return bool
   */
  public static function is_folio_live($folio_id)
  {
    $folio_id = (int) $folio_id;

    return $folio_id > 0
      && get_post_type($folio_id) === 'groove_folio'
      && in_array(get_post_status($folio_id), self::LIVE_FOLIO_STATUSES, true);
  }

  /**
   * The IDs of a folio's pages that are in the given statuses.
   *
   * @param int      $folio_id
   * @param string[] $statuses
   * @return int[]
   */
  public static function get_page_ids($folio_id, array $statuses)
  {
    return array_map('intval', get_posts(array(
      'post_type' => 'groove_folio_page',
      'post_status' => $statuses,
      'posts_per_page' => -1,
      'fields' => 'ids',
      'no_found_rows' => true,
      'meta_key' => 'folio_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- folio_id meta is the page-to-folio link; this fetches one folio's pages.
      'meta_value' => (string) (int) $folio_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- As above.
    )));
  }

  /**
   * The folio a page is being saved into: the folio_id this save writes
   * (meta_input, as sample content and imports pass it) when the user may
   * make that link, otherwise the one the page already has.
   *
   * @param int   $page_id 0 for a page not yet created.
   * @param array $postarr
   * @return int
   */
  private static function resolve_folio_id($page_id, array $postarr)
  {
    if (isset($postarr['meta_input']) && is_array($postarr['meta_input']) && isset($postarr['meta_input']['folio_id'])) {
      $folio_id = (int) $postarr['meta_input']['folio_id'];
      // The same rule Content::guard_folio_link() applies to the meta write.
      if (!is_user_logged_in() || current_user_can('edit_post', $folio_id)) {
        return $folio_id;
      }
    }

    return $page_id > 0 ? (int) get_post_meta($page_id, 'folio_id', true) : 0;
  }

  /**
   * A page whose folio is not live is saved as a draft instead of live. Core
   * publishes a scheduled post straight through the database, so a page is
   * not left scheduled either.
   *
   * @param array $data    Slashed, sanitised post data about to be written.
   * @param array $postarr
   * @return array
   */
  public static function hold_page_for_folio($data, $postarr)
  {
    if (
      !is_array($data)
      || ($data['post_type'] ?? '') !== 'groove_folio_page'
      || !in_array($data['post_status'] ?? '', self::LIVE_PAGE_STATUSES, true)
    ) {
      return $data;
    }

    $page_id = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
    if (!static::is_folio_live(static::resolve_folio_id($page_id, (array) $postarr))) {
      $data['post_status'] = 'draft';
    }

    return $data;
  }

  /**
   * The block editor's Publish (and Schedule, and Private) on a page whose
   * folio is not live fails with a reason, rather than saving a draft that
   * the editor would report as published.
   *
   * @param \stdClass|\WP_Error $prepared_post
   * @param \WP_REST_Request     $request
   * @return \stdClass|\WP_Error
   */
  public static function refuse_rest_publish($prepared_post, $request)
  {
    if (is_wp_error($prepared_post) || !isset($prepared_post->post_status)) {
      return $prepared_post;
    }
    if (!in_array($prepared_post->post_status, self::LIVE_PAGE_STATUSES, true)) {
      return $prepared_post;
    }

    $page_id = isset($prepared_post->ID) ? (int) $prepared_post->ID : 0;
    $meta = $request->get_param('meta');
    $folio_id = is_array($meta) && isset($meta['folio_id'])
      ? (int) $meta['folio_id']
      : ($page_id > 0 ? (int) get_post_meta($page_id, 'folio_id', true) : 0);

    if (static::is_folio_live($folio_id)) {
      // REST writes meta after the post, so hold_page_for_folio() would
      // still see the old folio (none, on a create) and save a draft. Pass
      // the link through the insert, where it reads it.
      if (is_array($meta) && isset($meta['folio_id'])) {
        $prepared_post->meta_input = array('folio_id' => $folio_id);
      }

      return $prepared_post;
    }

    return new \WP_Error(
      'groove_folio_not_published',
      __('Publish its folio first. A folio’s pages go live with it.', 'groove-folios'),
      array('status' => 409)
    );
  }

  /**
   * The block editor offers Published, Scheduled and Private (and names its
   * main button Publish) only when the page's REST data carries the
   * wp:action-publish link. While the folio is unpublished the link is left
   * out, so the editor offers Draft and Pending, as it would a Contributor,
   * instead of a status refuse_rest_publish() would turn down on save.
   *
   * @param \WP_REST_Response $response
   * @param \WP_Post          $post
   * @return \WP_REST_Response
   */
  public static function withhold_publish_action($response, $post)
  {
    if (
      $response instanceof \WP_REST_Response
      && $post instanceof \WP_Post
      && !in_array($post->post_status, self::LIVE_PAGE_STATUSES, true)
      && !static::is_folio_live((int) get_post_meta($post->ID, 'folio_id', true))
    ) {
      $response->remove_link('https://api.w.org/action-publish');
    }

    return $response;
  }

  /**
   * A folio leaving a live status takes its live pages with it, back to draft.
   *
   * @param string   $new_status
   * @param string   $old_status
   * @param \WP_Post $post
   */
  public static function unpublish_pages_with_folio($new_status, $old_status, $post)
  {
    if (
      !$post instanceof \WP_Post
      || $post->post_type !== 'groove_folio'
      || !in_array($old_status, self::LIVE_FOLIO_STATUSES, true)
      || in_array($new_status, self::LIVE_FOLIO_STATUSES, true)
    ) {
      return;
    }

    foreach (static::get_page_ids($post->ID, self::LIVE_PAGE_STATUSES) as $page_id) {
      wp_update_post(array(
        'ID' => $page_id,
        'post_status' => 'draft',
      ));
    }
  }

  /**
   * wp_publish_post() writes the status straight to the database, past
   * hold_page_for_folio(), so a page it publishes ahead of its folio is put
   * back to draft here. Every other save has been held already and never
   * arrives in publish.
   *
   * @param string   $new_status
   * @param string   $old_status
   * @param \WP_Post $post
   */
  public static function hold_published_page($new_status, $old_status, $post)
  {
    if (
      $new_status !== 'publish'
      || $old_status === 'publish'
      || !$post instanceof \WP_Post
      || $post->post_type !== 'groove_folio_page'
      || static::is_folio_live((int) get_post_meta($post->ID, 'folio_id', true))
    ) {
      return;
    }

    wp_update_post(array(
      'ID' => $post->ID,
      'post_status' => 'draft',
    ));
  }

  /**
   * A standing notice in a page's block editor while its folio is
   * unpublished, with the way to the folio.
   */
  public static function enqueue_editor_notice()
  {
    $page = get_post();
    if (!$page || $page->post_type !== 'groove_folio_page') {
      return;
    }

    $folio_id = (int) get_post_meta($page->ID, 'folio_id', true);
    if (static::is_folio_live($folio_id)) {
      return;
    }

    $settings = array(
      'message' => __('This page’s folio isn’t published, so the page can’t be published yet. It goes live when you publish the folio.', 'groove-folios'),
      'actionLabel' => '',
      'actionUrl' => '',
    );
    if ($folio_id > 0 && get_post_type($folio_id) === 'groove_folio' && current_user_can('edit_post', $folio_id)) {
      $settings['actionLabel'] = __('Open the folio', 'groove-folios');
      $settings['actionUrl'] = Folio::get_edit_url($folio_id);
    }

    $path = GROOVE_PATH . 'assets/js/groove-page-publishing.js';
    wp_enqueue_script(
      self::EDITOR_SCRIPT_HANDLE,
      GROOVE_URL . 'assets/js/groove-page-publishing.js',
      array('wp-data', 'wp-notices', 'wp-dom-ready'),
      file_exists($path) ? (string) filemtime($path) : GROOVE_VERSION,
      true
    );
    wp_add_inline_script(
      self::EDITOR_SCRIPT_HANDLE,
      'window.GROOVE_PAGE_PUBLISHING = ' . wp_json_encode($settings, JSON_HEX_TAG | JSON_HEX_AMP) . ';',
      'before'
    );
  }
}
