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

Option B, in `wp-config.php` (takes precedence over the database). Put the lines above the line `/* That's all, stop editing! Happy publishing. */`:

```php
define( 'RCOM_TURNSTILE_SITE_KEY', '0x...' );
define( 'RCOM_TURNSTILE_SECRET_KEY', '0x...' );
```

### Read order (fill in once)

Each key is read in this order and the first one that is set wins:

1. the `RCOM_TURNSTILE_SITE_KEY` / `RCOM_TURNSTILE_SECRET_KEY` constants in `wp-config.php`;
2. the keys saved in this plugin (Settings > Spam protection);
3. if the Bricks theme is active: the Turnstile keys in Bricks > Settings > API keys (`apiKeyTurnstile`, `apiSecretKeyTurnstile` in the option `bricks_global_settings`). They are only read, never copied; Settings shows "Taken from the Bricks settings (read only)" and the last 4 characters. **Test my keys** tests these keys too. Fill in your own key here to override the Bricks one.

Without Bricks nothing changes. The secret key is never printed in HTML or REST output.

### Local development

Cloudflare publishes test keys that always pass. They are only used when you add `define( 'RCOM_ALLOW_DEV_BYPASS', true );` to `wp-config.php` of a development site (never on production) and no real keys are set.

## Terms and consent

Posting a review requires ticking "I agree to the terms". Choose the terms page (and optionally a privacy page) under Settings > Terms and consent; the checkbox links there. The moment, the page and the version are stored per review; the version is automatic: the last-modified date of the terms page (every edit counts, keep post revisions enabled so the applicable text can be found; the review detail links to the matching revision). The plugin does not supply terms text.

## Account page

Visitors manage their reviews, votes and data on one page with the shortcode `[rcom_account]` (WordPress does not get user accounts).

1. Settings > Account page: choose an existing page, or enter a slug (default `pitmaster`) and a title (default "Pitmaster") and press **Create page**. The slug is checked against posts of every type, terms, post type archives and core paths; if it is taken a free alternative is suggested. A page that already contains the shortcode is chosen instead of creating a second one.
2. "Name of the account" (default "Pitmaster") is used in link texts and mails ("Go to your Pitmaster page"). `[rcom_account_link]` prints a small link to the page, for the footer or a menu. Without a chosen page there are no links to it anywhere.
3. All links use the page's permalink, so renaming the slug later keeps everything working.
4. The page is sent with `no-store` and `noindex`. Exclude it and `/wp-json/rcom/v1/` from page caches. Turnstile keys are required (login form).

## Helpful? (was this article helpful)

Choose the post types, the count threshold, the two follow-up questions, and whether the feedback text needs Turnstile and may contain links. Place `[rcom_helpful]` on the article template.

## Newsletter

Choose a service. Only visitors who tick the (unticked by default) newsletter box **and** click the confirmation link are sent on.

Laposta:

- API key: paste it, or set it in `wp-config.php`, above the line `/* That's all, stop editing! Happy publishing. */`:

  ```php
  define( 'RCOM_LAPOSTA_API_KEY', 'your-key' );
  ```

  The constant takes precedence over the saved key and the field shows the constant's name instead of an input. Shown masked.
- List: a dropdown appears once a valid key is saved (or type the list ID).
- First name field: optional name of a Laposta custom field.
- Flow: paste the key, press Connect (read-only check), pick a list (the dropdown shows member counts), the first-name field is suggested from the list's fields.
- Confirmation: "Our email link confirms it" (default, the visitor receives one email; the member is added with `ignore_doubleoptin`) or "Laposta sends its own confirmation email".
- **Test Laposta connection** only reads your lists, it never creates a member.
- Read order of the key, first one set wins: 1) `RCOM_LAPOSTA_API_KEY`, 2) `LAPOSTA_API_KEY` (also read by Bricks Laposta, so **one `define( 'LAPOSTA_API_KEY', 'your-key' );` serves both plugins**), 3) the key saved in this plugin, 4) the key of Bricks Laposta when that plugin is active (read live through `Bricks_Laposta::get_api_key()`, not copied). Settings shows where the key comes from and its last 4 characters, never the whole key. When the key comes from Bricks Laposta, the button **Copy to this plugin** saves a copy for those who want to switch Bricks Laposta off later.
- List: if you choose none here and Bricks Laposta has a list, that list is used (read live, not copied; Settings shows "Taken from Bricks Laposta"). Choose a list here to use another one.
- **Do not send the Laposta notification email**: sends `suppress_email_notification` with every sign-up. Laposta can email the list owner for each sign-up through the API; this stops that email for sign-ups from this site (Laposta API documentation, member options). Off by default.

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
