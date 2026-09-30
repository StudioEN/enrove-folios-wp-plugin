# Folio Embed Regression Checklist

Use this checklist whenever folio page rendering, block rendering, or theme page templates change.

## Scope
1. Post type: `groove_folio_page`
2. Themes: `folio-starter`, `groove-ebook`, `groove-newsletter`, `groove-magazine`, `groove-proposal`
3. Embed providers: Spotify (episode/track/playlist), YouTube (watch URL), X/Twitter (post URL)

## Preconditions
1. Permalinks are enabled (not plain query-only URLs).
2. At least one published folio exists with at least one published page.
3. You have one URL for each provider above.
4. Test both logged-in admin view and logged-out/public view.

## Quick Test Data
1. Spotify episode URL on its own line (example):
`https://open.spotify.com/episode/6aWnkehMR2rEIF3Uwqb0hp?si=e93PAWLOTc2RbnvgLcSq9A`
2. YouTube watch URL on its own line (example format):
`https://www.youtube.com/watch?v=dQw4w9WgXcQ`
3. X/Twitter post URL on its own line (example format):
`https://x.com/jack/status/20`

## Core Regression Steps (Run Per Theme)
1. Set folio theme.
2. Edit a folio page and add one embed URL per provider, each on its own line in paragraph blocks.
3. Add one control URL that is not on its own line (inside sentence text).
4. Publish or update page.
5. Open the public folio page URL.
6. Confirm each standalone URL renders as embedded content (not raw URL text); for X/Twitter, as the fallback blockquote (see Rendering Acceptance Criteria 3).
7. Confirm the inline/in-sentence control URL remains a normal link/text (no forced embed).
8. Confirm page title, nav, previous/next links, and "On this page" anchors still work.
9. Confirm no PHP warnings/notices in debug log for page load.

## Rendering Acceptance Criteria
1. No raw standalone Spotify/YouTube/X URL text is visible after render.
2. Spotify and YouTube embeds are visible and interactive (play/open actions available).
3. X/Twitter (and Instagram, TikTok, or any embed that needs its provider's script) renders as the provider's fallback: the quoted post text and a link to it, with no widget. That is expected. Page content is echoed through `wp_kses()`, which keeps no `<script>`, so the provider's script never loads. A live X widget on a folio page is a regression: it means something is printing content around the escape.
4. Embed width is contained within content column on desktop and mobile.
5. No layout breakage (overflow/cutoff/horizontal scroll).
6. Existing headings still have anchor IDs and sidebar/mobile heading navigation still works.
7. No script or CSS text shows on the page where a Custom HTML block held a `<script>` or `<style>`.

## Cross-Checks
1. Regular WordPress post with same URLs still embeds correctly.
2. Folio cover page behavior is unchanged.
3. Password-protected folio behavior is unchanged after unlock.

## Pass/Fail Matrix
| Theme | Spotify | YouTube | X/Twitter (fallback) | Inline URL Control | Nav/Anchors | Mobile Layout | Result | Notes |
|---|---|---|---|---|---|---|---|---|
| folio-starter |  |  |  |  |  |  |  |  |
| groove-ebook |  |  |  |  |  |  |  |  |
| groove-newsletter |  |  |  |  |  |  |  |  |
| groove-magazine |  |  |  |  |  |  |  |  |
| groove-proposal |  |  |  |  |  |  |  |  |

## If a Regression Is Found
1. Capture theme ID, folio page URL, and exact provider URL.
2. Capture screenshot of rendered output.
3. Note whether URL was standalone line or inline text.
4. Record browser/device and logged-in vs logged-out state.
5. Attach PHP debug log snippet (if any) and recent plugin commit hash.
