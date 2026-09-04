<?php
/**
 * Pexels imagery sets.
 *
 * A *set* is a coherent look — a photographic register that suits one kind of
 * folio. A *role* is what an image has to do inside sample content: the opener,
 * the texture, the face in a team grid. Sample content asks for a role; the
 * theme's set decides what that role looks like.
 *
 * The point of the split is that a magazine's "hero" and an eBook's "hero" are
 * the same job and completely different photographs. Before this file every
 * theme drew from one shared pool, so seeding a proposal and seeding a
 * newsletter produced the same imagery.
 *
 * Sets are deliberately not one-per-theme. A new theme picks an existing set
 * when the register already fits, and only earns its own when it genuinely
 * looks like nothing else — that is why `studio` serves two themes.
 *
 * Set shape:
 *   label  string  Human name, for CLI output.
 *   note   string  The register in a sentence. Read this before adding a role.
 *   roles  array   role slug => role definition.
 *
 * Role definition — the manifest slot fields, minus the ones this file derives:
 *   query           string  Pexels search terms.
 *   fallback_query  string  Used only when `query` returns nothing.
 *   orientation     string  landscape|portrait|square.
 *   src_size        string  Which `src` variant to download.
 *   color           string  Optional Pexels colour hint; dropped on a retry.
 *   ratio           array   [min, max] acceptable width/height of the source.
 *   legacy          string  Shared-pool slug this role replaces. Used as a
 *                           fallback so an install that curated the old pool
 *                           but not the sets still renders a photograph.
 *   note            string  Why this image exists / what it has to carry.
 *
 * Curated files land flat in assets/images/pexels/<set>-<role>.jpg, so the
 * manifest slug and the filename stay the same string and every existing
 * slug-keyed helper (paths, URLs, credits) works unchanged.
 *
 * Query choices follow the house design principles: muted, textural, editorial,
 * plenty of negative space where type sits. Nothing bright, saturated or
 * obviously "stock".
 *
 * @package Groove
 * @since 0.3.0
 */

if (!defined('ABSPATH')) {
  exit;
}

return [

  // ── studio ───────────────────────────────────────────────────────────────
  // folio-starter (typography-led) and groove-proposal (indigo + bronze).
  // Both want architecture and material rather than people or incident.

  'studio' => [
    'label' => 'Studio',
    'note'  => 'Material and architectural. Concrete, plaster, matte steel, a working surface with the tools at rest. Human traces, rarely a face.',
    'roles' => [

      'hero' => [
        'query'          => 'architect desk drawings minimal',
        'fallback_query' => 'minimal workspace desk',
        'orientation'    => 'landscape',
        'src_size'       => 'large',
        'color'          => '',
        'ratio'          => [1.2, 2.2],
        'legacy'         => 'ph-workspace',
        'note'           => 'The opener. A surface mid-work; someone at the desk is fine, a posed portrait is not.',
      ],

      'scene' => [
        'query'          => 'concrete stairwell shadow minimal',
        'fallback_query' => 'concrete architecture shadow',
        'orientation'    => 'landscape',
        'src_size'       => 'large',
        'color'          => '',
        'ratio'          => [1.2, 2.2],
        'legacy'         => 'ph-architecture',
        'note'           => 'Structural geometry for section imagery. Shape and shadow, not a landmark.',
      ],

      'detail' => [
        'query'          => 'minimal still life ceramic neutral',
        'fallback_query' => 'still life minimal object',
        'orientation'    => 'landscape',
        'src_size'       => 'large',
        'color'          => '',
        'ratio'          => [0.9, 1.5],
        'legacy'         => 'ph-detail-object',
        'note'           => 'Near-square so it crops well in a feature-card grid cell.',
      ],

      'process' => [
        'query'          => 'hands working model workshop',
        'fallback_query' => 'workshop hands making',
        'orientation'    => 'landscape',
        'src_size'       => 'large',
        'color'          => '',
        'ratio'          => [1.2, 2.2],
        'legacy'         => 'ph-studio',
        'note'           => 'The "how we work" slot. Hands and materials, mid-task.',
      ],

      'texture' => [
        'query'          => 'raw concrete wall texture',
        'fallback_query' => 'concrete texture',
        'orientation'    => 'landscape',
        'src_size'       => 'large',
        'color'          => '',
        'ratio'          => [1.1, 2.2],
        'legacy'         => 'ph-texture-paper',
        'note'           => 'Section breaks and pull-quote grounds. Must carry overlaid text at low contrast.',
      ],

      'portrait-a' => [
        'query'          => 'artist portrait studio natural light',
      'fallback_query' => 'maker portrait workshop',
        'orientation'    => 'portrait',
        'src_size'       => 'portrait',
        'color'          => '',
        'ratio'          => [0.55, 0.9],
        'legacy'         => 'ph-portrait-a',
        'note'           => 'A single author or contributor, in their own space. Environmental rather than a plain-ground headshot — this set has no other faces to match.',
      ],

    ],
  ],

  // ── paper ────────────────────────────────────────────────────────────────
  // groove-ebook. Print, ink and the reading moment; warm rather than cool.

  'paper' => [
    'label' => 'Paper',
    'note'  => 'Print and the reading moment. Book pages, ink, letterpress, warm paper tone. Quiet and close-up; nothing wide or civic.',
    'roles' => [

      'hero' => [
        'query'          => 'open book pages soft window light',
        'fallback_query' => 'open book reading light',
        'orientation'    => 'landscape',
        'src_size'       => 'large',
        'color'          => '',
        'ratio'          => [1.2, 2.2],
        'legacy'         => 'ph-reading',
        'note'           => 'The opener. Paper and shadow, no faces, no clutter.',
      ],

      'texture' => [
        'query'          => 'old book page paper texture',
        'fallback_query' => 'paper texture close up',
        'orientation'    => 'landscape',
        'src_size'       => 'large',
        'color'          => '',
        'ratio'          => [1.1, 2.2],
        'legacy'         => 'ph-texture-paper',
        'note'           => 'Plate and pull-quote ground. Grain, fold, foxing.',
      ],

      'process' => [
        'query'          => 'letterpress printing type workshop',
        'fallback_query' => 'printing press type',
        'orientation'    => 'landscape',
        'src_size'       => 'large',
        'color'          => '',
        'ratio'          => [1.2, 2.2],
        'legacy'         => 'ph-studio',
        'note'           => 'Making the book rather than reading it — the colophon image.',
      ],

      'backdrop' => [
        'query'          => 'out of focus neutral beige wall',
      'fallback_query' => 'blurred neutral background',
        'orientation'    => 'landscape',
        'src_size'       => 'large',
        'color'          => '',
        'ratio'          => [1.2, 2.2],
        'legacy'         => 'ph-texture-muted',
        'note'           => 'The out-of-focus field that stays behind the words.',
      ],

    ],
  ],

  // ── editorial ────────────────────────────────────────────────────────────
  // groove-magazine. Culture and street; the only set where faces carry weight.

  'editorial' => [
    'label' => 'Editorial',
    'note'  => 'Culture, street and gallery. Wide muted horizons, brutalist geometry, and portraits with enough presence to run at column width.',
    'roles' => [

      'hero' => [
        'query'          => 'foggy city street morning muted',
        'fallback_query' => 'city street fog',
        'orientation'    => 'landscape',
        'src_size'       => 'large',
        'color'          => '',
        'ratio'          => [1.2, 2.2],
        'legacy'         => 'ph-cityscape',
        'note'           => 'The cover story image. Haze flattens the depth so a headline survives on top.',
      ],

      'wide' => [
        'query'          => 'misty mountain horizon muted wide',
        'fallback_query' => 'foggy landscape horizon',
        'orientation'    => 'landscape',
        'src_size'       => 'large2x',
        'color'          => '',
        'ratio'          => [1.4, 2.6],
        'legacy'         => 'ph-landscape-wide',
        'note'           => 'Full-bleed banner. Mist keeps it low-contrast, which is what makes it survivable behind type.',
      ],

      'scene' => [
        'query'          => 'brutalist facade geometry shadow',
        'fallback_query' => 'brutalist architecture',
        'orientation'    => 'landscape',
        'src_size'       => 'large',
        'color'          => '',
        'ratio'          => [1.2, 2.2],
        'legacy'         => 'ph-architecture',
        'note'           => 'The architecture feature. Repetition and raked light.',
      ],

      'process' => [
        'query'          => 'artist studio painting process',
        'fallback_query' => 'artist studio',
        'orientation'    => 'landscape',
        'src_size'       => 'large',
        'color'          => '',
        'ratio'          => [1.2, 2.2],
        'legacy'         => 'ph-studio',
        'note'           => 'The studio-visit piece. Work in progress, not a finished wall.',
      ],

      'people' => [
        'query'          => 'people looking at art gallery',
        'fallback_query' => 'gallery visitors art',
        'orientation'    => 'landscape',
        'src_size'       => 'large',
        'color'          => '',
        'ratio'          => [1.2, 2.2],
        'legacy'         => 'ph-team-meeting',
        'note'           => 'People in the presence of the work, seen from behind or in profile — attention rather than eye contact.',
      ],

      'detail' => [
        'query'          => 'sculpture detail gallery neutral',
        'fallback_query' => 'gallery sculpture detail',
        'orientation'    => 'landscape',
        'src_size'       => 'large',
        'color'          => '',
        'ratio'          => [0.9, 1.5],
        'legacy'         => 'ph-detail-object',
        'note'           => 'Near-square review thumbnail.',
      ],

      'portrait-a' => [
        'query'          => 'editorial portrait natural light muted',
        'fallback_query' => 'portrait natural light',
        'orientation'    => 'portrait',
        'src_size'       => 'portrait',
        'color'          => '',
        'ratio'          => [0.55, 0.9],
        'legacy'         => 'ph-portrait-a',
        'note'           => 'Contributor grid 1 of 4. Even light and plain ground, so four read as one set.',
      ],

      'portrait-b' => [
        'query'          => 'woman editorial portrait muted background',
      'fallback_query' => 'woman headshot studio',
        'orientation'    => 'portrait',
        'src_size'       => 'portrait',
        'color'          => '',
        'ratio'          => [0.55, 0.9],
        'legacy'         => 'ph-portrait-b',
        'note'           => 'Contributor grid 2 of 4.',
      ],

      'portrait-c' => [
        'query'          => 'man editorial portrait muted background',
        'fallback_query' => 'man portrait daylight',
        'orientation'    => 'portrait',
        'src_size'       => 'portrait',
        'color'          => '',
        'ratio'          => [0.55, 0.9],
        'legacy'         => 'ph-portrait-c',
        'note'           => 'Contributor grid 3 of 4.',
      ],

      'portrait-d' => [
        'query'          => 'editorial portrait studio muted background',
      'fallback_query' => 'studio headshot',
        'orientation'    => 'portrait',
        'src_size'       => 'portrait',
        'color'          => '',
        'ratio'          => [0.55, 0.9],
        'legacy'         => 'ph-portrait-d',
        'note'           => 'Contributor grid 4 of 4.',
      ],

    ],
  ],

  // ── daylight ─────────────────────────────────────────────────────────────
  // groove-newsletter. Domestic and warm — the one set that is allowed to be
  // friendly, because a newsletter arrives in someone's morning.

  'daylight' => [
    'label' => 'Daylight',
    'note'  => 'Lived-in interiors in morning light. Kitchen tables, plants, a cafe conversation. Warm and low-contrast; the only set where people look at each other.',
    'roles' => [

      'hero' => [
        'query'          => 'sunlit kitchen table morning calm',
        'fallback_query' => 'morning light table interior',
        'orientation'    => 'landscape',
        'src_size'       => 'large',
        'color'          => '',
        'ratio'          => [1.2, 2.2],
        'legacy'         => 'ph-workspace',
        'note'           => 'The masthead image. Diffuse daylight across a surface.',
      ],

      'scene' => [
        'query'          => 'reading chair window plants home',
        'fallback_query' => 'window light interior plants',
        'orientation'    => 'landscape',
        'src_size'       => 'large',
        'color'          => '',
        'ratio'          => [1.2, 2.2],
        'legacy'         => 'ph-reading',
        'note'           => 'A room someone actually uses. Sets the domestic register.',
      ],

      'people' => [
        'query'          => 'friends talking kitchen home daylight',
      'fallback_query' => 'people talking at home',
        'orientation'    => 'landscape',
        'src_size'       => 'large',
        'color'          => '',
        'ratio'          => [1.2, 2.2],
        'legacy'         => 'ph-team-meeting',
        'note'           => 'Conversation without the cliche: mid-sentence, not celebrating at a whiteboard.',
      ],

      'backdrop' => [
        'query'          => 'soft sunlight wall shadow blurred',
        'fallback_query' => 'sunlight wall shadow',
        'orientation'    => 'landscape',
        'src_size'       => 'large',
        'color'          => '',
        'ratio'          => [1.2, 2.2],
        'legacy'         => 'ph-texture-muted',
        'note'           => 'Low-contrast field for a quote or a sign-off block.',
      ],

      'portrait-a' => [
        'query'          => 'woman headshot soft diffused daylight neutral',
      'fallback_query' => 'woman headshot soft light',
        'orientation'    => 'portrait',
        'src_size'       => 'portrait',
        'color'          => '',
        'ratio'          => [0.55, 0.9],
        'legacy'         => 'ph-portrait-a',
        'note'           => 'Contributor grid 1 of 3. Warmer and closer than the editorial set.',
      ],

      'portrait-b' => [
        'query'          => 'headshot smiling soft daylight plain background',
      'fallback_query' => 'smiling headshot plain background',
        'orientation'    => 'portrait',
        'src_size'       => 'portrait',
        'color'          => '',
        'ratio'          => [0.55, 0.9],
        'legacy'         => 'ph-portrait-b',
        'note'           => 'Contributor grid 2 of 3.',
      ],

      'portrait-c' => [
        'query'          => 'man headshot soft daylight plain background',
      'fallback_query' => 'man headshot plain background',
        'orientation'    => 'portrait',
        'src_size'       => 'portrait',
        'color'          => '',
        'ratio'          => [0.55, 0.9],
        'legacy'         => 'ph-portrait-c',
        'note'           => 'Contributor grid 3 of 3.',
      ],

    ],
  ],

];
