# Changelog

## [Unreleased]

### Added
- Pexels integration for theme covers and sample-content placeholder imagery. Images are curated once at build time and committed as local assets — the plugin makes no Pexels API calls when rendering folios or loading admin screens.
- `bin/curate-pexels.php` CLI script to download the curated image set. Bootstraps WordPress on its own (`--wp=` to point at a specific install) and supports `--slots=`, `--theme=`, `--covers-only`, `--placeholders-only`, `--force`, `--dry-run`, and `--help`. Re-runnable and idempotent; exits non-zero if any slot fails.
- Settings → Imagery tab: store the Pexels API key (option `groove_pexels_api_key`, autoload off), remove it, and run an explicit on-demand connection test. The stored key is never rendered back into the page — only its source and a masked value are shown.
- Key resolution order: `GROOVE_PEXELS_API_KEY` in `wp-config.php`, then the `PEXELS_API_KEY` environment variable, then a gitignored `.pexels-key` file, then the WordPress option. When the constant is defined the settings field is disabled and a notice explains why.
- Image credits panel in Settings → Imagery listing each curated photo's photographer and Pexels links, plus the "Photos provided by Pexels" link required by the Pexels licence.

## [0.2.0] - 2026-03-28

### Added
- Anonymous usage analytics via PostHog, opt-in only via Settings → Privacy.
- Tracks folio lifecycle events (created, saved, published, unpublished) and plugin activation/deactivation.
- Each event includes theme, page count (on publish), plugin version, WordPress version, and PHP version. No personal data or site content is ever collected.
- Privacy Policy link in Settings → Privacy tab pointing to groove.studio/privacy.

## [2026-03-18]

### Added
- New **Groove Proposal** theme with custom Gutenberg blocks: Callout Box, Comparison Columns, Key Metrics, Pricing Table, Process Steps, Pull Quote, Team Grid, and Timeline.
- New **Groove Magazine** theme with magazine-style cover, navigation pane, and page layouts.
- Folio duplication — duplicate an entire Folio (with all its pages) from the All Folios screen or the Folio editor.
- Collection Tags management tab in Settings with full CRUD (create, rename, delete) and tag-count display.
- Routing settings tab for configuring Folio URL slug and permalink structure.
- Add New Folio page for streamlined Folio creation.
- Folio Overview page showing active collection tags summary.
- Quick Edit inline editing for Folios in the All Folios list table (status, date, collection tags).
- Logo customization support in Folio setup (custom logo upload per Folio).
- Password-protected Folio support with a styled password form in the preview template.
- Gutenberg breadcrumb navigation for Folio Pages in the block editor.
- Theme embed/oEmbed support across all themes via base theme helpers.

### Changed
- Overhauled All Folios list table with bulk actions, sortable columns, pagination, and collection tag filters.
- Updated theme navigation panes across all themes (Folio Starter, Groove eBook, Groove Newsletter) for consistent behavior and styling.
- Improved Folio editor layout with expanded setup options, reordered sections, and better Tailwind styling.
- Enhanced preview template to handle password-protected and draft Folios gracefully.
- Refined theme manager to support the new Proposal and Magazine themes and improved theme registration.

### Fixed
- Fixed Folio duplication creating orphaned or duplicate page entries.
- Fixed preview template rendering issues for password-protected Folios.
- Fixed embed/oEmbed display in theme page templates.
- Removed legacy Insights module and related scripts that were no longer in use.

## [2026-03-12]

### Fixed
- Fixed folio-page embed rendering: Spotify/YouTube/X URLs showed as raw text instead of embedded players because folio page themes rendered blocks via `render_block()` and bypassed WordPress's standard embed processing. Added `apply_embed_processing()` helper in `themes/base-theme.php` (runs `run_shortcode`/`autoembed`) and wired it into the render paths for Folio Starter, Groove eBook, Groove Newsletter, and Groove Magazine page templates.

## [2026-02-25]

### Added
- Added Folio primary typeface customization in the Folio setup page with Google Font options: DM Sans, Inter, Lato, Merriweather, Montserrat, Noto Sans, Noto Serif, Nunito Sans, Poppins, and Roboto.
- Added a reset-to-default icon button for the Folio primary font selector.
- Added `fonts` Folio meta registration and sanitization helpers to safely persist supported font choices.

### Changed
- Updated frontend theme rendering to load and apply the selected Folio primary font across cover and page views while preserving theme defaults when no custom font is selected.

## [2026-02-24]

### Added
- Implemented optional Folio covers: Users can now enable or disable the cover for a Folio. 
- Added `is_folio_cover_enabled()` and `get_first_folio_page_id()` utility functions to support the new cover toggle logic.
- Included an admin toggle in the Folio settings (`use_folio` meta key) to control cover visibility.
- Implemented frontend redirection logic in `themes-manager.php`: If a Folio cover is disabled, visitors are automatically redirected to the first available page within the Folio.
- Added a sortable "Theme Name" column instead of "Folio ID" to the `All Folios` table.

### Changed
- Refined Folio navigation across themes (`folio-starter`, `groove-ebook`, `groove-newsletter`): The Folio Title in the navigation pane now correctly links to the cover only if the cover is enabled.
- Updated terminology on Folio covers from "Enter" to "Open folio".
- Improved table layouts on all pages, including adjustments to visual widths and structure.
- Refined theme navigation and CSS styles for local environments to resolve loading issues.
- Updated overall Folio data structure and replaced broken media paths for image assets in the themes.
- Enhanced the AI curation workflow scripts with a focus on gathering, scoring (using LLMs), and consolidated publishing for news items.
