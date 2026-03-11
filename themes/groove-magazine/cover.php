<?php
namespace Groove\Themes\Groove_Magazine;

use Groove\Themes\Base_Theme;
use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
    exit;
}

class Cover extends Base_Theme
{
    public $subtitle;

    /**
     * Returns the latest timestamp across all folio pages using publish/modified time.
     */
    protected function get_latest_pages_timestamp()
    {
        if (empty($this->pages) || !is_array($this->pages)) {
            return 0;
        }

        $latest_timestamp = 0;

        foreach ($this->pages as $page) {
            $published_timestamp = (int) get_post_time('U', true, $page);
            $modified_timestamp = (int) get_post_modified_time('U', true, $page);
            $page_latest_timestamp = max($published_timestamp, $modified_timestamp);

            if ($page_latest_timestamp > $latest_timestamp) {
                $latest_timestamp = $page_latest_timestamp;
            }
        }

        return $latest_timestamp;
    }

    function get_page_data()
    {
        $page = parent::get_page_data();
        $this->subtitle = $page ? get_post_meta($page->ID, 'subtitle', true) : '';
    }

    /**
     * Enqueue the theme's color-extraction JS alongside the default scripts.
     */
    public function ensure_script()
    {
        parent::ensure_script();

        $js_path = trailingslashit(GROOVE_PATH) . 'themes/' . static::get_id() . '/assets/js/groove-magazine.js';
        $version = file_exists($js_path) ? filemtime($js_path) : GROOVE_VERSION;

        wp_enqueue_script(
            'groove-magazine-colors',
            $this->get_theme_assets_url() . 'js/groove-magazine.js',
            [],
            $version,
            true
        );
    }

    /**
     * Collect feature image URLs for the slideshow.
     * Returns up to $limit images from the most recent pages (stories).
     */
    function get_slideshow_images($limit = 3)
    {
        $images = [];
        $count = 0;
        foreach ($this->pages as $page) {
            if ($count >= $limit)
                break;
            $url = get_the_post_thumbnail_url($page->ID, 'large');
            if ($url) {
                $images[] = [
                    'url' => $url,
                    'page_id' => $page->ID,
                ];
                $count++;
            }
        }
        // Fall back to the theme cover if no feature images
        if (empty($images)) {
            $images[] = [
                'url' => $this->theme_cover_url,
                'page_id' => 0,
            ];
        }
        return $images;
    }

    function display_nav()
    {
        $nav = $this->get_navigation_context([
            'title' => $this->theme_name,
            'title_url' => '',
        ]);

        display_magazine_navigation_pane([
            'title' => $nav['title'],
            'title_url' => $nav['title_url'],
            'label' => 'LATEST',
            'aria_label' => 'Issue contents',
            'pages' => $nav['pages'],
            'show_theme_toggle' => true,
        ]);
    }

    function display_password_form($post)
    {
        $post = get_post($post);
        ?>
        <form action="<?= esc_url(site_url('wp-login.php?action=postpass', 'login_post')) ?>" class="post-password-form"
            method="post">
            <input placeholder="Enter password" class="gm-cover__password-input" name="post_password" type="password"
                spellcheck="false" size="20" />
            <input class="gm-cover__password-submit" type="submit" name="Submit" value="Enter" />
        </form>
        <?php
    }

    function display_hero()
    {
        $slideshow_images = $this->get_slideshow_images(3);
        $latest_pages_timestamp = $this->get_latest_pages_timestamp();
        $latest_pages_date = $latest_pages_timestamp > 0 ? wp_date('M j Y', $latest_pages_timestamp) : '';

        // Build the recent stories list (up to 4: 1 featured + 3 more)
        $recent_stories = [];
        foreach ($this->pages as $page) {
            if (count($recent_stories) >= 4)
                break;
            $feature_url = get_the_post_thumbnail_url($page->ID, 'large');
            $recent_stories[] = [
                'id' => $page->ID,
                'title' => $page->post_title,
                'url' => Utils::get_folio_permalink_by_id($page->ID),
                'feature_url' => $feature_url ?: '',
            ];
        }

        $featured = $recent_stories[0] ?? null;
        $more_stories = array_slice($recent_stories, 1, 3);
        ?>
        <div class="gm-cover__hero" data-gm-hero-image="<?= esc_url($slideshow_images[0]['url'] ?? '') ?>">

            <!-- Slideshow layers -->
            <div class="gm-cover__slideshow">
                <?php foreach ($slideshow_images as $i => $img): ?>
                    <div class="gm-cover__slide<?= $i === 0 ? ' gm-cover__slide--active' : '' ?>" data-gm-slide-index="<?= $i ?>"
                        style="background-image: url(<?= esc_url($img['url']) ?>)">
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="gm-cover__hero-overlay"></div>

            <div class="gm-cover__hero-content">
                <div class="gm-cover__hero-top">
                    <button class="gm-cover__nav-toggle" aria-label="Open navigation" data-tooltip="Contents">
                        <svg class="gm-icon gm-icon--menu" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <line x1="3" y1="12" x2="21" y2="12"></line>
                            <line x1="3" y1="18" x2="21" y2="18"></line>
                        </svg>
                    </button>
                    <i class="gm-cover__logo">
                        <img src="<?= esc_url($this->theme_logo_url) ?>" alt="<?= esc_attr($this->theme_name) ?>" />
                    </i>
                    <div style="flex: 1;"></div>
                </div>

                <div class="gm-cover__meta">
                    <h1 class="gm-cover__title"><?= esc_html($this->title) ?></h1>
                    <?php if (!empty($this->subtitle)): ?>
                        <h2 class="gm-cover__subtitle"><?= esc_html($this->subtitle) ?></h2>
                    <?php endif; ?>
                    <?php if (!empty($this->author)): ?>
                        <p class="gm-cover__author">By <?= esc_html($this->author) ?></p>
                    <?php endif; ?>
                </div>

                <?php
                $post_password_required = post_password_required($this->id);
                if ($post_password_required) {
                    $this->display_password_form($this->id);
                } else {
                    ?>
                    <!-- Story links -->
                    <div class="gm-cover__stories">
                        <?php if ($featured): ?>
                            <div class="gm-cover__featured">
                                <span class="gm-cover__stories-label">LATEST</span>
                                <a class="gm-cover__featured-link" href="<?= esc_url($featured['url']) ?>"
                                    data-gm-story-image="<?= esc_url($featured['feature_url']) ?>">
                                    <span class="gm-cover__featured-title"><?= esc_html($featured['title']) ?></span>
                                    <span class="gm-cover__featured-arrow">→</span>
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($more_stories)): ?>
                            <div class="gm-cover__more">
                                <span class="gm-cover__stories-label">RECENT</span>
                                <?php foreach ($more_stories as $story): ?>
                                    <a class="gm-cover__more-link" href="<?= esc_url($story['url']) ?>"
                                        data-gm-story-image="<?= esc_url($story['feature_url']) ?>">
                                        <?= esc_html($story['title']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php } ?>

                <div class="gm-cover__footer">
                    <?php if (!empty($this->copyright)): ?>
                        <span class="gm-cover__copyright">
                            <?= esc_html($this->copyright) ?>
                        </span>
                    <?php else: ?>
                        <span class="gm-cover__copyright" aria-hidden="true"></span>
                    <?php endif; ?>
                    <span class="gm-cover__powerby">Powered by Groove Folios</span>
                    <?php if (!empty($latest_pages_date)): ?>
                        <span class="gm-cover__updated">
                            <?= esc_html(sprintf(__('Updated %s', 'groove'), $latest_pages_date)) ?>
                        </span>
                    <?php else: ?>
                        <span class="gm-cover__updated" aria-hidden="true"></span>
                    <?php endif; ?>
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
        <script>
            (function () {
                try {
                    var theme = localStorage.getItem('gm-theme');
                    var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (theme === 'dark' || (!theme && prefersDark)) document.documentElement.classList.add('gm-theme-dark');
                    else if (theme === 'light' || (!theme && !prefersDark)) document.documentElement.classList.add('gm-theme-light');
                } catch (e) { }
            })();
        </script>
        <div class="gm gm-cover g-folio__theme-cover">
            <?php $this->display_nav() ?>
            <?php $this->display_hero() ?>
        </div>
        <?php
    }
}
