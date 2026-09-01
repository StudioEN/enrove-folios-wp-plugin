<?php
namespace Groove\Themes\Folio_Starter;

use Groove\Themes\Base_Theme;
use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
  exit;
}

class Page extends Base_Theme
{
  public $folio_id;
  public $folio;
  public $catalog_entries;

  public function ensure_script()
  {
    parent::ensure_script();

    $js_path = $this->get_theme_assets_path() . 'js/folio-starter.js';
    $version = file_exists($js_path) ? filemtime($js_path) : GROOVE_VERSION;

    wp_enqueue_script(
      'folio-starter-theme',
      $this->get_theme_assets_url() . 'js/folio-starter.js',
      [],
      $version,
      true
    );
  }

  // ─────────────────────────────────────────────────────────────────────────

  public function __construct()
  {
    parent::__construct();

    $this->folio_id = $this->resolve_page_folio_id();
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

  function load_fragment($html)
  {
    $doc = new \DOMDocument();
    $previous = libxml_use_internal_errors(true);
    // The XML declaration is the charset hint; without it libxml assumes
    // ISO-8859-1 and "Café" round-trips as "CafÃ©".
    $doc->loadHTML(
      '<?xml encoding="utf-8" ?>' . $html,
      LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    foreach (iterator_to_array($doc->childNodes) as $node) {
      if ($node->nodeType === XML_PI_NODE) {
        $doc->removeChild($node);
      }
    }

    return $doc;
  }

  function get_html($html)
  {
    $doc = $this->load_fragment($html);

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

  function inject_heading_anchor($html, $anchor)
  {
    $doc = $this->load_fragment($html);

    $element = $doc->getElementsByTagName('h2')->item(0);
    if (!$element) {
      return $html;
    }

    if (!$element->getAttribute('id')) {
      $element->setAttribute('id', $anchor);
    }

    return $doc->saveHTML();
  }

  function get_html_id($element)
  {
    $id = $element->getAttribute('id');
    $textContent = $element->textContent;
    return $id ? $id : $this->to_anchor_name($textContent);
  }

  /**
   * The section headings this page contributes to "On this page".
   * Empty when the page has no h2s — the rail and its toggle are hidden then.
   */
  function get_catalog_entries()
  {
    if (isset($this->catalog_entries)) {
      return $this->catalog_entries;
    }

    $entries = array();

    foreach (parse_blocks((string) $this->content) as $block) {
      if ($block['blockName'] !== 'core/heading') {
        continue;
      }

      $html = $this->get_html($block['innerContent'][0]);
      if (!$html || $html[0] === '') {
        continue;
      }

      $entries[] = $html;
    }

    $this->catalog_entries = $entries;

    return $entries;
  }

  function display_catalogs()
  {
    echo '<div class="g-folio__theme-page-catalogs-content">';

    foreach ($this->get_catalog_entries() as $entry) {
      echo '<div class="g-folio__theme-page-catalog"><a href="' . esc_attr('#' . $entry[0]) . '">' . esc_html($entry[1]) . '</a></div>';
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
        $html = $this->get_html($block['innerContent'][0]);
        if ($html) {
          // $html[0] is the author's own id when the heading has one.
          $block['innerContent'][0] = $this->inject_heading_anchor($block['innerContent'][0], $html[0]);
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
    ?>
    <div class="g-folio__theme-page-nav-bar">
      <div class="g-folio__theme-page-nav-bar-main">
        <button type="button" class="g-folio__theme-page-nav-button" aria-label="<?= esc_attr__('Open navigation', 'groove') ?>"
          aria-expanded="false">
          <svg class="g-folio__theme-menu-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>
        <div class="g-folio__theme-page-name"><span class="g-folio__theme-folio-name">
            <?= esc_html(isset($this->folio->post_title) ? $this->folio->post_title : '') ?> |
          </span>
          <?= esc_html($this->page->post_title ?? __('Page', 'groove')) ?>
        </div>
      </div>


    </div>
    <?php
  }

  /**
   * Small-screen "On this page". Sits with the content rather than in the
   * navbar, so it does not compete with the folio nav. Native <details>, so
   * it needs no JS and is keyboard-operable for free.
   */
  function display_contents()
  {
    $entries = $this->get_catalog_entries();
    if (!$entries) {
      return;
    }

    $on_this_page_label = Utils::get_folio_on_this_page_label((int) $this->folio_id);
    ?>
    <details class="g-folio__theme-page-contents">
      <summary class="g-folio__theme-page-contents-summary">
        <span class="g-folio__theme-page-contents-label"><?= esc_html($on_this_page_label) ?></span>
        <svg class="g-folio__theme-page-contents-chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
      </summary>
      <div class="g-folio__theme-page-contents-list">
        <?php foreach ($entries as $entry): ?>
          <a class="g-folio__theme-page-contents-link" href="<?= esc_attr('#' . $entry[0]) ?>">
            <?= esc_html($entry[1]) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </details>
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
    <nav class="g-folio__theme-page-nav" aria-label="<?= esc_attr__('Folio contents', 'groove') ?>">
      <div class="g-folio__theme-page-nav-content">
        <button type="button" class="g-folio__theme-page-nav-close" aria-label="<?= esc_attr__('Close navigation', 'groove') ?>">
          <svg class="g-folio__theme-close-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <line x1="6" y1="6" x2="18" y2="18"></line>
            <line x1="18" y1="6" x2="6" y2="18"></line>
          </svg>
        </button>
        <h3 class="g-folio__theme-page-nav-name">
          <?php if (!empty($folio_url)): ?>
            <a href="<?= esc_url($folio_url) ?>">
              <?= esc_html($folio_title) ?>
            </a>
          <?php else: ?>
            <?= esc_html($folio_title) ?>
          <?php endif; ?>
        </h3>
        <p class="g-folio__theme-page-nav-label"><?= esc_html__('Contents', 'groove') ?></p>
        <div class="g-folio__theme-page-navs">
          <?php
          $index = 1;
          foreach ($this->pages as $page) {
            ?>
            <a class="g-folio__theme-page-nav-item-link" href="<?= esc_url(Utils::get_folio_permalink_by_id($page->ID)) ?>">
              <div class="g-folio__theme-page-nav-item">
                <i class="g-folio__theme-page-nav-item-order"><?= $index ?></i>
                <?= esc_html($page->post_title) ?>
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
          <svg class="g-folio__theme-page-arrow" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <polyline points="9 6 15 12 9 18"></polyline>
          </svg>
          <a href="<?= esc_url(Utils::get_folio_permalink_by_id($prev_page->ID)) ?>">
            <?= esc_html($prev_page->post_title) ?></a>
          <?php
        }
        ?>
      </div>

      <div class="g-folio__theme-page-powerby"><?= esc_html__('Powered by Groove Folios', 'groove') ?></div>

      <div class="g-folio__theme-page-next">
        <?php
        if ($next_page) {
          ?>
          <a href="<?= esc_url(Utils::get_folio_permalink_by_id($next_page->ID)) ?>">
            <?= esc_html($next_page->post_title) ?></a>
          <svg class="g-folio__theme-page-arrow" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <polyline points="9 6 15 12 9 18"></polyline>
          </svg>
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
    <div class="g-folio__theme-1-page">
      <?php $this->display_navbar() ?>
      <?php $this->display_nav() ?>
      <main class="g-folio__theme-page-main">
        <div class="g-folio__theme-page-body">
          <div class="g-folio__theme-page-center">
            <div class="g-folio__theme-page-container">
              <h1 class="g-folio__theme-page-title"><?= esc_html($this->title) ?></h1>
              <?php $this->display_contents() ?>
              <div class="g-folio__theme-page-content">
                <?= $this->get_content() ?>
              </div>
              <?php $this->display_footer() ?>
            </div>
            <?php if ($this->get_catalog_entries()): ?>
              <div class="g-folio__theme-page-sidebar">
                <div class="g-folio__theme-page-catalogs">
                  <p class="g-folio__theme-page-catalogs-label"><?= esc_html($on_this_page_label) ?></p>
                  <?php $this->display_catalogs(); ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </main>
    </div>
    <?php
  }
}
