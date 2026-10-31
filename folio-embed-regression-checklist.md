# Folio Embed Regression Checklist

Use this checklist whenever folio page rendering, block rendering, or theme page templates change.

## Scope
1. Post type: `enrove_folio_page`
2. Themes: `folio-starter`, `enrove-ebook`, `enrove-newsletter`, `enrove-magazine`, `enrove-proposal`
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

## Imported Pages (Import posts and pages)
Run when import, the converter frame, or page rendering changes. Keep a few classic-editor posts on the test site: one with headings, one with `[caption]` and `[gallery]` (one gallery naming no `ids`), one with a bare YouTube URL on its own line and `[audio]`/`[video]`, one with an unknown shortcode, and one block-editor post with a featured image.
1. From the folio editor, Add Page's chevron → Import posts and pages: pick them in a chosen order and add them. They arrive as drafts after the last page, in pick order, and the toast counts them.
2. Each classic copy is real blocks in the page editor (no Classic block), its headings show in the theme's "On this page" list, and every gallery image renders.
3. The YouTube link renders as an embed and the audio/video players show; the unknown shortcode prints as typed.
4. The block-editor post is copied unchanged, featured image included.
5. From All Folios → Add New, Import posts and pages with one post (the list replaces the themes in the same dialog; Done returns to them with the selection, Cancel without it), then Continue: the new folio takes that post's title, and with a "publish" default folio status its page is published with it.
6. With the converter frame blocked (DevTools → block `admin-post.php?action=enrove_block_converter`), an import still succeeds and the classic copy keeps its paragraphs.

## Saving as a PDF
Run per theme, in the real print window of Chrome and of one other browser, on a folio with an accordion, an embed, a Button and a table of more than a sheet.
1. Save as PDF… from the folio editor, and from All Folios, opens the folio in a new tab with the print window on top.
2. The cover fills the sheet to all four edges in Chrome and Edge, with no navigation and no Open folio button, and it appears once. In Safari it sits on one sheet inside a white margin, the contents page has no numbers, and the bar above the document names Chrome and Edge. A cover that repeats on every sheet is `position: fixed` getting through.
3. With a printer as the destination, in the print window's preview (no need to print), the cover reaches all four edges. The A4 and US Letter switch in the bar reloads on that paper, and on the wrong one the preview shows white strips.
4. Each number on the contents page matches the sheet that section starts on, and each entry links to it.
5. Accordions are open, an embed is a link, and no block is sliced by a sheet edge. A short table stays whole with the heading above it, a long one splits between rows and repeats its head, and no column breaks a word in two.
6. Text is selectable, links work, and the theme's fonts are embedded (download them first on Settings → Fonts).
7. Let readers save as PDF shows the link on the cover and page footers, and turning it off removes it.
8. Logged out, a draft or private folio's print address is a 404, and a locked folio shows the password gate, then the document.

## Cross-Checks
1. Regular WordPress post with same URLs still embeds correctly.
2. Folio cover page behavior is unchanged.
3. Password-protected folio behavior is unchanged after unlock.

## Pass/Fail Matrix
| Theme | Spotify | YouTube | X/Twitter (fallback) | Inline URL Control | Nav/Anchors | Mobile Layout | Result | Notes |
|---|---|---|---|---|---|---|---|---|
| folio-starter |  |  |  |  |  |  |  |  |
| enrove-ebook |  |  |  |  |  |  |  |  |
| enrove-newsletter |  |  |  |  |  |  |  |  |
| enrove-magazine |  |  |  |  |  |  |  |  |
| enrove-proposal |  |  |  |  |  |  |  |  |

## If a Regression Is Found
1. Capture theme ID, folio page URL, and exact provider URL.
2. Capture screenshot of rendered output.
3. Note whether URL was standalone line or inline text.
4. Record browser/device and logged-in vs logged-out state.
5. Attach PHP debug log snippet (if any) and recent plugin commit hash.
