<?php
/**
 * Enrove Folios — self-test for bin/check-theme-contract.php.
 *
 * Every check in that tool exists to catch a mistake that produces no error of
 * any kind. The tool passing on all five shipped themes therefore proves very
 * little: a check that is silently broken and a theme that is genuinely correct
 * look exactly the same from outside. So each case here copies a real theme into
 * a throwaway tree, changes exactly one thing, and asserts what the checker says.
 *
 * Cases come in two kinds, and both matter:
 *
 *   warn   — break a contract, expect the checker to name it. A check that
 *            cannot fail is not a check.
 *   absent — write something unusual but CORRECT, expect the checker to stay
 *            quiet about it. A checker that cries wolf gets switched off.
 *
 * Both run against two subject themes. One is not enough: the page-root check
 * once scanned the whole file, which made it dead on enrove-newsletter — a nav
 * button 178 lines from the root satisfied it — while every case here still
 * reported a tick, because folio-starter has only one candidate element.
 *
 * Usage:
 *   php bin/check-theme-contract-selftest.php [--keep]
 *
 * Options:
 *   --keep  Leave the fixture tree on disk for inspection.
 *   --help  Show this help.
 *
 * Exit codes: 0 every case behaved, 1 one did not.
 */

$root = dirname(__DIR__);
$opts = getopt('', ['keep', 'help']);

if (isset($opts['help'])) {
    if (preg_match('#/\*\*(.*?)\*/#s', file_get_contents(__FILE__), $m)) {
        echo trim(preg_replace('/^\s*\* ?/m', '', str_replace('*/', '', $m[1]))), "\n";
    }
    exit(0);
}

$subjects = ['folio-starter', 'enrove-newsletter'];
$fixture  = sys_get_temp_dir() . '/enrove-contract-selftest-' . getmypid();

// ---------------------------------------------------------------------------
// Fixture plumbing
// ---------------------------------------------------------------------------

function enrove_copy_tree(string $from, string $to): void
{
    mkdir($to, 0777, true);
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($items as $item) {
        $target = $to . DIRECTORY_SEPARATOR . $items->getSubPathName();
        $item->isDir() ? mkdir($target, 0777, true) : copy($item->getPathname(), $target);
    }
}

function enrove_remove_tree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    if (!is_dir($path)) {
        unlink($path);
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($path);
}

function enrove_edit(string $path, callable $fn): void
{
    file_put_contents($path, $fn(file_get_contents($path)));
}

/** Replace once, and say so loudly if the fixture pattern stopped matching. */
$GLOBALS['enrove_fixture_misses'] = 0;

function enrove_sub(string $path, string $pattern, string $replacement): void
{
    enrove_edit($path, static function ($src) use ($pattern, $replacement, $path) {
        $out = preg_replace($pattern, $replacement, $src, 1, $count);
        if ($count === 0) {
            // A mutation that silently no-ops turns its case into a test of
            // nothing — loudly enough to fail, not just to warn.
            $GLOBALS['enrove_fixture_misses']++;
            fwrite(STDERR, '  (fixture) pattern did not match in ' . basename($path) . ": {$pattern}\n");
        }
        return $out;
    });
}

/**
 * Rewrite the class attribute of the element display_theme() roots itself in,
 * whichever theme this is — the cases below should not have to know.
 */
function enrove_rewrite_root(string $path, callable $fn): void
{
    enrove_edit($path, static function ($src) use ($fn, $path) {
        $at = strpos($src, 'function display_theme');
        if ($at === false) {
            fwrite(STDERR, '  (fixture) no display_theme in ' . basename($path) . "\n");
            return $src;
        }

        $head = substr($src, 0, $at);
        $tail = substr($src, $at);
        $done = false;

        $tail = preg_replace_callback(
            '/class\s*=\s*"([^"]*)"/',
            static function ($m) use ($fn, &$done) {
                if ($done) {
                    return $m[0];
                }
                $done = true;
                return 'class="' . $fn($m[1]) . '"';
            },
            $tail,
            1
        );

        if (!$done) {
            fwrite(STDERR, '  (fixture) no root class attribute in ' . basename($path) . "\n");
        }

        return $head . $tail;
    });
}

// ---------------------------------------------------------------------------
// The cases
// ---------------------------------------------------------------------------

/**
 * name => [expectation, needle, mutation]
 *   'warn'   the output must mention the needle
 *   'absent' the output must NOT mention it — this input is correct
 */
function enrove_cases(string $subject, string $theme, string $gate): array
{
    $page  = $theme . '/page.php';
    $cover = $theme . '/cover.php';
    $setup = $theme . '/setup.php';
    $css   = $theme . '/assets/css/theme.css';
    $js    = $theme . '/assets/js/repaint.js';

    return [
        // --- registration ---------------------------------------------------
        'folder name does not match the derived ID' => ['warn', 'the ID derived from name', static function () use ($setup) {
            enrove_sub($setup, "/'name'\s*=>\s*'[^']*'/", "'name' => 'Totally Different'");
        }],
        'a name whose WP slug keeps an apostrophe out' => ['absent', 'the ID derived from name', static function () use ($setup, $theme) {
            // WordPress removes the apostrophe rather than making it a separator,
            // so this folder name is correct and must not be reported.
            enrove_sub($setup, "/'name'\s*=>\s*'[^']*'/", "'name' => \"Designer's Folio\"");
            rename($theme, dirname($theme) . '/designers-folio');
        }],
        'an accented name folds to ASCII' => ['absent', 'the ID derived from name', static function () use ($setup, $theme) {
            enrove_sub($setup, "/'name'\s*=>\s*'[^']*'/", "'name' => 'Café Noir'");
            rename($theme, dirname($theme) . '/cafe-noir');
        }],
        'declared class is not the one setup.php names' => ['warn', 'declares no class', static function () use ($page) {
            enrove_sub($page, '/class Page extends /', 'class Pagey extends ');
        }],
        'view class does not reach Base_Theme' => ['warn', 'does not reach Base_Theme', static function () use ($page) {
            enrove_sub($page, '/class Page extends \w+/', 'class Page extends Definitely_Not_Base_Theme');
        }],
        'view class reaches Base_Theme through a shared parent' => ['absent', 'does not reach Base_Theme', static function () use ($page, $theme, $setup) {
            // What `dependencies` is for. is_subclass_of is transitive, so this
            // registers fine and must not be reported.
            file_put_contents($theme . '/shared.php', "<?php\nnamespace X;\nclass Shared_Page extends \\Enrove\\Themes\\Base_Theme {}\n");
            if (preg_match("/'dependencies'/", file_get_contents($setup))) {
                enrove_sub($setup, "/'dependencies'\s*=>\s*\[/", "'dependencies' => ['shared.php', ");
            } else {
                enrove_sub($setup, "/('page_class'\s*=>[^,]*,)/", "$1\n    'dependencies' => ['shared.php'],");
            }
            enrove_sub($page, '/class Page extends \w+/', 'class Page extends Shared_Page');
        }],
        'namespace disagrees with setup.php' => ['warn', 'declares namespace', static function () use ($page) {
            enrove_sub($page, '/^namespace [^;]+;/m', 'namespace Enrove\\Themes\\Wrong;');
        }],
        'namespace line deleted outright' => ['warn', 'declares no namespace', static function () use ($page) {
            enrove_sub($page, '/^namespace [^;]+;\s*/m', '');
        }],
        'a declared dependency is not there' => ['warn', 'not there', static function () use ($setup) {
            // A second 'dependencies' key would be a no-op — the later literal
            // wins — so add to the existing list where there is one.
            if (preg_match("/'dependencies'/", file_get_contents($setup))) {
                enrove_sub($setup, "/'dependencies'\s*=>\s*\[/", "'dependencies' => ['nope.php', ");
            } else {
                enrove_sub($setup, "/('page_class'\s*=>[^,]*,)/", "$1\n    'dependencies' => ['nope.php'],");
            }
        }],
        'setup.php has a side effect' => ['warn', 'literal array', static function () use ($setup) {
            enrove_sub($setup, '/^<\?php/m', "<?php\necho 'hello';");
        }],
        'setup.php holds a legal boolean' => ['absent', 'literal array', static function () use ($setup) {
            // true/false/null are T_STRING like any function name. Flagging one
            // used to report a legal setup.php and return early, taking every
            // other check for this theme with it.
            enrove_sub($setup, "/('page_class'\s*=>[^,]*,)/", "$1\n    'supports_dark' => true,");
        }],
        'a legal boolean does not mask a broken root' => ['warn', 'END in -page', static function () use ($setup, $page) {
            enrove_sub($setup, "/('page_class'\s*=>[^,]*,)/", "$1\n    'supports_dark' => true,");
            enrove_rewrite_root($page, static fn($v) => str_replace('-page', '', $v));
        }],
        'no setup.php at all' => ['warn', 'no readable setup.php', static function () use ($setup) {
            unlink($setup);
        }],

        // --- the font-injection contract, which is a check on the ROOT --------
        "page root misses Font_Loader's selector" => ['warn', 'END in -page', static function () use ($page) {
            enrove_rewrite_root($page, static fn($v) => str_replace('-page', '', $v));
        }],
        'page root has a trailing space' => ['warn', 'END in -page', static function () use ($page) {
            // [class$="-page"] sees the raw attribute, so this really does break.
            enrove_rewrite_root($page, static fn($v) => $v . ' ');
        }],
        'cover root misses g-folio__theme-cover' => ['warn', 'g-folio__theme-cover', static function () use ($cover) {
            enrove_rewrite_root($cover, static fn($v) => str_replace('g-folio__theme-cover', '', $v));
        }],
        'an old root left behind in an HTML comment' => ['warn', 'END in -page', static function () use ($page) {
            enrove_rewrite_root($page, static fn($v) => str_replace('-page', '', $v));
            enrove_sub($page, '/(function display_theme[^\n]*\n)/', "$1    // <div class=\"g-folio__theme-1-page\">\n");
        }],
        'root built with PHP' => ['absent', 'Font_Loader', static function () use ($page) {
            // Cannot be resolved without running PHP, and is correct at runtime.
            enrove_rewrite_root($page, static fn($v) => '<?= esc_attr($scheme) ?> ' . $v);
        }],

        // --- method bodies ---------------------------------------------------
        'display_theme() never calls parent' => ['warn', 'never calls parent::display_theme', static function () use ($page) {
            enrove_sub($page, '/if \(!parent::display_theme\(\)\)\s*\{\s*return;\s*\}/s', '');
        }],
        'parent call commented out' => ['warn', 'never calls parent::display_theme', static function () use ($page) {
            enrove_sub($page, '/(if \(!parent::display_theme\(\)\)\s*\{\s*return;\s*\})/s', "// $1");
        }],
        'an interface declares display_theme() with no body' => ['absent', 'has no display_theme', static function () use ($page) {
            enrove_sub($page, '/^(class Page extends)/m', "interface Renderable { public function display_theme(); }\n$1");
        }],
        'a decoy class cannot answer for the real one' => ['warn', 'is_preview_mode guard', static function () use ($page) {
            // A helper class declared above the view class used to shadow it:
            // the first matching method in the FILE won, so a decoy with a
            // correct-looking body satisfied a check the real class fails.
            enrove_sub($page, '/if \(\$this->is_preview_mode\) \{ return; \}/', '');
            enrove_sub($page, '/^(class Page extends)/m',
                "class Decoy { function get_data() { if (\$this->is_preview_mode) { return; } } }\n$1");
        }],
        'a decoy class cannot answer for the root either' => ['warn', 'END in -page', static function () use ($page) {
            enrove_rewrite_root($page, static fn($v) => str_replace('-page', '', $v));
            enrove_sub($page, '/^(class Page extends)/m',
                "class Decoy { function display_theme() { ?><div class=\"g-folio__theme-decoy-page\"><?php } }\n$1");
        }],
        'get_data() has no preview guard' => ['warn', 'is_preview_mode guard', static function () use ($page) {
            enrove_sub($page, '/if \(\$this->is_preview_mode\) \{ return; \}/', '');
        }],
        'preview guard inverted' => ['warn', 'inverted', static function () use ($page) {
            enrove_sub($page, '/if \(\$this->is_preview_mode\)/', 'if (!$this->is_preview_mode)');
        }],
        'no ABSPATH guard' => ['warn', 'no ABSPATH guard', static function () use ($page) {
            enrove_sub($page, "/if \(!defined\('ABSPATH'\)\)\s*\{\s*exit;\s*\}/s", '');
        }],
        'ABSPATH guard commented out' => ['warn', 'no ABSPATH guard', static function () use ($page) {
            enrove_sub($page, "/(if \(!defined\('ABSPATH'\)\)\s*\{\s*exit;\s*\})/s", "/* $1 */");
        }],
        'get_content() skips embed processing' => ['warn', 'apply_embed_processing', static function () use ($page) {
            enrove_sub($page, '/return \$this->apply_embed_processing\(([^)]*)\);/', 'return $1;');
        }],
        '__construct rolls its own folio lookup' => ['warn', 'resolve_page_folio_id', static function () use ($page) {
            enrove_sub($page, '/\$this->resolve_page_folio_id\(\)/', "(int) get_post_meta(\$this->id, 'folio_id', true)");
        }],
        're-implements a Base_Theme helper' => ['warn', 're-implements', static function () use ($page) {
            enrove_sub($page, '/(class Page extends \w+\s*\{)/', "$1\n  function to_anchor_name(\$s) { return \$s; }");
        }],
        'ensure_script overridden in one view only' => ['warn', 'overridden in', static function () use ($cover, $page) {
            $has = preg_match('/function ensure_script/', file_get_contents($cover));
            $target = $has ? $page : $cover;   // remove from one, or add to one
            if ($has) {
                enrove_sub($target, '/(public )?function ensure_script\s*\([^)]*\)\s*\{/', 'function ensure_script_disabled() {');
            } else {
                enrove_sub($target, '/(class \w+ extends \w+\s*\{)/', "$1\n  public function ensure_script() { parent::ensure_script(); }");
            }
        }],
        'ensure_script never calls parent' => ['warn', 'never calls parent::ensure_script', static function () use ($cover, $page) {
            foreach ([$cover, $page] as $file) {
                if (preg_match('/function ensure_script/', file_get_contents($file))) {
                    enrove_sub($file, '/parent::ensure_script\(\);/', '');
                } else {
                    enrove_sub($file, '/(class \w+ extends \w+\s*\{)/', "$1\n  public function ensure_script() { wp_enqueue_script('x'); }");
                }
            }
        }],

        // --- stylesheet ------------------------------------------------------
        'theme.css loads a font itself' => ['warn', '@import', static function () use ($css) {
            file_put_contents($css, "\n@import url('https://fonts.googleapis.com/css2?family=Inter');\n", FILE_APPEND);
        }],
        'theme PHP names a font CDN' => ['warn', 'names a font CDN', static function () use ($cover) {
            enrove_sub($cover, '/(class Cover extends \w+\s*\{)/', "$1\n  const F = 'https://fonts.googleapis.com/css2?family=Inter';");
        }],
        'a font CDN outside the two view files' => ['warn', 'names a font CDN', static function () use ($theme) {
            // blocks.php is where a theme most plausibly enqueues one.
            file_put_contents($theme . '/blocks.php', "<?php\nwp_enqueue_style('x', 'https://fonts.googleapis.com/css2?family=Inter');\n");
        }],
        'no theme.css at all' => ['warn', 'renders unstyled', static function () use ($css) {
            unlink($css);
        }],

        // --- runtime repaint --------------------------------------------------
        // The contract nothing verified until it turned out enrove-newsletter
        // was breaking it: theme JS setting an alias leaves the slot stale.
        'theme JS sets an alias instead of the slot' => ['warn', 'sets --x-alias at runtime', static function () use ($css, $js) {
            enrove_edit($css, static fn($src) => $src . "\n:root { --x-alias: var(--folio-accent); }\n");
            @mkdir(dirname($js), 0777, true);
            file_put_contents($js, "node.style.setProperty('--x-alias', c);\n");
        }],
        'theme JS setting the slot itself is fine' => ['absent', 'at runtime, but theme.css declares it', static function () use ($css, $js) {
            enrove_edit($css, static fn($src) => $src . "\n:root { --x-alias: var(--folio-accent); }\n");
            @mkdir(dirname($js), 0777, true);
            file_put_contents($js, "node.style.setProperty('--folio-accent', c);\n");
        }],
        'theme JS writing a private that holds a literal is fine' => ['absent', 'at runtime, but theme.css declares it', static function () use ($css, $js) {
            // The case that would make this rule noise if it flagged every
            // private: nothing points at --x-literal, so JS is its only handle.
            enrove_edit($css, static fn($src) => $src . "\n:root { --x-literal: #abc; }\n");
            @mkdir(dirname($js), 0777, true);
            file_put_contents($js, "node.style.setProperty('--x-literal', c);\n");
        }],

        // --- password gate ----------------------------------------------------
        // These used to mutate includes/folio-preview-template.php, back when
        // the gate keyed off two hardcoded ID maps there. A theme declares its
        // own gate colours now, so the failures live in setup.php — which is
        // the point of the change: a packaged theme can reach them.
        'no gate block at all' => ['warn', 'declares no usable gate', static function () use ($setup) {
            enrove_edit($setup, static fn($src) => preg_replace("/'gate'\s*=>\s*\[.*?\],\n/s", '', $src, 1));
        }],
        'gate block missing one slot' => ['warn', 'declares no usable gate', static function () use ($setup) {
            enrove_edit($setup, static fn($src) => preg_replace("/^\s*'accent_hover'\s*=>.*\n/m", '', $src, 1));
        }],
        'gate colour that is not hex' => ['warn', 'declares no usable gate', static function () use ($setup) {
            // Interpolated straight into the gate's inline CSS, so a value that
            // is not a colour is the one that matters most to catch.
            enrove_edit($setup, static fn($src) => preg_replace(
                "/^(\s*'background'\s*=>\s*)'[^']*'/m",
                "$1'red; } body { display:none'",
                $src,
                1
            ));
        }],
    ];
}

// ---------------------------------------------------------------------------
// Run
// ---------------------------------------------------------------------------

enrove_remove_tree($fixture);
mkdir($fixture . '/bin', 0777, true);
mkdir($fixture . '/assets/css', 0777, true);
mkdir($fixture . '/includes', 0777, true);
mkdir($fixture . '/themes', 0777, true);
copy($root . '/bin/check-theme-contract.php', $fixture . '/bin/check-theme-contract.php');
copy($root . '/assets/css/folio-contract.css', $fixture . '/assets/css/folio-contract.css');
copy($root . '/includes/folio-preview-template.php', $fixture . '/includes/folio-preview-template.php');

$gate          = $fixture . '/includes/folio-preview-template.php';
$gate_pristine = file_get_contents($gate);

$pass = 0;
$fail = 0;

echo "Checker self-test — one change per case, against " . count($subjects) . " themes\n";

foreach ($subjects as $subject) {
    echo "\n" . $subject . "\n";

    enrove_remove_tree($fixture . '/pristine');
    enrove_copy_tree($root . '/themes/' . $subject, $fixture . '/pristine');
    $theme = $fixture . '/themes/' . $subject;

    foreach (enrove_cases($subject, $theme, $gate) as $name => [$expect, $needle, $mutate]) {
        // Rebuild the whole themes/ dir: a case may have renamed the folder.
        enrove_remove_tree($fixture . '/themes');
        mkdir($fixture . '/themes', 0777, true);
        enrove_copy_tree($fixture . '/pristine', $theme);
        file_put_contents($gate, $gate_pristine);

        $GLOBALS['enrove_fixture_misses'] = 0;
        $mutate();

        if ($GLOBALS['enrove_fixture_misses'] > 0) {
            echo "  ✗ {$name}\n";
            echo "      the mutation did not apply — this case tests nothing\n";
            $fail++;
            continue;
        }

        $output = (string) shell_exec(
            'cd ' . escapeshellarg($fixture) . ' && php bin/check-theme-contract.php 2>&1'
        );

        // "The needle is absent" is also true of a checker that died before it
        // could say anything, so absence only counts when the run completed.
        // Without this an input-specific fatal passes every absent case.
        $ran = str_contains($output, 'Folio theme contract')
            && !str_contains($output, 'Fatal error')
            && !str_contains($output, 'Parse error');

        $mentions = str_contains($output, $needle);
        $ok = $expect === 'warn' ? $mentions : ($ran && !$mentions);

        if ($ok) {
            echo "  ✓ {$name}\n";
            $pass++;
            continue;
        }

        echo "  ✗ {$name}\n";
        if ($expect === 'warn') {
            echo "      expected a warning mentioning: {$needle}\n";
        } elseif (!$ran) {
            echo "      the checker did not complete on this input\n";
            foreach (array_slice(explode("\n", trim($output)), 0, 3) as $line) {
                echo '      got: ' . trim($line) . "\n";
            }
        } else {
            echo "      expected NO warning mentioning: {$needle}\n";
        }

        $lines = preg_grep('/^\s+!/', explode("\n", $output));
        if ($lines) {
            foreach (array_slice($lines, 0, 3) as $line) {
                echo '      got: ' . trim($line) . "\n";
            }
        } else {
            echo "      got: no warnings at all\n";
        }
        $fail++;
    }

    // A pristine copy must come back clean, or every case above is just
    // reporting some unrelated standing warning.
    enrove_remove_tree($fixture . '/themes');
    mkdir($fixture . '/themes', 0777, true);
    enrove_copy_tree($fixture . '/pristine', $theme);
    file_put_contents($gate, $gate_pristine);

    $clean = (string) shell_exec(
        'cd ' . escapeshellarg($fixture) . ' && php bin/check-theme-contract.php 2>&1'
    );

    if (str_contains($clean, 'No warnings.')) {
        echo "  ✓ an unmodified {$subject} reports no warnings\n";
        $pass++;
    } else {
        echo "  ✗ an unmodified {$subject} already warns — every case above is suspect\n";
        $fail++;
    }
}

if (!isset($opts['keep'])) {
    enrove_remove_tree($fixture);
} else {
    echo "\nFixture kept at {$fixture}\n";
}

echo "\n  {$pass} passed, {$fail} failed\n";

exit($fail > 0 ? 1 : 0);
