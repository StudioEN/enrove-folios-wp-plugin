<?php
namespace Groove\Themes\Groove_Newsletter;

use Groove\Themes\Base_Theme;
use Groove\Utils\Utils;


if (!defined('ABSPATH')) {
  exit;
}

class Cover extends Base_Theme
{
  public $subtitle;

  function get_page_data()
  {
    $page = parent::get_page_data();
    // subtitle is post meta, not a WP_Post property. Guard against null $page.
    $this->subtitle = $page ? get_post_meta($page->ID, 'subtitle', true) : '';
  }

  function display_nav()
  {
    display_recent_navigation_pane($this, array(
      'class_prefix' => 'g-folio__theme-nav',
      'folio_id' => (int) $this->id,
      'title' => $this->theme_name,
      'label' => 'RECENT',
      'limit' => 10,
    ));
  }

  function display_password_form($post)
  {
    $post = get_post($post);
    ?>
    <form action="<?= esc_url(site_url('wp-login.php?action=postpass', 'login_post')) ?>" class="post-password-form"
      method="post">
      <input placeholder="Enter password" class="g-folio__theme-fields-submit-input" name="post_password" type="password"
        spellcheck="false" size="20" />
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
        <img src="<?= esc_url($this->theme_logo_url) ?>" />
      </i>
      <h1 class="g-folio__theme-title">
        <?= esc_html($this->title) ?>
      </h1>
      <h2 class="g-folio__theme-subtitle">
        <?= esc_html($this->subtitle) ?>
      </h2>
      <?php if (!empty($this->author)): ?>
        <p class="g-folio__theme-author">By
          <?= esc_html($this->author) ?>
        </p>
      <?php endif; ?>

      <div class="g-folio__theme-fields g-folio__theme-password-form">
        <?php
        $post_password_required = post_password_required($this->id);

        if ($post_password_required) {
          $this->display_password_form($this->id);
        } else {
          if (!empty($this->pages) && $page) {
            ?>
            <a class="g-folio__theme-fields-submit" href="<?= Utils::get_folio_permalink_by_id($page->ID) ?>">
              Open folio
            </a>
            <?php
          }
        }
        ?>
      </div>
      <?php if (!empty($this->copyright)): ?>
        <div class="g-folio__theme-copyright">
          <?= esc_html($this->copyright) ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
  }

  function display_theme()
  {
    if (!parent::display_theme()) {
      return;
    }

    ?>
    <div class="g-folio__theme-newsletter g-folio__theme-cover"
      style="background: url(<?= esc_url($this->theme_cover_url) ?>)">
      <button class="g-folio__theme-nav-button" aria-label="Open navigation">
        <svg class="g-folio__theme-menu-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <line x1="3" y1="6" x2="21" y2="6"></line>
          <line x1="3" y1="12" x2="21" y2="12"></line>
          <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
      </button>
      <?php $this->display_nav() ?>
      <div class="g-folio__theme-content">
        <?php $this->display_brief() ?>
      </div>
      <div class="g-folio__theme-powerby">Powered by Groove Folios. Theme designed by <a class="g-folio__theme-site"
          href="/"><?= esc_html(static::get_author()) ?></a></div>
    </div>
    <?php
  }
}
