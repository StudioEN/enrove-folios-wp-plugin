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

  protected function get_latest_pages_timestamp(): int
  {
    if (empty($this->pages) || !is_array($this->pages)) {
      return 0;
    }

    $latest_timestamp = 0;
    foreach ($this->pages as $page) {
      $published_timestamp = (int) get_post_time('U', true, $page);
      $modified_timestamp = (int) get_post_modified_time('U', true, $page);
      $latest_timestamp = max($latest_timestamp, max($published_timestamp, $modified_timestamp));
    }

    return $latest_timestamp;
  }

  protected function get_issue_date_label(): string
  {
    $latest_timestamp = $this->get_latest_pages_timestamp();
    if ($latest_timestamp <= 0) {
      return '';
    }

    return (string) wp_date(get_option('date_format'), $latest_timestamp);
  }

  public function ensure_script()
  {
    parent::ensure_script();

    $js_path = trailingslashit(GROOVE_PATH) . 'themes/' . static::get_id() . '/assets/js/groove-newsletter.js';
    $version = file_exists($js_path) ? filemtime($js_path) : GROOVE_VERSION;

    wp_enqueue_script(
      'groove-newsletter-theme',
      $this->get_theme_assets_url() . 'js/groove-newsletter.js',
      [],
      $version,
      true
    );
  }

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
      'title' => $this->title,
      'limit' => 10,
    ));
  }

  function display_brief()
  {
    $issue_date_label = $this->get_issue_date_label();
    ?>
    <div class="g-folio__theme-brief">
      <div class="gn-cover__edition-line">
        <div class="gn-cover__edition-actions">
          <button class="g-folio__theme-nav-button gn-nav-trigger gn-nav-trigger--cover"
            aria-label="<?php echo esc_attr__('Open issue navigation', 'groove-folios'); ?>">
            <span class="gn-nav-trigger__label"><?php echo esc_html__('Contents', 'groove-folios'); ?></span>
            <svg class="g-folio__theme-menu-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <line x1="3" y1="12" x2="21" y2="12"></line>
              <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
          </button>
        </div>
      </div>

      <div class="gn-cover__intro">
        <?php if (!empty($this->theme_logo_url)): ?>
          <i class="g-folio__theme-logo">
            <img src="<?php echo esc_url($this->theme_logo_url); ?>" alt="<?php echo esc_attr($this->theme_name); ?>" />
          </i>
        <?php endif; ?>
        <?php if (!empty($this->subtitle)): ?>
          <p class="gn-cover__kicker"><?php echo esc_html($this->subtitle); ?></p>
        <?php endif; ?>
        <h1 class="g-folio__theme-title">
          <?php echo esc_html($this->title); ?>
        </h1>
        <?php if (!empty($this->author)): ?>
          <p class="g-folio__theme-author">
            <?php echo esc_html(sprintf(__('By %s', 'groove-folios'), $this->author)); ?>
          </p>
        <?php endif; ?>

        <?php if ($issue_date_label !== ''): ?>
          <p class="gn-cover__left-meta">
            <span><?php echo esc_html__('Updated', 'groove-folios'); ?></span>
            <span><?php echo esc_html($issue_date_label); ?></span>
          </p>
        <?php endif; ?>
      </div>

      <div class="gn-cover__actions">
        <?php if (!empty($this->pages)): ?>
          <div class="gn-cover__story-list">
            <div class="gn-cover__story-list-head">
              <div class="gn-cover__story-list-label"><?php echo esc_html__('Latest', 'groove-folios'); ?></div>
            </div>
            <ol class="gn-cover__story-items">
              <?php foreach ($this->pages as $index => $story): ?>
                <li class="gn-cover__story-item">
                  <a class="gn-cover__story-link" href="<?php echo esc_url(Utils::get_folio_permalink_by_id($story->ID)); ?>">
                    <span
                      class="gn-cover__story-order"><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span>
                    <span class="gn-cover__story-title"><?php echo esc_html($story->post_title ?? ''); ?></span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ol>
          </div>
        <?php else: ?>
          <span class="gn-cover__empty-state"><?php echo esc_html__('Pages will appear here once published.', 'groove-folios'); ?></span>
        <?php endif; ?>
      </div>

      <div class="gn-cover__footer">
        <?php if (!empty($this->copyright)): ?>
          <div class="g-folio__theme-copyright">
            <?php echo esc_html($this->copyright); ?>
          </div>
        <?php endif; ?>

        <div class="g-folio__theme-powerby">
          <?php echo esc_html__('Powered by Groove Folios', 'groove-folios'); ?>
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
    <div class="g-folio__theme-newsletter gn gn-cover g-folio__theme-cover"
      style="background-image: url(<?php echo esc_url($this->theme_cover_url); ?>)">
      <?php $this->display_nav() ?>
      <div class="g-folio__theme-content">
        <?php $this->display_brief() ?>
      </div>
    </div>
    <?php
  }
}
