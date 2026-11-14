# Changelog

All notable changes to this project are documented here.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0] - 2026-11-14

### Added
- Star reviews on recipes in an own table: optional text, first name, email (never shown), BBQ type, optional photo.
- Shortcodes `[rcom_stars]`, `[rcom_summary]`, `[rcom_form]`, `[rcom_list]`, `[rcom_reviews]` and helper `rcom_get_rating()`.
- Photo handling: real MIME check, resize to 1600 px, WebP re-encode, metadata (EXIF/GPS) removed, random file names.
- Email-link identity: verification link, signed 30-day cookie, manage-link, remove own review.
- Cloudflare Turnstile (keys in settings or wp-config.php, key test), honeypot, minimum time, rate limits.
- Admin: reviews list with filters and bulk actions, replies, photo approval, people tab, CSV export, settings.
- Newsletter opt-in with provider interface, Laposta provider and `rcom_newsletter_optin` action.
- WordPress privacy exporter and eraser, uninstall cleanup (optional keep data).
- Built-in update check against GitHub Releases, release workflow, Dutch translation.
