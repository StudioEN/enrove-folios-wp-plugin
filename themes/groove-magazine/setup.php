<?php
return [
    'name' => 'Groove Magazine',
    'default_title' => 'A New Issue',
    'thumbnail' => 'theme-thumb.png',
    'cover' => 'theme-cover.jpg',
    'image_set' => 'editorial',
    'logo' => 'theme-g-logo.png',
    'description' => 'A bold editorial theme with adaptive colorways driven by each story\'s feature image. Designed for weekly digital publications.',
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
