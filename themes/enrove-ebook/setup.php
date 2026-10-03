<?php
return [
    'name' => 'Enrove eBook',
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
    // Colours the password gate borrows so it looks like the folio behind
    // it. These used to live in two hardcoded maps in plugin source.
    'gate' => [
        'accent' => '#1D35B4',
        'accent_hover' => '#162a90',
        'background' => '#1D35B4',
    ],
    'namespace' => 'Enrove\Themes\Enrove_Ebook',
    'cover_class' => 'Enrove\Themes\Enrove_Ebook\Cover',
    'page_class' => 'Enrove\Themes\Enrove_Ebook\Page',
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
