# Groove Folios

Create, manage, and publish beautiful digital literature directly within WordPress — ebooks, newsletters, product catalogs, portfolios, proposals, and more. Groove Folios provides custom themes, access permissions, dynamic previews, and a dedicated folio builder interface powered by the Block Editor.

A "Folio" is a general-purpose multi-page document container; the theme you pick determines whether it reads as an ebook, newsletter, portfolio, proposal, or another format.

- **Plugin URI:** https://studioen.us/groove/
- **Author:** [StudioEN](https://studioen.us/)
- **Requires:** PHP 7.0+, WordPress 5.9+

## Features

- Custom post types for Folios and Folio Pages, organized with a flat Collection Tags taxonomy
- A dedicated Folio editor and Add New Folio flow, with Quick Edit support from the All Folios list
- Multiple built-in themes (Folio Starter, Groove eBook, Groove Newsletter, Groove Magazine, Groove Proposal), each with cover/page/setup templates
- Folio duplication, password-protected folios, custom logo and typeface support
- Configurable Folio URL routing/permalinks
- Collects nothing: no analytics, no telemetry, no phone-home (Settings → Privacy states what the plugin does and does not send)

## Requirements

- WordPress 5.9 or later
- PHP 7.0 or later

## Development

Frontend assets are built with [Vite](https://vitejs.dev/) and [Tailwind CSS 4](https://tailwindcss.com/).

```bash
npm install
npm run dev    # watch mode
npm run build  # production build
```

Admin UI is scoped under `body.groove` and built with Tailwind utility classes and jQuery. See `assets/js/groove-main.js` for the shared JS entry point.

## Imagery (Pexels)

Theme covers and the shared placeholder pool used by sample content are curated
**once, at build time** from [Pexels](https://www.pexels.com) and committed as local
assets. The plugin makes no Pexels API calls when a folio is rendered or an admin
page is loaded.

### Supplying the API key

Get a free key at <https://www.pexels.com/api/>. It is read from the first of these
that is set:

| # | Source | Notes |
|---|---|---|
| 1 | `GROOVE_PEXELS_API_KEY` constant in `wp-config.php` | Recommended for production/staging |
| 2 | `PEXELS_API_KEY` environment variable | Handy for CI or one-off runs |
| 3 | `.pexels-key` file in the plugin root (single line) | Recommended for local dev — gitignored |
| 4 | Settings → Imagery → Pexels API Key | Stored in the `groove_pexels_api_key` option with autoload off |

```php
// wp-config.php
define('GROOVE_PEXELS_API_KEY', 'your-key-here');
```

```bash
# or, for local development
printf %s "your-key-here" > .pexels-key
```

Never commit the key. The plugin never prints or renders it — the settings screen and
the CLI only ever show the source and a masked value such as `••••••••1234`.

### Running the curation script

```bash
php bin/curate-pexels.php --help          # usage
php bin/curate-pexels.php --dry-run       # resolve everything, download nothing
php bin/curate-pexels.php                 # curate every slot
php bin/curate-pexels.php --theme=groove-proposal
php bin/curate-pexels.php --covers-only
php bin/curate-pexels.php --slots=ph-workspace,ph-reading --force
php bin/curate-pexels.php --wp=/path/to/wordpress
```

The script bootstraps WordPress itself (locating `wp-load.php` automatically, or via
`--wp=`), is safely re-runnable — existing files are skipped unless `--force` is passed —
and exits non-zero if any slot fails. Downloaded files land in
`themes/<theme>/assets/images/theme-cover.jpg` and `assets/images/pexels/`, with
attribution recorded in `assets/images/pexels/credits.json`.

### Attribution

The Pexels licence requires a prominent link to Pexels and credit to the photographer
wherever an image is used. Settings → Imagery lists every curated image with its
photographer, the photographer's Pexels profile, and the photo page, alongside the
required "Photos provided by Pexels" link. Do not remove these.

## Project Structure

| Path | Purpose |
|---|---|
| `groove-folios.php` | Plugin bootstrap |
| `includes/` | Core plugin wiring (CPTs, taxonomy, hooks) |
| `pages/` | Admin page templates (All Folios, Folio editor, Settings, Themes, etc.) |
| `list/` | WP_List_Table implementations for the admin list views |
| `themes/` | Self-contained folio themes (`cover.php`, `page.php`, `setup.php`, `theme.css` per theme) |
| `modules/` | Feature modules |
| `menu/` | Admin menu registration |
| `fields/` | Custom meta field helpers |
| `assets/` | Compiled/source JS and CSS |
| `utils/` | Shared PHP utilities |
| `pexels/` | Pexels API client, key resolution, curator, and credits |
| `bin/` | Developer CLI scripts (`curate-pexels.php`) |

## Testing

`folio-embed-regression-checklist.md` documents the manual regression checklist for folio-page embed rendering (Spotify, YouTube, X/Twitter) — run it whenever folio page or theme rendering changes.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.

## License

MIT — see [LICENSE](LICENSE).
