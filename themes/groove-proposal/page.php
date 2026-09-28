<?php
namespace Groove\Themes\Groove_Proposal;

use Groove\Themes\Base_Theme;
use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
  exit;
}

class Page extends Base_Theme
{
  public $show_in_page_nav = true;

  public function __construct()
  {
    parent::__construct();

    $this->folio_id = $this->resolve_page_folio_id();
  }

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

    // In <head>, on a header handle of its own: everything above prints in
    // the footer, which is too late to stop the flash.
    wp_register_script('groove-proposal-scheme', false, [], $version, false);
    wp_enqueue_script('groove-proposal-scheme');
    wp_add_inline_script('groove-proposal-scheme', $this->theme_bootstrap_script());
  }

  public function get_data()
  {
    if ($this->is_preview_mode) { return; }
    $this->get_folio_data();
    $this->get_page_data();
    $this->get_pages_data((int) $this->folio_id);
    $this->get_theme_data();

    $show_nav_meta = (string) get_post_meta((int) $this->folio_id, 'proposal_show_in_page_nav', true);
    $this->show_in_page_nav = $show_nav_meta !== '0';
  }

  /**
   * The light/dark class, set on <html> before first paint so a reader on a
   * dark scheme never sees the light page flash first.
   */
  protected function theme_bootstrap_script(): string
  {
    return "(function () { try { var saved = localStorage.getItem('gp-theme'); var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches; var mode = saved === 'dark' || saved === 'light' ? saved : (prefersDark ? 'dark' : 'light'); document.documentElement.classList.remove('gp-theme-light', 'gp-theme-dark'); document.documentElement.classList.add(mode === 'dark' ? 'gp-theme-dark' : 'gp-theme-light'); } catch (error) { document.documentElement.classList.add('gp-theme-light'); } })();";
  }

  public function get_html($html)
  {
    $doc = new \DOMDocument();
    @$doc->loadHTML($html);

    $element_names = ['h2', 'h3'];

    foreach ($element_names as $element_name) {
      $element = $doc->getElementsByTagName($element_name)->item(0);
      if ($element) {
        return [
          $this->get_html_id($element),
          $element->textContent,
          $element_name,
        ];
      }
    }

    return null;
  }

  protected function get_heading_anchors(): array
  {
    $anchors = [];
    $blocks = parse_blocks((string) $this->content);

    foreach ($blocks as $block) {
      if (($block['blockName'] ?? '') !== 'core/heading') {
        continue;
      }

      $title = $block['innerContent'][0] ?? '';
      $html = $this->get_html((string) $title);
      if (!$html) {
        continue;
      }

      $anchor = $this->to_anchor_name((string) $html[1]);
      if ($anchor === '') {
        continue;
      }

      $anchors[] = [
        'anchor' => $anchor,
        'title' => (string) $html[1],
        'level' => (string) $html[2],
      ];
    }

    return $anchors;
  }

  protected function should_display_in_page_nav(array $anchors): bool
  {
    return $this->show_in_page_nav && count($anchors) >= 2;
  }

  protected function get_page_role(): array
  {
    $title = strtolower(trim((string) ($this->page->post_title ?? '')));
    if ($title === '') {
      return ['key' => '', 'label' => ''];
    }

    $role_map = [
      'context' => [
        'label' => __('Context', 'groove-folios'),
        'keywords' => ['context', 'introduction', 'summary', 'overview'],
      ],
      'problem-framing' => [
        'label' => __('Problem framing', 'groove-folios'),
        'keywords' => ['problem', 'challenge', 'opportunity', 'diagnosis'],
      ],
      'approach' => [
        'label' => __('Approach', 'groove-folios'),
        'keywords' => ['approach', 'methodology', 'method', 'scope', 'workstream', 'plan'],
      ],
      'evidence' => [
        'label' => __('Evidence', 'groove-folios'),
        'keywords' => ['evidence', 'case', 'result', 'impact', 'proof'],
      ],
      'investment' => [
        'label' => __('Investment', 'groove-folios'),
        'keywords' => ['investment', 'pricing', 'budget', 'fee', 'commercial'],
      ],
      'next-steps' => [
        'label' => __('Next steps', 'groove-folios'),
        'keywords' => ['next', 'timeline', 'kickoff', 'decision'],
      ],
    ];

    foreach ($role_map as $key => $definition) {
      foreach ($definition['keywords'] as $keyword) {
        if (strpos($title, (string) $keyword) !== false) {
          return [
            'key' => (string) $key,
            'label' => (string) $definition['label'],
          ];
        }
      }
    }

    return ['key' => '', 'label' => ''];
  }

  public function get_content()
  {
    $content = (string) $this->content;
    $blocks = parse_blocks($content);
    $results = '';

    if (!empty($this->feature_image)) {
      $results .= '<figure class="gp-page__feature" id="gp-page-feature">' . $this->feature_image . '</figure>';
    }

    foreach ($blocks as $block) {
      if (($block['blockName'] ?? '') === 'core/heading') {
        $title = $block['innerContent'][0] ?? '';
        $html = $this->get_html((string) $title);
        if ($html) {
          $anchor = $this->to_anchor_name((string) $html[1]);
          $block['innerContent'][0] = '<' . $html[2] . ' id="' . esc_attr($anchor) . '">' . esc_html($html[1]) . '</' . $html[2] . '>';
        }
      }

      $results .= render_block($block);
    }

    return $this->apply_embed_processing($results);
  }

  protected function get_progress_context(): array
  {
    $total = is_array($this->pages) ? count($this->pages) : 0;
    $current_index = $this->get_current_index();
    $current = $current_index >= 0 ? $current_index + 1 : 0;
    $percent = ($total > 0 && $current > 0) ? round(($current / $total) * 100) : 0;

    return [
      'current' => $current,
      'total' => $total,
      'percent' => $percent,
    ];
  }

  protected function get_proposal_color_scheme(int $folio_id): string
  {
    if ($folio_id <= 0) {
      return 'default';
    }

    return (string) get_post_meta($folio_id, 'proposal_color_scheme', true) === 'dynamic' ? 'dynamic' : 'default';
  }

  protected function get_folio_feature_image_url(int $folio_id): string
  {
    if ($folio_id <= 0 || !has_post_thumbnail($folio_id)) {
      return '';
    }

    return (string) get_the_post_thumbnail_url($folio_id, 'full');
  }

  protected function get_palette_source_url(int $folio_id): string
  {
    $folio_feature_image_url = $this->get_folio_feature_image_url($folio_id);
    if ($folio_feature_image_url !== '') {
      return $folio_feature_image_url;
    }

    return !empty($this->theme_cover_url) ? (string) $this->theme_cover_url : '';
  }

  protected function display_nav(): void
  {
    $folio = $this->folio;
    if (!$folio) {
      $current_path = Utils::get_current_path();
      $base_slug = Utils::get_folio_base_slug();
      $pattern = '#^/' . preg_quote($base_slug, '#') . '/([^/]+)/page/#';
      if (preg_match($pattern, $current_path, $matches)) {
        $folio_slug = rtrim($matches[1], '/');
        $folio = Utils::get_groove_post_by_post_type_and_post_name('groove_folio', $folio_slug);
      }
    }
    $folio_id = isset($folio->ID) ? (int) $folio->ID : 0;
    $folio_title = isset($folio->post_title) ? (string) $folio->post_title : '';
    $folio_url = ($folio_id > 0 && Utils::is_folio_cover_enabled($folio_id)) ? (string) Utils::get_folio_permalink_by_id($folio_id) : '';

    // Ensure pages are loaded — reload if empty and we have a valid folio
    $pages = is_array($this->pages) ? $this->pages : [];
    if (empty($pages) && $folio_id > 0) {
      $this->get_pages_data($folio_id);
      $pages = is_array($this->pages) ? $this->pages : [];
    }

    $nav_meta = $this->get_nav_info($folio_id);

    display_proposal_navigation_pane([
      'class_prefix'  => 'g-folio__theme-page-nav',
      'title'         => (string) $folio_title,
      'title_url'     => $folio_url,
      'label'         => __('Proposal', 'groove-folios'),
      'aria_label'    => __('Proposal navigation', 'groove-folios'),
      'pages'         => $pages,
      'current_page_id' => (int) $this->id,
      'info_version'  => $nav_meta['version'],
      'info_status'   => $nav_meta['status'],
      'info_date'     => $nav_meta['date'],
      'info_contacts' => $nav_meta['contacts'],
    ]);
  }

  protected function get_nav_info(int $folio_id): array
  {
    if ($folio_id <= 0) {
      return ['version' => '', 'status' => '', 'date' => '', 'contacts' => []];
    }

    $version = trim((string) get_post_meta($folio_id, 'proposal_version', true));
    $status  = trim((string) get_post_meta($folio_id, 'proposal_status', true));
    $date    = trim((string) get_post_meta($folio_id, 'proposal_date', true));

    if ($date !== '') {
      $timestamp = strtotime($date);
      $date = $timestamp !== false ? (string) wp_date('F j, Y', $timestamp) : $date;
    }

    $contacts = [];
    $raw_contacts = trim((string) get_post_meta($folio_id, 'proposal_contacts', true));

    if ($raw_contacts !== '') {
      $lines = preg_split('/\r\n|\r|\n/', $raw_contacts);
      if (is_array($lines)) {
        foreach ($lines as $line) {
          $line = trim((string) $line);
          if ($line === '') {
            continue;
          }

          $parts = array_map('trim', explode('|', $line));
          $name  = (string) ($parts[0] ?? '');
          $role  = (string) ($parts[1] ?? '');
          $email = sanitize_email((string) ($parts[2] ?? ''));
          $phone = (string) ($parts[3] ?? '');

          if ($name === '' && $role === '' && $email === '' && $phone === '') {
            continue;
          }

          $contacts[] = ['name' => $name, 'role' => $role, 'email' => $email, 'phone' => $phone];
        }
      }
    }

    if (empty($contacts)) {
      $name  = trim((string) get_post_meta($folio_id, 'proposal_contact_name', true));
      $role  = trim((string) get_post_meta($folio_id, 'proposal_contact_role', true));
      $email = sanitize_email((string) get_post_meta($folio_id, 'proposal_contact_email', true));
      $phone = trim((string) get_post_meta($folio_id, 'proposal_contact_phone', true));

      if ($name !== '' || $role !== '' || $email !== '' || $phone !== '') {
        $contacts[] = ['name' => $name, 'role' => $role, 'email' => $email, 'phone' => $phone];
      }
    }

    return ['version' => $version, 'status' => $status, 'date' => $date, 'contacts' => $contacts];
  }

  protected function display_mobile_header(array $progress, bool $show_in_page_nav, array $anchors): void
  {
    ?>
    <header class="gp-page__mobile-header">
      <div class="gp-page__mobile-header-main">
        <button type="button" class="g-folio__theme-page-nav-button gp-nav-trigger" aria-label="<?php echo esc_attr__('Open proposal navigation', 'groove-folios'); ?>">
          <svg class="g-folio__theme-menu-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
          <span><?php echo esc_html__('Contents', 'groove-folios'); ?></span>
        </button>

        <div class="gp-page__mobile-header-title"><?php echo esc_html($this->page->post_title ?? ''); ?></div>

        <div class="gp-page__mobile-header-controls">
          <span class="gp-page__progress-text"><?php /* translators: 1: current page number, 2: total number of pages */ echo esc_html(sprintf(__('%1$d / %2$d', 'groove-folios'), (int) $progress['current'], (int) $progress['total'])); ?></span>
          <?php if ($show_in_page_nav): ?>
            <button type="button" class="g-folio__theme-page-nav-bar-toggle" aria-expanded="false"><?php echo esc_html(Utils::get_folio_on_this_page_label((int) $this->folio_id)); ?></button>
          <?php endif; ?>
        </div>
      </div>
      <div class="gp-page__scroll-progress" aria-hidden="true">
        <span class="gp-page__scroll-progress-bar" style="width: <?php echo (int) $progress['percent']; ?>%"></span>
      </div>
      <?php $this->display_mobile_nav($anchors, $show_in_page_nav); ?>
    </header>
    <?php
  }

  protected function display_mobile_nav(array $anchors, bool $show_in_page_nav): void
  {
    if (!$show_in_page_nav) {
      return;
    }
    ?>
    <?php /* Rendered inside the sticky mobile header, and positioned absolutely
             against it, so it hangs from the bar its trigger sits in. In flow at
             the top of <main> it opened wherever the top of the page was — two
             screens above the reader, who saw nothing happen — and taking it out
             of flow is also what keeps opening and closing it from shifting the
             content underneath.

             No data-groove-nav-toggle="own": this theme ships a closer for this
             panel but no opener, so the shared toggle handler in groove-main.js
             is the one that opens it. data-groove-drawer adds the ARIA state,
             Escape and click-outside that a bare toggle cannot — lock="off"
             because this is a dropdown, not a full-screen drawer. The theme's
             own drawer controller is scoped to .g-folio__theme-page-nav and does
             not touch this pane. */ ?>
    <nav class="g-folio__theme-page-mobile-nav gp-page__mobile-nav" aria-label="<?php echo esc_attr__('On this page', 'groove-folios'); ?>"
      data-groove-drawer=".g-folio__theme-page-nav-bar-toggle" data-groove-drawer-lock="off">
      <div class="g-folio__theme-page-mobile-nav-content">
        <div class="g-folio__theme-page-mobile-nav-label"><?php echo esc_html(Utils::get_folio_on_this_page_label((int) $this->folio_id)); ?></div>
        <div class="g-folio__theme-page-mobile-navs">
          <?php foreach ($anchors as $anchor): ?>
            <a class="g-folio__theme-page-mobile-nav-item-link" data-g-scroll-target="#<?php echo esc_attr($anchor['anchor']); ?>" href="#<?php echo esc_attr($anchor['anchor']); ?>">
              <div class="g-folio__theme-page-mobile-nav-item"><?php echo esc_html($anchor['title']); ?></div>
            </a>
          <?php endforeach; ?>
        </div>
        <?php /* No data-g-scroll-target: groove-main.js already scrolls this
                 button to the top, and binding the theme's smooth scroll to the
                 same click ran both — an instant jump, then a smooth no-op. */ ?>
        <button type="button" class="g-folio__theme-page-mobile-nav-back">↑ <?php echo esc_html__('Back to top', 'groove-folios'); ?></button>
      </div>
    </nav>
    <?php
  }

  /**
   * The right rail: a decorative section ordinal, then the on-page contents.
   *
   * Both are rendered from the same container so the rail keeps one shape from
   * page to page — previously a page showed either the ordinal or the contents,
   * never both, so the column changed character as you moved through the folio.
   * The role label is deliberately not repeated here; it already sits above the
   * page title as the eyebrow.
   */
  protected function display_catalogs(array $anchors, bool $show_in_page_nav): void
  {
    $current_index = $this->get_current_index();
    $ordinal       = $current_index >= 0 ? str_pad((string) ($current_index + 1), 2, '0', STR_PAD_LEFT) : '';

    // No marker and nothing to list: drop the rail so the article takes the
    // full width (see .gp-page__center:not(:has(> .gp-page__sidebar))).
    if ($ordinal === '' && !$show_in_page_nav) {
      return;
    }
    ?>
    <div class="gp-page__sidebar">
      <?php if ($ordinal !== ''): ?>
        <div class="gp-page__locator" aria-hidden="true">
          <span class="gp-page__locator-ordinal"><?php echo esc_html($ordinal); ?></span>
        </div>
      <?php endif; ?>

      <?php if ($show_in_page_nav): ?>
        <nav class="gp-page__toc" aria-label="<?php echo esc_attr(Utils::get_folio_on_this_page_label((int) $this->folio_id)); ?>">
          <label class="g-folio__theme-page-catalogs-label"><?php echo esc_html(Utils::get_folio_on_this_page_label((int) $this->folio_id)); ?></label>
          <div class="g-folio__theme-page-catalogs-content">
            <?php foreach ($anchors as $anchor): ?>
              <div class="g-folio__theme-page-catalog">
                <a class="g-folio__theme-page-catalog-link" data-g-scroll-target="#<?php echo esc_attr($anchor['anchor']); ?>" href="#<?php echo esc_attr($anchor['anchor']); ?>">
                  <?php echo esc_html($anchor['title']); ?>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        </nav>
      <?php endif; ?>
    </div>
    <?php
  }

  protected function get_footer_meta(): array
  {
    $folio_id = (int) $this->folio_id;
    if ($folio_id <= 0) {
      return [];
    }

    $version = trim((string) get_post_meta($folio_id, 'proposal_version', true));
    $status  = trim((string) get_post_meta($folio_id, 'proposal_status', true));
    $email   = sanitize_email((string) get_post_meta($folio_id, 'proposal_contact_email', true));

    return [
      'version' => $version,
      'status'  => $status,
      'email'   => $email,
    ];
  }

  protected function display_footer(): void
  {
    $prev_page = $this->get_prev_page();
    $next_page = $this->get_next_page();
    ?>
    <footer class="g-folio__theme-page-footer gp-page__footer">
      <nav class="gp-page__footer-nav" aria-label="<?php echo esc_attr__('Page navigation', 'groove-folios'); ?>">
        <?php if ($prev_page): ?>
          <a class="gp-page__footer-arrow g-folio__theme-page-prev" href="<?php echo esc_url(Utils::get_folio_permalink_by_id((int) $prev_page->ID)); ?>" data-tooltip="<?php /* translators: %s: previous page title */ echo esc_attr(sprintf(__('Previous: %s', 'groove-folios'), $prev_page->post_title)); ?>" aria-label="<?php /* translators: %s: previous page title */ echo esc_attr(sprintf(__('Previous: %s', 'groove-folios'), $prev_page->post_title)); ?>">
            <span aria-hidden="true">&larr;</span>
          </a>
        <?php else: ?>
          <span class="gp-page__footer-arrow gp-page__footer-arrow--disabled" aria-hidden="true">&larr;</span>
        <?php endif; ?>

        <?php if ($next_page): ?>
          <a class="gp-page__footer-arrow g-folio__theme-page-next" href="<?php echo esc_url(Utils::get_folio_permalink_by_id((int) $next_page->ID)); ?>" data-tooltip="<?php /* translators: %s: next page title */ echo esc_attr(sprintf(__('Next: %s', 'groove-folios'), $next_page->post_title)); ?>" aria-label="<?php /* translators: %s: next page title */ echo esc_attr(sprintf(__('Next: %s', 'groove-folios'), $next_page->post_title)); ?>">
            <span aria-hidden="true">&rarr;</span>
          </a>
        <?php else: ?>
          <span class="gp-page__footer-arrow gp-page__footer-arrow--disabled" aria-hidden="true">&rarr;</span>
        <?php endif; ?>
      </nav>
    </footer>
    <?php
  }

  public function display_theme()
  {
    if (!parent::display_theme()) {
      return;
    }

    $progress = $this->get_progress_context();
    $anchors = $this->get_heading_anchors();
    $show_in_page_nav = $this->should_display_in_page_nav($anchors);
    $page_role = $this->get_page_role();
    $container_role_class = $page_role['key'] !== '' ? ' gp-page__container--role-' . sanitize_html_class($page_role['key']) : '';
    $folio_id = isset($this->folio->ID) ? (int) $this->folio->ID : (int) $this->folio_id;
    if ($folio_id <= 0) {
      $folio_id = $this->get_folio_id_for_customization();
    }
    $proposal_color_scheme = $this->get_proposal_color_scheme($folio_id);
    $palette_source_url = $this->get_palette_source_url($folio_id);
    ?>
    <div
      class="gp gp-page g-folio__theme-page"
      id="gp-page-top"
      data-gp-color-scheme="<?php echo esc_attr($proposal_color_scheme); ?>"
      data-gp-palette-source-url="<?php echo esc_url($palette_source_url); ?>"
    >

      <!-- Mobile-only header: nav trigger + title + progress + on-page toggle,
           and the on-page panel that hangs beneath it -->
      <?php $this->display_mobile_header($progress, $show_in_page_nav, $anchors); ?>

      <!-- Desktop + mobile layout: [nav sidebar | main content] -->
      <div class="gp-layout">
        <?php $this->display_nav(); ?>

        <main class="g-folio__theme-page-main gp-page__main">

          <div class="g-folio__theme-page-body gp-page__body">
            <div class="g-folio__theme-page-center gp-page__center">
              <article class="g-folio__theme-page-container gp-page__container<?php echo esc_attr($container_role_class); ?>">
                <?php if ($page_role['label'] !== ''): ?>
                  <p class="gp-page__role"><?php echo esc_html($page_role['label']); ?></p>
                <?php endif; ?>
                <h1 class="g-folio__theme-page-title gp-page__title"><?php echo esc_html($this->title); ?></h1>
                <div class="g-folio__theme-page-content gp-page__content">
                  <?php echo $this->get_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block-rendered post content, escaped by core; kses would strip embed iframes. ?>
                </div>
                <?php $this->display_footer(); ?>
              </article>
              <?php $this->display_catalogs($anchors, $show_in_page_nav); ?>
            </div>
          </div>
        </main>
      </div><!-- .gp-layout -->

    </div>
    <?php
  }
}
