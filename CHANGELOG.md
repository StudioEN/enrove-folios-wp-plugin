# Changelog

## [2026-02-25]

### Added
- Added Folio primary typeface customization in the Folio setup page with Google Font options: Roboto, Open Sans, Noto Sans, Inter, Montserrat, Poppins, Lato, Nunito Sans, and DM Sans.
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
