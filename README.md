# Recipe Community

Light, fast and safe community features for recipe sites, by [J-Concept](https://jconcept.nl).

Version 0.1 ships **star reviews** and a **"Was this helpful?"** module with optional text, photo and a reply from the site owner. No WordPress comments, no visitor accounts, no jQuery, no build step. Accounts and favourites are on the roadmap; the code is split per domain (reviews, identity, photos, newsletter, admin) so they can be added later.

## Highlights

- Own database table, cached rating per recipe in post meta (`rcom_get_rating()` for later schema work).
- CSS and JS load only on a single recipe page (about 5 KB each, no dependencies).
- Cloudflare Turnstile, honeypot, minimum fill-in time, hashed-IP rate limit, cache-safe nonce.
- Reviews are live immediately. Photos wait for approval. Reviewers confirm through a one-time email link (no account).
- Newsletter opt-in (separate, unticked box, double opt-in) with a Laposta provider and a generic hook.
- Privacy tools (WordPress export/erase), CSV export, Dutch translation included.

Requires WordPress 6.2+ and PHP 8.0+ (GD or Imagick with WebP support for photos).

## Quick start

1. Install the plugin zip and activate it ([docs/installation.md](docs/installation.md)).
2. Add your Cloudflare Turnstile keys under **Recipe community > Settings** ([docs/configuration.md](docs/configuration.md)). Without keys the form stays hidden.
3. Put the shortcodes on your recipe template ([docs/usage.md](docs/usage.md)):

```
[rcom_stars]     [rcom_reviews]
```

## Documentation

| | |
|---|---|
| [Installation](docs/installation.md) | [Configuration](docs/configuration.md) |
| [Usage and shortcodes](docs/usage.md) | [Hooks and filters](docs/hooks-and-filters.md) |
| [Security](docs/security.md) | [Privacy (GDPR)](docs/privacy.md) |
| [Development and releases](docs/development.md) | [FAQ](docs/faq.md) |
| [Theming](docs/theming.md) | [Licensing](docs/licensing.md) |
| [AI assistants](docs/ai.md) | |
| [Changelog](CHANGELOG.md) | |

## Updates

The plugin checks the GitHub Releases of `J-Concept-Agency/recipe-community` (every 12 hours, plain request, nothing about your site is sent). Disable with `define( 'RCOM_DISABLE_UPDATE_CHECK', true );` or in the settings. The header also carries `GitHub Plugin URI` for Git Updater users (use one updater, not both).

## Roadmap

- Favourites and optional visitor accounts (the "community" part).
- Recipe schema (`aggregateRating`) built on `rcom_get_rating()`.
- More newsletter providers through the provider interface.

## License

GPL-2.0-or-later. Copyright (C) 2026 J-Concept. See [LICENSE](LICENSE), [NOTICE](NOTICE) and [docs/licensing.md](docs/licensing.md).

Recipe Community by [J-Concept](https://jconcept.nl).
