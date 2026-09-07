<?php
/**
 * Groove Folios — folio theme contract conformance check.
 *
 * Reports how each theme fills the --folio-* slots declared in
 * assets/css/folio-contract.css, and flags the mistakes that fail silently.
 *
 * Usage:
 *   php bin/check-theme-contract.php [options]
 *
 * Options:
 *   --theme=<slug>  Check one theme instead of all of them.
 *   --strict        Exit non-zero if any theme has a warning (for CI).
 *   --help          Show this help.
 *
 * Exit codes: 0 clean (or warnings without --strict), 1 warnings under
 * --strict, 2 the contract file could not be read.
 *
 * Nothing here talks to WordPress — it is pure text analysis of the
 * stylesheets, so it runs on a checkout with no install.
 *
 * @package Groove
 */

$root = dirname(__DIR__);
$opts = getopt('', ['theme::', 'strict', 'help']);

if (isset($opts['help'])) {
    $doc = file_get_contents(__FILE__);
    if (preg_match('#/\*\*(.*?)\*/#s', $doc, $m)) {
        echo preg_replace('/^\s*\*ic?/m', '', str_replace('*/', '', $m[1])), "\n";
    }
    exit(0);
}

$contract_path = $root . '/assets/css/folio-contract.css';
if (!is_readable($contract_path)) {
    fwrite(STDERR, "Cannot read {$contract_path}\n");
    exit(2);
}

/** Strip comments so a slot named only in prose is never counted as declared. */
function groove_strip_comments(string $css): string
{
    return preg_replace('#/\*.*?\*/#s', '', $css);
}

/** Every --folio-* name the contract declares, in declaration order. */
function groove_contract_slots(string $css): array
{
    preg_match_all('/^\s*(--folio-[a-z0-9-]+)\s*:/m', groove_strip_comments($css), $m);
    return array_values(array_unique($m[1]));
}

$contract = file_get_contents($contract_path);
$slots = groove_contract_slots($contract);

// The colour slots every theme is expected to answer. The space and type
// ramps are opt-in, so a theme that ignores them is conforming, not lacking.
$optional = array_values(array_filter($slots, static function ($s) {
    return str_starts_with($s, '--folio-space-')
        || str_starts_with($s, '--folio-text-') && $s !== '--folio-text-muted' && $s !== '--folio-text-subtle'
        || str_starts_with($s, '--folio-leading-');
}));
$core = array_values(array_diff($slots, $optional));

$theme_dirs = glob($root . '/themes/*', GLOB_ONLYDIR) ?: [];
if (!empty($opts['theme'])) {
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

foreach ($theme_dirs as $dir) {
    $id  = basename($dir);
    $css = $dir . '/assets/css/theme.css';
    if (!is_readable($css)) {
        continue; // not a theme package
    }

    $src    = groove_strip_comments(file_get_contents($css));
    $warn   = [];

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

    // Declaring on :root leaks into the admin, which also carries body.groove.
    // Legitimate only when a light/dark class on :root drives the tokens.
    $on_root = (bool) preg_match('/^\s*:root[^{]*\{[^}]*--folio-/m', $src);
    $has_scheme = (bool) preg_match('/(folio-scheme|theme-(light|dark))/i', $src);
    if ($on_root && !$has_scheme) {
        $warn[] = 'declares --folio-* on :root without a scheme toggle — scope to the theme root instead';
    }

    $rows[] = [
        'id'     => $id,
        'filled' => count($filled),
        'total'  => count($core),
        'missing' => array_values(array_diff($core, $filled)),
        'warn'   => $warn,
    ];
    if ($warn) {
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
