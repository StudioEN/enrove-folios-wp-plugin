<?php
return [
    'name' => 'Folio Starter',
    'default_title' => 'A New Folio',
    'thumbnail' => 'theme-thumb.png',
    'cover' => 'theme-cover.jpg',
    'image_set' => 'studio',
    'logo' => 'theme-g-logo.png',
    'description' => 'A clean, typography-led starting point: a generous measure, a contents rail that carries from page to page, and nothing set to compete with the words.',
    'author' => 'StudioEN',
    'last_updated' => '2026-09-01',
    'namespace' => 'Groove\Themes\Folio_Starter',
    'cover_class' => 'Groove\Themes\Folio_Starter\Cover',
    'page_class' => 'Groove\Themes\Folio_Starter\Page',
    // Theme typeface defaults. A folio's own font pickers override these;
    // Base_Theme loads whichever wins in one request. See themes/README.md.
    'fonts' => [
        'header' => [
            'css_stack'     => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
            'google_family' => 'Inter:wght@400;500;600;700',
        ],
        'body' => [
            'css_stack'     => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
            'google_family' => 'Inter:wght@400;500;600;700',
        ],
    ],
];
