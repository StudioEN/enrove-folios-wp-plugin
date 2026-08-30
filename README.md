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
- Anonymous, opt-in usage analytics (Settings → Privacy)

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

## Testing

`folio-embed-regression-checklist.md` documents the manual regression checklist for folio-page embed rendering (Spotify, YouTube, X/Twitter) — run it whenever folio page or theme rendering changes.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.

## License

MIT — see [LICENSE](LICENSE).
