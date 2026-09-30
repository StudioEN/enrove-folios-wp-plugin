<?php
namespace Groove\Utils;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * View parameters on Groove's admin screens: which folio is open, which tab,
 * and a list's filter, search, sort and page.
 *
 * Every link and GET form that carries one is built here and signed with a
 * view nonce, and every read of one comes back through read(), which verifies
 * that nonce before it touches the value. A link without a valid nonce (a
 * bookmark, one older than a nonce lives, one from before a logout) reads as
 * if the parameter were absent, so the screen opens on its default view. The
 * nonce is not authorization: each screen still checks the user's capability
 * against whatever the parameter names.
 *
 * Action outcomes (bulk_action, groove_reset, message…) ride on the same
 * signed redirect, so they are read the same way.
 */
final class Request
{
  /** Nonce action for view links. */
  const NONCE_ACTION = 'groove_view';

  /** Query argument that carries it. */
  const NONCE_ARG = '_groove_view';

  /**
   * A signed admin.php URL for one of Groove's screens.
   *
   * @param string $page PAGE_ID of the screen.
   * @param array  $args Query arguments; null and '' values are left out.
   * @return string Unescaped; escape it where it is printed.
   */
  public static function admin_url(string $page, array $args = array()): string
  {
    $args = array_filter($args, function ($value) {
      return $value !== null && $value !== '';
    });

    return self::sign(add_query_arg(array_merge(array('page' => $page), $args), admin_url('admin.php')));
  }

  /**
   * Add the view nonce to a URL.
   *
   * @param string $url
   * @return string Unescaped.
   */
  public static function sign(string $url): string
  {
    return add_query_arg(self::NONCE_ARG, wp_create_nonce(self::NONCE_ACTION), $url);
  }

  /** The view nonce as a hidden field, for a GET form that carries view parameters. */
  public static function nonce_field(): void
  {
    wp_nonce_field(self::NONCE_ACTION, self::NONCE_ARG, false);
  }

  /** Whether a signed request carries this parameter at all. */
  public static function has(string $name): bool
  {
    return self::read($name, 'key') !== null;
  }

  /** A key-like parameter (sanitize_key()), or $default. */
  public static function key(string $name, string $default = ''): string
  {
    $value = self::read($name, 'key');

    return is_string($value) && $value !== '' ? $value : $default;
  }

  /** A key-like parameter from an allow-list, or $default. */
  public static function choice(string $name, array $allowed, string $default): string
  {
    $value = self::key($name, $default);

    return in_array($value, $allowed, true) ? $value : $default;
  }

  /** A non-negative integer parameter (absint()), or $default. */
  public static function int(string $name, int $default = 0): int
  {
    $value = self::read($name, 'int');

    return is_int($value) ? $value : $default;
  }

  /** A text parameter (sanitize_text_field()), or $default. */
  public static function text(string $name, string $default = ''): string
  {
    $value = self::read($name, 'text');

    return is_string($value) ? $value : $default;
  }

  /**
   * A parameter that may be a single slug or a list of them
   * (sanitize_title() each), empty ones dropped.
   *
   * @return string[]
   */
  public static function slugs(string $name): array
  {
    $value = self::read($name, 'slugs');

    return is_array($value) ? $value : array();
  }

  /**
   * One view parameter, sanitized, or null when it is absent or the request
   * does not carry a valid view nonce. The nonce is verified before the
   * value is read.
   *
   * @param string $name
   * @param string $type key, int, text or slugs.
   * @return string|int|string[]|null
   */
  private static function read(string $name, string $type)
  {
    if (!isset($_GET[self::NONCE_ARG], $_GET[$name])) {
      return null;
    }
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET[self::NONCE_ARG])), self::NONCE_ACTION)) {
      return null;
    }

    switch ($type) {
      case 'int':
        return is_array($_GET[$name]) ? null : absint(wp_unslash($_GET[$name]));
      case 'text':
        return is_array($_GET[$name]) ? null : sanitize_text_field(wp_unslash($_GET[$name]));
      case 'slugs':
        // map_deep(), not array_map(): a nested array must not reach
        // sanitize_title() as an array. Anything nested is then dropped.
        $slugs = (array) map_deep(wp_unslash($_GET[$name]), 'sanitize_title');
        return array_values(array_unique(array_filter($slugs, function ($slug) {
          return is_string($slug) && $slug !== '';
        })));
      default:
        return is_array($_GET[$name]) ? null : sanitize_key(wp_unslash($_GET[$name]));
    }
  }
}
