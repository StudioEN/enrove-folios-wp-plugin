<?php
namespace Groove\Pexels;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Curator
 *
 * Walks a slot manifest, resolves one Pexels photo per slot, downloads it to
 * the slot's destination and records the attribution in credits.json.
 *
 * Design rules this class keeps to:
 *   - Deterministic. The first result that suits the slot wins, so re-running
 *     does not churn imagery for no reason.
 *   - Idempotent. A slot whose file already exists is skipped unless forced.
 *   - Fail soft. One bad slot never aborts the run; it is recorded and the
 *     walk continues.
 *   - Never commit rubbish. Bytes are verified as an image in a temp file
 *     before anything is written to the destination.
 *
 * Nothing here runs on a hook. It only executes when something explicitly
 * calls run().
 *
 * Build-time only: bin/curate-pexels.php is its one caller, and neither file
 * ships in the release zip. It writes into the plugin's own folder (the photos
 * and credits.json it commits to the repository), which a plugin must never do
 * on a live site, where an update replaces that folder.
 *
 * @since 0.2.0
 */
class Curator
{

  /** How many results to ask Pexels for per slot. One request either way. */
  const POOL_SIZE = 30;

  /** Download timeout in seconds. */
  const DOWNLOAD_TIMEOUT = 60;

  /** Preference order when a slot's requested src size is missing. */
  const SRC_FALLBACKS = ['large2x', 'large', 'original', 'landscape', 'portrait', 'medium'];

  /**
   * credits.json field order, so re-running the curator produces clean diffs.
   *
   * @var string[]
   */
  private static $credit_field_order = [
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
   * @var Client
   */
  private $client;

  /**
   * Photo IDs already claimed during this run, so a four-portrait grid does
   * not end up as the same face four times.
   *
   * @var int[]
   */
  private $used_ids = [];

  /**
   * @param Client $client
   */
  public function __construct(Client $client)
  {
    $this->client = $client;
  }

  // ── Public API ───────────────────────────────────────────────────────────

  /**
   * Curate a set of slots.
   *
   * @param array $slots Slot definitions from manifest.php — a map keyed by
   *                     slug or a plain list; a subset is fine.
   * @param array $opts  ['force' => bool, 'dry_run' => bool].
   *
   * @return array Per-slot results keyed by slug, each:
   *               ['slot','status','path','error', …]
   *               status is one of downloaded|skipped|failed|dry-run.
   */
  public function run(array $slots, array $opts = []): array
  {
    $opts = array_merge(['force' => false, 'dry_run' => false], $opts);

    $this->used_ids = [];
    $results = [];

    foreach ($slots as $key => $slot) {
      if (!is_array($slot)) {
        continue;
      }

      if (empty($slot['slug']) && is_string($key)) {
        $slot['slug'] = $key;
      }

      $slug = isset($slot['slug']) ? (string) $slot['slug'] : '';
      if ('' === $slug) {
        continue;
      }

      $results[$slug] = $this->process($slot, $opts);
    }

    return $results;
  }

  // ── Per-slot pipeline ────────────────────────────────────────────────────

  /**
   * Resolve, download and record a single slot.
   *
   * @param array $slot
   * @param array $opts
   *
   * @return array
   */
  private function process(array $slot, array $opts): array
  {
    $slug = (string) $slot['slug'];
    $relative = isset($slot['path']) ? ltrim((string) $slot['path'], '/\\') : '';

    if ('' === $relative) {
      return $this->result($slug, 'failed', '', __('The slot has no destination path.', 'groove-folios'));
    }

    if (empty($slot['query'])) {
      return $this->result($slug, 'failed', '', __('The slot has no search query.', 'groove-folios'));
    }

    $destination = $this->plugin_path() . $relative;

    // Idempotency: an existing file is left alone unless forced. Reported for
    // dry runs too, so a dry run tells you exactly what a real run would do.
    if (file_exists($destination) && empty($opts['force'])) {
      $existing = Credits::get($slug);
      if ($existing && !empty($existing['pexels_id'])) {
        $this->used_ids[] = (int) $existing['pexels_id'];
      }

      return $this->result(
        $slug,
        'skipped',
        $destination,
        null,
        [
          'relative_path' => $relative,
          'message'       => __('Destination already exists. Re-run with force to replace it.', 'groove-folios'),
        ]
      );
    }

    $photo = $this->pick_photo($slot);
    if (is_wp_error($photo)) {
      return $this->result($slug, 'failed', $destination, $photo->get_error_message(), [
        'relative_path' => $relative,
        'error_code'    => $photo->get_error_code(),
      ]);
    }

    $src_size = isset($slot['src_size']) ? (string) $slot['src_size'] : 'large';
    $src_url = $this->src_url($photo, $src_size);
    if ('' === $src_url) {
      return $this->result($slug, 'failed', $destination, sprintf(
        /* translators: %s: Pexels src size name, e.g. large2x. */
        __('The chosen photo has no usable "%s" source.', 'groove-folios'),
        $src_size
      ), ['relative_path' => $relative]);
    }

    $this->used_ids[] = (int) $photo['id'];

    $meta = [
      'relative_path' => $relative,
      'photo_id'      => (int) $photo['id'],
      'photographer'  => isset($photo['photographer']) ? (string) $photo['photographer'] : '',
      'pexels_url'    => isset($photo['url']) ? (string) $photo['url'] : '',
      'query'         => (string) $slot['query'],
      'src_size'      => $src_size,
      'src_url'       => $src_url,
    ];

    if (!empty($opts['dry_run'])) {
      $meta['message'] = __('Dry run: nothing was written.', 'groove-folios');

      return $this->result($slug, 'dry-run', $destination, null, $meta);
    }

    $dir = dirname($destination);
    if (!is_dir($dir) && !wp_mkdir_p($dir)) {
      return $this->result($slug, 'failed', $destination, sprintf(
        /* translators: %s: directory path. */
        __('Could not create the directory %s.', 'groove-folios'),
        $dir
      ), $meta);
    }

    $temp = $this->fetch_to_temp($src_url);
    if (is_wp_error($temp)) {
      return $this->result($slug, 'failed', $destination, $temp->get_error_message(), $meta);
    }

    // Verify the bytes really are an image before anything touches the
    // destination — a half-written or HTML error page never gets committed.
    $size = @getimagesize($temp);
    if (!is_array($size) || empty($size[0]) || empty($size[1])) {
      wp_delete_file($temp);

      return $this->result($slug, 'failed', $destination, __('The downloaded file is not a valid image.', 'groove-folios'), $meta);
    }

    if (!$this->move($temp, $destination)) {
      wp_delete_file($temp);

      return $this->result($slug, 'failed', $destination, sprintf(
        /* translators: %s: file path. */
        __('Could not write %s.', 'groove-folios'),
        $destination
      ), $meta);
    }

    $meta['width'] = (int) $size[0];
    $meta['height'] = (int) $size[1];

    $recorded = $this->write_credit($slug, $this->credit_record($slug, $slot, $photo, $relative, $src_size, $meta));
    if (!$recorded) {
      $meta['message'] = __('Image saved, but the credit could not be written to credits.json.', 'groove-folios');
    }

    return $this->result($slug, 'downloaded', $destination, null, $meta);
  }

  // ── Photo selection ──────────────────────────────────────────────────────

  /**
   * Search Pexels and choose a photo for the slot, deterministically.
   *
   * Tries the slot query with its colour hint, then without the hint, then the
   * fallback query — so a narrow query never leaves a slot empty.
   *
   * @param array $slot
   *
   * @return array|\WP_Error The chosen photo object.
   */
  private function pick_photo(array $slot)
  {
    $attempts = [];

    $base = [
      'orientation' => isset($slot['orientation']) ? (string) $slot['orientation'] : '',
      'per_page'    => self::POOL_SIZE,
    ];

    if (!empty($slot['color'])) {
      $attempts[] = [(string) $slot['query'], array_merge($base, ['color' => (string) $slot['color']])];
    }

    $attempts[] = [(string) $slot['query'], $base];

    if (!empty($slot['fallback_query'])) {
      $attempts[] = [(string) $slot['fallback_query'], $base];
    }

    $last_error = null;

    foreach ($attempts as $attempt) {
      list($query, $args) = $attempt;

      $response = $this->client->search($query, $args);
      if (is_wp_error($response)) {
        // A key or quota problem will not improve on the next attempt.
        $code = $response->get_error_code();
        if (in_array($code, ['groove_pexels_no_key', 'groove_pexels_unauthorized', 'groove_pexels_rate_limited'], true)) {
          return $response;
        }

        $last_error = $response;
        continue;
      }

      $photos = isset($response['photos']) && is_array($response['photos']) ? $response['photos'] : [];
      $choice = $this->choose($photos, $slot);
      if ($choice) {
        return $choice;
      }
    }

    if ($last_error) {
      return $last_error;
    }

    return new \WP_Error(
      'groove_pexels_no_results',
      sprintf(
        /* translators: %s: search query. */
        __('Pexels returned no usable photos for “%s”.', 'groove-folios'),
        (string) $slot['query']
      )
    );
  }

  /**
   * Pick from a result set: the first photo whose aspect ratio suits the slot
   * and that this run has not already used. Falls back to the first unused
   * photo, then to the first photo.
   *
   * @param array $photos
   * @param array $slot
   *
   * @return array|null
   */
  private function choose(array $photos, array $slot): ?array
  {
    $ratio = isset($slot['ratio']) && is_array($slot['ratio']) && 2 === count($slot['ratio'])
      ? [(float) $slot['ratio'][0], (float) $slot['ratio'][1]]
      : null;

    $first_valid = null;
    $first_unused = null;

    foreach ($photos as $photo) {
      if (!is_array($photo) || empty($photo['id']) || empty($photo['src'])) {
        continue;
      }

      if (null === $first_valid) {
        $first_valid = $photo;
      }

      $used = in_array((int) $photo['id'], $this->used_ids, true);
      if ($used) {
        continue;
      }

      if (null === $first_unused) {
        $first_unused = $photo;
      }

      if (null === $ratio) {
        return $photo;
      }

      $width = isset($photo['width']) ? (float) $photo['width'] : 0.0;
      $height = isset($photo['height']) ? (float) $photo['height'] : 0.0;
      if ($height <= 0.0) {
        continue;
      }

      $aspect = $width / $height;
      if ($aspect >= $ratio[0] && $aspect <= $ratio[1]) {
        return $photo;
      }
    }

    return $first_unused ?: $first_valid;
  }

  /**
   * The download URL for the requested src size, with sensible fallbacks.
   *
   * @param array  $photo
   * @param string $size
   *
   * @return string Empty string when the photo has no usable source.
   */
  private function src_url(array $photo, string $size): string
  {
    $src = isset($photo['src']) && is_array($photo['src']) ? $photo['src'] : [];

    if (!empty($src[$size])) {
      return (string) $src[$size];
    }

    foreach (self::SRC_FALLBACKS as $candidate) {
      if (!empty($src[$candidate])) {
        return (string) $src[$candidate];
      }
    }

    return '';
  }

  // ── Filesystem ───────────────────────────────────────────────────────────

  /**
   * Download a URL to a temp file.
   *
   * Prefers download_url() from wp-admin/includes/file.php; falls back to
   * wp_remote_get() plus a body write when that file cannot be loaded (as in
   * some CLI bootstraps).
   *
   * @param string $url
   *
   * @return string|\WP_Error Absolute temp file path.
   */
  private function fetch_to_temp(string $url)
  {
    if (!function_exists('download_url') && defined('ABSPATH')) {
      $file_api = ABSPATH . 'wp-admin/includes/file.php';
      if (is_readable($file_api)) {
        require_once $file_api;
      }
    }

    if (function_exists('download_url')) {
      $temp = download_url($url, self::DOWNLOAD_TIMEOUT);
      if (is_wp_error($temp)) {
        return $temp;
      }

      return (string) $temp;
    }

    $response = wp_remote_get($url, ['timeout' => self::DOWNLOAD_TIMEOUT]);
    if (is_wp_error($response)) {
      return $response;
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    if (200 !== $status) {
      return new \WP_Error(
        'groove_pexels_download_failed',
        sprintf(
          /* translators: %d: HTTP status code. */
          __('Downloading the image failed with HTTP %d.', 'groove-folios'),
          $status
        )
      );
    }

    $body = wp_remote_retrieve_body($response);
    if ('' === $body) {
      return new \WP_Error('groove_pexels_download_empty', __('The image download returned an empty body.', 'groove-folios'));
    }

    $temp = function_exists('wp_tempnam') ? wp_tempnam('groove-pexels') : tempnam(sys_get_temp_dir(), 'groove-pexels');
    if (!$temp) {
      return new \WP_Error('groove_pexels_temp_failed', __('Could not create a temporary file for the download.', 'groove-folios'));
    }

    if (false === file_put_contents($temp, $body)) {
      wp_delete_file($temp);

      return new \WP_Error('groove_pexels_temp_failed', __('Could not write the temporary download file.', 'groove-folios'));
    }

    return (string) $temp;
  }

  /**
   * Move a verified temp file into place, copying when rename cannot cross
   * filesystems. The temp file is always cleaned up.
   *
   * @param string $temp
   * @param string $destination
   *
   * @return bool
   */
  private function move(string $temp, string $destination): bool
  {
    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

    // The direct filesystem, not the global $wp_filesystem: both paths are
    // local, and the CLI curator never sets up FTP credentials. Its move()
    // is rename() with a copy fallback, overwriting a previous curation.
    $fs = new \WP_Filesystem_Direct(null);
    $mode = defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644;

    $moved = $fs->move($temp, $destination, true);

    if (!$moved) {
      wp_delete_file($temp);

      // Never leave a partial file behind.
      if (file_exists($destination) && 0 === filesize($destination)) {
        wp_delete_file($destination);
      }

      return false;
    }

    $fs->chmod($destination, $mode);

    return true;
  }

  // ── Records ──────────────────────────────────────────────────────────────

  /**
   * Merge one record into credits.json and write the whole manifest back,
   * pretty-printed, sorted by slug and in the canonical field order.
   *
   * @param string $slug
   * @param array  $record
   *
   * @return bool True on a successful write.
   */
  private function write_credit(string $slug, array $record): bool
  {
    $credits = Credits::all();
    $credits[$slug] = array_merge(['slug' => $slug], $record);

    ksort($credits);

    $ordered_credits = [];
    foreach ($credits as $key => $credit) {
      if (!is_array($credit)) {
        continue;
      }

      $ordered = [];
      foreach (self::$credit_field_order as $field) {
        if (array_key_exists($field, $credit)) {
          $ordered[$field] = $credit[$field];
        }
      }

      // Anything unexpected keeps its place at the end, alphabetically.
      $extra = array_diff_key($credit, array_flip(self::$credit_field_order));
      ksort($extra);

      $ordered_credits[$key] = array_merge($ordered, $extra);
    }

    $path = Credits::path();
    if (!is_dir(dirname($path)) && !wp_mkdir_p(dirname($path))) {
      return false;
    }

    $json = wp_json_encode(
      $ordered_credits,
      JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    if (false === $json || null === $json) {
      return false;
    }

    if (false === file_put_contents($path, $json . "\n", LOCK_EX)) {
      return false;
    }

    Credits::flush_cache();

    return true;
  }

  /**
   * Build the credits.json record for a downloaded slot.
   *
   * @param string $slug
   * @param array  $slot
   * @param array  $photo
   * @param string $relative
   * @param string $src_size
   * @param array  $meta
   *
   * @return array
   */
  private function credit_record(string $slug, array $slot, array $photo, string $relative, string $src_size, array $meta): array
  {
    return [
      'slug'             => $slug,
      'file'             => $relative,
      'pexels_id'        => (int) $photo['id'],
      'pexels_url'       => isset($photo['url']) ? (string) $photo['url'] : '',
      'photographer'     => isset($photo['photographer']) ? (string) $photo['photographer'] : '',
      'photographer_url' => isset($photo['photographer_url']) ? (string) $photo['photographer_url'] : '',
      'alt'              => isset($photo['alt']) ? (string) $photo['alt'] : '',
      'avg_color'        => isset($photo['avg_color']) ? (string) $photo['avg_color'] : '',
      'query'            => (string) $slot['query'],
      'src_size'         => $src_size,
      'src_url'          => isset($meta['src_url']) ? (string) $meta['src_url'] : '',
      'width'            => isset($meta['width']) ? (int) $meta['width'] : 0,
      'height'           => isset($meta['height']) ? (int) $meta['height'] : 0,
      'downloaded_at'    => gmdate('c'),
    ];
  }

  /**
   * Shape one entry of the return value.
   *
   * @param string      $slug
   * @param string      $status downloaded|skipped|failed|dry-run
   * @param string      $path
   * @param string|null $error
   * @param array       $extra
   *
   * @return array
   */
  private function result(string $slug, string $status, string $path, $error = null, array $extra = []): array
  {
    return array_merge(
      [
        'slot'   => $slug,
        'status' => $status,
        'path'   => $path,
        'error'  => $error,
      ],
      $extra
    );
  }

  /**
   * Plugin root path with a trailing slash.
   *
   * @return string
   */
  private function plugin_path(): string
  {
    return defined('GROOVE_PATH') ? trailingslashit(GROOVE_PATH) : dirname(__DIR__) . DIRECTORY_SEPARATOR;
  }
}
