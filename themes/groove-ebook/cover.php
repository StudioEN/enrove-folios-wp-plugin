<?php
namespace Groove\Themes\Groove_Ebook;

use Groove\Themes\Base_Theme;
use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
  exit;
}

class Cover extends Base_Theme
{
  public $subtitle;

  public static function get_name(): string
  {
    return 'Groove eBook';
  }

  protected static function get_thumbnail_filename(): string
  {
    return 'theme-thumb.png';
  }

  protected static function get_cover_filename(): string
  {
    return 'theme-cover.png';
  }

  protected static function get_logo_filename(): string
  {
    return 'theme-g-logo.png';
  }

  function get_page_data()
  {
    $page = parent::get_page_data();
    // subtitle is post meta, not a WP_Post property. Guard against null $page.
    $this->subtitle = $page ? get_post_meta($page->ID, 'subtitle', true) : '';
  }

  function display_nav()
  {
?>
<nav class="g-folio__theme-nav">
  <div class="g-folio__theme-nav-content">
    <button class="g-folio__theme-nav-close"></button>
    <h3 class="g-folio__theme-nav-name">
      <?= $this->theme_name?>
    </h3>
    <label class="g-folio__theme-nav-label">CONTENTS</label>
    <div class="g-folio__theme-navs">
      <?php
            $index = 1;
            foreach ($this->pages as $page) {
              ?>
                <a class="g-folio__theme-nav-item-link" href="<?= Utils::get_folio_permalink_by_id($page->ID)?>">
      <div class="g-folio__theme-nav-item">
        <i class="g-folio__theme-nav-item-order">
          <?= $index?>
        </i>
        <?= esc_html($page->post_title)?>
      </div>
      </a>
      <?php
              $index = $index + 1;
            }
          ?>
        </div>
      </div>
    </nav>
  <?php
  }

  function display_password_form($post)
  {
    $post = get_post($post);
  ?>
    <form action="<?= esc_url(site_url('wp-login.php?action=postpass', 'login_post'))?>" class="post-password-form"
      method="post">
      <input placeholder="Enter password" class="g-folio__theme-fields-submit-input" name="post_password"
        type="password" spellcheck="false" size="20" />
      <input class="g-folio__theme-fields-submit" type="submit" name="Submit" value="Enter" />
    </form>
    <?php
  }

  function display_brief()
  {
    $page = $this->pages[0] ?? null;
?>
  <div class="g-folio__theme-brief">
    <i class="g-folio__theme-logo">
      <img src="<?= esc_url($this->theme_logo_url)?>"/>
    </i>
    <h1 class="g-folio__theme-title">
      <?= esc_html($this->title)?>
    </h1>
    <h2 class="g-folio__theme-subtitle">
      <?= esc_html($this->subtitle)?>
    </h2>
    <p class="g-folio__theme-author">By
      <?= esc_html($this->author)?>
    </p>

    <div class="g-folio__theme-fields g-folio__theme-password-form">
      <?php
        $post_password_required = post_password_required($this->id);

        if ($post_password_required) {
          $this->display_password_form($this->id);
        } else {
          if (!empty($this->pages) && $page) {
            ?>
              <a class="g-folio__theme-fields-submit" href="<?= Utils::get_folio_permalink_by_id($page->ID)?>">
                Enter
              </a>
            <?php
          }
        }
      ?>
    </div>
    <div class="g-folio__theme-copyright">© 2023 StudioEN</div>
  </div>
<?php
  }

  function display_theme()
  {
    if (!parent::display_theme()) {
      return;
    }
?>
    <div class="g-folio__theme-2 g-folio__theme-cover" style="background: url(<?= esc_url($this->theme_cover_url)?>)">
      <button class="g-folio__theme-nav-button"></button>
      <?php $this->display_nav() ?>
      <div class="g-folio__theme-content">
        <?php $this->display_brief() ?>
      </div>
      <div class="g-folio__theme-powerby">Powered by Groove Folios. Theme designed by <a class="g-folio__theme-site" href="/">StudioEN</a></div>
    </div>
  <?php
  }
}
