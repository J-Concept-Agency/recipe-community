# Changelog

All notable changes to this project are documented here.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.2.0] - 2026-11-14

### Added
- Reviewer names are as large as the owner reply label by default (`--rcom-name-size`, shared by both).
- Settings > Appearance: Style (Neutral, Core Framework, None) with a bundled `assets/skins/core-framework.css`, and an Extra CSS field (max 20 KB, sanitised, printed after the plugin CSS only where the plugin renders). Replaces the `rcom_skin_active` filter, which is removed.
- Markup efficiency: stars are one element drawn by a CSS mask, one `rcom-i18n` JSON block for all JS strings, `data-post` once per section, distribution bars use `--w`, no SVG sprite or wrapper elements. 3 reviews: 10,247 to 6,074 bytes (gzip 2,182 to 1,774), 206 to 126 DOM nodes; 20 reviews: 27,140 to 13,167 bytes (gzip 2,536 to 1,981), 579 to 261 nodes.
- Review list as `ul.rcom-items > li.rcom-item > article.rcom-review > div.rcom-head` with `<time datetime>`, also in the load-more REST output and the account page; `docs/accessibility.md`.
- `bin/check-rendered.php` (run by `bin/check-translations.sh` when WP-CLI is available): renders the shortcodes in nl_NL and en_US and fails on strings in the wrong language.
- Theming: default style rebuilt on `--rcom-*` tokens with neutral fallbacks (no site-specific values), filters `rcom_load_default_css`, `rcom_css_classes`, `rcom_skin_active`, Settings > Appearance, `docs/theming.md`.
- Demo data (admin button and `wp rcom demo add|clear`), flagged `is_demo`, shown on the page only; Dutch or English texts by locale.
- Summary block full width with count per star; consistent label hints; full-width photo button; review header with larger stars and body-font names; owner reply as a compact tinted block.
- Account page settings: grouped "Create the page" field with URL preview.
- Account page `[rcom_account]` ("Pitmaster"): passwordless login by emailed single-use link (mail-scanner safe landing page, 15 minutes), server-side sessions with rotation, "My reviews / My votes / My data", change or remove votes, delete reviews, download data, newsletter unsubscribe (Laposta), log out everywhere, delete everything; re-authentication for sensitive actions; security log (90 days); admin can end sessions under People.
- Settings: choose or create the account page (slug check, existing page detection), editable name of the account, `[rcom_account_link]`.
- `rank_math/frontend/canonical` plus a duplicate-free own canonical on `?rcom_page=N` variants.
- Credit link spacing and clearer settings label ("Show Powered by J-Concept below the reviews and below Helpful?").

### Fixed
- Review form: an unclosed element after the photo field put the checkboxes in the two-column row.

## [0.1.0] - 2026-11-14

### Added
- Photo field is a proper button ("Add photo" / "Change photo") with client-side preview, size and type check (the server stays the real check).
- Reviews load 10 more per click (cursor based REST, focus and aria-live, no-JS fallback `?rcom_page=N`); compact review summary with fixed-width bars.
- Terms snapshots: a text copy per terms version in its own table, viewable from a review ("View text"); the version is the last-modified date of the terms page.
- Integrity check (screens and daily cron), "Repair tables" and "Recalculate cache"; option to keep reviews hidden when a recipe is deleted for good; filter "Recipe deleted".
- Shared toolbar and footer on every list, one empty-state component, consistent selects with chevron, 36px controls.
- Small admin thumbnails for photos; extra database indexes; `bin/check-translations.sh` (also run by the release workflow, which also minifies JS and CSS in the release copy).
- Terms link only on the word "terms", privacy line under the review button.
- Star reviews on recipes in an own table: optional text, first name, email (never shown), BBQ type, optional photo.
- Shortcodes `[rcom_stars]`, `[rcom_summary]`, `[rcom_form]`, `[rcom_list]`, `[rcom_reviews]` and helper `rcom_get_rating()`.
- Photo handling: real MIME check, resize to 1600 px, WebP re-encode, metadata (EXIF/GPS) removed, random file names.
- Email-link identity: verification link, signed 30-day cookie, manage-link, remove own review.
- Cloudflare Turnstile (keys in settings or wp-config.php, key test), honeypot, minimum time, rate limits.
- Admin: reviews list with filters and bulk actions, replies, photo approval, people tab, CSV export, settings.
- Newsletter opt-in with provider interface, Laposta provider and `rcom_newsletter_optin` action.
- WordPress privacy exporter and eraser, uninstall cleanup (optional keep data).
- "Was this helpful?" module (`[rcom_helpful]`): anonymous votes, optional feedback text, optional link to a confirmed identity, admin screen and Dashboard block.
- Terms checkbox (required) with terms/privacy page, version and timestamp stored per review.
- Coarse device category (no raw user agent) with aggregated Dashboard charts.
- Admin: Community menu with Dashboard, Reviews, By recipe, Helpful, People, Settings, About; compact reviews table; per-post-type activation; recipe metabox switch.
- Laposta: connect, list dropdown with member counts, first-name field suggestion, one-email confirmation option, connection test.
- Optional WordPress Abilities (off by default), `AGENTS.md`, `llms.txt` and a Claude skill.
- Built-in update check against GitHub Releases, release workflow, Dutch translation.
