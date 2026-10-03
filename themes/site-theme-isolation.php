<?php
namespace Enrove\Themes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Site_Theme_Isolation
 *
 * Keeps the site's WordPress theme out of a folio. A folio renders in its own
 * folio theme, as a document of its own, yet WordPress still hands the site
 * theme four ways in, on the folio's front end and in a folio page's block
 * editor alike:
 *
 *   1. theme.json, through global styles: body background and text colour,
 *      fonts, heading weights, link and button styles, and the presets (the
 *      palette, font sizes, the theme's own web fonts) behind them;
 *   2. the Site Editor's saved styles, which ride on the same global styles,
 *      and the Customizer's Additional CSS;
 *   3. stylesheets and scripts the theme enqueues from its own folder;
 *   4. editor styles (add_editor_style()) in the block editor canvas.
 *
 * All four are cut off for the length of one request: a folio front-end
 * render (isolate_front_end(), called by Plugin's template_redirect handler
 * before either folio template is required) or an enrove_folio_page edit
 * screen (isolate_editor(), on current_screen). What is left is core's own
 * defaults, so a folio page looks, and offers the same colours and sizes,
 * whichever theme the site runs. Nothing is changed outside those requests,
 * and nothing is written: the site's global styles are hidden, not edited.
 *
 * Content that picked a site-theme colour before this (a has-*-color class
 * naming a site preset) loses that colour, as it would on a theme switch.
 *
 * Known gap: output a theme prints straight from a wp_head callback, rather
 * than through an enqueued handle, cannot be told apart from anyone else's
 * and still reaches the front end.
 *
 * @since 0.5.1
 */
class Site_Theme_Isolation
{
    /**
     * Theme supports that carry the site theme's editor presets and
     * restrictions into the block editor and into global styles.
     */
    const EDITOR_THEME_SUPPORTS = [
        'editor-color-palette',
        'editor-gradient-presets',
        'editor-font-sizes',
        'editor-spacing-sizes',
        'disable-custom-colors',
        'disable-custom-font-sizes',
        'disable-custom-gradients',
        'disable-layout-styles',
        'custom-line-height',
        'custom-spacing',
        'custom-units',
        'border',
        'link-color',
        'appearance-tools',
    ];

    public static function register(): void
    {
        add_action('current_screen', [static::class, 'maybe_isolate_editor']);
    }

    /**
     * For a request that renders a folio document. Must run before wp_head().
     */
    public static function isolate_front_end(): void
    {
        static::isolate_global_styles();

        // Additional CSS is saved per theme and written for it.
        remove_action('wp_head', 'wp_custom_css_cb', 101);

        add_filter('print_styles_array', [static::class, 'drop_site_theme_styles']);
        add_filter('print_scripts_array', [static::class, 'drop_site_theme_scripts']);
    }

    public static function maybe_isolate_editor($screen): void
    {
        if (!$screen instanceof \WP_Screen || $screen->base !== 'post' || $screen->post_type !== 'enrove_folio_page') {
            return;
        }

        static::isolate_editor();
    }

    /**
     * For an enrove_folio_page edit screen. Runs on current_screen, ahead of
     * the editor settings and of the REST responses the editor preloads.
     */
    public static function isolate_editor(): void
    {
        static::isolate_global_styles();

        add_filter('block_editor_settings_all', [static::class, 'filter_editor_settings'], PHP_INT_MAX);

        // Stylesheets only. Theme editor scripts are left alone: another
        // script may depend on one, and a script left without its dependency
        // breaks the editor. What those scripts register cannot reach a folio's
        // front end anyway.
        add_filter('print_styles_array', [static::class, 'drop_site_theme_styles']);

        // On a block theme the editor rebuilds its canvas styles in the
        // browser from the global styles REST data it was preloaded with. The
        // theme's base styles in that data are already neutral (the resolver
        // filters below apply to the preload too); the Site Editor's saved
        // styles are read straight from their post, so the editor is not told
        // where that post is. It then treats them as empty, and never loads or
        // saves them from this screen.
        add_filter('rest_prepare_theme', [static::class, 'unlink_user_global_styles']);

        // With the site theme gone the canvas has no base of its own.
        add_action('enqueue_block_assets', [static::class, 'enqueue_canvas_base']);
    }

    /**
     * Paper, ink and a sans stack for the canvas, which the folio's fonts
     * replace where its theme loads them into the editor.
     */
    public static function enqueue_canvas_base(): void
    {
        $path = ENROVE_PATH . 'assets/css/folio-editor-canvas.css';
        wp_enqueue_style(
            'enrove-folio-editor-canvas',
            ENROVE_URL . 'assets/css/folio-editor-canvas.css',
            [],
            file_exists($path) ? (string) filemtime($path) : ENROVE_VERSION
        );
    }

    /**
     * Global styles and settings as if the site theme had no theme.json, no
     * editor theme supports, and no Site Editor customisations.
     */
    private static function isolate_global_styles(): void
    {
        add_filter('wp_theme_json_data_theme', [static::class, 'empty_theme_json_data']);
        add_filter('wp_theme_json_data_user', [static::class, 'empty_user_json_data']);
        // A parent theme's theme.json is merged in after the filter above has
        // run on the child's, so it is hidden at the file instead.
        add_filter('theme_file_path', [static::class, 'hide_parent_theme_json'], 10, 2);

        foreach (self::EDITOR_THEME_SUPPORTS as $feature) {
            remove_theme_support($feature);
        }

        // Global styles may already have been resolved and cached this request.
        if (function_exists('wp_clean_theme_json_cache')) {
            wp_clean_theme_json_cache();
        } elseif (class_exists('WP_Theme_JSON_Resolver')) {
            \WP_Theme_JSON_Resolver::clean_cached_data();
        }
    }

    public static function empty_theme_json_data($theme_json)
    {
        return static::empty_json_data($theme_json, 'theme');
    }

    public static function empty_user_json_data($theme_json)
    {
        return static::empty_json_data($theme_json, 'custom');
    }

    private static function empty_json_data($theme_json, string $origin)
    {
        if (!class_exists('WP_Theme_JSON_Data') || !class_exists('WP_Theme_JSON')) {
            return $theme_json;
        }

        return new \WP_Theme_JSON_Data(['version' => \WP_Theme_JSON::LATEST_SCHEMA], $origin);
    }

    public static function hide_parent_theme_json($path, $file)
    {
        if ($file !== 'theme.json' || !is_child_theme() || !is_string($path)) {
            return $path;
        }

        $parent_json = wp_normalize_path(trailingslashit(get_template_directory()) . 'theme.json');

        return wp_normalize_path($path) === $parent_json ? '' : $path;
    }

    /**
     * Drop the site theme's styles from the canvas: its editor styles
     * (non-global "theme" entries) and the Customizer's Additional CSS (the
     * non-global "user" entry). The global entries are kept; they are core's
     * defaults now.
     */
    public static function filter_editor_settings($settings)
    {
        if (!is_array($settings) || empty($settings['styles']) || !is_array($settings['styles'])) {
            return $settings;
        }

        $settings['styles'] = array_values(array_filter($settings['styles'], function ($style) {
            $type = is_array($style) ? ($style['__unstableType'] ?? '') : '';
            if (($type === 'theme' || $type === 'user') && empty($style['isGlobalStyles'])) {
                return false;
            }
            return true;
        }));

        return $settings;
    }

    public static function unlink_user_global_styles($response)
    {
        if ($response instanceof \WP_REST_Response) {
            $response->remove_link('https://api.w.org/user-global-styles');
        }

        return $response;
    }

    public static function drop_site_theme_styles($handles)
    {
        return static::without_site_theme_handles($handles, wp_styles());
    }

    public static function drop_site_theme_scripts($handles)
    {
        return static::without_site_theme_handles($handles, wp_scripts());
    }

    /**
     * The handles whose source is not a file in the active theme's folder or
     * its parent's. A small stylesheet core has inlined (wp_maybe_inline_styles())
     * has lost its src by print time, so its stored file path is checked too.
     */
    private static function without_site_theme_handles($handles, \WP_Dependencies $dependencies)
    {
        if (!is_array($handles)) {
            return $handles;
        }

        $url_roots = array_unique([
            static::strip_scheme(trailingslashit(get_stylesheet_directory_uri())),
            static::strip_scheme(trailingslashit(get_template_directory_uri())),
        ]);
        $path_roots = array_unique([
            wp_normalize_path(trailingslashit(get_stylesheet_directory())),
            wp_normalize_path(trailingslashit(get_template_directory())),
        ]);

        return array_values(array_filter($handles, function ($handle) use ($dependencies, $url_roots, $path_roots) {
            $src = isset($dependencies->registered[$handle]) ? $dependencies->registered[$handle]->src : '';
            if (is_string($src) && $src !== '' && static::starts_with_any(static::strip_scheme($src), $url_roots)) {
                return false;
            }

            $path = $dependencies->get_data($handle, 'path');
            if (is_string($path) && $path !== '' && static::starts_with_any(wp_normalize_path($path), $path_roots)) {
                return false;
            }

            return true;
        }));
    }

    private static function starts_with_any(string $subject, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (strpos($subject, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    private static function strip_scheme(string $url): string
    {
        return (string) preg_replace('#^(?:https?:)?//#i', '', $url);
    }
}
