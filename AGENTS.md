# AGENTS.md: Recipe Community

WordPress plugin (PHP 8.0+, no Composer, no build step, no jQuery). Prefix `rcom_` / class prefix `RCOM_`. Text domain `recipe-community`. English source strings, Dutch `.po/.mo`.

## Layout
`includes/class-reviews.php` (table `{prefix}rcom_reviews`, cached rating in post meta `_rcom_avg/_rcom_count/_rcom_vcount/_rcom_dist`), `class-helpful.php` (tables `rcom_votes`, `rcom_feedback`, meta `_rcom_helpful_yes/no`), `class-identity.php` (email link tokens, signed cookie `rcom_id`, rate limits), `class-photo.php`, `class-turnstile.php`, `class-newsletter.php` + `class-laposta.php`, `class-front.php` (shortcodes), `class-rest.php` (namespace `rcom/v1`), `class-device.php`, `class-stats.php`, `class-abilities.php`, `class-updater.php`, `admin/`.

## Shortcodes
`[rcom_stars]`, `[rcom_summary]`, `[rcom_form]`, `[rcom_list per_page="3"]`, `[rcom_reviews heading="0"]`, `[rcom_helpful]`. Helper: `rcom_get_rating( $post_id )`.

Account: `class-account.php` (tables `rcom_sessions`, `rcom_tokens`, `rcom_audit`, shortcodes `[rcom_account]`, `[rcom_account_link]`, REST `rcom/v1/account/*`). Never consume a login token on GET; every account action must check object ownership by `identity_id`.

## Hooks
Action `rcom_newsletter_optin( $email, $name, $post_id )` (confirmed opt-in), `rcom_reviews_changed( $post_id )`. Filters `rcom_post_types`, `rcom_helpful_post_types`, `rcom_load_assets`, `rcom_bbq_types`, `rcom_client_ip`, `rcom_newsletter_providers`.

## Rules
- Never log, print, commit or send email addresses or IPs. Rate limits use an HMAC of the IP in a transient.
- Never store a raw user agent: only a coarse device category (`RCOM_Device`).
- No real keys in code or repo. Turnstile/Laposta keys live in options or wp-config constants.
- Every query through `$wpdb->prepare()`, all output escaped, every REST route and admin action checks nonce/capability.
- Assets load only where a shortcode renders. No queries on pages without output.
- Reviews are live at once; photos wait for approval; only verified reviews count in `avg`.
- Do not add remotes or push from automated sessions.

## Test quickly
`php -l` on all files; run the flows listed in `docs/development.md`; intercept mail with `pre_wp_mail`, block HTTP with `pre_http_request`.
