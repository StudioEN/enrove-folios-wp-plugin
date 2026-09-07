<?php
return [
    'name' => 'Groove eBook',
    'default_title' => 'A New eBook',
    'thumbnail' => 'theme-thumb.png',
    'cover' => 'theme-cover.jpg',
    'image_set' => 'paper',
    'logo' => 'theme-g-logo.png',
    'description' => 'A structured reading theme for long-form eBooks: numbered contents, a frosted chapter bar, and a per-page outline beside the text.',
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
];
