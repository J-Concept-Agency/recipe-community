# Development

## Structure

```
recipe-community.php        header, constants, bootstrap, rcom_get_rating()
includes/class-settings.php options and wp-config constants
includes/class-reviews.php  reviews domain: table, CRUD, cached stats
includes/class-photo.php    photo validation and processing
includes/class-identity.php tokens, signed cookie, rate limit, mails
includes/class-turnstile.php captcha verification
includes/class-newsletter.php provider interface + dispatcher
includes/class-laposta.php  Laposta provider
includes/class-front.php    shortcodes, assets
includes/class-rest.php     REST routes
includes/class-privacy.php  WP privacy exporter/eraser
includes/class-updater.php  GitHub release updater
admin/                      list table and admin screens
assets/                     rcom.css, rcom.js (no build step)
languages/                  .pot, nl_NL .po and compiled .mo
```

New domains (accounts, favourites) get their own classes next to `class-reviews.php`.

## Translations

English source strings, text domain `recipe-community`.

```
xgettext --language=PHP --keyword=__ --keyword=esc_html__ --keyword=esc_attr__ --keyword=esc_html_e --keyword=_n:1,2 \
  --add-comments=translators -o languages/recipe-community.pot recipe-community.php includes/*.php admin/*.php
msgfmt -o languages/recipe-community-nl_NL.mo languages/recipe-community-nl_NL.po
```

The compiled `.mo` is committed (no build step on installs).

Check that every source string has a Dutch translation (also run by the release workflow, which fails otherwise):

```
./bin/check-translations.sh
```

It re-extracts the strings, compares them with `languages/recipe-community-nl_NL.po` (`msgcmp`) and reports missing, untranslated and fuzzy entries.

## Tests

There is no test framework dependency. Before a release check on a local site: `php -l` on all files, then run the flow in a browser (submit, mail link, verify, remove, reply, photo approve, CSV, delete recipe), and the security cases from [security.md](security.md): missing/bad nonce, honeypot, too-fast submit, rate limit, oversized and disguised files, EXIF removal, XSS and SQL strings, CSV formula cells. Intercept mail with a `pre_wp_mail` filter and block external calls with `pre_http_request`.

## Updates

`RCOM_Updater` asks `https://api.github.com/repos/J-Concept-Agency/recipe-community/releases/latest` (cached 12 hours, 1 hour after a failure or 404), compares the tag with `RCOM_VERSION`, and feeds the `pre_set_site_transient_update_plugins` and `plugins_api` filters. The package must be a `.zip` release asset of that repository. Nothing about the site is sent (fixed user agent). Disable with `RCOM_DISABLE_UPDATE_CHECK` or the setting. Test without GitHub by filtering `pre_http_request` to return a fake response.

## Making a release

1. Update the version in the plugin header (`Version:`) and `RCOM_VERSION`.
2. Move the `[Unreleased]` notes in `CHANGELOG.md` to a new `## [x.y.z] - date` section (Keep a Changelog, SemVer).
3. Commit, then tag and push: `git tag vX.Y.Z && git push origin main vX.Y.Z`.
4. The workflow `.github/workflows/release.yml` checks that tag and header agree, checks translations, builds `recipe-community.zip` (JS and CSS minified in the release copy only) (folder `recipe-community/`, without dev files such as `docs/`, `.github/`, `.po`), and creates the release with the changelog section as notes.
5. Sites pick it up within 12 hours (or clear the `rcom_update` transient).
