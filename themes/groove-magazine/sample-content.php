<?php
/**
 * Sample content for the Groove Magazine theme.
 *
 * Returned to Themes_Manager::get_sample_content() and consumed by the
 * "Add New" folio flow. Keys:
 *   label       Checkbox label shown on the Add New screen.
 *   description Helper copy beneath the checkbox.
 *   subtitle    Value written to the folio's `subtitle` meta.
 *   folio_meta  Extra folio meta written when the seed runs.
 *   pages       Ordered list of ['title', 'content', 'feature_image'] pages.
 *
 * Groove Magazine registers no custom blocks, so the bodies are core Gutenberg.
 * It does, however, build each story's hero and its adaptive colorway from the
 * page's featured image, so every page here names a placeholder slug in
 * `feature_image`. The seeder attaches that file when curation has already
 * downloaded it and silently skips the thumbnail when it has not.
 *
 * @package Groove
 */

use Groove\Themes\Themes_Manager;

if (!defined('ABSPATH')) {
  exit;
}

// Placeholder imagery is asked for by role, not by filename. `image_set` in
// this theme's setup.php decides which photographic register those roles
// resolve to, so two themes can seed the same layout with different pictures.
$theme = 'groove-magazine';

$img_hero       = Themes_Manager::theme_image_url($theme, 'hero');
$img_wide       = Themes_Manager::theme_image_url($theme, 'wide');
$img_process    = Themes_Manager::theme_image_url($theme, 'process');
$img_detail     = Themes_Manager::theme_image_url($theme, 'detail');
$img_scene      = Themes_Manager::theme_image_url($theme, 'scene');
$img_portrait_a = Themes_Manager::theme_image_url($theme, 'portrait-a');
$img_portrait_b = Themes_Manager::theme_image_url($theme, 'portrait-b');
$img_portrait_c = Themes_Manager::theme_image_url($theme, 'portrait-c');
$img_portrait_d = Themes_Manager::theme_image_url($theme, 'portrait-d');

$cap_hero    = Themes_Manager::theme_image_caption($theme, 'hero');
$cap_wide    = Themes_Manager::theme_image_caption($theme, 'wide');
$cap_process = Themes_Manager::theme_image_caption($theme, 'process');
$cap_detail  = Themes_Manager::theme_image_caption($theme, 'detail');
$cap_scene   = Themes_Manager::theme_image_caption($theme, 'scene');

return array(
  'label'       => __('Create with sample magazine content', 'groove-folios'),
  'description' => __('Seeds a four-story issue with hero images, a studio visit, a photo essay and a contributors page.', 'groove-folios'),
  'subtitle'    => __('Issue 04 — The Quiet Cities', 'groove-folios'),
  'folio_meta'  => array(),
  'pages'       => array(
    array(
      'title' => __('The Quiet Cities', 'groove-folios'),
      'feature_image' => Themes_Manager::sample_image_slug($theme, 'hero'),
      'content' => (string) <<<HTML
<!-- wp:paragraph {"dropCap":true} -->
<p class="has-drop-cap">Three years ago the city of Almerin removed forty per cent of its street signage. Not the road names — those stayed — but the instructions: the arrows, the warnings, the little rectangles telling you what you were not allowed to do. Traffic incidents fell by a fifth in the first year and have not risen since.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>The removal</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>The programme was not sold as an experiment in psychology. It was sold as a maintenance saving. Every sign in Almerin had to be cleaned twice a year and replaced every eleven, and the transport department had been quietly over budget since the mid-nineties. Cutting the estate by two-fifths solved a spreadsheet problem. That it also changed how people drove was, by the department's own account, a surprise.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>What the drivers describe, when you ask them, is a kind of alertness. With nothing telling them what to expect, they look. They make eye contact at junctions. They slow at the top of a hill because they cannot see over it and nobody has promised them that it is safe.</p>
<!-- /wp:paragraph -->
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_scene}" alt="An empty city junction at dusk with no signage or road markings"/>{$cap_scene}</figure>
<!-- /wp:image -->
<!-- wp:heading {"level":2} -->
<h2>What the data actually shows</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Almerin's figures are good but they are not clean. The removal coincided with a fuel price shock and a new tram line, and the department has never separated the three. Two independent studies have tried; one found the effect halved, the other found it intact. Both agree the direction of travel.</p>
<!-- /wp:paragraph -->
<!-- wp:pullquote -->
<figure class="wp-block-pullquote"><blockquote><p>We did not make the streets safer. We stopped telling people they were safe, and they started behaving as though they were not.</p><cite>Transport department, internal review</cite></blockquote></figure>
<!-- /wp:pullquote -->
<!-- wp:heading {"level":2} -->
<h2>Elsewhere</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Four other cities have since run versions of the same programme, with results ranging from a modest improvement to none at all. The variable that seems to matter most is not the signage. It is whether the streets were narrow enough, before the removal, that a driver had to look anyway.</p>
<!-- /wp:paragraph -->
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_hero}" alt="A wide view over rooftops towards a low horizon"/>{$cap_hero}</figure>
<!-- /wp:image -->
HTML,
    ),
    array(
      'title' => __('Studio Visit: Making Slowly', 'groove-folios'),
      'feature_image' => Themes_Manager::sample_image_slug($theme, 'process'),
      'content' => (string) <<<HTML
<!-- wp:paragraph {"dropCap":true} -->
<p class="has-drop-cap">The workshop is above a tyre fitter and smells faintly of both trades. Ilse Marchetti has worked here for nineteen years and produces, by her own count, somewhere between eleven and fourteen finished pieces annually. She is not interested in producing more.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>The bench</h2>
<!-- /wp:heading -->
<!-- wp:media-text {"mediaType":"image","mediaWidth":50} -->
<div class="wp-block-media-text is-stacked-on-mobile" style="grid-template-columns:50% auto"><figure class="wp-block-media-text__media"><img src="{$img_process}" alt="Ilse Marchetti's workbench with tools arranged along the back edge"/></figure><div class="wp-block-media-text__content"><!-- wp:paragraph -->
<p>Everything on the bench is within one arm's reach and has been in the same position for a decade. She has never drawn a plan of it. When she moved buildings in 2011 she photographed the bench, rebuilt it exactly, and threw the photograph away.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>"You cannot think about the work and think about where the small file is," she says. "One of those has to be free."</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:media-text -->
<!-- wp:heading {"level":2} -->
<h2>On commissions</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>She takes two a year and turns down roughly thirty. The criterion is not money and not prestige; it is whether the client has already decided what they want. "If they know, they should buy something. If they do not know, we can find out together. The impossible one is the client who half knows."</p>
<!-- /wp:paragraph -->
<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p>People ask how long a piece takes. Six weeks, usually. Nineteen years and six weeks, if you want the real answer.</p>
<!-- /wp:paragraph --><cite>Ilse Marchetti</cite></blockquote>
<!-- /wp:quote -->
<!-- wp:heading {"level":2} -->
<h2>What she keeps</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Along the far wall is a shelf of failures — pieces that cracked, warped, or simply came out wrong. She has kept every one since 2004. It is the only part of the studio she was reluctant to have photographed, and the only part she talked about without being asked.</p>
<!-- /wp:paragraph -->
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_detail}" alt="A single finished piece photographed against a neutral ground"/>{$cap_detail}</figure>
<!-- /wp:image -->
HTML,
    ),
    array(
      'title' => __('Field Notes from the Edge of the Map', 'groove-folios'),
      'feature_image' => Themes_Manager::sample_image_slug($theme, 'wide'),
      'content' => (string) <<<HTML
<!-- wp:paragraph {"dropCap":true} -->
<p class="has-drop-cap">A photo essay, made over eleven days walking the boundary of a national survey area that has not been formally resurveyed since 1974. The line exists on paper. On the ground it runs through a caravan park, a reservoir and, for about four hundred metres, somebody's kitchen.</p>
<!-- /wp:paragraph -->
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_wide}" alt="A wide, low-contrast landscape with the survey line running out of frame"/>{$cap_wide}</figure>
<!-- /wp:image -->
<!-- wp:heading {"level":2} -->
<h2>Day three</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>The boundary is marked, where it is marked at all, by cast iron posts about a metre high. Eleven of the original sixty-two are still standing. Four have been moved by farmers, which is illegal and universally understood to be unenforceable. One is now the gatepost of a house whose owner was delighted to be asked about it.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>Day seven</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Reservoir day. The line crosses open water for one and a half kilometres and there is no lawful way to follow it, so I walked the shore and photographed the gap. The resulting images are the least interesting in the sequence and the ones I have thought about most.</p>
<!-- /wp:paragraph -->
<!-- wp:gallery {"columns":2,"linkTo":"none"} -->
<figure class="wp-block-gallery has-nested-images columns-2 is-cropped"><!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_hero}" alt="The survey line entering the outskirts of a town"/></figure>
<!-- /wp:image -->
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_scene}" alt="A boundary post absorbed into a later wall"/></figure>
<!-- /wp:image --></figure>
<!-- /wp:gallery -->
<!-- wp:heading {"level":2} -->
<h2>Day eleven</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>The line ends where it began, which is the only claim about it I can make with confidence. Everything between those two points is an agreement that nobody alive was present for, maintained by the fact that no one has yet had a reason to argue.</p>
<!-- /wp:paragraph -->
HTML,
    ),
    array(
      'title' => __('Contributors', 'groove-folios'),
      'feature_image' => Themes_Manager::sample_image_slug($theme, 'people'),
      'content' => (string) <<<HTML
<!-- wp:paragraph -->
<p>Four people made this issue. Two of them have met.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>This issue</h2>
<!-- /wp:heading -->
<!-- wp:gallery {"columns":4,"linkTo":"none"} -->
<figure class="wp-block-gallery has-nested-images columns-4 is-cropped"><!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_portrait_a}" alt="Portrait of contributor Rin Adeyemi"/></figure>
<!-- /wp:image -->
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_portrait_b}" alt="Portrait of contributor Tomas Vlk"/></figure>
<!-- /wp:image -->
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_portrait_c}" alt="Portrait of contributor Sofia Rees"/></figure>
<!-- /wp:image -->
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_portrait_d}" alt="Portrait of contributor Amara Osei"/></figure>
<!-- /wp:image --></figure>
<!-- /wp:gallery -->
<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph -->
<p><strong>Rin Adeyemi</strong> reports on infrastructure and spent most of this commission being told that the data did not exist. It did.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><strong>Tomas Vlk</strong> photographed the studio visit. He owns four cameras and used the oldest.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph -->
<p><strong>Sofia Rees</strong> walked the survey boundary and has asked us to note that the kitchen was, in the end, very welcoming.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><strong>Amara Osei</strong> edits this publication and wrote the headline she liked least.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- wp:heading {"level":2} -->
<h2>Next issue</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Issue 05 is about repair: who does it, why it is nearly always cheaper than replacement, and why almost nobody chooses it anyway. Out in eight weeks.</p>
<!-- /wp:paragraph -->
HTML,
    ),
  ),
);
