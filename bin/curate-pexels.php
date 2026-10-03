<?php
/**
 * Enrove Folios — Pexels curation CLI.
 *
 * Downloads the theme covers and the shared placeholder pool from Pexels once,
 * so the shipped plugin never talks to the Pexels API at runtime.
 *
 * Usage:
 *   php bin/curate-pexels.php [options]
 *
 * Options:
 *   --slots=a,b,c        Only curate these slot slugs (default: every slot).
 *   --theme=<slug>       Only curate the cover + imagery set for one theme.
 *   --set=<slug>         Only curate one imagery set (see pexels/sets.php).
 *   --covers-only        Only curate theme cover slots.
 *   --placeholders-only  Only curate shared placeholder slots.
 *   --force              Re-download slots whose file already exists.
 *   --dry-run            Resolve and report, download nothing.
 *   --wp=/path/to/wp     WordPress root (dir containing wp-load.php).
 *   --help               Show this help.
 *
 * The API key is never printed. Supply it via one of (highest priority first):
 *   1. ENROVE_PEXELS_API_KEY constant in wp-config.php
 *   2. PEXELS_API_KEY environment variable
 *   3. .pexels-key file in the plugin root (gitignored, single line)
 *   4. Settings → Imagery in the WordPress admin
 *
 * @package Enrove
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

if (!defined('ENROVE_PEXELS_CLI')) {
  define('ENROVE_PEXELS_CLI', true);
}

define('ENROVE_PEXELS_CLI_PLUGIN_DIR', dirname(__DIR__));

// ---------------------------------------------------------------------------
// Small output helpers.
// ---------------------------------------------------------------------------

if (!function_exists('enrove_pexels_cli_out')) {
  /**
   * Write a line to STDOUT.
   *
   * @param string $line Line to write.
   * @return void
   */
  function enrove_pexels_cli_out($line = '')
  {
    fwrite(STDOUT, $line . PHP_EOL);
  }

  /**
   * Write a line to STDERR.
   *
   * @param string $line Line to write.
   * @return void
   */
  function enrove_pexels_cli_err($line = '')
  {
    fwrite(STDERR, $line . PHP_EOL);
  }

  /**
   * The usage / help text.
   *
   * @return string
   */
  function enrove_pexels_cli_usage()
  {
    return implode(PHP_EOL, array(
      'Enrove Folios — Pexels curation',
      '',
      'Usage:',
      '  php bin/curate-pexels.php [options]',
      '',
      'Options:',
      '  --slots=a,b,c        Only curate these slot slugs (comma separated).',
      '  --theme=<slug>       Only curate the cover + imagery set for one theme.',
      '  --set=<slug>         Only curate one imagery set (see pexels/sets.php).',
      '  --covers-only        Only curate theme cover slots.',
      '  --placeholders-only  Only curate shared placeholder slots.',
      '  --force              Re-download slots whose file already exists.',
      '  --dry-run            Resolve and report, download nothing.',
      '  --wp=<path>          WordPress root (the directory holding wp-load.php).',
      '  --help               Show this help.',
      '',
      'API key sources, highest priority first:',
      '  1. ENROVE_PEXELS_API_KEY constant in wp-config.php   (recommended for production)',
      '  2. PEXELS_API_KEY environment variable',
      '  3. .pexels-key file in the plugin root               (recommended for local dev)',
      '  4. The enrove_pexels_api_key WordPress option (Settings → Imagery)',
      '',
      'The key itself is never printed by this script.',
    ));
  }

  /**
   * The imagery set a bundled theme seeds from, read straight from its setup.
   *
   * Deliberately reads the file rather than asking Themes_Manager: the curator
   * runs before the theme registry is necessarily warm, and a plain array
   * return costs nothing to require.
   *
   * @param string $theme Theme slug.
   * @return string Set slug, or '' when the theme declares none.
   */
  function enrove_pexels_cli_theme_set($theme)
  {
    $setup = ENROVE_PEXELS_CLI_PLUGIN_DIR . '/themes/' . $theme . '/setup.php';
    if (!is_readable($setup)) {
      return '';
    }

    $data = require $setup;

    return is_array($data) && !empty($data['image_set']) ? (string) $data['image_set'] : '';
  }

  /**
   * Parse argv into an options array.
   *
   * @param array $argv Raw arguments.
   * @return array
   */
  function enrove_pexels_cli_parse_args($argv)
  {
    $opts = array(
      'slots' => array(),
      'theme' => '',
      'set' => '',
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
        case 'set':
          $opts['set'] = trim($value);
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
  function enrove_pexels_cli_root_hosts_plugin($root, $plugin_dir)
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
   * Checks --wp, then ENROVE_WP_ROOT, then walks up from the plugin directory
   * (and peeks one level into each ancestor, because this plugin is commonly
   * symlinked into wp-content/plugins from a sibling checkout).
   *
   * @param string $explicit   Value of --wp, or ''.
   * @param string $plugin_dir Plugin directory.
   * @return string Absolute path to the WordPress root, or '' when not found.
   */
  function enrove_pexels_cli_find_wp_root($explicit, $plugin_dir)
  {
    $candidates = array();

    if ($explicit !== '') {
      $candidates[] = rtrim($explicit, '/');
    }

    $env_root = getenv('ENROVE_WP_ROOT');
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
      if (enrove_pexels_cli_root_hosts_plugin($candidate, $plugin_dir)) {
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
  function enrove_pexels_cli_normalise_slot($key, $entry)
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
  function enrove_pexels_cli_table($headers, $rows)
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

$enrove_argv = isset($argv) && is_array($argv) ? $argv : array();
$enrove_opts = enrove_pexels_cli_parse_args($enrove_argv);

if ($enrove_opts['help']) {
  enrove_pexels_cli_out(enrove_pexels_cli_usage());
  exit(0);
}

if (!empty($enrove_opts['unknown'])) {
  enrove_pexels_cli_err('Unknown option(s): --' . implode(', --', $enrove_opts['unknown']));
  enrove_pexels_cli_err('');
  enrove_pexels_cli_err(enrove_pexels_cli_usage());
  exit(1);
}

if ($enrove_opts['covers_only'] && $enrove_opts['placeholders_only']) {
  enrove_pexels_cli_err('--covers-only and --placeholders-only are mutually exclusive.');
  exit(1);
}

// ---------------------------------------------------------------------------
// Bootstrap WordPress (skipped when already loaded, e.g. wp eval-file).
// ---------------------------------------------------------------------------

if (!defined('ABSPATH')) {
  if ($enrove_opts['wp'] !== '' && !is_file(rtrim($enrove_opts['wp'], '/') . '/wp-load.php')) {
    enrove_pexels_cli_err('No wp-load.php in --wp=' . $enrove_opts['wp']);
    exit(1);
  }

  $enrove_wp_root = enrove_pexels_cli_find_wp_root($enrove_opts['wp'], ENROVE_PEXELS_CLI_PLUGIN_DIR);

  if ($enrove_wp_root === '') {
    enrove_pexels_cli_err('Could not locate wp-load.php.');
    enrove_pexels_cli_err('Pass the WordPress root explicitly, e.g.:');
    enrove_pexels_cli_err('  php bin/curate-pexels.php --wp=/path/to/wordpress');
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

  require_once $enrove_wp_root . '/wp-load.php';

  enrove_pexels_cli_out('WordPress: ' . $enrove_wp_root);
}

if (!defined('ENROVE_PATH')) {
  enrove_pexels_cli_err('The Enrove Folios plugin is not active on this site — ENROVE_PATH is undefined.');
  enrove_pexels_cli_err('Activate the plugin, then run this script again.');
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

if (!class_exists('\Enrove\Pexels\Key') || !class_exists('\Enrove\Pexels\Client') || !class_exists('\Enrove\Pexels\Curator')) {
  enrove_pexels_cli_err('The Enrove\\Pexels classes could not be autoloaded.');
  enrove_pexels_cli_err('Expected them in: ' . ENROVE_PATH . 'pexels/');
  exit(1);
}

// ---------------------------------------------------------------------------
// Key.
// ---------------------------------------------------------------------------

$enrove_key_source = \Enrove\Pexels\Key::source();

if ($enrove_key_source === '' || \Enrove\Pexels\Key::resolve() === '') {
  enrove_pexels_cli_err('No Pexels API key found.');
  enrove_pexels_cli_err('');
  enrove_pexels_cli_err('Supply it in one of these ways (checked in this order):');
  enrove_pexels_cli_err('');
  enrove_pexels_cli_err('  1. wp-config.php constant (recommended for production)');
  enrove_pexels_cli_err("       define('ENROVE_PEXELS_API_KEY', 'your-key-here');");
  enrove_pexels_cli_err('');
  enrove_pexels_cli_err('  2. Environment variable');
  enrove_pexels_cli_err('       PEXELS_API_KEY=your-key-here php bin/curate-pexels.php');
  enrove_pexels_cli_err('');
  enrove_pexels_cli_err('  3. Key file in the plugin root (gitignored, recommended for local dev)');
  enrove_pexels_cli_err('       printf %s "your-key-here" > ' . ENROVE_PEXELS_CLI_PLUGIN_DIR . '/.pexels-key');
  enrove_pexels_cli_err('');
  enrove_pexels_cli_err('  4. WordPress setting');
  enrove_pexels_cli_err('       WP Admin → Enrove → Settings → Imagery → Pexels API key');
  enrove_pexels_cli_err('');
  enrove_pexels_cli_err('Get a free key at https://www.pexels.com/api/');
  exit(1);
}

enrove_pexels_cli_out('Key source: ' . $enrove_key_source . ' (' . \Enrove\Pexels\Key::masked() . ')');

// ---------------------------------------------------------------------------
// Manifest + slot selection.
// ---------------------------------------------------------------------------

$enrove_manifest_file = '';
foreach (array(ENROVE_PATH . 'pexels/manifest.php', ENROVE_PATH . 'includes/pexels/manifest.php') as $enrove_candidate) {
  if (is_readable($enrove_candidate)) {
    $enrove_manifest_file = $enrove_candidate;
    break;
  }
}

if ($enrove_manifest_file === '') {
  enrove_pexels_cli_err('Slot manifest not found (looked for pexels/manifest.php).');
  exit(1);
}

$enrove_manifest = require $enrove_manifest_file;
if (!is_array($enrove_manifest) || empty($enrove_manifest)) {
  enrove_pexels_cli_err('Slot manifest is empty or did not return an array: ' . $enrove_manifest_file);
  exit(1);
}

$enrove_slots = array();
foreach ($enrove_manifest as $enrove_key => $enrove_entry) {
  $enrove_slot = enrove_pexels_cli_normalise_slot($enrove_key, $enrove_entry);
  if ($enrove_slot === null) {
    continue;
  }
  $enrove_slots[$enrove_slot['slug']] = $enrove_slot;
}

$enrove_selected = $enrove_slots;

if ($enrove_opts['covers_only']) {
  $enrove_selected = array_filter($enrove_selected, function ($slot) {
    return $slot['kind'] === 'cover';
  });
}

if ($enrove_opts['placeholders_only']) {
  $enrove_selected = array_filter($enrove_selected, function ($slot) {
    return $slot['kind'] !== 'cover';
  });
}

if ($enrove_opts['theme'] !== '') {
  $enrove_theme = $enrove_opts['theme'];
  $enrove_theme_slots = array_filter($enrove_selected, function ($slot) use ($enrove_theme) {
    return isset($slot['theme']) && $slot['theme'] === $enrove_theme;
  });

  if (empty($enrove_theme_slots)) {
    enrove_pexels_cli_err('No slots found for theme "' . $enrove_theme . '".');
    exit(1);
  }

  // A theme run means its cover plus the imagery set it seeds from. A theme
  // that declares no set predates sets.php, so it still gets the whole shared
  // pool — that is the only imagery it can possibly reference.
  if (!$enrove_opts['covers_only']) {
    $enrove_theme_set = enrove_pexels_cli_theme_set($enrove_theme);

    foreach ($enrove_selected as $enrove_slug => $enrove_slot) {
      if ($enrove_slot['kind'] === 'cover') {
        continue;
      }
      $enrove_slot_set = isset($enrove_slot['set']) ? (string) $enrove_slot['set'] : '';
      if ($enrove_theme_set === '' ? $enrove_slot_set === '' : $enrove_slot_set === $enrove_theme_set) {
        $enrove_theme_slots[$enrove_slug] = $enrove_slot;
      }
    }
  }

  $enrove_selected = $enrove_theme_slots;
}

if ($enrove_opts['set'] !== '') {
  $enrove_set = $enrove_opts['set'];
  $enrove_selected = array_filter($enrove_selected, function ($slot) use ($enrove_set) {
    return isset($slot['set']) && $slot['set'] === $enrove_set;
  });

  if (empty($enrove_selected)) {
    enrove_pexels_cli_err('No slots found for set "' . $enrove_set . '".');
    exit(1);
  }
}

if (!empty($enrove_opts['slots'])) {
  $enrove_missing = array();
  $enrove_wanted = array();
  foreach ($enrove_opts['slots'] as $enrove_slug) {
    if (isset($enrove_selected[$enrove_slug])) {
      $enrove_wanted[$enrove_slug] = $enrove_selected[$enrove_slug];
    } elseif (isset($enrove_slots[$enrove_slug])) {
      // Named explicitly but excluded by another filter — honour the explicit name.
      $enrove_wanted[$enrove_slug] = $enrove_slots[$enrove_slug];
    } else {
      $enrove_missing[] = $enrove_slug;
    }
  }

  if (!empty($enrove_missing)) {
    enrove_pexels_cli_err('Unknown slot(s): ' . implode(', ', $enrove_missing));
    enrove_pexels_cli_err('Known slots: ' . implode(', ', array_keys($enrove_slots)));
    exit(1);
  }

  $enrove_selected = $enrove_wanted;
}

if (empty($enrove_selected)) {
  enrove_pexels_cli_err('No slots selected.');
  exit(1);
}

enrove_pexels_cli_out('Slots: ' . count($enrove_selected) . ' of ' . count($enrove_slots)
  . ($enrove_opts['dry_run'] ? '  (dry run)' : '')
  . ($enrove_opts['force'] ? '  (force)' : ''));
enrove_pexels_cli_out('');

// ---------------------------------------------------------------------------
// Run.
// ---------------------------------------------------------------------------

$enrove_client = new \Enrove\Pexels\Client();

if (!$enrove_client->has_key()) {
  enrove_pexels_cli_err('The Pexels client reports no usable key.');
  exit(1);
}

$enrove_probe = $enrove_client->verify();
if (is_wp_error($enrove_probe)) {
  enrove_pexels_cli_err('Pexels rejected the key: ' . $enrove_probe->get_error_message());
  enrove_pexels_cli_err('Check the key supplied via "' . $enrove_key_source . '".');
  exit(1);
}

$enrove_curator = new \Enrove\Pexels\Curator($enrove_client);
$enrove_results = $enrove_curator->run($enrove_selected, array(
  'force' => (bool) $enrove_opts['force'],
  'dry_run' => (bool) $enrove_opts['dry_run'],
));

// ---------------------------------------------------------------------------
// Report.
// ---------------------------------------------------------------------------

$enrove_rows = array();
$enrove_failed = 0;
$enrove_plugin_path = rtrim(ENROVE_PATH, '/') . '/';

foreach ((array) $enrove_results as $enrove_result) {
  if (!is_array($enrove_result)) {
    continue;
  }

  $enrove_slug = isset($enrove_result['slot']) ? (string) $enrove_result['slot'] : '';
  if ($enrove_slug === '' && isset($enrove_result['slug'])) {
    $enrove_slug = (string) $enrove_result['slug'];
  }

  $enrove_status = isset($enrove_result['status']) ? (string) $enrove_result['status'] : 'unknown';
  if ($enrove_status === 'failed' || $enrove_status === 'error') {
    $enrove_failed++;
  }

  $enrove_dest = isset($enrove_result['path']) ? (string) $enrove_result['path'] : '';
  if ($enrove_dest !== '' && strpos($enrove_dest, $enrove_plugin_path) === 0) {
    $enrove_dest = substr($enrove_dest, strlen($enrove_plugin_path));
  }

  $enrove_note = '';
  if (!empty($enrove_result['error'])) {
    $enrove_note = is_string($enrove_result['error']) ? $enrove_result['error'] : 'error';
  } else {
    $enrove_credit = null;
    if (class_exists('\Enrove\Pexels\Credits') && $enrove_slug !== '') {
      $enrove_credit = \Enrove\Pexels\Credits::get($enrove_slug);
    }

    $enrove_photographer = '';
    $enrove_id = '';
    if (is_array($enrove_credit)) {
      $enrove_photographer = isset($enrove_credit['photographer']) ? (string) $enrove_credit['photographer'] : '';
      $enrove_id = isset($enrove_credit['pexels_id']) ? (string) $enrove_credit['pexels_id'] : '';
    }
    if ($enrove_photographer === '' && !empty($enrove_result['photographer'])) {
      $enrove_photographer = (string) $enrove_result['photographer'];
    }
    if ($enrove_id === '' && !empty($enrove_result['pexels_id'])) {
      $enrove_id = (string) $enrove_result['pexels_id'];
    }

    if ($enrove_photographer !== '' || $enrove_id !== '') {
      $enrove_note = trim($enrove_photographer . ($enrove_id !== '' ? ' (#' . $enrove_id . ')' : ''));
    }
  }

  $enrove_rows[] = array($enrove_slug, $enrove_status, $enrove_dest, $enrove_note);
}

if (empty($enrove_rows)) {
  enrove_pexels_cli_err('The curator returned no results.');
  exit(1);
}

enrove_pexels_cli_out(enrove_pexels_cli_table(
  array('SLOT', 'STATUS', 'DESTINATION', 'PHOTO'),
  $enrove_rows
));
enrove_pexels_cli_out('');

$enrove_counts = array();
foreach ($enrove_rows as $enrove_row) {
  $enrove_counts[$enrove_row[1]] = isset($enrove_counts[$enrove_row[1]]) ? $enrove_counts[$enrove_row[1]] + 1 : 1;
}
$enrove_summary = array();
foreach ($enrove_counts as $enrove_status_name => $enrove_count) {
  $enrove_summary[] = $enrove_count . ' ' . $enrove_status_name;
}
enrove_pexels_cli_out('Result: ' . implode(', ', $enrove_summary));

$enrove_rate = $enrove_client->verify();
if (!is_wp_error($enrove_rate) && is_array($enrove_rate)) {
  $enrove_remaining = isset($enrove_rate['remaining']) ? (int) $enrove_rate['remaining'] : 0;
  $enrove_limit = isset($enrove_rate['limit']) ? (int) $enrove_rate['limit'] : 0;
  enrove_pexels_cli_out('Rate limit: ' . $enrove_remaining . ' of ' . $enrove_limit . ' requests remaining.');
} else {
  enrove_pexels_cli_out('Rate limit: unavailable.');
}

if (class_exists('\Enrove\Pexels\Credits')) {
  enrove_pexels_cli_out('Credits: ' . \Enrove\Pexels\Credits::path());
}

enrove_pexels_cli_out('');
enrove_pexels_cli_out('Pexels licence: credit photographers and keep a visible "Photos provided by Pexels" link.');

if ($enrove_failed > 0) {
  enrove_pexels_cli_err($enrove_failed . ' slot(s) failed.');
  exit(1);
}

exit(0);
