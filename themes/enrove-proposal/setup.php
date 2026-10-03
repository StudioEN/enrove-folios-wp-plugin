<?php
return [
  'name' => 'Enrove Proposal',
  'default_title' => 'A New Proposal',
  'thumbnail' => 'theme-thumb.png',
  'cover' => 'theme-cover.jpg',
  'image_set' => 'studio',
  'logo' => 'theme-g-logo.png',
  'description' => 'A calm, structured theme with fourteen content blocks — pricing tables, timelines, case studies, key metrics — a light and a dark mode, and an accent taken from the cover image.',
  // Capabilities named in the theme picker. Keys from the shared
  // vocabulary in Themes_Manager::feature_label() are translated; anything
  // else is shown verbatim. See themes/README.md §3.
  'features' => ['blocks', 'dynamic-color', 'light-dark'],
  'author' => 'StudioEN',
  'last_updated' => '2026-03-15',
  // Colours the password gate borrows so it looks like the folio behind
  // it. These used to live in two hardcoded maps in plugin source.
  'gate' => [
      'accent' => '#27498c',
      'accent_hover' => '#1a4173',
      'background' => '#ededeb',
  ],
  'namespace' => 'Enrove\\Themes\\Enrove_Proposal',
  'cover_class' => 'Enrove\\Themes\\Enrove_Proposal\\Cover',
  'page_class' => 'Enrove\\Themes\\Enrove_Proposal\\Page',
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
