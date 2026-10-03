<?php
return [
    'name' => 'Enrove Newsletter',
    'default_title' => 'A New Issue',
    'thumbnail' => 'theme-thumb.png',
    'cover' => 'theme-cover.jpg',
    'image_set' => 'daylight',
    'logo' => 'theme-g-logo.png',
    'description' => 'An editorial theme whose palette is generated from the reader\'s own time of day — dawn amber through evening ink — over a serif reading column that reflows to any width.',
    // Capabilities named in the theme picker. Keys from the shared
    // vocabulary in Themes_Manager::feature_label() are translated; anything
    // else is shown verbatim. See themes/README.md §3.
    'features' => ['dynamic-color', 'page-transitions'],
    'author' => 'StudioEN',
    'last_updated' => '2026-03-12',
    // Colours the password gate borrows so it looks like the folio behind
    // it. These used to live in two hardcoded maps in plugin source.
    'gate' => [
        'accent' => '#6f3115',
        'accent_hover' => '#5a2710',
        'background' => '#eee7db',
    ],
    'namespace' => 'Enrove\Themes\Enrove_Newsletter',
    'cover_class' => 'Enrove\Themes\Enrove_Newsletter\Cover',
    'page_class' => 'Enrove\Themes\Enrove_Newsletter\Page',
    'fonts' => [
        'header' => [
            'css_stack'     => "'Space Grotesk', 'Helvetica Neue', sans-serif",
            'google_family' => 'Space+Grotesk:wght@400;500;700',
        ],
        'body' => [
            'css_stack'     => "'Source Serif 4', Georgia, serif",
            'google_family' => 'Source+Serif+4:opsz,wght@8..60,400;8..60,500;8..60,600',
        ],
    ],
    'dependencies' => [
        'navigation-pane.php'
    ],
];
