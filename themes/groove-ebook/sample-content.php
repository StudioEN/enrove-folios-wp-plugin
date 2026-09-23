<?php
/**
 * Sample content for the Groove eBook theme.
 *
 * Returned to Themes_Manager::get_sample_content() and consumed by the
 * "Add New" folio flow. Keys:
 *   label       Checkbox label shown on the Add New screen.
 *   description Helper copy beneath the checkbox.
 *   subtitle    Value written to the folio's `subtitle` meta.
 *   folio_meta  Extra folio meta written when the seed runs.
 *   pages       Ordered list of ['title', 'content'] folio pages.
 *
 * Its contents rail is built from h2 headings, so every section leads with one.
 *
 * The seed mixes core Gutenberg with all seven of the theme's own book blocks
 * (blocks.php), so a new folio shows what the kit is for rather than listing it
 * in an empty inserter. Each appears where a book would actually use it: the
 * epigraph opens a chapter, the summary closes one, the references sit in the
 * back matter.
 *
 * Most blocks are written as literal block comments below, which is the most
 * readable form. The plates are not: their credit carries the Pexels
 * attribution links, so the attribute JSON contains markup, and markup has to
 * be escaped before it can sit inside an HTML comment. $block() does that
 * escaping the same way the editor does — see the note on it.
 *
 * @package Groove
 */

use Groove\Themes\Themes_Manager;

if (!defined('ABSPATH')) {
  exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Not globals: Base_Theme::get_sample_content_data() includes this file inside a static method, so these variables are local to that call.

// Placeholder imagery is asked for by role, not by filename. `image_set` in
// this theme's setup.php decides which photographic register those roles
// resolve to, so two themes can seed the same layout with different pictures.
$theme = 'groove-ebook';

$img_hero     = Themes_Manager::theme_image_url($theme, 'hero');
$img_texture  = Themes_Manager::theme_image_url($theme, 'texture');
$img_process  = Themes_Manager::theme_image_url($theme, 'process');
$img_backdrop = Themes_Manager::theme_image_url($theme, 'backdrop');

$cap_backdrop = Themes_Manager::theme_image_caption($theme, 'backdrop');

// The plate has a credit field of its own, so it wants the credit line rather
// than the <figcaption> core/image wants.
$cr_hero    = Themes_Manager::theme_image_credit($theme, 'hero');
$cr_texture = Themes_Manager::theme_image_credit($theme, 'texture');
$cr_process = Themes_Manager::theme_image_credit($theme, 'process');

/**
 * Serialise one void block comment.
 *
 * Attribute JSON sits inside an HTML comment, so four sequences have to be
 * escaped before they can appear in it: `<`, `>`, `&` and `--`. That is what
 * serializeAttributes() in @wordpress/blocks does, and doing the same here
 * means a seeded block is byte-identical to the one the editor writes back the
 * first time somebody saves the page — no spurious diff, and no broken comment
 * when a credit contains an anchor.
 *
 * Empty attributes are dropped rather than written out as "": the block's own
 * defaults already cover them, and a seed that only states what it changes is
 * far easier to read against the block's registration.
 *
 * @param string $name  Block name, e.g. 'groove-ebook/plate'.
 * @param array  $attrs Attributes to state.
 * @return string
 */
$block = static function (string $name, array $attrs): string {
  $attrs = array_filter($attrs, static function ($value) {
    return $value !== '' && $value !== false && $value !== null;
  });

  $json = (string) wp_json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $json = str_replace(
    ['--', '<', '>', '&'],
    ['\\u002d\\u002d', '\\u003c', '\\u003e', '\\u0026'],
    $json
  );

  return '<!-- wp:' . $name . ' ' . $json . ' /-->';
};

// Three plates, numbered the way a book numbers them: the frontispiece is not
// a figure, so it is labelled rather than counted, and the two that are
// figures run in one sequence across chapters rather than restarting.
$plate_front = $block('groove-ebook/plate', [
  'url'     => $img_hero,
  'alt'     => __('An open book resting face down beside a window', 'groove-folios'),
  'label'   => __('Frontispiece', 'groove-folios'),
  'caption' => __('The reading moment this book keeps returning to.', 'groove-folios'),
  'credit'  => $cr_hero,
  'bleed'   => true,
]);

$plate_one = $block('groove-ebook/plate', [
  'url'     => $img_process,
  'alt'     => __('A workbench mid-project, offcuts and practice pieces pushed to one side', 'groove-folios'),
  'label'   => __('Fig. 1', 'groove-folios'),
  'caption' => __('A bench part-way through the work. Everything to the left of the vice is a mistake somebody was allowed to make.', 'groove-folios'),
  'credit'  => $cr_process,
]);

$plate_two = $block('groove-ebook/plate', [
  'url'     => $img_texture,
  'alt'     => __('Layers of proofing paper stacked and slightly fanned', 'groove-folios'),
  'label'   => __('Fig. 2', 'groove-folios'),
  'caption' => __('Eleven proofs of the same page, kept in order. Only the last one was any good, and it needed the other ten.', 'groove-folios'),
  'credit'  => $cr_texture,
]);

return array(
  'label'       => __('Create with sample eBook content', 'groove-folios'),
  'description' => __('Seeds a four-part eBook — a foreword, two chapters and a colophon — using the whole book kit: epigraphs, plates, section breaks and a reference list.', 'groove-folios'),
  'subtitle'    => __('A short book about working slowly', 'groove-folios'),
  'folio_meta'  => array(),
  'pages'       => array(
    array(
      'title' => __('Foreword', 'groove-folios'),
      'content' => (string) ($plate_front . '
<!-- wp:paragraph {"dropCap":true} -->
<p class="has-drop-cap">This book began as a complaint. I had spent a decade being paid to make things faster and had started to notice that almost nothing I was proud of had been made quickly. What follows is an attempt to work out whether that was sentiment or evidence.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>How to read this</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Each chapter stands on its own and can be read in about fifteen minutes. They are ordered by argument rather than chronology, so beginning in the middle costs you very little. Where I quote someone, the full source is in the colophon at the back.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>A warning</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>I am not going to argue that slow is good. Plenty of slow work is slow because it is badly organised, and plenty of fast work is fast because someone thought hard in advance. The distinction I care about is different, and it takes the whole book to draw.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-ebook/ornament {"mark":"asterism"} /-->
<!-- wp:paragraph -->
<p>Replace this page with your own front matter — a foreword, a dedication, or simply a summary of what the reader is about to get.</p>
<!-- /wp:paragraph -->'),
    ),
    array(
      'title' => __('One: The Material of Attention', 'groove-folios'),
      'content' => (string) ('<!-- wp:groove-ebook/epigraph {"text":"The hand is the window on to the mind.","attribution":"Immanuel Kant"} /-->
<!-- wp:paragraph {"dropCap":true} -->
<p class="has-drop-cap">A bookbinder I know can tell, by the sound a signature makes when it is folded, whether the grain is running the right way. She has never explained this to me in a way I could act on. She has simply done it, several thousand times, until the knowledge moved out of her head and into her hands.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>Knowledge that will not compress</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>There is a category of skill that resists being written down. Not because it is mystical, but because the useful part of it is the thousand small corrections, and a list of a thousand corrections is not a lesson. It is a lookup table, and nobody can read fast enough to use one in the moment.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>What transfers instead is a habit of attention: knowing where to look, and knowing when something is slightly wrong before you can say why. That habit is built by repetition under conditions where mistakes are cheap and visible. Remove either condition and the habit does not form.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-ebook/pull-quote {"text":"The apprentice is not slow because they are learning. They are learning because they are slow."} /-->
<!-- wp:heading {"level":2} -->
<h2>Cheap mistakes, visible mistakes</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Consider what happens when we optimise a workshop. The offcuts go, the false starts go, the practice pieces go. Each of those is waste by any reasonable measure, and each of them was the only place a beginner could be wrong without consequence. We did not remove the learning deliberately. We removed the conditions and the learning left with them.</p>
<!-- /wp:paragraph -->
' . $plate_one . '
<!-- wp:heading {"level":2} -->
<h2>What this costs</h2>
<!-- /wp:heading -->
<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Material, which is the cheapest of the three and the only one anyone measures.</li>
<!-- /wp:list-item --><!-- wp:list-item -->
<li>Time, which is expensive but recoverable.</li>
<!-- /wp:list-item --><!-- wp:list-item -->
<li>Tolerance for visible failure, which is the scarcest thing in most organisations.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->
<!-- wp:paragraph -->
<p>The third is the one that decides whether the other two were worth spending.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-ebook/summary {"title":"What this chapter argued","points":["Skill leaves the head and moves into the hands, and only repetition moves it.","The offcuts and the practice pieces were the learning, not the waste.","Tolerance for visible failure is the scarce resource — not time, and not material."]} /-->'),
    ),
    array(
      'title' => __('Two: Working in Public', 'groove-folios'),
      'content' => (string) ('<!-- wp:paragraph {"dropCap":true} -->
<p class="has-drop-cap">The second half of the argument is less comfortable, because it asks something of the people around the work rather than the person doing it. If mistakes have to be visible, somebody has to agree to look at them without flinching.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>The demonstration problem</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Most workplaces reward the demonstration of competence rather than its acquisition. This is not malice; competence is legible and acquisition is not. But the incentive is real, and it teaches people to show work only once it is safe to show, which is precisely when feedback has stopped being useful.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-ebook/aside {"label":"A caveat","body":"None of this is an argument against deadlines. A deadline decides when the work stops. It is not a verdict on how the work was made."} /-->
<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p>Show me the version you are embarrassed by. The finished one has nothing left to teach either of us.</p>
<!-- /wp:paragraph --><cite>A former editor, on receiving a fourth draft</cite></blockquote>
<!-- /wp:quote -->
<!-- wp:heading {"level":2} -->
<h2>Three habits that help</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>None of these are original and all of them are hard to keep.</p>
<!-- /wp:paragraph -->
<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list"><!-- wp:list-item -->
<li>Circulate the rough version on a fixed day, whether or not it is ready. The date does the work that willpower will not.</li>
<!-- /wp:list-item --><!-- wp:list-item -->
<li>Say out loud what kind of response you want. "Is the structure right" and "does this read well" are different requests and cannot be answered at once.</li>
<!-- /wp:list-item --><!-- wp:list-item -->
<li>Keep the rejected versions somewhere you will see them. They are the only record of why the final thing looks the way it does.</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->
' . $plate_two . '
<!-- wp:heading {"level":2} -->
<h2>Where this leaves us</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Not with a method. Methods are the thing organisations reach for when they want the result without the conditions. What is on offer here is smaller and harder: a set of conditions, maintained deliberately, inside which people get better at things. Everything else in this book is an argument for paying that price.</p>
<!-- /wp:paragraph -->'),
    ),
    array(
      'title' => __('Colophon', 'groove-folios'),
      'content' => (string) ('<!-- wp:paragraph -->
<p>This edition was set for the screen and is intended to be read in one or two sittings.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>Sources</h2>
<!-- /wp:heading -->
<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Interviews conducted between March and September, transcripts held by the author.</li>
<!-- /wp:list-item --><!-- wp:list-item -->
<li>Workshop observations at three studios, all quoted with permission.</li>
<!-- /wp:list-item --><!-- wp:list-item -->
<li>The editor quoted in chapter two asked not to be named.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->
<!-- wp:heading {"level":2} -->
<h2>Further reading</h2>
<!-- /wp:heading -->
<!-- wp:groove-ebook/references {"title":"","entries":[{"author":"Sennett, Richard","work":"The Craftsman","note":"Yale University Press, 2008","url":""},{"author":"Pye, David","work":"The Nature and Art of Workmanship","note":"Cambridge University Press, 1968","url":""},{"author":"Sudjic, Deyan","work":"The Language of Things","note":"Penguin, 2008","url":""}]} /-->
<!-- wp:heading {"level":2} -->
<h2>Thanks</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>To everyone who read a draft and said the structure was wrong, particularly the two who were right. To the bindery that let me stand in the corner for a week. And to the reader who gets this far — the argument only works if somebody finishes it.</p>
<!-- /wp:paragraph -->
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="' . $img_backdrop . '" alt="A soft, near-monochrome surface used as a closing plate"/>' . $cap_backdrop . '</figure>
<!-- /wp:image -->
<!-- wp:groove-ebook/ornament {"mark":"rule"} /-->
<!-- wp:paragraph -->
<p>Replace this page with your own colophon: edition details, sources, permissions and thanks.</p>
<!-- /wp:paragraph -->'),
    ),
  ),
);
