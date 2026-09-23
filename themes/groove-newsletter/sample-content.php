<?php
/**
 * Sample content for the Groove Newsletter theme.
 *
 * Returned to Themes_Manager::get_sample_content() and consumed by the
 * "Add New" folio flow. Keys:
 *   label       Checkbox label shown on the Add New screen.
 *   description Helper copy beneath the checkbox.
 *   subtitle    Value written to the folio's `subtitle` meta (the cover kicker).
 *   folio_meta  Extra folio meta written when the seed runs.
 *   pages       Ordered list of ['title', 'content', 'feature_image'] pages.
 *
 * Groove Newsletter registers no custom blocks, so the bodies are core
 * Gutenberg. It renders each issue's featured image above the body and uses it
 * as the navigation thumbnail, so every page names a placeholder slug in
 * `feature_image`; the seeder attaches the file when curation has downloaded it
 * and silently skips the thumbnail when it has not.
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
$theme = 'groove-newsletter';

$img_hero       = Themes_Manager::theme_image_url($theme, 'hero');
$img_scene      = Themes_Manager::theme_image_url($theme, 'scene');
$img_people     = Themes_Manager::theme_image_url($theme, 'people');
$img_backdrop   = Themes_Manager::theme_image_url($theme, 'backdrop');
$img_portrait_a = Themes_Manager::theme_image_url($theme, 'portrait-a');
$img_portrait_b = Themes_Manager::theme_image_url($theme, 'portrait-b');
$img_portrait_c = Themes_Manager::theme_image_url($theme, 'portrait-c');

$cap_hero     = Themes_Manager::theme_image_caption($theme, 'hero');
$cap_scene    = Themes_Manager::theme_image_caption($theme, 'scene');
$cap_backdrop = Themes_Manager::theme_image_caption($theme, 'backdrop');

return array(
  'label'       => __('Create with sample newsletter content', 'groove-folios'),
  'description' => __('Seeds a four-part newsletter issue — a shipping note, a reading list, team notes and a look ahead — each with its own header image.', 'groove-folios'),
  'subtitle'    => __('Dispatch No. 12', 'groove-folios'),
  'folio_meta'  => array(
    'newsletter_theme_preset' => 'evergreen-ink',
  ),
  'pages'       => array(
    array(
      'title' => __('What We Shipped', 'groove-folios'),
      'feature_image' => Themes_Manager::sample_image_slug($theme, 'hero'),
      'content' => (string) <<<HTML
<!-- wp:paragraph -->
<p>A short issue this fortnight. One large thing landed, two small ones, and we finally deleted something.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>Scheduled exports</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>You can now set an export to run on a schedule and land in a folder rather than your inbox. This has been the single most requested thing since March and took considerably longer than it should have, because getting the timezone right for recurring jobs is a genuinely hard problem and we got it wrong twice before we got it right.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Existing manual exports are untouched. If you want one on a schedule, open it and pick a cadence — everything else carries over.</p>
<!-- /wp:paragraph -->
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_hero}" alt="A desk with two screens showing an export running"/>{$cap_hero}</figure>
<!-- /wp:image -->
<!-- wp:heading {"level":2} -->
<h2>Smaller changes</h2>
<!-- /wp:heading -->
<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Search now matches on partial words, which is what everyone assumed it already did.</li>
<!-- /wp:list-item --><!-- wp:list-item -->
<li>The archive filter remembers your last selection for the length of a session.</li>
<!-- /wp:list-item --><!-- wp:list-item -->
<li>Fixed a case where duplicating an item copied its tags but not its owner.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->
<!-- wp:heading {"level":2} -->
<h2>What we removed</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>The dashboard's activity sparkline is gone. Six people had used it in the previous ninety days, and four of those were us. If you were one of the other two, reply and tell us what it was doing for you — we will build something better rather than putting it back.</p>
<!-- /wp:paragraph -->
HTML,
    ),
    array(
      'title' => __('Three Things Worth Reading', 'groove-folios'),
      'feature_image' => Themes_Manager::sample_image_slug($theme, 'scene'),
      'content' => (string) <<<HTML
<!-- wp:paragraph -->
<p>What the team passed around this fortnight, with a line on why each one stuck.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>On maintenance as design work</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>An essay arguing that the discipline treats maintenance as the absence of design rather than a kind of it, and that this is why so much infrastructure is beautiful for eighteen months and unbearable for thirty years. The section on handrails is worth the whole piece.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>A postmortem worth stealing from</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Not for the incident, which was routine, but for the format. They separate what happened from why nobody noticed, and refuse to merge the two sections even when it makes the document longer. We have started doing the same and our own writeups are better for it.</p>
<!-- /wp:paragraph -->
<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p>Every incident has two stories. The first is short and technical. The second is long, organisational, and the only one that stops it happening again.</p>
<!-- /wp:paragraph --></blockquote>
<!-- /wp:quote -->
<!-- wp:heading {"level":2} -->
<h2>The one that started an argument</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>A claim that estimation is not merely inaccurate but actively harmful, because the act of producing a number commits people to defending it. Two of us found it obviously correct and two found it obviously wrong, which is usually a sign that something is worth reading.</p>
<!-- /wp:paragraph -->
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_scene}" alt="A stack of printed articles with margin notes"/>{$cap_scene}</figure>
<!-- /wp:image -->
HTML,
    ),
    array(
      'title' => __('Team Notes', 'groove-folios'),
      'feature_image' => Themes_Manager::sample_image_slug($theme, 'people'),
      'content' => (string) <<<HTML
<!-- wp:paragraph -->
<p>Three changes to how we work, one of which will affect you.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>Support hours are moving</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>From the first of next month, live support runs 09:00–17:00 in two timezones rather than one, which closes the gap that has been swallowing Asia-Pacific tickets overnight. Response times outside those hours stay as they are: one working day, and we have hit that ninety-six per cent of the time this year.</p>
<!-- /wp:paragraph -->
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_people}" alt="Four people around a table reviewing a printed schedule"/></figure>
<!-- /wp:image -->
<!-- wp:heading {"level":2} -->
<h2>Two people joined</h2>
<!-- /wp:heading -->
<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_portrait_b}" alt="Portrait of Noor Haddad"/></figure>
<!-- /wp:image -->
<!-- wp:paragraph -->
<p><strong>Noor Haddad</strong> joins support after four years doing the same job somewhere with considerably worse tooling, and has already filed eleven bug reports.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_portrait_c}" alt="Portrait of Dan Okafor"/></figure>
<!-- /wp:image -->
<!-- wp:paragraph -->
<p><strong>Dan Okafor</strong> is our first dedicated infrastructure hire and spent his first week reading logs rather than writing code, which we took as a very good sign.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- wp:heading {"level":2} -->
<h2>One person left</h2>
<!-- /wp:heading -->
<!-- wp:media-text {"mediaType":"image","mediaWidth":32} -->
<div class="wp-block-media-text is-stacked-on-mobile" style="grid-template-columns:32% auto"><figure class="wp-block-media-text__media"><img src="{$img_portrait_a}" alt="Portrait of Petra Lindqvist"/></figure><div class="wp-block-media-text__content"><!-- wp:paragraph -->
<p>Petra Lindqvist has gone back to teaching after five years here. She wrote most of the import pipeline, named every table in it after a river, and left better documentation than anyone has any right to expect.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:media-text -->
HTML,
    ),
    array(
      'title' => __('What Comes Next', 'groove-folios'),
      'feature_image' => Themes_Manager::sample_image_slug($theme, 'backdrop'),
      'content' => (string) <<<HTML
<!-- wp:paragraph -->
<p>The next two months, stated plainly enough that you can hold us to it.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>Committed</h2>
<!-- /wp:heading -->
<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Per-field permissions, currently in testing with four accounts.</li>
<!-- /wp:list-item --><!-- wp:list-item -->
<li>A proper audit log, replacing the one that only recorded deletions.</li>
<!-- /wp:list-item --><!-- wp:list-item -->
<li>Faster first paint on the archive view, which is embarrassing on slow connections.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->
<!-- wp:heading {"level":2} -->
<h2>Considering</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>A public API. We are not committing to it because the version we would ship in a hurry would be one we could never change, and an API you regret is worse than one you do not have. If you have a concrete use for it, tell us what you would call first — that is the detail that decides the shape.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>Not doing</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Mobile apps, this year or next. The site works on a phone and we would rather it worked well than have a second thing to keep in step with the first.</p>
<!-- /wp:paragraph -->
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{$img_backdrop}" alt="A soft, low-contrast surface closing the issue"/>{$cap_backdrop}</figure>
<!-- /wp:image -->
<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->
<!-- wp:paragraph -->
<p>Replies reach a person. If something here was wrong, or you want more of one section and less of another, say so — this issue is shorter than the last one because three of you asked.</p>
<!-- /wp:paragraph -->
HTML,
    ),
  ),
);
