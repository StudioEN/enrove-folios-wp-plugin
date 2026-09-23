<?php
namespace Groove\Pexels;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Library
 *
 * Where the curated photos are on this site, and how they get there.
 *
 * A development checkout carries every photo in the plugin: the shared pool in
 * assets/images/pexels/ and each built-in theme's theme-cover.jpg. The
 * WordPress.org package carries none of them. The Pexels licence forbids
 * redistributing unaltered copies, and everything in the plugin directory has
 * to be redistributable under the GPL, so the two cannot meet. A site installed
 * from WordPress.org starts with no photos and gets its own copy only when an
 * administrator presses Download on Settings → Imagery.
 *
 * The download needs no API key. credits.json records, for every photo, the
 * src_url the curator fetched it from on Pexels's image CDN; this class fetches
 * the same bytes from the same address into uploads/groove-folios/photos/.
 *
 * Resolution for a slug is the bundled file first, then the downloaded copy.
 * url() is the exception that returns an address with nothing behind it yet:
 * sample content stores its image URLs once, at seed time, so a folio seeded
 * before the download points at the place the photo will land, and pressing
 * Download later fills it in. That is the same promise sample_image_url() made
 * when a missing file meant "the curator has not run".
 *
 * @since 0.4.0
 */
class Library
{
  /** Folder under the uploads base directory that holds downloaded photos. */
  const UPLOAD_SUBDIR = 'groove-folios/photos';

  /** The only host a download may come from. */
  const ALLOWED_HOST = 'images.pexels.com';

  /** Seconds per photo. They are 60–400 KB each. */
  const TIMEOUT = 20;

  /**
   * Absolute path of the photo shipped inside the plugin, whether or not it exists.
   *
   * Covers live in their theme's folder; credits.json records where. Everything
   * else is in the shared pool.
   *
   * @param string $slug
   * @return string  '' for an unusable slug.
   */
  public static function bundled_path(string $slug): string
  {
    $slug = sanitize_key($slug);
    if ($slug === '') {
      return '';
    }

    $credit = Credits::get($slug);
    $file = is_array($credit) && !empty($credit['file']) ? ltrim((string) $credit['file'], '/') : '';
    if ($file === '' || strpos($file, '..') !== false) {
      $file = 'assets/images/pexels/' . $slug . '.jpg';
    }

    return GROOVE_PATH . $file;
  }

  /**
   * Absolute path a downloaded copy lives at, whether or not it exists.
   *
   * @param string $slug
   * @return string
   */
  public static function downloaded_path(string $slug): string
  {
    $slug = sanitize_key($slug);
    $uploads = wp_get_upload_dir();
    if ($slug === '' || empty($uploads['basedir'])) {
      return '';
    }

    return trailingslashit($uploads['basedir']) . self::UPLOAD_SUBDIR . '/' . $slug . '.jpg';
  }

  /**
   * Public URL of the downloaded copy, whether or not it exists.
   *
   * @param string $slug
   * @return string
   */
  public static function downloaded_url(string $slug): string
  {
    $slug = sanitize_key($slug);
    $uploads = wp_get_upload_dir();
    if ($slug === '' || empty($uploads['baseurl'])) {
      return '';
    }

    return trailingslashit($uploads['baseurl']) . self::UPLOAD_SUBDIR . '/' . $slug . '.jpg';
  }

  /**
   * Absolute path of a photo that is actually on disk.
   *
   * @param string $slug
   * @return string  '' when neither copy exists.
   */
  public static function path(string $slug): string
  {
    foreach (array(static::bundled_path($slug), static::downloaded_path($slug)) as $path) {
      if ($path !== '' && is_readable($path)) {
        return $path;
      }
    }

    return '';
  }

  /**
   * Public URL of a photo: the bundled one when the plugin carries it, otherwise
   * where the download puts it (see the class comment for why that is returned
   * even before the download has happened).
   *
   * @param string $slug
   * @return string
   */
  public static function url(string $slug): string
  {
    $slug = sanitize_key($slug);
    if ($slug === '') {
      return '';
    }

    $bundled = static::bundled_path($slug);
    if ($bundled !== '' && is_readable($bundled)) {
      $relative = ltrim(substr(wp_normalize_path($bundled), strlen(wp_normalize_path(GROOVE_PATH))), '/');
      return GROOVE_URL . $relative;
    }

    return static::downloaded_url($slug);
  }

  /**
   * URL of a photo only when it is on disk — for places that would rather show
   * nothing than a broken image, such as a theme's cover.
   *
   * @param string $slug
   * @return string
   */
  public static function existing_url(string $slug): string
  {
    return static::path($slug) === '' ? '' : static::url($slug);
  }

  /**
   * Every photo credits.json knows about, and which of them this site has.
   *
   * @return array{total: int, present: int, missing: string[]}
   */
  public static function status(): array
  {
    $missing = array();
    $total = 0;

    foreach (Credits::all() as $slug => $credit) {
      if (!is_array($credit)) {
        continue;
      }
      $total++;
      if (static::path((string) $slug) === '') {
        $missing[] = (string) $slug;
      }
    }

    return array(
      'total' => $total,
      'present' => $total - count($missing),
      'missing' => $missing,
    );
  }

  /**
   * Fetch every photo this site does not have yet.
   *
   * Runs only from an explicit button press. Each file is checked to be a real
   * image before it is moved into place, so a failed or truncated response never
   * leaves a broken photo behind for sample content to point at.
   *
   * @return array{downloaded: int, failed: array<string, string>}
   */
  public static function download_missing(): array
  {
    $result = array('downloaded' => 0, 'failed' => array());
    $status = static::status();

    if (empty($status['missing'])) {
      return $result;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';

    $dir = dirname(static::downloaded_path($status['missing'][0]));
    if (!wp_mkdir_p($dir)) {
      $result['failed']['*'] = sprintf(
        /* translators: %s: directory path. */
        __('Could not create %s.', 'groove-folios'),
        $dir
      );
      return $result;
    }

    foreach ($status['missing'] as $slug) {
      $error = static::download($slug);
      if ($error === '') {
        $result['downloaded']++;
      } else {
        $result['failed'][$slug] = $error;
      }
    }

    return $result;
  }

  /**
   * Fetch one photo.
   *
   * @param string $slug
   * @return string  '' on success, otherwise the reason it failed.
   */
  protected static function download(string $slug): string
  {
    $credit = Credits::get($slug);
    $src = is_array($credit) && !empty($credit['src_url']) ? (string) $credit['src_url'] : '';

    if ($src === '' || wp_parse_url($src, PHP_URL_SCHEME) !== 'https' || wp_parse_url($src, PHP_URL_HOST) !== self::ALLOWED_HOST) {
      return __('credits.json has no Pexels download address for this photo.', 'groove-folios');
    }

    $temp = download_url($src, self::TIMEOUT);
    if (is_wp_error($temp)) {
      return $temp->get_error_message();
    }

    $size = getimagesize($temp);
    if (!is_array($size) || empty($size[0]) || (isset($size[2]) && $size[2] !== IMAGETYPE_JPEG)) {
      wp_delete_file($temp);
      return __('The download was not a JPEG image.', 'groove-folios');
    }

    $destination = static::downloaded_path($slug);
    $moved = copy($temp, $destination);
    wp_delete_file($temp);

    if (!$moved) {
      return sprintf(
        /* translators: %s: file path. */
        __('Could not write %s.', 'groove-folios'),
        $destination
      );
    }

    return '';
  }
}
