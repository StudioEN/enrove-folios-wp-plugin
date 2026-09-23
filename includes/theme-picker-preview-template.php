<?php
/**
 * Theme Picker Preview Template
 *
 * Renders a live theme preview with dummy content, no real folio post required.
 * Triggered by ?groove_theme_preview=<theme-id> in plugin.php's template_redirect hook.
 * Requires: admin capability + valid nonce.
 */

use Groove\Themes\Themes_Manager;

if (!defined('ABSPATH')) {
  exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Required from inside a closure (Plugin's template_redirect handler), so these variables are local to it, not globals.

// ── Auth ─────────────────────────────────────────────────────────────────────

if (!current_user_can('edit_posts')) {
  status_header(403);
  exit;
}

$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
if (!wp_verify_nonce($nonce, 'groove_theme_preview')) {
  status_header(403);
  exit;
}

// ── Params ────────────────────────────────────────────────────────────────────

$theme_id = isset($_GET['groove_theme_preview']) ? sanitize_key(wp_unslash($_GET['groove_theme_preview'])) : '';
if (empty($theme_id) || !Themes_Manager::has($theme_id)) {
  status_header(404);
  exit;
}

$view       = isset($_GET['groove_preview_view']) ? sanitize_key(wp_unslash($_GET['groove_preview_view'])) : 'cover';
$page_index = isset($_GET['groove_preview_page']) ? intval(wp_unslash($_GET['groove_preview_page'])) : 0;
$page_index = max(0, min(1, $page_index));

// ── Dummy data ────────────────────────────────────────────────────────────────

$dummy_folio            = new \stdClass();
$dummy_folio->ID        = 0;
$dummy_folio->post_title  = 'Sample Folio';
$dummy_folio->post_name   = 'sample-folio';
$dummy_folio->post_status = 'publish';
$dummy_folio->post_author = 0;

$page0_content = implode("\n", [
  '<!-- wp:heading {"level":2} --><h2>Overview</h2><!-- /wp:heading -->',
  '<!-- wp:paragraph --><p>This is where your content lives. Each page in a Groove Folio can contain rich text, images, and structured sections that keep readers engaged.</p><!-- /wp:paragraph -->',
  '<!-- wp:heading {"level":2} --><h2>Key Points</h2><!-- /wp:heading -->',
  '<!-- wp:paragraph --><p>Groove Folios gives you a clean, professional canvas to present your work, proposals, reports, and ideas to clients and collaborators anywhere.</p><!-- /wp:paragraph -->',
  '<!-- wp:list --><ul><li>Clear, distraction-free layout</li><li>Optimised for reading on any device</li><li>Easy to update and share</li></ul><!-- /wp:list -->',
]);

$page1_content = implode("\n", [
  '<!-- wp:heading {"level":2} --><h2>Approach</h2><!-- /wp:heading -->',
  '<!-- wp:paragraph --><p>Your second page can go deeper into specifics — scope, methodology, timeline, or whatever structure best serves the document you are building.</p><!-- /wp:paragraph -->',
  '<!-- wp:heading {"level":2} --><h2>What to Expect</h2><!-- /wp:heading -->',
  '<!-- wp:paragraph --><p>Each page is independently scrollable with a consistent navigation rail that keeps readers oriented throughout the folio at all times.</p><!-- /wp:paragraph -->',
  '<!-- wp:list --><ul><li>Structured navigation between pages</li><li>Section headings auto-linked in the sidebar</li><li>Consistent header and footer across all pages</li></ul><!-- /wp:list -->',
]);

$make_page = function (int $id, string $title, string $content): \stdClass {
  $p               = new \stdClass();
  $p->ID           = $id;
  $p->post_title   = $title;
  $p->post_content = $content;
  $p->post_status  = 'publish';
  $p->post_author  = 0;
  $p->filter       = 'raw';
  return $p;
};

$dummy_pages = [
  $make_page(-1, 'Introduction', $page0_content),
  $make_page(-2, 'Details',      $page1_content),
];

// ── Theme descriptor ──────────────────────────────────────────────────────────

$all_themes     = Themes_Manager::get_all_themes();
$theme_desc     = $all_themes[$theme_id] ?? [];

// ── Instantiate ───────────────────────────────────────────────────────────────

$theme = ($view === 'cover')
  ? Themes_Manager::create_cover_theme($theme_id)
  : Themes_Manager::create_page_theme($theme_id);

if (!$theme) {
  status_header(404);
  exit;
}

// ── Inject preview data ───────────────────────────────────────────────────────

$theme->is_preview_mode  = true;
$theme->title            = 'Sample Folio';
$theme->subtitle         = 'A short subtitle for this folio';
$theme->author           = 'Jane Smith';
$theme->copyright        = '© ' . (int) wp_date('Y') . ' Your Company';
$theme->feature_image    = '';
$theme->pages            = $dummy_pages;
$theme->folio            = $dummy_folio;
$theme->folio_id         = 0;
$theme->theme_id         = $theme_id;
$theme->theme_name       = $theme_desc['name'] ?? $theme_id;
$theme->theme_cover_url  = $theme_desc['cover_url'] ?? '';
$theme->theme_logo_url   = $theme_desc['logo_url'] ?? '';
$theme->show_logo        = true;

// Proposal cover: inject dummy meta so the cover renders fully.
if (property_exists($theme, 'proposal_meta')) {
  $theme->proposal_meta = [
    'proposal_version'       => 'v1.0',
    'proposal_status'        => 'Draft',
    'proposal_prepared_for'  => 'Client Name',
    'proposal_prepared_by'   => 'Your Agency',
    'proposal_client_name'   => 'Client Name',
    'proposal_contact_name'  => 'Engagement Lead',
    'proposal_contact_role'  => 'Principal Consultant',
    'proposal_contact_email' => 'hello@example.com',
    'proposal_contact_phone' => '+1 (555) 010-2020',
    'proposal_date'          => (string) wp_date('Y-m-d'),
    'proposal_color_scheme'  => 'default',
    'proposal_client_logo_url' => '',
    'proposal_agency_logo_url' => '',
  ];
}

if ($view === 'page') {
  $current_page       = $dummy_pages[$page_index];
  $theme->id          = $current_page->ID;
  $theme->page        = $current_page;
  $theme->title       = $current_page->post_title;
  $theme->content     = $current_page->post_content;
} else {
  $theme->id   = 0;
  $theme->page = null;
  $theme->content = '';
}

// Proposal page: set show_in_page_nav default.
if (property_exists($theme, 'show_in_page_nav')) {
  $theme->show_in_page_nav = true;
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <?php wp_head(); ?>
  <style>
    html { margin-top: 0 !important; }
    /* Disable all internal navigation links — this is a read-only preview */
    .g-folio__theme-fields-submit,
    .g-folio__theme-page-nav-item-link,
    .g-folio__theme-nav-item-link,
    .g-folio__theme-page-prev a,
    .g-folio__theme-page-next a {
      pointer-events: none;
      cursor: default;
    }
  </style>
</head>
<body <?php body_class('groove'); ?>>
  <?php $theme->display_theme(); ?>
  <?php wp_footer(); ?>
</body>
</html>
