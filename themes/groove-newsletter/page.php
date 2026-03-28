<?php
namespace Groove\Themes\Groove_Newsletter;

use Groove\Themes\Base_Theme;
use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
  exit;
}

class Page extends Base_Theme
{
  public $folio_id;
  public $folio;

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
      _n('%d min read', '%d min read', $minutes, 'groove'),
      $minutes
    );
  }

  // ─────────────────────────────────────────────────────────────────────────

  public function __construct()
  {
    parent::__construct();

    $this->folio_id = isset($_REQUEST['folio_id']) ? $_REQUEST['folio_id'] : '';

    if ($this->post_type == 'groove_folio_page') {
      $meta = get_post_meta($this->id);
      $this->folio_id = $meta['folio_id'][0] ?? '';
    }
  }

  public function ensure_script()
  {
    parent::ensure_script();

    $js_path = trailingslashit(GROOVE_PATH) . 'themes/' . static::get_id() . '/assets/js/groove-newsletter.js';
    $version = file_exists($js_path) ? filemtime($js_path) : GROOVE_VERSION;

    wp_enqueue_script(
      'groove-newsletter-theme',
      $this->get_theme_assets_url() . 'js/groove-newsletter.js',
      [],
      $version,
      true
    );
  }

  function get_folio_data()
  {
    $wp_query = $this->get_the_wp_query(array(
      'post__in' => array($this->folio_id),
      'post_status' => Utils::get_viewable_post_statuses(),
      'post_type' => 'groove_folio',
    ));

    $page = $wp_query->post;
    $this->folio = $page;

    return $page;
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

  function to_anchor_name($string)
  {
    $dstr = preg_replace_callback('/([A-Z]+)/', function ($matchs) {
      return '-' . strtolower($matchs[0]);
    }, $string);

    $dst = preg_replace_callback('/([\s]+)/', function ($matchs) {
      return '-';
    }, $dstr);

    return trim(preg_replace('/_{2,}/', '-', $dst), '-');
  }

  function get_html_id($element)
  {
    $id = $element->getAttribute('id');
    $textContent = $element->textContent;
    return $id ? $id : $this->to_anchor_name($textContent);
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
          echo '<div class="g-folio__theme-page-catalog"><a class="g-folio__theme-page-catalog-link" data-g-scroll-target="' . esc_attr($anchor ? ('#' . $anchor) : '#') . '" href="' . ($anchor ? ('#' . $anchor) : '') . '">' . esc_html($html[1]) . '</a></div>';
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
          $block['innerContent'][0] = '<' . $html[2] . ' id="' . $anchor . '">' . $html[1] . '</' . $html[2] . '>';
        }
      }

      $results .= render_block($block);
    }

    return $this->apply_embed_processing($results);
  }

  function is_first_page()
  {
    return empty($this->pages) ? false : ($this->pages[0]->ID === $this->id);
  }

  function is_last_page()
  {
    return empty($this->pages) ? false : ($this->pages[count($this->pages) - 1]->ID === $this->id);
  }

  function get_current_index()
  {
    $index = 0;
    foreach ($this->pages as $page) {
      if ($page->ID == $this->id) {
        return $index;
      }
      $index++;
    }
    return -1;
  }

  function get_prev_page()
  {
    $index = $this->get_current_index();
    if ($index > 0) {
      return $this->pages[$index - 1];
    }
    return null;
  }

  function get_next_page()
  {
    if (empty($this->pages)) {
      return null;
    }
    $index = $this->get_current_index();
    if ($index > -1 && $index < count($this->pages) - 1) {
      return $this->pages[$index + 1];
    }
    return null;
  }

  function display_navbar()
  {
    $prev_page = $this->get_prev_page();
    $next_page = $this->get_next_page();
    $page_nav_count = ($prev_page ? 1 : 0) + ($next_page ? 1 : 0);
    ?>
    <div class="g-folio__theme-page-nav-bar">
      <div class="g-folio__theme-page-nav-bar-main">
        <button type="button" class="g-folio__theme-page-nav-button gn-nav-trigger gn-nav-trigger--page"
          aria-label="<?= esc_attr__('Open issue navigation', 'groove') ?>">
          <svg class="g-folio__theme-menu-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
          <span class="gn-nav-trigger__label"><?= esc_html__('Contents', 'groove') ?></span>
        </button>

        <div class="gn-page__masthead-copy">
          <?php if ($page_nav_count > 0): ?>
            <nav class="gn-page__story-switcher<?= $page_nav_count === 1 ? ' gn-page__story-switcher--single' : '' ?>"
              aria-label="<?= esc_attr__('Page navigation', 'groove') ?>">
              <?php if ($prev_page): ?>
                <a class="gn-page__story-link gn-page__story-link--prev"
                  href="<?= esc_url(Utils::get_folio_permalink_by_id($prev_page->ID)) ?>">
                  <span class="gn-page__story-link-direction"><?= esc_html__('Previous page', 'groove') ?></span>
                  <span class="gn-page__story-link-title"><?= esc_html($prev_page->post_title) ?></span>
                </a>
              <?php endif; ?>

              <?php if ($next_page): ?>
                <a class="gn-page__story-link gn-page__story-link--next"
                  href="<?= esc_url(Utils::get_folio_permalink_by_id($next_page->ID)) ?>">
                  <span class="gn-page__story-link-direction"><?= esc_html__('Next page', 'groove') ?></span>
                  <span class="gn-page__story-link-title"><?= esc_html($next_page->post_title) ?></span>
                </a>
              <?php endif; ?>
            </nav>
          <?php endif; ?>
        </div>

        <button type="button" class="g-folio__theme-page-nav-bar-toggle">
          <?= esc_html__('Sections', 'groove') ?>
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
        <div class="g-folio__theme-page-mobile-nav-label"><?= esc_html($on_this_page_label) ?></div>
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
              <a class="g-folio__theme-page-mobile-nav-item-link" data-g-scroll-target="<?= '#' . $anchor ?>"
                href="<?= '#' . $anchor ?>">
                <div class="g-folio__theme-page-mobile-nav-item"><?= esc_html($html[1]) ?></div>
              </a>
              <?php
            }
          }
          ?>
        </div>
        <button type="button" class="g-folio__theme-page-mobile-nav-back" data-g-scroll-target="#gn-page-top">↑
          <?= esc_html__('Back to top', 'groove') ?></button>
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
        $folio = Utils::get_groove_post_by_post_type_and_post_name('groove_folio', $folio_slug);
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
          <a href="<?= Utils::get_folio_permalink_by_id($prev_page->ID) ?>" title="<?= esc_attr($prev_page->post_title) ?>">
            <?= esc_html($prev_page->post_title) ?></a>
          <?php
        }
        ?>
      </div>

      <div class="g-folio__theme-page-powerby">Powered by Groove Folios</div>

      <div class="g-folio__theme-page-next">
        <?php
        if ($next_page) {
          ?>
          <a href="<?= Utils::get_folio_permalink_by_id($next_page->ID) ?>" title="<?= esc_attr($next_page->post_title) ?>">
            <?= esc_html($next_page->post_title) ?></a>
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
    <aside class="gn-page__meta-rail" aria-label="<?= esc_attr__('Page tools', 'groove') ?>">
      <div class="gn-page__meta-group">
        <div class="gn-page__meta-label"><?= esc_html__('Read time', 'groove') ?></div>
        <div class="gn-page__meta-value"><?= esc_html($read_time_label) ?></div>
      </div>

      <?php if ($feature_thumb_url !== ''): ?>
        <a class="gn-page__feature-peek" data-g-scroll-target="#gn-page-feature" href="#gn-page-feature"
          aria-label="<?= esc_attr__('Jump to feature image', 'groove') ?>">
          <span class="gn-page__feature-peek-frame">
            <img class="gn-page__feature-peek-image" src="<?= esc_url($feature_thumb_url) ?>" alt="" loading="lazy" />
          </span>
        </a>
      <?php endif; ?>

      <button type="button" class="gn-page__back-to-top" data-g-scroll-target="#gn-page-top">
        <?= esc_html__('Back to top', 'groove') ?>
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
                  <p class="gn-page__dateline"><?= esc_html($page_date_label) ?></p>
                <?php endif; ?>
                <h1 class="g-folio__theme-page-title"><?= esc_html($this->title) ?></h1>
              </header>
              <article class="g-folio__theme-page-content">
                <?= $this->get_content() ?>
              </article>
              <?php $this->display_footer() ?>
            </div>
            <div class="g-folio__theme-page-sidebar">
              <div class="g-folio__theme-page-catalogs">
                <label class="g-folio__theme-page-catalogs-label"><?= esc_html($on_this_page_label) ?></label>
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
