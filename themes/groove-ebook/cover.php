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

  function get_page_data()
  {
    $page = parent::get_page_data();
    // subtitle is post meta, not a WP_Post property. Guard against null $page.
    $this->subtitle = $page ? get_post_meta($page->ID, 'subtitle', true) : '';
  }

  function display_nav()
  {
    ?>
    <nav class="g-folio__theme-nav" aria-label="<?php echo esc_attr__('Folio contents', 'groove-folios'); ?>"
      data-groove-drawer=".g-folio__theme-nav-button">
      <?php // Outside .g-folio__theme-nav-content, which is the scrolling box: a
        // long contents list used to carry the close button off the top of the pane. ?>
      <button type="button" class="g-folio__theme-nav-close"
        aria-label="<?php echo esc_attr__('Close navigation', 'groove-folios'); ?>"></button>
      <div class="g-folio__theme-nav-content">
        <h3 class="g-folio__theme-nav-name">
          <?php echo esc_html($this->theme_name); ?>
        </h3>
        <label class="g-folio__theme-nav-label"><?php esc_html_e('Contents', 'groove-folios'); ?></label>
        <div class="g-folio__theme-navs">
          <?php
          $index = 1;
          foreach ($this->pages as $page) {
            ?>
            <a class="g-folio__theme-nav-item-link" href="<?php echo esc_url(Utils::get_folio_permalink_by_id($page->ID)); ?>">
              <div class="g-folio__theme-nav-item">
                <i class="g-folio__theme-nav-item-order">
                  <?php echo (int) $index; ?>
                </i>
                <?php echo esc_html($page->post_title); ?>
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

  function display_brief()
  {
    $page = $this->pages[0] ?? null;
    ?>
    <div class="g-folio__theme-brief">
      <?php if (!empty($this->theme_logo_url)): ?>
        <i class="g-folio__theme-logo">
          <img src="<?php echo esc_url($this->theme_logo_url); ?>" />
        </i>
      <?php endif; ?>
      <h1 class="g-folio__theme-title">
        <?php echo esc_html($this->title); ?>
      </h1>
      <h2 class="g-folio__theme-subtitle">
        <?php echo esc_html($this->subtitle); ?>
      </h2>
      <?php if (!empty($this->author)): ?>
        <p class="g-folio__theme-author">By
          <?php echo esc_html($this->author); ?>
        </p>
      <?php endif; ?>

      <div class="g-folio__theme-fields g-folio__theme-password-form">
        <?php if (!empty($this->pages) && $page): ?>
          <a class="g-folio__theme-fields-submit" href="<?php echo esc_url(Utils::get_folio_permalink_by_id($page->ID)); ?>">
            <?php esc_html_e('Open folio', 'groove-folios'); ?>
          </a>
        <?php endif; ?>
      </div>
      <?php if (!empty($this->copyright)): ?>
        <div class="g-folio__theme-copyright">
          <?php echo esc_html($this->copyright); ?>
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
    <div class="g-folio__theme-2 g-folio__theme-cover<?php echo esc_attr($this->get_cover_fallback_class()); ?>" style="<?php echo esc_attr($this->get_cover_background_style('background')); ?>">
      <button type="button" class="g-folio__theme-nav-button"
        aria-label="<?php echo esc_attr__('Open navigation', 'groove-folios'); ?>" aria-expanded="false">
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
      <?php
      // Credit, not rendered: "Powered by Groove Folios. Theme designed by
      // {author}", the author linking to the site. WordPress.org guideline 10
      // allows no public credit without the site admin opting in.
      ?>
    </div>
    <?php
  }
}
