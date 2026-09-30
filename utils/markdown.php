<?php

namespace Groove\Utils;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

/**
 * A Markdown renderer for the documentation this plugin ships with itself.
 *
 * Deliberately not a general-purpose parser. It covers exactly the constructs
 * `themes/README.md` and `themes/BUILDING-A-THEME.md` use, and `bin/check-docs.php`
 * fails the build if either file grows a construct this class does not handle —
 * which is the part that makes a hand-written parser safe to rely on. Without
 * that check, unsupported syntax does not raise anything: it renders as literal
 * asterisks and pipes in the middle of the spec, which reads as a broken page
 * with nothing to say why.
 *
 * Everything is escaped at the point it becomes text, so the return value is
 * ready to echo. The tag vocabulary is fixed and closed — there is no branch
 * anywhere below that emits markup taken from the source document, so raw HTML
 * in a doc file is escaped and shown rather than run.
 *
 * @since 0.4.0
 */
class Markdown
{
	/**
	 * Code spans are lifted out before any other inline rule runs, so the
	 * contents of `**not bold**` stay literal. The sentinels are control
	 * characters because `esc_html()` leaves them alone and no doc contains one.
	 */
	const CODE_OPEN = "\x02";
	const CODE_CLOSE = "\x03";

	/** @var array Rendering options — see render(). */
	private $args;

	/** @var array Heading slugs already handed out, for de-duplication. */
	private $anchors = array();

	/** @var array Extracted inline code spans, keyed by their placeholder index. */
	private $code_spans = array();

	/**
	 * Render a Markdown string to HTML.
	 *
	 * @param string $text     The Markdown source.
	 * @param array  $args     {
	 *     @type int   $heading_offset How far to demote headings. The admin page
	 *                                 already owns an <h1>, so the doc's own title
	 *                                 must not be a second one. Default 1.
	 *     @type array $doc_links      Map of linkable document filename => URL, for
	 *                                 rewriting the cross-references between the two
	 *                                 doc files into links between the two tabs.
	 * }
	 * @return string
	 */
	public static function render($text, array $args = array())
	{
		$renderer = new self($args);

		return $renderer->render_blocks($renderer->split_lines($text));
	}

	/**
	 * The closed tag vocabulary render() emits, for the wp_kses() its caller
	 * echoes the result through. It lives beside the renderer so a new
	 * construct and its tag are added together: a tag render() starts
	 * emitting without being listed here is silently dropped on the page.
	 *
	 * wp_kses_post() is not a substitute: it drops the task-list checkboxes.
	 *
	 * @return array Allowed-HTML array for wp_kses().
	 */
	public static function allowed_html()
	{
		$class = array('class' => true);
		$heading = array('id' => true, 'class' => true);

		return array(
			'h1'         => $heading,
			'h2'         => $heading,
			'h3'         => $heading,
			'h4'         => $heading,
			'h5'         => $heading,
			'h6'         => $heading,
			'p'          => $class,
			'blockquote' => $class,
			'pre'        => $class,
			'code'       => $class,
			'hr'         => $class,
			'div'        => $class,
			'span'       => $class,
			'em'         => array(),
			'strong'     => array(),
			'ul'         => $class,
			'ol'         => array('class' => true, 'start' => true),
			'li'         => $class,
			'input'      => array(
				'type'     => array('values' => array('checkbox')),
				'class'    => true,
				'disabled' => true,
				'checked'  => true,
			),
			'table'      => $class,
			'thead'      => array(),
			'tbody'      => array(),
			'tr'         => array(),
			'th'         => array('scope' => true, 'class' => true),
			'td'         => $class,
			'a'          => array(
				'class'      => true,
				'href'       => true,
				'aria-label' => true,
				'target'     => true,
				'rel'        => true,
			),
		);
	}

	/**
	 * The document's headings, in order, as `render()` will emit them.
	 *
	 * Feeds the contents rail on the Themes screen, and lets `bin/check-docs.php`
	 * prove that every anchor the admin links to — and every in-document
	 * cross-reference — still points at a heading that exists.
	 *
	 * This is a second pass over the same source rather than a by-product of
	 * rendering, so it has to reach the same answer: same heading regex, same
	 * fence skipping, same slug, same de-duplication counter, and the same
	 * `heading_offset`. Pass this method and `render()` the same `$args`.
	 * `check-docs.php` asserts the two agree on every anchor.
	 *
	 * @param string $text The Markdown source.
	 * @param array  $args Same options as render().
	 * @return array List of ['level' => int, 'text' => string, 'anchor' => string].
	 */
	public static function outline($text, array $args = array())
	{
		$renderer = new self($args);
		$offset = (int) $renderer->args['heading_offset'];
		$outline = array();
		$in_fence = false;
		$fence = '';

		foreach ($renderer->split_lines($text) as $line) {
			if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $line, $m)) {
				if (!$in_fence) {
					$in_fence = true;
					$fence = $m[1][0];
				} elseif ($m[1][0] === $fence) {
					$in_fence = false;
				}
				continue;
			}

			if ($in_fence) {
				continue;
			}

			if (preg_match('/^ {0,3}(#{1,6})\s+(.*?)\s*#*\s*$/', $line, $m)) {
				$plain = self::strip_inline($m[2]);

				$outline[] = array(
					'level' => min(6, strlen($m[1]) + $offset),
					'text' => $plain,
					'anchor' => $renderer->unique_anchor(self::slug($plain)),
				);
			}
		}

		return $outline;
	}

	private function __construct(array $args = array())
	{
		$this->args = array_merge(
			array(
				'heading_offset' => 1,
				'doc_links' => array(),
			),
			$args
		);
	}

	// -----------------------------------------------------------------------
	// Block level
	// -----------------------------------------------------------------------

	private function split_lines($text)
	{
		$text = str_replace(array("\r\n", "\r"), "\n", (string) $text);

		return explode("\n", $text);
	}

	/**
	 * Render a run of lines as block-level content.
	 *
	 * Called recursively for blockquotes and list items, which is what lets a
	 * fenced code block or a nested list inside a list item work without a
	 * second code path.
	 */
	private function render_blocks(array $lines)
	{
		$out = '';
		$count = count($lines);
		$i = 0;

		while ($i < $count) {
			$line = $lines[$i];

			if (trim($line) === '') {
				$i++;
				continue;
			}

			if (preg_match('/^ {0,3}(`{3,}|~{3,})\s*([A-Za-z0-9_+-]*)\s*$/', $line, $m)) {
				$out .= $this->render_fence($lines, $i, $count, $m[1], $m[2]);
				continue;
			}

			if (preg_match('/^ {0,3}(#{1,6})\s+(.*?)\s*#*\s*$/', $line, $m)) {
				$out .= $this->render_heading($m[1], $m[2]);
				$i++;
				continue;
			}

			if (self::is_rule($line)) {
				$out .= '<hr class="g-docs__rule">';
				$i++;
				continue;
			}

			if (self::is_table_row($line) && isset($lines[$i + 1]) && self::is_table_delimiter($lines[$i + 1])) {
				$out .= $this->render_table($lines, $i, $count);
				continue;
			}

			if (preg_match('/^ {0,3}>/', $line)) {
				$out .= $this->render_quote($lines, $i, $count);
				continue;
			}

			if (self::list_marker($line) !== null) {
				$out .= $this->render_list($lines, $i, $count);
				continue;
			}

			$out .= $this->render_paragraph($lines, $i, $count);
		}

		return $out;
	}

	private function render_fence(array $lines, &$i, $count, $fence, $language)
	{
		$char = $fence[0];
		$length = strlen($fence);
		$closer = '/^ {0,3}' . preg_quote($char, '/') . '{' . $length . ',}\s*$/';
		$buffer = array();

		$i++;

		while ($i < $count && !preg_match($closer, $lines[$i])) {
			$buffer[] = $lines[$i];
			$i++;
		}

		// A fence that runs to the end of the document has no closing line to skip.
		if ($i < $count) {
			$i++;
		}

		$class = 'g-docs__pre';
		if ($language !== '') {
			$class .= ' language-' . strtolower($language);
		}

		return '<pre class="' . esc_attr($class) . '"><code>'
			. esc_html(implode("\n", $buffer))
			. '</code></pre>';
	}

	private function render_heading($hashes, $text)
	{
		$level = min(6, strlen($hashes) + (int) $this->args['heading_offset']);
		$anchor = $this->unique_anchor(self::slug(self::strip_inline($text)));

		return sprintf(
			'<h%1$d id="%2$s" class="g-docs__h g-docs__h--%1$d">'
				. '<a class="g-docs__anchor" href="#%2$s" aria-label="%3$s">#</a>%4$s</h%1$d>',
			$level,
			esc_attr($anchor),
			esc_attr__('Link to this section', 'groove-folios'),
			$this->inline($text)
		);
	}

	private function render_quote(array $lines, &$i, $count)
	{
		$buffer = array();

		while ($i < $count && preg_match('/^ {0,3}>( ?)(.*)$/', $lines[$i], $m)) {
			$buffer[] = $m[2];
			$i++;
		}

		return '<blockquote class="g-docs__quote">' . $this->render_blocks($buffer) . '</blockquote>';
	}

	private function render_table(array $lines, &$i, $count)
	{
		$header = self::split_row($lines[$i]);
		$aligns = self::row_alignments($lines[$i + 1]);
		$i += 2;

		$html = '<div class="g-docs__table-wrap"><table class="g-docs__table"><thead><tr>';
		foreach ($header as $index => $cell) {
			$html .= '<th scope="col"' . self::align_attr($aligns, $index) . '>' . $this->inline($cell) . '</th>';
		}
		$html .= '</tr></thead><tbody>';

		while ($i < $count && self::is_table_row($lines[$i])) {
			$cells = self::split_row($lines[$i]);
			$html .= '<tr>';
			foreach ($cells as $index => $cell) {
				$html .= '<td' . self::align_attr($aligns, $index) . '>' . $this->inline($cell) . '</td>';
			}
			$html .= '</tr>';
			$i++;
		}

		return $html . '</tbody></table></div>';
	}

	private function render_list(array $lines, &$i, $count)
	{
		$first = self::list_marker($lines[$i]);
		$type = $first['type'];
		$base_indent = $first['indent'];
		$start = $first['start'];
		$items = array();
		$loose = false;
		$blank_pending = false;

		while ($i < $count) {
			$line = $lines[$i];

			if (trim($line) === '') {
				$blank_pending = true;
				$i++;
				continue;
			}

			$marker = self::list_marker($line);
			$indent = strlen($line) - strlen(ltrim($line, ' '));

			if ($marker !== null && $marker['indent'] === $base_indent) {
				// A `-` list butting up against a `1.` list is two lists, not one.
				if ($marker['type'] !== $type) {
					break;
				}

				if ($blank_pending && !empty($items)) {
					$loose = true;
				}

				$items[] = array(
					'task' => $marker['task'],
					'indent' => $marker['content_indent'],
					'lines' => array($marker['text']),
				);
				$blank_pending = false;
				$i++;
				continue;
			}

			if (empty($items)) {
				break;
			}

			$last = count($items) - 1;

			if ($indent >= $items[$last]['indent']) {
				if ($blank_pending) {
					$items[$last]['lines'][] = '';
					$loose = true;
					$blank_pending = false;
				}
				$items[$last]['lines'][] = substr($line, $items[$last]['indent']);
				$i++;
				continue;
			}

			// An under-indented line still belongs to the item when no blank line
			// separated them — Markdown's lazy continuation. After a blank line it
			// is something else, and the list is over.
			if (!$blank_pending) {
				$items[$last]['lines'][] = ltrim($line);
				$i++;
				continue;
			}

			break;
		}

		$tag = $type === 'ol' ? 'ol' : 'ul';
		$attrs = ' class="g-docs__list"';
		if ($tag === 'ol' && $start !== 1) {
			$attrs .= ' start="' . esc_attr($start) . '"';
		}

		$html = '<' . $tag . $attrs . '>';

		foreach ($items as $item) {
			$content = $this->render_blocks($item['lines']);

			if (!$loose) {
				$content = self::unwrap_paragraph($content);
			}

			if ($item['task'] === null) {
				$html .= '<li class="g-docs__item">' . $content . '</li>';
			} else {
				$html .= '<li class="g-docs__item g-docs__item--task">'
					. '<input type="checkbox" class="g-docs__checkbox" disabled'
					. ($item['task'] ? ' checked' : '') . '> '
					. $content . '</li>';
			}
		}

		return $html . '</' . $tag . '>';
	}

	private function render_paragraph(array $lines, &$i, $count)
	{
		$buffer = array(trim($lines[$i]));
		$i++;

		while ($i < $count) {
			$line = $lines[$i];

			if (trim($line) === '' || $this->starts_block($lines, $i, $count)) {
				break;
			}

			$buffer[] = trim($line);
			$i++;
		}

		return '<p class="g-docs__p">' . $this->inline(implode("\n", $buffer)) . '</p>';
	}

	/**
	 * Whether a line inside a running paragraph in fact opens a new block.
	 *
	 * Only consulted for continuation lines, never for the first line of the
	 * paragraph — so a line that looks like a setext underline reads as a rule
	 * here. That is deliberate and `bin/check-docs.php` rejects setext headings,
	 * because supporting both would make every `---` ambiguous.
	 */
	private function starts_block(array $lines, $i, $count)
	{
		$line = $lines[$i];

		if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $line)) {
			return true;
		}

		if (preg_match('/^ {0,3}#{1,6}\s/', $line)) {
			return true;
		}

		if (self::is_rule($line)) {
			return true;
		}

		if (preg_match('/^ {0,3}>/', $line)) {
			return true;
		}

		if (self::list_marker($line) !== null) {
			return true;
		}

		if (self::is_table_row($line) && isset($lines[$i + 1]) && self::is_table_delimiter($lines[$i + 1])) {
			return true;
		}

		return false;
	}

	// -----------------------------------------------------------------------
	// Inline level
	// -----------------------------------------------------------------------

	/**
	 * Render inline markup and escape everything else.
	 *
	 * Order is the whole trick. Code spans come out first so their contents are
	 * never read as emphasis or a link; the remaining text is escaped before any
	 * rule inserts a tag, so nothing a doc file contains can become markup.
	 */
	private function inline($text)
	{
		$this->code_spans = array();

		$text = preg_replace_callback('/(`+)([\s\S]*?)\1/', array($this, 'lift_code_span'), (string) $text);
		$text = esc_html($text);
		$text = preg_replace_callback('/\[([^\]\n]*)\]\(([^)\s]*)\)/', array($this, 'render_link'), $text);
		$text = preg_replace('/\*\*(?=\S)([\s\S]+?)(?<=\S)\*\*/', '<strong>$1</strong>', $text);
		$text = preg_replace('/(?<![\w*])\*(?=\S)([^*\n]+?)(?<=\S)\*(?![\w*])/', '<em>$1</em>', $text);

		return $this->restore_code_spans($text);
	}

	private function lift_code_span($m)
	{
		$content = $m[2];

		// CommonMark strips one leading and one trailing space when both are
		// present, so `` ` `` and `` `a` `` both work. It does not trim further.
		if (strlen($content) > 1 && $content[0] === ' ' && substr($content, -1) === ' ' && trim($content) !== '') {
			$content = substr($content, 1, -1);
		}

		$this->code_spans[] = $content;

		return self::CODE_OPEN . (count($this->code_spans) - 1) . self::CODE_CLOSE;
	}

	private function restore_code_spans($text)
	{
		$spans = $this->code_spans;

		return preg_replace_callback(
			'/' . self::CODE_OPEN . '(\d+)' . self::CODE_CLOSE . '/',
			function ($m) use ($spans) {
				$index = (int) $m[1];

				return '<code class="g-docs__code">' . esc_html($spans[$index]) . '</code>';
			},
			$text
		);
	}

	/**
	 * Turn a Markdown link into something the admin can actually follow.
	 *
	 * Three of the four kinds are ordinary links. The fourth — a relative path to
	 * a file in the repository, which is most of the links in these documents —
	 * has nothing to open in wp-admin, so it renders as the path it names rather
	 * than as a link that 404s.
	 */
	private function render_link($m)
	{
		$text = $m[1];
		$href = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');

		if ($href === '') {
			return $text;
		}

		if ($href[0] === '#') {
			return '<a class="g-docs__link" href="' . esc_attr($href) . '">' . $text . '</a>';
		}

		if (preg_match('#^https?://#i', $href)) {
			return '<a class="g-docs__link g-docs__link--external" href="' . esc_url($href) . '"'
				. ' target="_blank" rel="noopener noreferrer">' . $text
				. '<span class="screen-reader-text"> ' . esc_html__('(opens in a new tab)', 'groove-folios') . '</span></a>';
		}

		$resolved = $this->resolve_doc_link($href);

		if ($resolved !== null) {
			return '<a class="g-docs__link" href="' . esc_url($resolved) . '">' . $text . '</a>';
		}

		return '<code class="g-docs__code g-docs__code--path">' . $text . '</code>';
	}

	/**
	 * Map a cross-reference between the two doc files onto the tab that renders it.
	 */
	private function resolve_doc_link($href)
	{
		$parts = explode('#', $href, 2);
		$file = basename($parts[0]);
		$fragment = isset($parts[1]) ? $parts[1] : '';

		// `../pages/folio.php` and `README.md` both basename to something short, so
		// only a path with no directory part of its own can be a sibling document.
		if ($file !== $parts[0] && ltrim($parts[0], './') !== $file) {
			return null;
		}

		if (!isset($this->args['doc_links'][$file])) {
			return null;
		}

		$url = $this->args['doc_links'][$file];

		return $fragment === '' ? $url : $url . '#' . $fragment;
	}

	// -----------------------------------------------------------------------
	// Small helpers
	// -----------------------------------------------------------------------

	private static function is_rule($line)
	{
		return (bool) preg_match('/^ {0,3}(-{3,}|\*{3,}|_{3,})\s*$/', $line);
	}

	private static function is_table_row($line)
	{
		return strpos(ltrim($line), '|') === 0;
	}

	private static function is_table_delimiter($line)
	{
		$trimmed = trim($line);

		if ($trimmed === '' || strpos($trimmed, '|') === false || strpos($trimmed, '-') === false) {
			return false;
		}

		return (bool) preg_match('/^\|?(\s*:?-+:?\s*\|)+\s*:?-*:?\s*\|?$/', $trimmed);
	}

	/**
	 * Split a table row on its cell separators.
	 *
	 * Walks the string rather than exploding on `|`, because a pipe inside a code
	 * span is content — `| `a|b` |` is one cell, and these documents are full of
	 * code spans carrying CSS selectors.
	 */
	private static function split_row($line)
	{
		$line = trim($line);
		$length = strlen($line);
		$cells = array();
		$current = '';
		$ticks = 0;

		for ($i = 0; $i < $length; $i++) {
			$char = $line[$i];

			if ($char === '\\' && $i + 1 < $length && $line[$i + 1] === '|') {
				$current .= '|';
				$i++;
				continue;
			}

			if ($char === '`') {
				$run = 0;
				while ($i + $run < $length && $line[$i + $run] === '`') {
					$run++;
				}
				if ($ticks === 0) {
					$ticks = $run;
				} elseif ($ticks === $run) {
					$ticks = 0;
				}
				$current .= str_repeat('`', $run);
				$i += $run - 1;
				continue;
			}

			if ($char === '|' && $ticks === 0) {
				$cells[] = $current;
				$current = '';
				continue;
			}

			$current .= $char;
		}

		$cells[] = $current;

		// A row written with the usual leading and trailing pipes produces an empty
		// cell at each end; drop those, but never a genuinely empty interior cell.
		if (count($cells) > 1 && trim($cells[0]) === '') {
			array_shift($cells);
		}
		if (count($cells) > 1 && trim($cells[count($cells) - 1]) === '') {
			array_pop($cells);
		}

		return array_map('trim', $cells);
	}

	private static function row_alignments($line)
	{
		$aligns = array();

		foreach (self::split_row($line) as $cell) {
			$left = strpos($cell, ':') === 0;
			$right = substr($cell, -1) === ':';

			if ($left && $right) {
				$aligns[] = 'center';
			} elseif ($right) {
				$aligns[] = 'right';
			} elseif ($left) {
				$aligns[] = 'left';
			} else {
				$aligns[] = '';
			}
		}

		return $aligns;
	}

	private static function align_attr(array $aligns, $index)
	{
		if (empty($aligns[$index])) {
			return '';
		}

		return ' class="g-docs__cell--' . esc_attr($aligns[$index]) . '"';
	}

	/**
	 * Describe a list marker, or return null when the line does not open an item.
	 */
	private static function list_marker($line)
	{
		if (preg_match('/^( *)([-*+])( +)(.*)$/', $line, $m)) {
			$text = $m[4];
			$task = null;

			if (preg_match('/^\[([ xX])\]( +|$)(.*)$/', $text, $t)) {
				$task = strtolower($t[1]) === 'x';
				$text = $t[3];
			}

			return array(
				'type' => 'ul',
				'indent' => strlen($m[1]),
				'content_indent' => strlen($m[1]) + 1 + strlen($m[3]),
				'start' => 1,
				'task' => $task,
				'text' => $text,
			);
		}

		if (preg_match('/^( *)(\d{1,9})([.)])( +)(.*)$/', $line, $m)) {
			return array(
				'type' => 'ol',
				'indent' => strlen($m[1]),
				'content_indent' => strlen($m[1]) + strlen($m[2]) + 1 + strlen($m[4]),
				'start' => (int) $m[2],
				'task' => null,
				'text' => $m[5],
			);
		}

		return null;
	}

	/**
	 * A single-paragraph list item renders without the <p>, which is what keeps a
	 * tight list tight.
	 */
	private static function unwrap_paragraph($html)
	{
		if (preg_match('#^<p class="g-docs__p">([\s\S]*)</p>$#', $html, $m) && strpos($m[1], '<p ') === false) {
			return $m[1];
		}

		return $html;
	}

	/**
	 * Reduce heading text to the plain words its anchor and contents entry are
	 * built from.
	 *
	 * Underscores survive. They are emphasis markers in Markdown generally, but
	 * not here — `_em_` is rejected by `bin/check-docs.php` precisely because
	 * these documents are written in snake_case — so stripping them turned
	 * `Base_Theme` into `BaseTheme` in the contents rail, and gave the heading an
	 * anchor GitHub does not agree with. GitHub's slug keeps them, and so does
	 * slug() below; this is the pass that was quietly taking them out first.
	 */
	private static function strip_inline($text)
	{
		$text = preg_replace('/`+([\s\S]*?)`+/', '$1', $text);
		$text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text);
		$text = str_replace(array('**', '*'), '', $text);

		return trim($text);
	}

	/**
	 * GitHub's heading slug, so an anchor written in a doc file — `#13-known-warts`
	 * — resolves both on GitHub and here, and so a section linked from one can be
	 * opened in the other.
	 *
	 * One space, one hyphen: `\s` and not `\s+`, because GitHub replaces spaces
	 * individually rather than collapsing runs. It is the run *left behind by
	 * stripped punctuation* that this is about — "Base_Theme — what you inherit"
	 * loses the em-dash and keeps the two spaces that surrounded it, so the anchor
	 * is `base_theme--what-you-inherit` with two hyphens. Collapsing here would
	 * read better and agree with nothing.
	 */
	private static function slug($text)
	{
		$slug = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
		$slug = preg_replace('/[^\p{L}\p{N}\s_-]+/u', '', $slug);
		$slug = preg_replace('/\s/u', '-', trim($slug));

		return $slug === '' ? 'section' : $slug;
	}

	private function unique_anchor($slug)
	{
		if (!isset($this->anchors[$slug])) {
			$this->anchors[$slug] = 0;

			return $slug;
		}

		$this->anchors[$slug]++;

		return $slug . '-' . $this->anchors[$slug];
	}
}
