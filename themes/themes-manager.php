<?php
namespace Enrove\Themes;

use Enrove\Modules\Assets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Themes_Manager
 *
 * Central registry for the folio themes the plugin ships in themes/.
 *
 * Themes are built-in only. Installing a theme from an uploaded .zip was
 * removed in 0.5.1: it copied the package's PHP into wp-content/enrove-themes/,
 * and WordPress.org does not allow a plugin to write executable code there.
 * Third-party themes will come back through a route that does not write files.
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

    /**
     * Theme folders that did not register this request, and why.
     *
     * Request-scoped, because the loaders are. Nothing is persisted: the guards
     * run on every request anyway, so a cache would be a database read
     * replacing a free in-memory append, with no honest way to invalidate it —
     * theme folders are files, and nothing fires a hook when one is edited.
     *
     * Records are data, never prose. register_defaults() runs from
     * Plugin::__construct(), which is called at file scope and therefore before
     * plugins_loaded — so the 'enrove' text domain is not loaded yet, and __()
     * here would return English and trip _load_textdomain_just_in_time on
     * WordPress 6.7+, on every request. describe_skipped_theme() builds the
     * sentences instead, and only admin code calls it.
     *
     * @var array[]
     */
    private static $skipped = [];

    // ── Boot ───────────────────────────────────────────────────────────────

    /**
     * Register all built-in themes.
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
     * @return bool  False when the theme declares no usable ID.
     */
    public static function register($cover_class, $page_class)
    {
        $theme_id = $cover_class::get_id();

        // get_id() is sanitize_title(get_name()), and get_name() falls back to
        // '' when setup.php declares no name — which no loader guard catches,
        // because they check cover_class and page_class and not the one key the
        // ID is derived from. Registering under '' is worse than not
        // registering: has('') then answers true while
        // resolve_registered_theme_id() treats '' as "never assigned" and
        // returns the default, so the two disagree about the same theme, and
        // get_all_themes() puts a nameless entry in every picker.
        if ($theme_id === '') {
            return false;
        }

        // Two theme folders claiming one ID — two `name`s that sanitize_title()
        // to the same thing. The later one wins. What was missing was any sign
        // of it: the displaced theme vanished from the picker with nothing
        // said, and every folio storing that ID quietly rendered as something
        // else. The record is filed against the theme that lost, because that
        // is the one someone is looking for and cannot find.
        if (isset(self::$registry[$theme_id]) && self::$registry[$theme_id]['cover_class'] !== $cover_class) {
            static::record_skipped_theme($theme_id, 'builtin', 'shadowed', [
                'class' => self::$registry[$theme_id]['cover_class'],
            ]);
        }

        self::$registry[$theme_id] = [
            'cover_class' => $cover_class,
            'page_class' => $page_class,
        ];

        return true;
    }

    /**
     * Note a theme folder that did not register.
     *
     * Called at the point of the decision, so the record and the verdict cannot
     * disagree — which is the reason this is not a second scan the admin screen
     * runs for itself. Free on a healthy site: the append only happens on a
     * branch that was already going to skip, and that branch is never taken.
     *
     * @param string $folder Folder name, never an absolute path.
     * @param string $kind   'builtin' (the only kind since package installs were removed).
     * @param string $reason Stable code; describe_skipped_theme() turns it into words.
     * @param array  $detail Raw context for the sentence — a class name, a file.
     */
    protected static function record_skipped_theme(string $folder, string $kind, string $reason, array $detail = []): void
    {
        self::$skipped[] = [
            'folder' => $folder,
            'kind' => $kind,
            'reason' => $reason,
            'detail' => $detail,
        ];
    }

    /**
     * Every theme folder that did not register this request.
     *
     * @param string|null $kind 'builtin', or null for every record.
     * @return array[]
     */
    public static function get_skipped_themes(?string $kind = null): array
    {
        if ($kind === null) {
            return self::$skipped;
        }

        return array_values(array_filter(self::$skipped, static function ($record) use ($kind) {
            return $record['kind'] === $kind;
        }));
    }

    /**
     * Turn one record into the two things a person can act on: what went wrong,
     * and what to do about it.
     *
     * Split in two — message is the fault, data is the fix.
     *
     * Lives here rather than in the loaders because this is where __() is safe.
     *
     * @return \WP_Error Code is the reason; message the fault; data the fix.
     */
    public static function describe_skipped_theme(array $record): \WP_Error
    {
        $reason = (string) ($record['reason'] ?? '');
        $detail = (array) ($record['detail'] ?? []);
        $file = (string) ($detail['file'] ?? '');
        $class = (string) ($detail['class'] ?? '');
        $key = (string) ($detail['key'] ?? '');
        $dep = (string) ($detail['dependency'] ?? '');

        switch ($reason) {
            case 'no_setup':
                return new \WP_Error($reason,
                    __('No setup.php in this folder.', 'enrove-folios'),
                    __('A theme needs setup.php, cover.php and page.php side by side. The full spec ships at themes/README.md.', 'enrove-folios'));

            case 'unreadable_file':
                return new \WP_Error($reason,
                    sprintf(/* translators: %s: a theme file name */ __('%s is missing, or cannot be read.', 'enrove-folios'), $file),
                    __('Restore the file, or check the web server is allowed to read it.', 'enrove-folios'));

            case 'setup_not_array':
                return new \WP_Error($reason,
                    __('setup.php does not return an array.', 'enrove-folios'),
                    __('It must be a literal return array( … ); with no side effects — it is included more than once per request.', 'enrove-folios'));

            case 'no_class_names':
                return new \WP_Error($reason,
                    __('setup.php declares no cover_class and page_class.', 'enrove-folios'),
                    __('Each is a fully-qualified class name, namespace included, declared in cover.php and page.php respectively.', 'enrove-folios'));

            case 'missing_dependency':
                return new \WP_Error($reason,
                    sprintf(/* translators: %s: the dependency path declared in setup.php */ __('setup.php lists the dependency "%s", which is not there.', 'enrove-folios'), $dep),
                    __('Every dependency a theme declares has to ship inside it. The theme is skipped rather than registered, because a dependency that is missing is a fatal on the first folio anyone opens.', 'enrove-folios'));

            case 'class_missing':
                return new \WP_Error($reason,
                    sprintf(/* translators: 1: a theme file name, 2: the class setup.php names */ __('%1$s declares no class %2$s.', 'enrove-folios'), $file, $class),
                    __('Check the name in setup.php against the class in that file, including the namespace.', 'enrove-folios'));

            case 'not_base_theme':
                return new \WP_Error($reason,
                    sprintf(/* translators: 1: setup.php key, 2: the class name */ __('%1$s names %2$s, which does not extend Base_Theme.', 'enrove-folios'), $key, $class),
                    __('Both view classes must extend Base_Theme, or the theme cannot register.', 'enrove-folios'));

            case 'shadowed':
                return new \WP_Error($reason,
                    __('Another theme folder uses this ID and has taken it over.', 'enrove-folios'),
                    __('Rename one of the two in its setup.php — the ID is derived from the name.', 'enrove-folios'));
        }

        return new \WP_Error('unknown', __('This folder did not register.', 'enrove-folios'), '');
    }

    /**
     * Does this folder claim to be a theme at all?
     *
     * Same four markers, and the same reasoning, as enrove_check_theme_dir() in
     * bin/check-theme-contract.php: "does it have a setup.php" cannot be the
     * test, because a folder missing one is exactly the failure worth naming.
     * Anything carrying one of the four is making a claim; anything else under
     * themes/ is not a broken theme, it is not a theme — an unzipped __MACOSX,
     * a stray node_modules — and reporting it would be noise.
     *
     * Four filenames repeated rather than shared: reaching into bin/ from the
     * boot path, on every request, costs more than the duplication. Change both.
     */
    protected static function folder_claims_to_be_a_theme(string $dir): bool
    {
        foreach (['setup.php', 'cover.php', 'page.php', 'assets/css/theme.css'] as $marker) {
            if (is_readable(trailingslashit($dir) . $marker)) {
                return true;
            }
        }

        return false;
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
     * A registered theme's declared password-gate colours.
     *
     * Same shape as get_theme_default_fonts() below, and for the same caller:
     * the gate has a theme ID and no theme instance. An unregistered ID returns
     * nothing and the gate uses its own defaults, which is also what happens
     * for a theme that declares no gate block.
     *
     * @param string $theme_id
     * @return array{accent?:string, accent_hover?:string, background?:string}
     */
    public static function get_theme_gate_colors(string $theme_id): array
    {
        if (!static::has($theme_id)) {
            return [];
        }

        $cover_class = self::$registry[$theme_id]['cover_class'];

        return $cover_class::get_gate_colors();
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
     * A theme opts in by shipping themes/<slug>/sample-content.php. The file
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
            $label = __('Create with sample content', 'enrove-folios');
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
        $fallback = __('A New Folio', 'enrove-folios');

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
            $file = ENROVE_PATH . 'pexels/sets.php';
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
     * Wording for a feature key a theme declared in its setup.php.
     *
     * The vocabulary lives here rather than in the themes so that two themes
     * offering the same thing say the same thing — and so the words are
     * translatable, which a string inside an installed theme package is not.
     * A key outside the vocabulary is shown as the theme wrote it, trimmed and
     * stripped of markup: a theme is free to name something the plugin has
     * never heard of, it just does not get a translation.
     *
     * @param string $key Feature key from Base_Theme::get_features().
     * @return string  Label to show, or '' if there is nothing to show.
     */
    public static function feature_label(string $key): string
    {
        $labels = array(
            'blocks' => __('Content blocks', 'enrove-folios'),
            'dynamic-color' => __('Dynamic color', 'enrove-folios'),
            'light-dark' => __('Light and dark', 'enrove-folios'),
            'page-transitions' => __('Page transitions', 'enrove-folios'),
        );

        if (isset($labels[$key])) {
            return $labels[$key];
        }

        $label = trim(wp_strip_all_tags($key));

        return mb_substr($label, 0, 32);
    }

    /**
     * Absolute path of a curated Pexels placeholder, by manifest slug.
     *
     * The photo is either bundled (a development checkout) or downloaded into
     * uploads from Settings → Imagery (a WordPress.org install, which may not
     * carry Pexels photos). \Enrove\Pexels\Library knows both places.
     *
     * @param string $slug
     * @return string  Empty when the slug is unusable or the photo is on neither.
     */
    public static function sample_image_path(string $slug): string
    {
        return \Enrove\Pexels\Library::path($slug);
    }

    /**
     * Public URL of a curated Pexels placeholder, by manifest slug.
     *
     * The photo may not be on this site yet. The URL is emitted regardless:
     * sample content is stored once, at seed time, so a URL that resolves later
     * means downloading the photos fills in imagery for folios that were already
     * created. A missing file is a broken <img>, never a fatal.
     *
     * @param string $slug
     * @return string
     */
    public static function sample_image_url(string $slug): string
    {
        return esc_url_raw(\Enrove\Pexels\Library::url($slug));
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
        if ($slug === '' || !class_exists('\Enrove\Pexels\Credits')) {
            return '';
        }

        $credit = trim((string) \Enrove\Pexels\Credits::render($slug, 'caption'));

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
        if ($slug === '' || !class_exists('\Enrove\Pexels\Credits')) {
            return '';
        }

        $credit = trim((string) \Enrove\Pexels\Credits::render($slug, 'inline'));
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
        $theme_id = static::resolve_registered_theme_id($theme_id);
        if ($theme_id === null) {
            return null;
        }
        $class = self::$registry[$theme_id]['cover_class'];
        return new $class();
    }

    /**
     * Resolve a stored theme ID to a key the registry answers to, or null.
     *
     * Two cases that used to resolve identically, and must not:
     *
     * An **empty** ID is a folio that was never assigned one — created by
     * wp-cli, an import, or anything but the Add New screen. That is a legitimate
     * state, so it resolves to the configured default and then to whatever
     * registered first. Preferring the default is new; "first registered" alone
     * meant the setting was ignored for exactly the folios that had no other
     * answer.
     *
     * An ID that is **set but unregistered** is a broken folio, not a
     * defaulted one. It used to take the same fallback, so the folio rendered
     * in whichever theme happened to sort first — HTTP 200, a page that looks
     * finished, nothing logged, and no way for the author to discover that the
     * theme they wrote never loaded. Returning null is what lets the
     * "Theme not found" notice in folio-preview-template.php run at all; with
     * the fallback in place that branch was unreachable on any site with a
     * working theme, which is every site, since five ship built in.
     *
     * @param string $theme_id
     * @return string|null
     */
    public static function resolve_registered_theme_id($theme_id)
    {
        $theme_id = (string) $theme_id;

        if ($theme_id !== '') {
            return static::has($theme_id) ? $theme_id : null;
        }

        $default_id = (string) get_option('enrove_default_theme_id', '');
        if ($default_id !== '' && static::has($default_id)) {
            return $default_id;
        }

        reset(self::$registry);
        $first_id = key(self::$registry);

        // Registry is empty — no themes installed at all.
        return $first_id !== null ? (string) $first_id : null;
    }

    /**
     * Instantiate the inner-page theme class for a given theme ID.
     *
     * @param string $theme_id
     * @return Base_Theme|null
     */
    public static function create_page_theme($theme_id)
    {
        $theme_id = static::resolve_registered_theme_id($theme_id);
        if ($theme_id === null) {
            return null;
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
        $id = \Enrove\Utils\Utils::get_enrove_post_id();
        $post_type = \Enrove\Utils\Utils::get_enrove_post_type();

        // No folio context (e.g. admin pages not related to a folio).
        if (!$id) {
            return null;
        }

        $requested_post = get_post($id);
        if (
            !$requested_post ||
            !\Enrove\Utils\Utils::is_enrove_post($requested_post) ||
            !\Enrove\Utils\Utils::can_current_request_view_post($requested_post)
        ) {
            return null;
        }

        if ($post_type === 'enrove_folio_page') {
            $folio_id = null;

            // Prefer URL-based resolution: the folio slug in the path is
            // always correct, even when the page's folio_id meta is stale
            // (e.g. after duplication + deletion of the source folio).
            $current_path = \Enrove\Utils\Utils::get_current_path();
            $base_slug = \Enrove\Utils\Utils::get_folio_base_slug();
            $pattern = '#^/' . preg_quote($base_slug, '#') . '/([^/]+)/page/#';
            if (preg_match($pattern, $current_path, $matches)) {
                $folio_slug = rtrim($matches[1], '/');
                $folio_post = \Enrove\Utils\Utils::get_enrove_post_by_post_type_and_post_name('enrove_folio', $folio_slug);
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
            $folio_post->post_type !== 'enrove_folio' ||
            !\Enrove\Utils\Utils::can_current_request_view_post($folio_post)
        ) {
            return null;
        }

        $meta = get_post_meta($folio_id);
        $theme_id = $meta['theme_id'][0] ?? '';

        if ($post_type === 'enrove_folio') {
            $is_cover_enabled = \Enrove\Utils\Utils::is_folio_cover_enabled($folio_id);
            if (!$is_cover_enabled) {
                $first_page_id = \Enrove\Utils\Utils::get_first_folio_page_id($folio_id);
                if ($first_page_id > 0) {
                    $first_page_url = \Enrove\Utils\Utils::get_folio_permalink_by_id($first_page_id);
                    if (!empty($first_page_url)) {
                        wp_safe_redirect($first_page_url, 302);
                        exit;
                    }
                }
            }
            return static::create_cover_theme($theme_id);
        }

        if ($post_type === 'enrove_folio_page') {
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
        $id = \Enrove\Utils\Utils::get_enrove_post_id();
        if (!$id) {
            return null;
        }

        $post = get_post($id);
        if (!$post || !\Enrove\Utils\Utils::is_enrove_post($post)) {
            return null;
        }

        // Resolve to the parent folio if the request targets a folio page.
        $folio_post = $post;
        if ($post->post_type === 'enrove_folio_page') {
            $folio_id = (int) get_post_meta($id, 'folio_id', true);
            $folio_post = $folio_id ? get_post($folio_id) : null;
        }

        if ($folio_post && post_password_required($folio_post)) {
            return $folio_post;
        }

        return null;
    }

    /**
     * Discover built-in themes shipped inside plugin /themes/.
     * A valid theme folder must contain cover.php and page.php.
     */
    public static function load_builtin_themes(): void
    {
        $themes_path = trailingslashit(ENROVE_PATH . 'themes');
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

            $folder = basename($dir);

            // A folder carrying none of a theme's four files is not a broken
            // theme, it is not a theme — an unzipped __MACOSX, a stray
            // node_modules. Skipped without a word, because naming it would be
            // noise in a report about themes that failed.
            if (!static::folder_claims_to_be_a_theme($dir)) {
                continue;
            }

            // is_readable, not file_exists, for setup.php as well as the two
            // view files. A setup.php that is present but unreadable passed
            // this guard, and the include below then emitted a PHP warning on
            // every request before the is_array() check caught it one line
            // later. Same outcome, minus the warning — and is_readable already
            // implies existence, so nothing that used to load stops loading.
            //
            // Tested one file at a time so the report can name which is absent.
            if (!is_readable($setup_file)) {
                static::record_skipped_theme($folder, 'builtin', 'no_setup');
                continue;
            }
            foreach (['cover.php' => $cover_file, 'page.php' => $page_file] as $name => $path) {
                if (!is_readable($path)) {
                    static::record_skipped_theme($folder, 'builtin', 'unreadable_file', ['file' => $name]);
                    continue 2;
                }
            }

            $setup = include $setup_file;
            if (!is_array($setup)) {
                static::record_skipped_theme($folder, 'builtin', 'setup_not_array');
                continue;
            }
            if (empty($setup['cover_class']) || empty($setup['page_class'])) {
                static::record_skipped_theme($folder, 'builtin', 'no_class_names');
                continue;
            }

            // A declared dependency that is not on disk used to be skipped in
            // silence and the theme registered anyway. That is the worst of the
            // failures here, because it is the only one that does not look like
            // a failure: dependencies declare functions, not classes, so
            // cover.php still parses, class_exists() is still true, the theme
            // still appears in the picker — and the first visitor to a folio
            // using it gets a fatal on an undefined function. A theme that
            // cannot render is better left out of the picker than offered.
            if (!empty($setup['dependencies']) && is_array($setup['dependencies'])) {
                $missing_dependency = '';
                foreach ($setup['dependencies'] as $dep) {
                    $dep_file = trailingslashit($dir) . $dep;
                    if (!is_readable($dep_file)) {
                        $missing_dependency = (string) $dep;
                        break;
                    }
                    require_once $dep_file;
                }
                if ($missing_dependency !== '') {
                    static::record_skipped_theme($folder, 'builtin', 'missing_dependency', ['dependency' => $missing_dependency]);
                    continue;
                }
            }

            require_once $cover_file;
            require_once $page_file;

            $cover_class = $setup['cover_class'];
            $page_class = $setup['page_class'];

            foreach (['cover.php' => $cover_class, 'page.php' => $page_class] as $name => $class) {
                if (!class_exists($class)) {
                    static::record_skipped_theme($folder, 'builtin', 'class_missing', ['file' => $name, 'class' => $class]);
                    continue 2;
                }
            }

            foreach (['cover_class' => $cover_class, 'page_class' => $page_class] as $key => $class) {
                if (!is_subclass_of($class, Base_Theme::class)) {
                    static::record_skipped_theme($folder, 'builtin', 'not_base_theme', ['key' => $key, 'class' => $class]);
                    continue 2;
                }
            }

            static::register($cover_class, $page_class);
        }
    }

    /**
     * Run one-time migrations for theme data.
     * Fixes folios using legacy 'theme-1' IDs.
     */
    public static function run_migrations()
    {
        if (get_option('enrove_theme_migration_v1')) {
            return;
        }

        global $wpdb;

        // Map old legacy IDs — numeric slugs used before v0.1.10, and any stale
        // slug-based IDs that might have been stored before the renaming.
        $mapping = [
            'theme-1' => 'folio-starter',
            'theme-2' => 'enrove-ebook',
        ];

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- One-time bulk rewrite of legacy theme_id meta values across all posts, guarded by the option above; core has no API for it and there is nothing to cache.
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
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

        update_option('enrove_theme_migration_v1', time());
    }
}
