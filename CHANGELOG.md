# Changelog

## [Unreleased]

### Added
- Pexels integration for theme covers and sample-content placeholder imagery. Images are curated once at build time and committed as local assets — the plugin makes no Pexels API calls when rendering folios or loading admin screens.
- `bin/curate-pexels.php` CLI script to download the curated image set. Bootstraps WordPress on its own (`--wp=` to point at a specific install) and supports `--slots=`, `--theme=`, `--covers-only`, `--placeholders-only`, `--force`, `--dry-run`, and `--help`. Re-runnable and idempotent; exits non-zero if any slot fails.
- Settings → Imagery tab: store the Pexels API key (option `groove_pexels_api_key`, autoload off), remove it, and run an explicit on-demand connection test. The stored key is never rendered back into the page — only its source and a masked value are shown.
- Key resolution order: `GROOVE_PEXELS_API_KEY` in `wp-config.php`, then the `PEXELS_API_KEY` environment variable, then a gitignored `.pexels-key` file, then the WordPress option. When the constant is defined the settings field is disabled and a notice explains why.
- Image credits panel in Settings → Imagery listing each curated photo's photographer and Pexels links, plus the "Photos provided by Pexels" link required by the Pexels licence.
- Sample content for every theme. Each theme ships a `sample-content.php` the Add New screen can seed from, drawing on the shared pool of placeholder photography; previously only Groove Proposal could be seeded at all.
- Themes declare their own `default_title`, so a new folio is named after the theme it was created with — "A new issue" for Magazine, "A new proposal" for Proposal. Themes that say nothing fall back to the generic title, so a third-party theme cannot break this.
- Toast notifications (`\Groove\Toast`, `assets/js/groove-toast.js`) report the outcome of an action as a transient pill in the corner of the viewport. An inline notice reflowed the screen it landed in and stayed there long after it had been read; a toast reports and gets out of the way. Standing conditions — a missing dependency, an unreachable service — keep their inline notice, because they describe the state of the screen rather than the outcome of a click.
- Toggletips (`assets/js/groove-toggletip.js`) pin the reason a save failed, and the next step to take, to the button that was pressed. A toast alone reports a failure and then takes the explanation away with it; `\Groove\Toast::failure()` queues both, so every failure on the settings screens now names something to do about it — which slug clashed, which key to check, which field is empty.
- Settings save buttons stay inert until the form differs from what is stored (`assets/js/groove-form-state.js`), across General, Routing, Privacy, Collection Tags and the Pexels key. Pressing an inert button explains why it is not doing anything rather than doing nothing silently. The buttons are rendered live and switched off from script, so the forms still work with JavaScript off, and the inert state is `aria-disabled` rather than the `disabled` attribute, so the button keeps its place in the tab order and can still speak.
- Feedback submissions are stored locally in a private `groove_feedback` post type before delivery is attempted, so a message is never lost to a network failure or a silently discarded event. Undelivered submissions are retried hourly via WP-Cron, up to 5 attempts. The store is deliberately invisible in the admin — it is a safety net, not an inbox.

### Changed
- The Support page is now **Feedback** (`admin.php?page=groove-feedback`). Submissions are sent to StudioEN as a `feedback_submitted` PostHog event and forwarded to Slack by a PostHog destination, instead of being emailed to the site's own `admin_email` — where they never reached us.
- The feedback form adds a Type field (broken / question / idea / other), prefills the sender's email from their WordPress account, and keeps an undelivered message in a transient so a failed send doesn't lose what was typed.
- Feedback is sent regardless of the Settings → Privacy analytics opt-in — pressing Send is the consent — and the form and privacy tab both say so. Passive analytics stays anonymous and opt-in.
- Analytics now POSTs to PostHog's current `/i/v0/e/` capture endpoint, and passive events set `$process_person_profile: false` so anonymous telemetry no longer creates a person profile per site.
- Theme covers moved from PNG to the curated JPEGs, retiring 24MB of source art that included three byte-identical copies of the same 7.7MB placeholder.
- Seeded folios are always created as drafts, whatever the site's default folio status. Sample copy and stand-in photography used to go live the moment a folio was created; the draft status flows through to the seeded pages, and the analytics event reports what was actually created rather than what was intended.
- The default folio title setting can be left blank, which means "let the theme decide"; the Settings field previews what the currently selected theme would produce. An explicit setting still wins everywhere.
- A folio that cannot be shown says which of the three things went wrong — the folio is missing, it is not viewable yet (a draft opened without a session allowed to see it), or its theme is genuinely unregistered — instead of always reporting "Theme not found." and sending people hunting for a broken theme. All three still return 404, so an unpublished folio does not confirm its own existence.
- The collection filter pills follow the admin colour scheme accent like the rest of the plugin chrome, instead of a fixed legacy blue.
- A folio base slug that cannot be used in a URL is now reported instead of silently replaced with "folio". Substituting a slug behind the operator's back moved every published folio to a URL they had not asked for, and then showed the substitution back to them as if it were their own choice.

### Fixed
- Folio bulk actions run on `admin_init`, before WordPress emits the admin header. They were called from `display_content()`, by which point the header was already sent: PHP warned, the redirect silently did nothing, and the action stayed in the URL so a refresh re-submitted it.
- Saving one settings tab no longer undoes another. Every tab fell through to one of two branches in the save handler, so saving General reset the routing base slug to "folio", and saving Routing turned the usage analytics opt-in off — while never saving the slug the operator had just typed, which is what they had pressed the button for. Each tab now writes only its own options.
- Deleting a collection tag that was already gone reported success. `wp_delete_term()` returns `false` rather than a `WP_Error` for a term that does not exist, and only the error case was checked.
- Groove's accent tokens resolve on `<body>`, where WordPress publishes the admin colour scheme (`body.admin-color-*`), instead of `:root`, where several block-package stylesheets still leave a legacy `#007cba` behind. A `var()` is substituted where the property is declared, so the plugin's highlights — filter pills, theme cards — froze that stale blue and rendered a different colour from core's own primary buttons on the same screen.

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
