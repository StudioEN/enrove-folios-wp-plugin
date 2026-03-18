<?php
namespace Groove\Themes\Groove_Magazine;

use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Order magazine pages for navigation and cover rendering.
 *
 * Default order is latest-to-oldest by publish date. If any page has a
 * non-zero menu_order, treat that as an explicit manual override and sort by
 * menu_order ASC instead.
 */
function get_magazine_pages_in_display_order(array $pages): array
{
    if (empty($pages)) {
        return [];
    }

    $ordered_pages = array_values($pages);
    $menu_orders = [];
    $published_timestamps = [];
    $has_menu_order_override = false;

    foreach ($ordered_pages as $page) {
        $page_id = isset($page->ID) ? (int) $page->ID : 0;
        $menu_order = isset($page->menu_order) ? (int) $page->menu_order : 0;
        $menu_orders[$page_id] = $menu_order;
        if ($menu_order !== 0) {
            $has_menu_order_override = true;
        }

        $published_timestamps[$page_id] = $page_id > 0 ? (int) get_post_time('U', true, $page) : 0;
    }

    usort($ordered_pages, static function ($a, $b) use ($has_menu_order_override, $menu_orders, $published_timestamps): int {
        $a_id = isset($a->ID) ? (int) $a->ID : 0;
        $b_id = isset($b->ID) ? (int) $b->ID : 0;

        if ($has_menu_order_override) {
            $a_menu_order = $menu_orders[$a_id] ?? 0;
            $b_menu_order = $menu_orders[$b_id] ?? 0;
            if ($a_menu_order !== $b_menu_order) {
                return $a_menu_order <=> $b_menu_order;
            }
        }

        $a_published = $published_timestamps[$a_id] ?? 0;
        $b_published = $published_timestamps[$b_id] ?? 0;
        if ($a_published !== $b_published) {
            return $b_published <=> $a_published;
        }

        return $a_id <=> $b_id;
    });

    return $ordered_pages;
}

/**
 * Render the magazine navigation pane.
 *
 * This is intentionally theme-local so layout redesigns stay scoped to
 * groove-magazine without impacting other themes.
 */
function display_magazine_navigation_pane(array $args = [])
{
    $title = isset($args['title']) ? (string) $args['title'] : '';
    $title_url = isset($args['title_url']) ? (string) $args['title_url'] : '';
    $label = isset($args['label']) ? (string) $args['label'] : 'LATEST';
    $aria_label = isset($args['aria_label']) ? (string) $args['aria_label'] : 'Issue contents';
    $current_page_id = isset($args['current_page_id']) ? (int) $args['current_page_id'] : 0;
    $show_theme_toggle = !isset($args['show_theme_toggle']) || (bool) $args['show_theme_toggle'];
    $pages = isset($args['pages']) && is_array($args['pages']) ? $args['pages'] : [];

    $valid_pages = [];
    $latest_timestamp = 0;
    foreach ($pages as $page) {
        $page_id = isset($page->ID) ? (int) $page->ID : 0;
        if ($page_id <= 0) {
            continue;
        }

        $valid_pages[] = $page;

        $published_timestamp = (int) get_post_time('U', true, $page);
        $modified_timestamp = (int) get_post_modified_time('U', true, $page);
        $page_latest_timestamp = max($published_timestamp, $modified_timestamp);
        if ($page_latest_timestamp > $latest_timestamp) {
            $latest_timestamp = $page_latest_timestamp;
        }
    }

    $latest_page = $valid_pages[0] ?? null;
    $recent_pages = array_slice($valid_pages, 1);
    $latest_date = $latest_timestamp > 0 ? strtoupper(wp_date('M j Y', $latest_timestamp)) : '';
    $latest_datetime = $latest_timestamp > 0 ? wp_date('Y-m-d', $latest_timestamp) : '';
    $latest_label = trim($label);
    if ($latest_label === '' || strtoupper($latest_label) === 'STORIES') {
        $latest_label = 'LATEST';
    }

    $render_item = static function ($page, int $index, bool $is_current, bool $is_featured = false): void {
        $page_id = isset($page->ID) ? (int) $page->ID : 0;
        if ($page_id <= 0) {
            return;
        }

        $link_class = 'gm-nav__item-link';
        $link_class .= $is_current ? ' gm-nav__item-link--active' : '';
        $link_class .= $is_featured ? ' gm-nav__item-link--featured' : ' gm-nav__item-link--recent';
        $thumb_url = get_the_post_thumbnail_url($page_id, 'thumbnail');
        $nav_image_url = get_the_post_thumbnail_url($page_id, 'large');
        if (!$nav_image_url) {
            $nav_image_url = $thumb_url ?: '';
        }
        ?>
        <a class="<?= esc_attr($link_class) ?>" href="<?= esc_url(Utils::get_folio_permalink_by_id($page_id)) ?>"<?=
              !empty($nav_image_url) ? ' data-gm-nav-image="' . esc_attr($nav_image_url) . '"' : '' ?>>
            <div class="gm-nav__item<?= $is_featured ? ' gm-nav__item--featured' : ' gm-nav__item--recent' ?>">
                <?php if ($thumb_url): ?>
                    <img class="gm-nav__item-thumb<?= $is_featured ? ' gm-nav__item-thumb--featured' : ' gm-nav__item-thumb--recent' ?>"
                        src="<?= esc_url($thumb_url) ?>" alt="" loading="lazy" />
                <?php else: ?>
                    <span
                        class="gm-nav__item-index<?= $is_featured ? ' gm-nav__item-index--featured' : ' gm-nav__item-index--recent' ?>"><?= (int) $index ?></span>
                <?php endif; ?>
                <span class="gm-nav__item-title<?= $is_featured ? ' gm-nav__item-title--featured' : ' gm-nav__item-title--recent' ?>">
                    <?= esc_html($page->post_title ?? '') ?>
                </span>
            </div>
        </a>
        <?php
    };
    ?>
    <nav class="gm-nav" aria-label="<?= esc_attr($aria_label) ?>">
        <div class="gm-nav__content">
            <button class="gm-nav__close" aria-label="Close navigation" data-tooltip="Close"></button>
            <h3 class="gm-nav__title">
                <?php if (!empty($title_url)): ?>
                    <a href="<?= esc_url($title_url) ?>">
                        <?= esc_html($title) ?>
                    </a>
                <?php else: ?>
                    <?= esc_html($title) ?>
                <?php endif; ?>
            </h3>
            <div class="gm-nav__meta">
                <span class="gm-nav__meta-label"><?= esc_html($latest_label) ?></span>
                <?php if (!empty($latest_date)): ?>
                    <span class="gm-nav__meta-separator" aria-hidden="true">|</span>
                    <time class="gm-nav__meta-date" datetime="<?= esc_attr($latest_datetime) ?>"><?= esc_html($latest_date) ?></time>
                <?php endif; ?>
            </div>

            <?php
            if ($latest_page) {
                $latest_page_id = isset($latest_page->ID) ? (int) $latest_page->ID : 0;
                $render_item(
                    $latest_page,
                    1,
                    $current_page_id > 0 && $latest_page_id === $current_page_id,
                    true
                );
            }
            ?>

            <?php if (!empty($recent_pages)): ?>
                <hr class="gm-nav__divider" />
                <span class="gm-nav__section-title">RECENT</span>
                <div class="gm-nav__items gm-nav__items--recent">
                    <?php
                    foreach ($recent_pages as $offset => $page) {
                        $page_id = isset($page->ID) ? (int) $page->ID : 0;
                        $render_item(
                            $page,
                            $offset + 2,
                            $current_page_id > 0 && $page_id === $current_page_id
                        );
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($show_theme_toggle): ?>
            <button class="gm-theme-toggle gm-theme-toggle--nav" aria-label="Switch to dark mode"
                data-tooltip="Switch to dark mode"></button>
        <?php endif; ?>
    </nav>
    <?php
}
