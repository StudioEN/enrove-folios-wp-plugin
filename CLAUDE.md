# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Groove Folios — a WordPress plugin (PHP 7.1+, WP 5.9+) for authoring multi-page documents ("folios") that render through self-contained themes, bypassing the site's WordPress theme entirely. Bootstrap is [groove-folios.php](groove-folios.php); everything else hangs off [includes/plugin.php](includes/plugin.php).

## Commands

```bash
npm install
npm run dev            # Vite dev server on :5173 (the plugin auto-detects it — see "Admin assets")
npm run build          # → assets/build/ + .vite/manifest.json (commit the build; PHP reads the manifest)

php bin/check-theme-contract.php            # conformance check for every theme's CSS + PHP contract
php bin/check-theme-contract.php --theme=groove-ebook --strict    # --strict exits non-zero on warnings (CI)
php bin/check-theme-contract-selftest.php   # the checker's own test suite (37 cases × 2 subject themes)
php bin/check-docs.php                      # the two theme docs render cleanly and their anchors resolve
php bin/check-docs.php --verbose            # ...and show the offending line for each problem

php bin/curate-pexels.php --help            # build-time image curation; needs a Pexels key (see README)
```

There is no PHP unit-test suite. `check-theme-contract-selftest.php` is the only automated test; run it after touching `check-theme-contract.php`.

**[themes/README.md](themes/README.md) and [themes/BUILDING-A-THEME.md](themes/BUILDING-A-THEME.md) are rendered in wp-admin** (Groove → Themes → *Spec* / *Playbook*), by `Groove\Utils\Markdown` ([utils/markdown.php](utils/markdown.php)) — a renderer for the Markdown those two files use, not Markdown in general. Syntax it does not implement renders as literal asterisks and pipes rather than raising anything, so **run `php bin/check-docs.php` after editing either file**. It rejects images, footnotes, reference links, strikethrough, raw HTML, autolinks, HTML entities, `_underscore emphasis_` and setext headings, and it verifies that every anchor linked to — inside a document, and from [pages/themes.php](pages/themes.php) via `Groove\Utils\Theme_Docs` — still names a heading that exists. [folio-embed-regression-checklist.md](folio-embed-regression-checklist.md) is a **manual** checklist to run whenever folio page or theme rendering changes.

## Architecture

### Bootstrap and the autoloader

`Plugin::instance()` (singleton, [includes/plugin.php](includes/plugin.php)) registers the autoloader, boots `Themes_Manager::register_defaults()`, wires filters in its constructor, then runs `init()` on `init` priority 0.

[includes/autoloader.php](includes/autoloader.php) resolves `Groove\Foo\Bar_Baz` → `foo/bar-baz.php` relative to the plugin root: strip the `Groove\` prefix, split camelCase on hyphens, `_` → `-`, `\` → `/`, lowercase. **A new class file's path must match its namespace** or it silently never loads. A short explicit map at the top of the file covers the exceptions.

### The four managers

| Manager | Registers |
|---|---|
| `Contents_Manager` ([contents/](contents/)) | CPTs `groove_folio` + `groove_folio_page`, the `groove_collection_tag` taxonomy, and all `register_post_meta()` calls |
| `Menu_Manager` ([menu/](menu/)) | Admin menu items on `admin_menu` prio 20; each `Menu_Item` wraps a `Page` and delegates `render()` to `$page->display_page()` |
| `Modules_Manager` ([modules/](modules/)) | Admin-side feature modules; today just `groove-main`, which enqueues everything |
| `Themes_Manager` ([themes/](themes/)) | Folio themes, built-in and installed |

Admin screens are `Groove\Pages\*` classes extending [pages/page.php](pages/page.php) (`Page extends Assets`), each with a `PAGE_ID` const (`groove-overview`, `groove-all-folios`, `groove-folio`, `groove-add-new`, `groove-themes`, `groove-settings`). A `Page` registers its menu item from its own constructor via the `groove/menu/register` action, and POST handlers via `add_post_action()` (→ `admin_post_*`). List views use [list/](list/) `WP_List_Table` subclasses.

### Front-end routing — no rewrite rules, by design

`Plugin::add_rewrite()` is deliberately empty. Adding rewrite rules makes WP see a CPT archive query, and since `has_archive => false`, `redirect_canonical` bounces the visitor home before templates run. Instead a `template_redirect` hook at **priority 5** (ahead of `redirect_canonical`) intercepts and `exit`s:

- `?groove_theme_preview=` → [includes/theme-picker-preview-template.php](includes/theme-picker-preview-template.php)
- a path under `/{base_slug}/`, or `?groove_preview=1`, or `?folio_id=` / `?p=` naming a groove post → [includes/folio-preview-template.php](includes/folio-preview-template.php)

That template resolves the theme, renders the password gate or a 404, and otherwise emits a bare document around `$theme->display_theme()`. Don't hardcode `/folio/` — the base slug is the `groove_folio_base_slug` option; call `Utils::get_folio_base_slug()`, and build every folio URL with `Utils::get_folio_permalink_by_id()`.

### Themes

**Read [themes/README.md](themes/README.md) before touching anything under `themes/`** — it is the authoritative spec (folder anatomy, `setup.php` manifest, `Base_Theme` lifecycle, font contract, markup conventions, migrations). There is also a `groove-folios-theme` skill covering this work. Highlights that bite:

- A theme's ID is `sanitize_title()` of its `name` in `setup.php`, never declared. The folder name must match it; renaming `name` orphans every folio storing the old ID.
- `Themes_Manager::load_builtin_themes()` skips a malformed theme folder rather than failing. It is no longer silent: each skip is recorded by `record_skipped_theme()` as a reason code, and the problem panel on Groove → Themes names the folder and the fault. A theme missing from the picker failed one of its guards — read the panel before reading the code.
- **Theme loading happens at plugin-include time**, before `plugins_loaded` and on every request, so a broken theme file can white-screen the whole site, wp-admin included. Only *one* of the four ways that happens is actually uncatchable (class redeclaration); the other three are site-wide fatals merely because the loaders do not `try` around the `require`. themes/README.md §13 has the table and the fix. Two follow-ons: `__()` does not work in the loaders (text domain not loaded yet), and anything a loader wants to report must be stored as data and turned into a sentence later.
- A `display_theme()` override must start with `if (!parent::display_theme()) { return; }` — that call is what loads the data.
- Themes never load fonts themselves. Declare a `fonts` block in `setup.php`; [themes/font-loader.php](themes/font-loader.php) is the only code in the plugin that touches a font CDN. Font variables are injected on a selector that requires the cover root to carry `g-folio__theme-cover` and the page root's class attribute to **end** in `-page`.
- [assets/css/folio-contract.css](assets/css/folio-contract.css) defines the `--folio-*` token slots. A theme points its private tokens *at* the slots (`--my-surface: var(--folio-surface)`), never the reverse, and redeclares the slots — not the aliases — in its scheme blocks.
- A theme declares its password-gate colours as a `gate` block in `setup.php` (`accent`, `accent_hover`, `background`, all hex). There are no colour maps in `folio-preview-template.php` any more — they were plugin source no packaged theme could join, so the rule was unsatisfiable for third parties.

`bin/check-theme-contract.php` mechanically checks most of the above. It reads CSS as text and tokenises PHP rather than executing it, so it runs on a bare checkout with no WordPress.

### Folio ↔ page linkage

A `groove_folio_page` belongs to a folio via `folio_id` post meta, which `includes/plugin.php` backfills from the URL on classic saves (`save_post_groove_folio_page`) and from the Referer header on block-editor saves (`rest_after_insert_groove_folio_page`); the same hooks keep the slug in sync with the title. That meta goes stale after duplicate-then-delete, so **resolution is URL-first, meta second** — use `Base_Theme::resolve_page_folio_id()` in `Page` subclasses rather than reading the meta directly. Page order within a folio is `menu_order`.

### Admin assets

[modules/groove-main/module.php](modules/groove-main/module.php) (`is_active()` → `is_admin()`) is the single enqueue point. Tailwind 4 comes from the Vite dev server when a socket probe *and* a HEAD on `/@vite/client` both succeed on localhost:5173, otherwise from `assets/build/.vite/manifest.json`. Hand-authored CSS/JS under `assets/css` and `assets/js` is enqueued directly and versioned by `filemtime()`. Load order matters: `groove-toggletip` → `groove-toast` → `groove-main`. PHP config reaches JS through `window.GROOVE_SETTINGS`.

All admin pages get `body.groove` via an `admin_body_class` filter — the CSS scope for the whole plugin.

**A Tailwind utility loses to core admin CSS.** Tailwind 4 compiles utilities into `@layer utilities`, and an unlayered rule beats a layered one whatever its specificity — `:where()`-wrapped utilities like `space-y-*` have no specificity to begin with. So on any admin screen, `wp-admin/css/common.css` wins: `p { font-size: 13px; line-height: 1.5; margin: 1em 0 }`, `li, dd { margin-bottom: 6px }`, the heading rules. `text-sm`, `m-0` and `space-y-4` on a `<p>` do nothing. Utilities are safe on `div`s and `section`s, which core does not style; anything a layout depends on that lands on a text element belongs in `assets/css/groove-main.css` as component CSS. Two notes from fixing this in the replace-theme dialog: a `margin: 0` reset there outranks the block's own `> * + *` rule, so separate children with a flex `gap` instead; and a component class used in a new place inherits none of its old container's padding — `.g-theme-details__actions` is a row *inside* the 20px-padded body, so lifting it out into a footer left the buttons on the dialog's corner and drew an edge-to-edge rule no other line in that frame has. The `.g-theme-details` dialogs keep their actions in the body for that reason; only `.g-theme-picker-modal` has a true footer.

Use `\Groove\Toast` ([includes/toast.php](includes/toast.php)) for action outcomes (`Toast::success/error/failure`); `Toast::failure()` also pins a toggletip hint to the button that was pressed. Reserve inline notices for standing conditions, not click outcomes.

## Conventions

- Tabs for indentation in PHP in most files (the repo is mixed; match the file you're in). `.editorconfig` covers JSON/YAML/Markdown only.
- Every PHP file starts with an `if (!defined('ABSPATH')) exit;` guard. The one exception is theme `setup.php`: the contract requires a literal array with no calls, and a direct request to one prints nothing.
- Code must pass WordPress.org's Plugin Check. That means no `<?=` short echo tags (write `<?php echo …; ?>`), no heredoc/nowdoc, output escaped late in the right context, `/* translators: */` comments on placeholder strings, and `wp_unslash()` plus sanitising on every `$_GET`/`$_POST` read. Folio page body content is the exception to escaping: it is block-rendered by core, and `wp_kses_post` would strip the embed iframes. A `phpcs:ignore` must name the exact sniff and give a true reason. The local `.claude/scripts/pcp-lint.sh FILE…` runs the same sniffs in seconds (when present; it is not tracked).
- Text domain is `groove-folios` (it must equal the WordPress.org slug) for all i18n, including strings copied from core. Translations load just in time; there is no `load_plugin_textdomain()` call.
- Managers and singletons follow the same `instance()` / `__clone()` / `__wakeup()` shape — copy an existing one when adding a module or content type.
- `CHANGELOG.md` is kept in prose-heavy Keep-a-Changelog form under `## [Unreleased]`; add entries there for user-visible changes.

## Notes

- `.mcp.json` (WordPress Studio MCP server) and `.claude/` are gitignored.
- `tools/agent-skills` is a git submodule (StudioEN/Base-Agent-Skills) and is not checked out locally; the weekly GitHub Action only checks it for updates against `.codex/agent-skills.lock.json`. It runs no build, lint or test for this plugin.
