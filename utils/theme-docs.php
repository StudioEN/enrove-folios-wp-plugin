<?php

namespace Enrove\Utils;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

/**
 * The theme documentation the admin can read, and the places it links into.
 *
 * Data only, and deliberately free of WordPress: `bin/check-docs.php` loads this
 * file on a bare checkout to prove that every anchor named below still exists in
 * the document it points at. That is the whole reason the anchors are constants
 * here rather than string literals inside `pages/themes.php` — a heading renamed
 * in `BUILDING-A-THEME.md` would otherwise turn an admin link into a silent jump
 * to the top of the page, which is exactly the class of breakage these documents
 * spend eleven hundred lines warning about.
 *
 * @since 0.4.0
 */
class Theme_Docs
{
	/**
	 * Tab key => file, relative to the plugin root.
	 *
	 * The key is what appears in `?tab_key=`, so it is part of every URL anyone
	 * pastes to a colleague. Adding a document here adds a tab; nothing else has
	 * to change.
	 */
	const DOCS = array(
		'spec' => 'themes/README.md',
		'playbook' => 'themes/BUILDING-A-THEME.md',
	);

	/**
	 * Where the Themes screen sends someone whose theme folder did not load.
	 *
	 * The playbook's section 9 is a table of the same reason codes the problem
	 * panel prints, which is why the panel links to the section rather than
	 * restating any of it.
	 */
	const SKIPPED_THEME_DOC = 'playbook';
	const SKIPPED_THEME_ANCHOR = '9-when-it-does-not-appear-in-the-picker';

	/**
	 * Whether a tab key names a document.
	 *
	 * The only thing that ever reaches the filesystem is a key that survives this,
	 * so no request parameter can name a path.
	 *
	 * @param string $key
	 * @return bool
	 */
	public static function exists($key)
	{
		// array_key_exists rather than isset(self::DOCS[$key]): isset() on a class
		// constant is a fatal before PHP 7.1, and this plugin's floor is 7.0.
		return is_string($key) && $key !== '' && array_key_exists($key, self::DOCS);
	}

	/**
	 * Absolute path of a document, or '' for a key that names none.
	 *
	 * @param string $key
	 * @param string $root Plugin root, trailing slash optional. Defaults to ENROVE_PATH.
	 * @return string
	 */
	public static function path($key, $root = '')
	{
		if (!self::exists($key)) {
			return '';
		}

		if ($root === '') {
			$root = defined('ENROVE_PATH') ? ENROVE_PATH : '';
		}

		return rtrim($root, '/\\') . '/' . self::DOCS[$key];
	}

	/**
	 * Read a document, or '' when it is not on disk.
	 *
	 * A missing file is a real possibility rather than a defensive flourish: the
	 * documents are plain files in the plugin folder and a deployment that copies
	 * only `*.php` leaves the tab with nothing to render.
	 *
	 * @param string $key
	 * @param string $root
	 * @return string
	 */
	public static function read($key, $root = '')
	{
		$path = self::path($key, $root);

		if ($path === '' || !is_file($path) || !is_readable($path)) {
			return '';
		}

		$contents = file_get_contents($path);

		return $contents === false ? '' : $contents;
	}
}
