<?php
namespace Enrove\Setup;

use Enrove\Pexels\Library;
use Enrove\Themes\Font_Library;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * First-run setup: the fonts and photos a fresh install does not have.
 *
 * A site installed from WordPress.org starts with neither (Font_Library and
 * \Enrove\Pexels\Library say why), and nothing about a folio says so: the fonts
 * fall back to system ones and the covers to a gradient, quietly. Left to the
 * Settings tabs, most people would never find out, and would take the themes
 * for plainer than they are.
 *
 * So the first Enrove screen an administrator opens asks, once, in a dialog
 * that also runs the downloads and shows their progress. Each item is a choice
 * with a real "no" — system fonts instead of Google's, gradients instead of
 * photos — and an answer either way settles that item. Closing the dialog
 * settles nothing: it does not open by itself again, but a panel on Overview
 * and a "Finish setup" button in the other screens' headers bring it back
 * until both items are settled. A "no" is not final either: while an item is
 * declined, a quieter panel on Overview (no header button, so a choice made
 * on purpose is not nagged about) opens the same dialog with that choice
 * selected, to download after all.
 *
 * The downloads run as a series of short requests from the dialog rather than
 * one long one, so a slow host shows progress instead of timing out, and a
 * failure names the item that failed.
 *
 * @since 0.5.1
 */
class First_Run
{
  /**
   * User option set once the dialog has opened by itself for that user. A
   * per-site option rather than plain user meta, so on a multisite network
   * each site asks its own administrators once.
   */
  const PROMPTED_META = 'enrove_setup_prompted';

  const NONCE = 'enrove_first_run';

  /**
   * Seconds each step of a download may spend before it stops starting new
   * files. Short, so the progress bar moves; the dialog asks again for the rest.
   */
  const STEP_BUDGET = 6;

  const ITEMS = array('fonts', 'photos');

  /** @var First_Run|null */
  private static $instance = null;

  /**
   * status() for this request. Every Enrove screen asks several times (the
   * menu, the header, the panel, the dialog) and each answer stats every font
   * and photo, so it is worked out once and cleared whenever a choice or a
   * download changes it.
   *
   * @var array|null
   */
  private static $status = null;

  public function __clone()
  {
    _doing_it_wrong(
      __FUNCTION__,
      sprintf('Cloning instances of the singleton "%s" class is forbidden.', esc_html(get_class($this))),
      esc_html(ENROVE_VERSION)
    );
  }

  public function __wakeup()
  {
    _doing_it_wrong(
      __FUNCTION__,
      sprintf('Unserializing instances of the singleton "%s" class is forbidden.', esc_html(get_class($this))),
      esc_html(ENROVE_VERSION)
    );
  }

  public static function instance()
  {
    if (is_null(self::$instance)) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  private function __construct()
  {
    add_action('wp_ajax_enrove_first_run_step', array($this, 'ajax_step'));
    add_action('wp_ajax_enrove_first_run_decline', array($this, 'ajax_decline'));
  }

  // ── State ──────────────────────────────────────────────────────────────

  /**
   * Where each item stands.
   *
   * State is one of:
   *   ready    — everything is on this site (or there is nothing to fetch)
   *   declined — the administrator chose system fonts / no photos
   *   partial  — a download was started and some files are still missing
   *   pending  — never asked, and the files are not here
   *
   * @return array{fonts: array{state: string, total: int, present: int}, photos: array{state: string, total: int, present: int}}
   */
  public static function status(): array
  {
    if (self::$status !== null) {
      return self::$status;
    }

    $fonts = Font_Library::status();
    $font_source = Font_Library::source();

    // A copy of the plugin without the pexels/ helpers has nothing to fetch,
    // which reads as ready. Library's constants are only named once it loads.
    $photos = array('total' => 0, 'present' => 0, 'missing' => array());
    $photo_source = '';
    $photo_declined = 'none';
    if (class_exists('\Enrove\Pexels\Library') && class_exists('\Enrove\Pexels\Credits')) {
      $photos = Library::status();
      $photo_source = Library::source();
      $photo_declined = Library::SOURCE_NONE;
    }

    self::$status = array(
      'fonts' => array(
        'state' => static::state($fonts, $font_source, Font_Library::SOURCE_SYSTEM),
        'total' => (int) $fonts['total'],
        'present' => (int) $fonts['present'],
      ),
      'photos' => array(
        'state' => static::state($photos, $photo_source, $photo_declined),
        'total' => (int) $photos['total'],
        'present' => (int) $photos['present'],
      ),
    );

    return self::$status;
  }

  /**
   * @param array  $status   A library's status(): total, present, missing.
   * @param string $source   The library's recorded choice.
   * @param string $declined The value of that choice that means "no".
   * @return string
   */
  private static function state(array $status, string $source, string $declined): string
  {
    if ($source === $declined) {
      return 'declined';
    }

    if (empty($status['missing'])) {
      return 'ready';
    }

    return $source === '' ? 'pending' : 'partial';
  }

  /**
   * Whether anything is left for an administrator to settle.
   *
   * @return bool
   */
  public static function needs_attention(): bool
  {
    foreach (static::status() as $item) {
      if (in_array($item['state'], array('pending', 'partial'), true)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Whether the current user should be offered setup at all: only an
   * administrator can download into uploads, so nobody else is asked.
   *
   * @return bool
   */
  public static function should_offer(): bool
  {
    return current_user_can('manage_options') && static::needs_attention();
  }

  /**
   * The items the administrator turned down.
   *
   * @return string[] 'fonts', 'photos', or both.
   */
  public static function declined_items(): array
  {
    return array_keys(array_filter(static::status(), static function ($item) {
      return $item['state'] === 'declined';
    }));
  }

  /**
   * Whether the way back to the dialog is offered: nothing is left undecided,
   * but something was declined, and can still be downloaded or switched back
   * to.
   *
   * @return bool
   */
  public static function should_offer_return(): bool
  {
    return current_user_can('manage_options') && !static::needs_attention() && static::declined_items() !== array();
  }

  // ── Requests ───────────────────────────────────────────────────────────

  /**
   * Run one step of one item's download and report where it stands.
   *
   * Pressing Download is the administrator's choice of that source, so the
   * choice is recorded before the first file is fetched.
   */
  public function ajax_step()
  {
    $item = $this->verify_request();

    if ($item === 'fonts') {
      Font_Library::set_source(Font_Library::SOURCE_GOOGLE);
      $result = Font_Library::download_missing(self::STEP_BUDGET);
    } else {
      Library::set_source(Library::SOURCE_PEXELS);
      $result = Library::download_missing(self::STEP_BUDGET);
    }

    self::$status = null;
    $status = static::status();

    wp_send_json_success(array(
      'item' => $item,
      'state' => $status[$item]['state'],
      'total' => $status[$item]['total'],
      'present' => $status[$item]['present'],
      'remaining' => (int) $result['remaining'],
      'failed' => count($result['failed']),
      'error' => empty($result['failed']) ? '' : (string) reset($result['failed']),
      'needsAttention' => static::needs_attention(),
    ));
  }

  /**
   * Record "no" for one item: system fonts, or no photos.
   */
  public function ajax_decline()
  {
    $item = $this->verify_request();

    if ($item === 'fonts') {
      Font_Library::set_source(Font_Library::SOURCE_SYSTEM);
    } else {
      Library::set_source(Library::SOURCE_NONE);
    }

    self::$status = null;
    $status = static::status();

    wp_send_json_success(array(
      'item' => $item,
      'state' => $status[$item]['state'],
      'needsAttention' => static::needs_attention(),
    ));
  }

  /**
   * Check the nonce and capability, and return the item the request names.
   *
   * @return string 'fonts' or 'photos'.
   */
  private function verify_request(): string
  {
    check_ajax_referer(self::NONCE, 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error(array('message' => __('You do not have permission to modify settings.', 'enrove-folios')), 403);
    }

    $item = isset($_POST['item']) ? sanitize_key(wp_unslash($_POST['item'])) : '';
    if (!in_array($item, self::ITEMS, true)) {
      wp_send_json_error(array('message' => __('Unknown setup item.', 'enrove-folios')), 400);
    }

    if ($item === 'photos' && !class_exists('\Enrove\Pexels\Library')) {
      wp_send_json_error(array('message' => __('The Pexels helpers are missing from this copy of the plugin.', 'enrove-folios')), 500);
    }

    return $item;
  }

  // ── Screen ─────────────────────────────────────────────────────────────

  /**
   * Whether the dialog should open by itself on this request: the first
   * Enrove screen this administrator opens, and only once. Reading it marks
   * it done, so a reload or the next screen does not ask again.
   *
   * @return bool
   */
  public static function take_auto_open(): bool
  {
    $user_id = get_current_user_id();
    if ($user_id <= 0 || get_user_option(self::PROMPTED_META, $user_id)) {
      return false;
    }

    update_user_option($user_id, self::PROMPTED_META, 1);

    return true;
  }

  /**
   * Config for assets/js/enrove-setup.js.
   *
   * @param bool $auto_open
   * @return array
   */
  public static function script_settings(bool $auto_open): array
  {
    return array(
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce(self::NONCE),
      'autoOpen' => $auto_open,
      'i18n' => array(
        /* translators: 1: files downloaded so far, 2: files in total. */
        'progress' => __('%1$s of %2$s', 'enrove-folios'),
        'preparing' => __('Starting…', 'enrove-folios'),
        'downloading' => __('Downloading…', 'enrove-folios'),
        'download' => __('Download', 'enrove-folios'),
        'downloadBoth' => __('Download fonts and photos', 'enrove-folios'),
        'downloadFonts' => __('Download fonts', 'enrove-folios'),
        'downloadPhotos' => __('Download photos', 'enrove-folios'),
        'saveChoices' => __('Save choices', 'enrove-folios'),
        'retry' => __('Try again', 'enrove-folios'),
        'done' => __('Done', 'enrove-folios'),
        'notNow' => __('Not now', 'enrove-folios'),
        'close' => __('Close', 'enrove-folios'),
        /* translators: %s: the reason the download failed. */
        'failed' => __('Stopped: %s', 'enrove-folios'),
        'network' => __('The server did not answer. Check your connection and try again.', 'enrove-folios'),
        'expired' => __('This page has been open too long. Reload it and try again.', 'enrove-folios'),
        'stalled' => __('The download stopped making progress. Try again, or check that the uploads folder is writable.', 'enrove-folios'),
        'finished' => __('Setup finished. Folios now use their full fonts and photos.', 'enrove-folios'),
        'finishedDeclined' => __('Setup finished.', 'enrove-folios'),
        'leaveWarning' => __('Downloads are still running. Leave anyway?', 'enrove-folios'),
        'keptChoice' => __('Your earlier choice is kept.', 'enrove-folios'),
      ),
    );
  }

  /**
   * The copy that says what an administrator is missing, for the Overview
   * panel: names only the items still unsettled.
   *
   * @return string
   */
  private static function summary(): string
  {
    $status = static::status();
    $fonts = in_array($status['fonts']['state'], array('pending', 'partial'), true);
    $photos = in_array($status['photos']['state'], array('pending', 'partial'), true);

    if ($fonts && $photos) {
      return __('The theme fonts and sample photos are not on this site yet, so folios use system fonts and gradient covers for now.', 'enrove-folios');
    }

    if ($fonts) {
      return __('The theme fonts are not on this site yet, so folios use system fonts for now.', 'enrove-folios');
    }

    return __('The sample photos are not on this site yet, so folio covers use gradients for now.', 'enrove-folios');
  }

  /**
   * The Overview panel that stands in for the dialog once it has been closed.
   * A standing condition, so it is inline and stays until the items are
   * settled, rather than a toast or a dismissible notice.
   */
  public static function display_overview_panel(): void
  {
    ?>
<section class="g-setup-panel" data-enrove-setup-entry aria-labelledby="g-setup-panel-title">
  <div class="g-setup-panel__text">
    <h2 id="g-setup-panel-title" class="g-setup-panel__title"><?php esc_html_e('Finish setting up', 'enrove-folios'); ?></h2>
    <p class="g-setup-panel__desc"><?php echo esc_html(static::summary()); ?></p>
  </div>
  <button type="button" class="button button-primary" data-enrove-setup-open aria-haspopup="dialog">
    <?php esc_html_e('Set up fonts and photos', 'enrove-folios'); ?>
  </button>
</section>
<?php
  }

  /**
   * The Overview panel once everything is decided but something was declined:
   * says what folios use instead, and opens the dialog to download after all.
   * Quieter than the setup panel, and only here, since the choice was made.
   */
  public static function display_return_panel(): void
  {
    $status = static::status();
    $declined = static::declined_items();
    $fonts_here = static::is_on_site($status['fonts']);
    $photos_here = static::is_on_site($status['photos']);

    if (count($declined) === 2) {
      if ($fonts_here && $photos_here) {
        $desc = __('Folios use system fonts and gradient covers. The theme fonts and photos are on this site, so you can switch back to them at any time.', 'enrove-folios');
      } elseif ($fonts_here) {
        $desc = __('Folios use system fonts and gradient covers. You can switch back to the theme fonts, which are on this site, or download the photos at any time.', 'enrove-folios');
      } elseif ($photos_here) {
        $desc = __('Folios use system fonts and gradient covers. You can download the theme fonts, or switch back to the photos, which are on this site, at any time.', 'enrove-folios');
      } else {
        $desc = __('Folios use system fonts and gradient covers. You can download the theme fonts and photos at any time.', 'enrove-folios');
      }
      $button = __('Set up fonts and photos', 'enrove-folios');
    } elseif ($declined === array('fonts')) {
      $desc = $fonts_here
        ? __('Folios use system fonts. The theme fonts are on this site, so you can switch back to them at any time.', 'enrove-folios')
        : __('Folios use system fonts. You can download the theme fonts at any time.', 'enrove-folios');
      $button = __('Set up fonts', 'enrove-folios');
    } else {
      $desc = $photos_here
        ? __('Folio covers use gradients. The photos are on this site, so you can switch back to them at any time.', 'enrove-folios')
        : __('Folio covers use gradients. You can download the sample photos at any time.', 'enrove-folios');
      $button = __('Set up photos', 'enrove-folios');
    }
    ?>
<section class="g-setup-panel g-setup-panel--quiet" data-enrove-setup-entry aria-labelledby="g-setup-panel-title">
  <div class="g-setup-panel__text">
    <h2 id="g-setup-panel-title" class="g-setup-panel__title"><?php esc_html_e('Fonts and photos', 'enrove-folios'); ?></h2>
    <p class="g-setup-panel__desc"><?php echo esc_html($desc); ?></p>
  </div>
  <button type="button" class="button button-secondary" data-enrove-setup-open aria-haspopup="dialog">
    <?php echo esc_html($button); ?>
  </button>
</section>
<?php
  }

  /**
   * Whether every file of an item is on this site, whatever was chosen.
   *
   * @param array $item An entry from status().
   * @return bool
   */
  private static function is_on_site(array $item): bool
  {
    return $item['total'] > 0 && $item['present'] >= $item['total'];
  }

  /**
   * The header button that brings the dialog back on screens with no panel.
   */
  public static function display_header_entry(): void
  {
    ?>
<button type="button" class="button button-secondary g-setup-entry" data-enrove-setup-open data-enrove-setup-entry aria-haspopup="dialog">
  <span class="g-setup-entry__dot" aria-hidden="true"></span>
  <?php esc_html_e('Finish setup', 'enrove-folios'); ?>
</button>
<?php
  }

  /**
   * The dialog. Printed in the admin footer of every Enrove screen while
   * something is undecided, and of Overview while something is declined, so
   * every entry point opens the same one.
   *
   * Both items are always shown. One that is already on this site —
   * downloaded, or carried in this copy of the plugin — says so in place of its
   * choice, so the dialog describes the whole setup rather than looking as if
   * part of it went missing. A declined one shows its choice again, with "no"
   * selected, so it can be changed.
   */
  public static function render_dialog(): void
  {
    $status = static::status();
    $fonts = $status['fonts'];
    $photos = $status['photos'];

    // A development checkout carries the photos rather than downloading them.
    $photos_bundled = $photos['state'] === 'ready' && class_exists('\Enrove\Pexels\Library') && Library::downloaded_count() === 0;
    ?>
<div id="g-setup-modal" class="g-theme-details g-setup" role="dialog" aria-modal="true"
  aria-labelledby="g-setup-title" aria-describedby="g-setup-intro" hidden>
  <div class="g-theme-details__backdrop" data-enrove-setup-close></div>
  <div class="g-theme-details__dialog g-setup__dialog" tabindex="-1">
    <div class="g-theme-details__header">
      <h2 id="g-setup-title" class="g-theme-details__title"><?php echo static::needs_attention() ? esc_html__('Finish setting up Enrove Folios', 'enrove-folios') : esc_html__('Fonts and photos', 'enrove-folios'); ?></h2>
      <button type="button" class="g-theme-details__close" data-enrove-setup-close
        aria-label="<?php esc_attr_e('Close', 'enrove-folios'); ?>">
        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
      </button>
    </div>
    <div class="g-theme-details__body g-setup__body">
      <p id="g-setup-intro" class="g-setup__intro">
        <?php esc_html_e('Folio themes are designed around web fonts and photographs. Neither comes with the plugin, so your site can download its own copy — once, and only if you choose to.', 'enrove-folios'); ?>
      </p>

      <?php
      static::render_item('fonts', $fonts, array(
        'title' => __('Fonts', 'enrove-folios'),
        'desc' => sprintf(
          /* translators: %s: number of font families. */
          _n(
            '%s font family from Google Fonts, about 4 MB. It is saved to your uploads folder and served from your site, so readers never contact Google.',
            '%s font families from Google Fonts, about 4 MB. They are saved to your uploads folder and served from your site, so readers never contact Google.',
            $fonts['total'],
            'enrove-folios'
          ),
          number_format_i18n($fonts['total'])
        ),
        'download' => static::is_on_site($fonts) ? __('Use the theme fonts', 'enrove-folios') : __('Download from Google Fonts', 'enrove-folios'),
        'download_hint' => static::is_on_site($fonts) ? __('Already on this site. Nothing is downloaded.', 'enrove-folios') : __('How the themes are designed to look.', 'enrove-folios'),
        'decline' => __('Use system fonts', 'enrove-folios'),
        'decline_hint' => __('The fonts the WordPress dashboard uses. Nothing is downloaded.', 'enrove-folios'),
        'bar_label' => __('Fonts downloaded', 'enrove-folios'),
        // Switching back to fonts already here downloads nothing, so it is not
        // reported as a download.
        'ready' => $fonts['state'] === 'declined' && static::is_on_site($fonts)
          ? __('Folios use the theme fonts.', 'enrove-folios')
          : __('Downloaded. Folios use the theme fonts.', 'enrove-folios'),
        'declined' => __('Using system fonts. You can download the theme fonts later in Settings → Fonts.', 'enrove-folios'),
      ));

      static::render_item('photos', $photos, array(
        'title' => __('Photos', 'enrove-folios'),
        'desc' => sprintf(
          /* translators: %s: number of photos. */
          _n(
            '%s photo from Pexels for theme covers and sample content, about 4 MB, saved to your uploads folder.',
            '%s photos from Pexels for theme covers and sample content, about 4 MB, saved to your uploads folder.',
            $photos['total'],
            'enrove-folios'
          ),
          number_format_i18n($photos['total'])
        ),
        'download' => static::is_on_site($photos) ? __('Use the photos', 'enrove-folios') : __('Download from Pexels', 'enrove-folios'),
        'download_hint' => static::is_on_site($photos) ? __('Already on this site. Nothing is downloaded.', 'enrove-folios') : __('Photo covers, and sample folios with their pictures.', 'enrove-folios'),
        'decline' => __('Use gradient covers', 'enrove-folios'),
        'decline_hint' => __('A soft gradient in the theme’s colours until you add your own image.', 'enrove-folios'),
        'bar_label' => __('Photos downloaded', 'enrove-folios'),
        'ready' => $photos_bundled
          ? __('Included with this copy of the plugin. Nothing to download.', 'enrove-folios')
          : ($photos['state'] === 'declined' && static::is_on_site($photos)
            ? __('Covers and sample folios use the photos.', 'enrove-folios')
            : __('Downloaded. Covers and sample folios use the photos.', 'enrove-folios')),
        'declined' => __('Using gradient covers. You can download the photos later in Settings → Imagery.', 'enrove-folios'),
      ));
      ?>

      <p class="g-setup__note">
        <?php esc_html_e('You can change either choice later under Settings → Fonts and Settings → Imagery.', 'enrove-folios'); ?>
      </p>
      <p class="screen-reader-text" aria-live="polite" data-enrove-setup-live></p>
    </div>
    <div class="g-theme-details__footer">
      <button type="button" class="button button-secondary" data-enrove-setup-close data-enrove-setup-dismiss><?php esc_html_e('Not now', 'enrove-folios'); ?></button>
      <button type="button" class="button button-primary" data-enrove-setup-run><?php esc_html_e('Download fonts and photos', 'enrove-folios'); ?></button>
    </div>
  </div>
</div>
<?php
  }

  /**
   * One item of the dialog: its choice while undecided or declined, or that it
   * is on this site.
   *
   * @param string $key    'fonts' or 'photos'.
   * @param array  $item   Its entry from status().
   * @param array  $copy   title, desc, download(_hint), decline(_hint), bar_label, ready, declined.
   */
  private static function render_item(string $key, array $item, array $copy): void
  {
    $settled = $item['state'] === 'ready';
    $declined = $item['state'] === 'declined';
    $show_progress = $settled || $item['state'] === 'partial';
    $total = (int) $item['total'];
    $present = $item['state'] === 'ready' ? $total : (int) $item['present'];
    $pct = $total > 0 ? (int) round($present / $total * 100) : 0;

    if ($item['state'] === 'ready') {
      $status_text = $copy['ready'];
    } elseif ($item['state'] === 'declined') {
      $status_text = $copy['declined'];
    } else {
      /* translators: 1: files downloaded so far, 2: files in total. */
      $status_text = sprintf(__('%1$s of %2$s', 'enrove-folios'), number_format_i18n($present), number_format_i18n($total));
    }
    ?>
      <fieldset class="g-setup__item" data-enrove-setup-item="<?php echo esc_attr($key); ?>" data-state="<?php echo esc_attr($item['state']); ?>"
        data-total="<?php echo esc_attr((string) $total); ?>" data-present="<?php echo esc_attr((string) $present); ?>"
        data-ready-text="<?php echo esc_attr($copy['ready']); ?>" data-declined-text="<?php echo esc_attr($copy['declined']); ?>"
        data-was-declined="<?php echo $declined ? '1' : '0'; ?>">
        <legend class="g-setup__item-title"><?php echo esc_html($copy['title']); ?></legend>
        <p class="g-setup__item-desc"><?php echo esc_html($copy['desc']); ?></p>
        <div class="g-choices"<?php echo $settled ? ' hidden' : ''; ?>>
          <label class="g-choice">
            <input type="radio" name="g-setup-<?php echo esc_attr($key); ?>" value="download"<?php checked(!$declined); ?><?php disabled($settled, true); ?> />
            <span class="g-choice__text">
              <span class="g-choice__label"><?php echo esc_html($copy['download']); ?></span>
              <span class="g-choice__hint"><?php echo esc_html($copy['download_hint']); ?></span>
            </span>
          </label>
          <label class="g-choice">
            <input type="radio" name="g-setup-<?php echo esc_attr($key); ?>" value="decline"<?php checked($declined); ?><?php disabled($settled, true); ?> />
            <span class="g-choice__text">
              <span class="g-choice__label"><?php echo esc_html($copy['decline']); ?></span>
              <span class="g-choice__hint"><?php echo esc_html($copy['decline_hint']); ?></span>
            </span>
          </label>
        </div>
        <div class="g-setup__progress"<?php echo $show_progress ? '' : ' hidden'; ?>>
          <div class="g-setup__bar" role="progressbar" aria-label="<?php echo esc_attr($copy['bar_label']); ?>"
            aria-valuemin="0" aria-valuemax="<?php echo esc_attr((string) $total); ?>" aria-valuenow="<?php echo esc_attr((string) $present); ?>">
            <span class="g-setup__bar-fill" style="width: <?php echo esc_attr((string) $pct); ?>%"></span>
          </div>
          <p class="g-setup__status" data-enrove-setup-status><?php echo esc_html($status_text); ?></p>
        </div>
      </fieldset>
<?php
  }
}
