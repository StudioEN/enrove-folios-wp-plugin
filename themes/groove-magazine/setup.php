<?php
return [
    'name' => 'Groove Magazine',
    'default_title' => 'A New Issue',
    'thumbnail' => 'theme-thumb.png',
    'cover' => 'theme-cover.jpg',
    'image_set' => 'editorial',
    'logo' => 'theme-g-logo.png',
    'description' => 'A bold editorial theme whose colorway is built from each page\'s feature image, contrast-checked as it goes, and which follows light or dark as the reader prefers.',
    // Capabilities named in the theme picker. Keys from the shared
    // vocabulary in Themes_Manager::feature_label() are translated; anything
    // else is shown verbatim. See themes/README.md §3.
    'features' => ['dynamic-color', 'light-dark'],
    'author' => 'StudioEN',
    'last_updated' => '2026-03-10',
    'namespace' => 'Groove\Themes\Groove_Magazine',
    'cover_class' => 'Groove\Themes\Groove_Magazine\Cover',
    'page_class' => 'Groove\Themes\Groove_Magazine\Page',
    'fonts' => [
        'header' => [
            'css_stack'     => "'STIX Two Text', Georgia, 'Times New Roman', serif",
            'google_family' => 'STIX+Two+Text:ital,wght@0,400..700;1,400..700',
        ],
        'body' => [
            'css_stack'     => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
            'google_family' => 'Inter:wght@300;400;500;600;700',
        ],
    ],
    'dependencies' => [
        'navigation-pane.php',
    ],
];
