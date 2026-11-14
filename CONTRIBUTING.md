# Contributing

Issues and pull requests are welcome.

- Keep it light: no jQuery, no libraries, no build step. PHP 8.0+, WordPress coding style (tabs).
- All user-facing strings in English with the `recipe-community` text domain; update the Dutch `.po` and rebuild the `.mo` (`msgfmt`).
- All queries through `$wpdb->prepare()`, all output escaped, every REST route and admin action checks its nonce or capability.
- Describe user-visible changes in `CHANGELOG.md` under `[Unreleased]`.
- Report security issues privately, see [SECURITY.md](SECURITY.md).

See [docs/development.md](docs/development.md) for structure and tests.
