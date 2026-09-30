<?php
namespace Groove\Setup;

use Groove\Themes\Font_Library;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Reset: put the plugin back the way it was when it was first installed.
 *
 * For troubleshooting, and for trying the first-run setup again. It clears
 * what the plugin itself keeps — its settings, its downloads, and the record
 * that each administrator has seen the setup dialog — and, only when asked,
 * the folios people made with it. Folios are content, so keeping them is the
 * default and deleting them is a separate, explicit answer.
 *
 * What it leaves alone, on purpose:
 * - the markers of one-time migrations and clean-ups (_groove_installed_time,
 *   _groove_removed_features_cleaned, groove_theme_migration_v1,
 *   groove_page_publishing_migration_v1). They record
 *   that old data was already converted, and clearing them would only run the
 *   conversions again over folios that do not need it;
 * - Media Library items, including sample images copied there when a folio
 *   was seeded: attachments may be used anywhere on the site;
 * - a Pexels key set in wp-config.php, the environment or a .pexels-key file,
 *   which the plugin does not own. The key stored in its own setting goes.
 *
 * @since 0.5.1
 */
class Reset
{
  /** Every setting the plugin stores for this site. */
  const OPTIONS = array(
    'groove_default_theme_id',
    'groove_default_folio_status',
    'groove_default_folio_title',
    'groove_default_allow_pdf_download',
    'groove_folio_base_slug',
    'groove_cpt_support',
    'groove_pexels_api_key',
  );

  const TAXONOMY = 'groove_collection_tag';

  /**
   * How much content a reset could delete: every folio and folio page in any
   * status, trash included, and every collection tag.
   *
   * @return array{folios: int, pages: int, tags: int}
   */
  public static function content_counts(): array
  {
    $tags = wp_count_terms(array('taxonomy' => self::TAXONOMY, 'hide_empty' => false));

    return array(
      'folios' => static::count_posts('groove_folio'),
      'pages' => static::count_posts('groove_folio_page'),
      'tags' => is_wp_error($tags) ? 0 : (int) $tags,
    );
  }

  /**
   * @param string $post_type
   * @return int Posts of that type in every status except auto-draft, which
   *             nobody made on purpose.
   */
  private static function count_posts(string $post_type): int
  {
    $total = 0;

    foreach ((array) wp_count_posts($post_type) as $status => $count) {
      if ($status !== 'auto-draft') {
        $total += (int) $count;
      }
    }

    return $total;
  }

  /**
   * Reset the plugin.
   *
   * @param bool $keep_content Keep folios, their pages and collection tags.
   * @return array{folios: int, pages: int, tags: int} What was deleted; all
   *         zero when the content was kept.
   */
  public static function run(bool $keep_content): array
  {
    foreach (self::OPTIONS as $option) {
      delete_option($option);
    }

    // Each clears its download and forgets its choice.
    Font_Library::remove_downloaded();
    if (class_exists('\Groove\Pexels\Library')) {
      \Groove\Pexels\Library::remove_downloaded();
    }

    // The two downloads share a parent folder; leave nothing behind when it
    // is empty. rmdir() without the recursive flag refuses a folder with
    // anything else in it.
    $uploads = wp_get_upload_dir();
    if (!empty($uploads['basedir'])) {
      $parent = trailingslashit($uploads['basedir']) . 'groove-folios';
      if (is_dir($parent)) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        (new \WP_Filesystem_Direct(null))->rmdir($parent, false);
      }
    }

    // Every administrator of this site is asked again. update_user_option()
    // stores the flag under the site's table prefix, so that is the key.
    global $wpdb;
    delete_metadata('user', 0, $wpdb->get_blog_prefix() . First_Run::PROMPTED_META, '', true);

    $deleted = array('folios' => 0, 'pages' => 0, 'tags' => 0);
    if ($keep_content) {
      return $deleted;
    }

    // Every status, trash and auto-drafts included, so nothing is left behind
    // to surface later. Pages first, so none is ever without its folio.
    foreach (array('groove_folio_page' => 'pages', 'groove_folio' => 'folios') as $post_type => $key) {
      $ids = get_posts(array(
        'post_type' => $post_type,
        'post_status' => array_keys(get_post_stati()),
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
      ));

      foreach ($ids as $id) {
        // Counted the way content_counts() counts, so the outcome matches
        // the numbers the confirmation showed.
        $counted = get_post_status((int) $id) !== 'auto-draft';
        if (wp_delete_post((int) $id, true) && $counted) {
          $deleted[$key]++;
        }
      }
    }

    $terms = get_terms(array(
      'taxonomy' => self::TAXONOMY,
      'hide_empty' => false,
      'fields' => 'ids',
    ));

    if (!is_wp_error($terms)) {
      foreach ($terms as $term_id) {
        if (wp_delete_term((int) $term_id, self::TAXONOMY) === true) {
          $deleted['tags']++;
        }
      }
    }

    return $deleted;
  }
}
