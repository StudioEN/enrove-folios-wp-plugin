<?php
namespace Groove\Themes;

use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Where the folio fonts are on this site, and how they get there.
 *
 * Folios are typeset in open-licence families from Google Fonts. Loading them
 * from Google's CDN when a folio renders would hand every reader's IP address
 * to Google without their say, which WordPress.org counts as phoning home. So
 * a folio only ever loads fonts from this site: an administrator presses
 * Download Fonts on Settings → Fonts once, and this class fetches every family
 * the plugin can use into uploads/groove-folios/fonts/. Until then, and for any
 * family that failed, a folio falls through to the rest of its font stack.
 *
 * One family fragment (the `google_family` value from Utils or a theme's
 * setup.php) becomes one stylesheet, <slug>.css, plus the woff2 files it
 * names, <slug>-<n>.woff2, side by side. The stylesheet is written last, so it
 * existing is what "this family is here" means: a download that stopped half
 * way leaves no stylesheet and is simply tried again.
 *
 * @since 0.5.1
 */
class Font_Library
{
  /** Folder under the uploads base directory that holds the fonts. */
  const UPLOAD_SUBDIR = 'groove-folios/fonts';

  /** The CSS API a download asks, and the only host a font file may come from. */
  const API_BASE = 'https://fonts.googleapis.com/css2';
  const FILE_HOST = 'fonts.gstatic.com';

  /**
   * The css2 API picks its format by user agent, and only a current browser
   * gets woff2 with unicode-range subsets. Sent on the stylesheet request only.
   */
  const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

  /** Seconds per request. Font files are 10–80 KB each. */
  const TIMEOUT = 20;

  /**
   * Seconds one press may spend before it stops starting new families. About
   * 110 files in all; a slow host finishes on a second press rather than
   * hitting max_execution_time half way through a family.
   */
  const TIME_BUDGET = 20;

  /**
   * Option recording the administrator's answer to "use Google's fonts?":
   * 'google', 'system', or '' for not asked yet. 'system' is a real choice,
   * not a missing download: folios then use the system fonts WordPress's own
   * dashboard uses, even for families that happen to be on disk.
   */
  const SOURCE_OPTION = 'groove_font_source';
  const SOURCE_GOOGLE = 'google';
  const SOURCE_SYSTEM = 'system';

  // ── Where ──────────────────────────────────────────────────────────────

  /**
   * File-name stem for a family fragment: the family name, plus a hash of the
   * whole fragment, since one family is requested with different axes.
   *
   * @param string $family e.g. 'Inter:wght@400;700'.
   * @return string e.g. 'inter-3f2a91c0'. '' for an empty fragment.
   */
  public static function slug(string $family): string
  {
    if ($family === '') {
      return '';
    }

    $name = sanitize_title(str_replace('+', ' ', (string) strtok($family, ':')));

    return ($name !== '' ? $name : 'font') . '-' . substr(md5($family), 0, 8);
  }

  /**
   * Absolute path of the fonts folder, with a trailing slash.
   *
   * @return string '' when the uploads folder cannot be resolved.
   */
  public static function dir(): string
  {
    $uploads = wp_get_upload_dir();

    return empty($uploads['basedir']) ? '' : trailingslashit($uploads['basedir']) . self::UPLOAD_SUBDIR . '/';
  }

  /**
   * Public URL of a family's stylesheet, whether or not it exists.
   *
   * @param string $family
   * @return string
   */
  public static function css_url(string $family): string
  {
    $uploads = wp_get_upload_dir();
    $slug = static::slug($family);

    if ($slug === '' || empty($uploads['baseurl'])) {
      return '';
    }

    return trailingslashit($uploads['baseurl']) . self::UPLOAD_SUBDIR . '/' . $slug . '.css';
  }

  /**
   * Absolute path of a family's stylesheet, whether or not it exists.
   *
   * @param string $family
   * @return string
   */
  public static function css_path(string $family): string
  {
    $dir = static::dir();
    $slug = static::slug($family);

    return ($dir === '' || $slug === '') ? '' : $dir . $slug . '.css';
  }

  /**
   * Whether a family has been downloaded to this site.
   *
   * @param string $family
   * @return bool
   */
  public static function has(string $family): bool
  {
    $path = static::css_path($family);

    return $path !== '' && is_readable($path);
  }

  /**
   * The administrator's font choice; see SOURCE_OPTION.
   *
   * @return string 'google', 'system' or ''.
   */
  public static function source(): string
  {
    $source = (string) get_option(self::SOURCE_OPTION, '');

    return in_array($source, array(self::SOURCE_GOOGLE, self::SOURCE_SYSTEM), true) ? $source : '';
  }

  /**
   * Record the administrator's font choice.
   *
   * @param string $source SOURCE_GOOGLE or SOURCE_SYSTEM.
   */
  public static function set_source(string $source): void
  {
    if (in_array($source, array(self::SOURCE_GOOGLE, self::SOURCE_SYSTEM), true)) {
      update_option(self::SOURCE_OPTION, $source, true);
    }
  }

  /**
   * Whether a folio may load this family: it is on this site, and the
   * administrator has not chosen system fonts instead.
   *
   * @param string $family
   * @return bool
   */
  public static function is_usable(string $family): bool
  {
    return static::source() !== self::SOURCE_SYSTEM && static::has($family);
  }

  // ── What ───────────────────────────────────────────────────────────────

  /**
   * Every family fragment a folio on this site can ask for: the fixed list a
   * folio picks from, and every registered theme's defaults.
   *
   * @return string[]
   */
  public static function families(): array
  {
    $families = array();

    foreach (Utils::get_supported_primary_fonts() as $font) {
      $families[] = (string) ($font['google_family'] ?? '');
    }

    foreach (array_keys(Themes_Manager::get_all_themes()) as $theme_id) {
      foreach (Themes_Manager::get_theme_default_fonts((string) $theme_id) as $font) {
        $families[] = (string) ($font['google_family'] ?? '');
      }
    }

    return array_values(array_unique(array_filter($families)));
  }

  /**
   * Which families this site has.
   *
   * @return array{total: int, present: int, missing: string[]}
   */
  public static function status(): array
  {
    $families = static::families();
    $missing = array_values(array_filter($families, static function ($family) {
      return !static::has($family);
    }));

    return array(
      'total' => count($families),
      'present' => count($families) - count($missing),
      'missing' => $missing,
    );
  }

  // ── How ────────────────────────────────────────────────────────────────

  /**
   * Fetch every family this site does not have yet.
   *
   * Runs only from an explicit button press. Stops starting new families once
   * the time budget has passed; those come back as `remaining`, for the next
   * press — or the next step, when the setup dialog drives it in batches.
   *
   * @param int $time_budget Seconds before no new family is started.
   * @return array{downloaded: int, failed: array<string, string>, remaining: int}
   */
  public static function download_missing(int $time_budget = self::TIME_BUDGET): array
  {
    $result = array('downloaded' => 0, 'failed' => array(), 'remaining' => 0);
    $missing = static::status()['missing'];

    if (empty($missing)) {
      return $result;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';

    $dir = static::dir();
    if ($dir === '' || !wp_mkdir_p($dir)) {
      $result['failed']['*'] = sprintf(
        /* translators: %s: directory path. */
        __('Could not create %s.', 'groove-folios'),
        $dir !== '' ? $dir : 'uploads/' . self::UPLOAD_SUBDIR
      );
      return $result;
    }

    $started = microtime(true);

    foreach ($missing as $index => $family) {
      if (microtime(true) - $started > $time_budget) {
        $result['remaining'] = count($missing) - $index;
        break;
      }

      $error = static::download($family);
      if ($error === '') {
        $result['downloaded']++;
      } else {
        $result['failed'][$family] = $error;
      }
    }

    return $result;
  }

  /**
   * Fetch one family: its stylesheet from the CSS API, then every woff2 file
   * it names, then the stylesheet again, rewritten to name the local copies.
   *
   * @param string $family
   * @return string '' on success, otherwise the reason it failed.
   */
  protected static function download(string $family): string
  {
    $label = str_replace('+', ' ', (string) strtok($family, ':'));

    // The fragment is already in the API's own grammar (Font_Loader sanitised
    // it), and add_query_arg() would encode the `:`, `;` and `@` it relies on.
    $response = wp_remote_get(self::API_BASE . '?family=' . $family . '&display=swap', array(
      'timeout' => self::TIMEOUT,
      'user-agent' => self::USER_AGENT,
    ));

    if (is_wp_error($response)) {
      return $response->get_error_message();
    }

    $css = (string) wp_remote_retrieve_body($response);
    if ((int) wp_remote_retrieve_response_code($response) !== 200 || $css === '') {
      return sprintf(
        /* translators: 1: font family name, 2: HTTP status code. */
        __('Google Fonts did not return %1$s (HTTP %2$s).', 'groove-folios'),
        $label,
        (string) wp_remote_retrieve_response_code($response)
      );
    }

    preg_match_all('#url\((https://' . preg_quote(self::FILE_HOST, '#') . '/[^)\s\'"]+\.woff2)\)#', $css, $matches);
    $urls = array_values(array_unique($matches[1]));

    if (empty($urls)) {
      return sprintf(
        /* translators: %s: font family name. */
        __('Google Fonts named no font files for %s.', 'groove-folios'),
        $label
      );
    }

    $fs = static::filesystem();
    // Passed to every write: with no mode, the direct class falls back to
    // FS_CHMOD_FILE, which core defines only once WP_Filesystem() connects.
    // This is the value core would give it.
    $mode = defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : (fileperms(ABSPATH . 'index.php') & 0777 | 0644);
    $slug = static::slug($family);
    $dir = static::dir();
    $local = array();

    foreach ($urls as $n => $url) {
      $temp = download_url($url, self::TIMEOUT);
      if (is_wp_error($temp)) {
        return $temp->get_error_message();
      }

      // Every woff2 file opens with this signature. Anything else — an error
      // page, a truncated body — is not kept.
      if (substr((string) $fs->get_contents($temp), 0, 4) !== 'wOF2') {
        wp_delete_file($temp);
        return sprintf(
          /* translators: %s: font family name. */
          __('A file Google Fonts sent for %s was not a font.', 'groove-folios'),
          $label
        );
      }

      $name = $slug . '-' . $n . '.woff2';
      $copied = $fs->copy($temp, $dir . $name, true, $mode);
      wp_delete_file($temp);

      if (!$copied) {
        return sprintf(
          /* translators: %s: file path. */
          __('Could not write %s.', 'groove-folios'),
          $dir . $name
        );
      }

      $local[$url] = $name;
    }

    // Relative URLs, so they resolve against the stylesheet's own address,
    // whatever domain or scheme the site is reached on.
    $css = str_replace(array_keys($local), array_values($local), $css);

    // The API's response is @font-face rules and comments. Nothing that still
    // points off this site is written out.
    if (preg_match('#url\(\s*[\'"]?(?:https?:)?//#i', $css) || stripos($css, '@import') !== false) {
      return sprintf(
        /* translators: %s: font family name. */
        __('The Google Fonts stylesheet for %s had something in it other than fonts.', 'groove-folios'),
        $label
      );
    }

    $css = '/* ' . $label . ', from Google Fonts (fonts.google.com), served from this site. */' . "\n" . $css;
    if (!$fs->put_contents($dir . $slug . '.css', $css, $mode)) {
      return sprintf(
        /* translators: %s: file path. */
        __('Could not write %s.', 'groove-folios'),
        $dir . $slug . '.css'
      );
    }

    return '';
  }

  /**
   * Delete every downloaded family and forget the font choice, which puts the
   * site back where a fresh install starts: nothing on disk, nothing asked, so
   * the setup dialog's entry points offer the choice again. Only this plugin's
   * own folder under uploads is touched.
   *
   * @return int The number of families that were on this site.
   */
  public static function remove_downloaded(): int
  {
    $removed = count(array_filter(static::families(), static function ($family) {
      return static::has($family);
    }));

    $dir = static::dir();
    // dir() is built from UPLOAD_SUBDIR; the check keeps a recursive delete
    // from ever reaching anything else should that change.
    if ($dir !== '' && substr($dir, -strlen(self::UPLOAD_SUBDIR . '/')) === self::UPLOAD_SUBDIR . '/' && is_dir($dir)) {
      static::filesystem()->delete($dir, true);
    }

    delete_option(self::SOURCE_OPTION);

    return $removed;
  }

  /**
   * WordPress's direct filesystem: every path here is under uploads, which
   * WordPress itself writes to directly, so an FTP-configured site must not
   * route these through a connection that may need credentials.
   *
   * @return \WP_Filesystem_Direct
   */
  protected static function filesystem(): \WP_Filesystem_Direct
  {
    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

    return new \WP_Filesystem_Direct(null);
  }
}
