<?php
namespace Groove\Pexels;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Key
 *
 * Resolves the Pexels API key from the first available source, in order:
 *
 *   1. the GROOVE_PEXELS_API_KEY constant (wp-config.php)
 *   2. the PEXELS_API_KEY environment variable
 *   3. a single-line `.pexels-key` file at the plugin root
 *   4. the `groove_pexels_api_key` option
 *
 * The key is only ever read by the curation path. Nothing in this class
 * returns, logs or renders the full value except resolve() itself — use
 * masked() for anything a human will see.
 *
 * @since 0.2.0
 */
class Key
{

  /** Name of the wp-config.php constant that always wins. */
  const CONSTANT = 'GROOVE_PEXELS_API_KEY';

  /** Name of the environment variable checked second. */
  const ENV_VAR = 'PEXELS_API_KEY';

  /** Filename (relative to the plugin root) checked third. */
  const KEY_FILE = '.pexels-key';

  /** Option name checked last. Stored with autoload = no. */
  const OPTION = 'groove_pexels_api_key';

  // ── Public API ───────────────────────────────────────────────────────────

  /**
   * Resolve the API key.
   *
   * @return string The key, or '' when no source supplied one.
   */
  public static function resolve(): string
  {
    $found = self::detect();

    return $found['key'];
  }

  /**
   * Which source supplied the key.
   *
   * @return string One of 'constant', 'env', 'file', 'option', or '' when unset.
   */
  public static function source(): string
  {
    $found = self::detect();

    return $found['source'];
  }

  /**
   * Whether a non-empty key is pinned by the wp-config.php constant.
   *
   * When true the settings field should be shown read-only: the option can
   * still be stored, but it will never be used.
   *
   * @return bool
   */
  public static function is_locked_by_constant(): bool
  {
    return '' !== self::constant_value();
  }

  /**
   * A display-safe rendering of the key: bullets plus the last four chars.
   *
   * @return string '••••••••abcd', or '' when no key is set.
   */
  public static function masked(): string
  {
    $key = self::resolve();
    if ('' === $key) {
      return '';
    }

    $tail = strlen($key) > 4 ? substr($key, -4) : $key;

    return str_repeat('•', 8) . $tail;
  }

  // ── Helpers (additive — not part of the frozen contract) ─────────────────

  /**
   * Whether any source supplied a key.
   *
   * @return bool
   */
  public static function exists(): bool
  {
    return '' !== self::resolve();
  }

  /**
   * Absolute path to the optional `.pexels-key` file.
   *
   * @return string
   */
  public static function file_path(): string
  {
    return self::plugin_path() . self::KEY_FILE;
  }

  /**
   * Human-readable, translated label for the active source.
   *
   * @return string Empty string when no key is set.
   */
  public static function source_label(): string
  {
    switch (self::source()) {
      case 'constant':
        return sprintf(
          /* translators: %s: PHP constant name. */
          __('the %s constant in wp-config.php', 'groove-folios'),
          self::CONSTANT
        );
      case 'env':
        return sprintf(
          /* translators: %s: environment variable name. */
          __('the %s environment variable', 'groove-folios'),
          self::ENV_VAR
        );
      case 'file':
        return sprintf(
          /* translators: %s: filename. */
          __('the %s file at the plugin root', 'groove-folios'),
          self::KEY_FILE
        );
      case 'option':
        return __('the Groove settings screen', 'groove-folios');
      default:
        return '';
    }
  }

  // ── Internals ────────────────────────────────────────────────────────────

  /**
   * Walk the sources in priority order.
   *
   * Deliberately not memoised: the settings screen saves the option and then
   * verifies it within the same request.
   *
   * @return array ['key' => string, 'source' => string]
   */
  private static function detect(): array
  {
    $constant = self::constant_value();
    if ('' !== $constant) {
      return ['key' => $constant, 'source' => 'constant'];
    }

    $env = self::env_value();
    if ('' !== $env) {
      return ['key' => $env, 'source' => 'env'];
    }

    $file = self::file_value();
    if ('' !== $file) {
      return ['key' => $file, 'source' => 'file'];
    }

    $option = self::option_value();
    if ('' !== $option) {
      return ['key' => $option, 'source' => 'option'];
    }

    return ['key' => '', 'source' => ''];
  }

  /**
   * @return string
   */
  private static function constant_value(): string
  {
    if (!defined(self::CONSTANT)) {
      return '';
    }

    $value = constant(self::CONSTANT);

    return is_string($value) ? trim($value) : '';
  }

  /**
   * @return string
   */
  private static function env_value(): string
  {
    $value = getenv(self::ENV_VAR);

    if (false === $value && isset($_SERVER[self::ENV_VAR])) {
      $value = $_SERVER[self::ENV_VAR];
    }

    return is_string($value) ? trim($value) : '';
  }

  /**
   * Read the `.pexels-key` file, trimming whitespace and newlines.
   *
   * @return string
   */
  private static function file_value(): string
  {
    $path = self::file_path();
    if (!is_readable($path) || !is_file($path)) {
      return '';
    }

    $contents = file_get_contents($path);
    if (false === $contents) {
      return '';
    }

    // A single line: tolerate a trailing newline, BOM or stray whitespace.
    $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
    $lines = preg_split('/\R/', (string) $contents);

    return is_array($lines) ? trim((string) reset($lines)) : '';
  }

  /**
   * @return string
   */
  private static function option_value(): string
  {
    if (!function_exists('get_option')) {
      return '';
    }

    $value = get_option(self::OPTION, '');

    return is_string($value) ? trim($value) : '';
  }

  /**
   * Plugin root path, with a trailing slash, usable outside WordPress.
   *
   * @return string
   */
  private static function plugin_path(): string
  {
    if (defined('GROOVE_PATH')) {
      return trailingslashit(GROOVE_PATH);
    }

    return dirname(__DIR__) . DIRECTORY_SEPARATOR;
  }
}
