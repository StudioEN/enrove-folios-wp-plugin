<?php
namespace Enrove\Pexels;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Credits
 *
 * Reads assets/images/pexels/credits.json — the attribution manifest the
 * build-time curator fills in, keyed by slot slug. This class never writes it:
 * a plugin must not write to its own folder at runtime (an update replaces the
 * folder), so the writer lives in Curator, which is not shipped.
 *
 * The Pexels licence asks for two things and this class provides both:
 * a per-photo "Photo by {photographer} on Pexels" credit linking to the
 * photographer, and a prominent link back to Pexels itself.
 *
 * Nothing here touches the network, and a missing credits.json is a normal
 * state (nobody has run the curator yet) — never a fatal.
 *
 * @since 0.2.0
 */
class Credits
{

  /** credits.json location, relative to the plugin root. */
  const RELATIVE_PATH = 'assets/images/pexels/credits.json';

  /** Public Pexels URL used for the required attribution link. */
  const PEXELS_URL = 'https://www.pexels.com';

  /**
   * In-request cache of the decoded manifest. Null means "not read yet".
   *
   * @var array|null
   */
  private static $cache = null;

  // ── Public API ───────────────────────────────────────────────────────────

  /**
   * Absolute path to credits.json.
   *
   * @return string
   */
  public static function path(): string
  {
    $root = defined('ENROVE_PATH') ? trailingslashit(ENROVE_PATH) : dirname(__DIR__) . DIRECTORY_SEPARATOR;

    return $root . self::RELATIVE_PATH;
  }

  /**
   * Every credit record, keyed by slot slug.
   *
   * @return array Empty array when credits.json is absent or unreadable.
   */
  public static function all(): array
  {
    if (is_array(self::$cache)) {
      return self::$cache;
    }

    self::$cache = [];

    $path = self::path();
    if (!is_readable($path) || !is_file($path)) {
      return self::$cache;
    }

    $raw = file_get_contents($path);
    if (false === $raw || '' === trim((string) $raw)) {
      return self::$cache;
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
      self::$cache = $decoded;
    }

    return self::$cache;
  }

  /**
   * One credit record.
   *
   * @param string $slug Slot slug, e.g. 'cover-enrove-proposal'.
   *
   * @return array|null Null when the slug has no record.
   */
  public static function get(string $slug): ?array
  {
    $all = self::all();

    return isset($all[$slug]) && is_array($all[$slug]) ? $all[$slug] : null;
  }

  /**
   * Forget the in-request cache (useful in long-running CLI runs).
   *
   * @return void
   */
  public static function flush_cache()
  {
    self::$cache = null;
  }

  /**
   * Escaped attribution markup for one slot.
   *
   * @param string $slug    Slot slug.
   * @param string $context 'inline' (a <span>) or 'caption' (a <figcaption>).
   *
   * @return string Empty string when the slug has no credit record.
   */
  public static function render(string $slug, string $context = 'inline'): string
  {
    $record = self::get($slug);
    if (!$record) {
      return '';
    }

    $photographer = isset($record['photographer']) ? (string) $record['photographer'] : '';
    if ('' === trim($photographer)) {
      return '';
    }

    $photographer_url = isset($record['photographer_url']) ? (string) $record['photographer_url'] : '';
    $photo_url = isset($record['pexels_url']) ? (string) $record['pexels_url'] : self::PEXELS_URL;

    $photographer_link = '' !== $photographer_url
      ? sprintf(
        '<a href="%1$s" rel="nofollow noopener" target="_blank">%2$s</a>',
        esc_url($photographer_url),
        esc_html($photographer)
      )
      : esc_html($photographer);

    $pexels_link = sprintf(
      '<a href="%1$s" rel="nofollow noopener" target="_blank">%2$s</a>',
      esc_url($photo_url),
      esc_html__('Pexels', 'enrove-folios')
    );

    $text = sprintf(
      /* translators: 1: photographer name (linked), 2: the word "Pexels" (linked). */
      esc_html__('Photo by %1$s on %2$s', 'enrove-folios'),
      $photographer_link,
      $pexels_link
    );

    $tag = 'caption' === $context ? 'figcaption' : 'span';
    $class = 'caption' === $context
      ? 'enrove-pexels-credit enrove-pexels-credit--caption'
      : 'enrove-pexels-credit';

    return sprintf('<%1$s class="%2$s">%3$s</%1$s>', $tag, esc_attr($class), $text);
  }

  /**
   * The licence's "prominent link to Pexels" — use it once per surface that
   * shows curated imagery.
   *
   * Additive helper — not part of the frozen contract.
   *
   * @return string
   */
  public static function pexels_link(): string
  {
    return sprintf(
      '<a class="enrove-pexels-link" href="%1$s" rel="nofollow noopener" target="_blank">%2$s</a>',
      esc_url(self::PEXELS_URL),
      esc_html__('Photos provided by Pexels', 'enrove-folios')
    );
  }
}
