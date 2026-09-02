<?php
/**
 * Sample content for the Folio Starter theme.
 *
 * Returned to Themes_Manager::get_sample_content() and consumed by the
 * "Add New" folio flow. Keys:
 *   label       Checkbox label shown on the Add New screen.
 *   description Helper copy beneath the checkbox.
 *   subtitle    Value written to the folio's `subtitle` meta.
 *   folio_meta  Extra folio meta written when the seed runs.
 *   pages       Ordered list of ['title', 'content'] folio pages.
 *
 * Folio Starter registers no custom blocks, so this is core Gutenberg only.
 * Its "On this page" rail is built from h2 headings, so every section leads
 * with one.
 *
 * @package Groove
 */

use Groove\Themes\Themes_Manager;

if (!defined('ABSPATH')) {
  exit;
}

$img_workspace   = Themes_Manager::sample_image_url('ph-workspace');
$img_architect   = Themes_Manager::sample_image_url('ph-architecture');
$img_detail      = Themes_Manager::sample_image_url('ph-detail-object');
$img_studio      = Themes_Manager::sample_image_url('ph-studio');
$img_texture     = Themes_Manager::sample_image_url('ph-texture-paper');
$img_portrait_a  = Themes_Manager::sample_image_url('ph-portrait-a');

$cap_workspace   = Themes_Manager::sample_image_caption('ph-workspace');
$cap_architect   = Themes_Manager::sample_image_caption('ph-architecture');
$cap_detail      = Themes_Manager::sample_image_caption('ph-detail-object');
$cap_studio      = Themes_Manager::sample_image_caption('ph-studio');
$cap_texture     = Themes_Manager::sample_image_caption('ph-texture-paper');

return array(
  'label'       => __('Create with sample portfolio content', 'groove'),
  'description' => __('Seeds a four-page starter portfolio: an introduction, selected work, a note on process, and a contact page.', 'groove'),
  'subtitle'    => __('Selected work and working notes', 'groove'),
  'folio_meta'  => array(),
  'pages'       => array(
    array(
      'title' => __('Introduction', 'groove'),
      'content' => (string) <<<HTML
<!-- wp:paragraph {"dropCap":true} -->
<p class="has-drop-cap">This folio collects four years of work made mostly in quiet: identity systems, publications, and the occasional building sign. It is not a complete record. It is the part I would want to talk about if we sat down together.</p>
<!-- /wp:paragraph -->
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_workspace}" alt="A working desk with layout proofs, a scale rule and a cold cup of coffee"/>{$cap_workspace}</figure>
<!-- /wp:image -->
<!-- wp:heading {"level":2} -->
<h2>What I do</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>I design the parts of a brand that have to survive contact with the real world — the wayfinding that still reads at dusk, the price list that fits on one page, the newsletter template someone else has to fill in every Thursday. Most of the work is typographic. All of it is meant to be used rather than admired.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>Who I work with</h2>
<!-- /wp:heading -->
<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Cultural institutions with more ambition than budget.</li>
<!-- /wp:list-item --><!-- wp:list-item -->
<li>Small manufacturers who make one thing carefully.</li>
<!-- /wp:list-item --><!-- wp:list-item -->
<li>Publishers moving a print habit onto the web without losing the habit.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->
<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->
<!-- wp:paragraph -->
<p>Replace this page with your own introduction. Two or three short paragraphs is usually enough; the work on the following pages will do the rest.</p>
<!-- /wp:paragraph -->
HTML,
    ),
    array(
      'title' => __('Selected Work', 'groove'),
      'content' => (string) <<<HTML
<!-- wp:paragraph -->
<p>Three projects, chosen because each one solved a different kind of problem.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>Ravensgate Civic Archive</h2>
<!-- /wp:heading -->
<!-- wp:media-text {"mediaType":"image","mediaWidth":48} -->
<div class="wp-block-media-text is-stacked-on-mobile" style="grid-template-columns:48% auto"><figure class="wp-block-media-text__media"><img src="{$img_architect}" alt="Concrete stair and handrail casting a long diagonal shadow"/></figure><div class="wp-block-media-text__content"><!-- wp:paragraph -->
<p>A hundred and forty years of planning records, most of them handwritten, needed a reading interface that did not feel like a database. We built the whole thing around a single wide measure and a strict four-step type scale, then spent the remaining time on the index.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Identity, wayfinding, and a public search interface. Two years, ongoing.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:media-text -->
<!-- wp:heading {"level":2} -->
<h2>Hollow Bone Press</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>A three-person poetry press printing runs of four hundred. They needed a catalogue that could be reset in an afternoon and a spine treatment that made a shelf of their books legible from across a room. The answer was a fixed grid, one weight of one typeface, and a colour reserved entirely for the year of publication.</p>
<!-- /wp:paragraph -->
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_detail}" alt="A single bound book photographed flat against a plain surface"/>{$cap_detail}</figure>
<!-- /wp:image -->
<!-- wp:heading {"level":2} -->
<h2>Kilnwork Ceramics</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>A studio that had been trading on word of mouth for eleven years and wanted to keep it that way while selling online. We wrote the labels before we designed anything, which turned out to be the whole project.</p>
<!-- /wp:paragraph -->
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_studio}" alt="Hands at a workbench, tools and unfinished pieces laid out in rows"/>{$cap_studio}</figure>
<!-- /wp:image -->
HTML,
    ),
    array(
      'title' => __('How I Work', 'groove'),
      'content' => (string) <<<HTML
<!-- wp:paragraph -->
<p>Every project runs the same four steps, whether it lasts three weeks or two years. The steps do not change; how long each one takes does.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>The four steps</h2>
<!-- /wp:heading -->
<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3>01 — Read</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>I ask for everything you already have and read it before we speak. Old brochures, complaint emails, the internal deck nobody liked. It is faster than a workshop and more honest.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3>02 — Narrow</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>We agree what the work must do and, more usefully, what it is allowed to ignore. This is written down in one page and referred to constantly.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3>03 — Make</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Two directions, never five. Five directions is a way of asking the client to do the deciding, and they hired you so they would not have to.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3>04 — Hand over</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Files, fonts, a short guide written for the person who will actually use it, and an hour on a call to walk through it.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- wp:heading {"level":2} -->
<h2>A note on revisions</h2>
<!-- /wp:heading -->
<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p>Two rounds are included, and two rounds are almost always enough. If we need a third, something went wrong in step two and we should go back rather than forward.</p>
<!-- /wp:paragraph --></blockquote>
<!-- /wp:quote -->
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_texture}" alt="Close texture of uncoated paper stock in raking light"/>{$cap_texture}</figure>
<!-- /wp:image -->
HTML,
    ),
    array(
      'title' => __('About and Contact', 'groove'),
      'content' => (string) <<<HTML
<!-- wp:heading {"level":2} -->
<h2>About</h2>
<!-- /wp:heading -->
<!-- wp:media-text {"mediaType":"image","mediaWidth":38} -->
<div class="wp-block-media-text is-stacked-on-mobile" style="grid-template-columns:38% auto"><figure class="wp-block-media-text__media"><img src="{$img_portrait_a}" alt="Portrait of the studio's founder against a plain wall"/></figure><div class="wp-block-media-text__content"><!-- wp:paragraph -->
<p>I trained as a printer before I trained as a designer, which is the reason I care so much about what a thing costs to produce. I have worked alone since 2019, from a room above a bakery, and I take on roughly six projects a year.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Before that: seven years in-house at a museum group, and two very instructive years at an agency that no longer exists.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:media-text -->
<!-- wp:heading {"level":2} -->
<h2>Availability</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>I am usually booked one quarter ahead. Small pieces of work — a template, a type audit, a second opinion on something already underway — can normally start within a fortnight.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>Get in touch</h2>
<!-- /wp:heading -->
<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Email — hello@example.com</li>
<!-- /wp:list-item --><!-- wp:list-item -->
<li>Studio — 2 Bakehouse Yard, by appointment</li>
<!-- /wp:list-item --><!-- wp:list-item -->
<li>Elsewhere — @example on most things</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->
<!-- wp:paragraph -->
<p>The most useful first message is one paragraph: what the thing is, when you need it, and roughly what you can spend. I reply to everything within two working days.</p>
<!-- /wp:paragraph -->
HTML,
    ),
  ),
);
