<?php
/**
 * Enrove Folios — folio theme contract conformance check.
 *
 * Reports how each theme fills the --folio-* slots declared in
 * assets/css/folio-contract.css, and flags the mistakes that fail silently — in
 * the stylesheet and in the theme's PHP. The PHP half checks the contracts that
 * produce no error of any kind when broken: a root element that misses
 * Font_Loader's selector, a display_theme() that never loads its data, a
 * get_data() with no preview guard, a folder name that does not match the ID
 * derived from setup.php, a page class re-implementing what Base_Theme provides.
 *
 * Usage:
 *   php bin/check-theme-contract.php [options]
 *
 * Options:
 *   --theme=<slug>  Check one theme instead of all of them. (`--theme=` with no
 *                   slug is indistinguishable from omitting the flag, because
 *                   getopt drops it, so it checks everything.)
 *   --dir=<path>    Check a theme outside this plugin's themes/ folder — a
 *                   theme being developed anywhere else. Point it at one theme
 *                   folder or at a folder of them.
 *   --strict        Exit non-zero if any theme has a warning (for CI).
 *   --help          Show this help.
 *
 * Exit codes: 0 clean (or warnings without --strict), 1 warnings under
 * --strict, 2 the contract file could not be read or --theme named a theme
 * that is not there.
 *
 * Nothing here talks to WordPress — the stylesheets are read as text and the
 * PHP is tokenised rather than executed, so it runs on a checkout with no
 * install. setup.php is the one file included, which is safe because it is a
 * literal array with no side effects.
 */


// Two ways in, and only two: `php bin/check-theme-contract.php` from a shell, or
// code including it as a library on a WordPress request (the theme installer
// did, until 0.5.1). A browser asking for the file directly gets nothing. The
// file is a development tool and is left out of the release zip. The shell defines ABSPATH for itself (as
// bin/check-docs.php does) so the guard can be the plain form Plugin Check reads.
if (PHP_SAPI === 'cli' && !defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress's own constant, defined only in a shell where WordPress is not loaded.
}
if (!defined('ABSPATH')) {
    exit;
}

// This file stopped being CLI-only when Themes_Manager began including it to
// check a package at install time (until 0.5.1), which put it on a WordPress
// request, and whatever brings third-party themes back may do so again. The
// rest of the plugin's runtime code uses no PHP 8 function anywhere — it is
// written to the 7.x floor — and three of them are used below, so on a 7.x host
// the include would fatal and take the upload with it. Defining them is a
// smaller price than rewriting the fourteen call sites, and on PHP 8 these
// cost nothing: function_exists is true and the bodies never run.
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

/** Strip comments so a slot named only in prose is never counted as declared. */
function enrove_strip_comments(string $css): string
{
    return preg_replace('#/\*.*?\*/#s', '', $css);
}

/** Every --folio-* name the contract declares, in declaration order. */
function enrove_contract_slots(string $css): array
{
    preg_match_all('/^\s*(--folio-[a-z0-9-]+)\s*:/m', enrove_strip_comments($css), $m);
    return array_values(array_unique($m[1]));
}


// ---------------------------------------------------------------------------
// The PHP half of the contract.
//
// Everything above is CSS. Everything below is the set of rules the skill files
// under "get these wrong and the theme silently misbehaves" — and every one of
// them is PHP, so none of them was checked by anything until now. They share a
// shape: nothing errors, nothing logs, the theme just quietly does less than it
// should. A theme with the wrong root class renders perfectly and ignores the
// folio's fonts. One missing the preview guard renders live and blank in the
// picker.
//
// Still pure text analysis: tokenised rather than executed, so this runs on a
// checkout with no WordPress. setup.php is the one exception — it is a literal
// array with no side effects, which is exactly why the plugin includes it
// repeatedly, so including it here is safe.
// ---------------------------------------------------------------------------

/**
 * WordPress' sanitize_title, closely enough for a theme name.
 *
 * The three things a naive [^a-z0-9]+ gets wrong, and each one would report a
 * correct theme as broken: WP folds accents to their ASCII letter rather than
 * dropping them (Café → cafe, not caf), removes apostrophes rather than turning
 * them into a separator (Designer's → designers, not designer-s), and keeps the
 * underscore.
 */
function enrove_sanitize_title(string $title): string
{
    $slug = $title;

    if (function_exists('iconv')) {
        $folded = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
        if (is_string($folded) && $folded !== '') {
            // //TRANSLIT can render 'é' as "'e" depending on locale; drop the
            // combining punctuation it leaves behind rather than keeping it.
            $slug = preg_replace('/[\'"`^~]/', '', $folded);
        }
    }

    $slug = strtolower(trim($slug));
    $slug = str_replace(['\'', "\xe2\x80\x99"], '', $slug);   // straight and curly apostrophe
    $slug = preg_replace('/[^a-z0-9_]+/', '-', $slug);

    return trim($slug, '-');
}

/**
 * The token range of one class's body, as [start, end] offsets, or null.
 *
 * Without this every method lookup takes the first match in the FILE, so a
 * helper class or a trait declared above the view class shadows it — and a
 * decoy with a correct-looking display_theme() silently satisfies checks the
 * real class fails.
 */
function enrove_class_token_range(array $tokens, string $class): ?array
{
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_CLASS) {
            continue;
        }

        $j = $i + 1;
        while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            $j++;
        }
        if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || $tokens[$j][1] !== $class) {
            continue;
        }

        $depth = 0;
        for ($k = $j; $k < $count; $k++) {
            $text = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
            if ($text === '{') {
                $depth++;
            } elseif ($text === '}') {
                $depth--;
                if ($depth === 0) {
                    return [$j, $k];
                }
            }
        }
    }

    return null;
}

/**
 * The source of one method, brace-matched over tokens rather than text, so a
 * brace inside a string or a block of inline HTML cannot end it early.
 *
 * Pass $class to scope the search to that class's body — always do, where the
 * class is known, or a decoy declared earlier in the file answers for it.
 */
function enrove_method_source(string $src, string $method, ?string $class = null): ?string
{
    $tokens = token_get_all($src);
    $count  = count($tokens);
    $from   = 0;

    if ($class !== null) {
        $range = enrove_class_token_range($tokens, $class);
        if ($range === null) {
            return null;
        }
        [$from, $count] = $range;
    }

    for ($i = $from; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }

        // The name is the next T_STRING; skip whitespace and a by-ref '&'.
        $j = $i + 1;
        while ($j < $count && (
            is_array($tokens[$j]) && ($tokens[$j][0] === T_WHITESPACE || $tokens[$j][1] === '&')
            || $tokens[$j] === '&'
        )) {
            $j++;
        }
        if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || $tokens[$j][1] !== $method) {
            continue;
        }

        // Walk to the body's opening brace, then match it.
        $depth = 0;
        $body  = '';
        for ($k = $j; $k < $count; $k++) {
            $text = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
            $id   = is_array($tokens[$k]) ? $tokens[$k][0] : null;

            if ($text === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
            } elseif ($text === '}') {
                $depth--;
                if ($depth === 0) {
                    return $body . '}';
                }
            } elseif ($text === ';' && $depth === 0) {
                // An abstract or interface declaration of the same name. Keep
                // looking rather than giving up: returning here would report
                // "no display_theme()" on a theme that has one, and would
                // silently switch off every check that reads a method body.
                continue 2;
            }

            // Comments are not an implementation. Every check below regex-scans
            // this body, so leaving them in means a contract can be satisfied by
            // the commented-out call somebody meant to restore.
            if ($depth > 0 && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
                $body .= $text;
            }
        }
    }

    return null;
}

/** Class name => parent name, for every class declared in the source. */
function enrove_declared_classes(string $src): array
{
    preg_match_all(
        '/^\s*(?:final\s+|abstract\s+|readonly\s+)*class\s+(\w+)(?:\s+extends\s+([\\\\\w]+))?/mi',
        enrove_strip_php_comments($src),
        $m,
        PREG_SET_ORDER
    );

    $out = [];
    foreach ($m as $c) {
        $out[$c[1]] = $c[2] ?? '';
    }

    return $out;
}

/** Source with PHP comments and HTML comments removed. */
function enrove_strip_php_comments(string $src): string
{
    $out = '';
    foreach (token_get_all($src) as $token) {
        if (is_array($token)) {
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                continue;
            }
            $text = $token[1];
            if ($token[0] === T_INLINE_HTML) {
                $text = preg_replace('/<!--.*?-->/s', '', $text);
            }
            $out .= $text;
            continue;
        }
        $out .= $token;
    }

    return $out;
}

/**
 * Every `class="…"` attribute value in the source, in document order.
 *
 * Handles both quote styles and the escaped form inside an echoed string. A
 * value carrying PHP is returned as-is: the caller has to decide, because its
 * final text is not knowable here and guessing produces a false positive on a
 * root that is perfectly correct at runtime.
 */
function enrove_class_attributes(string $src): array
{
    $src = enrove_strip_php_comments($src);

    // An attribute written inside an echoed PHP string arrives escaped.
    $src = str_replace('\\"', '"', $src);

    if (!preg_match_all('/class\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/', $src, $m, PREG_SET_ORDER)) {
        return [];
    }

    $out = [];
    foreach ($m as $hit) {
        // Group 2 only exists when the single-quoted alternative matched.
        $out[] = isset($hit[2]) && $hit[1] === '' ? $hit[2] : $hit[1];
    }

    return $out;
}

/** True when a class attribute's final text cannot be known without running PHP. */
function enrove_attribute_is_dynamic(string $value): bool
{
    return str_contains($value, '<?') || str_contains($value, '$');
}

/**
 * A class attribute with Base_Theme's own class helpers taken out. Their output
 * is known: get_cover_fallback_class() returns '' or ' g-folio__cover-fallback',
 * which can neither supply nor remove the tokens the root rules look for. Left
 * in, the echo made every theme using it "dynamic", and its root went unchecked.
 */
function enrove_without_known_class_helpers(string $value): string
{
    return (string) preg_replace(
        '/<\?php\s+echo\s+(?:esc_attr\s*\(\s*)?\$this->get_cover_fallback_class\s*\(\s*\)\s*\)?\s*;?\s*\?>/',
        '',
        $value
    );
}

/**
 * The class attribute of the element a view actually roots itself in: the first
 * one inside display_theme().
 *
 * Scanning the whole file instead is what made this check useless — in
 * enrove-newsletter a nav button 178 lines from the root carries
 * "g-folio__theme-page-nav-button gn-nav-trigger gn-nav-trigger--page", which
 * satisfies the selector while saying nothing about the root.
 */
function enrove_root_class_attribute(string $src, ?string $class = null): ?string
{
    $display = enrove_method_source($src, 'display_theme', $class);
    if ($display === null) {
        return null;
    }

    $attributes = enrove_class_attributes($display);

    return $attributes[0] ?? null;
}

/**
 * Does a class reach Base_Theme through its parents?
 *
 * `is_subclass_of` is transitive and the registry uses it, so a theme may put a
 * shared base between its view class and Base_Theme and load it through
 * `dependencies` — which is what dependencies are for. A one-level substring
 * test would fail that theme and pass `extends Definitely_Not_Base_Theme`.
 */
function enrove_reaches_base_theme(string $class, string $dir, array $dependencies = []): bool
{
    $files = glob($dir . '/*.php') ?: [];

    // A dependency may live in a subdirectory, and it is exactly where a shared
    // base class would sit — so read what setup.php actually declares rather
    // than only the top level.
    foreach ($dependencies as $dep) {
        if (is_string($dep) && $dep !== '' && !str_contains($dep, '..') && is_readable($dir . '/' . $dep)) {
            $files[] = $dir . '/' . $dep;
        }
    }

    $map     = [];
    $aliases = [];

    foreach (array_unique($files) as $file) {
        $src = file_get_contents($file);

        foreach (enrove_declared_classes($src) as $name => $parent) {
            $map[$name] = $parent;
        }

        // `use Enrove\Themes\Base_Theme as BT;` makes BT a legitimate parent
        // name; without this the chain stops at an alias it cannot resolve.
        if (preg_match_all('/^\s*use\s+([\\\\\w]+)(?:\s+as\s+(\w+))?\s*;/mi', enrove_strip_php_comments($src), $m, PREG_SET_ORDER)) {
            foreach ($m as $u) {
                $target = substr(strrchr('\\' . $u[1], '\\'), 1);
                $aliases[$u[2] ?? $target] = $target;
            }
        }
    }

    $seen = [];

    while ($class !== '' && !isset($seen[$class])) {
        $seen[$class] = true;

        $parent = $map[$class] ?? '';
        $short  = $parent === '' ? '' : substr(strrchr('\\' . $parent, '\\'), 1);
        $short  = $aliases[$short] ?? $short;

        if ($short === 'Base_Theme') {
            return true;
        }

        // A parent nothing in this theme declares is the end of the chain. The
        // registry's is_subclass_of would return false here too, so this is a
        // finding rather than something to wave through.
        if ($short === '' || !isset($map[$short])) {
            return false;
        }

        $class = $short;
    }

    return false;
}

/**
 * Warnings for one theme's PHP. Ordered roughly by how badly the mistake bites:
 * a theme that will not register at all comes before one that renders with the
 * wrong typeface.
 */
function enrove_php_warnings(string $dir, string $root): array
{
    $id    = basename($dir);
    $warn  = [];
    $setup_path = $dir . '/setup.php';

    if (!is_readable($setup_path)) {
        return ['no readable setup.php — load_builtin_themes() skips this folder silently'];
    }

    // Including setup.php is what the plugin does, and it is safe because the
    // file is a literal array. Check that before trusting it, so a theme that
    // grew a side effect yields one warning rather than a fatal that takes the
    // whole report down before a single row prints.
    $setup_tokens = token_get_all(file_get_contents($setup_path));
    $count = count($setup_tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $setup_tokens[$i];
        if (!is_array($token)) {
            continue;
        }

        if (in_array($token[0], [T_ECHO, T_PRINT, T_EVAL, T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE], true)) {
            return ["setup.php uses {$token[1]} — it must be a literal array with no side effects, "
                . 'because discovery, install validation and get_setup_data() all include it repeatedly'];
        }

        // A bare T_STRING is not a call. `true`, `false`, `null` and any
        // constant are T_STRING too, and flagging one of those used to report a
        // legal setup.php AND return early, taking every other check with it.
        if ($token[0] !== T_STRING) {
            continue;
        }

        $j = $i + 1;
        while ($j < $count && is_array($setup_tokens[$j]) && $setup_tokens[$j][0] === T_WHITESPACE) {
            $j++;
        }

        if ($j < $count && $setup_tokens[$j] === '(') {
            return ["setup.php calls {$token[1]}() — it must be a literal array with no side effects, "
                . 'because discovery, install validation and get_setup_data() all include it repeatedly'];
        }
    }

    $setup = include $setup_path;
    if (!is_array($setup)) {
        return ['setup.php does not return an array — the folder is skipped silently'];
    }

    // --- Registration: the checks load_builtin_themes() makes, and fails mutely.
    foreach (['name', 'cover_class', 'page_class'] as $key) {
        if (empty($setup[$key])) {
            $warn[] = "setup.php has no {$key} — the theme cannot register and will not appear in the picker";
        }
    }

    if (!empty($setup['name'])) {
        $name    = (string) $setup['name'];
        $derived = enrove_sanitize_title($name);

        // Outside plain Latin text this cannot reliably reproduce WordPress —
        // remove_accents has a table we do not, iconv is locale-sensitive, and
        // entities and symbols each have their own rule. Every divergence would
        // surface as "your folder name is wrong" about a folder that is right,
        // so where the name leaves that ground, say nothing.
        $certain = (bool) preg_match('/^[A-Za-z0-9 _\'\x{2019}-]+$/u', $name);

        if ($certain && $derived !== $id) {
            $warn[] = "folder is '{$id}' but the ID derived from name is '{$derived}' — folios store "
                . 'the derived ID, so lookups, the picker and migrations key on a value this folder '
                . 'does not answer to';
        }
    }

    if (array_key_exists('dependencies', $setup)) {
        if (!is_array($setup['dependencies'])) {
            $warn[] = 'setup.php dependencies is not an array — install validation rejects the package';
        } else {
            foreach ($setup['dependencies'] as $dep) {
                if (!is_string($dep) || $dep === '' || str_contains($dep, '..')) {
                    $warn[] = 'setup.php dependencies contains an empty or traversing path — install '
                        . 'validation rejects the ZIP, and a built-in theme requires nothing at all';
                } elseif (!is_readable($dir . '/' . $dep)) {
                    $warn[] = "setup.php lists dependency '{$dep}', which is not there — the loaders skip "
                        . 'the theme rather than register something that cannot render';
                }
            }
        }
    }

    // --- The two view classes.
    $views = [
        'cover' => ['file' => $dir . '/cover.php', 'class_key' => 'cover_class'],
        'page'  => ['file' => $dir . '/page.php',  'class_key' => 'page_class'],
    ];

    $sources    = [];
    $classes_of = [];
    foreach ($views as $role => $view) {
        if (!is_readable($view['file'])) {
            $warn[] = "no readable {$role}.php — the folder is skipped silently";
            continue;
        }

        $sources[$role] = file_get_contents($view['file']);
        $src = $sources[$role];

        // Every body lookup below is scoped to the class setup.php names, so a
        // decoy or helper class earlier in the file cannot answer for it.
        $view_class = $setup[$view['class_key']] ?? '';
        $view_class = $view_class === '' ? null : substr(strrchr('\\' . $view_class, '\\'), 1);
        $classes_of[$role] = $view_class;

        if (!preg_match('/defined\(\s*[\'"]ABSPATH[\'"]\s*\)/', enrove_strip_php_comments($src))) {
            $warn[] = "{$role}.php has no ABSPATH guard — the file is reachable directly over HTTP";
        }

        $classes = enrove_declared_classes($src);
        $wanted  = (string) ($setup[$view['class_key']] ?? '');
        $short   = $wanted === '' ? '' : substr(strrchr('\\' . $wanted, '\\'), 1);

        if ($short !== '' && !isset($classes[$short])) {
            $warn[] = "setup.php names {$view['class_key']} {$wanted} but {$role}.php declares no class {$short} "
                . '— registration fails the class_exists check and the folder is skipped silently';
        } elseif ($short !== '' && !enrove_reaches_base_theme($short, $dir, (array) ($setup['dependencies'] ?? []))) {
            $warn[] = "{$role}.php class {$short} does not reach Base_Theme through its parents — registration "
                . 'fails the is_subclass_of check and the folder is skipped silently';
        }

        if (!empty($setup['namespace'])) {
            $declared = preg_match('/^\s*namespace\s+([^;]+);/m', enrove_strip_php_comments($src), $nm)
                ? trim($nm[1])
                : '';

            if ($declared === '') {
                $warn[] = "{$role}.php declares no namespace but setup.php says {$setup['namespace']} — the "
                    . 'class lands in the global namespace, class_exists fails and the folder is skipped silently';
            } elseif (ltrim($declared, '\\') !== ltrim((string) $setup['namespace'], '\\')) {
                $warn[] = "{$role}.php declares namespace {$declared}, setup.php says {$setup['namespace']} "
                    . '— the class the registry looks for will not exist';
            }
        }

        // display_theme() must load the data before it draws anything.
        $display = enrove_method_source($src, 'display_theme', $view_class);
        if ($display === null) {
            $warn[] = "{$role}.php has no display_theme() — nothing renders";
        } elseif (!preg_match('/parent::display_theme\s*\(/', $display)) {
            $warn[] = "{$role}.php display_theme() never calls parent::display_theme() — that call is what "
                . 'runs get_data(), so the theme renders an empty shell';
        }

        // The preview injects fabricated posts and then get_data() wipes them.
        $get_data = enrove_method_source($src, 'get_data', $view_class);
        if ($get_data !== null && !preg_match('/if\s*\(\s*\$this->is_preview_mode\s*\)/', $get_data)) {
            $warn[] = preg_match('/is_preview_mode/', $get_data)
                ? "{$role}.php get_data() names is_preview_mode but not as a plain "
                    . 'if ($this->is_preview_mode) { return; } guard — inverted, it returns on every real '
                    . 'request and the live folio renders blank'
                : "{$role}.php overrides get_data() without an is_preview_mode guard — the theme-picker "
                    . 'preview renders blank while the live folio is fine';
        }

        // Overriding ensure_script() in one view and not the other is the single
        // most common bug in these themes: the cover loads the JS, the page does not.
        $ensure = enrove_method_source($src, 'ensure_script', $view_class);
        if ($ensure !== null && !preg_match('/parent::ensure_script\s*\(/', $ensure)) {
            $warn[] = "{$role}.php ensure_script() never calls parent::ensure_script() — the theme drops the "
                . 'shared CSS, the token contract and its own fonts';
        }

    }

    if (isset($sources['cover'], $sources['page'])) {
        $cover_has = enrove_method_source($sources['cover'], 'ensure_script', $classes_of['cover'] ?? null) !== null;
        $page_has  = enrove_method_source($sources['page'], 'ensure_script', $classes_of['page'] ?? null) !== null;
        if ($cover_has !== $page_has) {
            $only = $cover_has ? 'cover.php' : 'page.php';
            $missing = $cover_has ? 'page.php' : 'cover.php';
            $warn[] = "ensure_script() is overridden in {$only} but not {$missing} — the two views load "
                . 'different assets, which shows up as the theme working on one and not the other';
        }
    }

    // --- The font-injection contract, which is a selector match on the ROOT
    //     element specifically — so it is read off the first class attribute
    //     display_theme() emits, not off whichever element in the file happens
    //     to satisfy it.
    if (isset($sources['cover'])) {
        $root_class = enrove_root_class_attribute($sources['cover'], $classes_of['cover'] ?? null);
        $root_known = $root_class === null ? null : enrove_without_known_class_helpers($root_class);

        if ($root_known !== null && !enrove_attribute_is_dynamic($root_known)
            && !str_contains($root_known, 'g-folio__theme-cover')) {
            $warn[] = "cover.php roots itself in class=\"{$root_class}\", which does not carry "
                . 'g-folio__theme-cover — Font_Loader injects the folio\'s fonts into that selector, so the '
                . 'cover silently ignores the font pickers';
        }
    }

    if (isset($sources['page'])) {
        // Font_Loader's selector is [class*="g-folio__theme-"][class$="-page"]:
        // an attribute-suffix match on the whole string, so the LAST class
        // listed has to end in -page. No rtrim here — a trailing space in the
        // attribute breaks $= for real, and reporting it is the point.
        $root_class = enrove_root_class_attribute($sources['page'], $classes_of['page'] ?? null);
        $root_known = $root_class === null ? null : enrove_without_known_class_helpers($root_class);

        if ($root_known !== null && !enrove_attribute_is_dynamic($root_known)
            && !(str_contains($root_known, 'g-folio__theme-') && str_ends_with($root_known, '-page'))) {
            $warn[] = "page.php roots itself in class=\"{$root_class}\", which must both contain "
                . 'g-folio__theme- and END in -page — that is Font_Loader\'s selector, so the page silently '
                . 'ignores the font pickers';
        }

        $get_content = enrove_method_source($sources['page'], 'get_content', $classes_of['page'] ?? null);
        if ($get_content !== null && !preg_match('/apply_embed_processing\s*\(/', $get_content)) {
            $warn[] = 'page.php get_content() does not run its HTML through apply_embed_processing() — themes '
                . 'bypass the_content, so a bare Spotify or YouTube URL stays inert text';
        }

        $ctor = enrove_method_source($sources['page'], '__construct', $classes_of['page'] ?? null);
        if ($ctor !== null && preg_match('/folio_id/', $ctor) && !preg_match('/resolve_page_folio_id\s*\(/', $ctor)) {
            $warn[] = 'page.php __construct() resolves folio_id without resolve_page_folio_id() — the canonical '
                . 'order is URL path, then meta, then request param, and a private copy drifts from it';
        }


    }

    // A font CDN anywhere in the theme's PHP, not just in the two view files —
    // blocks.php is where a theme most plausibly enqueues one, and is one of the
    // three files Enrove Proposal used to load Fraunces from.
    foreach ((glob($dir . '/*.php') ?: []) as $file) {
        if (preg_match('#fonts\.(googleapis|gstatic)\.com#', enrove_strip_php_comments(file_get_contents($file)))) {
            $warn[] = basename($file) . ' names a font CDN — a theme declares fonts in setup.php and lets '
                . 'Font_Loader fetch them; it never loads one itself';
        }
    }

    // These moved onto Base_Theme precisely because the copies drifted, so a
    // private copy in either view is the thing to catch.
    $inherited = ['get_folio_data', 'get_current_index', 'get_prev_page', 'get_next_page', 'to_anchor_name', 'get_html_id'];
    foreach ($sources as $role => $src) {
        $reimplemented = [];
        foreach ($inherited as $method) {
            if (enrove_method_source($src, $method, $classes_of[$role] ?? null) !== null) {
                $reimplemented[] = $method . '()';
            }
        }
        if ($reimplemented) {
            $warn[] = "{$role}.php re-implements " . implode(', ', $reimplemented) . ' — these live on '
                . 'Base_Theme; a private copy renders correctly today and misses every fix made to the shared one';
        }
    }

    // --- Theme JS that repaints must write the slot, not an alias of it.
    //
    // The first rule in this file that reads assets/js at all. README §7 spends
    // forty-odd lines on this contract and nothing verified it, which is how
    // enrove-newsletter came to set --gn-focus at runtime while --gn-focus is
    // declared as var(--folio-focus): the ring rendered in the live palette and
    // --folio-focus stayed on its stylesheet literal, so anything reading the
    // contract got a colour the page was not showing.
    //
    // Only aliases are flagged. A private holding a literal has to be written
    // directly — nothing points at it — and the themes write plenty of those on
    // purpose, so flagging every private write would be noise. The test is
    // whether the theme's own CSS declares the name as `--private: var(--folio-*)`.
    $js_files = glob($dir . '/assets/js/*.js') ?: [];
    $theme_css = $dir . '/assets/css/theme.css';

    if ($js_files && is_readable($theme_css)) {
        // Comments stripped, so an alias shown in prose is never read as one.
        $css_src = enrove_strip_comments((string) file_get_contents($theme_css));
        $aliases = [];
        if (preg_match_all('/(--[a-z0-9-]+)\s*:\s*var\(\s*(--folio-[a-z0-9-]+)/i', $css_src, $am, PREG_SET_ORDER)) {
            foreach ($am as $a) {
                $aliases[strtolower($a[1])] = strtolower($a[2]);
            }
        }

        foreach ($js_files as $js) {
            $js_src = (string) file_get_contents($js);
            if (!preg_match_all('/setProperty\(\s*[\'"](--[a-z0-9-]+)/i', $js_src, $wm)) {
                continue;
            }

            foreach (array_unique($wm[1]) as $written) {
                $written = strtolower($written);
                if (str_starts_with($written, '--folio-') || !isset($aliases[$written])) {
                    continue;
                }

                $warn[] = basename($js) . " sets {$written} at runtime, but theme.css declares it as "
                    . "var({$aliases[$written]}) — an inline value on the alias renders correctly and "
                    . "leaves the slot on its stylesheet literal, so anything reading the contract gets "
                    . "a colour the page is not showing. Set {$aliases[$written]} instead";
            }
        }
    }

    // --- The password gate borrows the theme's colours, from setup.php.
    //
    // This used to check two hardcoded ID maps in the plugin, which a theme
    // shipped as a package could not join — plugin source, no filter — so the
    // rule failed permanently for every third-party theme and could only be
    // satisfied by patching core, which an update then overwrote. It is a
    // setup.php declaration now, which a package can actually carry.
    $gate_declared = (array) ($setup['gate'] ?? []);
    $gate_missing = array_values(array_filter(
        ['accent', 'accent_hover', 'background'],
        static function ($slot) use ($gate_declared) {
            return empty($gate_declared[$slot])
                || !preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', (string) $gate_declared[$slot]);
        }
    ));

    if ($gate_missing) {
        $warn[] = 'setup.php declares no usable gate ' . implode('/', $gate_missing)
            . ' — a password-protected folio on this theme shows a gate in the plugin\'s default '
            . 'colours rather than its own. Values must be hex, because they are interpolated into CSS';
    }

    return $warn;
}

/**
 * The slots a theme may ignore and still conform: the space and type ramps are
 * opt-in, so a theme that leaves them alone is conforming, not lacking.
 */
function enrove_contract_optional_slots(array $slots): array
{
    return array_values(array_filter($slots, static function ($s) {
        return str_starts_with($s, '--folio-space-')
            || str_starts_with($s, '--folio-text-') && $s !== '--folio-text-muted' && $s !== '--folio-text-subtle'
            || str_starts_with($s, '--folio-leading-');
    }));
}

/**
 * The colour slots every theme is expected to answer.
 *
 * A function rather than two lines at each call site: the CLI and
 * Themes_Manager::check_theme_contract() both need this split, and a second
 * copy of the rule is a second thing to keep in step.
 */
function enrove_contract_core_slots(array $slots): array
{
    return array_values(array_diff($slots, enrove_contract_optional_slots($slots)));
}

// ── CLI entry point ──────────────────────────────────────────────────────────
//
// Everything above is callable as a library. Code that includes this file to
// check a theme it has just unpacked (the installer did, until 0.5.1) must not
// inherit getopt(), the help text, or any of the exits below — so it defines
// this constant first.
if (defined('ENROVE_THEME_CONTRACT_LIB')) {
    return;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI-only code (a WordPress include returns above): shell-script globals, plain-text terminal output and STDERR messages, with no WordPress loaded to escape or write through.

$root = dirname(__DIR__);
$opts = getopt('', ['theme::', 'dir::', 'strict', 'help']);

if (isset($opts['help'])) {
    $doc = file_get_contents(__FILE__);
    if (preg_match('#/\*\*(.*?)\*/#s', $doc, $m)) {
        echo trim(preg_replace('/^\s*\* ?/m', '', str_replace('*/', '', $m[1]))), "\n";
    }
    exit(0);
}

$contract_path = $root . '/assets/css/folio-contract.css';
if (!is_readable($contract_path)) {
    fwrite(STDERR, "Cannot read {$contract_path}\n");
    exit(2);
}

$contract = file_get_contents($contract_path);
$slots = enrove_contract_slots($contract);

$optional = enrove_contract_optional_slots($slots);
$core = enrove_contract_core_slots($slots);

// --dir points the checker at a theme that is not in this plugin's themes/
// folder — which is every third-party theme. Without it the only tool that
// knows these rules could never be run against the artefact a theme author
// actually ships.
// $root stays the plugin, because the cross-file checks (the password-gate
// colour maps, Base_Theme's helper list) resolve against plugin source.
if (isset($opts['dir'])) {
    if ($opts['dir'] === false || $opts['dir'] === '') {
        fwrite(STDERR, "--dir needs a path, for example --dir=../my-theme\n");
        exit(2);
    }
    $dir_arg = realpath($opts['dir']);
    if ($dir_arg === false || !is_dir($dir_arg)) {
        fwrite(STDERR, "No such directory: {$opts['dir']}\n");
        exit(2);
    }
    // A folder holding theme folders is as useful to point at as one theme.
    $theme_dirs = is_readable($dir_arg . '/setup.php')
        ? [$dir_arg]
        : (glob($dir_arg . '/*', GLOB_ONLYDIR) ?: [$dir_arg]);
} else {
    $theme_dirs = glob($root . '/themes/*', GLOB_ONLYDIR) ?: [];
}

if (isset($opts['theme'])) {
    if ($opts['theme'] === false || $opts['theme'] === '') {
        fwrite(STDERR, "--theme needs a slug, for example --theme=folio-starter\n");
        exit(2);
    }

    $theme_dirs = array_values(array_filter($theme_dirs, static function ($d) use ($opts) {
        return basename($d) === $opts['theme'];
    }));
    if (!$theme_dirs) {
        fwrite(STDERR, "No such theme: {$opts['theme']}\n");
        exit(2);
    }
}

$had_warning = false;
$rows = [];

// enrove_check_theme_dir() is library code that also runs on WordPress requests.
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

/**
 * Check one theme folder against the contract.
 *
 * Extracted from the CLI loop so something other than the CLI can call it.
 * The package installer (removed in 0.5.1) ran it over each theme it unpacked;
 * whatever brings third-party themes back should do the same.
 *
 * @param string $dir  Absolute path to the theme folder.
 * @param string $root Plugin root; what the cross-file checks resolve against.
 * @param array  $core Core slot names, from enrove_contract_slots().
 * @return array|null  Row of id/filled/total/missing/warn, or null when the
 *                     folder carries none of a theme's four marker files.
 */
function enrove_check_theme_dir(string $dir, string $root, array $core): ?array
{
    $id = basename($dir);

    // "Is this a theme?" cannot be "does it have a setup.php", because a folder
    // that is missing one is the exact failure this tool exists to name — that
    // is how load_builtin_themes() skips a theme in silence. Anything carrying
    // one of a theme's four files is a theme package making a claim; anything
    // else in themes/ is not our business.
    $looks_like_theme = false;
    foreach (['setup.php', 'cover.php', 'page.php', 'assets/css/theme.css'] as $marker) {
        if (is_readable($dir . '/' . $marker)) {
            $looks_like_theme = true;
            break;
        }
    }
    if (!$looks_like_theme) {
        return null;
    }

    $warn = enrove_php_warnings($dir, $root);

    $css = $dir . '/assets/css/theme.css';
    if (!is_readable($css)) {
        $warn[] = 'no assets/css/theme.css — Base_Theme enqueues that path by convention, so the theme '
            . 'renders unstyled';
        return ['id' => $id, 'filled' => 0, 'total' => count($core), 'missing' => [], 'warn' => $warn];
    }

    $src = enrove_strip_comments(file_get_contents($css));

    if (preg_match('/@import/i', $src)) {
        $warn[] = 'theme.css has an @import — fonts are declared in setup.php and fetched by Font_Loader, '
            . 'and a render-blocking import is what that replaced';
    }

    // Which core slots does this theme actually declare?
    $filled = [];
    foreach ($core as $slot) {
        if (preg_match('/^\s*' . preg_quote($slot, '/') . '\s*:/m', $src)) {
            $filled[] = $slot;
        }
    }

    // Every custom property this theme declares, name => first value.
    preg_match_all('/(--[a-z0-9-]+)\s*:\s*([^;}]+)/i', $src, $m, PREG_SET_ORDER);
    $decls = [];
    foreach ($m as $d) {
        $decls[$d[1]] = $decls[$d[1]] ?? trim($d[2]);
    }

    // The silent failure: a slot aliased ONTO a private token rather than the
    // other way round. Then overriding the slot does nothing, because the slot
    // is downstream of the private name instead of upstream of it.
    foreach ($decls as $name => $value) {
        if (!str_starts_with($name, '--folio-')) {
            continue;
        }
        // --g-folio-* is the plugin's own font handoff, written by PHP at
        // runtime (themes/font-loader.php). A slot reading it is correct, not
        // backwards — it is upstream of the theme, not private to it.
        if (preg_match('/^var\(\s*(--(?!folio-|g-folio-)[a-z0-9-]+)/i', $value, $vm)) {
            $warn[] = "backwards alias: {$name} reads {$vm[1]} — the slot must carry the value, "
                . "not read a private token";
        }
    }

    // Fixed type is what stops a theme adapting — but only when nothing else
    // adapts it. Two legitimate mechanisms exist: clamp() on the declaration,
    // or a breakpoint that overrides the same selector. UI chrome (labels,
    // captions, controls) is fine fixed either way; 16px on an input is also
    // what stops iOS zooming a focused field. So flag only display-scale sizes
    // that are neither clamped nor stepped.
    //
    // The selector match is exact string equality, which is what the themes'
    // own formatting gives us. It does not model specificity or a descendant
    // override, so a size stepped via a *different* selector still reports.
    $base = [];      // selector => size, declared outside any @media
    $stepped = [];   // selectors that carry a font-size inside some @media
    $depth = 0;
    $media_at = null;
    $selector = null;

    foreach (explode("\n", $src) as $line) {
        $trimmed = trim($line);

        if (str_starts_with($trimmed, '@media')) {
            $media_at = $depth;
        }
        if (str_ends_with($trimmed, '{') && !str_starts_with($trimmed, '@')) {
            $selector = trim(rtrim($trimmed, '{'));
        }
        if (preg_match('/font-size:\s*([0-9.]+)px/i', $trimmed, $fs) && $selector !== null) {
            if ($media_at !== null) {
                $stepped[$selector] = true;
            } elseif ((float) $fs[1] >= 20.0) {
                $base[$selector] = $fs[1];
            }
        }

        $depth += substr_count($line, '{') - substr_count($line, '}');
        if ($media_at !== null && $depth <= $media_at) {
            $media_at = null;
        }
    }

    $frozen = array_diff_key($base, $stepped);
    if ($frozen) {
        foreach ($frozen as $sel_name => $size) {
            $warn[] = "{$sel_name} is {$size}px at every width — neither clamp()ed nor "
                . 'stepped at a breakpoint. Confirm that is intended: an icon, a glyph or a '
                . 'control legitimately stays fixed; a heading does not.';
        }
    }

    // One breakpoint means one hard switch, with nothing in between.
    preg_match_all('/@media[^{]*\(\s*(?:min|max)-width/i', $src, $mq);
    $breakpoints = count($mq[0]);
    if ($breakpoints < 2) {
        $warn[] = "only {$breakpoints} width breakpoint" . ($breakpoints === 1 ? '' : 's')
            . ' — intermediate widths get a layout built for another size';
    }

    if (!preg_match('/prefers-reduced-motion/i', $src)) {
        $warn[] = 'no prefers-reduced-motion block — the contract deliberately cannot do this for you';
    }

    // Interaction states. A folio renders through the site's own front end, so
    // the active WordPress theme's stylesheet loads alongside the folio theme's:
    // anything a theme leaves unsaid about its links, some other stylesheet says
    // instead — including the `a:hover { text-decoration: none }` that ships with
    // a great many of them. Silence here is not neutrality.
    if (!preg_match('/:hover/i', $src)) {
        $warn[] = 'no :hover states — a folio renders inside the site\'s own front end, so the '
            . 'active WordPress theme styles any link this one does not';
    }

    if (!preg_match('/:focus-visible/i', $src)) {
        $warn[] = 'no :focus-visible states — keyboard users get whatever the site theme supplies, '
            . 'or nothing at all where this theme sets outline: none';
    }

    // outline: none with no ring of its own is the one that leaves a keyboard
    // user with no focus indicator at all.
    if (preg_match('/outline:\s*(none|0)\b/i', $src) && !preg_match('/:focus-visible[^{]*\{[^}]*outline/is', $src)) {
        $warn[] = 'suppresses outline with no :focus-visible ring to replace it';
    }

    // Declaring on :root leaks into the admin, which also carries body.enrove.
    // Legitimate only when a light/dark class on :root drives the tokens.
    $on_root = (bool) preg_match('/^\s*:root[^{]*\{[^}]*--folio-/m', $src);
    $has_scheme = (bool) preg_match('/(folio-scheme|theme-(light|dark))/i', $src);
    if ($on_root && !$has_scheme) {
        $warn[] = 'declares --folio-* on :root without a scheme toggle — scope to the theme root instead';
    }

    return [
        'id'     => $id,
        'filled' => count($filled),
        'total'  => count($core),
        'missing' => array_values(array_diff($core, $filled)),
        'warn'   => $warn,
    ];
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI-only code (a WordPress include returns above): shell-script globals, plain-text terminal output and STDERR messages, with no WordPress loaded to escape or write through.

foreach ($theme_dirs as $dir) {
    $row = enrove_check_theme_dir($dir, $root, $core);
    if ($row === null) {
        continue;
    }
    $rows[] = $row;
    if ($row['warn']) {
        $had_warning = true;
    }
}

echo "Folio theme contract — " . count($core) . " core slots, "
    . count($optional) . " opt-in\n\n";

foreach ($rows as $r) {
    printf("%-20s %2d/%-2d\n", $r['id'], $r['filled'], $r['total']);
    if ($r['missing']) {
        echo "  unfilled: " . implode(', ', array_map(static function ($s) {
            return substr($s, strlen('--folio-'));
        }, $r['missing'])) . "\n";
    }
    foreach ($r['warn'] as $w) {
        echo "  ! {$w}\n";
    }
    echo "\n";
}

echo $had_warning
    ? "Warnings above. Unfilled slots are often correct — a theme with no scrim should\n"
      . "leave overlay empty. The ! lines are the ones worth reading.\n"
    : "No warnings.\n";

exit($had_warning && isset($opts['strict']) ? 1 : 0);
