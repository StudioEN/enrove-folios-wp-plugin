<?php
namespace Groove\Themes;
use Groove\Modules\Assets;

class Theme_2 extends Assets {
  public $data;
  public function __construct($data) {
    $this->data = $data;
    
    add_action( 'wp_enqueue_scripts', [$this, 'ensure_script'] );
  }

  public function ensure_script () {
    wp_enqueue_style( 'groove', $this->get_css_assets_url( 'groove-main', null, 'default', true ), [], GROOVE_VERSION);	
    wp_enqueue_script( 'groove', $this->get_js_assets_url( 'groove-main' ), ['jquery'], GROOVE_VERSION, true);
  }

  function display_theme () {
    

    $data = $this->data;
    $title = $data->title;
    $subtitle = $data->subtitle;
    $cover = $data->cover;
    $id = $data->id;
    $logo = $data->logo;
    $author = $data->author;
    $theme_name = $data->theme_name;
    $pages = $data->pages;

    $count = count($pages);
  ?>
    <div class="g-folio__theme-2 g-folio__theme-cover" style="background: url(<?= $cover ?>)">
      <button class="g-folio__theme-nav-button"></button>
      <nav class="g-folio__theme-nav">
        <div class="g-folio__theme-nav-content">
          <button class="g-folio__theme-nav-close"></button>
          <h3 class="g-folio__theme-nav-name"><?= $theme_name ?></h3>
          <label class="g-folio__theme-nav-label">CONTENTS</label>
          <div class="g-folio__theme-navs">
            <div class="g-folio__theme-nav-item">
            <i class="g-folio__theme-nav-item-order">1</i>Overview
            </div>
            <?
              $index = 2;
              foreach ($pages as $page) {
                ?>
                  <div class="g-folio__theme-nav-item">
                    <i class="g-folio__theme-nav-item-order"><?= $index ?></i>
                    <?= $page->post_title ?>
                  </div>
                <?
                $index = $index + 1;
              }
            ?>
            <div class="g-folio__theme-nav-item">
              <i class="g-folio__theme-nav-item-order"><?= $count + 2 ?></i>Next steps
            </div>
          </div>
        </div>
      </nav>
      <div class="g-folio__theme-content">
        <div class="g-folio__theme-brief">
          <i class="g-folio__theme-logo">
            <img src="<?= $logo ?>"/>
          </i>   
          <h1 class="g-folio__theme-title"><?= $title ?></h1>
          <h2 class="g-folio__theme-subtitle"><?= $subtitle ?></h2>
          <p class="g-folio__theme-author">By <?= $author ?></p>

          <div class="g-folio__theme-fields"></div>
          <div class="g-folio__theme-copyright">© 2023 StudioEN</div>
        </div>
      </div>

      <div class="g-folio__theme-powerby">Powered by Groove Folios. Theme designed by <a class="g-folio__theme-site" href="/">StudioEN</a></div>
    </div>
  <?php
  }
}

?>