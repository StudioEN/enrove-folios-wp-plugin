<?php
namespace Groove\Themes\Groove_Ebook;

use Groove\Themes\Base_Theme;
use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
  exit;
}

class Page extends Base_Theme
{

  // ─────────────────────────────────────────────────────────────────────────

  public function __construct()
  {
    parent::__construct();

    $this->folio_id = $this->resolve_page_folio_id();
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
          echo '<div class="g-folio__theme-page-catalog"><a href="' . esc_attr($anchor ? ('#' . $anchor) : '') . '">' . esc_html($html[1]) . '</a></div>';
        }
      }
    }

    echo '</div>';
  }

  function get_content()
  {
    $content = $this->content;
    $blocks = parse_blocks($content);

    // The thumbnail leads the body, and reaches the page through the same
    // wp_kses() as the blocks after it.
    $results = (string) $this->feature_image;

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
    ?>
    <div class="g-folio__theme-page-nav-bar">
      <div class="g-folio__theme-page-nav-bar-main">
        <button type="button" class="g-folio__theme-page-nav-button"
          aria-label="<?php echo esc_attr__('Open navigation', 'groove-folios'); ?>" aria-expanded="false">
          <svg class="g-folio__theme-menu-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>
        <div class="g-folio__theme-page-name"><span class="g-folio__theme-folio-name">
            <?php echo esc_html(isset($this->folio->post_title) ? $this->folio->post_title : ''); ?> |
          </span>
          <?php echo esc_html($this->page->post_title ?? 'Page'); ?>
        </div>
      </div>

      <button type="button" class="g-folio__theme-page-nav-bar-toggle"
        aria-label="<?php echo esc_attr__('Toggle contents', 'groove-folios'); ?>" aria-expanded="false"></button>

    </div>
    <?php $this->display_mobile_nav() ?>
    <?php
  }

  function display_mobile_nav()
  {
    $on_this_page_label = Utils::get_folio_on_this_page_label((int) $this->folio_id);
    $blocks = parse_blocks($this->content);
    ?>
    <nav class="g-folio__theme-page-mobile-nav" aria-label="<?php echo esc_attr__('On this page', 'groove-folios'); ?>"
      data-groove-drawer=".g-folio__theme-page-nav-bar-toggle" data-groove-drawer-lock="off">
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
              <a class="g-folio__theme-page-mobile-nav-item-link" href="<?php echo esc_attr('#' . $anchor); ?>">
                <div class="g-folio__theme-page-mobile-nav-item"><?php echo esc_html($html[1]); ?></div>
              </a>
              <?php
            }
          }
          ?>
        </div>
        <button type="button" class="g-folio__theme-page-mobile-nav-back"><?php esc_html_e('↑ Back to top', 'groove-folios'); ?></button>
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
    $folio_title = isset($folio->post_title) ? (string) $folio->post_title : '';
    $folio_url = '';
    if ($folio && Utils::is_folio_cover_enabled((int) $folio->ID)) {
      $folio_url = Utils::get_folio_permalink_by_id($folio->ID);
    }
    ?>
    <nav class="g-folio__theme-page-nav" aria-label="<?php echo esc_attr__('Folio contents', 'groove-folios'); ?>"
      data-groove-drawer=".g-folio__theme-page-nav-button">
      <?php // Outside .g-folio__theme-page-nav-content, which is the scrolling box. ?>
      <button type="button" class="g-folio__theme-page-nav-close"
        aria-label="<?php echo esc_attr__('Close navigation', 'groove-folios'); ?>"></button>
      <div class="g-folio__theme-page-nav-content">
        <h3 class="g-folio__theme-page-nav-name">
          <?php if (!empty($folio_url)): ?>
            <a href="<?php echo esc_url($folio_url); ?>">
              <?php echo esc_html($folio_title); ?>
            </a>
          <?php else: ?>
            <?php echo esc_html($folio_title); ?>
          <?php endif; ?>
        </h3>
        <label class="g-folio__theme-page-nav-label"><?php esc_html_e('Contents', 'groove-folios'); ?></label>
        <div class="g-folio__theme-page-navs">
          <?php
          $index = 1;
          foreach ($this->pages as $page) {
            ?>
            <a class="g-folio__theme-page-nav-item-link" href="<?php echo esc_url(Utils::get_folio_permalink_by_id($page->ID)); ?>">
              <div class="g-folio__theme-page-nav-item">
                <i class="g-folio__theme-page-nav-item-order"><?php echo (int) $index; ?></i>
                <?php echo esc_html($page->post_title); ?>
              </div>
            </a>
            <?php
            $index++;
          }
          ?>
        </div>
      </div>
    </nav>
    <?php
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
      // Credit, not rendered: "Powered by Groove Folios". WordPress.org
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

  function display_theme()
  {
    if (!parent::display_theme()) {
      return;
    }
    $on_this_page_label = Utils::get_folio_on_this_page_label((int) $this->folio_id);
    ?>
    <div class="g-folio__theme-2-page">
      <?php $this->display_navbar() ?>
      <?php $this->display_nav() ?>
      <main class="g-folio__theme-page-main">
        <div class="g-folio__theme-page-body">
          <div class="g-folio__theme-page-center">
            <div class="g-folio__theme-page-container">
              <h1 class="g-folio__theme-page-title"><?php echo esc_html($this->title); ?></h1>
              <div class="g-folio__theme-page-content">
                <?php echo wp_kses($this->get_content(), static::get_content_allowed_html()); ?>
              </div>
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
