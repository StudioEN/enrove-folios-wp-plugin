# Building a Groove Folio Theme — a development playbook

Two documents cover theme work, and they do not overlap:

| Read | For |
|---|---|
| [README.md](README.md) | **The spec.** What the system is, organised by subsystem — `setup.php` keys, `Base_Theme` lifecycle, the `--folio-*` contract, routing, packaging. Cited below as §N. |
| **This file** | **The order of work, and the traps.** What to do first, how to get the thing in front of your eyes, and the specific mistakes that cost real time. Written from building a theme end to end. |

Everything either one refers to ships with the plugin. Where a claim here can be checked against code,
the file is named so you can go and read it.

If you read only one thing before starting, make it §13 of README.md — the one about theme files being
`require`d with nothing above them to catch anything. It changes how you work, not just what you write.

---

## 0. The rule that governs everything else

Theme files are `require`d at plugin-include time, on every request, before `plugins_loaded`, with
nothing in the call stack that catches anything. **A broken theme file white-screens the entire site,
wp-admin included** — which is where you would have gone to remove it. Recovery is FTP or WP-CLI.

Three practical consequences:

1. **Work on a local site. Never edit a theme on production.**
2. **`php -l` every PHP file before it reaches a server.** The contract checker tokenises rather than
   executes — that is what lets it run on a bare checkout with no WordPress — and a tokeniser accepts
   plenty the compiler rejects. The two tools are not substitutes.
3. **`__()` does not work in the loaders.** The text domain is not loaded yet. Anything a loader wants
   to report has to be stored as data and turned into a sentence later, in admin.

Only class redeclaration is genuinely uncatchable. Parse errors, a missing parent class and an
undefined function at file scope are all `Throwable` since PHP 7.0 — they are site-wide fatals today
only because nothing wraps the `require`, not because they could not be caught. Do not repeat the
pre-PHP-7 folklore.

---

## 1. Decide three things before you write anything

All three are expensive to change once folios exist.

### The name, because the name is the ID

A theme's ID is `sanitize_title(setup.php['name'])`. It is never declared. The folder name must equal
it. Folios store it. Renaming `name` later orphans every folio pointing at the old ID and requires a
`$wpdb->postmeta` remap in `Themes_Manager::run_migrations()` behind a new option guard.

Pick a two-letter CSS prefix at the same time (`gd`, `gn`, `gm`, `gp`) and use BEM under it.

### Which theme to copy

| Copy | For |
|---|---|
| `folio-starter` | The baseline shape. Also the reference for responsive, reduced motion, and the opt-in drawer |
| `groove-ebook` | A block kit styled entirely from `theme.css`; cross-document view transitions with no JS; a mobile-first desktop-only sidebar |
| `groove-newsletter` | A shared nav pane via `dependencies`; `body.groove .gn` scoping throughout; runtime palette |
| `groove-magazine` | Theme JS, runtime palette extraction from the feature image, dark-mode bootstrap |
| `groove-proposal` | Folio-level settings, custom blocks, and the reference three-state light/dark logic |

Copy structure, not class names: `folio-starter` and `groove-ebook` still use legacy bare
`.g-folio__theme-1` / `-2` roots.

### Bundled or packaged

| | Bundled in `themes/` | Installable package |
|---|---|---|
| Discovery | `load_builtin_themes()` globs `themes/*` | `groove_installed_themes` option, **not** a directory scan |
| Folder name | You name it; must match the derived ID | The installer names it `sanitize_title(name)` |
| Version shown | `last_updated` | `version` |
| Install | Already there | *Groove → Themes → Install a Theme* |

A package can never shadow a built-in — an ID collision with a bundled theme is refused outright,
because a redeclared class is an engine fatal no `try`/`catch` can contain.

**Declare both `version` and `last_updated`** in anything you intend to distribute.

**Never hand-drop a package folder into `wp-content/groove-themes/`.** Installed themes come from the
option, not a scan, so the folder is ignored entirely — you get silence, not a broken theme.

---

## 2. Build in this order

The order matters because each step makes the next one cheap. Writing CSS before the markup exists is
the classic way to waste an afternoon.

**1. `setup.php`.** The manifest, no side effects — it is `include`d repeatedly. Get `name`,
`namespace`, `cover_class`, `page_class`, `gate` and `fonts` right now; everything downstream reads
them. (§3)

**2. `cover.php`.** Class `Cover extends Base_Theme`. Root element must carry `g-folio__theme-cover`.
`display_theme()` opens with `if (!parent::display_theme()) { return; }` — that call is what loads the
data.

**3. `page.php`.** Class `Page extends Base_Theme`. Constructor sets
`$this->folio_id = $this->resolve_page_folio_id()`. `get_data()` opens with the `is_preview_mode`
guard. The root element's **class attribute must end in `-page`** and contain `g-folio__theme-`.

**4. A minimal `theme.css`** — just the 22 core slots and enough layout to see the shape. Do not style
yet.

**5. Seed a folio and look at it** (§4 below). Everything after this point is faster because you can
see what you are doing.

**6. Style for real.** Tokens first, then components. Interaction states as you go, not at the end.

**7. Theme JS, only if the behaviour is genuinely this theme's own.** Override `ensure_script()` in
**both** `Cover` and `Page` — overriding it in one view only is the single most common bug in this
codebase.

**8. `sample-content.php`.** Without it the "create with sample content" toggle never appears on Add
New. Ask for imagery by **role**, never by filename.

**9. Artwork** — `theme-thumb.png` (800×498), `theme-cover.jpg` (1880×1253), `theme-g-logo.png`
(212×41).

---

## 3. What you inherit, and must not rewrite

`Base_Theme` already provides the page helpers. Four of the five themes had byte-identical copies, and
the fifth had hardened two of them without the fix travelling back — which is why they live on the
base now. **The checker fails a theme that re-implements any of these:**

```
get_folio_data  get_current_index  get_prev_page  get_next_page  to_anchor_name  get_html_id
```

What is still yours: `get_html()` (which headings you collect), `get_content()` (how blocks become
HTML), and every `display_*()` method.

Need a different page order? Reorder `$this->pages` in `get_data()` the way `groove-magazine` does —
the helpers read the list through `get_ordered_pages()`, which re-indexes, so an `array_filter()` will
not throw prev/next off by one.

### Properties: the Cover/Page asymmetry that catches people

`$this->folio` is **Page-only** — it is populated by `get_folio_data()`, which a Cover never calls. On
a cover, `$this->id` is the folio and **`$this->page` *is* the folio post**. Reading `$this->folio` in
`cover.php` silently gives you nothing.

Likewise, a Cover normally does not override `get_data()` at all; it inherits the base version, which
is already preview-guarded and which also populates `$this->copyright` and the byline. A Page that
overrides `get_data()` does **not** get those — that is why page footers read the folio title rather
than a copyright.

### Preview mode

The theme-picker preview injects fabricated `stdClass` posts with **negative IDs** and no database
rows. Every `get_data()` override must start with `if ($this->is_preview_mode) { return; }`, and your
markup must survive `$this->page === null`, `$this->pages === []`, `$this->id <= 0`, and
`get_post_meta()` returning nothing. Read meta inside `get_data()` after the guard and default it —
never inline in markup.

---

## 4. Getting it in front of your eyes

This is the part neither other document covers, and it is what makes theme work fast. There is no
substitute for looking at the rendered page: reading your own CSS will not tell you that a gradient
box is wider than its words.

### Seed a real folio

With a local WordPress (WP-CLI, or the Studio MCP server's `wp_cli`):

```php
// wp eval-file, or wp eval '<this>'
$fid = wp_insert_post([
  'post_type' => 'groove_folio', 'post_title' => 'Smoke Test',
  'post_name' => 'smoke', 'post_status' => 'publish',
]);
update_post_meta($fid, 'theme_id', '<your-theme-id>');

$s = Groove\Themes\Themes_Manager::get_sample_content('<your-theme-id>');
update_post_meta($fid, 'subtitle', $s['subtitle']);
foreach (($s['folio_meta'] ?? []) as $k => $v) { update_post_meta($fid, $k, $v); }

$order = 0;
foreach ($s['pages'] as $p) {
  $order += 10;
  $pid = wp_insert_post([
    'post_type' => 'groove_folio_page', 'post_title' => $p['title'],
    'post_content' => $p['content'], 'post_status' => 'publish', 'menu_order' => $order,
  ]);
  update_post_meta($pid, 'folio_id', $fid);
}
echo Groove\Utils\Utils::get_folio_permalink_by_id($fid), "\n";
```

Seeding from `get_sample_content()` rather than lorem ipsum is deliberate: it exercises the heading
rail, tables, code blocks and the pager in one pass, and it proves the sample content file works.

### Look at all four combinations

Light and dark, desktop and mobile. A theme that was only ever looked at in one scheme will have a
token pinned to a literal somewhere. Then read the render for:

- Does the accent mean one thing?
- Does anything land on a background it was not designed for?
- What happens on the **last** page, where there is no "next"? (A lone flex child in a reversed row
  sits at the wrong end.)
- What happens with **zero pages**? Every empty state is a designed state.

### Check for notices, which a production site will hide

```bash
curl -s "http://localhost:PORT/folio/smoke/page/one" \
  | grep -oiE "(Fatal error|Warning:|Notice:|Deprecated:)[^<]{0,120}"
```

`WP_DEBUG` is usually off, which suppresses notices from the HTML. To be sure, render both views under
`error_reporting(E_ALL)` with a `set_error_handler` capture inside `wp eval`.

### Confirm the wiring, not just the paint

```bash
curl -s "<page url>" | grep -oE 'data-groove-drawer="[^"]*"|class="g-folio__theme-page-nav[^"]*"'
curl -s "<page url>" | grep -oE 'fonts\.googleapis[^"]*|--g-folio-(header|body)-font: [^;]*'
curl -s "<page url>" | grep -oE '<div class="<prefix> [^"]*"'   # root classes
```

A screenshot proves paint, not behaviour. Drive the drawer by hand at least once: open it, press
Escape, click outside, Tab into it while closed.

### Test a package the way a user installs it

Do not hand-sync files and call it verified.

```php
$tmp = wp_tempnam('theme.zip');
copy('/path/to/theme.zip', $tmp);          // the installer consumes the file
$r = Groove\Themes\Themes_Manager::install_theme_from_zip($tmp, false);
echo is_wp_error($r) ? $r->get_error_code() . ' — ' . $r->get_error_message()
                     : wp_json_encode(get_option('groove_installed_themes')['<id>']);
```

A clean install prints `"contract_warnings":[]`.

---

## 5. The contract checker

```bash
php bin/check-theme-contract.php --theme=<id>            # a bundled theme
php bin/check-theme-contract.php --dir=/path/to/theme    # a package, outside themes/
php bin/check-theme-contract.php --theme=<id> --strict   # non-zero exit on warnings, for CI
php bin/check-theme-contract-selftest.php                # the checker's own suite
```

Read the `!` lines, not the slot counts. **Unfilled slots are often correct** — a theme with no scrim
should leave `overlay` empty.

Everything it catches, and what each one means:

### From `setup.php`

| Warning | Means |
|---|---|
| no `name` / `cover_class` / `page_class` | The theme cannot register and will not appear in the picker |
| folder is X but ID derived from name is Y | Folios store the derived ID; the folder must match |
| `dependencies` not an array / traversing path / not on disk | Install validation rejects it, or the loaders skip the theme |
| no usable `gate` | The password screen wears WordPress blue instead of your colours. All three keys must be hex — `sanitize_hex_color()` drops anything else |

### From the PHP — every one of these fails silently at runtime

| Warning | Means |
|---|---|
| no readable `cover.php` / `page.php` | The folder is skipped silently |
| no `ABSPATH` guard | The file is reachable directly over HTTP |
| class not declared / does not reach `Base_Theme` | Registration fails |
| namespace disagrees with `setup.php` | Usually the cause of "class_missing" in the problem panel |
| no `display_theme()` | Nothing renders |
| `display_theme()` never calls `parent::` | Empty shell — that call is what loads the data |
| `get_data()` with no `is_preview_mode` guard | The picker preview renders empty |
| `ensure_script()` does not call `parent::` | The theme drops its own CSS and fonts |
| `ensure_script()` overridden in one view only | Cover animates, page does not — **the most common bug** |
| cover root does not carry `g-folio__theme-cover` | Font pickers do nothing on the front end |
| page root does not end in `-page` | Same |
| `get_content()` skips `apply_embed_processing()` | Bare oEmbed URLs stay plain text |
| constructor rolls its own folio lookup | Empty nav after duplicate-then-delete |
| a font CDN named in theme code | `Font_Loader` is the only code allowed to touch one |
| re-implements an inherited helper | See §3 above |

### From the CSS

| Warning | Means |
|---|---|
| backwards alias: X reads Y | **The slot must carry the value, your private name is the alias.** The mistake it looks for hardest |
| `--folio-*` on `:root` without a scheme toggle | Leaks into wp-admin, which also carries `body.groove`. Legitimate only when a scheme class on `:root` drives the tokens |
| an `@import` | Fonts are declared in `setup.php` |
| X is Npx at every width | Display type that adapts at no width. An icon or control may legitimately stay fixed; a heading may not |
| only N width breakpoints | Intermediate widths get a layout built for another size |
| no `prefers-reduced-motion` block | The contract deliberately cannot do this for you — its fallbacks sit on `:root`, and your slots sit nearer |
| no `:hover` / no `:focus-visible` | A folio renders inside the site's own front end, so the active WordPress theme styles anything you leave unsaid |
| suppresses `outline` with no replacement ring | Keyboard users get nothing |

### From the JS

| Warning | Means |
|---|---|
| sets X at runtime but `theme.css` declares it as `var(--folio-…)` | A repaint writing the alias renders correctly and leaves the slot stale. Only the alias case is flagged — a private holding a literal must be written directly |

---

## 6. Colour: a method, not a palette

Filling 22 slots is not a colour design. What makes a theme's colour read is a rule you can state in
one sentence and then never break.

### State the rule first

A worked example:

> Colour appears in exactly two ways. The gradient paints display type and nothing else. One accent, a
> rose, means one thing: interactive, or where you are. Everything else is ink.

Then every token decision is an application of that rule rather than a fresh judgement. Before
introducing a second hue, ask what it *means* — if you cannot say, you do not need it.

### The accent traps

**`--folio-accent-2` is emphasis that is NOT interactive.** If you also point `--folio-focus` at it,
the same hue now says both "emphasis" and "interactive" and the reader can learn nothing from it.
Default `--folio-focus` to the accent unless your ground is that same hue.

**Fewer tones than slots? Point one slot at another** — `--folio-accent-2: var(--folio-accent)` — do
not leave it blank. The contract's fallback is a WordPress admin grey, off-palette for your theme. A
single-hue theme is a legitimate, documented answer.

**Derive the family, do not pick it.** Put the interactive accent on the same OKLCH hue line as the
brand colour it descends from, and vary lightness to hit contrast. A vivid brand hue and a hand-picked
"darker version of it" usually land 5–10° apart and read as two unrelated colours side by side.

### Contrast arithmetic you cannot skip

Check every foreground against **every** background it can land on — ground, surface, *and*
`surface-alt` — in **both** schemes. A token that passes on the ground routinely fails on the recessed
tone, and that is where table headers and captions live.

Body text and anything under ~19px bold needs **4.5:1**. Large text and UI boundaries need **3:1**.

**A gradient cannot back text.** If a fill runs across several hues, no single foreground survives all
of them. In a theme whose gradient ran rose → orange → violet, white cleared the violet stop and
failed the orange at 2.13:1, while ink cleared the orange and failed the violet. This is not fixable by choosing a better foreground. Use a
gradient *as* type (`background-clip: text`), where the contrast question does not arise, and never as
a surface behind a label.

### The custom-property trap that will cost you an hour

**A `var()` inside a custom property is substituted at the element where it is DECLARED, not where it
is read.**

```css
:root      { --folio-ground: #eae6de; --my-ground: var(--folio-ground); }  /* computes to bone, here */
.my-footer { --folio-ground: #0f0f0f; }                                    /* --my-ground is STILL bone */
```

This bites whenever you build an inverted block — a dark footer inside a light page. Redeclaring the
slot on a descendant does not move an alias declared on `:root`. **Redeclare the aliases alongside the
slots**, in the same block.

A scheme toggle does not hit this, because `:root.folio-scheme-dark` redeclares the slots on the same
element where the aliases are declared — the cascade resolves within that element.

If a block must invert in *both* schemes, hold the opposite palette as its own named set
(`--x-inv-*`) that each scheme block fills with the colours it is not using, and have the block read
that. Pinned to literals, an "inverted" footer becomes the same value as a dark page's ground and
stops being an inversion at all.

### Light and dark

Scheme blocks redeclare the **slots**, never the aliases — the aliases already point at the slots, so
re-pointing them is a no-op that breaks the chain. New themes use `.folio-scheme-light` /
`.folio-scheme-dark` on `:root`. Copy `groove-proposal`'s three-state logic rather than inventing one:
explicit dark, explicit light, and a system fallback whose `:not()` guard is load-bearing — it is what
lets someone who chose light stay light on a machine set to dark.

Print the bootstrap **inline at the top of `display_theme()`** so the class lands before first paint.
A page that flashes bone before turning to ink is worse than no scheme at all.

---

## 7. Traps that cost real time

Every one of these was hit building a real theme, and none of them announces itself.

### CSS

**`width: fit-content` does not hug wrapped text.** It is max-content capped by the available width,
so a two-line title still gets a full-column box — and anything painted across that box (a gradient,
a background) extends past the words. `width: min-content` breaks at the longest word and sizes to it.

**Core block styles tie on specificity and load after yours.** `.wp-block-table td` and `.gd-prose td`
are both (0,1,1), and the block library wins on source order. Outrank it — `.gd-prose table td` —
rather than matching it.

**`body.groove` is on wp-admin too**, via the `admin_body_class` filter. Unscoped rules leak. Scope to
your own prefix. `body.groove` scoping is *not* settled practice in this codebase — newsletter uses it
throughout, magazine and proposal not at all. You are picking a side, not following a convention.

### PHP

**The `ensure_script()` form every bundled theme uses is not portable.** All three themes that ship
JS — `groove-magazine`, `groove-newsletter`, `groove-proposal` — build the `filemtime()` path as
`trailingslashit(GROOVE_PATH) . 'themes/' . static::get_id() . '/assets/js/…'` while taking the URL
from `get_theme_assets_url()`. The URL half is portable; the path half only resolves for a theme
bundled in the plugin.

Nothing 404s, which is why this survives: for an installed package the path simply does not exist,
`file_exists()` returns false, and the version silently falls back to `GROOVE_VERSION`. The script
loads — and then stops cache-busting, so your next edit to it reaches nobody who already has the old
one. Use `get_theme_assets_path()` for the path, and the helpers generally:
`get_theme_assets_path()`, `get_theme_assets_url()`, `get_theme_folder_url()`.

**`sample-content.php` is a static context.** It is `include`d from a static method, so there is no
`$this`. Use `static::resolve_theme_folder_url()`, not the instance helpers.

**Image roles are a fixed vocabulary** — `hero`, `scene`, `detail`, `process`, `texture`, `backdrop`,
`wide`, `people`, `portrait-a`…`portrait-d`. An unrecognised role resolves to an empty slug and fails
silently. Credits return `''` before the curation script has run, so tolerate an empty credit rather
than printing "Photo by  on Pexels".

### Fonts

**`Font_Loader` dedupes `google_family` by exact string.** Two different weight specs for the same
family put `family=Inter` twice in one `css2` URL and the second is dropped — taking whichever weights
only it named. If both roles use one family, give them the **identical** string covering every weight
the theme uses.

Only two roles exist, `header` and `body`. A third voice — a monospace for code and labels — has to be
a system stack in your own private token. That is usually the better answer anyway: no request.

### Behaviour

**Do not write a drawer script.** `groove-main.js` owns opening and closing via the shared
`…-nav-button` / `…-nav-close` classes. Everything else — ARIA state, Escape, click-outside, scroll
lock, focus moved in and returned — is opt-in with `data-groove-drawer="<trigger selector>"` on the
pane. Add `data-groove-drawer-lock="off"` for a dropdown-style panel.

Two things your CSS still owns: **`visibility`, not just `transform`** (a pane translated off-screen is
still in the tab order — and drop `visibility` from the `.visible` rule's transition list so it flips
instantly on open, or focus cannot move in), and **scroll the content box, not the pane**.

Before adding any theme JS, ask whether the behaviour belongs in `groove-main.js` behind an opt-in
attribute instead. That is what makes it available to the next theme. `folio-starter` carried a
private 139-line copy of the drawer controller until it moved.

---

## 8. Definition of done

Mechanical, in order:

- [ ] `php -l` on every PHP file in the theme
- [ ] `php bin/check-theme-contract.php --theme=<id> --strict` — read the `!` lines
- [ ] `php bin/check-theme-contract-selftest.php` if you touched the checker
- [ ] No PHP notices in the rendered HTML of both views

By eye, in all four combinations (light/dark × desktop/mobile):

- [ ] Cover at `/<base-slug>/<folio-slug>`; page at `/<base-slug>/<folio-slug>/page/<page-slug>`
- [ ] Draft folio via `?groove_preview=1&folio_id=<id>`
- [ ] Picker preview, **both** views — open it from the picker, a hand-typed URL 403s on the nonce
- [ ] A folio with **zero pages** renders both views without a fatal
- [ ] The **last** page, where there is no "next"
- [ ] 320px wide and at 200% text: no horizontal scroll, nothing clipped
- [ ] Every foreground clears 4.5:1 on ground, surface **and** surface-alt, in both schemes

Behaviour, driven by hand:

- [ ] Nav opens and closes; Escape closes it; click-outside closes it; focus returns to the trigger;
      Tab cannot reach it while closed
- [ ] Changing the folio's header/body font changes the front end — and the editor canvas if you ship
      blocks
- [ ] `prefers-reduced-motion: reduce` kills the transitions
- [ ] Byline, copyright, logo and "On this page" label toggles all take effect
- [ ] Password-protected folio shows a gate in the theme's own colours
- [ ] No CSS leaks into wp-admin — open the All Folios list with the theme installed

Then run [folio-embed-regression-checklist.md](../folio-embed-regression-checklist.md) if you changed
anything shared.

---

## 9. When it does not appear in the picker

**Open *Groove → Themes* and read the problem panel before reading any code.** Every loader guard
records a reason code; the panel names the folder and the fault.

| Reason | Means |
|---|---|
| `no_setup` | `setup.php` absent or unreadable |
| `unreadable_file` | `cover.php` or `page.php` absent; the report names which |
| `setup_not_array` | It parsed but did not return an array |
| `no_class_names` | `cover_class` or `page_class` empty |
| `missing_dependency` | A path in `dependencies` is not on disk |
| `class_missing` | The file loaded but declared no such class — usually a namespace disagreement |
| `not_base_theme` | The class exists but does not extend `Base_Theme` |
| `shadowed` | Another theme registered the same derived ID and won |
| `package_gone` | An installed theme's option row outlived its files |

A folder carrying **none** of the four marker files (`setup.php`, `cover.php`, `page.php`,
`assets/css/theme.css`) is not reported at all — that is how stray files in `themes/` stay out.

### Other symptoms

| Symptom | Cause |
|---|---|
| Fonts ignored on the front end | Root element classes violate the font-injection selector |
| Fonts right on front end, wrong in editor | `blocks.php` not resolving the edited page's folio, or not checking `theme_id` |
| Empty theme | `display_theme()` missing `parent::display_theme()` |
| Preview empty but live fine | `get_data()` missing the `is_preview_mode` guard |
| Nav does not open | Not using the shared `g-folio__theme-*-nav-button` / `-close` classes |
| Nav opens but Escape does nothing | Missing `data-groove-drawer` on the pane |
| Tab walks into a closed drawer | Pane hidden by `transform` alone — add `visibility` |
| Embeds render as plain URLs | `get_content()` not passed through `apply_embed_processing()` |
| "Create with sample content" missing | `sample-content.php` absent, not returning an array, or every page entry lacking a `title` |
| Page renders but nav/pager/title empty | `$this->folio_id` did not resolve |
| Folio 404s with "Theme not found" | Stored `theme_id` is unregistered. This is **correct** — add a migration if you renamed the theme |
| Cover 302s to the first page | Expected: `Utils::is_folio_cover_enabled()` is false for that folio |
| A colour changes at runtime but a consumer reads the old one | Theme JS writing a private token instead of the `--folio-*` slot |
| A link loses its underline on hover with no `:hover` rule of yours | The site's own stylesheet is filling your silence |

---

## 10. Known pre-existing issues

Not yours to fix in a theme, but worth knowing so you do not chase them:

- **A published folio page returns HTTP 404 while rendering perfectly.** `Plugin::add_rewrite()` is
  deliberately empty, and `folio-preview-template.php` sets a status only on the gate and error
  branches — the success path never does. The cover is 200 only because it matches the CPT's own
  rewrite.
- **`Base_Theme::to_anchor_name()` emits double hyphens.** "Key Points" → `key--points`: it prefixes
  each uppercase run with `-` *and* converts the space. Anchors still resolve because both the heading
  id and the rail href use the same function. Do not "fix" it in a theme — you would break the match.
