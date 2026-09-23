<?php
namespace Groove\Themes;

use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * The single place Groove loads web fonts.
 *
 * Two sources feed it, in priority order:
 *
 *   1. The folio's own choice — `header_font` / `body_font` meta, validated
 *      against the fixed list in Utils::get_supported_primary_fonts().
 *   2. The theme's declared defaults — the optional `fonts` block in the
 *      theme's setup.php (see themes/README.md).
 *
 * Whatever wins, the families for both roles are requested in ONE css2
 * stylesheet, enqueued through wp_enqueue_style() so WordPress can dedupe,
 * order and dns-prefetch it, and paired with a preconnect to the font-file
 * host. Nothing is ever loaded with @import or a hand-written <link>.
 *
 * Fonts are only ever loaded on a surface that actually renders a theme:
 * a folio cover/page (published or previewed), the theme-picker preview, the
 * password gate, and the block editor for a folio page. There is no
 * plugin-wide or admin-wide font load.
 */
class Font_Loader
{
  /** Handle of the combined Google Fonts stylesheet. */
  const HANDLE = 'groove-folio-fonts';

  /** The CSS API host, and the second origin the font files themselves come from. */
  const API_HOST  = 'https://fonts.googleapis.com';
  const API_BASE  = self::API_HOST . '/css2';
  const FILE_HOST = 'https://fonts.gstatic.com';

  /** Roots the CSS variables are injected on when a theme renders. */
  const FRONTEND_SELECTOR = '.g-folio__theme-cover, body.groove [class*="g-folio__theme-"][class$="-page"]';

  /** The two typographic roles a folio can set. */
  const ROLES = ['header', 'body'];

  /** @var bool Whether the preconnect hint has been registered this request. */
  protected static $hinted = false;

  // ── Theme defaults ─────────────────────────────────────────────────────

  /**
   * Normalise the optional `fonts` block from a theme's setup.php.
   *
   * Expected shape — the same vocabulary as Utils::get_supported_primary_fonts():
   *
   *   'fonts' => [
   *     'header' => [
   *       'css_stack'     => "'Fraunces', Georgia, serif",
   *       'google_family' => 'Fraunces:ital,wght@0,300;0,400',  // omit for a system stack
   *     ],
   *     'body' => [ … ],
   *   ]
   *
   * Themes can ship as installable packages, so both values are sanitised
   * rather than trusted.
   *
   * @param mixed $fonts Raw value from setup.php.
   * @return array Roles that declared a usable stack.
   */
  public static function normalize_theme_fonts($fonts): array
  {
    if (!is_array($fonts)) {
      return [];
    }

    $normalized = [];

    foreach (self::ROLES as $role) {
      if (empty($fonts[$role]) || !is_array($fonts[$role])) {
        continue;
      }

      $css_stack = self::sanitize_css_stack($fonts[$role]['css_stack'] ?? '');
      if ($css_stack === '') {
        continue;
      }

      $normalized[$role] = [
        'css_stack'     => $css_stack,
        'google_family' => self::sanitize_google_family($fonts[$role]['google_family'] ?? ''),
      ];
    }

    return $normalized;
  }

  /**
   * A font stack is a CSS value we echo inline, so keep it to the characters
   * a family list can legitimately contain — no braces, semicolons or parens.
   */
  protected static function sanitize_css_stack($value): string
  {
    if (!is_string($value)) {
      return '';
    }

    $value = preg_replace('/[^A-Za-z0-9 ,\'\-_.]/', '', $value);

    return trim((string) $value);
  }

  /**
   * A `family=` fragment for the css2 API, e.g. `Source+Serif+4:opsz,wght@8..60,400`.
   * Spaces become `+`; everything outside the API's grammar is dropped.
   */
  protected static function sanitize_google_family($value): string
  {
    if (!is_string($value)) {
      return '';
    }

    $value = str_replace(' ', '+', trim($value));
    $value = preg_replace('/[^A-Za-z0-9+:,;@.]/', '', $value);

    return (string) $value;
  }

  // ── Resolution ─────────────────────────────────────────────────────────

  /**
   * Work out the final font for each role: the folio's choice, else the
   * theme's default, else nothing (the theme CSS fallback stack applies).
   *
   * @param int   $folio_id     Folio whose font meta applies. 0 for previews with no folio.
   * @param array $theme_fonts  Output of normalize_theme_fonts().
   * @return array Role => ['css_stack' => string, 'google_family' => string]
   */
  public static function resolve(int $folio_id, array $theme_fonts): array
  {
    $folio_keys = $folio_id > 0
      ? Utils::get_folio_font_keys($folio_id)
      : ['header' => '', 'body' => ''];

    $resolved = [];

    foreach (self::ROLES as $role) {
      $chosen = Utils::get_primary_font_data($folio_keys[$role] ?? '');

      if ($chosen) {
        $resolved[$role] = [
          'css_stack'     => $chosen['css_stack'],
          'google_family' => $chosen['google_family'],
        ];
        continue;
      }

      if (!empty($theme_fonts[$role])) {
        $resolved[$role] = $theme_fonts[$role];
      }
    }

    return $resolved;
  }

  /**
   * One css2 request covering every family the resolved roles need.
   *
   * @param array $resolved Output of resolve().
   * @return string Empty when nothing needs fetching (all system stacks).
   */
  public static function build_url(array $resolved): string
  {
    $families = [];

    foreach (self::ROLES as $role) {
      $family = $resolved[$role]['google_family'] ?? '';
      if ($family !== '' && !in_array($family, $families, true)) {
        $families[] = $family;
      }
    }

    if (empty($families)) {
      return '';
    }

    return self::API_BASE . '?family=' . implode('&family=', $families) . '&display=swap';
  }

  /**
   * The custom properties theme CSS consumes.
   *
   * `--g-folio-primary-font` is the pre-role-split name and stays aliased to
   * the body font for older theme CSS (groove-ebook still reads it).
   *
   * @param array  $resolved Output of resolve().
   * @param string $selector Root(s) the properties are set on.
   * @return string Empty when nothing resolved.
   */
  public static function build_inline_css(array $resolved, string $selector): string
  {
    $declarations = [];

    if (!empty($resolved['header']['css_stack'])) {
      $declarations[] = '--g-folio-header-font: ' . $resolved['header']['css_stack'];
    }

    if (!empty($resolved['body']['css_stack'])) {
      $declarations[] = '--g-folio-body-font: ' . $resolved['body']['css_stack'];
      $declarations[] = '--g-folio-primary-font: var(--g-folio-body-font)';
      $declarations[] = 'font-family: var(--g-folio-body-font)';
    }

    if (empty($declarations)) {
      return '';
    }

    return $selector . ' { ' . implode('; ', $declarations) . '; }';
  }

  // ── Enqueue ────────────────────────────────────────────────────────────

  /**
   * Load the resolved fonts and publish their CSS variables.
   *
   * @param array  $resolved       Output of resolve().
   * @param string $inline_handle  Style handle the variables ride on. Registered
   *                               as an empty handle when it does not exist yet.
   * @param string $selector       Root(s) the variables are set on.
   */
  public static function enqueue(array $resolved, string $inline_handle, string $selector = self::FRONTEND_SELECTOR): void
  {
    if (empty($resolved)) {
      return;
    }

    $url = self::build_url($resolved);

    if ($url !== '') {
      // No version on a CDN URL: the query string is the version. Adding one is
      // not harmless either: before WordPress 7.0 the `ver` goes on through
      // add_query_arg(), which keeps only the last repeated `family=` parameter.
      wp_enqueue_style(self::HANDLE, $url, [], null); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- CDN URL whose query string is the version; a `ver` drops families on WP < 7.0.
      self::add_preconnect();
    }

    $inline_css = self::build_inline_css($resolved, $selector);
    if ($inline_css === '') {
      return;
    }

    if (!wp_style_is($inline_handle, 'registered')) {
      wp_register_style($inline_handle, false, [], GROOVE_VERSION);
    }

    wp_enqueue_style($inline_handle);
    wp_add_inline_style($inline_handle, $inline_css);
  }

  /**
   * Preconnect to the font-file host. WordPress already dns-prefetches
   * fonts.googleapis.com for us once the stylesheet above is enqueued, but the
   * woff2 files come from a second origin that nothing hints at.
   *
   * Registered only when a stylesheet was actually enqueued, and only once.
   */
  protected static function add_preconnect(): void
  {
    if (self::$hinted) {
      return;
    }

    self::$hinted = true;

    add_filter('wp_resource_hints', [__CLASS__, 'filter_resource_hints'], 10, 2);
  }

  /**
   * @param array  $urls
   * @param string $relation_type
   * @return array
   */
  public static function filter_resource_hints($urls, $relation_type)
  {
    if ('preconnect' === $relation_type) {
      $urls[] = [
        'href'        => self::FILE_HOST,
        'crossorigin' => 'anonymous',
      ];
    }

    return $urls;
  }
}
