<?php
namespace Groove\Themes\Groove_Magazine;

use Groove\Themes\Base_Theme;
use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
    exit;
}

class Page extends Base_Theme
{
    public $folio_id;
    public $folio;

    // ─────────────────────────────────────────────────────────────────────────

    public function __construct()
    {
        parent::__construct();

        $this->folio_id = isset($_REQUEST['folio_id']) ? $_REQUEST['folio_id'] : '';

        if ($this->post_type == 'groove_folio_page') {
            $meta = get_post_meta($this->id);
            $this->folio_id = $meta['folio_id'][0] ?? '';

            // Auto-heal missing folio_id from URL.
            if (empty($this->folio_id) && $this->id) {
                $path = Utils::get_current_path();
                $base_slug = Utils::get_folio_base_slug();
                $pattern = '#^/' . preg_quote($base_slug, '#') . '/([^/]+)/page/#';
                if (preg_match($pattern, $path, $url_matches)) {
                    $folio_slug = $url_matches[1];
                    $folio_query = new \WP_Query(array(
                        'post_type' => 'groove_folio',
                        'name' => $folio_slug,
                        'posts_per_page' => 1,
                        'post_status' => Utils::get_viewable_post_statuses(),
                    ));
                    if ($folio_query->post) {
                        $this->folio_id = $folio_query->post->ID;
                        update_post_meta($this->id, 'folio_id', $this->folio_id);
                    }
                }
            }
        }
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

    function get_folio_data()
    {
        $wp_query = $this->get_the_wp_query(array(
            'post__in' => array($this->folio_id),
            'post_status' => Utils::get_viewable_post_statuses(),
            'post_type' => 'groove_folio',
        ));

        $page = $wp_query->post;
        $this->folio = $page;

        return $page;
    }

    function get_data()
    {
        if ($this->is_preview_mode) { return; }
        $this->get_folio_data();
        $this->get_page_data();
        $this->get_pages_data($this->folio_id);
        if (is_array($this->pages) && !empty($this->pages)) {
            $this->pages = get_magazine_pages_in_display_order($this->pages);
        }
        $this->get_theme_data();
    }

    // ── HTML helpers (same as folio-starter) ──────────────────────────────

    function get_html($html)
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML($html);

        $element_names = ['h2'];

        foreach ($element_names as $element_name) {
            $element = $doc->getElementsByTagName($element_name)->item(0);
            if ($element) {
                return [
                    $this->get_html_id($element),
                    $element->textContent,
                    $element_name
                ];
            }
        }

        return null;
    }

    function to_anchor_name($string)
    {
        $dstr = preg_replace_callback('/([A-Z]+)/', function ($matchs) {
            return '-' . strtolower($matchs[0]);
        }, $string);

        $dst = preg_replace_callback('/([\s]+)/', function ($matchs) {
            return '-';
        }, $dstr);

        return trim(preg_replace('/_{2,}/', '-', $dst), '-');
    }

    function get_html_id($element)
    {
        $id = $element->getAttribute('id');
        $textContent = $element->textContent;
        return $id ? $id : $this->to_anchor_name($textContent);
    }

    function display_catalogs()
    {
        $blocks = parse_blocks($this->content);

        echo '<div class="gm-page__catalogs-content">';

        foreach ($blocks as $block) {
            if ($block['blockName'] === 'core/heading') {
                $title = $block['innerContent'][0];
                $html = $this->get_html($title);
                if (!$html) {
                    continue;
                }
                $anchor = $this->to_anchor_name($html[1]);
                echo '<div class="gm-page__catalog"><a href="' . ($anchor ? ('#' . $anchor) : '') . '">' . esc_html($html[1]) . '</a></div>';
            }
        }

        echo '</div>';
    }

    function get_content()
    {
        $content = $this->content;
        $blocks = parse_blocks($content);

        $results = '';

        foreach ($blocks as $block) {
            if ($block['blockName'] === 'core/heading') {
                $title = $block['innerContent'][0];
                $html = $this->get_html($title);
                if ($html) {
                    $anchor = $this->to_anchor_name($html[1]);
                    $block['innerContent'][0] = '<' . $html[2] . ' id="' . $anchor . '">' . $html[1] . '</' . $html[2] . '>';
                }
            }

            $results .= render_block($block);
        }

        return $this->apply_embed_processing($results);
    }

    // ── Page index helpers ────────────────────────────────────────────────

    function is_first_page()
    {
        return empty($this->pages) ? false : ($this->pages[0]->ID === $this->id);
    }

    function is_last_page()
    {
        return empty($this->pages) ? false : ($this->pages[count($this->pages) - 1]->ID === $this->id);
    }

    function get_current_index()
    {
        $index = 0;
        foreach ($this->pages as $page) {
            if ($page->ID == $this->id) {
                return $index;
            }
            $index++;
        }
        return -1;
    }

    function get_prev_page()
    {
        $index = $this->get_current_index();
        if ($index > 0) {
            return $this->pages[$index - 1];
        }
        return null;
    }

    function get_next_page()
    {
        if (empty($this->pages)) {
            return null;
        }
        $index = $this->get_current_index();
        if ($index > -1 && $index < count($this->pages) - 1) {
            return $this->pages[$index + 1];
        }
        return null;
    }

    // ── Display methods ───────────────────────────────────────────────────

    function display_hero()
    {
        $feature_image_url = get_the_post_thumbnail_url($this->id, 'large');
        if (!$feature_image_url) {
            return;
        }
        ?>
        <div class="gm-page__hero" data-gm-feature-image="<?= esc_url($feature_image_url) ?>">
            <div class="gm-page__hero-bg" style="background-image: url(<?= esc_url($feature_image_url) ?>)"></div>
            <div class="gm-page__hero-overlay"></div>
        </div>
        <?php
    }

    function display_navbar()
    {
        ?>
        <header class="gm-page__navbar">
            <div class="gm-page__navbar-progress">
                <div class="gm-page__navbar-progress-bar" style="width: 0%"></div>
            </div>
            <div class="gm-page__navbar-inner">
                <button class="gm-page__nav-toggle" aria-label="Open navigation" data-tooltip="Contents">
                    <svg class="gm-icon gm-icon--menu" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <div class="gm-page__navbar-title">
                    <span class="gm-page__navbar-story">
                        <?= esc_html($this->page->post_title ?? 'Page') ?>
                    </span>
                </div>
                <div class="gm-page__navbar-progress-label">
                    <?= esc_html__('SECTIONS', 'groove') ?>
                </div>
            </div>
        </header>
        <?php $this->display_mobile_nav() ?>
        <?php
    }

    function display_mobile_nav()
    {
        $on_this_page_label = Utils::get_folio_on_this_page_label((int) $this->folio_id);
        $blocks = parse_blocks($this->content);
        ?>
        <nav class="gm-page__mobile-nav" aria-label="On this page">
            <div class="gm-page__mobile-nav-content">
                <div class="gm-page__mobile-nav-label">
                    <?= esc_html($on_this_page_label) ?>
                </div>
                <div class="gm-page__mobile-nav-items">
                    <?php
                    foreach ($blocks as $block) {
                        if ($block['blockName'] === 'core/heading') {
                            $title = $block['innerContent'][0];
                            $html = $this->get_html($title);
                            if (!$html) {
                                continue;
                            }
                            $anchor = $this->to_anchor_name($html[1]);
                            ?>
                            <a class="gm-page__mobile-nav-link" href="<?= '#' . $anchor ?>">
                                <div class="gm-page__mobile-nav-item">
                                    <?= esc_html($html[1]) ?>
                                </div>
                            </a>
                            <?php
                        }
                    }
                    ?>
                </div>
                <div class="gm-page__mobile-nav-top">↑ Back to top</div>
            </div>
        </nav>
        <?php
    }

    function display_nav()
    {
        $nav = $this->get_navigation_context();

        display_magazine_navigation_pane([
            'title' => $nav['title'],
            'title_url' => $nav['title_url'],
            'label' => 'LATEST',
            'aria_label' => 'Issue contents',
            'pages' => $nav['pages'],
            'current_page_id' => $nav['current_page_id'],
            'show_theme_toggle' => true,
        ]);
    }

    function display_footer()
    {
        $prev_page = $this->get_prev_page();
        $next_page = $this->get_next_page();
        ?>
        <nav class="gm-page__footer" aria-label="Story navigation">
            <div class="gm-page__footer-prev">
                <?php if ($prev_page): ?>
                    <a class="gm-page__footer-link" href="<?= esc_url(Utils::get_folio_permalink_by_id($prev_page->ID)) ?>">
                        <span class="gm-page__footer-direction">← Previous</span>
                        <span class="gm-page__footer-title" title="<?= esc_attr($prev_page->post_title) ?>">
                            <?= esc_html($prev_page->post_title) ?>
                        </span>
                    </a>
                <?php endif; ?>
            </div>

            <div class="gm-page__footer-next">
                <?php if ($next_page): ?>
                    <a class="gm-page__footer-link" href="<?= esc_url(Utils::get_folio_permalink_by_id($next_page->ID)) ?>">
                        <span class="gm-page__footer-direction">Next →</span>
                        <span class="gm-page__footer-title" title="<?= esc_attr($next_page->post_title) ?>">
                            <?= esc_html($next_page->post_title) ?>
                        </span>
                    </a>
                <?php endif; ?>
            </div>
        </nav>
        <?php
    }

    function display_theme()
    {
        if (!parent::display_theme()) {
            return;
        }

        $on_this_page_label = Utils::get_folio_on_this_page_label((int) $this->folio_id);

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
        <div class="gm gm-page g-folio__theme-groove-magazine-page">
            <?php $this->display_navbar() ?>
            <?php $this->display_nav() ?>

            <?php $this->display_hero() ?>

            <main class="gm-page__main">
                <div class="gm-page__body">
                    <div class="gm-page__center">
                        <article class="gm-page__container">
                            <h1 class="gm-page__title">
                                <?= esc_html($this->title) ?>
                            </h1>
                            <div class="gm-page__content">
                                <?= $this->get_content() ?>
                            </div>
                            <?php $this->display_footer() ?>
                        </article>
                        <aside class="gm-page__sidebar">
                            <div class="gm-page__catalogs">
                                <label class="gm-page__catalogs-label">
                                    <?= esc_html($on_this_page_label) ?>
                                </label>
                                <?php $this->display_catalogs(); ?>
                            </div>
                        </aside>
                    </div>
                </div>
            </main>
        </div>
        <?php
    }
}
