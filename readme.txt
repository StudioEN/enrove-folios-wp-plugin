=== Groove Folios ===
Contributors: studioenfrankw
Tags: ebook, newsletter, portfolio, publishing, documents
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 7.1
Stable tag: 0.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Write multi-page folios (ebooks, newsletters, magazines, proposals) in the block editor and publish each through a theme made for reading.

== Description ==

Groove Folios turns WordPress into a place to write and publish longer, multi-page pieces: an ebook, a newsletter issue, a magazine, a portfolio, a client proposal. A **folio** is a cover plus an ordered set of pages. You write every page in the block editor you already know, and the folio's theme decides whether it reads as a book, a newsletter or a pitch.

Folios render through their own self-contained themes, not your site's WordPress theme, so a folio looks the same whatever your site looks like, and changing your site's theme never breaks one.

= What you get =

* **Five built-in folio themes:** Folio Starter, Groove eBook, Groove Newsletter, Groove Magazine and Groove Proposal. Each has its own cover, page layout, typography and, where it helps, its own content blocks.
* **A dedicated folio editor:** add, reorder and edit pages, set the cover, logo and typefaces, and preview the result before publishing.
* **Sample content** for every theme, so a new folio starts as a finished example you can write over.
* **Password-protected folios**, with a reading gate styled to match the theme.
* **Collection tags** to organise folios, plus quick edit and duplication from the All Folios list.
* **Configurable URLs:** folios live under a base path you choose, for example `/folio/your-title/`.
* **Installable themes:** upload a folio theme as a ZIP from Groove → Themes. The plugin checks each package against the theme contract at install time and tells you what to fix. The full theme specification and a step-by-step playbook are built into the Themes screen.

= Privacy =

Groove Folios collects nothing: no analytics, no usage tracking, no telemetry, and nothing is ever sent to StudioEN. Settings → Privacy lists every request the plugin makes. There are two, and both are described under External services below.

== External services ==

This plugin uses two third-party services. Neither receives anything about your site's content or your visitors beyond what is described here.

= Google Fonts =

Folio themes set their type in fonts served by Google Fonts. When someone views a published folio, **their browser** requests the stylesheet from `fonts.googleapis.com` and the font files from `fonts.gstatic.com`. Google receives the visitor's IP address and browser details, as with any web request. This happens on published folios and folio previews only, not on the rest of your site and not in the admin.

* Terms of service: https://developers.google.com/fonts/terms
* Privacy policy: https://policies.google.com/privacy

= Pexels (sample photos) =

Theme covers and the photos in sample content come from Pexels. Their licence does not allow them to be packaged with the plugin, so the plugin does not include them. An administrator can fetch them by pressing **Download Photos** on Groove → Settings → Imagery. That sends one request per photo (46 in total, about 4 MB) from **your server** to `images.pexels.com`. Pexels receives your server's IP address and the addresses of the photos requested. Nothing else is sent, no account or API key is involved, and nothing is fetched until the button is pressed. The photos are then served from your own uploads folder.

* Pexels licence: https://www.pexels.com/license/
* Terms of service: https://www.pexels.com/terms-of-service/
* Privacy policy: https://www.pexels.com/privacy-policy/

== Installation ==

1. In your WordPress admin, go to Plugins → Add New Plugin and search for "Groove Folios", or upload the ZIP with Upload Plugin.
2. Activate the plugin.
3. Go to **Groove → Add New**, pick a theme, and tick the sample content box to start from a finished example.
4. Optional: to see the sample content with its photos, press **Download Photos** on Groove → Settings → Imagery first.

== Frequently Asked Questions ==

= Does a folio use my site's theme? =

No. A folio is rendered by its folio theme alone, as a standalone page, so it looks the same on any site. Your site's theme still controls the rest of your site.

= Why are there no photos in the sample content? =

The sample photos come from Pexels, whose licence does not allow them to be redistributed inside a plugin. Press Download Photos on Groove → Settings → Imagery and the plugin fetches this site's own copy. Folios you have already seeded pick the photos up once they have been downloaded, except for each page's featured image, which is attached only when the sample content is created.

= Can I make my own folio theme? =

Yes. A folio theme is a small folder with a manifest, a cover template, a page template and a stylesheet. The full specification and a step-by-step playbook are on Groove → Themes, under the Spec and Playbook tabs. Upload the finished ZIP from the same screen.

= Where do folios live on my site? =

Under a base path, `/folio/` by default. You can change it on Groove → Settings → Routing.

== Changelog ==

= 0.5.0 =
* Change theme in the folio editor uses the same dialog as choosing a theme for a new folio.
* Switching a folio's theme asks first when the new theme would hide blocks or proposal details, and nothing is applied until you press Switch theme.
* Removing a theme on Groove → Themes asks on a second screen of the theme's dialog, with a count of the folios that use it.
* The folio editor's header shows Preview, Save, Publish and Copy link on every tab. Save is greyed out when there is nothing to save.
* Copy link on a draft folio copies the preview link until the folio is published.
* Publishing a folio no longer clears its collection tags.
* With reduced motion on, the Add New dialog no longer blocks clicks after it closes.

= 0.4.0 =
* First release on WordPress.org.
* Sample photos are downloaded on request (Settings → Imagery) instead of being bundled.
* Published folios are served with HTTP 200 instead of 404.
* Security: headings in the eBook, Magazine and Newsletter themes can no longer inject markup.
* Sample content works on PHP 7.1 and 7.2.
* CHANGELOG.md in the plugin folder has the full history.

== Upgrade Notice ==

= 0.5.0 =
Theme switches and theme removal now ask before hiding content, and publishing a folio no longer clears its collection tags.

= 0.4.0 =
Security fix for headings in the eBook, Magazine and Newsletter themes, and published folios now return HTTP 200. Sample photos must be downloaded once from Settings → Imagery.

== Credits and licences ==

Groove Folios is licensed under the GPLv2 or later. It includes or uses:

* **eicons** icon font (5.21.0) by Elementor, distributed under the GPLv3 as part of the Elementor plugin. Some glyphs are based on Font Awesome 4.7.0, licensed under the SIL Open Font License 1.1. https://github.com/elementor/elementor-icons
* **Tailwind CSS**, compiled into assets/build, licensed under the MIT licence. https://tailwindcss.com
* **Google Fonts** typefaces, loaded from Google at view time (not bundled), each under the SIL Open Font License or Apache License 2.0.
* **Pexels photos**, fetched on request (not bundled), under the Pexels licence. Each photo is credited to its photographer on Groove → Settings → Imagery.

= Source code and build =

The compiled admin stylesheet in `assets/build/` is generated from `assets/css/tailwind.css` with Vite and Tailwind CSS. The source, `package.json` and `vite.config.js` are included in the plugin. To rebuild it, run `npm install` and then `npm run build`.
