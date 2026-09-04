<?php
/**
 * Pexels slot manifest.
 *
 * The frozen list of images the curator downloads, keyed by slot slug.
 * Iteration order is the order below; the slug is repeated inside each slot so
 * a subset can be passed to Curator::run() as either a map or a plain list.
 *
 * Two kinds of slot live here. The theme covers and the shared `ph-*` pool are
 * written out longhand below. The per-set placeholders are generated at the end
 * of the file from pexels/sets.php — add a role there, not a slot here.
 *
 * Slot shape:
 *   slug           string  Frozen identifier. Sample content references these.
 *   kind           string  'cover' (a theme cover) or 'placeholder' (shared pool).
 *   theme          string  Theme slug for covers; '' for placeholders.
 *   set            string  Imagery set slug, on generated set slots only.
 *   role           string  Role within that set, on generated set slots only.
 *   path           string  Destination, relative to the plugin root.
 *   query          string  Pexels search terms.
 *   fallback_query string  Used only when `query` returns nothing.
 *   orientation    string  landscape|portrait|square.
 *   src_size       string  Which `src` variant to download.
 *   color          string  Optional Pexels colour hint; dropped on a retry.
 *   ratio          array   [min, max] acceptable width/height of the source photo.
 *   note           string  Why this image exists / what it has to carry.
 *
 * Query choices follow the house design principles: muted, textural, editorial,
 * plenty of negative space where cover type sits. Nothing bright, saturated or
 * obviously "stock".
 *
 * @package Groove
 * @since 0.2.0
 */

if (!defined('ABSPATH')) {
  exit;
}

$slots = [

  // ── Theme covers ─────────────────────────────────────────────────────────
  // themes/<theme>/assets/images/theme-cover.jpg — landscape, large2x.

  'cover-folio-starter' => [
    'slug'           => 'cover-folio-starter',
    'kind'           => 'cover',
    'theme'          => 'folio-starter',
    'path'           => 'themes/folio-starter/assets/images/theme-cover.jpg',
    'query'          => 'minimal concrete wall architecture',
    'fallback_query' => 'minimalist architecture',
    'orientation'    => 'landscape',
    'src_size'       => 'large2x',
    'color'          => '',
    'ratio'          => [1.2, 2.2],
    'note'           => 'Typography-led default theme: a near-empty plane of concrete or plaster so the title has somewhere quiet to sit.',
  ],

  'cover-groove-ebook' => [
    'slug'           => 'cover-groove-ebook',
    'kind'           => 'cover',
    'theme'          => 'groove-ebook',
    'path'           => 'themes/groove-ebook/assets/images/theme-cover.jpg',
    'query'          => 'open book wooden desk soft light',
    'fallback_query' => 'open book desk',
    'orientation'    => 'landscape',
    'src_size'       => 'large2x',
    'color'          => '',
    'ratio'          => [1.2, 2.2],
    'note'           => 'A quiet reading desk. Paper and shadow, no faces, no clutter — the long-form promise of the ebook theme.',
  ],

  'cover-groove-magazine' => [
    'slug'           => 'cover-groove-magazine',
    'kind'           => 'cover',
    'theme'          => 'groove-magazine',
    'path'           => 'themes/groove-magazine/assets/images/theme-cover.jpg',
    'query'          => 'printed magazine paper texture',
    'fallback_query' => 'paper texture',
    'orientation'    => 'landscape',
    'src_size'       => 'large2x',
    'color'          => '',
    'ratio'          => [1.2, 2.2],
    'note'           => 'Editorial and tactile: printed stock, fold, grain. Texture rather than subject, so headlines stay legible over it.',
  ],

  'cover-groove-newsletter' => [
    'slug'           => 'cover-groove-newsletter',
    'kind'           => 'cover',
    'theme'          => 'groove-newsletter',
    'path'           => 'themes/groove-newsletter/assets/images/theme-cover.jpg',
    'query'          => 'morning light window interior calm',
    'fallback_query' => 'window light interior',
    'orientation'    => 'landscape',
    'src_size'       => 'large2x',
    'color'          => '',
    'ratio'          => [1.2, 2.2],
    'note'           => 'Light, airy, lived-in. Diffuse daylight across a wall gives the masthead a low-contrast field to sit on.',
  ],

  'cover-groove-proposal' => [
    'slug'           => 'cover-groove-proposal',
    'kind'           => 'cover',
    'theme'          => 'groove-proposal',
    'path'           => 'themes/groove-proposal/assets/images/theme-cover.jpg',
    'query'          => 'modern office building facade dusk',
    'fallback_query' => 'modern architecture facade',
    'orientation'    => 'landscape',
    'src_size'       => 'large2x',
    'color'          => 'blue',
    'ratio'          => [1.2, 2.2],
    'note'           => 'Muted corporate architecture at blue hour — sits with the theme’s indigo accent and warm bronze secondary without competing.',
  ],

  // ── Shared placeholder pool ──────────────────────────────────────────────
  // assets/images/pexels/<slug>.jpg — referenced from sample content as
  // GROOVE_URL . 'assets/images/pexels/<slug>.jpg'.

  'ph-workspace' => [
    'slug'           => 'ph-workspace',
    'kind'           => 'placeholder',
    'theme'          => '',
    'path'           => 'assets/images/pexels/ph-workspace.jpg',
    'query'          => 'minimal desk workspace notebook',
    'fallback_query' => 'minimal workspace',
    'orientation'    => 'landscape',
    'src_size'       => 'large',
    'color'          => '',
    'ratio'          => [1.2, 2.2],
    'note'           => 'General-purpose hero for sample pages: a working surface, tools at rest, no people.',
  ],

  'ph-team-meeting' => [
    'slug'           => 'ph-team-meeting',
    'kind'           => 'placeholder',
    'theme'          => '',
    'path'           => 'assets/images/pexels/ph-team-meeting.jpg',
    'query'          => 'small team meeting table daylight',
    'fallback_query' => 'team meeting table',
    'orientation'    => 'landscape',
    'src_size'       => 'large',
    'color'          => '',
    'ratio'          => [1.2, 2.2],
    'note'           => 'Collaboration without the cliché: people mid-conversation around a table, not celebrating at a whiteboard.',
  ],

  'ph-portrait-a' => [
    'slug'           => 'ph-portrait-a',
    'kind'           => 'placeholder',
    'theme'          => '',
    'path'           => 'assets/images/pexels/ph-portrait-a.jpg',
    'query'          => 'portrait natural light neutral background',
    'fallback_query' => 'portrait natural light',
    'orientation'    => 'portrait',
    'src_size'       => 'portrait',
    'color'          => '',
    'ratio'          => [0.55, 0.9],
    'note'           => 'Team-grid headshot 1 of 4. Even daylight, plain ground, so four of these read as one set.',
  ],

  'ph-portrait-b' => [
    'slug'           => 'ph-portrait-b',
    'kind'           => 'placeholder',
    'theme'          => '',
    'path'           => 'assets/images/pexels/ph-portrait-b.jpg',
    'query'          => 'woman portrait soft daylight plain wall',
    'fallback_query' => 'woman portrait daylight',
    'orientation'    => 'portrait',
    'src_size'       => 'portrait',
    'color'          => '',
    'ratio'          => [0.55, 0.9],
    'note'           => 'Team-grid headshot 2 of 4.',
  ],

  'ph-portrait-c' => [
    'slug'           => 'ph-portrait-c',
    'kind'           => 'placeholder',
    'theme'          => '',
    'path'           => 'assets/images/pexels/ph-portrait-c.jpg',
    'query'          => 'man portrait soft daylight plain wall',
    'fallback_query' => 'man portrait daylight',
    'orientation'    => 'portrait',
    'src_size'       => 'portrait',
    'color'          => '',
    'ratio'          => [0.55, 0.9],
    'note'           => 'Team-grid headshot 3 of 4.',
  ],

  'ph-portrait-d' => [
    'slug'           => 'ph-portrait-d',
    'kind'           => 'placeholder',
    'theme'          => '',
    'path'           => 'assets/images/pexels/ph-portrait-d.jpg',
    'query'          => 'candid studio portrait muted',
    'fallback_query' => 'studio portrait',
    'orientation'    => 'portrait',
    'src_size'       => 'portrait',
    'color'          => '',
    'ratio'          => [0.55, 0.9],
    'note'           => 'Team-grid headshot 4 of 4.',
  ],

  'ph-texture-paper' => [
    'slug'           => 'ph-texture-paper',
    'kind'           => 'placeholder',
    'theme'          => '',
    'path'           => 'assets/images/pexels/ph-texture-paper.jpg',
    'query'          => 'crumpled paper texture close up',
    'fallback_query' => 'paper texture',
    'orientation'    => 'landscape',
    'src_size'       => 'large',
    'color'          => '',
    'ratio'          => [1.1, 2.2],
    'note'           => 'Material texture for section breaks and pull-quote grounds. Should carry overlaid text at low contrast.',
  ],

  'ph-texture-muted' => [
    'slug'           => 'ph-texture-muted',
    'kind'           => 'placeholder',
    'theme'          => '',
    'path'           => 'assets/images/pexels/ph-texture-muted.jpg',
    'query'          => 'soft blurred neutral abstract background',
    'fallback_query' => 'abstract neutral background',
    'orientation'    => 'landscape',
    'src_size'       => 'large',
    'color'          => '',
    'ratio'          => [1.2, 2.2],
    'note'           => 'The blurry, monochrome, out-of-focus field — a backdrop that stays behind the words.',
  ],

  'ph-architecture' => [
    'slug'           => 'ph-architecture',
    'kind'           => 'placeholder',
    'theme'          => '',
    'path'           => 'assets/images/pexels/ph-architecture.jpg',
    'query'          => 'concrete building lines shadow',
    'fallback_query' => 'architecture lines',
    'orientation'    => 'landscape',
    'src_size'       => 'large',
    'color'          => '',
    'ratio'          => [1.2, 2.2],
    'note'           => 'Structural geometry for case-study and section imagery. Shape and shadow, not a landmark.',
  ],

  'ph-cityscape' => [
    'slug'           => 'ph-cityscape',
    'kind'           => 'placeholder',
    'theme'          => '',
    'path'           => 'assets/images/pexels/ph-cityscape.jpg',
    'query'          => 'foggy city skyline horizon',
    'fallback_query' => 'city skyline fog',
    'orientation'    => 'landscape',
    'src_size'       => 'large',
    'color'          => '',
    'ratio'          => [1.4, 2.6],
    'note'           => 'Wide urban horizon, haze flattening the depth — a banner strip rather than a subject.',
  ],

  'ph-detail-object' => [
    'slug'           => 'ph-detail-object',
    'kind'           => 'placeholder',
    'theme'          => '',
    'path'           => 'assets/images/pexels/ph-detail-object.jpg',
    'query'          => 'minimal still life object neutral',
    'fallback_query' => 'still life minimal',
    'orientation'    => 'landscape',
    'src_size'       => 'large',
    'color'          => '',
    'ratio'          => [0.9, 1.5],
    'note'           => 'Product / still-life detail for feature cards. Deliberately near-square so it crops well in a grid cell.',
  ],

  'ph-reading' => [
    'slug'           => 'ph-reading',
    'kind'           => 'placeholder',
    'theme'          => '',
    'path'           => 'assets/images/pexels/ph-reading.jpg',
    'query'          => 'hands holding book reading quiet',
    'fallback_query' => 'reading book',
    'orientation'    => 'landscape',
    'src_size'       => 'large',
    'color'          => '',
    'ratio'          => [1.2, 2.2],
    'note'           => 'The reading moment — used by the ebook and newsletter sample content.',
  ],

  'ph-landscape-wide' => [
    'slug'           => 'ph-landscape-wide',
    'kind'           => 'placeholder',
    'theme'          => '',
    'path'           => 'assets/images/pexels/ph-landscape-wide.jpg',
    'query'          => 'misty landscape muted horizon',
    'fallback_query' => 'foggy landscape',
    'orientation'    => 'landscape',
    'src_size'       => 'large2x',
    'color'          => '',
    'ratio'          => [1.4, 2.6],
    'note'           => 'Full-bleed editorial landscape. Mist keeps it low-contrast, which is what makes it survivable behind type.',
  ],

  'ph-studio' => [
    'slug'           => 'ph-studio',
    'kind'           => 'placeholder',
    'theme'          => '',
    'path'           => 'assets/images/pexels/ph-studio.jpg',
    'query'          => 'craft workshop studio hands working',
    'fallback_query' => 'workshop studio craft',
    'orientation'    => 'landscape',
    'src_size'       => 'large',
    'color'          => '',
    'ratio'          => [1.2, 2.2],
    'note'           => 'Making and process — the "how we work" slot in sample proposals and case studies.',
  ],

];

// ── Per-set placeholder slots ──────────────────────────────────────────────
// Generated from pexels/sets.php. Each set contributes one slot per role it
// declares, so adding a role there is the only edit needed to curate a new
// image. Slugs are `<set>-<role>` and files land flat beside the shared pool,
// which keeps slug and filename the same string for every slug-keyed helper.
//
// The shared `ph-*` pool above is deliberately kept. Folios seeded before sets
// existed have those URLs written into post_content, so removing the files
// would break imagery in content that is already saved.

$sets = require __DIR__ . '/sets.php';

foreach ($sets as $set_slug => $set) {
  if (empty($set['roles']) || !is_array($set['roles'])) {
    continue;
  }

  foreach ($set['roles'] as $role => $role_def) {
    $slug = $set_slug . '-' . $role;

    $slots[$slug] = [
      'slug'           => $slug,
      'kind'           => 'placeholder',
      'theme'          => '',
      'set'            => $set_slug,
      'role'           => $role,
      'path'           => 'assets/images/pexels/' . $slug . '.jpg',
      'query'          => isset($role_def['query']) ? (string) $role_def['query'] : '',
      'fallback_query' => isset($role_def['fallback_query']) ? (string) $role_def['fallback_query'] : '',
      'orientation'    => isset($role_def['orientation']) ? (string) $role_def['orientation'] : 'landscape',
      'src_size'       => isset($role_def['src_size']) ? (string) $role_def['src_size'] : 'large',
      'color'          => isset($role_def['color']) ? (string) $role_def['color'] : '',
      'ratio'          => isset($role_def['ratio']) ? (array) $role_def['ratio'] : [1.2, 2.2],
      'note'           => isset($role_def['note']) ? (string) $role_def['note'] : '',
    ];
  }
}

return $slots;
