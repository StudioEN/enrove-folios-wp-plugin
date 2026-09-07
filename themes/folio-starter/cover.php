<?php
namespace Groove\Themes\Folio_Starter;

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
    <nav class="g-folio__theme-nav" aria-label="<?= esc_attr__('Folio contents', 'groove') ?>"
      data-groove-drawer=".g-folio__theme-nav-button">
      <?php // Outside the -content box, which is the scroller: the close button
        // used to scroll away with a long contents list. ?>
      <button type="button" class="g-folio__theme-nav-close" aria-label="<?= esc_attr__('Close navigation', 'groove') ?>">
        <svg class="g-folio__theme-close-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <line x1="6" y1="6" x2="18" y2="18"></line>
          <line x1="18" y1="6" x2="6" y2="18"></line>
        </svg>
      </button>
      <div class="g-folio__theme-nav-content">
        <h3 class="g-folio__theme-nav-name">
          <?= esc_html($this->theme_name) ?>
        </h3>
        <p class="g-folio__theme-nav-label"><?= esc_html__('Contents', 'groove') ?></p>
        <div class="g-folio__theme-navs">
          <?php
          $index = 1;
          foreach ($this->pages as $page) {
            ?>
            <a class="g-folio__theme-nav-item-link" href="<?= esc_url(Utils::get_folio_permalink_by_id($page->ID)) ?>">
              <div class="g-folio__theme-nav-item">
                <i class="g-folio__theme-nav-item-order">
                  <?= $index ?>
                </i>
                <?= esc_html($page->post_title) ?>
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
      <div class="g-folio__theme-brief-inner">

        <div class="g-folio__theme-header">
          <div class="g-folio__theme-header-inner">
            <button type="button" class="g-folio__theme-nav-button" aria-label="<?= esc_attr__('Open navigation', 'groove') ?>"
              aria-expanded="false">
              <svg class="g-folio__theme-menu-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
              </svg>
            </button>
            <?php if (!empty($this->theme_logo_url)): ?>
              <i class="g-folio__theme-logo">
                <img src="<?= esc_url($this->theme_logo_url) ?>" alt="" />
              </i>
            <?php endif; ?>
          </div>
        </div>

        <div class="g-folio__theme-brief-content">
          <h1 class="g-folio__theme-title">
            <?= esc_html($this->title) ?>
          </h1>
          <h2 class="g-folio__theme-subtitle">
            <?= esc_html($this->subtitle) ?>
          </h2>
          <?php if (!empty($this->author)): ?>
            <p class="g-folio__theme-author">
              <?= esc_html(sprintf(__('By %s', 'groove'), $this->author)) ?>
            </p>
          <?php endif; ?>

          <div class="g-folio__theme-fields g-folio__theme-password-form">
            <?php if (!empty($this->pages) && $page): ?>
              <a class="g-folio__theme-fields-submit" href="<?= esc_url(Utils::get_folio_permalink_by_id($page->ID)) ?>">
                <?= esc_html__('Open folio', 'groove') ?>
              </a>
            <?php endif; ?>
          </div>
          <div class="g-folio__theme-colophon">
            <?php if (!empty($this->copyright)): ?>
              <div class="g-folio__theme-copyright">
                <?= esc_html($this->copyright) ?>
              </div>
            <?php endif; ?>
            <div class="g-folio__theme-powerby">
              <?php
              $author_link = '<a class="g-folio__theme-site" href="/">' . esc_html(static::get_author()) . '</a>';
              printf(
                /* translators: %s: theme author, rendered as a link. */
                esc_html__('Powered by Groove Folios. Theme designed by %s', 'groove'),
                $author_link
              );
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php
  }

  function display_theme()
  {
    if (!parent::display_theme()) {
      return;
    }
    ?>
    <div class="g-folio__theme-1 g-folio__theme-cover">

      <?php $this->display_nav() ?>

      <div class="g-folio__theme-content">
        <?php $this->display_brief() ?>
        <div class="g-folio__theme-background" style="background-image: url(<?= esc_url($this->theme_cover_url) ?>)">
        </div>
      </div>
    </div>
    <?php
  }
}
