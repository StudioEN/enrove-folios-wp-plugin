<?php
namespace Groove\Themes;
use Groove\Modules\Assets;

class Theme_Page_2 extends Assets {
  public $data;
  public function __construct($data) {
    $this->data = $data;
    
    add_action( 'wp_enqueue_scripts', [$this, 'ensure_script'] );
  }

  public function ensure_script () {
    wp_enqueue_style( 'groove', $this->get_css_assets_url( 'groove-main', null, 'default', true ), [], GROOVE_VERSION);	
    wp_enqueue_script( 'groove', $this->get_js_assets_url( 'groove-main' ), ['jquery'], GROOVE_VERSION, true);
  }

  function display_catalogs () {
    global $wp_embed;
    $data = $this->data;
    $page = $data->page;
    $content = $page->post_content;
   
    $blocks = parse_blocks($content);

    echo '<div class="g-folio__theme-page-catalogs-content">';
    
    foreach ($blocks as $block) {
      if ($block['blockName'] === 'core/heading') {
        $title = $block['innerContent'][0];
        echo '<a href="#'. 1 .'" class="g-folio__theme-page-catalog">' . $title . '</a>';
      }
    }

    echo '</div>';
  }

  function get_content () {
    global $wp_embed;

    $data = $this->data;
    $page = $data->page;
    $content = $page->post_content;
		$content = $wp_embed->autoembed( $content );
		$content = shortcode_unautop( $content );
		$content = do_blocks( $content );
		$content = wptexturize( $content );
		$content = convert_smilies( $content );
		$content = wp_filter_content_tags( $content, 'template' );
		$content = str_replace( ']]>', ']]&gt;', $content );

    return $content;
  }

  function display_theme () {
    

    $data = $this->data;
    $title = $data->title;
    $feature_image = $data->feature_image;
    $theme_name = $data->theme_name;
    $pages = $data->pages;
    $page = $data->page;

    $content = $this->get_content();

    $count = count($pages);
  ?>
    <div class="g-folio__theme-2-page">
      <main class="g-folio__theme-page-main">

        <div class="g-folio__theme-page-nav-bar">
          <button class="g-folio__theme-page-nav-button"></button>
          <div class="g-folio__theme-page-name"><?= $theme_name ?></div>
        </div>
        <nav class="g-folio__theme-page-nav">
          <div class="g-folio__theme-page-nav-content">
            <button class="g-folio__theme-page-nav-close"></button>
            <h3 class="g-folio__theme-page-nav-name"><?= $theme_name ?></h3>
            <label class="g-folio__theme-page-nav-label">CONTENTS</label>
            <div class="g-folio__theme-page-navs">
              <div class="g-folio__theme-page-nav-item">
              <i class="g-folio__theme-page-nav-item-order">1</i>Overview
              </div>
              <?
                $index = 2;
                foreach ($pages as $page) {
                  ?>
                    <div class="g-folio__theme-page-nav-item">
                      <i class="g-folio__theme-page-nav-item-order"><?= $index ?></i>
                      <?= $page->post_title ?>
                    </div>
                  <?
                  $index = $index + 1;
                }
              ?>
              <div class="g-folio__theme-page-nav-item">
                <i class="g-folio__theme-page-nav-item-order"><?= $count + 2 ?></i>Next steps
              </div>
            </div>
          </div>
        </nav>
        <div class="g-folio__theme-page-body">
          <div class="g-folio__theme-page-center">
            <div class="g-folio__theme-page-container">

              <h1 class="g-folio__theme-page-title"><?= $title ?></h1>
              <div class="g-folio__theme-page-content">
                <?= $content ?>
              </div>
              <nav class="g-folio__theme-page-footer">
                <div class="g-folio__theme-page-prev"></div>
                <div class="g-folio__theme-page-powerby">Powered by Groove Folios. Theme designed by <a class="g-folio__theme-site" href="/">StudioEN</a></div>
                <div class="g-folio__theme-page-next"></div>
              </nav>
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