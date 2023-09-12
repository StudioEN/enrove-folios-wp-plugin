<?php
namespace Groove\Themes;

class Theme_1 {
  public $data;
  public function __construct($data) {
    $this->data = $data;
  }
  function display_theme () {
    $data = $this->data;
    $title = $data->title;
    $subtitle = $data->subtitle;
    $cover = $data->cover;
    $id = $data->id;
  ?>
    <div class="g-foilo__theme g-foilo__theme-1">
      <nav class="g-foilo__theme-nav">
        <i class="g-foilo__theme-icon"></i>
        <i class="g-foilo__theme-logo"></i>    
      </nav>
      <div class="g-foilo__theme-brief">
        <h1 class="g-foilo__theme-title"><?= $title ?></h1>
        <h2 class="g-foilo__theme-subtitle"><?= $subtitle ?></h2>
      </div>
      <div class="g-foilo__theme-cover" style="background: url(<?= $cover ?>)">
        
      </div>
    </div>
  <?php
  }
}

?>