<?php
return [
  'name' => 'Groove Proposal',
  'default_title' => 'A new proposal',
  'thumbnail' => 'theme-thumb.png',
  'cover' => 'theme-cover.jpg',
  'image_set' => 'studio',
  'logo' => 'theme-g-logo.png',
  'description' => 'A calm, structured proposal theme for consultancies and digital agencies.',
  'author' => 'StudioEN',
  'last_updated' => '2026-03-15',
  'namespace' => 'Groove\\Themes\\Groove_Proposal',
  'cover_class' => 'Groove\\Themes\\Groove_Proposal\\Cover',
  'page_class' => 'Groove\\Themes\\Groove_Proposal\\Page',
  'fonts' => [
    'header' => [
      'css_stack'     => "'Fraunces', Georgia, 'Times New Roman', serif",
      'google_family' => 'Fraunces:ital,wght@0,300;0,400;1,300;1,400',
    ],
    'body' => [
      'css_stack'     => "'Inter', system-ui, -apple-system, sans-serif",
      'google_family' => 'Inter:wght@400;500;600',
    ],
  ],
  'dependencies' => [
    'navigation-pane.php',
    'blocks.php',
  ],
];
