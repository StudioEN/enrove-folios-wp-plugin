<?php
namespace Groove\Themes;


class Theme_2 extends Base_Theme {
  public $subtittle;

  function get_page_data () {
    $page = parent::get_page_data();

    $this->subtitle = $page->subtitle;
  }

  function display_nav () {
  ?>
    <nav class="g-folio__theme-nav">
      <div class="g-folio__theme-nav-content">
        <button class="g-folio__theme-nav-close"></button>
        <h3 class="g-folio__theme-nav-name"><?= $this->theme_name ?></h3>
        <label class="g-folio__theme-nav-label">CONTENTS</label>
        <div class="g-folio__theme-navs">
          <?
            $index = 1;
            foreach ($this->pages as $page) {
              ?>
                <a class="g-folio__theme-nav-item-link" href="/?folio_id=<?= $this->id ?>&post_type=groove_folio_page&preview=true&p=<?= $page->ID ?>">
                  <div class="g-folio__theme-nav-item">
                    <i class="g-folio__theme-nav-item-order"><?= $index ?></i>
                    <?= $page->post_title ?>
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

  function display_brief () {
    $page = $this->pages[0];
  ?>
  <div class="g-folio__theme-brief">
    <i class="g-folio__theme-logo">
      <img src="<?= $this->theme_logo_url ?>"/>
    </i>   
    <h1 class="g-folio__theme-title"><?= $this->title ?></h1>
    <h2 class="g-folio__theme-subtitle"><?= $this->subtitle ?></h2>
    <p class="g-folio__theme-author">By <?= $this->author ?></p>

    <div class="g-folio__theme-fields">
      <?
        if (sizeof($this->pages) > 0) {
          ?>
             <a class="g-folio__theme-fields-submit" href="<? echo get_permalink($page->ID) ?>">
              Enter
            </a>
          <?
        }
      ?>
    </div>
    <div class="g-folio__theme-copyright">© 2023 StudioEN</div>
  </div>
  <?
  }

  function display_theme () {
    parent::display_theme();
  ?>
    <div class="g-folio__theme-2 g-folio__theme-cover" style="background: url(<?= $this->theme_cover_url ?>)">
      <button class="g-folio__theme-nav-button"></button>
      <? $this->display_nav() ?>
      <div class="g-folio__theme-content">
        <? $this->display_brief() ?>
      </div>
      <div class="g-folio__theme-powerby">Powered by Groove Folios. Theme designed by <a class="g-folio__theme-site" href="/">StudioEN</a></div>
    </div>
  <?php
  }
}

?>