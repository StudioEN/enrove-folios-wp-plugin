<?php
namespace Groove\Themes\Groove_Newsletter;

use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
  exit;
}

function get_recent_navigation_pages($theme, int $folio_id, int $limit = 10): array
{
  if ($folio_id <= 0 || !method_exists($theme, 'get_the_wp_query')) {
    return [];
  }

  $wp_query = $theme->get_the_wp_query(array(
    'post_type' => 'groove_folio_page',
    'posts_per_page' => $limit,
    'orderby' => 'date',
    'order' => 'DESC',
    'post_status' => Utils::get_viewable_post_statuses(),
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- A page belongs to its folio only through folio_id post meta, and the query is capped at $limit rows.
    'meta_query' => array(
      array(
        'key' => 'folio_id',
        'value' => $folio_id,
        'compare' => '=',
        'type' => 'NUMERIC',
      ),
    ),
  ));

  return is_array($wp_query->posts) ? $wp_query->posts : [];
}

function display_recent_navigation_pane($theme, array $args = []): void
{
  $class_prefix = isset($args['class_prefix']) ? (string) $args['class_prefix'] : 'g-folio__theme-nav';
  $folio_id = isset($args['folio_id']) ? (int) $args['folio_id'] : 0;
  $title = isset($args['title']) ? (string) $args['title'] : '';
  $title_url = isset($args['title_url']) ? (string) $args['title_url'] : '';
  $label = isset($args['label']) ? (string) $args['label'] : '';
  $limit = isset($args['limit']) ? (int) $args['limit'] : 10;
  $current_page_id = isset($args['current_page_id']) ? (int) $args['current_page_id'] : 0;

  $items = get_recent_navigation_pages($theme, $folio_id, $limit);
  $latest_timestamp = 0;
  foreach ($items as $item) {
    $item_timestamp = max(
      (int) get_post_time('U', true, $item),
      (int) get_post_modified_time('U', true, $item)
    );
    $latest_timestamp = max($latest_timestamp, $item_timestamp);
  }
  $latest_date = $latest_timestamp > 0 ? wp_date(get_option('date_format'), $latest_timestamp) : '';

  $content_class = $class_prefix . '-content';
  $close_class = $class_prefix . '-close';
  $eyebrow_class = $class_prefix . '-eyebrow';
  $stats_class = $class_prefix . '-stats';
  $stat_class = $class_prefix . '-stat';
  $name_class = $class_prefix . '-name';
  $label_class = $class_prefix . '-label';
  $list_class = $class_prefix . 's';
  $item_link_class = $class_prefix . '-item-link';
  $item_class = $class_prefix . '-item';
  $item_order_class = $class_prefix . '-item-order';
  $item_body_class = $class_prefix . '-item-body';
  $item_title_class = $class_prefix . '-item-title';
  $item_meta_class = $class_prefix . '-item-meta';
  $item_state_class = $class_prefix . '-item-state';
  ?>
  <nav class="<?php echo esc_attr($class_prefix); ?>" aria-label="<?php echo esc_attr__('Issue navigation', 'groove-folios'); ?>">
    <div class="<?php echo esc_attr($content_class); ?>">
      <button class="<?php echo esc_attr($close_class); ?>" aria-label="<?php echo esc_attr__('Close navigation', 'groove-folios'); ?>"></button>
      <div class="<?php echo esc_attr($eyebrow_class); ?>"><?php echo esc_html__('Contents', 'groove-folios'); ?></div>
      <h3 class="<?php echo esc_attr($name_class); ?>">
        <?php if (!empty($title_url)): ?>
          <a href="<?php echo esc_url($title_url); ?>">
            <?php echo esc_html($title); ?>
          </a>
        <?php else: ?>
          <?php echo esc_html($title); ?>
        <?php endif; ?>
      </h3>
      <div class="<?php echo esc_attr($stats_class); ?>">
        <?php if ($latest_date !== ''): ?>
          <div class="<?php echo esc_attr($stat_class); ?>">
            <span><?php echo esc_html__('Updated', 'groove-folios'); ?></span>
            <time datetime="<?php echo esc_attr(gmdate('c', $latest_timestamp)); ?>"><?php echo esc_html($latest_date); ?></time>
          </div>
        <?php endif; ?>
        <?php if ($label !== ''): ?>
          <div class="<?php echo esc_attr($stat_class); ?>">
            <span><?php echo esc_html__('Showing', 'groove-folios'); ?></span>
            <span><?php echo esc_html($label); ?></span>
          </div>
        <?php endif; ?>
      </div>
      <?php if ($label !== ''): ?>
        <label class="<?php echo esc_attr($label_class); ?>"><?php echo esc_html($label); ?></label>
      <?php endif; ?>
      <div class="<?php echo esc_attr($list_class); ?>">
        <?php foreach ($items as $index => $page): ?>
          <?php
          $page_url = Utils::get_folio_permalink_by_id($page->ID);
          $page_timestamp = max(
            (int) get_post_time('U', true, $page),
            (int) get_post_modified_time('U', true, $page)
          );
          $page_date = $page_timestamp > 0 ? wp_date(get_option('date_format'), $page_timestamp) : '';
          $is_current = $current_page_id > 0 && (int) $page->ID === $current_page_id;
          $item_link_classes = $item_link_class . ($is_current ? ' ' . $item_link_class . '--current' : '');
          ?>
          <a class="<?php echo esc_attr($item_link_classes); ?>" href="<?php echo esc_url($page_url); ?>" <?php echo $is_current ? ' aria-current="page"' : ''; ?>>
            <div class="<?php echo esc_attr($item_class); ?>">
              <i
                class="<?php echo esc_attr($item_order_class); ?>"><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></i>
              <div class="<?php echo esc_attr($item_body_class); ?>">
                <div class="<?php echo esc_attr($item_title_class); ?>"><?php echo esc_html($page->post_title); ?></div>
                <?php if ($page_date !== ''): ?>
                  <time class="<?php echo esc_attr($item_meta_class); ?>" datetime="<?php echo esc_attr(gmdate('c', $page_timestamp)); ?>">
                    <?php echo esc_html($page_date); ?>
                  </time>
                <?php endif; ?>
              </div>
              <?php if ($is_current): ?>
                <span class="<?php echo esc_attr($item_state_class); ?>"><?php echo esc_html__('Current', 'groove-folios'); ?></span>
              <?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </nav>
  <?php
}
