<?php
return [
    'name' => 'Groove eBook',
    'default_title' => 'A New eBook',
    'thumbnail' => 'theme-thumb.png',
    'cover' => 'theme-cover.jpg',
    'image_set' => 'paper',
    'logo' => 'theme-g-logo.png',
    'description' => 'A structured reading theme: numbered contents, a frosted chapter bar that holds still while the page turns beneath it, and a kit of seven book blocks — epigraphs, plates, pull quotes, section breaks.',
    // Capabilities named in the theme picker. Keys from the shared
    // vocabulary in Themes_Manager::feature_label() are translated; anything
    // else is shown verbatim. See themes/README.md §3.
    'features' => ['blocks', 'page-transitions'],
    'author' => 'StudioEN',
    'last_updated' => '2026-09-07',
    'namespace' => 'Groove\Themes\Groove_Ebook',
    'cover_class' => 'Groove\Themes\Groove_Ebook\Cover',
    'page_class' => 'Groove\Themes\Groove_Ebook\Page',
    'fonts' => [
        'header' => [
            'css_stack'     => "'DM Sans', sans-serif",
            'google_family' => 'DM+Sans:wght@400;500;600;700',
        ],
        'body' => [
            'css_stack'     => "'DM Sans', sans-serif",
            'google_family' => 'DM+Sans:wght@400;500;600;700',
        ],
    ],
    'dependencies' => [
        'blocks.php',
    ],
];
