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
 *     Bulk Edit, WP-CLI), nor stay live if it is moved into such a folio;
 *   - when a live folio is unpublished, its live pages go back to draft with
 *     it, whatever unpublished it (the folio editor, Quick Edit, Bulk Edit,
 *     the trash, a permanent delete);
 *   - when a folio goes live, whatever published it (the folio editor, Quick
 *     Edit, its scheduled date), the pages it took down come back as they
 *     were, private ones private, and its other draft and pending pages are
 *     published with it.
 *
 * From then on each page can be taken down or put back on its own. The rules
 * are enforced where a post is written (wp_insert_post_data,
 * transition_post_status, the folio_id meta hooks), so no save path can skip
 * them. The block editor and the REST API are told why, rather than having a
 * Publish quietly come back as a draft. A WordPress import is left alone: it
 * writes pages before their folio_id, and copies statuses that already obeyed
 * the rules on the site they came from.
 */
class Publishing
{
  /** A folio in one of these is live, and its pages may be too. */
  const LIVE_FOLIO_STATUSES = array('publish', 'private');

  /** A page in one of these is live, or will be on its date. */
  const LIVE_PAGE_STATUSES = array('publish', 'private', 'future');

  /** The pages a folio publishes when it goes live, besides the ones it took down. */
  const STATUSES_TO_PUBLISH = array('draft', 'pending');

  /**
   * The status a page had when its folio took it down, so the folio's going
   * live again puts it back as it was.
   */
  const HELD_STATUS_META = '_groove_status_before_folio';

  const EDITOR_SCRIPT_HANDLE = 'groove-page-publishing';

  /** Set once migrate_existing_pages() has run. */
  const MIGRATION_OPTION = 'groove_page_publishing_migration_v1';

  /** Posts wp_delete_post() is deleting in this request; see note_deletion(). */
  private static $deleting = array();

  public static function register()
  {
    add_filter('wp_insert_post_data', array(static::class, 'hold_page_for_folio'), 10, 2);
    // After Content::guard_rest_folio_link() (10) has refused a folio the
    // user may not link to.
    add_filter('rest_pre_insert_groove_folio_page', array(static::class, 'refuse_rest_publish'), 20, 2);
    add_filter('rest_prepare_groove_folio_page', array(static::class, 'withhold_publish_action'), 10, 2);
    add_action('transition_post_status', array(static::class, 'follow_folio_status'), 10, 3);
    add_action('transition_post_status', array(static::class, 'follow_page_status'), 10, 3);
    add_action('before_delete_post', array(static::class, 'note_deletion'), 10, 2);
    add_action('added_post_meta', array(static::class, 'follow_folio_link'), 10, 3);
    add_action('updated_post_meta', array(static::class, 'follow_folio_link'), 10, 3);
    add_action('deleted_post_meta', array(static::class, 'follow_folio_link'), 10, 3);
    add_action('enqueue_block_editor_assets', array(static::class, 'enqueue_editor_notice'));
    add_action('admin_init', array(static::class, 'migrate_existing_pages'));
  }

  /**
   * Once per site: before these rules, a folio could be unpublished with its
   * pages left published, so a site upgrading from an earlier version can
   * hold live pages of an unpublished folio. They are taken down, as they
   * would have been with the rules in place, and come back when their folio
   * is published. Run on admin_init (wp-admin, admin-ajax, admin-post), not
   * on every front-end request; it only ever takes down pages that break
   * the rule, so a run cut short is simply finished by the next one.
   */
  public static function migrate_existing_pages()
  {
    if (get_option(self::MIGRATION_OPTION)) {
      return;
    }

    $pages = get_posts(array(
      'post_type' => 'groove_folio_page',
      'post_status' => self::LIVE_PAGE_STATUSES,
      'posts_per_page' => -1,
      'no_found_rows' => true,
    ));

    foreach ($pages as $page) {
      if (!static::is_folio_live((int) get_post_meta($page->ID, 'folio_id', true))) {
        static::take_down_page($page);
      }
    }

    update_option(self::MIGRATION_OPTION, time());
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
   * A folio's pages that are in the given statuses.
   *
   * @param int      $folio_id
   * @param string[] $statuses
   * @return \WP_Post[]
   */
  public static function get_pages($folio_id, array $statuses)
  {
    return get_posts(array(
      'post_type' => 'groove_folio_page',
      'post_status' => $statuses,
      'posts_per_page' => -1,
      'no_found_rows' => true,
      'meta_key' => 'folio_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- folio_id meta is the page-to-folio link; this fetches one folio's pages.
      'meta_value' => (string) (int) $folio_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- As above.
    ));
  }

  /**
   * The pages that publishing this folio now would bring live: the ones it
   * took down, and its other draft and pending pages, each only when the
   * current user may publish it. The folio editor counts these in its
   * Publish confirmation.
   *
   * @param int $folio_id
   * @return \WP_Post[]
   */
  public static function get_pages_to_publish($folio_id)
  {
    return array_values(array_filter(
      static::get_pages($folio_id, self::STATUSES_TO_PUBLISH),
      array(static::class, 'may_publish_page')
    ));
  }

  /**
   * Whether the folio going live may bring this page with it. The user
   * publishing the folio needs the right to publish the page; with no one
   * signed in, or in a scheduled folio's cron run, the folio's own
   * publishing was the check.
   *
   * @param \WP_Post $page
   * @return bool
   */
  private static function may_publish_page($page)
  {
    // wp_doing_cron(): on an ALTERNATE_WP_CRON site cron runs inside some
    // visitor's request, signed in or not.
    if (!is_user_logged_in() || wp_doing_cron()) {
      return true;
    }
    $post_type = get_post_type_object($page->post_type);

    return $post_type
      && current_user_can('edit_post', $page->ID)
      && current_user_can($post_type->cap->publish_posts);
  }

  /**
   * Changes only a page's status. wp_update_post() re-saves the whole post,
   * and on the way runs the stored content through the current user's save
   * filters: kses, and since WordPress 7.0 the block custom CSS stripper. For
   * a visitor's cron run, an Author or a multisite admin that strips the
   * embeds, SVG, forms and block CSS an administrator wrote. So the stored
   * fields are put back as the last step of this one save; nothing but the
   * status changes.
   *
   * @param int    $page_id
   * @param string $status
   */
  private static function set_page_status($page_id, $status)
  {
    $stored = get_post((int) $page_id);
    if (!$stored instanceof \WP_Post) {
      return;
    }

    $keep_fields = static function ($data, $postarr) use ($stored) {
      if (isset($postarr['ID']) && (int) $postarr['ID'] === (int) $stored->ID) {
        foreach (array('post_title', 'post_content', 'post_excerpt', 'post_content_filtered') as $field) {
          $data[$field] = wp_slash($stored->$field);
        }
      }
      return $data;
    };

    add_filter('wp_insert_post_data', $keep_fields, PHP_INT_MAX, 2);
    try {
      wp_update_post(array(
        'ID' => $stored->ID,
        'post_status' => $status,
      ));
    } finally {
      remove_filter('wp_insert_post_data', $keep_fields, PHP_INT_MAX);
    }
  }

  /**
   * Takes a live page down with its folio, noting how it was.
   *
   * @param \WP_Post $page
   */
  private static function take_down_page($page)
  {
    if (!in_array($page->post_status, self::LIVE_PAGE_STATUSES, true)) {
      return;
    }
    update_post_meta($page->ID, self::HELD_STATUS_META, $page->post_status);
    static::set_page_status($page->ID, 'draft');
  }

  /**
   * The folio a page is being saved into: the folio_id this save writes
   * (meta_input, as sample content and the REST API pass it) when the user
   * may make that link, otherwise the one the page already has.
   *
   * @param int   $page_id 0 for a page not yet created.
   * @param array $postarr
   * @return int
   */
  private static function resolve_folio_id($page_id, array $postarr)
  {
    if (isset($postarr['meta_input']) && is_array($postarr['meta_input']) && array_key_exists('folio_id', $postarr['meta_input'])) {
      $folio_id = (int) $postarr['meta_input']['folio_id'];
      // The same rule Content::guard_folio_link() applies to the meta write.
      if (!is_user_logged_in() || $folio_id <= 0 || current_user_can('edit_post', $folio_id)) {
        return $folio_id;
      }
    }

    return $page_id > 0 ? (int) get_post_meta($page_id, 'folio_id', true) : 0;
  }

  /**
   * @return bool
   */
  private static function is_importing()
  {
    return defined('WP_IMPORTING') && WP_IMPORTING;
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
      || static::is_importing()
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
    $links_folio = is_array($meta) && array_key_exists('folio_id', $meta);
    $folio_id = $links_folio
      ? (int) $meta['folio_id']
      : ($page_id > 0 ? (int) get_post_meta($page_id, 'folio_id', true) : 0);

    if (static::is_folio_live($folio_id)) {
      // REST writes meta after the post, so hold_page_for_folio() would
      // still see the old folio (none, on a create) and save a draft. Pass
      // the link through the insert, where it reads it.
      if ($links_folio) {
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
   * A folio's pages follow it. Leaving a live status takes its live pages
   * down; going live brings back the ones it took down, as they were, and
   * publishing it (from draft, pending, scheduled or private) publishes its
   * other draft and pending pages. A save that keeps the folio's status
   * changes no page, so a page taken down in a live folio stays down.
   * Private to published only publishes; published to private changes none;
   * going private brings back only the pages that were private.
   *
   * clean_post_cache() has run by now, so the pages' own saves see the
   * folio's new status.
   *
   * @param string   $new_status
   * @param string   $old_status
   * @param \WP_Post $post
   */
  public static function follow_folio_status($new_status, $old_status, $post)
  {
    if (!$post instanceof \WP_Post || $post->post_type !== 'groove_folio' || $new_status === $old_status || static::is_importing()) {
      return;
    }

    $was_live = in_array($old_status, self::LIVE_FOLIO_STATUSES, true);
    $is_live = in_array($new_status, self::LIVE_FOLIO_STATUSES, true);

    if ($was_live && !$is_live) {
      foreach (static::get_pages($post->ID, self::LIVE_PAGE_STATUSES) as $page) {
        static::take_down_page($page);
      }
      return;
    }

    if (!$is_live || ($was_live && $new_status !== 'publish')) {
      return;
    }

    foreach (static::get_pages_to_publish($post->ID) as $page) {
      $held = (string) get_post_meta($page->ID, self::HELD_STATUS_META, true);
      if ($held === 'private') {
        static::set_page_status($page->ID, 'private');
      } elseif ($new_status === 'publish') {
        // Back as it was, or published with the folio; a page whose date is
        // still ahead is scheduled again by core. A folio going private
        // makes nothing public: a published page can be read at its own
        // address whatever its folio's status, so the rest wait, still
        // marked, until the folio is published.
        static::set_page_status($page->ID, 'publish');
      }
    }
  }

  /**
   * A page follows its own status too: once live again its note of how it
   * was is spent. And wp_publish_post() writes the status straight to the
   * database, past hold_page_for_folio(), so a page it publishes ahead of its
   * folio is put back to draft here. Every other save has been held already
   * and never arrives live.
   *
   * @param string   $new_status
   * @param string   $old_status
   * @param \WP_Post $post
   */
  public static function follow_page_status($new_status, $old_status, $post)
  {
    if (
      !$post instanceof \WP_Post
      || $post->post_type !== 'groove_folio_page'
      || !in_array($new_status, self::LIVE_PAGE_STATUSES, true)
      || static::is_importing()
    ) {
      return;
    }

    if ($new_status === 'publish' && $old_status !== 'publish' && !static::is_folio_live((int) get_post_meta($post->ID, 'folio_id', true))) {
      // Still held, so its note of how it was stays.
      static::set_page_status($post->ID, 'draft');
      return;
    }

    delete_post_meta($post->ID, self::HELD_STATUS_META);
  }

  /**
   * A folio deleted outright (Empty Trash, or a delete with the trash turned
   * off) fires no status change, so its live pages are taken down here
   * rather than left published without a folio. Any post being deleted is
   * noted too: core deletes its meta before its row, and follow_folio_link()
   * must not take down a page that is on its way out.
   *
   * @param int           $post_id
   * @param \WP_Post|null $post
   */
  public static function note_deletion($post_id, $post = null)
  {
    self::$deleting[(int) $post_id] = true;

    if (get_post_type($post_id) !== 'groove_folio') {
      return;
    }

    foreach (static::get_pages($post_id, self::LIVE_PAGE_STATUSES) as $page) {
      static::take_down_page($page);
    }
  }

  /**
   * A live page moved into an unpublished folio, or out of every folio, by
   * its folio_id alone (REST meta, WP-CLI) is taken down like the folio's
   * other pages. It comes back when that folio is published.
   *
   * @param int|int[] $meta_id
   * @param int       $object_id
   * @param string    $meta_key
   */
  public static function follow_folio_link($meta_id, $object_id, $meta_key)
  {
    // Object ID 0: a delete across every post (delete_post_meta_by_key()),
    // where get_post(0) would be whatever post is global.
    if ($meta_key !== 'folio_id' || (int) $object_id <= 0 || static::is_importing() || isset(self::$deleting[(int) $object_id])) {
      return;
    }

    $page = get_post($object_id);
    if (
      !$page instanceof \WP_Post
      || $page->post_type !== 'groove_folio_page'
      || !in_array($page->post_status, self::LIVE_PAGE_STATUSES, true)
      || static::is_folio_live((int) get_post_meta($page->ID, 'folio_id', true))
    ) {
      return;
    }

    static::take_down_page($page);
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

    $has_folio = $folio_id > 0 && get_post_type($folio_id) === 'groove_folio';
    $settings = array(
      'message' => $has_folio
        ? __('This page’s folio isn’t published, so the page can’t be published yet. It goes live when you publish the folio.', 'groove-folios')
        : __('This page isn’t in a folio, so it can’t be published. Add pages from a folio’s Pages tab.', 'groove-folios'),
      'actionLabel' => '',
      'actionUrl' => '',
    );
    if ($has_folio && current_user_can('edit_post', $folio_id)) {
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
