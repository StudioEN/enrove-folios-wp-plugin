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

        // Require explicitly — the autoloader won't find these new sub-namespaced classes
        // automatically since they don't follow the flat namespace->path convention.
        $themes_path = GROOVE_PATH . 'themes/';
        require_once $themes_path . 'folio-starter/cover.php';
        require_once $themes_path . 'folio-starter/page.php';
        require_once $themes_path . 'groove-ebook/cover.php';
        require_once $themes_path . 'groove-ebook/page.php';

        static::register(Folio_Starter\Cover::class, Folio_Starter\Page::class);
        static::register(Groove_Ebook\Cover::class, Groove_Ebook\Page::class);
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

        if ($post_type === 'groove_folio_page') {
            $meta = get_post_meta($id);
            $folio_id = $meta['folio_id'][0] ?? null;

            if (!$folio_id) {
                $current_path = \Groove\Utils\Utils::get_current_path();
                if (preg_match('/^\/folio\/([^\/]+)\/page\//', $current_path, $matches)) {
                    $folio_slug = rtrim($matches[1], '/');
                    $folio_post = \Groove\Utils\Utils::get_groove_post_by_post_type_and_post_name('groove_folio', $folio_slug);
                    if ($folio_post) {
                        $folio_id = $folio_post->ID;
                    }
                }
            }
        } else {
            $folio_id = $id;
        }

        if (!$folio_id) {
            return null;
        }

        $meta = get_post_meta($folio_id);
        $theme_id = $meta['theme_id'][0] ?? '';

        if ($post_type === 'groove_folio') {
            return static::create_cover_theme($theme_id);
        }

        if ($post_type === 'groove_folio_page') {
            // Guard: if no folio_id was found (orphaned page), do not redirect.
            if (empty($folio_id)) {
                return null;
            }
            $post_password_required = post_password_required($folio_id);
            if ($post_password_required) {
                $folio_url = \Groove\Utils\Utils::get_folio_permalink_by_id($folio_id);
                if ($folio_url) {
                    wp_safe_redirect($folio_url, 302);
                    exit;
                }
                return null; // No valid URL — render nothing rather than redirect to home.
            }
            return static::create_page_theme($theme_id);
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
            $cover_file = $themes_dir . $theme_id . '/cover.php';
            $page_file = $themes_dir . $theme_id . '/page.php';

            if (!is_readable($cover_file) || !is_readable($page_file)) {
                continue; // Package deleted from disk — skip silently.
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
        $info_file = static::find_file_in_dir($tmp_dir, 'theme-info.json');

        if (!$info_file) {
            static::cleanup_dir($tmp_dir);
            return new \WP_Error('missing_info', 'theme-info.json not found in the package.');
        }

        $package_root = dirname($info_file) . '/';

        // 3. Parse and validate theme-info.json.
        $info = json_decode(file_get_contents($info_file), true);

        if (!isset($info['name']) || '' === trim($info['name'])) {
            static::cleanup_dir($tmp_dir);
            return new \WP_Error('invalid_info', 'theme-info.json must contain a "name" field.');
        }

        if (!file_exists($package_root . 'cover.php') || !file_exists($package_root . 'page.php')) {
            static::cleanup_dir($tmp_dir);
            return new \WP_Error('missing_files', 'Package must contain cover.php and page.php.');
        }

        // 4. Derive the theme ID from the name.
        $theme_name = sanitize_text_field($info['name']);
        $theme_id = sanitize_title($theme_name);

        // 5. Extract class names declared in cover.php / page.php (text scan, no eval).
        $cover_class = static::extract_class_name($package_root . 'cover.php');
        $page_class = static::extract_class_name($package_root . 'page.php');

        if (!$cover_class || !$page_class) {
            static::cleanup_dir($tmp_dir);
            return new \WP_Error('bad_class', 'Could not detect PHP class names in cover.php / page.php.');
        }

        // 6. Move to the permanent themes directory.
        $dest = static::get_themes_dir() . $theme_id . '/';
        wp_mkdir_p($dest);
        copy_dir($package_root, $dest);
        static::cleanup_dir($tmp_dir);

        // 7. Persist metadata.
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

        // 8. Register immediately for the current request.
        require_once $dest . 'cover.php';
        require_once $dest . 'page.php';

        if (class_exists($cover_class) && class_exists($page_class)) {
            static::register($cover_class, $page_class);
        }

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
            'theme-1' => Folio_Starter\Cover::get_id(), // folio-starter
            'theme-2' => Groove_Ebook\Cover::get_id(), // groove-ebook
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
     * Regex-extract the first class name (with namespace) declared in a PHP file.
     * Text-scan only — the file is never evaluated.
     *
     * @param string $file
     * @return string|null
     */
    private static function extract_class_name(string $file): ?string
    {
        $source = file_get_contents($file);
        if (preg_match('/^\s*(?:namespace\s+([\w\\\\]+);)?.*?^\s*class\s+(\w+)/ms', $source, $m)) {
            $namespace = rtrim($m[1] ?? '', '\\');
            $class = $m[2];
            return $namespace ? $namespace . '\\' . $class : $class;
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
