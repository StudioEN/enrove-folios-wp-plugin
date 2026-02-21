<?php
namespace Groove\Themes;

use Groove\Utils\Utils;

class Theme_Page_2 extends Base_Theme
{

  public $folio_id;
  public $folio;

  public function __construct()
  {
    parent::__construct();

    $this->folio_id = isset($_REQUEST['folio_id']) ? $_REQUEST['folio_id'] : '';

    if ($this->post_type == 'groove_folio_page') {
      $meta = get_post_meta($this->id);
      $this->folio_id = $meta['folio_id'][0];
    }
  }

  function get_folio_data()
  {
    $wp_query = $this->get_the_wp_query(array(
      'post__in' => array($this->folio_id),
      'post_status' => array('publish', 'draft', 'pending'),
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
    $doc->loadHTML($html);

    $element_names = ['h1', 'h2'];

    foreach ($element_names as $element_name) {
      $element = $doc->getElementsByTagName($element_name)[0];

      if ($element) {
        return [
          $this->get_html_id($element),
          $element->textContent,
          $element_name
        ];
      }
    }
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
    global $wp_embed;
    $blocks = parse_blocks($this->content);

    echo '<div class="g-folio__theme-page-catalogs-content">';

    foreach ($blocks as $block) {
      if ($block['blockName'] === 'core/heading') {
        $title = $block['innerContent'][0];

        $html = $this->get_html($title);
        $anchor = $this->to_anchor_name($html[1]);

        echo '<div class="g-folio__theme-page-catalog"><a href="' . ($anchor ? ('#' . $anchor) : '') . '">' . $html[1] . '</a></div>';
      }
    }

    echo '</div>';
  }


  function get_content()
  {
    global $wp_embed;

    $content = $this->content;
    $blocks = parse_blocks($content);

    echo $this->feature_image;
    $results = '';

    foreach ($blocks as $block) {
      if ($block['blockName'] === 'core/heading') {
        $title = $block['innerContent'][0];

        $html = $this->get_html($title);
        $anchor = $this->to_anchor_name($html[1]);

        if ($html) {
          $block['innerContent'][0] = '<' . $html[2] . ' id="' . $anchor . '">' . $html[1] . '</' . $html[2] . '>';
        }

      }

      $results .= render_block($block);
    }

    return $results;


    $content = $wp_embed->autoembed($content);
    $content = shortcode_unautop($content);
    $content = do_blocks($content);
    $content = wptexturize($content);
    $content = convert_smilies($content);
    $content = wp_filter_content_tags($content, 'template');
    $content = str_replace(']]>', ']]&gt;', $content);

    return $content;
  }

  function is_first_page()
  {
    return $this->pages[0]->ID === $this->id;
  }

  function is_last_page()
  {
    return $this->pages[0]->ID === $this->id;
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
    if ($index > -1) {
      return $this->pages[$this->get_current_index() - 1];
    }
    return null;
  }

  function get_next_page()
  {
    $index = $this->get_current_index();

    if ($index > -1) {
      return $this->pages[$this->get_current_index() + 1];
    }
    return null;
  }

  function display_navbar()
  {
?>
<div class="g-folio__theme-page-nav-bar">
  <div class="g-folio__theme-page-nav-bar-main">
    <button class="g-folio__theme-page-nav-button"></button>
    <div class="g-folio__theme-page-name"><span class="g-folio__theme-folio-name">
        <?= $this->folio->post_title?> |
      </span>
      <?= $this->page->post_title?>
    </div>
  </div>

  <div class="g-folio__theme-page-nav-bar-toggle">
  </div>

</div>
<?php $this->display_mobile_nav()?>
<?
  }

  function display_mobile_nav () {
      global $wp_embed;
      $blocks = parse_blocks($this->content);
    ?>
      <nav class="g-folio__theme-page-mobile-nav">
        <div class="g-folio__theme-page-mobile-nav-content">
          <div class="g-folio__theme-page-mobile-nav-label">JUMP TO...</div>
          <div class="g-folio__theme-page-mobile-navs">
            <?
              foreach ($blocks as $block) {
                if ($block['blockName'] === 'core/heading') {
                  $title = $block['innerContent'][0];
          
                  $html = $this->get_html($title);
                  $anchor = $this->to_anchor_name($html[1]);
                  ?>
                    <a class="g-folio__theme-page-mobile-nav-item-link" href="<?='#' . $anchor?>">
<div class="g-folio__theme-page-mobile-nav-item">
  <?= $html[1]?>
</div>
</a>
<?
                }
              }
            ?>
          </div>
          <div class="g-folio__theme-page-mobile-nav-back">↑ Back to top</div>
        </div>
      </nav>
    <?
  }

  function display_nav () {
  ?>
    <nav class="g-folio__theme-page-nav">
      <div class="g-folio__theme-page-nav-content">
        <button class="g-folio__theme-page-nav-close"></button>
        <h3 class="g-folio__theme-page-nav-name">
          <a href="<?= Utils::get_folio_permalink_by_id($this->folio->ID)?>">
<?= $this->folio->post_title?>
</a>
</h3>
<label class="g-folio__theme-page-nav-label">CONTENTS</label>
<div class="g-folio__theme-page-navs">
  <?
            $index = 1;
            foreach ($this->pages as $page) {
              ?>
                <a class="g-folio__theme-page-nav-item-link" href="<?= Utils::get_folio_permalink_by_id($page->ID)?>">
  <div class="g-folio__theme-page-nav-item">
    <i class="g-folio__theme-page-nav-item-order">
      <?= $index?>
    </i>
    <?= $page->post_title?>
  </div>
  </a>
  <?
              $index = $index + 1;
            }
          ?>
        </div>
      </div>
    </nav>
  <?
  }

  function display_footer () {
    $prev_page = $this->get_prev_page();
    $next_page = $this->get_next_page();
  ?>
    <nav class="g-folio__theme-page-footer">
      <div class="g-folio__theme-page-prev">
        <?
          if ($prev_page) {
          ?>
            <i class="g-folio__theme-page-arrow"></i>
            <a href="<?= Utils::get_folio_permalink_by_id($prev_page->ID)?>">
  <?= $prev_page->post_title?></a>
  <?
          }
        ?>
      </div>
      
      <div class="g-folio__theme-page-powerby">Powered by Groove Folios</div>

      <div class="g-folio__theme-page-next">
        <?
          if ($next_page) {
          ?>
            <a href="<?= Utils::get_folio_permalink_by_id($next_page->ID)?>">
  <?= $next_page->post_title?></a>
  <i class="g-folio__theme-page-arrow"></i>
  <?
          }
        ?>
      </div>
    </nav>
  <?
  }

  function display_theme () {
    parent::display_theme();
  ?>
    <div class="g-folio__theme-2-page">
      <? $this->display_navbar() ?>
      <? $this->display_nav() ?>
      <main class="g-folio__theme-page-main">
        <div class="g-folio__theme-page-body">
          <div class="g-folio__theme-page-center">
            <div class="g-folio__theme-page-container">

              <h1 class="g-folio__theme-page-title"><?= $this->title?>
  </h1>
  <div class="g-folio__theme-page-content">
    <?= $this->get_content()?>
  </div>
  <? $this->display_footer() ?>
            </div>
            <div class="g-folio__theme-page-sidebar">
              <div class="g-folio__theme-page-catalogs">
                <label class="g-folio__theme-page-catalogs-label">SECTION</label>
                <? $this->display_catalogs(); ?>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  <?php
  }
}

?>