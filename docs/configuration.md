# Configuration

Everything is under **Recipe community > Settings**.

## Cloudflare Turnstile (required)

The review form is **not shown to visitors** until both keys are present, because the form must never run without a captcha on a live site. Administrators see a notice in its place.

### Create the keys

1. Log in at the Cloudflare dashboard and open **Turnstile** (left menu).
2. Click **Add widget**.
3. Name: anything, for example `Recipe reviews`.
4. **Hostnames**: add your site's domain (for example `example.com`). Add a staging domain too if you test there.
5. **Widget mode**: choose **Managed**.
6. Create. Copy the **Site key** (public) and the **Secret key** (private).

### Paste the keys

Option A, in the plugin: paste both into Settings > Spam protection and save. The secret is shown masked afterwards. Click **Test my keys**: the server asks Cloudflare to verify a dummy token. "Accepted" means the secret is valid. The site key cannot be verified from the server, so open a recipe page and check that the widget appears.

Option B, in `wp-config.php` (takes precedence over the database):

```php
define( 'RCOM_TURNSTILE_SITE_KEY', '0x...' );
define( 'RCOM_TURNSTILE_SECRET_KEY', '0x...' );
```

### Local development

Cloudflare publishes test keys that always pass. They are only used when you add `define( 'RCOM_ALLOW_DEV_BYPASS', true );` to `wp-config.php` of a development site (never on production) and no real keys are set.

## Newsletter

Choose a service. Only visitors who tick the (unticked by default) newsletter box **and** click the confirmation link are sent on.

Laposta:

- API key: paste it, or `define( 'RCOM_LAPOSTA_API_KEY', '...' );`. Shown masked.
- List: a dropdown appears once a valid key is saved (or type the list ID).
- First name field: optional name of a Laposta custom field.
- Confirmation: "Our email link confirms it" (default, the visitor receives one email; the member is added with `ignore_doubleoptin`) or "Laposta sends its own confirmation email".
- **Test Laposta connection** only reads your lists, it never creates a member.
- If another plugin on the site already holds a Laposta connection that can be read (Bricks Laposta or a `LAPOSTA_API_KEY` constant), the button **Use existing Laposta settings** copies key and list once into these settings.

Other services: use the `rcom_newsletter_optin` action or add a provider ([hooks](hooks-and-filters.md)).

## General

- **Label for your reply**: heading above your reply (default "Reply from the author").
- **Sender email**: address used for the confirmation emails (default: the WordPress admin address). Use an address on your own domain with SPF/DKIM set up.
- **Post types**: comma separated slugs that get reviews (default `recipe,recept`).
- **Credit link**: small "Powered by J-Concept" link below the reviews. On by default, you can switch it off ([licensing](licensing.md)).
- **Updates**: switch the GitHub update check off.
- **Keep data on uninstall**.

## Reviews admin

Recipe community > Reviews: filter, search, bulk hide/show/approve photo/delete, open a review to write a reply or handle its photo. The menu badge counts photos waiting for approval. The People tab lists unique email addresses. **Export CSV** gives a semicolon-separated UTF-8 file for Excel.
