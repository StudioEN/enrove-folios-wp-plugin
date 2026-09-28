<?php
namespace Groove\Themes\Groove_Magazine;

use Groove\Themes\Base_Theme;
use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
    exit;
}

class Page extends Base_Theme
{

    // ─────────────────────────────────────────────────────────────────────────

    public function __construct()
    {
        parent::__construct();

        $this->folio_id = $this->resolve_page_folio_id();
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

        // The light/dark class goes on <html> before first paint, so a reader
        // on a dark scheme never sees the light page flash first. A header
        // handle of its own, since everything above prints in the footer.
        wp_register_script('groove-magazine-scheme', false, [], $version, false);
        wp_enqueue_script('groove-magazine-scheme');
        wp_add_inline_script('groove-magazine-scheme', "(function () { try { var theme = localStorage.getItem('gm-theme'); var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches; if (theme === 'dark' || (!theme && prefersDark)) document.documentElement.classList.add('gm-theme-dark'); else if (theme === 'light' || (!theme && !prefersDark)) document.documentElement.classList.add('gm-theme-light'); } catch (e) { } })();");
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
                echo '<div class="gm-page__catalog"><a href="' . esc_attr($anchor ? ('#' . $anchor) : '') . '">' . esc_html($html[1]) . '</a></div>';
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
                    $block['innerContent'][0] = '<' . $html[2] . ' id="' . esc_attr($anchor) . '">' . esc_html($html[1]) . '</' . $html[2] . '>';
                }
            }

            $results .= render_block($block);
        }

        return $this->apply_embed_processing($results);
    }

    // ── Page index helpers ────────────────────────────────────────────────

    // ── Display methods ───────────────────────────────────────────────────

    function display_hero()
    {
        $feature_image_url = get_the_post_thumbnail_url($this->id, 'large');
        if (!$feature_image_url) {
            return;
        }
        ?>
        <div class="gm-page__hero" data-gm-feature-image="<?php echo esc_url($feature_image_url); ?>">
            <div class="gm-page__hero-bg" style="background-image: url(<?php echo esc_url($feature_image_url); ?>)"></div>
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
                        <?php echo esc_html($this->page->post_title ?? 'Page'); ?>
                    </span>
                </div>
                <div class="gm-page__navbar-progress-label">
                    <?php echo esc_html__('SECTIONS', 'groove-folios'); ?>
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
                    <?php echo esc_html($on_this_page_label); ?>
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
                            <a class="gm-page__mobile-nav-link" href="<?php echo esc_attr('#' . $anchor); ?>">
                                <div class="gm-page__mobile-nav-item">
                                    <?php echo esc_html($html[1]); ?>
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
                    <a class="gm-page__footer-link" href="<?php echo esc_url(Utils::get_folio_permalink_by_id($prev_page->ID)); ?>">
                        <span class="gm-page__footer-direction">← Previous</span>
                        <span class="gm-page__footer-title" title="<?php echo esc_attr($prev_page->post_title); ?>">
                            <?php echo esc_html($prev_page->post_title); ?>
                        </span>
                    </a>
                <?php endif; ?>
            </div>

            <div class="gm-page__footer-next">
                <?php if ($next_page): ?>
                    <a class="gm-page__footer-link" href="<?php echo esc_url(Utils::get_folio_permalink_by_id($next_page->ID)); ?>">
                        <span class="gm-page__footer-direction">Next →</span>
                        <span class="gm-page__footer-title" title="<?php echo esc_attr($next_page->post_title); ?>">
                            <?php echo esc_html($next_page->post_title); ?>
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
        <div class="gm gm-page g-folio__theme-groove-magazine-page">
            <?php $this->display_navbar() ?>
            <?php $this->display_nav() ?>

            <?php $this->display_hero() ?>

            <main class="gm-page__main">
                <div class="gm-page__body">
                    <div class="gm-page__center">
                        <article class="gm-page__container">
                            <h1 class="gm-page__title">
                                <?php echo esc_html($this->title); ?>
                            </h1>
                            <div class="gm-page__content">
                                <?php echo $this->get_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block-rendered post content, escaped by core; kses would strip embed iframes. ?>
                            </div>
                            <?php $this->display_footer() ?>
                        </article>
                        <aside class="gm-page__sidebar">
                            <div class="gm-page__catalogs">
                                <label class="gm-page__catalogs-label">
                                    <?php echo esc_html($on_this_page_label); ?>
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
