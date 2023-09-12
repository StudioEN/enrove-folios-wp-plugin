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
  }

  function display_theme () {
    

    $data = $this->data;
    $title = $data->title;
    $subtitle = $data->subtitle;
    $cover = $data->cover;
    $id = $data->id;
    $logo = $data->logo;
    $author = $data->author;
  ?>
    <div class="g-folio__theme-2 g-folio__theme-cover" style="background: url(<?= $cover ?>)">
      <nav class="g-folio__theme-nav">
        <i class="g-folio__theme-icon"></i>
         
      </nav>
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

      <div class="g-folio__theme-powerby">Powered by Groove Folios. Theme designed by StudioEN</div>
    </div>
  <?php
  }
}

?>