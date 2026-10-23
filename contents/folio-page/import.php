<?php
namespace Enrove\Contents\FolioPage;

use Enrove\Themes\Base_Theme;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Import
 *
 * Makes folio pages from posts and pages the site already has. Each import is
 * a copy: the new page owes nothing to its source afterwards, and editing one
 * leaves the other alone. The source is noted on the copy (SOURCE_META), for
 * the picker to mark what a folio already holds.
 *
 * Content written in the block editor is copied as stored. Content written in
 * the classic editor has no blocks, so a folio theme would print it without
 * paragraphs and its "On this page" list would find no headings; the picker
 * converts it to blocks in the browser, with core's own raw handler (what the
 * editor's Convert to blocks runs), and sends the blocks with the request.
 * When none come, the copy falls back to the classic content with paragraphs
 * added (wpautop()), which reads correctly and converts in the editor later.
 *
 * Only what the user could edit can be imported: a draft, a private post or
 * someone else's work is not theirs to copy unless they could already change
 * it. The folio is checked the same way, by the handler that names it.
 */
class Import
{
  /** On a folio page: the ID of the post it was copied from. */
  const SOURCE_META = '_enrove_source_post_id';

  /** Nonce action for the picker's AJAX requests. */
  const AJAX_NONCE = 'enrove_import';

  /** Nonce action for the folio editor's import form. */
  const FORM_NONCE = 'enrove_import_pages';

  /** Nonce field of that form. */
  const FORM_NONCE_FIELD = '_enrove_import_nonce';

  /** The most sources one import takes. */
  const MAX_SOURCES = 50;

  /** Search results per page. */
  const PER_PAGE = 20;

  /** Statuses a source may be in. */
  const SOURCE_STATUSES = array('publish', 'future', 'draft', 'pending', 'private');

  /**
   * Shortcodes the raw handler turns into blocks. Any other shortcode stays a
   * Shortcode block, which a folio theme prints as typed: it renders blocks
   * without the_content, so no shortcode runs there.
   */
  const CONVERTED_SHORTCODES = array('caption', 'wp_caption', 'gallery', 'embed', 'audio', 'video');

  /**
   * Post meta that marks content laid out by a page builder, which keeps its
   * real layout outside post_content or in shortcodes no folio runs. Key =>
   * the value that means the builder is in use ('' for any value).
   */
  const BUILDER_META = array(
    '_elementor_edit_mode' => 'builder',
    '_et_pb_use_builder' => 'on',
    '_fl_builder_enabled' => '',
    '_bricks_editor_mode' => 'bricks',
    'ct_builder_shortcodes' => '',
  );

  public static function register()
  {
    add_action('wp_ajax_enrove_import_search', array(static::class, 'ajax_search'));
    add_action('wp_ajax_enrove_import_source', array(static::class, 'ajax_source'));
  }

  /**
   * What the picker's script needs: where to send its requests and the nonce
   * they carry.
   *
   * @return array
   */
  public static function script_settings(): array
  {
    return array(
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce(self::AJAX_NONCE),
      'maxSources' => self::MAX_SOURCES,
    );
  }

  /**
   * The post types whose posts can be imported: posts and pages unless a
   * filter says otherwise, and never a folio or a folio page.
   *
   * @return string[]
   */
  public static function get_post_types(): array
  {
    $types = (array) apply_filters('enrove_folios_importable_post_types', array('post', 'page'));

    return array_values(array_filter(array_map('strval', $types), static function ($type) {
      $object = get_post_type_object($type);

      return $object
        && $object->show_ui
        && post_type_supports($type, 'editor')
        && !in_array($type, array('enrove_folio', 'enrove_folio_page'), true);
    }));
  }

  /**
   * Whether the current user may import this post: an importable type, in a
   * status worth copying, and one they could edit.
   *
   * @param \WP_Post|null $post
   * @return bool
   */
  public static function can_import($post): bool
  {
    return $post instanceof \WP_Post
      && in_array($post->post_type, static::get_post_types(), true)
      && in_array($post->post_status, self::SOURCE_STATUSES, true)
      && current_user_can('edit_post', $post->ID);
  }

  // -----------------------------------------------------------------------
  // The picker's requests
  // -----------------------------------------------------------------------

  /**
   * One page of the posts the user may import, newest change first.
   *
   * Request: search, post_type ('' for every importable type), paged, and
   * folio_id to mark the posts that folio already holds a copy of.
   */
  public static function ajax_search()
  {
    check_ajax_referer(self::AJAX_NONCE, 'nonce');
    if (!current_user_can('edit_posts')) {
      wp_send_json_error(array('message' => __('Sorry, you are not allowed to import posts.', 'enrove-folios')), 403);
    }

    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $post_type = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : '';
    $paged = isset($_POST['paged']) ? max(1, absint(wp_unslash($_POST['paged']))) : 1;
    $folio_id = isset($_POST['folio_id']) ? absint(wp_unslash($_POST['folio_id'])) : 0;

    wp_send_json_success(static::search($search, $post_type, $paged, $folio_id));
  }

  /**
   * A classic source's content, for the picker to convert to blocks, with
   * the attachments its galleries show. A source already in blocks has
   * nothing to convert, and the import copies it as stored.
   *
   * Request: post_id.
   */
  public static function ajax_source()
  {
    check_ajax_referer(self::AJAX_NONCE, 'nonce');
    if (!current_user_can('edit_posts')) {
      wp_send_json_error(array('message' => __('Sorry, you are not allowed to import posts.', 'enrove-folios')), 403);
    }

    $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
    $post = $post_id ? get_post($post_id) : null;
    if (!static::can_import($post)) {
      wp_send_json_error(array('message' => __('Sorry, you are not allowed to import that post.', 'enrove-folios')), 403);
    }

    wp_send_json_success(static::get_source($post));
  }

  /**
   * @param string $search
   * @param string $post_type One importable type, or '' for all of them.
   * @param int    $paged
   * @param int    $folio_id  Marks the posts this folio already holds; 0 marks none.
   * @return array{items: array, page: int, pages: int, total: int}
   */
  public static function search(string $search, string $post_type, int $paged, int $folio_id): array
  {
    $types = static::get_post_types();
    if ($post_type !== '') {
      $types = in_array($post_type, $types, true) ? array($post_type) : array();
    }
    if (!$types) {
      return array('items' => array(), 'page' => 1, 'pages' => 0, 'total' => 0);
    }

    $args = array(
      'post_type' => $types,
      'post_status' => self::SOURCE_STATUSES,
      'posts_per_page' => self::PER_PAGE,
      'paged' => $paged,
      'orderby' => 'modified',
      'order' => 'DESC',
      'ignore_sticky_posts' => true,
    );
    if ($search !== '') {
      $args['s'] = $search;
    }
    // Someone who may not edit others' work gets only their own, so the
    // pages of results are not mostly posts they cannot import.
    foreach ($types as $type) {
      if (!current_user_can(get_post_type_object($type)->cap->edit_others_posts)) {
        $args['author'] = get_current_user_id();
        break;
      }
    }

    $query = new \WP_Query($args);
    $held = $folio_id > 0 && current_user_can('edit_post', $folio_id) ? static::get_imported_source_ids($folio_id) : array();

    $items = array();
    foreach ($query->posts as $post) {
      // Private posts of others, for one who may edit others' but not
      // private posts: rare enough to drop rather than query around.
      if (static::can_import($post)) {
        $items[] = static::describe($post, $held);
      }
    }

    return array(
      'items' => $items,
      'page' => $paged,
      'pages' => (int) $query->max_num_pages,
      'total' => (int) $query->found_posts,
    );
  }

  /**
   * A post as the picker lists it. Text is plain, for the script to set as
   * text, never as HTML.
   *
   * @param \WP_Post $post
   * @param int[]    $held Source IDs the folio already holds a copy of.
   * @return array
   */
  public static function describe(\WP_Post $post, array $held = array()): array
  {
    $type_object = get_post_type_object($post->post_type);
    $status_object = get_post_status_object($post->post_status);
    $shortcodes = static::get_unconverted_shortcodes($post->post_content);

    return array(
      'id' => (int) $post->ID,
      'title' => $post->post_title !== '' ? wp_strip_all_tags($post->post_title) : __('(no title)', 'enrove-folios'),
      'type' => $post->post_type,
      'typeLabel' => $type_object ? (string) $type_object->labels->singular_name : $post->post_type,
      'status' => $post->post_status,
      'statusLabel' => $status_object ? (string) $status_object->label : $post->post_status,
      'modified' => mysql2date(get_option('date_format'), $post->post_modified),
      'classic' => !has_blocks($post->post_content),
      'shortcodes' => $shortcodes,
      'builder' => static::uses_page_builder($post),
      'password' => $post->post_password !== '',
      'held' => in_array((int) $post->ID, $held, true),
    );
  }

  /**
   * What the picker converts: the raw content, with every [gallery] naming
   * its images (one that names none shows the post's attached images, which
   * the converter cannot look up), and the URL and alt text of each image a
   * gallery names. The raw handler turns a gallery into image blocks that
   * carry only an attachment ID, expecting the editor to fetch the rest;
   * without the URL a folio would print an empty gallery. An ID is only a
   * number someone typed, so only attachments the user may read are sent.
   *
   * @param \WP_Post $post
   * @return array{id: int, classic: bool, content: string, attachments: object}
   */
  public static function get_source(\WP_Post $post): array
  {
    if (has_blocks($post->post_content)) {
      return array('id' => (int) $post->ID, 'classic' => false, 'content' => '', 'attachments' => (object) array());
    }

    $attachments = array();
    $content = preg_replace_callback('/' . get_shortcode_regex(array('gallery')) . '/', static function ($match) use ($post, &$attachments) {
      // [[gallery]] is an escaped shortcode, printed as text.
      if ($match[1] === '[' && $match[6] === ']') {
        return $match[0];
      }
      $atts = shortcode_parse_atts($match[3]);
      $atts = is_array($atts) ? $atts : array();
      $named = isset($atts['ids']) ? $atts['ids'] : (isset($atts['include']) ? $atts['include'] : '');
      $ids = array_values(array_filter(array_map('absint', explode(',', (string) $named))));

      $shortcode = $match[0];
      if (!$ids) {
        $ids = array_map('intval', get_children(array(
          'post_parent' => $post->ID,
          'post_type' => 'attachment',
          'post_mime_type' => 'image',
          'post_status' => 'inherit',
          'orderby' => 'menu_order ID',
          'order' => 'ASC',
          'fields' => 'ids',
        )));
        if ($ids) {
          $shortcode = '[gallery' . $match[3] . ' ids="' . implode(',', $ids) . '"]';
        }
      }

      foreach ($ids as $id) {
        if (get_post_type($id) !== 'attachment' || !current_user_can('read_post', $id)) {
          continue;
        }
        $url = wp_get_attachment_image_url($id, 'large');
        if ($url) {
          $attachments[$id] = array(
            'url' => $url,
            'alt' => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
          );
        }
      }

      return $shortcode;
    }, $post->post_content);

    return array(
      'id' => (int) $post->ID,
      'classic' => true,
      // null when PCRE gives up on very large content; the gallery then
      // converts without its images rather than the post not converting.
      'content' => $content === null ? $post->post_content : $content,
      'attachments' => (object) $attachments,
    );
  }

  // -----------------------------------------------------------------------
  // Making the pages
  // -----------------------------------------------------------------------

  /**
   * The sources a form names (sources[], in the order the user put them),
   * each with the blocks the picker converted it to (converted[<id>]) or
   * null. The form's nonce is checked here, before anything is read, though
   * its handler has checked it already: the folio editor's import form and
   * the Add New form each carry their own.
   *
   * The blocks are post content the user wrote, as surely as if they had
   * typed them into the editor, and are saved as the editor would save them:
   * wp_insert_post() runs them through the user's own save filters. They are
   * also held here to what a folio page can print, the allowlist every
   * theme's page.php echoes content through, so nothing is stored that would
   * never show.
   *
   * @param string $nonce_action
   * @param string $nonce_field
   * @return array{items: array<int, array{post: \WP_Post, content: ?string}>, refused: int}
   */
  public static function read_form(string $nonce_action, string $nonce_field): array
  {
    check_admin_referer($nonce_action, $nonce_field);
    // Each source is checked below (can_import()); this is the floor.
    if (!current_user_can('edit_posts')) {
      return array('items' => array(), 'refused' => 0);
    }

    $ids =isset($_POST['sources']) ? array_map('absint', (array) wp_unslash($_POST['sources'])) : array();
    $ids = array_slice(array_values(array_unique(array_filter($ids))), 0, self::MAX_SOURCES);

    $items = array();
    $refused = 0;
    foreach ($ids as $id) {
      $post = get_post($id);
      if (!static::can_import($post)) {
        $refused++;
        continue;
      }

      $content = null;
      if (isset($_POST['converted'][$id]) && is_string($_POST['converted'][$id])) {
        // The picker removes script and style elements whole before it
        // converts; here wp_kses() takes away any tags that still arrive.
        $content = wp_kses(wp_unslash($_POST['converted'][$id]), Base_Theme::get_content_allowed_html());
        // Blocks or nothing: anything else takes the fallback.
        if (strpos($content, '<!-- wp:') === false) {
          $content = null;
        }
      }
      $items[] = array('post' => $post, 'content' => $content);
    }

    return array('items' => $items, 'refused' => $refused);
  }

  /**
   * Copy each source into a new page of the folio, after its last page.
   *
   * A source in blocks is copied as stored, whatever was sent for it. A
   * classic one takes its converted blocks, or, with none, its own content
   * with paragraphs added. Each copy takes the source's title, excerpt and
   * featured image, belongs to the user making it, and notes its source.
   * Live statuses are for users who may publish; anyone else's copies are
   * drafts, and Publishing holds a live copy back while its folio is not live.
   *
   * @param int    $folio_id
   * @param array  $items  As read_form() returns them.
   * @param string $status Status for the new pages.
   * @return int[] The new pages' IDs.
   */
  public static function create_pages(int $folio_id, array $items, string $status = 'draft'): array
  {
    $page_type = get_post_type_object('enrove_folio_page');
    if (
      !$page_type
      || !current_user_can($page_type->cap->edit_posts)
      || get_post_type($folio_id) !== 'enrove_folio'
      || !current_user_can('edit_post', $folio_id)
    ) {
      return array();
    }

    $status = in_array($status, array('draft', 'pending', 'publish', 'private'), true) ? $status : 'draft';
    if (in_array($status, array('publish', 'private'), true) && !current_user_can($page_type->cap->publish_posts)) {
      $status = 'draft';
    }

    $menu_order = static::get_last_menu_order($folio_id);
    $page_ids = array();

    foreach ($items as $item) {
      $source = $item['post'];
      if (has_blocks($source->post_content)) {
        $content = $source->post_content;
      } elseif (isset($item['content']) && is_string($item['content']) && $item['content'] !== '') {
        $content = $item['content'];
      } else {
        // What the converter would have made of it, short of the blocks.
        $content = wpautop(static::strip_scripts($source->post_content));
      }

      // Slashed, as wp_insert_post() unslashes what it is given.
      $page_id = wp_insert_post(wp_slash(array(
        'post_type' => 'enrove_folio_page',
        'post_status' => $status,
        'post_author' => get_current_user_id(),
        'post_title' => $source->post_title,
        'post_excerpt' => $source->post_excerpt,
        'post_content' => $content,
        'menu_order' => ++$menu_order,
        'meta_input' => array(
          'folio_id' => $folio_id,
          self::SOURCE_META => (int) $source->ID,
        ),
      )), true);

      if (is_wp_error($page_id) || !$page_id) {
        continue;
      }

      $thumbnail_id = (int) get_post_thumbnail_id($source);
      if ($thumbnail_id > 0) {
        set_post_thumbnail((int) $page_id, $thumbnail_id);
      }

      $page_ids[] = (int) $page_id;
    }

    return $page_ids;
  }

  // -----------------------------------------------------------------------
  // Helpers
  // -----------------------------------------------------------------------

  /**
   * The sources this folio already holds a copy of, trash left out.
   *
   * @param int $folio_id
   * @return int[]
   */
  public static function get_imported_source_ids(int $folio_id): array
  {
    $ids = array();
    foreach (Publishing::get_pages($folio_id, array('publish', 'future', 'draft', 'pending', 'private')) as $page) {
      $source_id = (int) get_post_meta($page->ID, self::SOURCE_META, true);
      if ($source_id > 0) {
        $ids[] = $source_id;
      }
    }

    return array_values(array_unique($ids));
  }

  /**
   * The highest menu_order among the folio's pages, trash included, so an
   * import goes after every page, even one about to be restored.
   *
   * @param int $folio_id
   * @return int
   */
  private static function get_last_menu_order(int $folio_id): int
  {
    $last = 0;
    foreach (Publishing::get_pages($folio_id, array('publish', 'future', 'draft', 'pending', 'private', 'trash')) as $page) {
      $last = max($last, (int) $page->menu_order);
    }

    return $last;
  }

  /**
   * Registered shortcodes in the content that stay shortcodes after import,
   * and so print as typed in a folio. A Shortcode block counts too. Only
   * registered tags are recognised: a bracketed word is text until a plugin
   * claims it.
   *
   * @param string $content
   * @return string[] Tag names.
   */
  public static function get_unconverted_shortcodes(string $content): array
  {
    global $shortcode_tags;

    $tags = array_diff(array_keys((array) $shortcode_tags), self::CONVERTED_SHORTCODES);
    if (!$tags || strpos($content, '[') === false) {
      return array();
    }

    $found = array();
    if (preg_match_all('/' . get_shortcode_regex($tags) . '/', $content, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        if (!($match[1] === '[' && $match[6] === ']')) {
          $found[] = $match[2];
        }
      }
    }

    return array_values(array_unique($found));
  }

  /**
   * Script and style elements removed whole, contents and all, as the
   * converter removes them, so a classic post copied without its blocks comes
   * out the same as one converted. A folio never prints them anyway
   * (Base_Theme::apply_embed_processing()).
   *
   * @param string $content
   * @return string
   */
  private static function strip_scripts(string $content): string
  {
    $stripped = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $content);

    // null when PCRE gives up on very large content; the content is copied as it is.
    return $stripped === null ? $content : $stripped;
  }

  /**
   * Whether a page builder laid this post out, by the builders' own markers.
   *
   * @param \WP_Post $post
   * @return bool
   */
  public static function uses_page_builder(\WP_Post $post): bool
  {
    foreach (self::BUILDER_META as $key => $value) {
      $stored = get_post_meta($post->ID, $key, true);
      if ($stored !== '' && ($value === '' || $stored === $value)) {
        return true;
      }
    }

    // WPBakery and Divi without their meta: their layout shortcodes.
    return (bool) preg_match('/\[(vc_row|et_pb_section)[\s\]]/', $post->post_content);
  }
}
