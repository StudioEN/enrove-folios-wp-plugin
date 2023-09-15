<?php
namespace Groove\Themes;

class Theme_1 extends Base_Theme {

  function display_nav () {
  ?>
    <nav class="g-foilo__theme-nav">
      <i class="g-foilo__theme-icon"></i>
      <i class="g-foilo__theme-logo"></i>    
    </nav>
  <?
  }

  function display_theme () {
    parent::display_theme();
  ?>
    <div class="g-foilo__theme g-foilo__theme-1">
      <? $this->display_nav() ?>
      <? $this->display_brief() ?>
      <div class="g-foilo__theme-brief">
        <h1 class="g-foilo__theme-title"><?= $this->title ?></h1>
        <h2 class="g-foilo__theme-subtitle"><?= $this->subtitle ?></h2>
      </div>
      <div class="g-foilo__theme-cover" style="background: url(<?= $this->theme_cover_urlcover ?>)">

      </div>
    </div>
  <?php
  }
}

?>