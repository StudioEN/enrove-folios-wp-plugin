<?php
namespace Groove\Themes;
use Groove\Utils\Utils;


class Theme_1 extends Base_Theme {
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
                <a class="g-folio__theme-nav-item-link" href="<?= Utils::get_folio_permalink_by_id($page->ID)?>">
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

  function display_password_form ($post) {
    $post   = get_post( $post );
  ?>
    <form action="<?= esc_url( site_url( 'wp-login.php?action=postpass', 'login_post' ) )?>" class="post-password-form" method="post">
      <input placeholder="Enter password" class="g-folio__theme-fields-submit-input" name="post_password" type="password" spellcheck="false" size="20" />
      <input class="g-folio__theme-fields-submit" type="submit" name="Submit" value="Enter" />
    </form>
  <?
  }

  function display_brief () {
    $page = $this->pages[0];
  ?>
  <div class="g-folio__theme-brief">
    <div class="g-folio__theme-brief-inner">

      <div class="g-folio__theme-header">
        <div class="g-folio__theme-header-bg" style="background-image: url(<?= $this->theme_cover_url ?>)"></div>
        <div class="g-folio__theme-header-inner">
          <button class="g-folio__theme-nav-button"></button>
          <i class="g-folio__theme-logo">
            <img src="<?= $this->theme_logo_url ?>"/>
          </i>   
        </div>
      </div>
      <div class="g-folio__theme-brief-content">
          
        <h1 class="g-folio__theme-title"><?= $this->title ?></h1>
        <h2 class="g-folio__theme-subtitle"><?= $this->subtitle ?></h2>
        <p class="g-folio__theme-author">By <?= $this->author ?></p>
        
        <div class="g-folio__theme-fields g-folio__theme-password-form">
          <?
            $post_password_required = post_password_required( $this->id );

            if ($post_password_required) {
              $this->display_password_form($this->id);
            } else {
              if (sizeof($this->pages) > 0) {
                ?>
                  <a class="g-folio__theme-fields-submit" href="<? echo get_permalink($page->ID) ?>">
                    Enter
                  </a>
                <?
              }
            }
          ?>
        </div>
        <div class="g-folio__theme-copyright">© 2023 StudioEN</div>
        
      </div>
    </div>
  </div>
  <?
  }

  function display_theme () {
    parent::display_theme();
  ?>
    <div class="g-folio__theme-1 g-folio__theme-cover">

      <? $this->display_nav() ?>
      
      <div class="g-folio__theme-content">
        <? $this->display_brief() ?>
        <div class="g-folio__theme-background" style="background-image: url(<?= $this->theme_cover_url ?>)">
          <div class="g-folio__theme-powerby">Powered by Groove Folios. Theme designed by <a class="g-folio__theme-site" href="/">StudioEN</a></div>
        </div>
      </div>
      
      
    </div>
  <?php
  }
}

?>