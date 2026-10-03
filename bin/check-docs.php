<?php
/**
 * Conformance check for the documentation the Themes screen renders.
 *
 * `Enrove\Utils\Markdown` covers the Markdown these documents use rather than
 * Markdown in general, which is a safe trade only while something is watching
 * the documents. Unsupported syntax does not raise anything — it renders as
 * literal asterisks and pipes in the middle of the spec — so this script is
 * what turns that silent failure into a failed build.
 *
 * It checks three things:
 *
 *   1. Neither document uses a construct the renderer does not implement.
 *   2. The rendered HTML carries no leftover Markdown.
 *   3. Every anchor linked to — from inside a document, and from the admin
 *      screens — still names a heading that exists.
 *
 * No WordPress: the escaping functions are shimmed below, so this runs on a
 * bare checkout.
 *
 *   php bin/check-docs.php
 *   php bin/check-docs.php --verbose
 */

if (PHP_SAPI !== 'cli') {
	exit(1);
}

define('ABSPATH', __DIR__);

if (!function_exists('esc_html')) {
	function esc_html($text)
	{
		return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('esc_attr')) {
	function esc_attr($text)
	{
		return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('esc_url')) {
	function esc_url($url)
	{
		return htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('esc_attr__')) {
	function esc_attr__($text)
	{
		return esc_attr($text);
	}
}

if (!function_exists('esc_html__')) {
	function esc_html__($text)
	{
		return esc_html($text);
	}
}

$root = dirname(__DIR__);

require_once $root . '/utils/markdown.php';
require_once $root . '/utils/theme-docs.php';

use Enrove\Utils\Markdown;
use Enrove\Utils\Theme_Docs;

$verbose = in_array('--verbose', $argv, true);
$failures = array();
$notes = array();

/**
 * Mask out every span the renderer treats as literal, so a scan for unsupported
 * syntax does not trip over a document that is *describing* that syntax. Both of
 * these files quote Markdown, CSS and PHP at length.
 */
function enrove_mask_code($markdown)
{
	$lines = explode("\n", str_replace(array("\r\n", "\r"), "\n", $markdown));
	$in_fence = false;
	$fence_char = '';

	foreach ($lines as $index => $line) {
		if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $line, $m)) {
			if (!$in_fence) {
				$in_fence = true;
				$fence_char = $m[1][0];
				$lines[$index] = '';
				continue;
			}

			if ($m[1][0] === $fence_char) {
				$in_fence = false;
			}

			$lines[$index] = '';
			continue;
		}

		if ($in_fence) {
			$lines[$index] = '';
			continue;
		}

		// Inline code spans become runs of a character no rule below looks for.
		$lines[$index] = preg_replace_callback('/(`+)(.*?)\1/', function ($m) {
			return str_repeat("\x01", strlen($m[0]));
		}, $line);
	}

	return $lines;
}

/**
 * Every construct the renderer does not implement, and what to write instead.
 */
function enrove_unsupported_syntax(array $masked)
{
	$found = array();
	$previous = '';

	foreach ($masked as $index => $line) {
		$number = $index + 1;
		$checks = array(
			array('/!\[[^\]]*\]\(/', 'an image — the renderer emits no <img>, and these documents ship no artwork'),
			array('/\[\^[^\]]+\]/', 'a footnote reference'),
			array('/^\s*\[[^\]]+\]:\s*\S/', 'a link reference definition — write the URL inline'),
			array('/\[[^\]\n]*\]\[[^\]]*\]/', 'a reference-style link — write the URL inline'),
			array('/~~[^~]+~~/', 'strikethrough'),
			array('/<[a-zA-Z][a-zA-Z0-9-]*(\s[^<>]*)?>/', 'raw HTML, which is escaped and shown rather than rendered'),
			array('/<(https?:\/\/|[^@\s>]+@)[^>\s]+>/', 'an autolink — write it as [text](url)'),
			array('/&[a-zA-Z][a-zA-Z0-9]{1,9};/', 'an HTML entity, which is escaped and shown literally'),
			array('/(?<![\w_])_(?=\S)[^_\n]+(?<=\S)_(?![\w_])/', 'underscore emphasis — use *asterisks*, because these documents are full of snake_case'),
			array('/\S {2,}$/', 'a hard line break written as trailing spaces, which the renderer joins'),
		);

		foreach ($checks as $check) {
			if (preg_match($check[0], $line)) {
				$found[] = array($number, $check[1], trim($line));
			}
		}

		// A run of dashes means one thing after a blank line and the opposite
		// directly under a line of text, and the renderer only implements the
		// first — a horizontal rule. So the ambiguous case is rejected outright
		// rather than guessed at: a setext heading here would render as a
		// paragraph followed by a rule, which looks deliberate and is not.
		if (
			preg_match('/^ {0,3}(=+|-+)\s*$/', $line)
			&& trim($previous) !== ''
			&& !preg_match('/^ {0,3}(-{3,}|\*{3,}|_{3,}|=+)\s*$/', $previous)
		) {
			$found[] = array(
				$number,
				'a setext heading — write it with # or ## instead, and keep a blank line above every rule',
				trim($previous) . ' / ' . trim($line)
			);
		}

		if (preg_match('/^ {0,3}>/', $previous) && trim($line) !== '' && !preg_match('/^ {0,3}>/', $line)) {
			$found[] = array($number, 'a lazy blockquote continuation — start the line with > as well', trim($line));
		}

		$previous = $line;
	}

	return $found;
}

/**
 * Markdown that survived into the output, which is what a missing rule looks like.
 */
function enrove_leftover_markdown($html)
{
	// Code is meant to hold literal punctuation; everything else is not.
	$prose = preg_replace('#<pre[^>]*>[\s\S]*?</pre>#', '', $html);
	$prose = preg_replace('#<code[^>]*>[\s\S]*?</code>#', '', $prose);

	$found = array();
	$checks = array(
		'/\]\(/' => 'an unrendered link',
		'/\*\*/' => 'unrendered bold',
		'/(?<![\w*])\*(?=\S)[^*<>\n]{1,80}(?<=\S)\*(?![\w*])/' => 'unrendered emphasis',
		'/^\s*\|/m' => 'an unrendered table row',
		'/^\s*#{1,6}\s+\S/m' => 'an unrendered heading',
		'/^\s*[-*+]\s+\S/m' => 'an unrendered list item',
	);

	foreach ($checks as $pattern => $label) {
		if (preg_match($pattern, $prose, $m)) {
			$found[] = $label . ': ' . trim(substr($m[0], 0, 70));
		}
	}

	return $found;
}

/**
 * Table rows that do not have the column count their header promised, which is
 * what an unescaped pipe in a cell produces.
 */
function enrove_ragged_tables($html)
{
	$found = array();

	if (!preg_match_all('#<table[^>]*>([\s\S]*?)</table>#', $html, $tables)) {
		return $found;
	}

	foreach ($tables[1] as $position => $table) {
		$columns = preg_match_all('#<th(\s[^>]*)?>#', $table);

		preg_match_all('#<tr>([\s\S]*?)</tr>#', $table, $rows);

		foreach ($rows[1] as $row) {
			$cells = preg_match_all('#<td(\s[^>]*)?>#', $row);

			if ($cells > 0 && $cells !== $columns) {
				$plain = trim(preg_replace('/\s+/', ' ', strip_tags($row)));
				$found[] = sprintf(
					'table %d: a row has %d cells against %d columns — %s',
					$position + 1,
					$cells,
					$columns,
					substr($plain, 0, 70)
				);
			}
		}
	}

	return $found;
}

/**
 * Same-document anchors a doc links to, so they can be checked against its headings.
 */
function enrove_internal_anchors(array $masked)
{
	$anchors = array();

	foreach ($masked as $line) {
		if (preg_match_all('/\]\(#([^)\s]+)\)/', $line, $m)) {
			foreach ($m[1] as $anchor) {
				$anchors[] = $anchor;
			}
		}
	}

	return array_unique($anchors);
}

// ---------------------------------------------------------------------------
// Run
// ---------------------------------------------------------------------------

echo "Enrove documentation check\n";
echo str_repeat('=', 60) . "\n\n";

$anchors_by_doc = array();

foreach (Theme_Docs::DOCS as $key => $relative) {
	$path = $root . '/' . $relative;

	printf("%-10s %s\n", $key, $relative);

	if (!is_readable($path)) {
		$failures[] = sprintf('%s: %s is not readable', $key, $relative);
		echo "  ! not readable\n\n";
		continue;
	}

	$markdown = file_get_contents($path);
	$masked = enrove_mask_code($markdown);

	$render_args = array(
		'doc_links' => array('README.md' => '#', 'BUILDING-A-THEME.md' => '#'),
	);

	$started = microtime(true);
	$html = Markdown::render($markdown, $render_args);
	$elapsed = (microtime(true) - $started) * 1000;

	$outline = Markdown::outline($markdown, $render_args);
	$anchors = array_column($outline, 'anchor');
	$anchors_by_doc[$key] = $anchors;

	$problems = 0;

	// The contents rail is built from outline(), the headings it links to from
	// render(), and the two are separate passes over the same source. They agree
	// today; this is what says so tomorrow.
	//
	// Level as well as anchor, because the two drift in different ways and only
	// one of them is visible. A slug that disagrees gives a rail of links that go
	// nowhere. A *level* that disagrees gives a rail of links that all work and
	// the wrong headings in it — the page filters the outline on level to decide
	// what a section is, so an offset applied to one pass and not the other empties
	// the rail while every anchor in it still resolves.
	foreach ($outline as $heading) {
		$emitted = sprintf('<h%d id="%s"', $heading['level'], $heading['anchor']);

		if (strpos($html, $emitted) === false) {
			$failures[] = sprintf(
				'%s: outline() offers #%s at h%d, which render() does not emit',
				$relative,
				$heading['anchor'],
				$heading['level']
			);
			printf("  ! outline() offers #%s at h%d, which render() does not emit\n", $heading['anchor'], $heading['level']);
			$problems++;
		}
	}

	foreach (enrove_unsupported_syntax($masked) as $item) {
		list($line_number, $reason, $excerpt) = $item;
		$failures[] = sprintf('%s:%d %s', $relative, $line_number, $reason);
		printf("  ! line %d — %s\n", $line_number, $reason);
		if ($verbose) {
			printf("      %s\n", substr($excerpt, 0, 100));
		}
		$problems++;
	}

	foreach (enrove_leftover_markdown($html) as $leftover) {
		$failures[] = sprintf('%s: %s', $relative, $leftover);
		printf("  ! %s\n", $leftover);
		$problems++;
	}

	foreach (enrove_ragged_tables($html) as $ragged) {
		$failures[] = sprintf('%s: %s', $relative, $ragged);
		printf("  ! %s\n", $ragged);
		$problems++;
	}

	foreach (enrove_internal_anchors($masked) as $anchor) {
		if (!in_array($anchor, $anchors, true)) {
			$failures[] = sprintf('%s: links to #%s, which is not a heading in it', $relative, $anchor);
			printf("  ! links to #%s, which is not a heading in it\n", $anchor);
			$problems++;
		}
	}

	$duplicates = array_keys(array_filter(array_count_values($anchors), function ($n) {
		return $n > 1;
	}));

	if (!empty($duplicates)) {
		$notes[] = sprintf(
			'%s: %d heading anchor(s) de-duplicated with a numeric suffix (%s) — a link to one of these is ambiguous',
			$relative,
			count($duplicates),
			implode(', ', array_slice($duplicates, 0, 3))
		);
	}

	printf(
		"  %s %s headings, %s tables, %s code blocks, %s of HTML in %.1fms\n\n",
		$problems === 0 ? "\xE2\x9C\x93" : ' ',
		number_format(count($anchors)),
		number_format(preg_match_all('#<table#', $html)),
		number_format(preg_match_all('#<pre#', $html)),
		number_format(strlen($html)),
		$elapsed
	);
}

// The anchors the admin links to. A heading renamed out from under one of these
// turns an admin link into a silent jump to the top of the page.
$admin_links = array(
	array(
		'Themes screen, theme problems panel',
		Theme_Docs::SKIPPED_THEME_DOC,
		Theme_Docs::SKIPPED_THEME_ANCHOR,
	),
);

echo "Anchors linked to from the admin\n";

foreach ($admin_links as $link) {
	list($where, $doc, $anchor) = $link;
	$exists = isset($anchors_by_doc[$doc]) && in_array($anchor, $anchors_by_doc[$doc], true);

	printf("  %s %s → %s#%s\n", $exists ? "\xE2\x9C\x93" : '!', $where, $doc, $anchor);

	if (!$exists) {
		$failures[] = sprintf('%s links to %s#%s, which no longer exists', $where, $doc, $anchor);
	}
}

echo "\n";

foreach ($notes as $note) {
	echo '  note: ' . $note . "\n";
}

if (!empty($notes)) {
	echo "\n";
}

if (empty($failures)) {
	echo "All documents render cleanly.\n";
	exit(0);
}

printf("%d problem(s):\n", count($failures));
foreach ($failures as $failure) {
	echo '  - ' . $failure . "\n";
}

exit(1);
