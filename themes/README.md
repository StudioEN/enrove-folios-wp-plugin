# Groove Folio Themes — Architecture

How a Groove Folio theme is built, registered, resolved, and rendered.

---

## 1. What a theme is

A Groove Folio theme is a **self-contained folder** that renders two views of a folio:

| View | Class | Renders |
|------|-------|---------|
| **Cover** | `cover.php` → `Cover` | The `groove_folio` post — title, subtitle, byline, table of contents, entry link |
| **Page** | `page.php` → `Page` | A `groove_folio_page` post — the actual reading experience |

Both classes extend `Groove\Themes\Base_Theme` ([base-theme.php](base-theme.php)), which handles data loading,
asset enqueueing, font injection, and theme metadata. A theme subclass supplies **markup and styling only**.

Themes ship two ways, and the code path is identical for both:

- **Built-in** — a folder inside `themes/`, auto-discovered on every request.
- **Installed package** — a ZIP uploaded via *Groove → Themes*, extracted to `wp-content/groove-themes/<theme-id>/`
  and recorded in the `groove_installed_themes` option. Lives outside the plugin so it survives updates.

---

## 2. Folder anatomy

```
themes/<theme-id>/
├── setup.php              REQUIRED — returns a metadata array (see §3)
├── cover.php              REQUIRED — declares class Cover extends Base_Theme
├── page.php               REQUIRED — declares class Page  extends Base_Theme
├── navigation-pane.php    optional — shared nav renderer, loaded via `dependencies`
├── blocks.php             optional — theme-specific Gutenberg blocks
├── blocks/<block>/        optional — block editor JS/CSS
└── assets/
    ├── css/theme.css      REQUIRED by convention — auto-enqueued by Base_Theme
    ├── js/<theme-id>.js   optional — enqueue yourself by overriding ensure_script()
    └── images/
        ├── theme-thumb.png    picker thumbnail
        ├── theme-cover.png    cover/hero background
        └── theme-g-logo.png   logo mark
```

`assets/css/theme.css` is enqueued automatically at the path
`get_theme_assets_path() . 'css/theme.css'`. There is no build step — it is authored as plain CSS
and cache-busted by `filemtime()`.

### Reference implementations

| Theme | Character | Learn from it |
|-------|-----------|---------------|
| [folio-starter/](folio-starter/) | Minimal, no JS, no nav pane | The baseline shape of a theme |
| [groove-ebook/](groove-ebook/) | Minimal + full-bleed cover | Same shape, different styling |
| [groove-newsletter/](groove-newsletter/) | Shared nav pane, scoped tokens, view transitions | `dependencies`, `body.groove .gn` scoping |
| [groove-magazine/](groove-magazine/) | Runtime palette extraction from feature images | Theme JS, dark mode bootstrap |
| [groove-proposal/](groove-proposal/) | Folio-level meta fields, custom blocks, dark mode | Theme-specific admin UI + blocks |

---

## 3. `setup.php` — the theme manifest

Returns a plain array. **No side effects** — it is `include`d repeatedly (discovery, install validation,
`Base_Theme::get_setup_data()` caching).

```php
<?php
return [
    'name'         => 'Groove Newsletter',            // Human name. The theme ID is sanitize_title() of this.
    'thumbnail'    => 'theme-thumb.png',              // Filename only, resolved under assets/images/
    'cover'        => 'theme-cover.png',
    'logo'         => 'theme-g-logo.png',
    'description'  => 'A modern editorial newsletter…',
    'author'       => 'StudioEN',
    'last_updated' => '2026-03-12',
    'namespace'    => 'Groove\Themes\Groove_Newsletter',
    'cover_class'  => 'Groove\Themes\Groove_Newsletter\Cover',
    'page_class'   => 'Groove\Themes\Groove_Newsletter\Page',
    'dependencies' => ['navigation-pane.php'],        // Optional, relative paths, require_once'd before cover/page
];
```

**The theme ID is derived, never declared.** `Base_Theme::get_id()` returns `sanitize_title(get_name())`,
so `'Groove Newsletter'` → `groove-newsletter`. The folder name must match, because
`resolve_theme_folder_url()` falls back to `GROOVE_URL . 'themes/' . get_id() . '/'` for plugin-bundled themes.
**Renaming `name` renames the ID and orphans every folio that stored the old one** — see §9 for migrations.

`version` is read only for installed packages (stored in the option, not exposed on the descriptor).

---

## 4. Registration and discovery

[`Themes_Manager::register_defaults()`](themes-manager.php) runs once from `Plugin::__construct()`
(includes/plugin.php:188) and does three things:

1. **`load_builtin_themes()`** — `glob()`s `themes/*` directories, `natsort()`s them, and for each folder
   with `setup.php` + `cover.php` + `page.php`: requires `dependencies`, requires cover/page, verifies both
   classes exist and are `is_subclass_of(Base_Theme::class)`, then `register()`s them.
   Any failing check skips the folder **silently** — a theme that does not appear in the picker almost
   always failed one of these guards.
2. **`load_installed_themes()`** — same, driven by the `groove_installed_themes` option.
3. **`run_migrations()`** — one-shot legacy `theme_id` remapping (see §9).

The registry is a flat map: `theme_id => ['cover_class' => …, 'page_class' => …]`.

`get_all_themes()` returns descriptors built statically by `Base_Theme::get_theme_descriptor()` —
no instantiation, so a theme constructor never runs just to draw the picker. Descriptor shape:

```php
['ID', 'name', 'thumbnail_url', 'cover_url', 'logo_url', 'description', 'author', 'last_updated']
```

Consumers: [pages/themes.php](../pages/themes.php) (manage), [pages/folio.php](../pages/folio.php)
(picker + folio panel), [pages/add-new.php](../pages/add-new.php), [pages/settings.php](../pages/settings.php),
[pages/all-folios.php](../pages/all-folios.php) (theme column).

Ordering matters: **the first registered theme is the fallback** whenever a folio's stored `theme_id`
is empty or unrecognised (`create_cover_theme()`, `create_page_theme()`, `Base_Theme::get_theme_data()`).

---

## 5. Request routing — how a theme reaches the browser

Groove deliberately registers **no rewrite rules** (see the comment in `Plugin::add_rewrite()`).
Adding them makes WordPress see a CPT archive query and `redirect_canonical` bounces the visitor home
before templates run. Instead, a `template_redirect` hook at **priority 5** (before `redirect_canonical`)
intercepts and `exit`s:

```
template_redirect (prio 5) in includes/plugin.php
├── ?groove_theme_preview=<id>  → includes/theme-picker-preview-template.php  (admin-only, nonce'd)
└── path matches /<base-slug>/  → includes/folio-preview-template.php
    OR ?groove_preview=1
    OR ?folio_id= / ?p= pointing at a groove post
```

[`folio-preview-template.php`](../includes/folio-preview-template.php) then:

1. Calls `Themes_Manager::create_theme_for_current_request()`.
2. If that returns `null`, checks for a password-protected folio and renders a themed password gate
   (accent + background colour keyed off `theme_id` via hardcoded maps in that file — **add your theme
   there when you ship one**), otherwise 404s.
3. Otherwise emits a bare document: `<head>` + `wp_head()`, `<body class="… groove">`, `$theme->display_theme()`,
   `wp_footer()`. **The active WordPress site theme is bypassed entirely.**

`create_theme_for_current_request()` resolves the folio like this:

- `Utils::get_groove_post_id()` / `get_groove_post_type()` identify the requested post.
- Viewability is checked (`is_groove_post`, `can_current_request_view_post`).
- For a **folio page**, the parent folio is resolved **URL-first** — the folio slug in
  `/<base>/<folio>/page/<page>` wins over the `folio_id` meta, because that meta goes stale after
  duplicate-then-delete. Meta is the fallback.
- For a **folio cover**, if `Utils::is_folio_cover_enabled()` is false it 302s to the first page instead.
- Then `theme_id` meta on the folio picks the class.

### URLs

`Utils::get_folio_permalink_by_id()` is the only URL builder themes should use.

| Context | URL |
|---------|-----|
| Published folio | `/{base_slug}/{folio-slug}` |
| Published page | `/{base_slug}/{folio-slug}/page/{page-slug}` |
| Draft / no pretty permalinks / admin | `?groove_preview=1&folio_id=…` or `?groove_preview=1&p=…&post_type=groove_folio_page` |

`{base_slug}` is the `groove_folio_base_slug` option, default `folio`. Never hardcode `/folio/` —
call `Utils::get_folio_base_slug()`.

---

## 6. `Base_Theme` — what you inherit

### Properties populated by `get_data()`

| Property | Source |
|----------|--------|
| `$id`, `$post_type` | Current request (`Utils::get_groove_post_id/type`) |
| `$title`, `$content`, `$author`, `$feature_image`, `$page` | `get_page_data()` — a `WP_Query` over the current post |
| `$pages` | `get_pages_data($id)` — sibling `groove_folio_page`s ordered by `menu_order ASC`, filtered by `folio_id` meta |
| `$theme_id`, `$theme_name`, `$theme_cover_url`, `$theme_logo_url`, `$show_logo` | `get_theme_data()` — resolved through `Themes_Manager` |
| `$copyright` | `copyright` meta on the folio |
| `$is_preview_mode` | `true` only under the theme-picker preview |

`$author` respects the `show_byline` and `byline` folio meta. `$theme_logo_url` respects `show_logo`
and a custom `logo_id` attachment override.

### Lifecycle

```
Themes_Manager::create_*_theme()      → new Cover() / new Page()
  Base_Theme::__construct()           → resolves $id/$post_type, hooks wp_enqueue_scripts → ensure_script()
folio-preview-template.php
  $theme->display_theme()
    parent::display_theme()           → get_data(); returns true
    …your markup…
```

`display_theme()` in a subclass **must** start with:

```php
if (!parent::display_theme()) {
    return;
}
```

That call is what loads the data. Skipping it renders an empty theme.

### `ensure_script()` — asset enqueueing

`Base_Theme::ensure_script()` runs on `wp_enqueue_scripts` and:

- prints `window.GROOVE_IS_PREVIEW = true` and calls `show_admin_bar(false)`
- enqueues `groove` (assets/css/groove-main.css) — the shared reset/layout
- enqueues `groove-folio-contract` (assets/css/folio-contract.css) — the `--folio-*` token
  contract and its fallbacks, dependent on `groove`
- enqueues `groove-theme-<theme-id>` from `assets/css/theme.css`, dependent on `groove` and
  `groove-folio-contract`, versioned by `filemtime()`
- calls `enqueue_folio_fonts()` (see below)
- enqueues `groove` JS (assets/js/groove-main.js) with jQuery

To add theme JS, **override and call `parent::ensure_script()` first** — in *both* `Cover` and `Page`:

```php
public function ensure_script()
{
    parent::ensure_script();

    $js_path = trailingslashit(GROOVE_PATH) . 'themes/' . static::get_id() . '/assets/js/my-theme.js';
    $version = file_exists($js_path) ? filemtime($js_path) : GROOVE_VERSION;

    wp_enqueue_script(
        'my-theme',
        $this->get_theme_assets_url() . 'js/my-theme.js',
        [],
        $version,
        true
    );
}
```

Path helpers available: `get_theme_folder_path()`, `get_theme_folder_url()`, `get_theme_assets_path()`,
`get_theme_assets_url()`, `get_theme_css_path()`, `get_theme_css_url()` — all resolve correctly for
both plugin-bundled and `wp-content/groove-themes/` installs, so prefer them over `plugin_dir_url()`.

### Fonts — declare them, never enqueue them

**A theme must not load a font itself.** No `@import` in `theme.css`, no `wp_enqueue_style()` of a
Google Fonts URL in `ensure_script()`, no hand-written `<link>`. Everything goes through
`Groove\Themes\Font_Loader`, which is the single place in the plugin that touches a font CDN.

Declare the theme's defaults in `setup.php` instead:

```php
'fonts' => [
    'header' => [
        'css_stack'     => "'Fraunces', Georgia, 'Times New Roman', serif",
        'google_family' => 'Fraunces:ital,wght@0,300;0,400;1,300;1,400',
    ],
    'body' => [
        'css_stack'     => "'Inter', system-ui, -apple-system, sans-serif",
        'google_family' => 'Inter:wght@400;500;600',
    ],
],
```

`google_family` is a `family=` fragment for the [css2 API](https://developers.google.com/fonts/docs/css2)
— spaces as `+`, weights after the colon. Omit it (or leave it empty) for a system stack that needs no
network request. Both values are sanitised, so an installed theme package cannot inject CSS or extra URL
parameters. The block is optional; a theme that declares nothing simply falls back to the stacks written
into its own CSS.

**Resolution order, per role.** `Base_Theme::ensure_script()` calls `enqueue_folio_fonts()`, which asks
`Font_Loader::resolve()` for each of `header` and `body`:

1. the folio's own choice — `header_font` / `body_font` meta, validated against
   `Utils::get_supported_primary_fonts()` (legacy single `fonts` meta is the fallback for both);
2. the theme's `setup.php` default;
3. nothing — the fallback stack in your CSS applies.

Whatever wins, **both roles are fetched in one `css2` request** (duplicate families deduped), enqueued as
the handle `groove-folio-fonts` with no `?ver=`, and paired with a `preconnect` to `fonts.gstatic.com`.
Then these variables are injected on the theme handle:

```css
.g-folio__theme-cover,
body.groove [class*="g-folio__theme-"][class$="-page"] {
  --g-folio-header-font: …;
  --g-folio-body-font: …;
  --g-folio-primary-font: var(--g-folio-body-font);
  font-family: var(--g-folio-body-font);
}
```

**This selector is a hard contract on your root element:**

- The **cover** root must carry the class `g-folio__theme-cover`.
- The **page** root's `class` attribute must **end** with `-page` (it is an attribute-suffix match on the
  whole string, so the *last* class listed must end in `-page`) and contain some `g-folio__theme-*` class.

Working examples: `class="gm gm-cover g-folio__theme-cover"`, `class="gp gp-page g-folio__theme-page"`,
`class="g-folio__theme-newsletter-page gn gn-page"`.

Theme CSS consumes the variables with its own fallbacks, so the theme still looks right if the variables
never arrive (an old folio, a partial render):

```css
--gn-font-heading: var(--g-folio-header-font, 'Space Grotesk', sans-serif);
--gn-font-body:    var(--g-folio-body-font,   'Source Serif 4', Georgia, serif);
```

`--g-folio-primary-font` is the pre-role-split name, aliased to the body font. New themes should use the
two role variables.

**Where fonts load — and where they must not.** A font request is only ever made on a surface that
renders a theme:

| Surface | Path |
| --- | --- |
| Folio cover / page, published or `?groove_preview=1` | `Base_Theme::ensure_script()` |
| Theme-picker preview (Add New → *Preview*) | same — the template instantiates the theme |
| Password gate | `includes/folio-preview-template.php`, resolved by `Font_Loader`, linked by hand (it renders before `wp_head()`) |
| Block editor, folio pages | the theme's `blocks.php`, **gated on the edited folio actually using that theme** |

Nothing loads a font on a plain admin screen, on another theme's folio, or plugin-wide. If you add an
editor-side font load, gate it the same way `groove-proposal/blocks.php` does — resolve the folio, check
its `theme_id`, and hand the result to `Font_Loader::enqueue()` with the `.editor-styles-wrapper` selector.

Folio-selectable fonts are the fixed list in `Utils::get_supported_primary_fonts()` (DM Sans, Inter, Lato,
Merriweather, Montserrat, Noto Sans, Noto Serif, Nunito Sans, Poppins, Roboto).

### Other helpers

- `get_navigation_context($args)` — resolves `folio_id`, `title`, `title_url`, `pages`, `current_page_id`
  with fallbacks. `title_url` is empty when the folio's cover is disabled, so nav titles do not link to a
  cover that redirects.
- `apply_embed_processing($html)` — **required** if you render blocks manually. Themes bypass `the_content`,
  so bare oEmbed URLs stay inert without this. Run rendered HTML through it before echoing.
- `get_folio_id_for_customization()` — the folio ID for the current request, whether the request is a
  cover or a page (URL path first, then `folio_id` meta).
- `resolve_page_folio_id()` — the canonical parent-folio resolver for `Page` subclasses. Order: URL path →
  `folio_id` meta → `folio_id` request param, matching `Themes_Manager`. Backfills missing meta from the
  URL. **Every `Page::__construct()` should use this** rather than re-implementing the lookup:
  ```php
  public function __construct()
  {
      parent::__construct();

      $this->folio_id = $this->resolve_page_folio_id();
  }
  ```

---

## 7. Markup conventions

### Two class layers

1. **Shared behavioural classes** (`g-folio__theme-*`) — wired to jQuery handlers in
   [assets/js/groove-main.js](../assets/js/groove-main.js) and to the font selector. Use these for anything
   that must *work*:

   | Class | Behaviour (groove-main.js) |
   |-------|---------------------------|
   | `.g-folio__theme-nav-button` | opens `.g-folio__theme-nav` (adds `.visible`) |
   | `.g-folio__theme-nav-close` | closes it |
   | `.g-folio__theme-page-nav-button` / `-close` | same pair for `.g-folio__theme-page-nav` |
   | `.g-folio__theme-page-nav-bar-toggle` | toggles `.g-folio__theme-page-mobile-nav.visible` |
   | `.g-folio__theme-page-mobile-nav-back` | scroll to top |
   | `.g-folio__theme-cover` / root ending `-page` | font variable injection target |

   The theme-picker preview additionally neutralises `.g-folio__theme-fields-submit`,
   `.g-folio__theme-page-nav-item-link`, `.g-folio__theme-nav-item-link`, `.g-folio__theme-page-prev a`,
   `.g-folio__theme-page-next a` with `pointer-events: none`. Using those class names keeps your links
   inert in the preview.

2. **Theme-private classes** — a short prefix per theme (`gn-` newsletter, `gm-` magazine, `gp-` proposal),
   BEM-ish: `gn-cover__story-title`. All visual styling hangs off these.

Newer themes stack both on the same element: `class="g-folio__theme-page-nav-button gp-nav-trigger"` —
shared class for behaviour, private class for looks.

### CSS scoping

The rendered document has `<body class="… groove">`. Scope theme CSS to a theme-private root
(`body.groove .gn { … }`) so it cannot leak into the WordPress admin, which also carries `body.groove`
via the `admin_body_class` filter. `folio-starter` and `groove-ebook` use bare `.g-folio__theme-1` roots —
that is legacy, not the pattern to copy.

Define design tokens as custom properties on the theme root, not on `:root`, for the same reason.
The exception is a theme that ships a light/dark toggle: its scheme class sits on `:root`, and custom
properties inherit downward, so declaring tokens on a closer, more specific element would defeat the
override. `groove-magazine` and `groove-proposal` declare on `:root` for exactly that reason. A theme
with no scheme toggle has no excuse.

### Design tokens — the `--folio-*` contract

Every theme fills the same set of named slots, declared in
[assets/css/folio-contract.css](../assets/css/folio-contract.css) and enqueued ahead of every
`theme.css`. A theme keeps its own values and as many private tokens as it likes — **the slots are a
floor, not a ceiling** — but anything that has to work across themes can only read a name every theme
agrees on. This is the same handoff `Font_Loader` already performs for `--g-folio-header-font` /
`--g-folio-body-font`, extended from type to colour, depth and motion.

| Slot | Role |
|------|------|
| `--folio-ground` | The page behind everything |
| `--folio-surface` | The plane content sits on |
| `--folio-surface-alt` | The theme's other surface tone — direction-neutral, it may lift or recess |
| `--folio-text` | Primary text |
| `--folio-text-muted` | Secondary text |
| `--folio-text-subtle` | Tertiary text |
| `--folio-on-accent` | Text that sits *on* the accent, wherever the accent is a fill |
| `--folio-on-ground` | Text that sits *on* the ground — only matters if your ground is chromatic; defaults to `--folio-text` |
| `--folio-rule` | Hairlines and dividers |
| `--folio-rule-strong` | The heavier rule |
| `--folio-accent` | The accent hue |
| `--folio-accent-hover` | Its interactive state |
| `--folio-accent-soft` | A wash pale enough to carry `--folio-text` |
| `--folio-focus` | The focus ring. Defaults to `--folio-accent`; override it the moment your ground is that same hue |
| `--folio-accent-2` | A second hue, for emphasis that is *not* interactive — quotes, ordinals, tags, stat rules. Defaults to the primary |
| `--folio-accent-2-soft` | Its pale wash. Defaults to `--folio-accent-soft` |
| `--folio-font-heading` | Display face — seeded from `--g-folio-header-font` |
| `--folio-font-body` | Reading face — seeded from `--g-folio-body-font` |
| `--folio-shadow` | The theme's elevation shadow |
| `--folio-overlay` | Scrim behind modals and covers |
| `--folio-ease` / `--folio-dur` | Motion, kept as two composable parts rather than one shorthand |

#### Filling the contract

The **slot carries the value; your private token is the alias** — never the reverse:

```css
body.groove .my-theme {
  --folio-surface: #f7f1e6;              /* the slot holds the literal */
  --my-surface: var(--folio-surface);    /* your name is an alias onto it */
}
```

That direction is the whole mechanism. Every selector in your stylesheet keeps reading `--my-surface`
and nothing has to change, while anything reading `--folio-surface` — the plugin, a future block, a
per-folio accent override — gets the right answer. Reverse it and an override of `--folio-surface`
silently does nothing.

Leave a slot **unfilled** rather than inventing a value for it: the contract file carries a WordPress
admin-palette fallback, so an unfilled slot degrades to something recognisably Groove instead of to
unstyled HTML. A theme is not required to fill all eighteen.

#### Light and dark

A theme that ships a scheme redeclares the **slots** in its scheme blocks, not its private aliases —
the aliases already point at the slots, so re-pointing them is a no-op that breaks the chain:

```css
:root.folio-scheme-dark {
  --folio-surface: #201f1b;   /* correct — the slot moves */
}
```

New themes should use `.folio-scheme-light` / `.folio-scheme-dark` on `:root`.
`groove-magazine` and `groove-proposal` predate that name and carry their own
(`gm-theme-*`, `gp-theme-*`), read and written by their theme JS; both now also answer to the
canonical names via a selector list. `groove-proposal` has the reference implementation of the
three-state logic — explicit dark, explicit light, and a system-preference fallback guarded with
`:not()` so an explicit choice always wins. Copy that pattern rather than inventing one.

#### Interaction states are not optional

**A folio renders through the site's own WordPress front end.**
`includes/folio-preview-template.php` calls `wp_head()`, so the active site theme's stylesheet loads
alongside yours. Anything your theme leaves unsaid about a link, *some other stylesheet says instead* —
and a great many WordPress themes ship `a:hover { text-decoration: none }`. That is not a hypothetical:
it is exactly how `groove-ebook` came to remove a link's underline on hover without a single `:hover`
rule anywhere in the theme. Silence is not neutrality; it is a delegation to a stylesheet you have
never seen.

So state your own, and state them by this rule: **hover and focus ADD affordance, they never subtract
it.** Removing an underline on hover inverts the signal and fails WCAG 1.4.1, which is why colour alone
cannot carry a link inside running text.

The three shapes a link takes in these themes, and what each one should do:

| Kind | At rest | On hover / focus |
|------|---------|------------------|
| Prose link, inside running text | Underlined | Thickens, and takes `--folio-accent-hover` |
| List row in a nav pane | Bare — the row, rule and ordinal already mark it | Row tint (`--folio-accent-soft`) and a colour shift |
| Standalone chrome link — pager, byline, catalog | Bare | Gains an underline |

And give **every** interactive element a focus ring:

```css
.your-theme-root :focus-visible {
  outline: 2px solid var(--folio-focus);
  outline-offset: 2px;
  border-radius: 2px;
}
```

`--folio-focus` defaults to `--folio-accent`, which is right on a pale ground. Override it when your
ground is chromatic — `groove-newsletter` uses near-black because its warm accent vanishes against the
paper, and `groove-ebook` keeps the accent for its white chrome but flips to white for the handful of
controls that sit on its blue field. **Never ship `outline: none` without a ring to replace it**;
`bin/check-theme-contract.php` fails a theme that does, and fails one with no `:hover` or
`:focus-visible` rules at all.

#### Drawers: one attribute, not a script

`groove-main.js` owns opening and closing — reuse `g-folio__theme-nav-button` / `-nav-close` /
`-page-nav-button` / `-page-nav-close` and it toggles `.visible` on the pane for you. Everything a bare
`addClass` cannot give you — ARIA state, Escape, click-outside dismissal, scroll lock, and focus moved
in and returned on close — is **opt-in on the pane**:

```html
<nav class="g-folio__theme-nav"
     aria-label="Folio contents"
     data-groove-drawer=".g-folio__theme-nav-button">
```

The attribute's value is the CSS selector of the pane's trigger. Add `data-groove-drawer-lock="off"`
for a dropdown-style panel that should not lock the page behind it — `groove-ebook`'s mobile "on this
page" list uses that. It is opt-in rather than automatic because `groove-newsletter` and
`groove-proposal` ship their own drawer controllers and would otherwise double-handle every event; a
theme that owns its drawers simply says nothing.

Two things your CSS still owns, and both matter:

- **`visibility`, not just `transform`.** A pane translated off-screen is still in the tab order, so
  tabbing walks into an invisible drawer. Hide it with `visibility: hidden` and a transition delay
  equal to the slide, so the animation still plays on the way out — and drop `visibility` from the
  `.visible` rule's transition list so it flips *instantly* on open, or focus cannot move into it.
- **Scroll the content box, not the pane.** Put `overflow-y: auto` on `…-nav-content` and leave the
  pane itself `overflow: hidden`, with the close button a **sibling** of that box rather than a child.
  Inside it, the button scrolls away with a long contents list. Give it the pane's own background so
  the list passes behind it rather than through it.

`folio-starter` and `groove-ebook` are both worked examples. `folio-starter` carried a private
140-line copy of this controller until it moved here.

#### Reduced motion is your job, not the contract's

The contract deliberately carries no `prefers-reduced-motion` rule. Its fallbacks sit on `:root`; your
slots sit on your theme root, which is nearer and therefore wins — so a contract-level override would
reach only the themes that filled nothing and silently miss every theme that filled something. Handle
it in your own stylesheet at your own specificity. `folio-starter` is the reference: it kills
transitions with `transition-duration: 1ms !important` rather than by retiming a token.

#### Opt-in: space and type scales

Everything in the table above is colour, and every theme fills it. The contract also carries a **fluid
type ramp and a nine-step space scale** which are entirely opt-in:

```
--folio-space-1 … --folio-space-9                       4 8 12 16 24 32 48 64 80
--folio-text-display / -title / -h2 / -h3 / -body       clamp()ed, phone → desktop
--folio-text-meta / -label                              fixed, UI-sized
--folio-leading-display / -title / -body / -ui
```

The values are `folio-starter`'s, which is the theme that was rebuilt for responsive and is the
reference for the rest. They exist so a **new** theme gets a working fluid ramp without deriving one,
and so a theme being modernised has something to adopt rather than inventing a parallel scale.

A theme with its own scale should ignore them entirely — `groove-newsletter` and `groove-proposal` are
heavily `clamp()`ed already in their own idiom, and `groove-magazine` deliberately mixes fluid display
type with fixed UI chrome. Retrofitting those would move pixels for no gain. Adopting the ramp is the
right move only when a theme has **no** scale of its own.

Where the ramp does not have a size your design needs, declare a private `--<prefix>-*` token. Do not
reach for a bare px literal, and do not bend a ramp step to a value it was not meant for.

#### If your theme repaints at runtime, set the slots

The alias direction has a corollary that is easy to miss. If theme JS writes custom properties at
runtime, it must set the **slots**, not the private aliases.

Inline styles beat the stylesheet, so setting `--gn-text` inline still renders your theme correctly:
your own selectors read `--gn-text` and get the live value. But `--folio-text` is still sitting on the
literal from your stylesheet, so anything reading the contract — the plugin, a shared block, a future
per-folio override — gets a stale colour while the page shows a different one. The theme looks right
and the contract quietly lies.

Three of the five themes repaint, and all three now write slots:

- **`groove-magazine`** — `applyPalette()` sets `--folio-accent`, `-accent-hover` and `-accent-soft`
  from a per-folio extracted accent. Its other four accent names hold literals in `theme.css` and stay
  private; `--gm-hero-overlay` in particular is *not* `--folio-overlay` (a hero scrim and a modal scrim
  are different jobs), so writing the slot there would have repainted the nav.
- **`groove-newsletter`** — `applyPalette()` sets nine slots for a time-of-day palette. This is the one
  that shows the coupling to watch for: `--folio-rule` and `--folio-rule-strong` are `color-mix()`
  expressions that used to read `--gn-text` / `--gn-bg` *precisely because* the JS set those inline.
  They now read `var(--folio-text)` / `var(--folio-ground)`. Moving the JS without moving the mixes
  would have frozen both rules at the static colour with nothing visibly broken.
- **`groove-proposal`** — `applyDynamicPalette()` sets eleven slots from a feature-image dominant
  colour, when a folio opts in with `data-gp-color-scheme="dynamic"`. Note its
  `DYNAMIC_ACCENT_PROPERTIES` list, which `clearDynamicPalette()` uses to tear the palette down when a
  folio leaves dynamic mode: **it has to name exactly what the setter writes.** A property written but
  not listed would survive the teardown and stick.

Set only the slot, never both — but check first that your private really is an alias. A private
holding a literal has to be written directly, because nothing points at it.

**And prefer a class to a property.** There is a fourth repaint that is not a palette:
`setNavBarBackgroundColor()` in `assets/js/groove-main.js` used to write two literal `rgba()` values
straight onto the top bar's `background-color` as the page scrolled — so the bar ignored
`--folio-overlay` and no theme could restyle either state. It now toggles `.is-scrolled` and the
colours live in the theme:

```css
.your-page-root .g-folio__theme-page-nav-bar { background-color: var(--folio-overlay); }
.your-page-root .g-folio__theme-page-nav-bar.is-scrolled { background-color: var(--folio-surface); }
```

`folio-starter` and `groove-ebook` use it; `groove-newsletter` and `groove-proposal` are filtered out
of that handler and paint their own bars. Where the runtime is choosing between two states rather than
computing a value, a class is the better handoff than a property — it leaves both states in CSS, where
a theme can see and change them.

#### Fewer tones than slots? Point one slot at another

Where a theme genuinely has no distinct value for a slot, point it at the slot it collapses onto
rather than leaving it unfilled:

```css
--folio-rule-strong: var(--folio-rule);   /* groove-magazine has one line tone */
--folio-ground: var(--folio-surface);     /* folio-starter's ground and plane are the same white */
```

Leaving it unfilled is the wrong answer here: the fallback is a WordPress admin grey, which is
off-palette for your theme, so a consumer reading the slot would get a colour your design never uses.
Pointing at a sibling slot keeps it on-palette *and* keeps it tracking your scheme blocks, since the
reference resolves wherever the winning declaration lands. Reserve "unfilled" for slots your theme has
no answer to at all — an overlay in a theme with no scrim, `on-accent` in a theme that never puts type
on the accent.

#### One ordering dependency worth knowing

`Base_Theme::ensure_script()` enqueues `theme.css` with `groove-folio-contract` as a dependency, so the
contract's `:root` fallbacks always load *before* your stylesheet. Your slot declarations then win on
source order at equal specificity — or outright, if you declare on a theme root. If that ordering ever
inverts, every theme's ground turns white.

### Escaping

Every dynamic value is escaped at output: `esc_html()`, `esc_attr()`, `esc_url()`, `esc_attr__()`.
Only `get_content()` output (already block-rendered + `wp_kses`'d inside blocks) is echoed raw.
All user-facing strings go through `__()` / `esc_html__()` with the `groove` text domain.

---

## 8. Rendering page content

`Page::get_content()` is where a theme decides how blocks become HTML. The common pattern
(folio-starter, newsletter, magazine, proposal):

```php
function get_content()
{
    $blocks  = parse_blocks($this->content);
    $results = '';

    foreach ($blocks as $block) {
        if ($block['blockName'] === 'core/heading') {
            // inject an id="" anchor derived from the heading text
        }
        $results .= render_block($block);
    }

    return $this->apply_embed_processing($results);
}
```

Anchors are generated by `to_anchor_name()` (lowercase, hyphenated) and consumed by `display_catalogs()`
(desktop "On this page" rail) and `display_mobile_nav()`. Only `h2` is collected —
`get_html()` hardcodes `$element_names = ['h2']`.

The rail's label is folio-configurable: `Utils::get_folio_on_this_page_label($folio_id)`,
default `On this page`. Never hardcode that string.

Prev/next navigation uses `get_current_index()` / `get_prev_page()` / `get_next_page()` over `$this->pages`.

---

## 9. Theme-specific data

Themes that need their own fields store them as **post meta on the folio** and add UI in
[pages/folio.php](../pages/folio.php). `groove-proposal` is the worked example:

- ~20 `proposal_*` meta keys, sanitized and saved in `Folio::save()` (pages/folio.php:429+).
- A whole extra editor tab, gated in `create_tabs()`:
  ```php
  $is_proposal_theme = (string) ($fields->theme_id ?? '') === 'groove-proposal';
  if ($is_proposal_theme) {
      $tabs['proposal'] = ['label' => …, 'attrs' => ['data-theme-target' => 'groove-proposal']];
  }
  ```
  `data-theme-target` / `data-add-new-theme-target` are handled in groove-main.js:1047 and :1093 —
  elements show only while that theme is selected in the picker, without a page reload.
- The theme reads them back in `get_data()` into `$this->proposal_meta`.
- Defaults are declared in [fields/folio-fields.php](../fields/folio-fields.php).

This coupling is deliberate but has a cost: **plugin-side code hardcodes theme IDs.** When adding a theme,
grep for existing theme IDs to find every place that needs a new entry —
notably the accent/background maps in `includes/folio-preview-template.php`.

### Custom blocks

`blocks.php` (loaded via `dependencies`) registers server-rendered blocks with
`register_block_type(..., ['render_callback' => …])`, adds a block category via `block_categories_all`,
and enqueues editor JS on `enqueue_block_editor_assets` **gated on `$screen->post_type === 'groove_folio_page'`**.
Editor scripts depend on `['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components']` and are plain
`wp.element.createElement` JS — no JSX, no build step. Block markup uses the theme's private prefix
(`gp-metrics__value`) and is styled from `assets/css/theme.css`.

### Theme JS

`groove-magazine` and `groove-proposal` bootstrap a light/dark class on `<html>` inline in `display_theme()`
(before paint, to avoid a flash), persist the choice in `localStorage`, and load a dependency-free IIFE from
`assets/js/`. Magazine's script extracts a dominant colour from the feature image and derives a WCAG-checked
palette into CSS custom properties.

**A theme should rarely need its own drawer script.** `folio-starter` had one and no longer does — see
"Drawers: one attribute, not a script" in §7. Before adding theme JS, check whether the behaviour
belongs in `groove-main.js` behind an opt-in attribute instead; that is what makes it available to the
next theme rather than to this one only.

### Migrations

`Themes_Manager::run_migrations()` remaps legacy `theme_id` meta values directly in `$wpdb->postmeta`,
guarded by the `groove_theme_migration_v1` option. Changing a theme's `name` (and therefore its ID) needs
the same treatment, or every folio using it silently falls back to the first registered theme.

---

## 10. Packaging a theme for distribution

`Themes_Manager::install_theme_from_zip()` validates, in order:

1. `setup.php` exists somewhere in the ZIP (its directory becomes the package root).
2. It returns an array with a non-empty `name`.
3. `cover.php` and `page.php` exist beside it.
4. `cover_class` and `page_class` are declared.
5. `dependencies`, if present, is an array (paths are rejected if empty or containing `..`).
6. The derived theme ID is not already registered/installed, and neither class is already loaded
   (prevents fatal redeclaration).

Then it copies to `wp-content/groove-themes/<theme-id>/`, requires dependencies → cover → page,
registers, and only then persists to `groove_installed_themes`. Any failure cleans up the destination.

`uninstall_theme()` refuses to touch built-in themes — only entries in the option.

---

## 11. The theme-picker preview

[`theme-picker-preview-template.php`](../includes/theme-picker-preview-template.php) renders a theme
against **fabricated `stdClass` posts with negative IDs**, no database folio. It sets
`$theme->is_preview_mode = true` and assigns every property by hand.

That flag is why every `get_data()` override starts with:

```php
if ($this->is_preview_mode) { return; }
```

Without that guard the preview's injected data is immediately overwritten by empty query results.

Consequences for theme code:

- Guard against `$this->page === null` and empty `$this->pages`.
- Do not assume `$this->id > 0` — the cover preview sets `id = 0`, pages use `-1` / `-2`.
- `get_post_meta()` returns nothing in preview; read meta in `get_data()` (after the guard) rather than
  inline in markup, or provide fallbacks.
- If your theme has meta-driven markup, add a defaulting branch to that template
  (`property_exists($theme, 'proposal_meta')` is the existing pattern) or the preview renders half-empty.

---

## 12. Checklist for a new theme

1. `mkdir themes/<theme-id>` — folder name must equal `sanitize_title(name)`.
2. Write `setup.php` (no side effects; unique `name`, unique namespace, unique classes).
3. `cover.php` — `class Cover extends Base_Theme`; `display_theme()` calls `parent::display_theme()` first;
   root element carries `g-folio__theme-cover`.
4. `page.php` — `class Page extends Base_Theme`; root `class` attribute ends in `-page`; implement
   `get_content()` and run output through `apply_embed_processing()`.
5. Both files start with `if (!defined('ABSPATH')) { exit; }`.
6. `assets/css/theme.css` scoped to a theme-private root, consuming `--g-folio-header-font` /
   `--g-folio-body-font` with fallbacks — and loading no font of its own (no `@import`; declare
   `fonts` in `setup.php` instead).
7. Fill the `--folio-*` contract (§7): the slot carries your literal, your private token aliases onto
   it. Leave a slot unfilled only where your theme has no answer at all; collapse it onto a sibling
   slot where it simply has fewer tones. If you ship a scheme, redeclare **slots** in the scheme
   blocks, use `.folio-scheme-light` / `.folio-scheme-dark` on `:root`, and if theme JS repaints at
   runtime, have it set the slots. If you have no type scale of your own, adopt the contract's opt-in
   ramp rather than writing fixed px sizes.
8. Interaction states (§7): a `:focus-visible` ring on `var(--folio-focus)` for everything focusable,
   and a `:hover` state for every link. Hover and focus **add** affordance — an underline that
   disappears on hover is the one thing this contract will not have. Never `outline: none` without a
   ring to replace it.
9. Drawers (§7): reuse `g-folio__theme-nav-button` / `-nav-close` / `-page-nav-button` /
   `-page-nav-close` so `groove-main.js` wires open and close, then add
   `data-groove-drawer="<trigger selector>"` to the pane for ARIA, Escape, click-outside, scroll lock
   and focus. Hide the pane with `visibility` rather than transform alone, and scroll `…-nav-content`
   with the close button outside it. Do not write a drawer script.
10. Responsive: no fixed `font-size` in px — use `clamp()` or the contract ramp. Degrade progressively
   rather than switching between a desktop and a phone state, and prefer intrinsic sizing
   (`min()`, `clamp()`, `flex`) to a step wherever one will do the job. Declare a desktop-only
   component mobile-first — absent by default, added back at a `min-width` — rather than adding it
   everywhere and walking it back down; `groove-ebook`'s contents sidebar is the reference. Size
   full-height boxes in `dvh` with a `vh` fallback, keep the document as the only scroller (a nested
   `height: 100vh; overflow: scroll` silently breaks every `window.scrollY` listener above the phone
   breakpoint), give anchored headings a `scroll-margin-top` that clears your fixed bar, and never
   pair `width: 100%` with a horizontal `margin`. Add `@media (pointer: coarse)` for 44px touch
   targets and `@media (prefers-reduced-motion: reduce)` to kill transitions.
11. `assets/images/theme-thumb.png`, `theme-cover.png`, `theme-g-logo.png`.
12. Add accent + background entries to the maps in `includes/folio-preview-template.php` so the password
   gate matches.
13. Run `php bin/check-theme-contract.php --theme=<theme-id>`. It reports which slots you filled and
    flags the mistakes that fail silently: a slot aliased *onto* a private token instead of the other
    way round, display type that adapts at no width, `outline: none` with no ring to replace it, and a
    theme with no `:hover` or `:focus-visible` rules at all. Unfilled slots are often correct — read
    the `!` lines, not the counts. `--strict` makes it exit non-zero, for CI.
14. Verify: theme appears in *Groove → Themes*, the picker preview renders (`?groove_theme_preview=<id>`),
    a published folio cover and page render, a draft folio renders via `?groove_preview=1`, fonts respond
    to the folio's font pickers, and a password-protected folio shows a matching gate.

---

## 13. Known warts

- **`themes/default-themes.php`** is a deprecated shim over `Themes_Manager::get_all_themes()`.
- **Theme IDs are hardcoded** in the plugin's password-gate colour maps and in folio.php's tab gating.
- **`newsletter_theme_preset`** meta is actively deleted on save (pages/folio.php:566); the preset helpers
  in `Utils` are vestigial.
- **`themes/landing-page.html` and `landing-page-v8 2.html`** in this directory are marketing page drafts,
  not themes. `load_builtin_themes()` only scans directories, so they are ignored.
- **`groove-magazine` deliberately mixes two responsive idioms.** Its cover and page titles are
  `clamp()`ed *and* overridden at the 768px breakpoint; its nav chrome is stepped only. Both adapt, so
  neither is a defect — and they cannot be unified without changing what renders: `.gm-cover__title`
  steps to a flat 36px below 768, which is *under* its clamp's 40px floor, so no single clamp
  reproduces both endpoints. Left as it is on purpose. The two sizes that genuinely adapted at no
  width (`.gm-page__content` h2 and h3) are now fluid.
- **`--folio-shadow` is filled in themes that never paint it** (`groove-newsletter`,
  `groove-proposal`). Both authored a considered shadow that no rule currently applies — probably a
  regression rather than a decision. The slot keeps the value, because a consumer asking "what shadow
  does this theme use" is better served by the theme's own on-brand answer than by the WordPress admin
  fallback. Worth deciding separately whether the themes should paint them again.
