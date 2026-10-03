<?php
namespace Enrove\Themes\Enrove_Newsletter;

use Enrove\Themes\Base_Theme;
use Enrove\Utils\Utils;

if (!defined('ABSPATH')) {
  exit;
}

class Page extends Base_Theme
{

  protected function get_page_timestamp(): int
  {
    if (!$this->page instanceof \WP_Post) {
      return 0;
    }

    return max(
      (int) get_post_time('U', true, $this->page),
      (int) get_post_modified_time('U', true, $this->page)
    );
  }

  protected function get_page_date_label(): string
  {
    $timestamp = $this->get_page_timestamp();
    if ($timestamp <= 0) {
      return '';
    }

    return (string) wp_date(get_option('date_format'), $timestamp);
  }

  protected function get_feature_image_thumb_url(): string
  {
    $thumb_url = get_the_post_thumbnail_url($this->id, 'medium_large');
    if ($thumb_url) {
      return (string) $thumb_url;
    }

    $fallback_url = get_the_post_thumbnail_url($this->id, 'thumbnail');
    return $fallback_url ? (string) $fallback_url : '';
  }

  protected function get_read_time_label(): string
  {
    $text = wp_strip_all_tags((string) $this->content, true);
    $word_count = str_word_count($text);
    $minutes = max(1, (int) ceil($word_count / 220));

    return sprintf(
      /* translators: %d: estimated reading time in minutes */
      _n('%d min read', '%d min read', $minutes, 'enrove-folios'),
      $minutes
    );
  }

  // ─────────────────────────────────────────────────────────────────────────

  public function __construct()
  {
    parent::__construct();

    $this->folio_id = $this->resolve_page_folio_id();
  }

  public function ensure_script()
  {
    parent::ensure_script();

    $js_path = trailingslashit(ENROVE_PATH) . 'themes/' . static::get_id() . '/assets/js/enrove-newsletter.js';
    $version = file_exists($js_path) ? filemtime($js_path) : ENROVE_VERSION;

    wp_enqueue_script(
      'enrove-newsletter-theme',
      $this->get_theme_assets_url() . 'js/enrove-newsletter.js',
      [],
      $version,
      true
    );
  }

  function get_data()
  {
    if ($this->is_preview_mode) { return; }
    $this->get_folio_data();
    $this->get_page_data();
    $this->get_pages_data($this->folio_id);
    $this->get_theme_data();
  }

  function get_html($html)
  {
    $doc = new \DOMDocument();
    @$doc->loadHTML($html);

    // "On this page" links are intended for section headings only.
    $element_names = ['h2'];

    foreach ($element_names as $element_name) {
      $element = $doc->getElementsByTagName($element_name)->item(0);
      if ($element) {
        return [
          $this->get_html_id($element),
          $element->textContent,
          $element_name
        ];
      }
    }

    return null;
  }

  function display_catalogs()
  {
    $blocks = parse_blocks($this->content);

    echo '<div class="g-folio__theme-page-catalogs-content">';

    foreach ($blocks as $block) {
      if ($block['blockName'] === 'core/heading') {
        $title = $block['innerContent'][0];

        $html = $this->get_html($title);
        if ($html) {
          $anchor = $this->to_anchor_name($html[1]);
          echo '<div class="g-folio__theme-page-catalog"><a class="g-folio__theme-page-catalog-link" data-g-scroll-target="' . esc_attr($anchor ? ('#' . $anchor) : '#') . '" href="' . esc_attr($anchor ? ('#' . $anchor) : '') . '">' . esc_html($html[1]) . '</a></div>';
        }
      }
    }

    echo '</div>';
  }

  function get_content()
  {
    $content = $this->content;
    $blocks = parse_blocks($content);
    $results = '';

    if (!empty($this->feature_image)) {
      $results .= '<figure class="gn-page__feature" id="gn-page-feature">' . $this->feature_image . '</figure>';
    }

    foreach ($blocks as $block) {
      if ($block['blockName'] === 'core/heading') {
        $title = $block['innerContent'][0];

        $html = $this->get_html($title);
        if ($html) {
          $anchor = $this->to_anchor_name($html[1]);
          $block['innerContent'][0] = '<' . $html[2] . ' id="' . esc_attr($anchor) . '">' . esc_html($html[1]) . '</' . $html[2] . '>';
        }
      }

      $results .= render_block($block);
    }

    return $this->apply_embed_processing($results);
  }

  function display_navbar()
  {
    $prev_page = $this->get_prev_page();
    $next_page = $this->get_next_page();
    $page_nav_count = ($prev_page ? 1 : 0) + ($next_page ? 1 : 0);
    ?>
    <?php /* This theme paints its own bar (gn-page--feature-out) and opens its own
             sections panel in enrove-newsletter.js, so it opts out of both shared
             handlers rather than being named in a filter inside enrove-main.js. */ ?>
    <div class="g-folio__theme-page-nav-bar" data-enrove-navbar="own">
      <div class="g-folio__theme-page-nav-bar-main">
        <button type="button" class="g-folio__theme-page-nav-button gn-nav-trigger gn-nav-trigger--page"
          aria-label="<?php echo esc_attr__('Open issue navigation', 'enrove-folios'); ?>">
          <svg class="g-folio__theme-menu-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
          <span class="gn-nav-trigger__label"><?php echo esc_html__('Contents', 'enrove-folios'); ?></span>
        </button>

        <div class="gn-page__masthead-copy">
          <?php if ($page_nav_count > 0): ?>
            <nav class="gn-page__story-switcher<?php echo $page_nav_count === 1 ? ' gn-page__story-switcher--single' : ''; ?>"
              aria-label="<?php echo esc_attr__('Page navigation', 'enrove-folios'); ?>">
              <?php if ($prev_page): ?>
                <a class="gn-page__story-link gn-page__story-link--prev"
                  href="<?php echo esc_url(Utils::get_folio_permalink_by_id($prev_page->ID)); ?>">
                  <span class="gn-page__story-link-direction"><?php echo esc_html__('Previous page', 'enrove-folios'); ?></span>
                  <span class="gn-page__story-link-title"><?php echo esc_html($prev_page->post_title); ?></span>
                </a>
              <?php endif; ?>

              <?php if ($next_page): ?>
                <a class="gn-page__story-link gn-page__story-link--next"
                  href="<?php echo esc_url(Utils::get_folio_permalink_by_id($next_page->ID)); ?>">
                  <span class="gn-page__story-link-direction"><?php echo esc_html__('Next page', 'enrove-folios'); ?></span>
                  <span class="gn-page__story-link-title"><?php echo esc_html($next_page->post_title); ?></span>
                </a>
              <?php endif; ?>
            </nav>
          <?php endif; ?>
        </div>

        <button type="button" class="g-folio__theme-page-nav-bar-toggle" data-enrove-nav-toggle="own">
          <?php echo esc_html__('Sections', 'enrove-folios'); ?>
        </button>
      </div>

      <?php $this->display_mobile_nav() ?>
    </div>
    <?php
  }

  function display_mobile_nav()
  {
    $on_this_page_label = Utils::get_folio_on_this_page_label((int) $this->folio_id);
    $blocks = parse_blocks($this->content);
    ?>
    <nav class="g-folio__theme-page-mobile-nav">
      <div class="g-folio__theme-page-mobile-nav-content">
        <div class="g-folio__theme-page-mobile-nav-label"><?php echo esc_html($on_this_page_label); ?></div>
        <div class="g-folio__theme-page-mobile-navs">
          <?php
          foreach ($blocks as $block) {
            if ($block['blockName'] === 'core/heading') {
              $title = $block['innerContent'][0];
              $html = $this->get_html($title);
              if (!$html) {
                continue;
              }
              $anchor = $this->to_anchor_name($html[1]);
              ?>
              <a class="g-folio__theme-page-mobile-nav-item-link" data-g-scroll-target="<?php echo esc_attr('#' . $anchor); ?>"
                href="<?php echo esc_attr('#' . $anchor); ?>">
                <div class="g-folio__theme-page-mobile-nav-item"><?php echo esc_html($html[1]); ?></div>
              </a>
              <?php
            }
          }
          ?>
        </div>
        <button type="button" class="g-folio__theme-page-mobile-nav-back" data-g-scroll-target="#gn-page-top">↑
          <?php echo esc_html__('Back to top', 'enrove-folios'); ?></button>
      </div>
    </nav>
    <?php
  }

  function display_nav()
  {
    $folio = $this->folio;
    if (!$folio) {
      $current_path = Utils::get_current_path();
      $base_slug = Utils::get_folio_base_slug();
      $pattern = '#^/' . preg_quote($base_slug, '#') . '/([^/]+)/page/#';
      if (preg_match($pattern, $current_path, $matches)) {
        $folio_slug = rtrim($matches[1], '/');
        $folio = Utils::get_enrove_post_by_post_type_and_post_name('enrove_folio', $folio_slug);
      }
    }
    $folio_id = isset($folio->ID) ? (int) $folio->ID : 0;
    $folio_title = isset($folio->post_title) ? (string) $folio->post_title : '';
    $folio_url = ($folio_id > 0 && Utils::is_folio_cover_enabled($folio_id))
      ? (string) Utils::get_folio_permalink_by_id($folio_id)
      : '';

    display_recent_navigation_pane($this, array(
      'class_prefix' => 'g-folio__theme-page-nav',
      'folio_id' => (int) $this->folio_id,
      'title' => $folio_title,
      'title_url' => $folio_url,
      'current_page_id' => (int) $this->id,
      'limit' => 10,
    ));
  }

  function display_footer()
  {
    $prev_page = $this->get_prev_page();
    $next_page = $this->get_next_page();
    ?>
    <nav class="g-folio__theme-page-footer">
      <div class="g-folio__theme-page-prev">
        <?php
        if ($prev_page) {
          ?>
          <i class="g-folio__theme-page-arrow"></i>
          <a href="<?php echo esc_url(Utils::get_folio_permalink_by_id($prev_page->ID)); ?>" title="<?php echo esc_attr($prev_page->post_title); ?>">
            <?php echo esc_html($prev_page->post_title); ?></a>
          <?php
        }
        ?>
      </div>

      <?php
      // Credit, not rendered: "Powered by Enrove Folios". WordPress.org
      // guideline 10 allows no public credit without the site admin opting in.
      // The empty cell stays: it is the middle column of the footer grid.
      ?>
      <div class="g-folio__theme-page-powerby" aria-hidden="true"></div>

      <div class="g-folio__theme-page-next">
        <?php
        if ($next_page) {
          ?>
          <a href="<?php echo esc_url(Utils::get_folio_permalink_by_id($next_page->ID)); ?>" title="<?php echo esc_attr($next_page->post_title); ?>">
            <?php echo esc_html($next_page->post_title); ?></a>
          <i class="g-folio__theme-page-arrow"></i>
          <?php
        }
        ?>
      </div>
    </nav>
    <?php
  }

  function display_editorial_rail()
  {
    $feature_thumb_url = $this->get_feature_image_thumb_url();
    $read_time_label = $this->get_read_time_label();
    ?>
    <aside class="gn-page__meta-rail" aria-label="<?php echo esc_attr__('Page tools', 'enrove-folios'); ?>">
      <div class="gn-page__meta-group">
        <div class="gn-page__meta-label"><?php echo esc_html__('Read time', 'enrove-folios'); ?></div>
        <div class="gn-page__meta-value"><?php echo esc_html($read_time_label); ?></div>
      </div>

      <?php if ($feature_thumb_url !== ''): ?>
        <a class="gn-page__feature-peek" data-g-scroll-target="#gn-page-feature" href="#gn-page-feature"
          aria-label="<?php echo esc_attr__('Jump to feature image', 'enrove-folios'); ?>">
          <span class="gn-page__feature-peek-frame">
            <img class="gn-page__feature-peek-image" src="<?php echo esc_url($feature_thumb_url); ?>" alt="" loading="lazy" />
          </span>
        </a>
      <?php endif; ?>

      <button type="button" class="gn-page__back-to-top" data-g-scroll-target="#gn-page-top">
        <?php echo esc_html__('Back to top', 'enrove-folios'); ?>
      </button>
    </aside>
    <?php
  }

  function display_theme()
  {
    if (!parent::display_theme()) {
      return;
    }

    $on_this_page_label = Utils::get_folio_on_this_page_label((int) $this->folio_id);
    $page_date_label = $this->get_page_date_label();

    ?>
    <div class="g-folio__theme-newsletter-page gn gn-page">
      <?php $this->display_nav() ?>
      <main class="g-folio__theme-page-main" id="gn-page-top">
        <div class="g-folio__theme-page-body">
          <div class="g-folio__theme-page-center">
            <div class="gn-page__left-rail">
              <?php $this->display_navbar() ?>
              <?php $this->display_editorial_rail() ?>
            </div>
            <div class="g-folio__theme-page-container">
              <header class="gn-page__header">
                <?php if ($page_date_label !== ''): ?>
                  <p class="gn-page__dateline"><?php echo esc_html($page_date_label); ?></p>
                <?php endif; ?>
                <h1 class="g-folio__theme-page-title"><?php echo esc_html($this->title); ?></h1>
              </header>
              <article class="g-folio__theme-page-content">
                <?php echo wp_kses($this->get_content(), static::get_content_allowed_html()); ?>
              </article>
              <?php $this->display_footer() ?>
            </div>
            <div class="g-folio__theme-page-sidebar">
              <div class="g-folio__theme-page-catalogs">
                <label class="g-folio__theme-page-catalogs-label"><?php echo esc_html($on_this_page_label); ?></label>
                <?php $this->display_catalogs(); ?>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
    <?php
  }
}
