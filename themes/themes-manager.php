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
     * plugins_loaded — so the 'groove' text domain is not loaded yet, and __()
     * here would return English and trip _load_textdomain_just_in_time on
     * WordPress 6.7+, on every request. describe_skipped_theme() builds the
     * sentences instead, and only admin code calls it.
     *
     * @var array[]
     */
    private static $skipped = [];

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

        // Two themes claiming one ID. The later one wins, which is the same
        // answer an upload gets — a package the operator installed deliberately
        // supersedes what was there — and load order makes "later" mean the
        // installed package, since built-ins register first. What was missing
        // was any sign of it: the displaced theme vanished from the picker with
        // nothing said, and every folio storing that ID quietly rendered as
        // something else.
        //
        // The record is filed against the theme that lost, because that is the
        // one someone is looking for and cannot find. Install refuses this
        // collision outright, so reaching here means the two arrived
        // separately — a plugin update shipping a built-in whose name derives
        // to an ID some installed package already answers to.
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
     * @param string $kind   'builtin' or 'installed'.
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
     * @param string|null $kind 'builtin', 'installed', or null for both.
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
     * Split the way install_theme_from_zip()'s rejections are — message is the
     * fault, data is the fix — and where the fault is one the installer already
     * names, deliberately the same words, so a theme that fails at upload and a
     * theme that fails at load do not describe one problem two ways.
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
                    __('No setup.php in this folder.', 'groove-folios'),
                    __('A theme needs setup.php, cover.php and page.php side by side. The full spec ships at themes/README.md.', 'groove-folios'));

            case 'unreadable_file':
                return new \WP_Error($reason,
                    sprintf(/* translators: %s: a theme file name */ __('%s is missing, or cannot be read.', 'groove-folios'), $file),
                    __('Restore the file, or check the web server is allowed to read it.', 'groove-folios'));

            case 'setup_not_array':
                return new \WP_Error($reason,
                    __('setup.php does not return an array.', 'groove-folios'),
                    __('It must be a literal return array( … ); with no side effects — it is included more than once per request.', 'groove-folios'));

            case 'no_class_names':
                return new \WP_Error($reason,
                    __('setup.php declares no cover_class and page_class.', 'groove-folios'),
                    __('Each is a fully-qualified class name, namespace included, declared in cover.php and page.php respectively.', 'groove-folios'));

            case 'missing_dependency':
                return new \WP_Error($reason,
                    sprintf(/* translators: %s: the dependency path declared in setup.php */ __('setup.php lists the dependency "%s", which is not there.', 'groove-folios'), $dep),
                    __('Every dependency a theme declares has to ship inside it. The theme is skipped rather than registered, because a dependency that is missing is a fatal on the first folio anyone opens.', 'groove-folios'));

            case 'class_missing':
                return new \WP_Error($reason,
                    sprintf(/* translators: 1: a theme file name, 2: the class setup.php names */ __('%1$s declares no class %2$s.', 'groove-folios'), $file, $class),
                    __('Check the name in setup.php against the class in that file, including the namespace.', 'groove-folios'));

            case 'not_base_theme':
                return new \WP_Error($reason,
                    sprintf(/* translators: 1: setup.php key, 2: the class name */ __('%1$s names %2$s, which does not extend Base_Theme.', 'groove-folios'), $key, $class),
                    __('Both view classes must extend Base_Theme, or the theme cannot register.', 'groove-folios'));

            case 'shadowed':
                return new \WP_Error($reason,
                    __('An installed theme package uses this ID and has taken it over.', 'groove-folios'),
                    __('The built-in theme of that name is not available while the package is installed. Remove the package to get it back, or rename one of the two — the ID is derived from the name.', 'groove-folios'));

            case 'package_gone':
                return new \WP_Error($reason,
                    __('The package files are no longer in wp-content/groove-themes/.', 'groove-folios'),
                    __('Remove the theme to clear the entry, then install the package again.', 'groove-folios'));
        }

        return new \WP_Error('unknown', __('This folder did not register.', 'groove-folios'), '');
    }

    /**
     * Does this folder claim to be a theme at all?
     *
     * Same four markers, and the same reasoning, as groove_check_theme_dir() in
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
            $label = __('Create with sample content', 'groove-folios');
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
        $fallback = __('A New Folio', 'groove-folios');

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
            'blocks' => __('Content blocks', 'groove-folios'),
            'dynamic-color' => __('Dynamic color', 'groove-folios'),
            'light-dark' => __('Light and dark', 'groove-folios'),
            'page-transitions' => __('Page transitions', 'groove-folios'),
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
     * carry Pexels photos). \Groove\Pexels\Library knows both places.
     *
     * @param string $slug
     * @return string  Empty when the slug is unusable or the photo is on neither.
     */
    public static function sample_image_path(string $slug): string
    {
        return \Groove\Pexels\Library::path($slug);
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
        return esc_url_raw(\Groove\Pexels\Library::url($slug));
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
    protected static function resolve_registered_theme_id($theme_id)
    {
        $theme_id = (string) $theme_id;

        if ($theme_id !== '') {
            return static::has($theme_id) ? $theme_id : null;
        }

        $default_id = (string) get_option('groove_default_theme_id', '');
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
            // Per iteration, not per loop: left outside, one package missing a
            // dependency would skip every package loaded after it.
            $missing_dependency = '';

            // The row in groove_installed_themes outlives the files, so this
            // is the one skip that leaves a stale entry behind. The report
            // offers to clear it.
            if (!is_readable($cover_file) || !is_readable($page_file)) {
                static::record_skipped_theme((string) $theme_id, 'installed', 'package_gone');
                continue;
            }

            if (is_readable($setup_file)) {
                $setup = include $setup_file;
                if (!empty($setup['dependencies']) && is_array($setup['dependencies'])) {
                    foreach ($setup['dependencies'] as $dep) {
                        $dep_file = $theme_path . $dep;
                        if (!is_readable($dep_file)) {
                            $missing_dependency = (string) $dep;
                            break;
                        }
                        require_once $dep_file;
                    }
                }
            }

            // Same reasoning as the built-in loader above: a package missing a
            // dependency it declares renders as a fatal, not as a broken theme.
            if ($missing_dependency !== '') {
                static::record_skipped_theme((string) $theme_id, 'installed', 'missing_dependency', ['dependency' => $missing_dependency]);
                continue;
            }

            require_once $cover_file;
            require_once $page_file;

            $cover_class = $meta['cover_class'] ?? '';
            $page_class = $meta['page_class'] ?? '';

            foreach (['cover.php' => $cover_class, 'page.php' => $page_class] as $name => $class) {
                if (!class_exists($class)) {
                    static::record_skipped_theme((string) $theme_id, 'installed', 'class_missing', ['file' => $name, 'class' => $class]);
                    continue 2;
                }
            }

            // The same guard load_builtin_themes() has always had, and the one
            // install_theme_from_zip() gained later. Without it this loader
            // takes the whole site down, not just the theme: register() opens
            // with $cover_class::get_id(), which a class that does not reach
            // Base_Theme has no such method for, and the resulting Error is
            // thrown while the plugin file is still being included — before
            // plugins_loaded, with nothing above it to catch anything. Front
            // end, wp-admin, REST and cron go together, so the operator cannot
            // even reach this screen to remove the package that did it.
            //
            // Install validates this now, but that only covers packages
            // installed since. A package already on disk, or one edited in
            // place afterwards, arrives here unchecked.
            foreach (['cover_class' => $cover_class, 'page_class' => $page_class] as $key => $class) {
                if (!is_subclass_of($class, Base_Theme::class)) {
                    static::record_skipped_theme((string) $theme_id, 'installed', 'not_base_theme', ['key' => $key, 'class' => $class]);
                    continue 2;
                }
            }

            static::register($cover_class, $page_class);
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
     * Swap a new version of an installed package into place.
     *
     * The outgoing version's classes are already declared in this request, so
     * the incoming ones cannot be required to check them — PHP does not
     * redeclare a class, and asking it to is a fatal. They are checked
     * statically instead, by the same tokeniser bin/check-theme-contract.php
     * uses, which reads the files rather than executing them.
     *
     * The working copy stays on disk until the new one is in place, so a swap
     * that fails half way leaves the theme the site already had.
     *
     * @return true|\WP_Error
     */
    protected static function replace_installed_package(
        string $package_root,
        string $dest,
        string $theme_id,
        string $cover_class,
        string $page_class,
        array $dependencies
    ) {
        $themes_dir = static::get_themes_dir();
        $staging = $themes_dir . $theme_id . '.incoming-' . uniqid() . '/';

        wp_mkdir_p($staging);
        $copied = copy_dir($package_root, $staging);
        if (is_wp_error($copied)) {
            static::cleanup_dir($staging);
            return $copied;
        }

        $checked = static::check_package_files($staging, $cover_class, $page_class, $dependencies);
        if (is_wp_error($checked)) {
            static::cleanup_dir($staging);
            return $checked;
        }

        $outgoing = $themes_dir . $theme_id . '.outgoing-' . uniqid() . '/';

        // move() is rename() for a directory: both targets are known not to
        // exist, so it neither overwrites nor falls back to a copy.
        $fs = static::local_filesystem();

        if (!$fs->move(untrailingslashit($dest), untrailingslashit($outgoing))) {
            static::cleanup_dir($staging);
            return new \WP_Error(
                'replace_failed',
                __('The installed copy of this theme could not be moved aside.', 'groove-folios'),
                __('Nothing was changed. Check that the web server can write to wp-content/groove-themes/.', 'groove-folios')
            );
        }

        if (!$fs->move(untrailingslashit($staging), untrailingslashit($dest))) {
            // Put the working theme back before reporting anything.
            $fs->move(untrailingslashit($outgoing), untrailingslashit($dest));
            static::cleanup_dir($staging);
            return new \WP_Error(
                'replace_failed',
                __('The new files could not be moved into place.', 'groove-folios'),
                __('The version that was already installed has been left where it was. Check that the web server can write to wp-content/groove-themes/.', 'groove-folios')
            );
        }

        static::cleanup_dir($outgoing);

        return true;
    }

    /**
     * Check an unpacked package without loading any of it.
     *
     * Same three failures install_theme_from_zip() catches by requiring the
     * files — a dependency that is missing or climbs out of the folder, a view
     * file that declares a different class than setup.php names, and a class
     * that does not reach Base_Theme — reported with the same codes and the
     * same wording, so a replace and a fresh install describe one problem the
     * same way. Requiring is not available on a replace: the outgoing version
     * holds those class names already.
     *
     * @return true|\WP_Error
     */
    protected static function check_package_files(
        string $dir,
        string $cover_class,
        string $page_class,
        array $dependencies
    ) {
        foreach ($dependencies as $dep) {
            $dep = ltrim((string) $dep, '/\\');

            if ($dep === '' || strpos($dep, '..') !== false) {
                return new \WP_Error(
                    'bad_dependency_path',
                    sprintf(
                        /* translators: %s: the offending dependency path */
                        __('setup.php lists the dependency "%s", which is empty or climbs out of the theme folder.', 'groove-folios'),
                        $dep
                    ),
                    __('Dependency paths are relative to the theme folder and may not contain "..".', 'groove-folios')
                );
            }

            if (!is_readable($dir . $dep)) {
                return new \WP_Error(
                    'missing_dependency',
                    sprintf(
                        /* translators: %s: the dependency path declared in setup.php */
                        __('setup.php lists the dependency "%s", which is not in the package.', 'groove-folios'),
                        $dep
                    ),
                    __('Every dependency a theme declares has to ship inside it.', 'groove-folios')
                );
            }
        }

        if (!is_readable($dir . 'cover.php') || !is_readable($dir . 'page.php')) {
            return new \WP_Error(
                'missing_files',
                __('cover.php and page.php copied across but cannot be read.', 'groove-folios'),
                __('This is a file-permission problem on the server rather than anything wrong with the package.', 'groove-folios')
            );
        }

        // No checker on disk means no static check. That is a reason to let the
        // replace through, not to fail it: the loaders guard both class
        // contracts on the next request, so the worst case is a theme that does
        // not come back rather than one that breaks the site.
        if (!static::load_contract_library()) {
            return true;
        }

        $views = array(
            'cover_class' => array($cover_class, 'cover.php'),
            'page_class' => array($page_class, 'page.php'),
        );

        foreach ($views as $key => $view) {
            list($fqcn, $file) = $view;
            $short = (string) substr(strrchr('\\' . $fqcn, '\\'), 1);
            $declared = groove_declared_classes((string) file_get_contents($dir . $file));

            if ($short === '' || !isset($declared[$short])) {
                return new \WP_Error(
                    'class_not_found',
                    sprintf(
                        /* translators: 1: the file, 2: the class name setup.php names */
                        __('%1$s does not declare %2$s.', 'groove-folios'),
                        $file,
                        $fqcn
                    ),
                    __('Check the names in setup.php against the classes in those files, including the namespace.', 'groove-folios')
                );
            }

            if (!groove_reaches_base_theme($short, untrailingslashit($dir), $dependencies)) {
                return new \WP_Error(
                    'bad_base_class',
                    sprintf(
                        /* translators: 1: setup.php key (cover_class or page_class), 2: the class name */
                        __('%1$s names %2$s, which does not extend Base_Theme.', 'groove-folios'),
                        $key,
                        $fqcn
                    ),
                    __('Both view classes must extend Base_Theme, or the theme cannot register.', 'groove-folios')
                );
            }
        }

        return true;
    }

    /**
     * Make bin/check-theme-contract.php's functions callable from here.
     *
     * The constant is what stops the CLI body running on a WordPress request;
     * see the guard at the foot of that file. Returns false when the script is
     * not on disk, which is not an error worth failing an install over — it
     * ships today, but nothing guarantees a future packaging step keeps it.
     */
    protected static function load_contract_library(): bool
    {
        $checker = rtrim(GROOVE_PATH, '/\\') . '/bin/check-theme-contract.php';

        if (!is_readable($checker)) {
            return false;
        }

        if (!defined('GROOVE_THEME_CONTRACT_LIB')) {
            define('GROOVE_THEME_CONTRACT_LIB', true);
        }
        require_once $checker;

        return function_exists('groove_check_theme_dir')
            && function_exists('groove_contract_slots')
            && function_exists('groove_declared_classes')
            && function_exists('groove_reaches_base_theme');
    }

    /**
     * Run the theme contract checker over an unpacked theme folder.
     *
     * bin/check-theme-contract.php is the de-facto spec — its rules are exactly
     * the ones that break a theme with no error, no warning and no log line.
     * It was CLI-only and globbed this plugin's own themes/ directory, so it
     * could never see an installed package: the one kind of theme whose author
     * is least likely to have a checkout to run it from. Install is the single
     * moment someone is looking at that package, so it runs here.
     *
     * Advisory by design — nothing this returns blocks an install. Two rules
     * cannot be satisfied by a third-party theme at all (the password-gate
     * colour maps are literal arrays in plugin source with no filter to join),
     * and most of the rest describe a theme that works but misbehaves. The hard
     * requirements are the WP_Errors above; these are things to go and fix.
     *
     * @param string $dir Absolute path to the theme folder.
     * @return string[] Warnings, empty when clean or when the checker is absent.
     */
    public static function check_theme_contract(string $dir): array
    {
        $root = rtrim(GROOVE_PATH, '/\\');
        $checker = $root . '/bin/check-theme-contract.php';
        $contract = $root . '/assets/css/folio-contract.css';

        // Both ship today, because there is no packaging step. If one is ever
        // added and strips bin/, an install must still succeed — silence here
        // is the right failure, not a broken upload.
        if (!static::load_contract_library() || !is_readable($contract)) {
            return [];
        }

        $slots = groove_contract_slots((string) file_get_contents($contract));
        $row = groove_check_theme_dir(rtrim($dir, '/\\'), $root, groove_contract_core_slots($slots));

        return empty($row['warn']) ? [] : array_values($row['warn']);
    }

    /**
     * Validate, extract, and install a theme ZIP package.
     *
     * @param string $zip_path  Absolute path to the uploaded temporary ZIP file.
     * @return string|\WP_Error  Theme name on success, WP_Error on failure.
     */
    public static function install_theme_from_zip(string $zip_path, bool $replace = false)
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
            return new \WP_Error(
                'missing_info',
                __('No setup.php in the package.', 'groove-folios'),
                __('It belongs at the top level of the zip, or one folder down. The zip must hold the theme folder, not a loose set of its files.', 'groove-folios')
            );
        }

        $package_root = dirname($info_file) . '/';

        // 3. Parse and validate setup.php.
        $info = include $info_file;

        if (!is_array($info) || !isset($info['name']) || '' === trim($info['name'])) {
            static::cleanup_dir($tmp_dir);
            return new \WP_Error(
                'invalid_info',
                __('setup.php must return an array with a "name".', 'groove-folios'),
                __('The name is what the theme is called everywhere, and the ID every folio stores is derived from it.', 'groove-folios')
            );
        }

        if (!file_exists($package_root . 'cover.php') || !file_exists($package_root . 'page.php')) {
            static::cleanup_dir($tmp_dir);
            return new \WP_Error(
                'missing_files',
                __('No cover.php and page.php beside setup.php.', 'groove-folios'),
                __('All three files are required, and all three must sit in the same folder.', 'groove-folios')
            );
        }

        // 4. Derive the theme ID from the name.
        $theme_name = sanitize_text_field($info['name']);
        $theme_id = sanitize_title($theme_name);

        // 5. Read class names declared in setup.php.
        $cover_class = $info['cover_class'] ?? null;
        $page_class = $info['page_class'] ?? null;

        if (!$cover_class || !$page_class) {
            static::cleanup_dir($tmp_dir);
            return new \WP_Error(
                'bad_class',
                __('setup.php must define cover_class and page_class.', 'groove-folios'),
                __('Each is a fully-qualified class name, namespace included, declared in cover.php and page.php respectively.', 'groove-folios')
            );
        }

        $dependencies = [];
        if (!empty($info['dependencies'])) {
            if (!is_array($info['dependencies'])) {
                static::cleanup_dir($tmp_dir);
                return new \WP_Error(
                    'bad_dependencies',
                    __('setup.php declares dependencies as something other than an array.', 'groove-folios'),
                    __('It takes a list of file paths, each relative to the theme folder.', 'groove-folios')
                );
            }
            $dependencies = $info['dependencies'];
        }

        $installed_meta = static::get_installed_themes_meta();
        $replaces_package = isset($installed_meta[$theme_id]);

        // A built-in cannot be replaced by an upload. Its files live inside the
        // plugin, so a package sharing its ID could only shadow it at
        // registration — and a shipped theme being taken over by an upload is
        // not something to do behind a confirmation dialog.
        if (static::has($theme_id) && !$replaces_package) {
            static::cleanup_dir($tmp_dir);
            return new \WP_Error(
                'theme_exists',
                sprintf(
                    /* translators: %s: theme ID derived from the package's name */
                    __('"%s" is a built-in theme, and this package\'s name derives to the same ID.', 'groove-folios'),
                    $theme_id
                ),
                __('Built-in themes cannot be replaced by an upload. Give this one a different name in setup.php — the ID is derived from the name.', 'groove-folios')
            );
        }

        // Replacing a package the site already has is an update, and the normal
        // way to ship a revision. It is not something to do without asking,
        // though: folios already using the theme change appearance. The caller
        // confirms, then calls back with $replace.
        if ($replaces_package && !$replace) {
            static::cleanup_dir($tmp_dir);
            return new \WP_Error(
                'replace_confirm_required',
                sprintf(
                    /* translators: %s: name of the theme already installed */
                    __('"%s" is already installed, and this package replaces it.', 'groove-folios'),
                    (string) ($installed_meta[$theme_id]['name'] ?? $theme_id)
                ),
                array(
                    'theme_id' => $theme_id,
                    'incoming_name' => $theme_name,
                    'incoming_version' => (string) ($info['version'] ?? '1.0.0'),
                    'existing_name' => (string) ($installed_meta[$theme_id]['name'] ?? $theme_id),
                    'existing_version' => (string) ($installed_meta[$theme_id]['version'] ?? ''),
                )
            );
        }

        // A class already loaded is only a conflict when it belongs to some
        // other theme. On a replace it is the outgoing version of this one,
        // which is exactly what we are here to supersede.
        $own_classes = $replaces_package
            ? array((string) ($installed_meta[$theme_id]['cover_class'] ?? ''), (string) ($installed_meta[$theme_id]['page_class'] ?? ''))
            : array();

        $conflicting = array_values(array_filter(array_unique(array(
            class_exists($cover_class, false) && !in_array($cover_class, $own_classes, true) ? $cover_class : '',
            class_exists($page_class, false) && !in_array($page_class, $own_classes, true) ? $page_class : '',
        ))));

        if ($conflicting) {
            static::cleanup_dir($tmp_dir);
            return new \WP_Error(
                'class_conflict',
                sprintf(
                    /* translators: %s: one or both fully-qualified class names */
                    __('This package declares %s, which is already loaded on this site.', 'groove-folios'),
                    implode(', ', $conflicting)
                ),
                __('Two themes cannot share a class name. Give this one a namespace of its own.', 'groove-folios')
            );
        }

        // 6. Move to the permanent themes directory.
        $dest = static::get_themes_dir() . $theme_id . '/';

        if ($replaces_package) {
            $replaced = static::replace_installed_package(
                $package_root,
                $dest,
                $theme_id,
                $cover_class,
                $page_class,
                $dependencies
            );
            static::cleanup_dir($tmp_dir);

            if (is_wp_error($replaced)) {
                return $replaced;
            }

            // Deliberately not registered. The outgoing version's classes are
            // declared in this request already and PHP will not replace them,
            // so the new files take effect on the next load — which is how a
            // plugin or theme update behaves in WordPress too.
            $installed_meta[$theme_id] = array(
                'name' => $theme_name,
                'version' => $info['version'] ?? '1.0.0',
                'description' => $info['description'] ?? '',
                'author' => $info['author'] ?? '',
                'cover_class' => $cover_class,
                'page_class' => $page_class,
                'contract_warnings' => static::check_theme_contract($dest),
            );
            update_option('groove_installed_themes', $installed_meta);

            return $theme_name;
        }

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
                return new \WP_Error(
                    'bad_dependency_path',
                    sprintf(
                        /* translators: %s: the offending dependency path */
                        __('setup.php lists the dependency "%s", which is empty or climbs out of the theme folder.', 'groove-folios'),
                        $dep
                    ),
                    __('Dependency paths are relative to the theme folder and may not contain "..".', 'groove-folios')
                );
            }

            $dep_file = $dest . $dep;
            if (!is_readable($dep_file)) {
                static::cleanup_dir($dest);
                return new \WP_Error(
                    'missing_dependency',
                    sprintf(
                        /* translators: %s: the dependency path declared in setup.php */
                        __('setup.php lists the dependency "%s", which is not in the package.', 'groove-folios'),
                        $dep
                    ),
                    __('Every dependency a theme declares has to ship inside it.', 'groove-folios')
                );
            }

            require_once $dep_file;
        }

        if (!is_readable($dest . 'cover.php') || !is_readable($dest . 'page.php')) {
            static::cleanup_dir($dest);
            return new \WP_Error(
                'missing_files',
                __('cover.php and page.php copied across but cannot be read.', 'groove-folios'),
                __('This is a file-permission problem on the server rather than anything wrong with the package.', 'groove-folios')
            );
        }

        // 7. Register immediately for the current request.
        require_once $dest . 'cover.php';
        require_once $dest . 'page.php';

        if (!class_exists($cover_class, false) || !class_exists($page_class, false)) {
            static::cleanup_dir($dest);
            return new \WP_Error(
                'class_not_found',
                sprintf(
                    /* translators: 1: cover_class value, 2: page_class value */
                    __('cover.php and page.php loaded, but do not declare %1$s and %2$s.', 'groove-folios'),
                    $cover_class,
                    $page_class
                ),
                __('Check the names in setup.php against the classes in those files, including the namespace.', 'groove-folios')
            );
        }

        // load_builtin_themes() has checked this since it was written; the
        // install path never did. register() opens with $cover_class::get_id(),
        // which a class that does not reach Base_Theme does not have, so a
        // package that got this far used to take the whole request down with a
        // fatal — no toast, no error text, just a white admin-post.php.
        foreach (['cover_class' => $cover_class, 'page_class' => $page_class] as $key => $class) {
            if (!is_subclass_of($class, Base_Theme::class)) {
                static::cleanup_dir($dest);
                return new \WP_Error(
                    'bad_base_class',
                    sprintf(
                        /* translators: 1: setup.php key (cover_class or page_class), 2: the class name */
                        __('%1$s names %2$s, which does not extend Base_Theme.', 'groove-folios'),
                        $key,
                        $class
                    ),
                    __('Both view classes must extend Base_Theme, or the theme cannot register.', 'groove-folios')
                );
            }
        }

        // Everything above is a hard requirement — the package cannot install
        // without it. The contract check is the opposite: advisory, and run
        // here because this is the only moment anyone sees the package. Its
        // rules are the ones that fail in silence, so a theme that trips them
        // installs, renders, and looks fine while ignoring the folio's fonts or
        // shipping no stylesheet at all.
        $contract_warnings = static::check_theme_contract($dest);

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
            // Stored with the theme rather than handed to the next page load.
            // These describe the package, not the upload: they stay true until
            // its files change, and uninstalling clears them with the row. As a
            // transient they were drained by the first render, so a reload lost
            // them and a second admin never saw them at all.
            'contract_warnings' => $contract_warnings,
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
            return new \WP_Error(
                'not_found',
                __('That theme is not an installed package.', 'groove-folios'),
                __('Built-in themes ship with the plugin and cannot be removed from here.', 'groove-folios')
            );
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
        static::local_filesystem()->delete($dir, true);
    }

    /**
     * WordPress's direct filesystem, for the local directories this class
     * stages, swaps and deletes (the temp dir and wp-content/groove-themes/).
     *
     * Deliberately the direct class rather than the global $wp_filesystem:
     * uninstall_theme() never initialises that, and on a site configured for
     * FTP it would route these local paths through a connection that may need
     * credentials. The direct methods are the same unlink/rmdir/rename calls
     * this class made itself before.
     *
     * @return \WP_Filesystem_Direct
     */
    private static function local_filesystem(): \WP_Filesystem_Direct
    {
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

        return new \WP_Filesystem_Direct(null);
    }
}
