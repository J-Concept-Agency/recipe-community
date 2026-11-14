# Hooks and filters

## Actions

### `rcom_newsletter_optin( string $email, string $name, int $post_id )`

Fires when a visitor who ticked the newsletter box confirmed their email address. The plugin's own provider listens to it; add your own listener for other services:

```php
add_action( 'rcom_newsletter_optin', function ( $email, $name, $post_id ) {
	my_esp_subscribe( $email, $name );
}, 10, 3 );
```

### `rcom_reviews_changed( int $post_id )`

After any change (new, hidden, deleted, verified). Use it to purge caches. `litespeed_purge_post` and WP Rocket's `rocket_clean_post()` are called already.

## Filters

| Filter | Purpose |
|---|---|
| `rcom_post_types` (array) | Post types that get reviews. |
| `rcom_helpful_post_types` (array) | Post types for the Helpful module. |
| `rcom_account_sections` (array) | Sections of the account page: `id => [ 'label' => ..., 'cb' => callable( $session ) returning HTML ]`. |
| `rcom_load_default_css` (bool) | Turn the default stylesheets off. See [theming](theming.md). |
| `rcom_css_classes` (string, `$type`) | Extra classes for `button`, `card`, `input`. |
| `rcom_load_assets` (bool) | Return false to skip CSS/JS on a single page. |
| `rcom_bbq_types` (array) | `key => [ form label, sentence under the review ('' = none) ]`. |
| `rcom_client_ip` (string) | IP used for the (hashed) rate limit, for sites behind a proxy: return the real visitor IP. |
| `rcom_newsletter_providers` (array) | `id => [ 'label' => ..., 'class' => 'My_Provider' ]` where the class implements `RCOM_Newsletter_Provider` (`subscribe()`, `test()`). |

## Constants (wp-config.php)

`RCOM_TURNSTILE_SITE_KEY`, `RCOM_TURNSTILE_SECRET_KEY`, `RCOM_LAPOSTA_API_KEY`, `RCOM_DISABLE_UPDATE_CHECK`, `RCOM_ALLOW_DEV_BYPASS` (development only).

## REST routes (`/wp-json/rcom/v1/`)

`GET state`, `GET reviews`, `POST reviews`, `POST delete`, `POST link`, `POST forget`. Public by design (no accounts); each checks a nonce, a signed form stamp, a signed cookie or a rate limit itself. Email addresses of others are never returned.
