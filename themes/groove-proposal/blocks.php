<?php
/**
 * Register custom Gutenberg blocks for the Groove Proposal theme.
 *
 * Loaded as a dependency via setup.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

// ── Block category ──────────────────────────────────────────────────────────

add_filter('block_categories_all', function (array $categories): array {
    array_unshift($categories, [
        'slug'  => 'groove-proposal',
        'title' => __('Groove Proposal', 'groove'),
    ]);
    return $categories;
});

// ── Key Metrics block ───────────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('groove-proposal/key-metrics', [
        'api_version'     => 3,
        'attributes'      => [
            'items' => [
                'type'    => 'array',
                'default' => [
                    ['value' => '150+', 'label' => 'Projects delivered'],
                    ['value' => '98%',  'label' => 'Client satisfaction'],
                    ['value' => '12',   'label' => 'Years in market'],
                ],
                'items'   => [
                    'type'       => 'object',
                    'properties' => [
                        'value' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
        'render_callback' => 'groove_proposal_render_key_metrics',
    ]);
});

/**
 * Server-side render for the Key Metrics block.
 */
function groove_proposal_render_key_metrics(array $attributes): string
{
    $items = $attributes['items'] ?? [];
    if (empty($items)) {
        return '';
    }

    $out = '<div class="gp-metrics">';
    foreach ($items as $item) {
        $value = wp_kses_post($item['value'] ?? '');
        $label = wp_kses_post($item['label'] ?? '');
        $out .= '<div class="gp-metrics__item">'
            . '<span class="gp-metrics__value">' . $value . '</span>'
            . '<span class="gp-metrics__label">' . $label . '</span>'
            . '</div>';
    }
    $out .= '</div>';

    return $out;
}

// ── Timeline block ──────────────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('groove-proposal/timeline', [
        'api_version'     => 3,
        'attributes'      => [
            'phases' => [
                'type'    => 'array',
                'default' => [
                    ['date' => 'Weeks 1–2', 'title' => 'Discovery',                'desc' => 'Stakeholder interviews, competitive audit, and user research to establish the project foundation.'],
                    ['date' => 'Weeks 3–5', 'title' => 'Strategy & architecture',   'desc' => 'Define the roadmap, information architecture, and content strategy based on research findings.'],
                    ['date' => 'Weeks 6–10', 'title' => 'Design & prototyping',     'desc' => 'Visual design, interactive prototyping, and iterative review cycles with your team.'],
                    ['date' => 'Weeks 11–12', 'title' => 'Handoff & launch support', 'desc' => 'Design system documentation, developer handoff, and launch QA.'],
                ],
                'items'   => [
                    'type'       => 'object',
                    'properties' => [
                        'date'  => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'desc'  => ['type' => 'string'],
                    ],
                ],
            ],
        ],
        'render_callback' => 'groove_proposal_render_timeline',
    ]);
});

/**
 * Server-side render for the Timeline block.
 */
function groove_proposal_render_timeline(array $attributes): string
{
    $phases = $attributes['phases'] ?? [];
    if (empty($phases)) {
        return '';
    }

    $count = count($phases);
    $out = '<div class="gp-timeline">';

    foreach ($phases as $i => $phase) {
        $ordinal = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
        $date    = wp_kses_post($phase['date'] ?? '');
        $title   = wp_kses_post($phase['title'] ?? '');
        $desc    = wp_kses_post($phase['desc'] ?? '');
        $is_last = $i === $count - 1;

        $out .= '<div class="gp-timeline__phase">'
            . '<div class="gp-timeline__marker">'
            . '<span class="gp-timeline__ordinal">' . esc_html($ordinal) . '</span>';
        if (!$is_last) {
            $out .= '<span class="gp-timeline__line"></span>';
        }
        $out .= '</div>'
            . '<div class="gp-timeline__body">'
            . '<span class="gp-timeline__date">' . $date . '</span>'
            . '<h4 class="gp-timeline__title">' . $title . '</h4>'
            . '<p class="gp-timeline__desc">' . $desc . '</p>'
            . '</div>'
            . '</div>';
    }

    $out .= '</div>';
    return $out;
}

// ── Pull Quote block ────────────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('groove-proposal/pull-quote', [
        'api_version'     => 3,
        'attributes'      => [
            'text'   => ['type' => 'string', 'default' => 'Working with this team transformed how we think about our product. The strategic clarity they brought was exactly what we needed.'],
            'author' => ['type' => 'string', 'default' => 'Sarah Chen'],
            'role'   => ['type' => 'string', 'default' => 'VP of Product, Acme Inc.'],
        ],
        'render_callback' => 'groove_proposal_render_pull_quote',
    ]);
});

/**
 * Server-side render for the Pull Quote block.
 */
function groove_proposal_render_pull_quote(array $attributes): string
{
    $text   = wp_kses_post($attributes['text'] ?? '');
    $author = wp_kses_post($attributes['author'] ?? '');
    $role   = wp_kses_post($attributes['role'] ?? '');

    if ($text === '') {
        return '';
    }

    $out = '<figure class="gp-quote">'
        . '<blockquote class="gp-quote__text">' . $text . '</blockquote>'
        . '<figcaption class="gp-quote__cite">';
    if ($author !== '') {
        $out .= '<span class="gp-quote__author">' . $author . '</span>';
    }
    if ($role !== '') {
        $out .= '<span class="gp-quote__role">' . $role . '</span>';
    }
    $out .= '</figcaption></figure>';

    return $out;
}

// ── Pricing Table block ─────────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('groove-proposal/pricing-table', [
        'api_version'     => 3,
        'attributes'      => [
            'rows' => [
                'type'    => 'array',
                'default' => [
                    ['name' => 'Discovery & research', 'desc' => 'Stakeholder interviews, competitive audit, user research', 'price' => 12000, 'optional' => false],
                    ['name' => 'Strategy & planning',  'desc' => 'Roadmap, information architecture, content strategy',      'price' => 18000, 'optional' => false],
                    ['name' => 'Design & prototyping',  'desc' => 'Visual design, interactive prototype, design system',     'price' => 24000, 'optional' => false],
                    ['name' => 'Development support',   'desc' => 'Front-end implementation oversight and QA',               'price' => 8000,  'optional' => true],
                ],
                'items'   => [
                    'type'       => 'object',
                    'properties' => [
                        'name'     => ['type' => 'string'],
                        'desc'     => ['type' => 'string'],
                        'price'    => ['type' => 'number'],
                        'optional' => ['type' => 'boolean'],
                    ],
                ],
            ],
            'includeOptional' => ['type' => 'boolean', 'default' => true],
            'currency'        => ['type' => 'string',  'default' => 'USD'],
            'locale'          => ['type' => 'string',  'default' => 'en-US'],
        ],
        'render_callback' => 'groove_proposal_render_pricing_table',
    ]);
});

/**
 * Server-side render for the Pricing Table block.
 */
function groove_proposal_render_pricing_table(array $attributes): string
{
    $rows             = $attributes['rows'] ?? [];
    $include_optional = $attributes['includeOptional'] ?? true;
    $currency         = $attributes['currency'] ?? 'USD';
    $locale           = $attributes['locale'] ?? 'en-US';

    if (empty($rows)) {
        return '';
    }

    $formatter = null;
    if (class_exists('NumberFormatter')) {
        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, 0);
    }

    $format_price = function (int $amount) use ($formatter, $currency): string {
        if ($formatter) {
            return $formatter->formatCurrency($amount, $currency);
        }
        return '$' . number_format($amount);
    };

    $total = 0;
    $out = '<div class="gp-pricing">'
        . '<div class="gp-pricing__header">'
        . '<span class="gp-pricing__col-label">' . esc_html__('Scope', 'groove') . '</span>'
        . '<span class="gp-pricing__col-label gp-pricing__col-label--right">' . esc_html__('Investment', 'groove') . '</span>'
        . '</div>';

    foreach ($rows as $row) {
        $name     = wp_kses_post($row['name'] ?? '');
        $desc     = wp_kses_post($row['desc'] ?? '');
        $price    = (int) ($row['price'] ?? 0);
        $optional = !empty($row['optional']);

        if (!$optional || $include_optional) {
            $total += $price;
        }

        $row_class = 'gp-pricing__row' . ($optional ? ' gp-pricing__row--optional' : '');

        $out .= '<div class="' . esc_attr($row_class) . '">'
            . '<div class="gp-pricing__item">'
            . '<span class="gp-pricing__name">' . $name . ($optional ? ' <em>(optional)</em>' : '') . '</span>'
            . '<span class="gp-pricing__desc">' . $desc . '</span>'
            . '</div>'
            . '<span class="gp-pricing__price">' . esc_html($format_price($price)) . '</span>'
            . '</div>';
    }

    $out .= '<div class="gp-pricing__total">'
        . '<span class="gp-pricing__total-label">' . esc_html__('Total', 'groove') . '</span>'
        . '<span class="gp-pricing__total-value">' . esc_html($format_price($total)) . '</span>'
        . '</div></div>';

    return $out;
}

// ── Team Grid block ────────────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('groove-proposal/team-grid', [
        'api_version'     => 3,
        'attributes'      => [
            'members' => [
                'type'    => 'array',
                'default' => [
                    ['name' => 'Sarah Chen',    'role' => 'Lead Designer',    'bio' => 'Leads design strategy and visual direction across the engagement.', 'photo' => ''],
                    ['name' => 'Marcus Rivera', 'role' => 'Project Manager',  'bio' => 'Ensures timely delivery and clear communication at every milestone.', 'photo' => ''],
                    ['name' => 'Aiko Tanaka',   'role' => 'Senior Developer', 'bio' => 'Oversees technical implementation, performance, and quality assurance.', 'photo' => ''],
                ],
                'items'   => [
                    'type'       => 'object',
                    'properties' => [
                        'name'  => ['type' => 'string'],
                        'role'  => ['type' => 'string'],
                        'bio'   => ['type' => 'string'],
                        'photo' => ['type' => 'string'],
                    ],
                ],
            ],
            'layout'  => ['type' => 'string', 'default' => 'grid'],
            'columns' => ['type' => 'number', 'default' => 3],
        ],
        'render_callback' => 'groove_proposal_render_team_grid',
    ]);
});

/**
 * Server-side render for the Team Grid block.
 */
function groove_proposal_render_team_grid(array $attributes): string
{
    $members = $attributes['members'] ?? [];
    $layout  = $attributes['layout'] ?? 'grid';
    $columns = (int) ($attributes['columns'] ?? 3);

    if (empty($members)) {
        return '';
    }

    $is_list = $layout === 'list';
    $classes = 'gp-team';
    if ($is_list) {
        $classes .= ' gp-team--list';
    } else {
        if ($columns === 2) {
            $classes .= ' gp-team--cols-2';
        }
    }
    $out = '<div class="' . esc_attr($classes) . '">';

    foreach ($members as $member) {
        $name  = wp_kses_post($member['name'] ?? '');
        $role  = wp_kses_post($member['role'] ?? '');
        $bio   = wp_kses_post($member['bio'] ?? '');
        $photo = esc_url($member['photo'] ?? '');

        // Build initials from plain-text name
        $plain_name = wp_strip_all_tags($name);
        $parts = preg_split('/\s+/', trim($plain_name));
        if (count($parts) >= 2) {
            $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
        } elseif (count($parts) === 1 && $parts[0] !== '') {
            $initials = mb_strtoupper(mb_substr($parts[0], 0, 1));
        } else {
            $initials = '?';
        }

        $out .= '<div class="gp-team__card">';

        // Avatar
        $out .= '<div class="gp-team__avatar">';
        if ($photo !== '') {
            $out .= '<img class="gp-team__avatar-img" src="' . $photo . '" alt="' . esc_attr($plain_name) . '">';
        } else {
            $out .= '<span class="gp-team__initials">' . esc_html($initials) . '</span>';
        }
        $out .= '</div>';

        $out .= '<div class="gp-team__copy">';
        if ($name !== '') {
            $out .= '<span class="gp-team__name">' . $name . '</span>';
        }
        if ($role !== '') {
            $out .= '<span class="gp-team__role">' . $role . '</span>';
        }
        if ($bio !== '') {
            $out .= '<p class="gp-team__bio">' . $bio . '</p>';
        }
        $out .= '</div>';

        $out .= '</div>';
    }

    $out .= '</div>';
    return $out;
}

// ── Process Steps block ────────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('groove-proposal/process-steps', [
        'api_version'     => 3,
        'attributes'      => [
            'steps' => [
                'type'    => 'array',
                'default' => [
                    ['title' => 'Discover', 'desc' => 'Deep dive into user needs, stakeholder goals, and the competitive landscape.'],
                    ['title' => 'Define',   'desc' => 'Synthesize research into a clear strategy, roadmap, and success criteria.'],
                    ['title' => 'Design',   'desc' => 'Explore concepts, refine the visual direction, and prototype key interactions.'],
                    ['title' => 'Deliver',  'desc' => 'Build, test, and launch with thorough QA and handoff documentation.'],
                ],
                'items'   => [
                    'type'       => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'desc'  => ['type' => 'string'],
                    ],
                ],
            ],
            'layout' => ['type' => 'string', 'default' => 'horizontal'],
        ],
        'render_callback' => 'groove_proposal_render_process_steps',
    ]);
});

/**
 * Server-side render for the Process Steps block.
 */
function groove_proposal_render_process_steps(array $attributes): string
{
    $steps  = $attributes['steps'] ?? [];
    $layout = $attributes['layout'] ?? 'horizontal';

    if (empty($steps)) {
        return '';
    }

    $is_vertical = $layout === 'vertical';
    $classes = 'gp-steps';
    if ($is_vertical) {
        $classes .= ' gp-steps--vertical';
    }

    $count = count($steps);
    $out = '<div class="' . esc_attr($classes) . '">';

    foreach ($steps as $i => $step) {
        $ordinal = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
        $title   = wp_kses_post($step['title'] ?? '');
        $desc    = wp_kses_post($step['desc'] ?? '');
        $is_last = $i === $count - 1;
        $is_first = $i === 0;

        $out .= '<div class="gp-steps__step">';

        if ($is_vertical) {
            // Vertical: badge + connector in marker column, copy in body column
            $out .= '<div class="gp-steps__marker">'
                . '<span class="gp-steps__badge">' . esc_html($ordinal) . '</span>';
            if (!$is_last) {
                $out .= '<span class="gp-steps__connector"></span>';
            }
            $out .= '</div>';
            $out .= '<div class="gp-steps__copy">';
            if ($title !== '') {
                $out .= '<h4 class="gp-steps__title">' . $title . '</h4>';
            }
            if ($desc !== '') {
                $out .= '<p class="gp-steps__desc">' . $desc . '</p>';
            }
            $out .= '</div>';
        } else {
            // Horizontal: badge row with connectors, then text below
            $out .= '<div class="gp-steps__badge-row">'
                . '<span class="gp-steps__connector' . ($is_first ? ' gp-steps__connector--hidden' : '') . '"></span>'
                . '<span class="gp-steps__badge">' . esc_html($ordinal) . '</span>'
                . '<span class="gp-steps__connector' . ($is_last ? ' gp-steps__connector--hidden' : '') . '"></span>'
                . '</div>';
            $out .= '<div class="gp-steps__copy">';
            if ($title !== '') {
                $out .= '<h4 class="gp-steps__title">' . $title . '</h4>';
            }
            if ($desc !== '') {
                $out .= '<p class="gp-steps__desc">' . $desc . '</p>';
            }
            $out .= '</div>';
        }

        $out .= '</div>';
    }

    $out .= '</div>';
    return $out;
}

// ── Callout Box block ───────────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('groove-proposal/callout-box', [
        'api_version'     => 3,
        'attributes'      => [
            'title' => ['type' => 'string', 'default' => ''],
            'body'  => ['type' => 'string', 'default' => 'Add your callout content here.'],
            'style' => ['type' => 'string', 'default' => 'note'],
        ],
        'render_callback' => 'groove_proposal_render_callout_box',
    ]);
});

/**
 * Server-side render for the Callout Box block.
 */
function groove_proposal_render_callout_box(array $attributes): string
{
    $title = wp_kses_post($attributes['title'] ?? '');
    $body  = wp_kses_post($attributes['body'] ?? '');
    $style = $attributes['style'] ?? 'note';

    $allowed = ['note', 'tip', 'important', 'warning'];
    if (!in_array($style, $allowed, true)) {
        $style = 'note';
    }

    if ($body === '' && $title === '') {
        return '';
    }

    $labels = [
        'note'      => 'Note',
        'tip'       => 'Tip',
        'important' => 'Important',
        'warning'   => 'Warning',
    ];

    $class = 'gp-callout-box gp-callout-box--' . esc_attr($style);
    $out = '<div class="' . $class . '">';
    $out .= '<span class="gp-callout-box__label">' . esc_html($labels[$style]) . '</span>';

    if ($title !== '') {
        $out .= '<div class="gp-callout-box__title">' . $title . '</div>';
    }
    if ($body !== '') {
        $out .= '<div class="gp-callout-box__body">' . $body . '</div>';
    }

    $out .= '</div>';
    return $out;
}

// ── Comparison Columns block ────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('groove-proposal/comparison-columns', [
        'api_version'     => 3,
        'attributes'      => [
            'columns' => [
                'type'    => 'array',
                'default' => [
                    ['name' => 'Essentials', 'subtitle' => 'Core deliverables', 'features' => ['Brand audit & competitive analysis', 'Visual identity refresh', 'Primary logo suite', 'Brand guidelines (digital)']],
                    ['name' => 'Professional', 'subtitle' => 'Recommended for most teams', 'features' => ['Everything in Essentials', 'Full design system', 'Interactive prototypes', 'Developer handoff package']],
                    ['name' => 'Enterprise', 'subtitle' => 'End-to-end partnership', 'features' => ['Everything in Professional', 'Motion & interaction design', 'Ongoing design support (3 mo)', 'Quarterly design reviews']],
                ],
                'items'   => [
                    'type'       => 'object',
                    'properties' => [
                        'name'     => ['type' => 'string'],
                        'subtitle' => ['type' => 'string'],
                        'features' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            ],
            'recommendedIndex' => ['type' => 'number', 'default' => 1],
        ],
        'render_callback' => 'groove_proposal_render_comparison_columns',
    ]);
});

/**
 * Server-side render for the Comparison Columns block.
 */
function groove_proposal_render_comparison_columns(array $attributes): string
{
    $columns = $attributes['columns'] ?? [];
    $rec_idx = (int) ($attributes['recommendedIndex'] ?? -1);

    if (empty($columns)) {
        return '';
    }

    $col_count = count($columns);
    $class = 'gp-compare gp-compare--cols-' . $col_count;

    // Find max feature rows
    $max_features = 0;
    foreach ($columns as $col) {
        $feat_count = count($col['features'] ?? []);
        if ($feat_count > $max_features) {
            $max_features = $feat_count;
        }
    }

    $out = '<div class="' . esc_attr($class) . '">';

    // Header row
    $out .= '<div class="gp-compare__header">';
    foreach ($columns as $ci => $col) {
        $is_rec = $ci === $rec_idx;
        $cell_class = 'gp-compare__header-cell';
        if ($is_rec) {
            $cell_class .= ' gp-compare__header-cell--recommended';
        }

        $out .= '<div class="' . esc_attr($cell_class) . '">';
        if ($is_rec) {
            $out .= '<span class="gp-compare__rec-badge">' . esc_html__('Recommended', 'groove') . '</span>';
        }
        $name = wp_kses_post($col['name'] ?? '');
        $subtitle = wp_kses_post($col['subtitle'] ?? '');
        if ($name !== '') {
            $out .= '<div class="gp-compare__name">' . $name . '</div>';
        }
        if ($subtitle !== '') {
            $out .= '<div class="gp-compare__subtitle">' . $subtitle . '</div>';
        }
        $out .= '</div>';
    }
    $out .= '</div>';

    // Feature rows
    for ($fi = 0; $fi < $max_features; $fi++) {
        $out .= '<div class="gp-compare__row">';
        foreach ($columns as $ci => $col) {
            $is_rec = $ci === $rec_idx;
            $cell_class = 'gp-compare__cell';
            if ($is_rec) {
                $cell_class .= ' gp-compare__cell--recommended';
            }
            $feat = wp_kses_post($col['features'][$fi] ?? '');
            $out .= '<div class="' . esc_attr($cell_class) . '">';
            if ($feat !== '') {
                $out .= '<span class="gp-compare__feature">' . $feat . '</span>';
            }
            $out .= '</div>';
        }
        $out .= '</div>';
    }

    $out .= '</div>';
    return $out;
}

// ── Editor assets ───────────────────────────────────────────────────────────

add_action('enqueue_block_editor_assets', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'groove_folio_page') {
        return;
    }

    $blocks_dir = __DIR__ . '/blocks/key-metrics/';
    $theme_url  = groove_proposal_blocks_url();

    // Block JS
    $js_path = $blocks_dir . 'index.js';
    wp_enqueue_script(
        'groove-proposal-block-key-metrics',
        $theme_url . 'blocks/key-metrics/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components'],
        file_exists($js_path) ? (string) filemtime($js_path) : GROOVE_VERSION,
        true
    );

    // Editor CSS
    $css_path = $blocks_dir . 'editor.css';
    wp_enqueue_style(
        'groove-proposal-block-key-metrics-editor',
        $theme_url . 'blocks/key-metrics/editor.css',
        [],
        file_exists($css_path) ? (string) filemtime($css_path) : GROOVE_VERSION
    );

    // Timeline block JS
    $timeline_js = __DIR__ . '/blocks/timeline/index.js';
    wp_enqueue_script(
        'groove-proposal-block-timeline',
        $theme_url . 'blocks/timeline/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components'],
        file_exists($timeline_js) ? (string) filemtime($timeline_js) : GROOVE_VERSION,
        true
    );

    // Pull Quote block JS
    $quote_js = __DIR__ . '/blocks/pull-quote/index.js';
    wp_enqueue_script(
        'groove-proposal-block-pull-quote',
        $theme_url . 'blocks/pull-quote/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components'],
        file_exists($quote_js) ? (string) filemtime($quote_js) : GROOVE_VERSION,
        true
    );

    // Pricing Table block JS
    $pricing_js = __DIR__ . '/blocks/pricing-table/index.js';
    wp_enqueue_script(
        'groove-proposal-block-pricing-table',
        $theme_url . 'blocks/pricing-table/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components'],
        file_exists($pricing_js) ? (string) filemtime($pricing_js) : GROOVE_VERSION,
        true
    );

    // Team Grid block JS
    $team_js = __DIR__ . '/blocks/team-grid/index.js';
    wp_enqueue_script(
        'groove-proposal-block-team-grid',
        $theme_url . 'blocks/team-grid/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components'],
        file_exists($team_js) ? (string) filemtime($team_js) : GROOVE_VERSION,
        true
    );

    // Process Steps block JS
    $steps_js = __DIR__ . '/blocks/process-steps/index.js';
    wp_enqueue_script(
        'groove-proposal-block-process-steps',
        $theme_url . 'blocks/process-steps/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components'],
        file_exists($steps_js) ? (string) filemtime($steps_js) : GROOVE_VERSION,
        true
    );

    // Callout Box block JS
    $callout_js = __DIR__ . '/blocks/callout-box/index.js';
    wp_enqueue_script(
        'groove-proposal-block-callout-box',
        $theme_url . 'blocks/callout-box/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components'],
        file_exists($callout_js) ? (string) filemtime($callout_js) : GROOVE_VERSION,
        true
    );

    // Comparison Columns block JS
    $compare_js = __DIR__ . '/blocks/comparison-columns/index.js';
    wp_enqueue_script(
        'groove-proposal-block-comparison-columns',
        $theme_url . 'blocks/comparison-columns/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components'],
        file_exists($compare_js) ? (string) filemtime($compare_js) : GROOVE_VERSION,
        true
    );

    // Google Fonts for editor preview (Fraunces + Inter)
    wp_enqueue_style(
        'groove-proposal-editor-fonts',
        'https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,300;0,400;1,300;1,400&family=Inter:wght@400;500;600&display=swap',
        [],
        null
    );
});

/**
 * Resolve the URL to this theme's folder.
 */
function groove_proposal_blocks_url(): string
{
    static $url;
    if ($url !== null) {
        return $url;
    }

    $theme_path = wp_normalize_path(trailingslashit(__DIR__));
    $content_dir = wp_normalize_path(trailingslashit(WP_CONTENT_DIR));

    if (strpos($theme_path, $content_dir) === 0) {
        $relative = ltrim(substr($theme_path, strlen($content_dir)), '/');
        $url = trailingslashit(WP_CONTENT_URL) . $relative;
    } else {
        $url = trailingslashit(plugin_dir_url(__FILE__));
    }

    return $url;
}
