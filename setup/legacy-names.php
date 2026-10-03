<?php
namespace Enrove\Setup;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Legacy_Names
 *
 * The plugin was called Groove Folios before it was Enrove Folios, and every
 * name it stores followed: post types, the collection tag taxonomy, options,
 * meta keys, the uploads folder, four theme IDs and the eBook and Proposal
 * block names. This moves what a site already holds under the old names to the
 * new ones, once, so its folios, settings and downloads are still there.
 *
 * It runs from Plugin::__construct(), ahead of the theme registry and long
 * before the post types are registered on init, so nothing reads a new name
 * before the data is under it. That is plugin-include time: no translations,
 * and nothing here may throw.
 *
 * Every old name is spelled out. Nothing is matched by prefix, because another
 * plugin's data may start with the same word.
 *
 * @since 0.5.1
 */
class Legacy_Names
{
  /** Set once the move has run on this site. */
  const MIGRATION_OPTION = 'enrove_legacy_names_migration_v1';

  const POST_TYPES = array(
    'groove_folio' => 'enrove_folio',
    'groove_folio_page' => 'enrove_folio_page',
  );

  const TAXONOMIES = array(
    'groove_collection_tag' => 'enrove_collection_tag',
  );

  const OPTIONS = array(
    '_groove_installed_time' => '_enrove_installed_time',
    '_groove_removed_features_cleaned' => '_enrove_removed_features_cleaned',
    'groove_cpt_support' => 'enrove_cpt_support',
    'groove_default_allow_pdf_download' => 'enrove_default_allow_pdf_download',
    'groove_default_folio_status' => 'enrove_default_folio_status',
    'groove_default_folio_title' => 'enrove_default_folio_title',
    'groove_default_theme_id' => 'enrove_default_theme_id',
    'groove_folio_base_slug' => 'enrove_folio_base_slug',
    'groove_font_source' => 'enrove_font_source',
    'groove_page_publishing_migration_v1' => 'enrove_page_publishing_migration_v1',
    'groove_pexels_api_key' => 'enrove_pexels_api_key',
    'groove_photo_source' => 'enrove_photo_source',
    'groove_theme_migration_v1' => 'enrove_theme_migration_v1',
    'groove_usage_analytics' => 'enrove_usage_analytics',
  );

  const POST_META = array(
    '_groove_status_before_folio' => '_enrove_status_before_folio',
    '_groove_pexels_slug' => '_enrove_pexels_slug',
  );

  /** Stored per site with update_user_option(), so under the table prefix. */
  const USER_OPTIONS = array(
    'groove_setup_prompted' => 'enrove_setup_prompted',
  );

  /** A theme's ID follows its name. Folios store it as theme_id meta. */
  const THEME_IDS = array(
    'groove-ebook' => 'enrove-ebook',
    'groove-magazine' => 'enrove-magazine',
    'groove-newsletter' => 'enrove-newsletter',
    'groove-proposal' => 'enrove-proposal',
  );

  /** Block names are written into page content, as `wp:<namespace>/<block>`. */
  const BLOCK_NAMESPACES = array(
    'wp:groove-ebook/' => 'wp:enrove-ebook/',
    'wp:groove-proposal/' => 'wp:enrove-proposal/',
  );

  /** Folders under the uploads base directory. */
  const OLD_UPLOAD_DIR = 'groove-folios';
  const NEW_UPLOAD_DIR = 'enrove-folios';

  public static function migrate(): void
  {
    if (get_option(self::MIGRATION_OPTION)) {
      return;
    }

    global $wpdb;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- One-time bulk rename of the plugin's own stored names, guarded by the option above; core has no API for renaming a post type, a taxonomy, a meta key or an option, and the object cache is flushed below.
    foreach (self::POST_TYPES as $old => $new) {
      $wpdb->update($wpdb->posts, array('post_type' => $new), array('post_type' => $old));
    }

    foreach (self::TAXONOMIES as $old => $new) {
      $wpdb->update($wpdb->term_taxonomy, array('taxonomy' => $new), array('taxonomy' => $old));
    }

    foreach (self::OPTIONS as $old => $new) {
      // A value already under the new name is the newer one: keep it.
      $exists = $wpdb->get_var($wpdb->prepare("SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $new));
      if ($exists) {
        $wpdb->delete($wpdb->options, array('option_name' => $old));
      } else {
        $wpdb->update($wpdb->options, array('option_name' => $new), array('option_name' => $old));
      }
    }

    foreach (self::POST_META as $old => $new) {
      $wpdb->update($wpdb->postmeta, array('meta_key' => $new), array('meta_key' => $old));
    }

    $prefix = $wpdb->get_blog_prefix();
    foreach (self::USER_OPTIONS as $old => $new) {
      $wpdb->update($wpdb->usermeta, array('meta_key' => $prefix . $new), array('meta_key' => $prefix . $old));
    }

    foreach (self::THEME_IDS as $old => $new) {
      $wpdb->update(
        $wpdb->postmeta,
        array('meta_value' => $new),
        array('meta_key' => 'theme_id', 'meta_value' => $old)
      );
      $wpdb->update(
        $wpdb->options,
        array('option_value' => $new),
        array('option_name' => 'enrove_default_theme_id', 'option_value' => $old)
      );
    }

    // Revisions included, so restoring one does not bring an old name back.
    foreach (self::BLOCK_NAMESPACES as $old => $new) {
      $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
        $old,
        $new,
        '%' . $wpdb->esc_like($old) . '%'
      ));
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

    // The rows above changed underneath every cached post, term and option.
    wp_cache_flush();

    static::move_uploads();

    update_option(self::MIGRATION_OPTION, time());
  }

  /**
   * Move the downloaded fonts and photos to the new folder, and the theme
   * covers among the photos to their themes' new IDs. The font stylesheets
   * address their files relatively, so they survive the move as they are.
   * A site where this fails loses nothing: the old folder stays, and Download
   * Fonts and Download Photos fetch fresh copies into the new one.
   */
  private static function move_uploads(): void
  {
    $uploads = wp_get_upload_dir();
    if (empty($uploads['basedir'])) {
      return;
    }

    $old_dir = trailingslashit($uploads['basedir']) . self::OLD_UPLOAD_DIR;
    $new_dir = trailingslashit($uploads['basedir']) . self::NEW_UPLOAD_DIR;
    if (!is_dir($old_dir)) {
      return;
    }

    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
    $fs = new \WP_Filesystem_Direct(null);

    if (file_exists($new_dir) || !$fs->move($old_dir, $new_dir)) {
      return;
    }

    foreach (self::THEME_IDS as $old => $new) {
      $old_photo = $new_dir . '/photos/cover-' . $old . '.jpg';
      if (is_file($old_photo)) {
        $fs->move($old_photo, $new_dir . '/photos/cover-' . $new . '.jpg');
      }
    }
  }
}
