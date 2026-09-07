<?php
namespace Groove\Themes;

use Groove\Modules\Assets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Themes_Manager
 *
 * Central registry for all managed (built-in and installed) themes.
 * To add a new built-in theme, call static::register() from register_defaults().
 * To install a theme package uploaded via the admin UI, call install_theme_from_zip().
 *
 * @since 1.0.0
 */
class Themes_Manager extends Assets
{

    /**
     * Registered themes keyed by theme ID.
     * Each entry: [ 'cover_class' => string, 'page_class' => string ]
     *
     * @var array
     */
    private static $registry = [];

    // ── Boot ───────────────────────────────────────────────────────────────

    /**
     * Register all built-in themes and load any installed packages.
     * Called once from Plugin::__construct().
     */
    public static function register_defaults()
    {
        // Guard: only run once per request.
        static $initialized = false;
        if ($initialized) {
            return;
        }
        $initialized = true;

        static::load_builtin_themes();
        static::load_installed_themes();
        static::run_migrations();
    }

    // ── Core registry ──────────────────────────────────────────────────────

    /**
     * Register a theme. The ID is derived automatically from the cover class.
     *
     * To add a new built-in managed theme:
     *   1. Create theme-{slug}.php      extending Base_Theme (cover page).
     *   2. Create theme-page-{slug}.php extending Base_Theme (inner pages).
     *   3. Implement get_name(), get_thumbnail_filename(),
     *      get_cover_filename(), and get_logo_filename() in the cover class.
     *   4. Add one line here: static::register( My_Theme::class, My_Theme_Page::class );
     *
     * @param string $cover_class  Fully-qualified class name for the folio cover.
     * @param string $page_class   Fully-qualified class name for folio inner pages.
     */
    public static function register($cover_class, $page_class)
    {
        $theme_id = $cover_class::get_id();
        self::$registry[$theme_id] = [
            'cover_class' => $cover_class,
            'page_class' => $page_class,
        ];
    }

    /**
     * Unregister a theme by ID.
     *
     * @param string $theme_id
     */
    public static function unregister($theme_id)
    {
        unset(self::$registry[$theme_id]);
    }

    /**
     * Check whether a theme ID is registered.
     *
     * @param string $theme_id
     * @return bool
     */
    public static function has($theme_id)
    {
        return isset(self::$registry[$theme_id]);
    }

    /**
     * Get the descriptor array for every registered theme (built-in + installed).
     * Delegates to each cover class's static get_theme_descriptor().
     *
     * @return array  Keyed by theme ID.
     */
    public static function get_all_themes()
    {
        $themes = [];
        foreach (self::$registry as $theme_id => $entry) {
            $cover_class = $entry['cover_class'];
            $themes[$theme_id] = $cover_class::get_theme_descriptor();
        }
        return $themes;
    }

    /**
     * A registered theme's declared typeface defaults.
     *
     * For callers that only have a theme ID (the password gate, the block
     * editor) rather than a live theme instance.
     *
     * @param string $theme_id
     * @return array Role => ['css_stack' => string, 'google_family' => string]
     */
    public static function get_theme_default_fonts(string $theme_id): array
    {
        if (!static::has($theme_id)) {
            return [];
        }

        $cover_class = self::$registry[$theme_id]['cover_class'];

        return $cover_class::get_default_fonts();
    }

    // ── Sample content ─────────────────────────────────────────────────────

    /**
     * Normalised sample-content definition for a registered theme.
     *
     * A theme opts in by shipping themes/<slug>/sample-content.php (built-in) or
     * <groove-themes>/<slug>/sample-content.php (installed package). The file
     * returns an array; anything missing or malformed is treated as "no sample
     * content" rather than an error, so a half-finished theme cannot break the
     * Add New screen.
     *
     * Returned shape:
     *   [
     *     'label'       => string,  Checkbox label on the Add New screen.
     *     'description' => string,  Helper copy beneath the checkbox.
     *     'subtitle'    => string,  Written to the folio's `subtitle` meta.
     *     'folio_meta'  => array,   Extra folio meta, key => scalar.
     *     'pages'       => array,   [ ['title','content','feature_image'], … ]
     *   ]
     *
     * @param string $theme_id
     * @return array|null  Null when the theme ships no sample content.
     */
    public static function get_sample_content(string $theme_id): ?array
    {
        static $cache = [];

        if ($theme_id === '' || !isset(self::$registry[$theme_id]['cover_class'])) {
            return null;
        }

        if (array_key_exists($theme_id, $cache)) {
            return $cache[$theme_id];
        }

        $cache[$theme_id] = null;

        $cover_class = self::$registry[$theme_id]['cover_class'];
        if (!is_subclass_of($cover_class, Base_Theme::class)) {
            return null;
        }

        $definition = $cover_class::get_sample_content_data();
        if (!is_array($definition) || empty($definition['pages']) || !is_array($definition['pages'])) {
            return null;
        }

        $pages = [];
        foreach ($definition['pages'] as $page) {
            if (!is_array($page)) {
                continue;
            }
            $title = isset($page['title']) ? trim((string) $page['title']) : '';
            if ($title === '') {
                continue;
            }
            $pages[] = [
                'title' => $title,
                'content' => isset($page['content']) ? (string) $page['content'] : '',
                // Optional pool slug used for the page's featured image.
                'feature_image' => isset($page['feature_image']) ? sanitize_key((string) $page['feature_image']) : '',
            ];
        }

        if (empty($pages)) {
            return null;
        }

        $folio_meta = [];
        if (!empty($definition['folio_meta']) && is_array($definition['folio_meta'])) {
            foreach ($definition['folio_meta'] as $meta_key => $meta_value) {
                if (!is_string($meta_key) || $meta_key === '' || !is_scalar($meta_value)) {
                    continue;
                }
                $folio_meta[$meta_key] = $meta_value;
            }
        }

        $label = isset($definition['label']) ? trim((string) $definition['label']) : '';
        if ($label === '') {
            $label = __('Create with sample content', 'groove');
        }

        $cache[$theme_id] = [
            'label' => $label,
            'description' => isset($definition['description']) ? trim((string) $definition['description']) : '',
            'subtitle' => isset($definition['subtitle']) ? trim((string) $definition['subtitle']) : '',
            'folio_meta' => $folio_meta,
            'pages' => $pages,
        ];

        return $cache[$theme_id];
    }

    /**
     * Whether a theme offers seedable sample content.
     *
     * @param string $theme_id
     * @return bool
     */
    public static function has_sample_content(string $theme_id): bool
    {
        return static::get_sample_content($theme_id) !== null;
    }

    /**
     * Title a new folio should take for a given theme.
     *
     * Only consulted when the site has not set an explicit default folio title;
     * an explicit setting always wins. Unknown or silent themes fall back to the
     * generic title so this never returns an empty string.
     *
     * @param string $theme_id
     * @return string
     */
    public static function get_default_folio_title(string $theme_id): string
    {
        $fallback = __('A New Folio', 'groove');

        if ($theme_id === '' || !isset(self::$registry[$theme_id]['cover_class'])) {
            return $fallback;
        }

        $cover_class = self::$registry[$theme_id]['cover_class'];
        if (!is_subclass_of($cover_class, Base_Theme::class)) {
            return $fallback;
        }

        $title = trim((string) $cover_class::get_default_folio_title());

        return $title === '' ? $fallback : $title;
    }

    // ── Imagery sets ───────────────────────────────────────────────────────

    /**
     * The imagery set a theme seeds placeholder content from.
     *
     * @param string $theme_id
     * @return string  Set slug, or '' when the theme declares none.
     */
    public static function get_image_set(string $theme_id): string
    {
        if ($theme_id === '' || !isset(self::$registry[$theme_id]['cover_class'])) {
            return '';
        }

        $cover_class = self::$registry[$theme_id]['cover_class'];
        if (!is_subclass_of($cover_class, Base_Theme::class)) {
            return '';
        }

        return sanitize_key((string) $cover_class::get_image_set());
    }

    /**
     * The imagery set definitions, loaded once.
     *
     * @return array  Set slug => definition. Empty when the file is absent.
     */
    protected static function image_sets(): array
    {
        static $sets = null;

        if ($sets === null) {
            $file = GROOVE_PATH . 'pexels/sets.php';
            $sets = is_readable($file) ? (array) require $file : [];
        }

        return $sets;
    }

    /**
     * Resolve a (theme, role) pair to a curated placeholder slug.
     *
     * Sample content asks for the job an image does — the opener, the texture,
     * the third face in a contributor grid — and the theme's set decides what
     * that looks like, so seeding a magazine and seeding an eBook no longer
     * produce the same photographs.
     *
     * Resolution prefers the set's own image, then the shared-pool slug the
     * role replaced, then the set's image again even though it is missing:
     *
     *   1. The set file exists            → use it. The normal case.
     *   2. It does not, but the legacy    → use the legacy one. Covers an
     *      shared-pool file does            install curated before sets existed,
     *                                       where the alternative is a broken
     *                                       image for content that used to work.
     *   3. Neither exists                 → return the set slug anyway, so a
     *                                       later curation run fills in imagery
     *                                       for folios already created.
     *
     * @param string $theme_id
     * @param string $role     Role slug declared by the theme's set.
     * @return string  Slug, or '' when the theme or role is unknown.
     */
    public static function sample_image_slug(string $theme_id, string $role): string
    {
        $role = sanitize_key($role);
        $set = static::get_image_set($theme_id);

        if ($set === '' || $role === '') {
            return '';
        }

        $sets = static::image_sets();
        if (!isset($sets[$set]['roles'][$role])) {
            return '';
        }

        $slug = $set . '-' . $role;
        if (is_readable(static::sample_image_path($slug))) {
            return $slug;
        }

        $legacy = isset($sets[$set]['roles'][$role]['legacy'])
            ? sanitize_key((string) $sets[$set]['roles'][$role]['legacy'])
            : '';

        if ($legacy !== '' && is_readable(static::sample_image_path($legacy))) {
            return $legacy;
        }

        return $slug;
    }

    /**
     * Public URL of the image a theme uses for a role.
     *
     * @param string $theme_id
     * @param string $role
     * @return string
     */
    public static function theme_image_url(string $theme_id, string $role): string
    {
        return static::sample_image_url(static::sample_image_slug($theme_id, $role));
    }

    /**
     * A core/image <figcaption> crediting the image a theme uses for a role.
     *
     * @param string $theme_id
     * @param string $role
     * @return string  Caption HTML, or ''.
     */
    public static function theme_image_caption(string $theme_id, string $role): string
    {
        return static::sample_image_caption(static::sample_image_slug($theme_id, $role));
    }

    /**
     * The same credit as a bare line, for a theme block that prints its own
     * element around it.
     *
     * theme_image_caption() returns a <figcaption> because core/image expects
     * one. A theme block with a `credit` attribute expects the opposite: the
     * text only, because the block already has a place to put it.
     *
     * @param string $theme_id
     * @param string $role
     * @return string  Credit HTML with no wrapper element, or ''.
     */
    public static function theme_image_credit(string $theme_id, string $role): string
    {
        return static::sample_image_credit_line(static::sample_image_slug($theme_id, $role));
    }

    /**
     * Absolute path of a curated Pexels placeholder, by manifest slug.
     *
     * @param string $slug
     * @return string  Empty when the slug is not a usable filename.
     */
    public static function sample_image_path(string $slug): string
    {
        $slug = sanitize_key($slug);
        return $slug === '' ? '' : GROOVE_PATH . 'assets/images/pexels/' . $slug . '.jpg';
    }

    /**
     * Public URL of a curated Pexels placeholder, by manifest slug.
     *
     * The file is downloaded by the curation script and may not exist yet. The
     * URL is emitted regardless: sample content is stored once, at seed time, so
     * a URL that resolves later means running the curator fills in imagery for
     * folios that were already created. A missing file is a broken <img>, never
     * a fatal.
     *
     * @param string $slug
     * @return string
     */
    public static function sample_image_url(string $slug): string
    {
        $slug = sanitize_key($slug);
        return $slug === '' ? '' : esc_url_raw(GROOVE_URL . 'assets/images/pexels/' . $slug . '.jpg');
    }

    /**
     * Photographer credit for a placeholder slug, ready for a block caption.
     *
     * Degrades to an empty string whenever the Pexels manifest is absent (the
     * class is only present once the integration ships, and credits.json only
     * once the curator has run). Callers MUST omit the caption element entirely
     * on an empty return rather than emit "Photo by  on Pexels".
     *
     * @param string $slug
     * @return string  Caption HTML, or ''.
     */
    public static function sample_image_credit(string $slug): string
    {
        $slug = sanitize_key($slug);
        if ($slug === '' || !class_exists('\Groove\Pexels\Credits')) {
            return '';
        }

        $credit = trim((string) \Groove\Pexels\Credits::render($slug, 'caption'));

        // The credit is stored in post_content, so run it through the same
        // filter WordPress applies to post bodies.
        return $credit === '' ? '' : wp_kses_post($credit);
    }

    /**
     * A core/image <figcaption> carrying the photographer credit, or ''.
     *
     * Credits::render($slug, 'caption') already returns a <figcaption>, so its
     * opening tag is rewritten to the class core/image serialises rather than
     * nested inside a second one — a stray class on that element makes the
     * editor flag seeded blocks as containing unexpected content.
     *
     * @param string $slug
     * @return string
     */
    public static function sample_image_caption(string $slug): string
    {
        $credit = static::sample_image_credit($slug);
        if ($credit === '') {
            return '';
        }

        if (stripos($credit, '<figcaption') === 0) {
            return (string) preg_replace(
                '#^<figcaption\b[^>]*>#i',
                '<figcaption class="wp-block-image__caption">',
                $credit,
                1
            );
        }

        return '<figcaption class="wp-block-image__caption">' . $credit . '</figcaption>';
    }

    /**
     * "Photo by X on Pexels" with its links but no wrapper element.
     *
     * Credits::render() always wraps its text — a <figcaption> or a <span>,
     * depending on context — and a block that stores this in an attribute is
     * going to print an element of its own around it. The wrapper is unwrapped
     * here rather than nested: the attribute is edited as rich text, where a
     * stray outer span is something an author can half-delete, and a
     * half-deleted wrapper is worse than no wrapper at all. The anchors stay,
     * because the Pexels licence asks for them.
     *
     * @param string $slug
     * @return string  Credit HTML, or '' when the manifest has no record.
     */
    public static function sample_image_credit_line(string $slug): string
    {
        $slug = sanitize_key($slug);
        if ($slug === '' || !class_exists('\Groove\Pexels\Credits')) {
            return '';
        }

        $credit = trim((string) \Groove\Pexels\Credits::render($slug, 'inline'));
        if ($credit === '') {
            return '';
        }

        if (preg_match('#^<span\b[^>]*>(.*)</span>$#is', $credit, $matches)) {
            $credit = trim($matches[1]);
        }

        // Stored in post_content, so filtered the way WordPress filters bodies.
        return $credit === '' ? '' : wp_kses_post($credit);
    }

    // ── Theme factories ────────────────────────────────────────────────────

    /**
     * Instantiate the cover theme class for a given theme ID.
     *
     * @param string $theme_id
     * @return Base_Theme|null
     */
    public static function create_cover_theme($theme_id)
    {
        // Fall back to first registered theme when theme_id is empty or unrecognised.
        if (!$theme_id || !static::has($theme_id)) {
            reset(self::$registry);
            $theme_id = key(self::$registry);
        }
        if (!$theme_id) {
            return null; // Registry is empty — no themes installed.
        }
        $class = self::$registry[$theme_id]['cover_class'];
        return new $class();
    }

    /**
     * Instantiate the inner-page theme class for a given theme ID.
     *
     * @param string $theme_id
     * @return Base_Theme|null
     */
    public static function create_page_theme($theme_id)
    {
        // Fall back to first registered theme when theme_id is empty or unrecognised.
        if (!$theme_id || !static::has($theme_id)) {
            reset(self::$registry);
            $theme_id = key(self::$registry);
        }
        if (!$theme_id) {
            return null; // Registry is empty.
        }
        $class = self::$registry[$theme_id]['page_class'];
        return new $class();
    }

    /**
     * Main factory: resolves post type from the current request and returns
     * the right theme instance. Called by folio-preview-template.php.
     *
     * @return Base_Theme|null
     */
    public static function create_theme_for_current_request()
    {
        $id = \Groove\Utils\Utils::get_groove_post_id();
        $post_type = \Groove\Utils\Utils::get_groove_post_type();

        // No folio context (e.g. admin pages not related to a folio).
        if (!$id) {
            return null;
        }

        $requested_post = get_post($id);
        if (
            !$requested_post ||
            !\Groove\Utils\Utils::is_groove_post($requested_post) ||
            !\Groove\Utils\Utils::can_current_request_view_post($requested_post)
        ) {
            return null;
        }

        if ($post_type === 'groove_folio_page') {
            $folio_id = null;

            // Prefer URL-based resolution: the folio slug in the path is
            // always correct, even when the page's folio_id meta is stale
            // (e.g. after duplication + deletion of the source folio).
            $current_path = \Groove\Utils\Utils::get_current_path();
            $base_slug = \Groove\Utils\Utils::get_folio_base_slug();
            $pattern = '#^/' . preg_quote($base_slug, '#') . '/([^/]+)/page/#';
            if (preg_match($pattern, $current_path, $matches)) {
                $folio_slug = rtrim($matches[1], '/');
                $folio_post = \Groove\Utils\Utils::get_groove_post_by_post_type_and_post_name('groove_folio', $folio_slug);
                if ($folio_post) {
                    $folio_id = $folio_post->ID;
                }
            }

            // Fall back to folio_id from page meta (admin previews, query-param URLs).
            if (!$folio_id) {
                $meta = get_post_meta($id);
                $folio_id = $meta['folio_id'][0] ?? null;
            }
        } else {
            $folio_id = $id;
        }

        if (!$folio_id) {
            return null;
        }

        $folio_post = get_post($folio_id);
        if (
            !$folio_post ||
            $folio_post->post_type !== 'groove_folio' ||
            !\Groove\Utils\Utils::can_current_request_view_post($folio_post)
        ) {
            return null;
        }

        $meta = get_post_meta($folio_id);
        $theme_id = $meta['theme_id'][0] ?? '';

        if ($post_type === 'groove_folio') {
            $is_cover_enabled = \Groove\Utils\Utils::is_folio_cover_enabled($folio_id);
            if (!$is_cover_enabled) {
                $first_page_id = \Groove\Utils\Utils::get_first_folio_page_id($folio_id);
                if ($first_page_id > 0) {
                    $first_page_url = \Groove\Utils\Utils::get_folio_permalink_by_id($first_page_id);
                    if (!empty($first_page_url)) {
                        wp_safe_redirect($first_page_url, 302);
                        exit;
                    }
                }
            }
            return static::create_cover_theme($theme_id);
        }

        if ($post_type === 'groove_folio_page') {
            // Guard: if no folio_id was found (orphaned page), do not redirect.
            if (empty($folio_id)) {
                return null;
            }
            return static::create_page_theme($theme_id);
        }

        return null;
    }

    /**
     * Returns the folio WP_Post if the current request targets a
     * password-protected folio whose password hasn't been entered yet.
     *
     * @return \WP_Post|null
     */
    public static function get_password_protected_post_for_current_request()
    {
        $id = \Groove\Utils\Utils::get_groove_post_id();
        if (!$id) {
            return null;
        }

        $post = get_post($id);
        if (!$post || !\Groove\Utils\Utils::is_groove_post($post)) {
            return null;
        }

        // Resolve to the parent folio if the request targets a folio page.
        $folio_post = $post;
        if ($post->post_type === 'groove_folio_page') {
            $folio_id = (int) get_post_meta($id, 'folio_id', true);
            $folio_post = $folio_id ? get_post($folio_id) : null;
        }

        if ($folio_post && post_password_required($folio_post)) {
            return $folio_post;
        }

        return null;
    }

    // ── Installed theme management (packages uploaded by admins) ───────────

    /**
     * Absolute path to the directory that holds uploaded theme packages.
     * Lives in wp-content so themes survive plugin updates.
     *
     * @return string  Path with trailing slash.
     */
    public static function get_themes_dir(): string
    {
        return WP_CONTENT_DIR . '/groove-themes/';
    }

    /**
     * Public URL that maps to get_themes_dir().
     *
     * @return string  URL with trailing slash.
     */
    public static function get_themes_url(): string
    {
        return WP_CONTENT_URL . '/groove-themes/';
    }

    /**
     * Return metadata stored in the WordPress option for all installed packages.
     * Does NOT include built-in themes.
     *
     * @return array  [ theme_id => [ 'name', 'version', 'cover_class', 'page_class', ... ] ]
     */
    public static function get_installed_themes_meta(): array
    {
        return get_option('groove_installed_themes', []);
    }

    /**
     * Include the PHP files for every installed package and register them.
     * Called automatically from register_defaults().
     */
    public static function load_installed_themes(): void
    {
        $meta_list = static::get_installed_themes_meta();
        $themes_dir = static::get_themes_dir();

        foreach ($meta_list as $theme_id => $meta) {
            $theme_path = $themes_dir . $theme_id . '/';
            $cover_file = $theme_path . 'cover.php';
            $page_file = $theme_path . 'page.php';
            $setup_file = $theme_path . 'setup.php';

            if (!is_readable($cover_file) || !is_readable($page_file)) {
                continue; // Package deleted from disk — skip silently.
            }

            if (is_readable($setup_file)) {
                $setup = include $setup_file;
                if (!empty($setup['dependencies']) && is_array($setup['dependencies'])) {
                    foreach ($setup['dependencies'] as $dep) {
                        $dep_file = $theme_path . $dep;
                        if (is_readable($dep_file)) {
                            require_once $dep_file;
                        }
                    }
                }
            }

            require_once $cover_file;
            require_once $page_file;

            $cover_class = $meta['cover_class'] ?? '';
            $page_class = $meta['page_class'] ?? '';

            if (class_exists($cover_class) && class_exists($page_class)) {
                static::register($cover_class, $page_class);
            }
        }
    }

    /**
     * Discover built-in themes shipped inside plugin /themes/.
     * A valid theme folder must contain cover.php and page.php.
     */
    public static function load_builtin_themes(): void
    {
        $themes_path = trailingslashit(GROOVE_PATH . 'themes');
        if (!is_dir($themes_path)) {
            return;
        }

        $theme_dirs = glob($themes_path . '*', GLOB_ONLYDIR);
        if (empty($theme_dirs)) {
            return;
        }

        natsort($theme_dirs);

        foreach ($theme_dirs as $dir) {
            $setup_file = trailingslashit($dir) . 'setup.php';
            $cover_file = trailingslashit($dir) . 'cover.php';
            $page_file = trailingslashit($dir) . 'page.php';

            if (!file_exists($setup_file) || !is_readable($cover_file) || !is_readable($page_file)) {
                continue;
            }

            $setup = include $setup_file;
            if (!is_array($setup) || empty($setup['cover_class']) || empty($setup['page_class'])) {
                continue;
            }

            if (!empty($setup['dependencies']) && is_array($setup['dependencies'])) {
                foreach ($setup['dependencies'] as $dep) {
                    $dep_file = trailingslashit($dir) . $dep;
                    if (is_readable($dep_file)) {
                        require_once $dep_file;
                    }
                }
            }

            require_once $cover_file;
            require_once $page_file;

            $cover_class = $setup['cover_class'];
            $page_class = $setup['page_class'];

            if (!class_exists($cover_class) || !class_exists($page_class)) {
                continue;
            }

            if (!is_subclass_of($cover_class, Base_Theme::class) || !is_subclass_of($page_class, Base_Theme::class)) {
                continue;
            }

            static::register($cover_class, $page_class);
        }
    }

    /**
     * Validate, extract, and install a theme ZIP package.
     *
     * @param string $zip_path  Absolute path to the uploaded temporary ZIP file.
     * @return string|\WP_Error  Theme name on success, WP_Error on failure.
     */
    public static function install_theme_from_zip(string $zip_path)
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();

        // 1. Extract to a temp directory.
        $tmp_dir = get_temp_dir() . 'groove-theme-' . uniqid() . '/';
        $result = unzip_file($zip_path, $tmp_dir);

        if (is_wp_error($result)) {
            return $result;
        }

        // 2. Find the package root (may be inside a sub-folder inside the zip).
        $info_file = static::find_file_in_dir($tmp_dir, 'setup.php');

        if (!$info_file) {
            static::cleanup_dir($tmp_dir);
            return new \WP_Error('missing_info', 'setup.php not found in the package.');
        }

        $package_root = dirname($info_file) . '/';

        // 3. Parse and validate setup.php.
        $info = include $info_file;

        if (!is_array($info) || !isset($info['name']) || '' === trim($info['name'])) {
            static::cleanup_dir($tmp_dir);
            return new \WP_Error('invalid_info', 'setup.php must return an array with a "name" field.');
        }

        if (!file_exists($package_root . 'cover.php') || !file_exists($package_root . 'page.php')) {
            static::cleanup_dir($tmp_dir);
            return new \WP_Error('missing_files', 'Package must contain cover.php and page.php.');
        }

        // 4. Derive the theme ID from the name.
        $theme_name = sanitize_text_field($info['name']);
        $theme_id = sanitize_title($theme_name);

        // 5. Read class names declared in setup.php.
        $cover_class = $info['cover_class'] ?? null;
        $page_class = $info['page_class'] ?? null;

        if (!$cover_class || !$page_class) {
            static::cleanup_dir($tmp_dir);
            return new \WP_Error('bad_class', 'setup.php must define cover_class and page_class.');
        }

        $dependencies = [];
        if (!empty($info['dependencies'])) {
            if (!is_array($info['dependencies'])) {
                static::cleanup_dir($tmp_dir);
                return new \WP_Error('bad_dependencies', 'setup.php dependencies must be an array of relative file paths.');
            }
            $dependencies = $info['dependencies'];
        }

        // Prevent fatal class redeclarations when uploading a duplicate/built-in package.
        if (static::has($theme_id) || isset(static::get_installed_themes_meta()[$theme_id])) {
            static::cleanup_dir($tmp_dir);
            return new \WP_Error('theme_exists', 'A theme with this name is already registered.');
        }
        if (class_exists($cover_class, false) || class_exists($page_class, false)) {
            static::cleanup_dir($tmp_dir);
            return new \WP_Error('class_conflict', 'This package declares classes that are already loaded.');
        }

        // 6. Move to the permanent themes directory.
        $dest = static::get_themes_dir() . $theme_id . '/';
        wp_mkdir_p($dest);
        $copy_result = copy_dir($package_root, $dest);
        static::cleanup_dir($tmp_dir);
        if (is_wp_error($copy_result)) {
            static::cleanup_dir($dest);
            return $copy_result;
        }

        // Ensure dependency files are present before loading cover/page classes.
        foreach ($dependencies as $dep) {
            $dep = ltrim((string) $dep, '/\\');
            if ($dep === '' || strpos($dep, '..') !== false) {
                static::cleanup_dir($dest);
                return new \WP_Error('bad_dependency_path', 'A dependency path in setup.php is invalid.');
            }

            $dep_file = $dest . $dep;
            if (!is_readable($dep_file)) {
                static::cleanup_dir($dest);
                return new \WP_Error('missing_dependency', sprintf('Missing dependency file: %s', $dep));
            }

            require_once $dep_file;
        }

        if (!is_readable($dest . 'cover.php') || !is_readable($dest . 'page.php')) {
            static::cleanup_dir($dest);
            return new \WP_Error('missing_files', 'Package must contain readable cover.php and page.php files.');
        }

        // 7. Register immediately for the current request.
        require_once $dest . 'cover.php';
        require_once $dest . 'page.php';

        if (!class_exists($cover_class, false) || !class_exists($page_class, false)) {
            static::cleanup_dir($dest);
            return new \WP_Error('class_not_found', 'cover_class/page_class could not be loaded from this package.');
        }

        static::register($cover_class, $page_class);

        // 8. Persist metadata only after successful load/registration.
        $installed = static::get_installed_themes_meta();
        $installed[$theme_id] = [
            'name' => $theme_name,
            'version' => $info['version'] ?? '1.0.0',
            'description' => $info['description'] ?? '',
            'author' => $info['author'] ?? '',
            'cover_class' => $cover_class,
            'page_class' => $page_class,
        ];
        update_option('groove_installed_themes', $installed);

        return $theme_name;
    }

    /**
     * Delete an installed theme package and unregister it.
     * Built-in themes cannot be uninstalled via this method.
     *
     * @param string $theme_id
     * @return true|\WP_Error
     */
    public static function uninstall_theme(string $theme_id)
    {
        $installed = static::get_installed_themes_meta();

        if (!isset($installed[$theme_id])) {
            return new \WP_Error('not_found', 'That theme is not an installed package.');
        }

        $theme_dir = static::get_themes_dir() . $theme_id . '/';
        if (is_dir($theme_dir)) {
            static::cleanup_dir($theme_dir);
        }

        unset($installed[$theme_id]);
        update_option('groove_installed_themes', $installed);
        static::unregister($theme_id);

        return true;
    }

    /**
     * Run one-time migrations for theme data.
     * Fixes folios using legacy 'theme-1' IDs.
     */
    public static function run_migrations()
    {
        if (get_option('groove_theme_migration_v1')) {
            return;
        }

        global $wpdb;

        // Map old legacy IDs — numeric slugs used before v0.1.10, and any stale
        // slug-based IDs that might have been stored before the renaming.
        $mapping = [
            'theme-1' => 'folio-starter',
            'theme-2' => 'groove-ebook',
        ];

        foreach ($mapping as $old_id => $new_id) {
            $wpdb->update(
                $wpdb->postmeta,
                ['meta_value' => $new_id],
                [
                    'meta_key' => 'theme_id',
                    'meta_value' => $old_id,
                ]
            );
        }

        update_option('groove_theme_migration_v1', time());
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /**
     * Recursively find a file by name inside a directory.
     *
     * @param string $dir
     * @param string $filename
     * @return string|null  Absolute path, or null if not found.
     */
    private static function find_file_in_dir(string $dir, string $filename): ?string
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $file) {
            if ($file->getFilename() === $filename) {
                return $file->getPathname();
            }
        }
        return null;
    }

    /**
     * Recursively delete a directory.
     *
     * @param string $dir
     */
    private static function cleanup_dir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($dir);
    }
}
