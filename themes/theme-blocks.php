<?php
namespace Enrove\Themes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Theme_Blocks
 *
 * Keeps each folio page's block inserter to the blocks its folio's theme can
 * style. A block belongs to a theme when its namespace is that theme's ID
 * (`enrove-proposal/timeline`); every theme's blocks.php is loaded on every
 * request, so without this a Magazine page offers Proposal pricing tables that
 * render unstyled.
 *
 * Other themes' blocks are hidden from the inserter, not unregistered: a page
 * that already holds one — after a theme switch — still opens and edits it,
 * under a note saying which theme it came from and that this one leaves it
 * unstyled. The Change theme dialog warns about the same loss before a switch.
 *
 * The hiding happens in the editor, on the blocks.registerBlockType hook,
 * rather than through allowed_block_types_all. That filter takes an allow
 * list, and the only list PHP can build is the server registry, which would
 * silently drop every block registered from JavaScript alone.
 *
 * @since 0.5.1
 */
class Theme_Blocks
{
    const SCRIPT_HANDLE = 'enrove-block-scope';

    public static function register(): void
    {
        // Ahead of the themes' own enqueues (priority 10) so the filter is in
        // place before their registerBlockType() calls run.
        add_action('enqueue_block_editor_assets', [static::class, 'enqueue_editor_scope'], 5);
    }

    /**
     * The theme that owns a block, or '' when no registered theme does.
     *
     * @param string   $block_name e.g. 'enrove-proposal/timeline'.
     * @param string[] $theme_ids  Registered theme IDs.
     */
    public static function get_owner(string $block_name, array $theme_ids): string
    {
        $slash = strpos($block_name, '/');
        if ($slash === false) {
            return '';
        }

        $namespace = substr($block_name, 0, $slash);

        return in_array($namespace, $theme_ids, true) ? $namespace : '';
    }

    /**
     * The folio a page in the block editor belongs to: its folio_id meta. The
     * editor has no folio path to read, and the meta is set when post-new.php
     * creates the page from an Add Page link (Contents\FolioPage\Content), so
     * nothing is read from the editor's URL.
     */
    public static function get_editor_folio_id(\WP_Post $page): int
    {
        return (int) get_post_meta($page->ID, 'folio_id', true);
    }

    /**
     * The theme the page's folio renders in, or '' when it cannot be told.
     * Resolved as the front end resolves it, so a folio with no theme_id
     * takes the default theme here too, and one naming an unregistered
     * theme gets ''.
     */
    public static function get_editor_theme_id(\WP_Post $page): string
    {
        $folio_id = static::get_editor_folio_id($page);
        if ($folio_id <= 0) {
            return '';
        }

        return (string) Themes_Manager::resolve_registered_theme_id(get_post_meta($folio_id, 'theme_id', true));
    }

    public static function enqueue_editor_scope(): void
    {
        $page = get_post();
        if (!$page || $page->post_type !== 'enrove_folio_page') {
            return;
        }

        // A page whose folio cannot be found keeps every block: hiding the
        // lot would leave nothing to insert, and guessing would hide the wrong ones.
        $theme_id = static::get_editor_theme_id($page);
        if ($theme_id === '') {
            return;
        }

        $themes = Themes_Manager::get_all_themes();
        $current_name = (string) ($themes[$theme_id]['name'] ?? $theme_id);

        // Other theme ID => the note shown on its blocks in this page's editor.
        $notes = [];
        foreach ($themes as $other_id => $theme) {
            $other_id = (string) $other_id;
            if ($other_id === $theme_id) {
                continue;
            }
            $notes[$other_id] = sprintf(
                /* translators: 1: the block's theme, e.g. Enrove Proposal; 2: this folio's theme */
                __('%1$s block: %2$s doesn\'t style it, so readers see it unstyled.', 'enrove-folios'),
                (string) ($theme['name'] ?? $other_id),
                $current_name
            );
        }
        if (!$notes) {
            return;
        }

        $path = ENROVE_PATH . 'assets/js/enrove-block-scope.js';
        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            ENROVE_URL . 'assets/js/enrove-block-scope.js',
            ['wp-hooks', 'wp-blocks', 'wp-compose', 'wp-element'],
            file_exists($path) ? (string) filemtime($path) : ENROVE_VERSION,
            true
        );
        wp_add_inline_script(
            self::SCRIPT_HANDLE,
            'window.ENROVE_BLOCK_SCOPE = ' . wp_json_encode(['notes' => $notes], JSON_HEX_TAG | JSON_HEX_AMP) . ';',
            'before'
        );
    }
}
