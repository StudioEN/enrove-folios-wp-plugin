<?php
/**
 * Register custom Gutenberg blocks for the Enrove Proposal theme.
 *
 * Loaded as a dependency via setup.php.
 */

use Enrove\Themes\Font_Loader;
use Enrove\Themes\Enrove_Proposal\Cover;

if (!defined('ABSPATH')) {
    exit;
}

// ── Block category ──────────────────────────────────────────────────────────

add_filter('block_categories_all', function (array $categories): array {
    array_unshift($categories, [
        'slug'  => 'enrove-proposal',
        'title' => __('Enrove Proposal', 'enrove-folios'),
    ]);
    return $categories;
});

// ── Key Metrics block ───────────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('enrove-proposal/key-metrics', [
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
        'render_callback' => 'enrove_proposal_render_key_metrics',
    ]);
});

/**
 * Server-side render for the Key Metrics block.
 */
function enrove_proposal_render_key_metrics(array $attributes): string
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
    register_block_type('enrove-proposal/timeline', [
        'api_version'     => 3,
        'attributes'      => [
            'phases' => [
                'type'    => 'array',
                'default' => [
                    ['date' => 'Weeks 1-2', 'title' => 'Discovery',                'desc' => 'Stakeholder interviews, competitive audit, and user research to establish the project foundation.'],
                    ['date' => 'Weeks 3-5', 'title' => 'Strategy & architecture',   'desc' => 'Define the roadmap, information architecture, and content strategy based on research findings.'],
                    ['date' => 'Weeks 6-10', 'title' => 'Design & prototyping',     'desc' => 'Visual design, interactive prototyping, and iterative review cycles with your team.'],
                    ['date' => 'Weeks 11-12', 'title' => 'Handoff & launch support', 'desc' => 'Design system documentation, developer handoff, and launch QA.'],
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
        'render_callback' => 'enrove_proposal_render_timeline',
    ]);
});

/**
 * Server-side render for the Timeline block.
 */
function enrove_proposal_render_timeline(array $attributes): string
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
    register_block_type('enrove-proposal/pull-quote', [
        'api_version'     => 3,
        'attributes'      => [
            'text'   => ['type' => 'string', 'default' => 'Working with this team transformed how we think about our product. The strategic clarity they brought was exactly what we needed.'],
            'author' => ['type' => 'string', 'default' => 'Sarah Chen'],
            'role'   => ['type' => 'string', 'default' => 'VP of Product, Acme Inc.'],
        ],
        'render_callback' => 'enrove_proposal_render_pull_quote',
    ]);
});

/**
 * Server-side render for the Pull Quote block.
 */
function enrove_proposal_render_pull_quote(array $attributes): string
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
    register_block_type('enrove-proposal/pricing-table', [
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
        'render_callback' => 'enrove_proposal_render_pricing_table',
    ]);
});

/**
 * Server-side render for the Pricing Table block.
 */
function enrove_proposal_render_pricing_table(array $attributes): string
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
        . '<span class="gp-pricing__col-label">' . esc_html__('Scope', 'enrove-folios') . '</span>'
        . '<span class="gp-pricing__col-label gp-pricing__col-label--right">' . esc_html__('Investment', 'enrove-folios') . '</span>'
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
            . '<span class="gp-pricing__name">' . $name . ($optional ? ' <em>' . esc_html__('(optional)', 'enrove-folios') . '</em>' : '') . '</span>'
            . '<span class="gp-pricing__desc">' . $desc . '</span>'
            . '</div>'
            . '<span class="gp-pricing__price">' . esc_html($format_price($price)) . '</span>'
            . '</div>';
    }

    $out .= '<div class="gp-pricing__total">'
        . '<span class="gp-pricing__total-label">' . esc_html__('Total', 'enrove-folios') . '</span>'
        . '<span class="gp-pricing__total-value">' . esc_html($format_price($total)) . '</span>'
        . '</div></div>';

    return $out;
}

// ── Team Grid block ────────────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('enrove-proposal/team-grid', [
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
        'render_callback' => 'enrove_proposal_render_team_grid',
    ]);
});

/**
 * Server-side render for the Team Grid block.
 */
function enrove_proposal_render_team_grid(array $attributes): string
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
    register_block_type('enrove-proposal/process-steps', [
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
        'render_callback' => 'enrove_proposal_render_process_steps',
    ]);
});

/**
 * Server-side render for the Process Steps block.
 */
function enrove_proposal_render_process_steps(array $attributes): string
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
    register_block_type('enrove-proposal/callout-box', [
        'api_version'     => 3,
        'attributes'      => [
            'title' => ['type' => 'string', 'default' => ''],
            'body'  => ['type' => 'string', 'default' => 'Add your callout content here.'],
            'style' => ['type' => 'string', 'default' => 'note'],
        ],
        'render_callback' => 'enrove_proposal_render_callout_box',
    ]);
});

/**
 * Server-side render for the Callout Box block.
 */
function enrove_proposal_render_callout_box(array $attributes): string
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
        'note'      => __('Note', 'enrove-folios'),
        'tip'       => __('Tip', 'enrove-folios'),
        'important' => __('Important', 'enrove-folios'),
        'warning'   => __('Warning', 'enrove-folios'),
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
    register_block_type('enrove-proposal/comparison-columns', [
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
        'render_callback' => 'enrove_proposal_render_comparison_columns',
    ]);
});

/**
 * Server-side render for the Comparison Columns block.
 */
function enrove_proposal_render_comparison_columns(array $attributes): string
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
            $out .= '<span class="gp-compare__rec-badge">' . esc_html__('Recommended', 'enrove-folios') . '</span>';
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

// ── Case Study block ────────────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('enrove-proposal/case-study', [
        'api_version'     => 3,
        'attributes'      => [
            'clientName'   => ['type' => 'string', 'default' => ''],
            'clientLogo'   => ['type' => 'string', 'default' => ''],
            'projectTitle' => ['type' => 'string', 'default' => ''],
            'heroImage'    => ['type' => 'string', 'default' => ''],
            'tagsText'     => ['type' => 'string', 'default' => ''],
            'stats'        => [
                'type'    => 'array',
                'default' => [],
                'items'   => [
                    'type'       => 'object',
                    'properties' => [
                        'value' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                    ],
                ],
            ],
            'link'   => ['type' => 'string', 'default' => ''],
            'layout' => ['type' => 'string', 'default' => 'spotlight'],
        ],
        'render_callback' => 'enrove_proposal_render_case_study',
    ]);
});

/**
 * Server-side render for the Case Study block.
 *
 * $content is the already-rendered InnerBlocks.Content HTML for the
 * narrative body (challenge / approach / results). It is final, safe HTML
 * produced by WordPress's own block rendering — do not re-parse or re-render it.
 */
function enrove_proposal_render_case_study(array $attributes, string $content): string
{
    $client_name   = wp_kses_post($attributes['clientName'] ?? '');
    $client_logo   = esc_url($attributes['clientLogo'] ?? '');
    $project_title = wp_kses_post($attributes['projectTitle'] ?? '');
    $hero_image    = esc_url($attributes['heroImage'] ?? '');
    $tags_text     = (string) ($attributes['tagsText'] ?? '');
    $stats         = $attributes['stats'] ?? [];
    $link          = esc_url($attributes['link'] ?? '');
    $layout        = $attributes['layout'] ?? 'spotlight';

    if (!in_array($layout, ['spotlight', 'compact'], true)) {
        $layout = 'spotlight';
    }

    if ($project_title === '' && trim(wp_strip_all_tags($content)) === '') {
        return '';
    }

    $tags = array_filter(array_map('trim', explode(',', $tags_text)), function (string $tag): bool {
        return $tag !== '';
    });

    $out = '<article class="gp-case-study gp-case-study--' . esc_attr($layout) . '">';

    $out .= '<header class="gp-case-study__header">';
    if ($client_logo !== '' || $client_name !== '') {
        $out .= '<div class="gp-case-study__client">';
        if ($client_logo !== '') {
            $out .= '<img class="gp-case-study__logo" src="' . $client_logo . '" alt="' . esc_attr(wp_strip_all_tags($client_name)) . '">';
        }
        if ($client_name !== '') {
            $out .= '<span class="gp-case-study__client-name">' . $client_name . '</span>';
        }
        $out .= '</div>';
    }
    if (!empty($tags)) {
        $out .= '<div class="gp-case-study__tags">';
        foreach ($tags as $tag) {
            $out .= '<span class="gp-case-study__tag">' . esc_html($tag) . '</span>';
        }
        $out .= '</div>';
    }
    if ($project_title !== '') {
        $out .= '<h3 class="gp-case-study__title">' . $project_title . '</h3>';
    }
    $out .= '</header>';

    if ($hero_image !== '') {
        $out .= '<figure class="gp-case-study__hero">'
            . '<img src="' . $hero_image . '" alt="' . esc_attr(wp_strip_all_tags($project_title)) . '">'
            . '</figure>';
    }

    if (!empty($stats)) {
        $out .= '<div class="gp-case-study__stats">';
        foreach ($stats as $stat) {
            $value = wp_kses_post($stat['value'] ?? '');
            $label = wp_kses_post($stat['label'] ?? '');
            $out .= '<div class="gp-case-study__stat">'
                . '<span class="gp-case-study__stat-value">' . $value . '</span>'
                . '<span class="gp-case-study__stat-label">' . $label . '</span>'
                . '</div>';
        }
        $out .= '</div>';
    }

    $out .= '<div class="gp-case-study__body">' . $content . '</div>';

    if ($link !== '') {
        $out .= '<a class="gp-case-study__link" href="' . $link . '" target="_blank" rel="noopener noreferrer">'
            . esc_html__('View live project', 'enrove-folios') . ' &rarr;</a>';
    }

    $out .= '</article>';

    return $out;
}

// ── FAQ Accordion block ─────────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('enrove-proposal/faq', [
        'api_version'     => 3,
        'attributes'      => [
            'items' => [
                'type'    => 'array',
                'default' => [
                    ['question' => 'What are the payment terms?', 'answer' => 'We require a 50% deposit to begin work, with the remaining balance due upon project completion. For larger engagements we can arrange milestone-based payments instead.'],
                    ['question' => 'Is the timeline flexible if our needs change?', 'answer' => 'Yes. The schedule outlined in this proposal reflects the current scope, and if priorities shift once we’re underway, we’ll revisit the timeline together and adjust accordingly.'],
                    ['question' => 'What happens after we sign?', 'answer' => 'Once the agreement is signed, we’ll schedule a kickoff call within three business days to align on goals, gather assets, and confirm the project timeline.'],
                ],
                'items'   => [
                    'type'       => 'object',
                    'properties' => [
                        'question' => ['type' => 'string'],
                        'answer'   => ['type' => 'string'],
                    ],
                ],
            ],
        ],
        'render_callback' => 'enrove_proposal_render_faq',
    ]);
});

/**
 * Server-side render for the FAQ Accordion block.
 */
function enrove_proposal_render_faq(array $attributes): string
{
    $items = $attributes['items'] ?? [];
    if (empty($items)) {
        return '';
    }

    $has_content = false;
    foreach ($items as $item) {
        if (trim(wp_strip_all_tags($item['question'] ?? '')) !== '' || trim(wp_strip_all_tags($item['answer'] ?? '')) !== '') {
            $has_content = true;
            break;
        }
    }
    if (!$has_content) {
        return '';
    }

    $out = '<div class="gp-faq">';
    foreach ($items as $item) {
        $question = wp_kses_post($item['question'] ?? '');
        $answer   = wp_kses_post($item['answer'] ?? '');

        if ($question === '' && $answer === '') {
            continue;
        }

        $out .= '<details class="gp-faq__item">'
            . '<summary class="gp-faq__question">' . $question . '</summary>'
            . '<div class="gp-faq__answer">' . $answer . '</div>'
            . '</details>';
    }
    $out .= '</div>';

    return $out;
}

// ── Closing CTA block ───────────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('enrove-proposal/cta', [
        'api_version'     => 3,
        'attributes'      => [
            'heading'    => ['type' => 'string', 'default' => 'Ready to get started?'],
            'body'       => ['type' => 'string', 'default' => 'Let’s schedule a call to walk through next steps and answer any questions.'],
            'buttonText' => ['type' => 'string', 'default' => 'Schedule a call'],
            'buttonUrl'  => ['type' => 'string', 'default' => ''],
            'style'      => ['type' => 'string', 'default' => 'primary'],
        ],
        'render_callback' => 'enrove_proposal_render_cta',
    ]);
});

/**
 * Server-side render for the Closing CTA block.
 */
function enrove_proposal_render_cta(array $attributes): string
{
    $heading     = wp_kses_post($attributes['heading'] ?? '');
    $body        = wp_kses_post($attributes['body'] ?? '');
    $button_text = wp_kses_post($attributes['buttonText'] ?? '');
    $button_url  = esc_url($attributes['buttonUrl'] ?? '');
    $style       = $attributes['style'] ?? 'primary';

    $allowed = ['primary', 'subtle'];
    if (!in_array($style, $allowed, true)) {
        $style = 'primary';
    }

    if ($heading === '' && $body === '' && $button_text === '') {
        return '';
    }

    $class = 'gp-cta gp-cta--' . esc_attr($style);
    $out = '<div class="' . $class . '">';

    if ($heading !== '') {
        $out .= '<h3 class="gp-cta__heading">' . $heading . '</h3>';
    }
    if ($body !== '') {
        $out .= '<div class="gp-cta__body">' . $body . '</div>';
    }
    if ($button_text !== '' && $button_url !== '') {
        $out .= '<a class="gp-cta__button" href="' . $button_url . '">' . $button_text . '</a>';
    }

    $out .= '</div>';
    return $out;
}

// ── Testimonial Grid block ──────────────────────────────────────────────────

add_action('init', function () {
    register_block_type('enrove-proposal/testimonial-grid', [
        'api_version'     => 3,
        'attributes'      => [
            'items' => [
                'type'    => 'array',
                'default' => [
                    ['text' => 'They took a vague brief and turned it into a roadmap we actually trusted.', 'author' => 'Priya Anand',   'role' => 'COO, Nordlight Group'],
                    ['text' => 'Communication was clear at every step, with no surprises and no scope creep.', 'author' => 'Diego Fernandez', 'role' => 'Head of Marketing, Vale & Co.'],
                    ['text' => 'The final result exceeded what we thought was possible on this timeline.',   'author' => 'Emily Zhou',      'role' => 'Founder, Zhou Studio'],
                ],
                'items'   => [
                    'type'       => 'object',
                    'properties' => [
                        'text'   => ['type' => 'string'],
                        'author' => ['type' => 'string'],
                        'role'   => ['type' => 'string'],
                    ],
                ],
            ],
            'columns' => ['type' => 'number', 'default' => 3],
        ],
        'render_callback' => 'enrove_proposal_render_testimonial_grid',
    ]);
});

/**
 * Server-side render for the Testimonial Grid block.
 */
function enrove_proposal_render_testimonial_grid(array $attributes): string
{
    $items   = $attributes['items'] ?? [];
    $columns = (int) ($attributes['columns'] ?? 3);
    if ($columns !== 2 && $columns !== 3) {
        $columns = 3;
    }

    if (empty($items)) {
        return '';
    }

    $has_content = false;
    foreach ($items as $item) {
        if (trim(wp_strip_all_tags($item['text'] ?? '')) !== '') {
            $has_content = true;
            break;
        }
    }
    if (!$has_content) {
        return '';
    }

    $class = 'gp-testimonials gp-testimonials--cols-' . $columns;
    $out = '<div class="' . esc_attr($class) . '">';

    foreach ($items as $item) {
        $text   = wp_kses_post($item['text'] ?? '');
        $author = wp_kses_post($item['author'] ?? '');
        $role   = wp_kses_post($item['role'] ?? '');

        if ($text === '') {
            continue;
        }

        $out .= '<figure class="gp-testimonials__item">'
            . '<blockquote class="gp-testimonials__text">' . $text . '</blockquote>'
            . '<figcaption class="gp-testimonials__cite">';
        if ($author !== '') {
            $out .= '<span class="gp-testimonials__author">' . $author . '</span>';
        }
        if ($role !== '') {
            $out .= '<span class="gp-testimonials__role">' . $role . '</span>';
        }
        $out .= '</figcaption></figure>';
    }

    $out .= '</div>';
    return $out;
}

// ── Trusted-by Logo Strip block ─────────────────────────────────────────────

add_action('init', function () {
    register_block_type('enrove-proposal/logo-strip', [
        'api_version'     => 3,
        'attributes'      => [
            'heading' => ['type' => 'string', 'default' => 'Trusted by teams like yours'],
            'logos'   => [
                'type'    => 'array',
                'default' => [
                    ['url' => '', 'name' => '', 'link' => ''],
                    ['url' => '', 'name' => '', 'link' => ''],
                    ['url' => '', 'name' => '', 'link' => ''],
                    ['url' => '', 'name' => '', 'link' => ''],
                ],
                'items'   => [
                    'type'       => 'object',
                    'properties' => [
                        'url'  => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'link' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
        'render_callback' => 'enrove_proposal_render_logo_strip',
    ]);
});

/**
 * Server-side render for the Trusted-by Logo Strip block.
 */
function enrove_proposal_render_logo_strip(array $attributes): string
{
    $heading = wp_kses_post($attributes['heading'] ?? '');
    $logos   = $attributes['logos'] ?? [];

    $has_logo = false;
    foreach ($logos as $logo) {
        if (trim((string) ($logo['url'] ?? '')) !== '') {
            $has_logo = true;
            break;
        }
    }
    if (!$has_logo) {
        return '';
    }

    $out = '<div class="gp-logos">';
    if ($heading !== '') {
        $out .= '<p class="gp-logos__heading">' . $heading . '</p>';
    }

    $out .= '<div class="gp-logos__row">';
    foreach ($logos as $logo) {
        $url = esc_url($logo['url'] ?? '');
        if ($url === '') {
            continue;
        }
        $name = esc_attr($logo['name'] ?? '');
        $link = esc_url($logo['link'] ?? '');

        $img = '<img class="gp-logos__img" src="' . $url . '" alt="' . $name . '">';

        if ($link !== '') {
            $out .= '<a class="gp-logos__item" href="' . $link . '">' . $img . '</a>';
        } else {
            $out .= '<span class="gp-logos__item">' . $img . '</span>';
        }
    }
    $out .= '</div></div>';

    return $out;
}

// ── Deliverables Checklist block ────────────────────────────────────────────

add_action('init', function () {
    register_block_type('enrove-proposal/deliverables', [
        'api_version'     => 3,
        'attributes'      => [
            'heading' => ['type' => 'string', 'default' => ''],
            'items'   => [
                'type'    => 'array',
                'default' => [
                    'Discovery workshop and stakeholder interviews',
                    'Complete visual identity and brand guidelines',
                    'Responsive website design and development',
                    '30 days of post-launch support',
                ],
                'items'   => ['type' => 'string'],
            ],
        ],
        'render_callback' => 'enrove_proposal_render_deliverables',
    ]);
});

/**
 * Server-side render for the Deliverables Checklist block.
 */
function enrove_proposal_render_deliverables(array $attributes): string
{
    $heading = wp_kses_post($attributes['heading'] ?? '');
    $items   = $attributes['items'] ?? [];

    $has_content = false;
    foreach ($items as $item) {
        if (trim(wp_strip_all_tags((string) $item)) !== '') {
            $has_content = true;
            break;
        }
    }
    if (!$has_content) {
        return '';
    }

    $out = '<div class="gp-checklist">';
    if ($heading !== '') {
        $out .= '<p class="gp-checklist__heading">' . $heading . '</p>';
    }

    $out .= '<ul class="gp-checklist__list">';
    foreach ($items as $item) {
        $text = wp_kses_post((string) $item);
        if ($text === '') {
            continue;
        }
        $out .= '<li class="gp-checklist__item">' . $text . '</li>';
    }
    $out .= '</ul></div>';

    return $out;
}

// ── Editor assets ───────────────────────────────────────────────────────────

add_action('enqueue_block_editor_assets', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'enrove_folio_page') {
        return;
    }

    $blocks_dir = __DIR__ . '/blocks/key-metrics/';
    $theme_url  = enrove_proposal_blocks_url();

    // Block JS
    $js_path = $blocks_dir . 'index.js';
    wp_enqueue_script(
        'enrove-proposal-block-key-metrics',
        $theme_url . 'blocks/key-metrics/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
        file_exists($js_path) ? (string) filemtime($js_path) : ENROVE_VERSION,
        true
    );

    // Editor CSS
    $css_path = $blocks_dir . 'editor.css';
    wp_enqueue_style(
        'enrove-proposal-block-key-metrics-editor',
        $theme_url . 'blocks/key-metrics/editor.css',
        [],
        file_exists($css_path) ? (string) filemtime($css_path) : ENROVE_VERSION
    );

    // Timeline block JS
    $timeline_js = __DIR__ . '/blocks/timeline/index.js';
    wp_enqueue_script(
        'enrove-proposal-block-timeline',
        $theme_url . 'blocks/timeline/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
        file_exists($timeline_js) ? (string) filemtime($timeline_js) : ENROVE_VERSION,
        true
    );

    // Pull Quote block JS
    $quote_js = __DIR__ . '/blocks/pull-quote/index.js';
    wp_enqueue_script(
        'enrove-proposal-block-pull-quote',
        $theme_url . 'blocks/pull-quote/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
        file_exists($quote_js) ? (string) filemtime($quote_js) : ENROVE_VERSION,
        true
    );

    // Pricing Table block JS
    $pricing_js = __DIR__ . '/blocks/pricing-table/index.js';
    wp_enqueue_script(
        'enrove-proposal-block-pricing-table',
        $theme_url . 'blocks/pricing-table/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
        file_exists($pricing_js) ? (string) filemtime($pricing_js) : ENROVE_VERSION,
        true
    );

    // Team Grid block JS
    $team_js = __DIR__ . '/blocks/team-grid/index.js';
    wp_enqueue_script(
        'enrove-proposal-block-team-grid',
        $theme_url . 'blocks/team-grid/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
        file_exists($team_js) ? (string) filemtime($team_js) : ENROVE_VERSION,
        true
    );

    // Process Steps block JS
    $steps_js = __DIR__ . '/blocks/process-steps/index.js';
    wp_enqueue_script(
        'enrove-proposal-block-process-steps',
        $theme_url . 'blocks/process-steps/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
        file_exists($steps_js) ? (string) filemtime($steps_js) : ENROVE_VERSION,
        true
    );

    // Callout Box block JS
    $callout_js = __DIR__ . '/blocks/callout-box/index.js';
    wp_enqueue_script(
        'enrove-proposal-block-callout-box',
        $theme_url . 'blocks/callout-box/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
        file_exists($callout_js) ? (string) filemtime($callout_js) : ENROVE_VERSION,
        true
    );

    // Comparison Columns block JS
    $compare_js = __DIR__ . '/blocks/comparison-columns/index.js';
    wp_enqueue_script(
        'enrove-proposal-block-comparison-columns',
        $theme_url . 'blocks/comparison-columns/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
        file_exists($compare_js) ? (string) filemtime($compare_js) : ENROVE_VERSION,
        true
    );

    // Case Study block JS
    $case_study_js = __DIR__ . '/blocks/case-study/index.js';
    wp_enqueue_script(
        'enrove-proposal-block-case-study',
        $theme_url . 'blocks/case-study/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
        file_exists($case_study_js) ? (string) filemtime($case_study_js) : ENROVE_VERSION,
        true
    );

    // FAQ Accordion block JS
    $faq_js = __DIR__ . '/blocks/faq/index.js';
    wp_enqueue_script(
        'enrove-proposal-block-faq',
        $theme_url . 'blocks/faq/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
        file_exists($faq_js) ? (string) filemtime($faq_js) : ENROVE_VERSION,
        true
    );

    // Closing CTA block JS
    $cta_js = __DIR__ . '/blocks/cta/index.js';
    wp_enqueue_script(
        'enrove-proposal-block-cta',
        $theme_url . 'blocks/cta/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
        file_exists($cta_js) ? (string) filemtime($cta_js) : ENROVE_VERSION,
        true
    );

    // Testimonial Grid block JS
    $testimonials_js = __DIR__ . '/blocks/testimonial-grid/index.js';
    wp_enqueue_script(
        'enrove-proposal-block-testimonial-grid',
        $theme_url . 'blocks/testimonial-grid/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
        file_exists($testimonials_js) ? (string) filemtime($testimonials_js) : ENROVE_VERSION,
        true
    );

    // Trusted-by Logo Strip block JS
    $logo_strip_js = __DIR__ . '/blocks/logo-strip/index.js';
    wp_enqueue_script(
        'enrove-proposal-block-logo-strip',
        $theme_url . 'blocks/logo-strip/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
        file_exists($logo_strip_js) ? (string) filemtime($logo_strip_js) : ENROVE_VERSION,
        true
    );

    // Deliverables Checklist block JS
    $deliverables_js = __DIR__ . '/blocks/deliverables/index.js';
    wp_enqueue_script(
        'enrove-proposal-block-deliverables',
        $theme_url . 'blocks/deliverables/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
        file_exists($deliverables_js) ? (string) filemtime($deliverables_js) : ENROVE_VERSION,
        true
    );


    // Every block script above labels its editor UI with wp.i18n.
    foreach (wp_scripts()->queue as $handle) {
        if (strpos($handle, 'enrove-proposal-block-') === 0) {
            wp_set_script_translations($handle, 'enrove-folios');
        }
    }

    // Editor typography. Loaded through the same resolver the front end uses,
    // so the canvas previews whatever this folio will actually render with —
    // and only when the folio really is an Enrove Proposal.
    enrove_proposal_enqueue_editor_fonts();
});

/**
 * Load the edited folio's fonts into the block editor canvas.
 *
 * Skips entirely unless the page being edited belongs to a folio using this
 * theme, so editing a folio built on any other theme costs no font request.
 */
function enrove_proposal_enqueue_editor_fonts(): void
{
    $page = get_post();
    if (!$page || $page->post_type !== 'enrove_folio_page') {
        return;
    }

    $folio_id = (int) get_post_meta($page->ID, 'folio_id', true);
    if ($folio_id <= 0) {
        return;
    }

    if ((string) get_post_meta($folio_id, 'theme_id', true) !== Cover::get_id()) {
        return;
    }

    $resolved = Font_Loader::resolve($folio_id, Cover::get_default_fonts());

    Font_Loader::enqueue($resolved, 'enrove-proposal-editor-fonts', Font_Loader::EDITOR_SELECTOR);
}

/**
 * The URL of this theme's folder, trailing slash. Themes are bundled with the
 * plugin, so plugin_dir_url() resolves it.
 */
function enrove_proposal_blocks_url(): string
{
    return plugin_dir_url(__FILE__);
}
