<?php
/**
 * Groove Folios — Pexels curation CLI.
 *
 * Downloads the theme covers and the shared placeholder pool from Pexels once,
 * so the shipped plugin never talks to the Pexels API at runtime.
 *
 * Usage:
 *   php bin/curate-pexels.php [options]
 *
 * Options:
 *   --slots=a,b,c        Only curate these slot slugs (default: every slot).
 *   --theme=<slug>       Only curate the cover + placeholders for one theme.
 *   --covers-only        Only curate theme cover slots.
 *   --placeholders-only  Only curate shared placeholder slots.
 *   --force              Re-download slots whose file already exists.
 *   --dry-run            Resolve and report, download nothing.
 *   --wp=/path/to/wp     WordPress root (dir containing wp-load.php).
 *   --help               Show this help.
 *
 * The API key is never printed. Supply it via one of (highest priority first):
 *   1. GROOVE_PEXELS_API_KEY constant in wp-config.php
 *   2. PEXELS_API_KEY environment variable
 *   3. .pexels-key file in the plugin root (gitignored, single line)
 *   4. Settings → Imagery in the WordPress admin
 *
 * @package Groove
 */

// ---------------------------------------------------------------------------
// Hard guards: CLI only, never reachable over HTTP.
// ---------------------------------------------------------------------------

if (PHP_SAPI !== 'cli') {
  header('HTTP/1.1 403 Forbidden');
  exit('This script can only be run from the command line.');
}

if (isset($_SERVER['REQUEST_METHOD']) && !defined('WP_CLI')) {
  exit('This script can only be run from the command line.');
}

if (!defined('GROOVE_PEXELS_CLI')) {
  define('GROOVE_PEXELS_CLI', true);
}

define('GROOVE_PEXELS_CLI_PLUGIN_DIR', dirname(__DIR__));

// ---------------------------------------------------------------------------
// Small output helpers.
// ---------------------------------------------------------------------------

if (!function_exists('groove_pexels_cli_out')) {
  /**
   * Write a line to STDOUT.
   *
   * @param string $line Line to write.
   * @return void
   */
  function groove_pexels_cli_out($line = '')
  {
    fwrite(STDOUT, $line . PHP_EOL);
  }

  /**
   * Write a line to STDERR.
   *
   * @param string $line Line to write.
   * @return void
   */
  function groove_pexels_cli_err($line = '')
  {
    fwrite(STDERR, $line . PHP_EOL);
  }

  /**
   * The usage / help text.
   *
   * @return string
   */
  function groove_pexels_cli_usage()
  {
    return implode(PHP_EOL, array(
      'Groove Folios — Pexels curation',
      '',
      'Usage:',
      '  php bin/curate-pexels.php [options]',
      '',
      'Options:',
      '  --slots=a,b,c        Only curate these slot slugs (comma separated).',
      '  --theme=<slug>       Only curate slots belonging to one theme.',
      '  --covers-only        Only curate theme cover slots.',
      '  --placeholders-only  Only curate shared placeholder slots.',
      '  --force              Re-download slots whose file already exists.',
      '  --dry-run            Resolve and report, download nothing.',
      '  --wp=<path>          WordPress root (the directory holding wp-load.php).',
      '  --help               Show this help.',
      '',
      'API key sources, highest priority first:',
      '  1. GROOVE_PEXELS_API_KEY constant in wp-config.php   (recommended for production)',
      '  2. PEXELS_API_KEY environment variable',
      '  3. .pexels-key file in the plugin root               (recommended for local dev)',
      '  4. The groove_pexels_api_key WordPress option (Settings → Imagery)',
      '',
      'The key itself is never printed by this script.',
    ));
  }

  /**
   * Parse argv into an options array.
   *
   * @param array $argv Raw arguments.
   * @return array
   */
  function groove_pexels_cli_parse_args($argv)
  {
    $opts = array(
      'slots' => array(),
      'theme' => '',
      'covers_only' => false,
      'placeholders_only' => false,
      'force' => false,
      'dry_run' => false,
      'wp' => '',
      'help' => false,
      'unknown' => array(),
    );

    foreach ((array) $argv as $index => $arg) {
      if (!is_string($arg)) {
        continue;
      }
      // Skip the script name and anything preceding it (wp eval-file passes its own argv).
      if (strpos($arg, '--') !== 0) {
        continue;
      }
      if ($arg === '--') {
        continue;
      }

      $value = '';
      $name = substr($arg, 2);
      $eq = strpos($name, '=');
      if ($eq !== false) {
        $value = substr($name, $eq + 1);
        $name = substr($name, 0, $eq);
      }

      switch ($name) {
        case 'slots':
          foreach (explode(',', $value) as $slug) {
            $slug = trim($slug);
            if ($slug !== '') {
              $opts['slots'][] = $slug;
            }
          }
          break;
        case 'theme':
          $opts['theme'] = trim($value);
          break;
        case 'covers-only':
        case 'covers':
          $opts['covers_only'] = true;
          break;
        case 'placeholders-only':
        case 'placeholders':
          $opts['placeholders_only'] = true;
          break;
        case 'force':
          $opts['force'] = true;
          break;
        case 'dry-run':
        case 'dry':
          $opts['dry_run'] = true;
          break;
        case 'wp':
          $opts['wp'] = trim($value);
          break;
        case 'help':
        case 'h':
          $opts['help'] = true;
          break;
        default:
          $opts['unknown'][] = $name;
          break;
      }

      unset($index);
    }

    return $opts;
  }

  /**
   * Does this WordPress root actually host the plugin we are running from?
   *
   * @param string $root       WordPress root directory.
   * @param string $plugin_dir Plugin directory (real path).
   * @return bool
   */
  function groove_pexels_cli_root_hosts_plugin($root, $plugin_dir)
  {
    $plugins_dir = rtrim($root, '/') . '/wp-content/plugins';
    if (!is_dir($plugins_dir)) {
      return false;
    }

    $entries = glob($plugins_dir . '/*');
    foreach ((array) $entries as $entry) {
      $real = realpath($entry);
      if ($real !== false && $real === $plugin_dir) {
        return true;
      }
    }

    return false;
  }

  /**
   * Locate a WordPress root containing wp-load.php.
   *
   * Checks --wp, then GROOVE_WP_ROOT, then walks up from the plugin directory
   * (and peeks one level into each ancestor, because this plugin is commonly
   * symlinked into wp-content/plugins from a sibling checkout).
   *
   * @param string $explicit   Value of --wp, or ''.
   * @param string $plugin_dir Plugin directory.
   * @return string Absolute path to the WordPress root, or '' when not found.
   */
  function groove_pexels_cli_find_wp_root($explicit, $plugin_dir)
  {
    $candidates = array();

    if ($explicit !== '') {
      $candidates[] = rtrim($explicit, '/');
    }

    $env_root = getenv('GROOVE_WP_ROOT');
    if (is_string($env_root) && $env_root !== '') {
      $candidates[] = rtrim($env_root, '/');
    }

    $dir = $plugin_dir;
    $previous = '';
    $depth = 0;
    while ($dir !== '' && $dir !== $previous && $depth < 10) {
      $candidates[] = $dir;

      foreach ((array) glob($dir . '/*/wp-load.php') as $hit) {
        $candidates[] = dirname($hit);
      }

      $previous = $dir;
      $dir = dirname($dir);
      $depth++;
    }

    $best = '';
    $best_score = -1;
    $seen = array();

    foreach ($candidates as $candidate) {
      if ($candidate === '' || isset($seen[$candidate])) {
        continue;
      }
      $seen[$candidate] = true;

      if (!is_file($candidate . '/wp-load.php')) {
        continue;
      }

      $score = 1;
      if (groove_pexels_cli_root_hosts_plugin($candidate, $plugin_dir)) {
        $score += 2;
      }
      if (is_file($candidate . '/wp-config.php')) {
        $score += 1;
      }

      if ($score > $best_score) {
        $best_score = $score;
        $best = $candidate;
      }
    }

    return $best;
  }

  /**
   * Normalise a raw manifest entry into the fields this script reports on.
   *
   * @param string|int $key   Manifest array key.
   * @param mixed      $entry Manifest value.
   * @return array|null
   */
  function groove_pexels_cli_normalise_slot($key, $entry)
  {
    if (!is_array($entry)) {
      return null;
    }

    $slug = '';
    foreach (array('slug', 'id', 'name') as $slug_key) {
      if (!empty($entry[$slug_key]) && is_string($entry[$slug_key])) {
        $slug = $entry[$slug_key];
        break;
      }
    }
    if ($slug === '' && is_string($key)) {
      $slug = $key;
    }
    if ($slug === '') {
      return null;
    }

    $kind = '';
    foreach (array('kind', 'type') as $kind_key) {
      if (!empty($entry[$kind_key]) && is_string($entry[$kind_key])) {
        $kind = $entry[$kind_key];
        break;
      }
    }
    if ($kind === '') {
      $kind = strpos($slug, 'cover-') === 0 ? 'cover' : 'placeholder';
    }

    $theme = '';
    if (!empty($entry['theme']) && is_string($entry['theme'])) {
      $theme = $entry['theme'];
    } elseif ($kind === 'cover' && strpos($slug, 'cover-') === 0) {
      $theme = substr($slug, strlen('cover-'));
    }

    $entry['slug'] = $slug;
    $entry['kind'] = $kind;
    if ($theme !== '') {
      $entry['theme'] = $theme;
    }

    return $entry;
  }

  /**
   * Render an aligned text table.
   *
   * @param array $headers Column headers.
   * @param array $rows    Rows of columns.
   * @return string
   */
  function groove_pexels_cli_table($headers, $rows)
  {
    $widths = array();
    foreach ($headers as $index => $header) {
      $widths[$index] = strlen((string) $header);
    }
    foreach ($rows as $row) {
      foreach (array_values($row) as $index => $cell) {
        $length = strlen((string) $cell);
        if (!isset($widths[$index]) || $length > $widths[$index]) {
          $widths[$index] = $length;
        }
      }
    }

    $line = function ($cells) use ($widths) {
      $out = array();
      foreach (array_values($cells) as $index => $cell) {
        $width = isset($widths[$index]) ? $widths[$index] : 0;
        $out[] = str_pad((string) $cell, $width);
      }
      return rtrim('  ' . implode('  ', $out));
    };

    $divider = array();
    foreach ($widths as $width) {
      $divider[] = str_repeat('-', $width);
    }

    $output = array($line($headers), $line($divider));
    foreach ($rows as $row) {
      $output[] = $line($row);
    }

    return implode(PHP_EOL, $output);
  }
}

// ---------------------------------------------------------------------------
// Arguments.
// ---------------------------------------------------------------------------

$groove_argv = isset($argv) && is_array($argv) ? $argv : array();
$groove_opts = groove_pexels_cli_parse_args($groove_argv);

if ($groove_opts['help']) {
  groove_pexels_cli_out(groove_pexels_cli_usage());
  exit(0);
}

if (!empty($groove_opts['unknown'])) {
  groove_pexels_cli_err('Unknown option(s): --' . implode(', --', $groove_opts['unknown']));
  groove_pexels_cli_err('');
  groove_pexels_cli_err(groove_pexels_cli_usage());
  exit(1);
}

if ($groove_opts['covers_only'] && $groove_opts['placeholders_only']) {
  groove_pexels_cli_err('--covers-only and --placeholders-only are mutually exclusive.');
  exit(1);
}

// ---------------------------------------------------------------------------
// Bootstrap WordPress (skipped when already loaded, e.g. wp eval-file).
// ---------------------------------------------------------------------------

if (!defined('ABSPATH')) {
  if ($groove_opts['wp'] !== '' && !is_file(rtrim($groove_opts['wp'], '/') . '/wp-load.php')) {
    groove_pexels_cli_err('No wp-load.php in --wp=' . $groove_opts['wp']);
    exit(1);
  }

  $groove_wp_root = groove_pexels_cli_find_wp_root($groove_opts['wp'], GROOVE_PEXELS_CLI_PLUGIN_DIR);

  if ($groove_wp_root === '') {
    groove_pexels_cli_err('Could not locate wp-load.php.');
    groove_pexels_cli_err('Pass the WordPress root explicitly, e.g.:');
    groove_pexels_cli_err('  php bin/curate-pexels.php --wp=/path/to/wordpress');
    exit(1);
  }

  if (!defined('WP_USE_THEMES')) {
    define('WP_USE_THEMES', false);
  }

  // wp-load.php expects a minimal server environment.
  $_SERVER['HTTP_HOST'] = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
  $_SERVER['REQUEST_URI'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
  $_SERVER['SERVER_NAME'] = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
  $_SERVER['SCRIPT_NAME'] = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/index.php';
  $_SERVER['SCRIPT_FILENAME'] = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '/index.php';
  unset($_SERVER['REQUEST_METHOD']);

  require_once $groove_wp_root . '/wp-load.php';

  groove_pexels_cli_out('WordPress: ' . $groove_wp_root);
}

if (!defined('GROOVE_PATH')) {
  groove_pexels_cli_err('The Groove Folios plugin is not active on this site — GROOVE_PATH is undefined.');
  groove_pexels_cli_err('Activate the plugin, then run this script again.');
  exit(1);
}

// download_url() and friends live in the admin includes, which a plain
// wp-load.php bootstrap does not pull in.
if (!function_exists('download_url')) {
  require_once ABSPATH . 'wp-admin/includes/file.php';
}
if (!function_exists('wp_read_image_metadata')) {
  require_once ABSPATH . 'wp-admin/includes/image.php';
}

// ---------------------------------------------------------------------------
// Classes.
// ---------------------------------------------------------------------------

if (!class_exists('\Groove\Pexels\Key') || !class_exists('\Groove\Pexels\Client') || !class_exists('\Groove\Pexels\Curator')) {
  groove_pexels_cli_err('The Groove\\Pexels classes could not be autoloaded.');
  groove_pexels_cli_err('Expected them in: ' . GROOVE_PATH . 'pexels/');
  exit(1);
}

// ---------------------------------------------------------------------------
// Key.
// ---------------------------------------------------------------------------

$groove_key_source = \Groove\Pexels\Key::source();

if ($groove_key_source === '' || \Groove\Pexels\Key::resolve() === '') {
  groove_pexels_cli_err('No Pexels API key found.');
  groove_pexels_cli_err('');
  groove_pexels_cli_err('Supply it in one of these ways (checked in this order):');
  groove_pexels_cli_err('');
  groove_pexels_cli_err('  1. wp-config.php constant (recommended for production)');
  groove_pexels_cli_err("       define('GROOVE_PEXELS_API_KEY', 'your-key-here');");
  groove_pexels_cli_err('');
  groove_pexels_cli_err('  2. Environment variable');
  groove_pexels_cli_err('       PEXELS_API_KEY=your-key-here php bin/curate-pexels.php');
  groove_pexels_cli_err('');
  groove_pexels_cli_err('  3. Key file in the plugin root (gitignored, recommended for local dev)');
  groove_pexels_cli_err('       printf %s "your-key-here" > ' . GROOVE_PEXELS_CLI_PLUGIN_DIR . '/.pexels-key');
  groove_pexels_cli_err('');
  groove_pexels_cli_err('  4. WordPress setting');
  groove_pexels_cli_err('       WP Admin → Groove → Settings → Imagery → Pexels API key');
  groove_pexels_cli_err('');
  groove_pexels_cli_err('Get a free key at https://www.pexels.com/api/');
  exit(1);
}

groove_pexels_cli_out('Key source: ' . $groove_key_source . ' (' . \Groove\Pexels\Key::masked() . ')');

// ---------------------------------------------------------------------------
// Manifest + slot selection.
// ---------------------------------------------------------------------------

$groove_manifest_file = '';
foreach (array(GROOVE_PATH . 'pexels/manifest.php', GROOVE_PATH . 'includes/pexels/manifest.php') as $groove_candidate) {
  if (is_readable($groove_candidate)) {
    $groove_manifest_file = $groove_candidate;
    break;
  }
}

if ($groove_manifest_file === '') {
  groove_pexels_cli_err('Slot manifest not found (looked for pexels/manifest.php).');
  exit(1);
}

$groove_manifest = require $groove_manifest_file;
if (!is_array($groove_manifest) || empty($groove_manifest)) {
  groove_pexels_cli_err('Slot manifest is empty or did not return an array: ' . $groove_manifest_file);
  exit(1);
}

$groove_slots = array();
foreach ($groove_manifest as $groove_key => $groove_entry) {
  $groove_slot = groove_pexels_cli_normalise_slot($groove_key, $groove_entry);
  if ($groove_slot === null) {
    continue;
  }
  $groove_slots[$groove_slot['slug']] = $groove_slot;
}

$groove_selected = $groove_slots;

if ($groove_opts['covers_only']) {
  $groove_selected = array_filter($groove_selected, function ($slot) {
    return $slot['kind'] === 'cover';
  });
}

if ($groove_opts['placeholders_only']) {
  $groove_selected = array_filter($groove_selected, function ($slot) {
    return $slot['kind'] !== 'cover';
  });
}

if ($groove_opts['theme'] !== '') {
  $groove_theme = $groove_opts['theme'];
  $groove_theme_slots = array_filter($groove_selected, function ($slot) use ($groove_theme) {
    return isset($slot['theme']) && $slot['theme'] === $groove_theme;
  });

  if (empty($groove_theme_slots)) {
    groove_pexels_cli_err('No slots found for theme "' . $groove_theme . '".');
    exit(1);
  }

  // A theme run means its cover plus the shared placeholder pool it draws on.
  if (!$groove_opts['covers_only']) {
    foreach ($groove_selected as $groove_slug => $groove_slot) {
      if ($groove_slot['kind'] !== 'cover') {
        $groove_theme_slots[$groove_slug] = $groove_slot;
      }
    }
  }

  $groove_selected = $groove_theme_slots;
}

if (!empty($groove_opts['slots'])) {
  $groove_missing = array();
  $groove_wanted = array();
  foreach ($groove_opts['slots'] as $groove_slug) {
    if (isset($groove_selected[$groove_slug])) {
      $groove_wanted[$groove_slug] = $groove_selected[$groove_slug];
    } elseif (isset($groove_slots[$groove_slug])) {
      // Named explicitly but excluded by another filter — honour the explicit name.
      $groove_wanted[$groove_slug] = $groove_slots[$groove_slug];
    } else {
      $groove_missing[] = $groove_slug;
    }
  }

  if (!empty($groove_missing)) {
    groove_pexels_cli_err('Unknown slot(s): ' . implode(', ', $groove_missing));
    groove_pexels_cli_err('Known slots: ' . implode(', ', array_keys($groove_slots)));
    exit(1);
  }

  $groove_selected = $groove_wanted;
}

if (empty($groove_selected)) {
  groove_pexels_cli_err('No slots selected.');
  exit(1);
}

groove_pexels_cli_out('Slots: ' . count($groove_selected) . ' of ' . count($groove_slots)
  . ($groove_opts['dry_run'] ? '  (dry run)' : '')
  . ($groove_opts['force'] ? '  (force)' : ''));
groove_pexels_cli_out('');

// ---------------------------------------------------------------------------
// Run.
// ---------------------------------------------------------------------------

$groove_client = new \Groove\Pexels\Client();

if (!$groove_client->has_key()) {
  groove_pexels_cli_err('The Pexels client reports no usable key.');
  exit(1);
}

$groove_probe = $groove_client->verify();
if (is_wp_error($groove_probe)) {
  groove_pexels_cli_err('Pexels rejected the key: ' . $groove_probe->get_error_message());
  groove_pexels_cli_err('Check the key supplied via "' . $groove_key_source . '".');
  exit(1);
}

$groove_curator = new \Groove\Pexels\Curator($groove_client);
$groove_results = $groove_curator->run($groove_selected, array(
  'force' => (bool) $groove_opts['force'],
  'dry_run' => (bool) $groove_opts['dry_run'],
));

// ---------------------------------------------------------------------------
// Report.
// ---------------------------------------------------------------------------

$groove_rows = array();
$groove_failed = 0;
$groove_plugin_path = rtrim(GROOVE_PATH, '/') . '/';

foreach ((array) $groove_results as $groove_result) {
  if (!is_array($groove_result)) {
    continue;
  }

  $groove_slug = isset($groove_result['slot']) ? (string) $groove_result['slot'] : '';
  if ($groove_slug === '' && isset($groove_result['slug'])) {
    $groove_slug = (string) $groove_result['slug'];
  }

  $groove_status = isset($groove_result['status']) ? (string) $groove_result['status'] : 'unknown';
  if ($groove_status === 'failed' || $groove_status === 'error') {
    $groove_failed++;
  }

  $groove_dest = isset($groove_result['path']) ? (string) $groove_result['path'] : '';
  if ($groove_dest !== '' && strpos($groove_dest, $groove_plugin_path) === 0) {
    $groove_dest = substr($groove_dest, strlen($groove_plugin_path));
  }

  $groove_note = '';
  if (!empty($groove_result['error'])) {
    $groove_note = is_string($groove_result['error']) ? $groove_result['error'] : 'error';
  } else {
    $groove_credit = null;
    if (class_exists('\Groove\Pexels\Credits') && $groove_slug !== '') {
      $groove_credit = \Groove\Pexels\Credits::get($groove_slug);
    }

    $groove_photographer = '';
    $groove_id = '';
    if (is_array($groove_credit)) {
      $groove_photographer = isset($groove_credit['photographer']) ? (string) $groove_credit['photographer'] : '';
      $groove_id = isset($groove_credit['pexels_id']) ? (string) $groove_credit['pexels_id'] : '';
    }
    if ($groove_photographer === '' && !empty($groove_result['photographer'])) {
      $groove_photographer = (string) $groove_result['photographer'];
    }
    if ($groove_id === '' && !empty($groove_result['pexels_id'])) {
      $groove_id = (string) $groove_result['pexels_id'];
    }

    if ($groove_photographer !== '' || $groove_id !== '') {
      $groove_note = trim($groove_photographer . ($groove_id !== '' ? ' (#' . $groove_id . ')' : ''));
    }
  }

  $groove_rows[] = array($groove_slug, $groove_status, $groove_dest, $groove_note);
}

if (empty($groove_rows)) {
  groove_pexels_cli_err('The curator returned no results.');
  exit(1);
}

groove_pexels_cli_out(groove_pexels_cli_table(
  array('SLOT', 'STATUS', 'DESTINATION', 'PHOTO'),
  $groove_rows
));
groove_pexels_cli_out('');

$groove_counts = array();
foreach ($groove_rows as $groove_row) {
  $groove_counts[$groove_row[1]] = isset($groove_counts[$groove_row[1]]) ? $groove_counts[$groove_row[1]] + 1 : 1;
}
$groove_summary = array();
foreach ($groove_counts as $groove_status_name => $groove_count) {
  $groove_summary[] = $groove_count . ' ' . $groove_status_name;
}
groove_pexels_cli_out('Result: ' . implode(', ', $groove_summary));

$groove_rate = $groove_client->verify();
if (!is_wp_error($groove_rate) && is_array($groove_rate)) {
  $groove_remaining = isset($groove_rate['remaining']) ? (int) $groove_rate['remaining'] : 0;
  $groove_limit = isset($groove_rate['limit']) ? (int) $groove_rate['limit'] : 0;
  groove_pexels_cli_out('Rate limit: ' . $groove_remaining . ' of ' . $groove_limit . ' requests remaining.');
} else {
  groove_pexels_cli_out('Rate limit: unavailable.');
}

if (class_exists('\Groove\Pexels\Credits')) {
  groove_pexels_cli_out('Credits: ' . \Groove\Pexels\Credits::path());
}

groove_pexels_cli_out('');
groove_pexels_cli_out('Pexels licence: credit photographers and keep a visible "Photos provided by Pexels" link.');

if ($groove_failed > 0) {
  groove_pexels_cli_err($groove_failed . ' slot(s) failed.');
  exit(1);
}

exit(0);
