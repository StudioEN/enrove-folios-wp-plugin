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
  $label = isset($args['label']) ? (string) $args['label'] : 'RECENT';
  $limit = isset($args['limit']) ? (int) $args['limit'] : 10;

  $items = get_recent_navigation_pages($theme, $folio_id, $limit);

  $content_class = $class_prefix . '-content';
  $close_class = $class_prefix . '-close';
  $name_class = $class_prefix . '-name';
  $label_class = $class_prefix . '-label';
  $list_class = $class_prefix . 's';
  $item_link_class = $class_prefix . '-item-link';
  $item_class = $class_prefix . '-item';
  $item_order_class = $class_prefix . '-item-order';
  ?>
  <nav class="<?= esc_attr($class_prefix) ?>">
    <div class="<?= esc_attr($content_class) ?>">
      <button class="<?= esc_attr($close_class) ?>"></button>
      <h3 class="<?= esc_attr($name_class) ?>">
        <?php if (!empty($title_url)): ?>
          <a href="<?= esc_url($title_url) ?>">
            <?= esc_html($title) ?>
          </a>
        <?php else: ?>
          <?= esc_html($title) ?>
        <?php endif; ?>
      </h3>
      <label class="<?= esc_attr($label_class) ?>"><?= esc_html($label) ?></label>
      <div class="<?= esc_attr($list_class) ?>">
        <?php foreach ($items as $index => $page): ?>
          <a class="<?= esc_attr($item_link_class) ?>" href="<?= esc_url(Utils::get_folio_permalink_by_id($page->ID)) ?>">
            <div class="<?= esc_attr($item_class) ?>">
              <!-- <i class="<?= esc_attr($item_order_class) ?>"><?= (int) $index + 1 ?></i> -->
              <?= esc_html($page->post_title) ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </nav>
  <?php
}
