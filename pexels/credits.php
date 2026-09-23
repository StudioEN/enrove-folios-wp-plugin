<?php
namespace Groove\Pexels;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Credits
 *
 * Reads and writes assets/images/pexels/credits.json — the attribution
 * manifest the curator fills in, keyed by slot slug.
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
   * Canonical field order, so re-running the curator produces clean diffs.
   *
   * @var string[]
   */
  private static $field_order = [
    'slug',
    'file',
    'pexels_id',
    'pexels_url',
    'photographer',
    'photographer_url',
    'alt',
    'avg_color',
    'query',
    'src_size',
    'src_url',
    'width',
    'height',
    'downloaded_at',
  ];

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
    $root = defined('GROOVE_PATH') ? trailingslashit(GROOVE_PATH) : dirname(__DIR__) . DIRECTORY_SEPARATOR;

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
   * @param string $slug Slot slug, e.g. 'cover-groove-proposal'.
   *
   * @return array|null Null when the slug has no record.
   */
  public static function get(string $slug): ?array
  {
    $all = self::all();

    return isset($all[$slug]) && is_array($all[$slug]) ? $all[$slug] : null;
  }

  /**
   * Write the whole manifest, pretty-printed with a stable key order.
   *
   * @param array $credits slug => credit record.
   *
   * @return bool True on a successful write.
   */
  public static function save(array $credits): bool
  {
    $credits = self::normalise($credits);

    $path = self::path();
    $dir = dirname($path);

    if (!is_dir($dir) && !self::mkdir($dir)) {
      return false;
    }

    $json = wp_json_encode(
      $credits,
      JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    if (false === $json || null === $json) {
      return false;
    }

    $written = file_put_contents($path, $json . "\n", LOCK_EX);
    if (false === $written) {
      return false;
    }

    self::$cache = $credits;

    return true;
  }

  /**
   * Merge a single record into the manifest and write it out.
   *
   * Additive helper for the curator — not part of the frozen contract.
   *
   * @param string $slug
   * @param array  $record
   *
   * @return bool
   */
  public static function put(string $slug, array $record): bool
  {
    $all = self::all();
    $all[$slug] = array_merge(['slug' => $slug], $record);

    return self::save($all);
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
      esc_html__('Pexels', 'groove-folios')
    );

    $text = sprintf(
      /* translators: 1: photographer name (linked), 2: the word "Pexels" (linked). */
      esc_html__('Photo by %1$s on %2$s', 'groove-folios'),
      $photographer_link,
      $pexels_link
    );

    $tag = 'caption' === $context ? 'figcaption' : 'span';
    $class = 'caption' === $context
      ? 'groove-pexels-credit groove-pexels-credit--caption'
      : 'groove-pexels-credit';

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
      '<a class="groove-pexels-link" href="%1$s" rel="nofollow noopener" target="_blank">%2$s</a>',
      esc_url(self::PEXELS_URL),
      esc_html__('Photos provided by Pexels', 'groove-folios')
    );
  }

  // ── Internals ────────────────────────────────────────────────────────────

  /**
   * Sort records by slug and fields into the canonical order.
   *
   * @param array $credits
   *
   * @return array
   */
  private static function normalise(array $credits): array
  {
    ksort($credits);

    $out = [];
    foreach ($credits as $slug => $record) {
      if (!is_array($record)) {
        continue;
      }

      $ordered = [];
      foreach (self::$field_order as $field) {
        if (array_key_exists($field, $record)) {
          $ordered[$field] = $record[$field];
        }
      }

      // Anything unexpected keeps its place at the end, alphabetically.
      $extra = array_diff_key($record, array_flip(self::$field_order));
      ksort($extra);

      $out[$slug] = array_merge($ordered, $extra);
    }

    return $out;
  }

  /**
   * Create a directory. This class only runs with WordPress loaded (the CLI
   * curator bootstraps wp-load.php), so wp_mkdir_p() is always there.
   *
   * @param string $dir
   *
   * @return bool
   */
  private static function mkdir(string $dir): bool
  {
    return (bool) wp_mkdir_p($dir);
  }
}
