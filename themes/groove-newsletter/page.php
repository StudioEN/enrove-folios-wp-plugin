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
          echo '<div class="g-folio__theme-page-catalog"><a href="' . ($anchor ? ('#' . $anchor) : '') . '">' . esc_html($html[1]) . '</a></div>';
        }
      }
    }

    echo '</div>';
  }

  function get_content()
  {
    $content = $this->content;
    $blocks = parse_blocks($content);

    echo $this->feature_image;
    $results = '';

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

    return $results;
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
    ?>
    <div class="g-folio__theme-page-nav-bar">
      <div class="g-folio__theme-page-nav-bar-main">
        <button class="g-folio__theme-page-nav-button" aria-label="Open navigation"></button>
        <div class="g-folio__theme-page-name"><span class="g-folio__theme-folio-name">
            <?= esc_html($this->folio->post_title ?? 'Folio') ?> |
          </span>
          <?= esc_html($this->page->post_title ?? 'Page') ?>
        </div>
      </div>

      <div class="g-folio__theme-page-nav-bar-toggle">
      </div>

    </div>
    <?php $this->display_mobile_nav() ?>
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
              <a class="g-folio__theme-page-mobile-nav-item-link" href="<?= '#' . $anchor ?>">
                <div class="g-folio__theme-page-mobile-nav-item"><?= esc_html($html[1]) ?></div>
              </a>
              <?php
            }
          }
          ?>
        </div>
        <div class="g-folio__theme-page-mobile-nav-back">↑ Back to top</div>
      </div>
    </nav>
    <?php
  }

  function display_nav()
  {
    $fallback_folio_id = (int) $this->folio_id;
    $folio_id = isset($this->folio->ID) ? (int) $this->folio->ID : $fallback_folio_id;
    $folio_title = $this->folio->post_title ?? 'Folio';
    $can_link_to_cover = Utils::is_folio_cover_enabled($folio_id);
    $folio_url = ($folio_id > 0 && $can_link_to_cover) ? Utils::get_folio_permalink_by_id($folio_id) : '';

    display_recent_navigation_pane($this, array(
      'class_prefix' => 'g-folio__theme-page-nav',
      'folio_id' => $folio_id,
      'title' => $folio_title,
      'title_url' => $folio_url,
      'label' => 'RECENT',
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
          <a href="<?= Utils::get_folio_permalink_by_id($prev_page->ID) ?>">
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
          <a href="<?= Utils::get_folio_permalink_by_id($next_page->ID) ?>">
            <?= esc_html($next_page->post_title) ?></a>
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
    <div class="g-folio__theme-newsletter-page">
      <?php $this->display_navbar() ?>
      <?php $this->display_nav() ?>
      <main class="g-folio__theme-page-main">
        <div class="g-folio__theme-page-body">
          <div class="g-folio__theme-page-center">
            <div class="g-folio__theme-page-container">
              <h1 class="g-folio__theme-page-title"><?= esc_html($this->title) ?></h1>
              <div class="g-folio__theme-page-content">
                <?= $this->get_content() ?>
              </div>
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
