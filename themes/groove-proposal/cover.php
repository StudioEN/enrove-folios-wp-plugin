<?php
namespace Groove\Themes\Groove_Proposal;

use Groove\Themes\Base_Theme;
use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
  exit;
}

class Cover extends Base_Theme
{
  public $subtitle;
  public $proposal_meta = [];

  public function ensure_script()
  {
    parent::ensure_script();

    $js_path = trailingslashit(GROOVE_PATH) . 'themes/' . static::get_id() . '/assets/js/groove-proposal.js';
    $version = file_exists($js_path) ? filemtime($js_path) : GROOVE_VERSION;

    wp_enqueue_script(
      'groove-proposal-theme',
      $this->get_theme_assets_url() . 'js/groove-proposal.js',
      [],
      $version,
      true
    );
  }

  public function get_page_data()
  {
    $page = parent::get_page_data();
    $this->subtitle = $page ? get_post_meta($page->ID, 'subtitle', true) : '';
  }

  public function get_data()
  {
    if ($this->is_preview_mode) { return; }
    parent::get_data();
    $this->proposal_meta = $this->get_proposal_meta((int) $this->id);
  }

  protected function get_proposal_meta(int $folio_id): array
  {
    if ($folio_id <= 0) {
      return [];
    }

    return [
      'proposal_version'          => trim((string) get_post_meta($folio_id, 'proposal_version', true)),
      'proposal_status'           => trim((string) get_post_meta($folio_id, 'proposal_status', true)),
      'proposal_prepared_for'     => trim((string) get_post_meta($folio_id, 'proposal_prepared_for', true)),
      'proposal_contact_email'    => sanitize_email((string) get_post_meta($folio_id, 'proposal_contact_email', true)),
      'proposal_contact_name'     => trim((string) get_post_meta($folio_id, 'proposal_contact_name', true)),
      'proposal_contact_role'     => trim((string) get_post_meta($folio_id, 'proposal_contact_role', true)),
      'proposal_contact_phone'    => trim((string) get_post_meta($folio_id, 'proposal_contact_phone', true)),
      'proposal_contact_linkedin' => esc_url_raw((string) get_post_meta($folio_id, 'proposal_contact_linkedin', true)),
      'proposal_contacts'         => trim((string) get_post_meta($folio_id, 'proposal_contacts', true)),
      'proposal_client_name'      => trim((string) get_post_meta($folio_id, 'proposal_client_name', true)),
      'proposal_client_logo_url'  => esc_url_raw((string) get_post_meta($folio_id, 'proposal_client_logo_url', true)),
      'proposal_date'             => trim((string) get_post_meta($folio_id, 'proposal_date', true)),
      'proposal_color_scheme'     => ((string) get_post_meta($folio_id, 'proposal_color_scheme', true) === 'dynamic') ? 'dynamic' : 'default',
      'proposal_open_text'        => trim((string) get_post_meta($folio_id, 'proposal_open_text', true)),
    ];
  }

  protected function get_first_page_url(): string
  {
    if (empty($this->pages) || !is_array($this->pages)) {
      return '';
    }

    $first_page = $this->pages[0] ?? null;
    if (!$first_page || !isset($first_page->ID)) {
      return '';
    }

    return (string) Utils::get_folio_permalink_by_id((int) $first_page->ID);
  }

  protected function get_palette_source_url(): string
  {
    if ($this->id > 0 && has_post_thumbnail($this->id)) {
      return (string) get_the_post_thumbnail_url($this->id, 'full');
    }

    return !empty($this->theme_cover_url) ? (string) $this->theme_cover_url : '';
  }

  protected function normalize_display_date(string $date): string
  {
    if ($date === '') {
      return '';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
      return $date;
    }

    return (string) wp_date('F j, Y', $timestamp);
  }

  protected function get_client_name(): string
  {
    $client_name = trim((string) ($this->proposal_meta['proposal_client_name'] ?? ''));
    if ($client_name !== '') {
      return $client_name;
    }

    return trim((string) ($this->proposal_meta['proposal_prepared_for'] ?? ''));
  }

  protected function get_cover_status_text(): string
  {
    if ((int) $this->id > 0 && get_post_status((int) $this->id) === 'publish') {
      return '';
    }

    return trim((string) ($this->proposal_meta['proposal_status'] ?? ''));
  }

  protected function get_cover_cta_text(): string
  {
    $cta_text = trim((string) ($this->proposal_meta['proposal_open_text'] ?? ''));
    if ($cta_text !== '') {
      return $cta_text;
    }

    return (string) __('Open proposal', 'groove-folios');
  }

  protected function get_contacts(): array
  {
    $contacts = [];
    $raw = trim((string) ($this->proposal_meta['proposal_contacts'] ?? ''));

    if ($raw !== '') {
      $lines = preg_split('/\r\n|\r|\n/', $raw);
      if (is_array($lines)) {
        foreach ($lines as $line) {
          $line = trim((string) $line);
          if ($line === '') {
            continue;
          }

          $parts = array_map('trim', explode('|', $line));
          $contacts[] = [
            'name'  => (string) ($parts[0] ?? ''),
            'role'  => (string) ($parts[1] ?? ''),
            'email' => sanitize_email((string) ($parts[2] ?? '')),
            'phone' => (string) ($parts[3] ?? ''),
          ];
        }
      }
    }

    if (!empty($contacts)) {
      return $contacts;
    }

    $single = [
      'name'  => trim((string) ($this->proposal_meta['proposal_contact_name'] ?? '')),
      'role'  => trim((string) ($this->proposal_meta['proposal_contact_role'] ?? '')),
      'email' => sanitize_email((string) ($this->proposal_meta['proposal_contact_email'] ?? '')),
      'phone' => trim((string) ($this->proposal_meta['proposal_contact_phone'] ?? '')),
    ];

    if ($single['name'] === '' && !empty($this->author)) {
      $single['name'] = trim((string) $this->author);
    }

    $has_data = false;
    foreach ($single as $value) {
      if ((string) $value !== '') {
        $has_data = true;
        break;
      }
    }

    return $has_data ? [$single] : [];
  }

  protected function display_theme_bootstrap_script(): void
  {
    ?>
    <script>
      (function () {
        try {
          var saved = localStorage.getItem('gp-theme');
          var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
          var mode = saved === 'dark' || saved === 'light' ? saved : (prefersDark ? 'dark' : 'light');
          document.documentElement.classList.remove('gp-theme-light', 'gp-theme-dark');
          document.documentElement.classList.add(mode === 'dark' ? 'gp-theme-dark' : 'gp-theme-light');
        } catch (error) {
          document.documentElement.classList.add('gp-theme-light');
        }
      })();
    </script>
    <?php
  }

  protected function display_nav(): void
  {
    $date = $this->normalize_display_date((string) ($this->proposal_meta['proposal_date'] ?? ''));

    display_proposal_navigation_pane([
      'class_prefix'    => 'g-folio__theme-page-nav',
      'alias_prefix'    => 'g-folio__theme-nav',
      'title'           => (string) $this->title,
      'label'           => __('Proposal', 'groove-folios'),
      'aria_label'      => __('Proposal navigation', 'groove-folios'),
      'pages'           => is_array($this->pages) ? $this->pages : [],
      'current_page_id' => 0,
      'info_version'    => trim((string) ($this->proposal_meta['proposal_version'] ?? '')),
      'info_status'     => trim((string) ($this->proposal_meta['proposal_status'] ?? '')),
      'info_date'       => $date,
      'info_contacts'   => $this->get_contacts(),
    ]);
  }

  /**
   * Renders a single meta datum in the flat document-control row.
   */
  protected function display_meta_item(string $label, string $value): void
  {
    if ($value === '') {
      return;
    }
    ?>
    <div class="gp-cover__meta-item">
      <span class="gp-cover__meta-label"><?php echo esc_html($label); ?></span>
      <span class="gp-cover__meta-value"><?php echo esc_html($value); ?></span>
    </div>
    <?php
  }

  /**
   * Renders a single contact in the flat contact block.
   */
  protected function display_contact_item(array $contact): void
  {
    $name  = trim((string) ($contact['name'] ?? ''));
    $role  = trim((string) ($contact['role'] ?? ''));
    $email = sanitize_email((string) ($contact['email'] ?? ''));
    $phone = trim((string) ($contact['phone'] ?? ''));
    ?>
    <div class="gp-cover__contact">
      <div class="gp-cover__contact-person">
        <?php if ($name !== ''): ?>
          <p class="gp-cover__contact-name"><?php echo esc_html($name); ?></p>
        <?php endif; ?>
        <?php if ($role !== ''): ?>
          <p class="gp-cover__contact-role"><?php echo esc_html($role); ?></p>
        <?php endif; ?>
      </div>
      <div class="gp-cover__contact-details">
        <?php if ($email !== ''): ?>
          <p class="gp-cover__contact-detail"><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></p>
        <?php endif; ?>
        <?php if ($phone !== ''): ?>
          <p class="gp-cover__contact-detail"><?php echo esc_html($phone); ?></p>
        <?php endif; ?>
      </div>
    </div>
    <?php
  }

  public function display_theme()
  {
    if (!parent::display_theme()) {
      return;
    }

    $first_page_url  = $this->get_first_page_url();
    $status          = $this->get_cover_status_text();
    $version         = trim((string) ($this->proposal_meta['proposal_version'] ?? ''));
    $date            = $this->normalize_display_date((string) ($this->proposal_meta['proposal_date'] ?? ''));
    $client_name     = $this->get_client_name();
    $client_logo_url = esc_url((string) ($this->proposal_meta['proposal_client_logo_url'] ?? ''));
    $contacts        = $this->get_contacts();
    $cta_text        = $this->get_cover_cta_text();
    $has_meta        = $version !== '' || $date !== '' || $status !== '';
    $proposal_color_scheme = ((string) ($this->proposal_meta['proposal_color_scheme'] ?? 'default') === 'dynamic') ? 'dynamic' : 'default';
    $palette_source_url = $this->get_palette_source_url();
    $cover_image_url = $palette_source_url;
    ?>
    <?php $this->display_theme_bootstrap_script(); ?>
    <div
      class="gp gp-cover g-folio__theme-cover"
      data-gp-color-scheme="<?php echo esc_attr($proposal_color_scheme); ?>"
      data-gp-palette-source-url="<?php echo esc_url($palette_source_url); ?>"
    >

      <!-- Mobile-only header: nav trigger only -->
      <header class="gp-cover__mobile-header">
        <button type="button" class="g-folio__theme-nav-button gp-nav-trigger" aria-label="<?php echo esc_attr__('Open proposal navigation', 'groove-folios'); ?>">
          <svg class="g-folio__theme-menu-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
          <span><?php echo esc_html__('Contents', 'groove-folios'); ?></span>
        </button>
      </header>

      <!-- Desktop + mobile layout: [nav sidebar | content] -->
      <div class="gp-layout">
        <?php $this->display_nav(); ?>

        <div class="gp-cover__content-area">
          <div class="gp-cover__document">

            <!-- Identity: client logo | divider | agency logo -->
            <div class="gp-cover__identity">
              <?php if ($client_logo_url !== ''): ?>
                <i class="gp-cover__identity-logo">
                  <img src="<?php echo esc_url($client_logo_url); ?>" alt="<?php echo esc_attr($client_name !== '' ? $client_name : __('Client logo', 'groove-folios')); ?>" />
                </i>
              <?php endif; ?>
              <?php if ($client_logo_url !== '' && !empty($this->theme_logo_url)): ?>
                <span class="gp-cover__identity-divider"></span>
              <?php endif; ?>
              <?php if (!empty($this->theme_logo_url)): ?>
                <i class="gp-cover__identity-logo">
                  <img src="<?php echo esc_url($this->theme_logo_url); ?>" alt="<?php echo esc_attr($this->theme_name); ?>" />
                </i>
              <?php endif; ?>
            </div>

            <div class="gp-cover__body">

            <!-- Title block: the dominant element -->
            <section class="gp-cover__title-section">
              <?php if ($this->subtitle !== ''): ?>
                <p class="gp-cover__engagement"><?php echo esc_html($this->subtitle); ?></p>
              <?php endif; ?>
              <h1 class="gp-cover__title"><?php echo esc_html($this->title); ?></h1>
              <?php if ($client_name !== ''): ?>
                <p class="gp-cover__client-line">
                  <?php
                  /* translators: %s: client name */
                  echo esc_html(sprintf(__('Prepared for %s', 'groove-folios'), $client_name)); ?>
                </p>
              <?php endif; ?>
            </section>

            <hr class="gp-cover__rule">

            <div class="gp-cover__lower">
              <div class="gp-cover__lower-info">
                <!-- Document control: flat row -->
                <?php if ($has_meta): ?>
                  <div class="gp-cover__meta-row" aria-label="<?php echo esc_attr__('Document control', 'groove-folios'); ?>">
                    <?php $this->display_meta_item(__('Version', 'groove-folios'), $version); ?>
                    <?php $this->display_meta_item(__('Date', 'groove-folios'), $date); ?>
                    <?php $this->display_meta_item(__('Status', 'groove-folios'), $status); ?>
                  </div>
                <?php endif; ?>

                <!-- Contact details -->
                <?php if (!empty($contacts)): ?>
                  <div class="gp-cover__contacts-section">
                    <p class="gp-cover__contacts-label"><?php echo esc_html__('Contact', 'groove-folios'); ?></p>
                    <div class="gp-cover__contacts">
                      <?php foreach ($contacts as $contact): ?>
                        <?php $this->display_contact_item($contact); ?>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>
              </div>

              <?php if ($first_page_url !== ''): ?>
                <div class="gp-cover__lower-cta">
                  <a class="gp-cover__cta" href="<?php echo esc_url($first_page_url); ?>">
                    <?php echo esc_html($cta_text); ?> &rarr;
                  </a>
                </div>
              <?php endif; ?>
            </div>

            </div><!-- .gp-cover__body -->

          </div><!-- .gp-cover__document -->

          <?php if ($cover_image_url !== ''): ?>
            <figure class="gp-cover__hero" data-gp-hero-contrast>
              <img
                src="<?php echo esc_url($cover_image_url); ?>"
                alt=""
                class="gp-cover__hero-image"
                crossorigin="anonymous"
              />
              <figcaption class="gp-cover__hero-credit">
                <?php echo esc_html__('Powered by Groove Folios', 'groove-folios'); ?>
              </figcaption>
            </figure>
          <?php endif; ?>

          <?php if (!empty($this->copyright) || $cover_image_url === ''): ?>
            <footer class="gp-cover__footer">
              <?php if (!empty($this->copyright)): ?>
                <span><?php echo esc_html($this->copyright); ?></span>
              <?php endif; ?>
              <?php if ($cover_image_url === ''): ?>
                <span><?php echo esc_html__('Powered by Groove Folios', 'groove-folios'); ?></span>
              <?php endif; ?>
            </footer>
          <?php endif; ?>
        </div><!-- .gp-cover__content-area -->
      </div><!-- .gp-layout -->

    </div>
    <?php
  }
}
