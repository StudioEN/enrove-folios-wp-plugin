<?php
return [
    'name' => 'Groove Newsletter',
    'default_title' => 'A New Issue',
    'thumbnail' => 'theme-thumb.png',
    'cover' => 'theme-cover.jpg',
    'image_set' => 'daylight',
    'logo' => 'theme-g-logo.png',
    'description' => 'A modern editorial newsletter with accessible preset color systems and responsive reading layouts.',
    'author' => 'StudioEN',
    'last_updated' => '2026-03-12',
    'namespace' => 'Groove\Themes\Groove_Newsletter',
    'cover_class' => 'Groove\Themes\Groove_Newsletter\Cover',
    'page_class' => 'Groove\Themes\Groove_Newsletter\Page',
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
