<?php
/**
 * Book furniture for the Groove eBook theme.
 *
 * Loaded as a dependency via setup.php.
 *
 * WHAT IS HERE AND WHAT IS DELIBERATELY NOT
 * -----------------------------------------
 * Core Gutenberg already sets a book's running text: paragraphs with drop caps,
 * headings, images with captions, lists, tables, separators, verse, and since
 * 6.3 real footnotes. None of that is repeated here. What core has no shape for
 * is the *furniture* a long-form book puts around its prose, and that is the
 * whole of this file — seven blocks, in the order a book uses them:
 *
 *   epigraph     a borrowed line under a chapter title, with attribution
 *   pull-quote   the book's OWN line, lifted out to break a long column
 *   aside        a short note set apart from the argument
 *   plate        a numbered figure with a caption and a credit
 *   ornament     a section break — the space between scenes, not a rule
 *   summary      what a chapter leaves the reader with
 *   references   works cited / further reading
 *
 * Epigraph and pull-quote look adjacent and are not. An epigraph is someone
 * else's words arriving before the argument: small, indented, attributed. A
 * pull-quote is the author's own words already on the page, set large and
 * unattributed because attributing them to the writer of the page would be
 * absurd. Collapsing the two into one block with a toggle would save a file and
 * lose the distinction that makes either of them worth setting.
 *
 * CONTENTS RAIL
 * -------------
 * Page::display_catalogs() and Page::display_mobile_nav() build both tables of
 * contents by walking TOP-LEVEL core/heading blocks only. Nothing here is a
 * core/heading, so none of these blocks can put a phantom entry in the rail —
 * which is why section titles stay core headings and no block in this kit
 * offers to own one.
 *
 * @package Groove
 */

use Groove\Themes\Font_Loader;
use Groove\Themes\Theme_Blocks;
use Groove\Themes\Groove_Ebook\Cover;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The kit, in one place.
 *
 * Folder names under blocks/, which are also the block slugs and the suffix of
 * each editor script handle. Read by the enqueue at the foot of this file, so
 * adding a block means adding one line here rather than another fifteen-line
 * wp_enqueue_script(). Registration stays explicit per block: attributes and
 * render callbacks are the part that genuinely differs.
 */
const GROOVE_EBOOK_BLOCKS = [
    'epigraph',
    'pull-quote',
    'aside',
    'plate',
    'ornament',
    'summary',
    'references',
];

// ── Block category ──────────────────────────────────────────────────────────

add_filter('block_categories_all', function (array $categories): array {
    array_unshift($categories, [
        'slug'  => 'groove-ebook',
        'title' => __('Groove eBook', 'groove-folios'),
    ]);
    return $categories;
});

// ── Epigraph ────────────────────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('groove-ebook/epigraph', [
        'api_version'     => 3,
        'attributes'      => [
            'text'        => ['type' => 'string', 'default' => 'We are what we repeatedly do. Excellence, then, is not an act but a habit.'],
            'attribution' => ['type' => 'string', 'default' => 'Will Durant'],
        ],
        'render_callback' => 'groove_ebook_render_epigraph',
    ]);
});

function groove_ebook_render_epigraph(array $attributes): string
{
    $text        = wp_kses_post($attributes['text'] ?? '');
    $attribution = wp_kses_post($attributes['attribution'] ?? '');

    if (trim(wp_strip_all_tags($text)) === '') {
        return '';
    }

    $out = '<figure class="ge-epigraph">'
        . '<blockquote class="ge-epigraph__text">' . $text . '</blockquote>';

    // The em dash lives in CSS, not here: an author who types their own dash
    // into the field would otherwise get two, and the mark is decoration.
    if (trim(wp_strip_all_tags($attribution)) !== '') {
        $out .= '<figcaption class="ge-epigraph__attribution">' . $attribution . '</figcaption>';
    }

    return $out . '</figure>';
}

// ── Pull quote ──────────────────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('groove-ebook/pull-quote', [
        'api_version'     => 3,
        'attributes'      => [
            'text'  => ['type' => 'string', 'default' => 'Almost nothing I am proud of was made quickly.'],
            'align' => ['type' => 'string', 'default' => 'full'],
        ],
        'render_callback' => 'groove_ebook_render_pull_quote',
    ]);
});

function groove_ebook_render_pull_quote(array $attributes): string
{
    $text  = wp_kses_post($attributes['text'] ?? '');
    $align = ($attributes['align'] ?? 'full') === 'inset' ? 'inset' : 'full';

    if (trim(wp_strip_all_tags($text)) === '') {
        return '';
    }

    return '<figure class="ge-pullquote ge-pullquote--' . esc_attr($align) . '">'
        . '<blockquote class="ge-pullquote__text">' . $text . '</blockquote>'
        . '</figure>';
}

// ── Aside ───────────────────────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('groove-ebook/aside', [
        'api_version'     => 3,
        'attributes'      => [
            'label' => ['type' => 'string', 'default' => 'Note'],
            'body'  => ['type' => 'string', 'default' => 'A short remark that belongs beside the argument rather than inside it.'],
        ],
        'render_callback' => 'groove_ebook_render_aside',
    ]);
});

function groove_ebook_render_aside(array $attributes): string
{
    $label = wp_kses_post($attributes['label'] ?? '');
    $body  = wp_kses_post($attributes['body'] ?? '');

    if (trim(wp_strip_all_tags($body)) === '') {
        return '';
    }

    $out = '<aside class="ge-aside">';
    if (trim(wp_strip_all_tags($label)) !== '') {
        $out .= '<span class="ge-aside__label">' . $label . '</span>';
    }
    return $out . '<div class="ge-aside__body">' . $body . '</div></aside>';
}

// ── Plate ───────────────────────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('groove-ebook/plate', [
        'api_version'     => 3,
        'attributes'      => [
            'url'     => ['type' => 'string', 'default' => ''],
            'id'      => ['type' => 'number'],
            'alt'     => ['type' => 'string', 'default' => ''],
            'label'   => ['type' => 'string', 'default' => 'Fig. 1'],
            'caption' => ['type' => 'string', 'default' => ''],
            'credit'  => ['type' => 'string', 'default' => ''],
            'bleed'   => ['type' => 'boolean', 'default' => false],
        ],
        'render_callback' => 'groove_ebook_render_plate',
    ]);
});

function groove_ebook_render_plate(array $attributes): string
{
    $url = esc_url_raw((string) ($attributes['url'] ?? ''));
    if ($url === '') {
        return '';
    }

    $alt     = (string) ($attributes['alt'] ?? '');
    $label   = wp_kses_post($attributes['label'] ?? '');
    $caption = wp_kses_post($attributes['caption'] ?? '');
    $credit  = wp_kses_post($attributes['credit'] ?? '');
    $bleed   = !empty($attributes['bleed']);

    $classes = 'ge-plate' . ($bleed ? ' ge-plate--bleed' : '');

    $out = '<figure class="' . esc_attr($classes) . '">'
        . '<img class="ge-plate__image" src="' . esc_url($url) . '"'
        . ' alt="' . esc_attr($alt) . '" loading="lazy" decoding="async" />';

    $has_label   = trim(wp_strip_all_tags($label)) !== '';
    $has_caption = trim(wp_strip_all_tags($caption)) !== '';
    $has_credit  = trim(wp_strip_all_tags($credit)) !== '';

    if ($has_label || $has_caption || $has_credit) {
        $out .= '<figcaption class="ge-plate__caption">';
        if ($has_label) {
            $out .= '<span class="ge-plate__label">' . $label . '</span>';
        }
        if ($has_caption) {
            $out .= '<span class="ge-plate__text">' . $caption . '</span>';
        }
        if ($has_credit) {
            $out .= '<span class="ge-plate__credit">' . $credit . '</span>';
        }
        $out .= '</figcaption>';
    }

    return $out . '</figure>';
}

// ── Ornament ────────────────────────────────────────────────────────────────

/**
 * The marks. Each is decoration with a separator role on the wrapper, so the
 * glyph itself is hidden from assistive technology — "asterisk asterisk
 * asterisk" is noise, and the break is already announced by the role.
 */
const GROOVE_EBOOK_ORNAMENTS = [
    'asterism' => "\u{2042}",
    'stars'    => "* * *",
    'dots'     => "\u{00B7} \u{00B7} \u{00B7}",
    'rule'     => '',
    'blank'    => '',
];

add_action('init', function () {
    register_block_type('groove-ebook/ornament', [
        'api_version'     => 3,
        'attributes'      => [
            'mark' => ['type' => 'string', 'default' => 'asterism'],
        ],
        'render_callback' => 'groove_ebook_render_ornament',
    ]);
});

function groove_ebook_render_ornament(array $attributes): string
{
    $mark = (string) ($attributes['mark'] ?? 'asterism');
    if (!array_key_exists($mark, GROOVE_EBOOK_ORNAMENTS)) {
        $mark = 'asterism';
    }

    $glyph = GROOVE_EBOOK_ORNAMENTS[$mark];

    $out = '<div class="ge-ornament ge-ornament--' . esc_attr($mark) . '" role="separator">';
    if ($glyph !== '') {
        $out .= '<span class="ge-ornament__mark" aria-hidden="true">' . esc_html($glyph) . '</span>';
    }
    return $out . '</div>';
}

// ── Chapter summary ─────────────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('groove-ebook/summary', [
        'api_version'     => 3,
        'attributes'      => [
            'title'  => ['type' => 'string', 'default' => 'What this chapter argued'],
            'points' => [
                'type'    => 'array',
                'default' => [
                    'Skill moves out of the head and into the hands, and that takes repetition.',
                    'Speed is not the opposite of care; hurry is.',
                    'A deadline is a constraint, not a verdict on the work.',
                ],
                'items'   => ['type' => 'string'],
            ],
        ],
        'render_callback' => 'groove_ebook_render_summary',
    ]);
});

function groove_ebook_render_summary(array $attributes): string
{
    $title  = wp_kses_post($attributes['title'] ?? '');
    $points = $attributes['points'] ?? [];

    if (!is_array($points)) {
        $points = [];
    }

    $points = array_values(array_filter(array_map(static function ($point): string {
        return wp_kses_post((string) $point);
    }, $points), static function (string $point): bool {
        return trim(wp_strip_all_tags($point)) !== '';
    }));

    if (empty($points)) {
        return '';
    }

    // h3 rather than h2: h2 is the chapter's own section level, and this box is
    // subordinate to whichever section it closes. It is still a real heading —
    // a screen reader should be able to jump to it.
    $out = '<section class="ge-summary">';
    if (trim(wp_strip_all_tags($title)) !== '') {
        $out .= '<h3 class="ge-summary__title">' . $title . '</h3>';
    }

    // role="list" survives the `list-style: none` in theme.css — Safari drops
    // list semantics when the marker is removed.
    $out .= '<ul class="ge-summary__points" role="list">';
    foreach ($points as $point) {
        $out .= '<li class="ge-summary__point">' . $point . '</li>';
    }

    return $out . '</ul></section>';
}

// ── References ──────────────────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('groove-ebook/references', [
        'api_version'     => 3,
        'attributes'      => [
            'title'   => ['type' => 'string', 'default' => 'Further reading'],
            'entries' => [
                'type'    => 'array',
                'default' => [
                    ['author' => 'Sennett, Richard', 'work' => 'The Craftsman', 'note' => 'Yale University Press, 2008', 'url' => ''],
                    ['author' => 'Pye, David', 'work' => 'The Nature and Art of Workmanship', 'note' => 'Cambridge University Press, 1968', 'url' => ''],
                    ['author' => 'Sudjic, Deyan', 'work' => 'The Language of Things', 'note' => 'Penguin, 2008', 'url' => ''],
                ],
                'items'   => [
                    'type'       => 'object',
                    'properties' => [
                        'author' => ['type' => 'string'],
                        'work'   => ['type' => 'string'],
                        'note'   => ['type' => 'string'],
                        'url'    => ['type' => 'string'],
                    ],
                ],
            ],
        ],
        'render_callback' => 'groove_ebook_render_references',
    ]);
});

function groove_ebook_render_references(array $attributes): string
{
    $title   = wp_kses_post($attributes['title'] ?? '');
    $entries = $attributes['entries'] ?? [];

    if (!is_array($entries) || empty($entries)) {
        return '';
    }

    $rows = '';
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $author = wp_kses_post($entry['author'] ?? '');
        $work   = wp_kses_post($entry['work'] ?? '');
        $note   = wp_kses_post($entry['note'] ?? '');
        $url    = esc_url_raw((string) ($entry['url'] ?? ''));

        if (trim(wp_strip_all_tags($author . $work . $note)) === '') {
            continue;
        }

        $row = '<li class="ge-references__entry">';
        if (trim(wp_strip_all_tags($author)) !== '') {
            $row .= '<span class="ge-references__author">' . $author . '</span>';
        }
        if (trim(wp_strip_all_tags($work)) !== '') {
            $cite = '<cite class="ge-references__work">' . $work . '</cite>';
            $row .= $url !== ''
                ? '<a class="ge-references__link" href="' . esc_url($url) . '">' . $cite . '</a>'
                : $cite;
        }
        if (trim(wp_strip_all_tags($note)) !== '') {
            $row .= '<span class="ge-references__note">' . $note . '</span>';
        }
        $rows .= $row . '</li>';
    }

    if ($rows === '') {
        return '';
    }

    $out = '<section class="ge-references">';
    if (trim(wp_strip_all_tags($title)) !== '') {
        $out .= '<h3 class="ge-references__title">' . $title . '</h3>';
    }

    return $out . '<ol class="ge-references__list" role="list">' . $rows . '</ol></section>';
}

// ── Editor assets ───────────────────────────────────────────────────────────

add_action('enqueue_block_editor_assets', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'groove_folio_page') {
        return;
    }

    $base_url = groove_ebook_blocks_url() . 'blocks/';

    foreach (GROOVE_EBOOK_BLOCKS as $slug) {
        $path = __DIR__ . '/blocks/' . $slug . '/index.js';

        wp_enqueue_script(
            'groove-ebook-block-' . $slug,
            $base_url . $slug . '/index.js',
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
            file_exists($path) ? (string) filemtime($path) : GROOVE_VERSION,
            true
        );
        wp_set_script_translations('groove-ebook-block-' . $slug, 'groove-folios');
    }

    // Editor typography, through the same resolver the front end uses, so the
    // canvas previews the fonts this folio will actually render with — and
    // only when the folio really is a Groove eBook.
    groove_ebook_enqueue_editor_fonts();
});

/**
 * Load the edited folio's fonts into the block editor canvas.
 *
 * Skips entirely unless the page being edited belongs to a folio using this
 * theme, so editing a folio built on any other theme costs no font request.
 */
function groove_ebook_enqueue_editor_fonts(): void
{
    $page = get_post();
    if (!$page || $page->post_type !== 'groove_folio_page') {
        return;
    }

    if (Theme_Blocks::get_editor_theme_id($page) !== Cover::get_id()) {
        return;
    }
    $folio_id = Theme_Blocks::get_editor_folio_id($page);

    $resolved = Font_Loader::resolve($folio_id, Cover::get_default_fonts());

    Font_Loader::enqueue($resolved, 'groove-ebook-editor-fonts', Font_Loader::EDITOR_SELECTOR);
}

/**
 * Resolve the URL to this theme's folder.
 *
 * Works for both the plugin-bundled copy and a package installed into
 * wp-content/groove-themes/, which plugin_dir_url() alone would get wrong.
 */
function groove_ebook_blocks_url(): string
{
    static $url;
    if ($url !== null) {
        return $url;
    }

    $theme_path  = wp_normalize_path(trailingslashit(__DIR__));
    $content_dir = wp_normalize_path(trailingslashit(WP_CONTENT_DIR));

    if (strpos($theme_path, $content_dir) === 0) {
        $relative = ltrim(substr($theme_path, strlen($content_dir)), '/');
        $url = trailingslashit(WP_CONTENT_URL) . $relative;
    } else {
        $url = trailingslashit(plugin_dir_url(__FILE__));
    }

    return $url;
}
