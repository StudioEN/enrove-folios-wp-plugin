<?php
namespace Groove\Themes\Groove_Proposal;

use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
  exit;
}

function display_proposal_navigation_pane(array $args = []): void
{
  $class_prefix = isset($args['class_prefix']) ? (string) $args['class_prefix'] : 'g-folio__theme-nav';
  $alias_prefix = isset($args['alias_prefix']) ? (string) $args['alias_prefix'] : '';
  $title = isset($args['title']) ? (string) $args['title'] : '';
  $title_url = isset($args['title_url']) ? (string) $args['title_url'] : '';
  $label = isset($args['label']) ? (string) $args['label'] : __('Proposal sections', 'groove-folios');
  $aria_label = isset($args['aria_label']) ? (string) $args['aria_label'] : __('Proposal navigation', 'groove-folios');
  $current_page_id = isset($args['current_page_id']) ? (int) $args['current_page_id'] : 0;
  $pages = isset($args['pages']) && is_array($args['pages']) ? $args['pages'] : [];

  // Info panel data
  $info_version = isset($args['info_version']) ? trim((string) $args['info_version']) : '';
  $info_date = isset($args['info_date']) ? trim((string) $args['info_date']) : '';
  $info_contacts = isset($args['info_contacts']) && is_array($args['info_contacts']) ? $args['info_contacts'] : [];

  $normalized_contacts = [];
  foreach ($info_contacts as $contact) {
    if (!is_array($contact)) {
      continue;
    }

    $c_name = trim((string) ($contact['name'] ?? ''));
    $c_role = trim((string) ($contact['role'] ?? ''));
    $c_email = sanitize_email((string) ($contact['email'] ?? ''));
    $c_phone = trim((string) ($contact['phone'] ?? ''));

    if ($c_name === '' && $c_role === '' && $c_email === '' && $c_phone === '') {
      continue;
    }

    $normalized_contacts[] = [
      'name' => $c_name,
      'role' => $c_role,
      'email' => $c_email,
      'phone' => $c_phone,
    ];
  }

  $has_meta_data = $info_version !== '' || $info_date !== '';
  $has_contact_data = !empty($normalized_contacts);
  $has_info_data = $has_meta_data || $has_contact_data;
  $info_panel_id = function_exists('wp_unique_id')
    ? wp_unique_id('gp-nav-details-')
    : ('gp-nav-details-' . uniqid());

  $valid_pages = [];
  foreach ($pages as $page) {
    if (isset($page->ID) && (int) $page->ID > 0) {
      $valid_pages[] = $page;
    }
  }

  $total_pages = count($valid_pages);
  $current_index = 0;

  if ($current_page_id > 0) {
    foreach ($valid_pages as $index => $page) {
      if ((int) $page->ID === $current_page_id) {
        $current_index = $index + 1;
        break;
      }
    }
  }

  $progress_percent = ($total_pages > 0 && $current_index > 0)
    ? (int) round(($current_index / $total_pages) * 100)
    : 0;

  $nav_class = $class_prefix . ($alias_prefix !== '' ? ' ' . $alias_prefix : '');
  $content_class = $class_prefix . '-content';
  $close_class = $class_prefix . '-close' . ($alias_prefix !== '' ? ' ' . $alias_prefix . '-close' : '');
  $name_class = $class_prefix . '-name';
  $list_class = $class_prefix . 's';
  $item_link_class = $class_prefix . '-item-link';
  $item_class = $class_prefix . '-item';
  $item_order_class = $class_prefix . '-item-order';
  ?>
  <nav class="<?php echo esc_attr($nav_class); ?>" aria-label="<?php echo esc_attr($aria_label); ?>">
    <div class="<?php echo esc_attr($content_class); ?>">

      <!-- Header: title + close -->
      <div class="gp-nav__panel-header">
        <div class="gp-nav__panel-title-area">
          <p class="gp-nav__eyebrow"><?php echo esc_html($label); ?></p>
          <h3 class="<?php echo esc_attr($name_class); ?>">
            <?php if ($title_url !== ''): ?>
              <a href="<?php echo esc_url($title_url); ?>"><?php echo esc_html($title); ?></a>
            <?php else: ?>
              <?php echo esc_html($title); ?>
            <?php endif; ?>
          </h3>
        </div>
        <button type="button" class="<?php echo esc_attr($close_class); ?>"
          aria-label="<?php echo esc_attr__('Close navigation', 'groove-folios'); ?>"></button>
      </div>

      <!-- Progress indicator -->
      <div class="gp-nav__progress">
        <p class="gp-nav__progress-label"><?php echo esc_html__('Progress', 'groove-folios'); ?></p>
        <div class="gp-nav__progress-track" aria-hidden="true">
          <span class="gp-nav__progress-value" style="width: <?php echo (int) $progress_percent; ?>%"></span>
        </div>
      </div>

      <!-- Section list -->
      <div class="<?php echo esc_attr($list_class); ?>">
        <?php foreach ($valid_pages as $index => $page): ?>
          <?php
          $page_id = (int) $page->ID;
          $ordinal = $index + 1;
          $is_current = $current_page_id > 0 && $page_id === $current_page_id;
          $is_completed = $current_page_id > 0 && $ordinal < $current_index;

          $item_link_classes = $item_link_class;
          if ($is_current) {
            $item_link_classes .= ' ' . $item_link_class . '--current';
          }
          if ($is_completed) {
            $item_link_classes .= ' ' . $item_link_class . '--past';
          }
          ?>
          <a class="<?php echo esc_attr($item_link_classes); ?>" href="<?php echo esc_url(Utils::get_folio_permalink_by_id($page_id)); ?>"
            <?php echo $is_current ? 'aria-current="page"' : ''; ?>>
            <div class="<?php echo esc_attr($item_class); ?>">
              <i
                class="<?php echo esc_attr($item_order_class); ?>"><?php echo esc_html(str_pad((string) $ordinal, 2, '0', STR_PAD_LEFT)); ?></i>
              <div class="gp-nav__item-copy">
                <span class="gp-nav__item-meta"><?php /* translators: %d: section number */ echo esc_html(sprintf(__('Section %d', 'groove-folios'), $ordinal)); ?></span>
                <span
                  class="<?php echo $is_current ? 'gp-nav__item-title' : 'gp-nav__item-title'; ?>"><?php echo esc_html($page->post_title ?? ''); ?></span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Footer: collapsible details panel -->
      <div class="gp-nav__footer">
        <div class="gp-nav__info" data-gp-nav-info>
          <button type="button" class="gp-nav__info-trigger" data-gp-nav-info-toggle aria-expanded="false"
            aria-controls="<?php echo esc_attr($info_panel_id); ?>">
            <span data-gp-nav-info-label-collapsed><?php echo esc_html__('Show more', 'groove-folios'); ?></span>
            <span data-gp-nav-info-label-expanded hidden><?php echo esc_html__('Show less', 'groove-folios'); ?></span>
            <svg class="gp-nav__info-chevron" width="12" height="12" viewBox="0 0 12 12" aria-hidden="true"
              focusable="false">
              <polyline points="3 5 6 8 9 5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                stroke-linejoin="round" />
            </svg>
          </button>
          <div id="<?php echo esc_attr($info_panel_id); ?>" class="gp-nav__info-panel" hidden>
            <?php if ($has_meta_data): ?>
              <div class="gp-nav__info-group">
                <?php if ($info_version !== ''): ?>
                  <div class="gp-nav__info-row">
                    <span class="gp-nav__info-label"><?php echo esc_html__('Version', 'groove-folios'); ?></span>
                    <span class="gp-nav__info-value"><?php echo esc_html($info_version); ?></span>
                  </div>
                <?php endif; ?>
                <?php if ($info_date !== ''): ?>
                  <div class="gp-nav__info-row">
                    <span class="gp-nav__info-label"><?php echo esc_html__('Date', 'groove-folios'); ?></span>
                    <span class="gp-nav__info-value"><?php echo esc_html($info_date); ?></span>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if ($has_contact_data): ?>
              <div class="gp-nav__info-group">
                <span class="gp-nav__info-group-label"><?php echo esc_html__('Contact', 'groove-folios'); ?></span>
                <?php foreach ($normalized_contacts as $contact): ?>
                  <div class="gp-nav__info-contact">
                    <?php if ($contact['name'] !== ''): ?>
                      <span class="gp-nav__info-contact-name"><?php echo esc_html($contact['name']); ?></span>
                    <?php endif; ?>
                    <?php if ($contact['role'] !== ''): ?>
                      <span class="gp-nav__info-contact-role"><?php echo esc_html($contact['role']); ?></span>
                    <?php endif; ?>
                    <?php if ($contact['email'] !== ''): ?>
                      <a class="gp-nav__info-contact-link"
                        href="mailto:<?php echo esc_attr($contact['email']); ?>"><?php echo esc_html($contact['email']); ?></a>
                    <?php endif; ?>
                    <?php if ($contact['phone'] !== ''): ?>
                      <span class="gp-nav__info-contact-detail"><?php echo esc_html($contact['phone']); ?></span>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if (!$has_info_data): ?>
              <p class="gp-nav__info-empty"><?php echo esc_html__('No metadata or contacts added yet.', 'groove-folios'); ?></p>
            <?php endif; ?>

            <div class="gp-nav__info-group gp-nav__info-group--mode">
              <button type="button" class="gp-theme-toggle gp-theme-toggle--nav" data-gp-theme-toggle
                aria-label="<?php echo esc_attr__('Switch colour mode', 'groove-folios'); ?>"></button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </nav>
  <?php
}
