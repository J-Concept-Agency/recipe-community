# Security

| Area | Measure |
|---|---|
| Spam | Cloudflare Turnstile (server-side verification, fails closed), honeypot field, minimum 4 s fill-in time (signed form stamp), max 3 reviews and 5 link requests per hour per visitor. |
| Rate limit | Keyed on an HMAC of the IP address (wp salt) in a one-hour transient. The IP is never stored readable or logged; the raw user agent is never stored (only a coarse device category). Behind a proxy use the `rcom_client_ip` filter. |
| CSRF / cache | Nonce fetched fresh through REST when the form opens, so page caches cannot serve a stale one. |
| Input | Server-side validation and sanitising; every query uses `$wpdb->prepare()`; all output escaped; email never in public HTML or JSON. |
| Photos | Only JPG/PNG/WebP, max 8 MB, real type via `finfo` and `getimagesize`, dimension and pixel limits, re-encoded to WebP at max 1600 px (so EXIF/GPS and any appended code are dropped; a RIFF pass also strips EXIF/XMP chunks), random file name, stored outside the media library in `uploads/rcom/` with `index.php` and an `.htaccess` that denies script execution. Photos stay hidden until approved. |
| Identity | One-time token (48 hex chars, only its SHA-256 stored), 7-day expiry. Cookie is HMAC signed, HttpOnly, SameSite=Lax, Secure on https, 30 days. A review can only be removed with that cookie. Manage-link requests always get the same answer (no user enumeration) and one mail per address per 10 minutes. |
| Admin | Capability `manage_options` and nonces on every action; CSV export neutralises formula injection (`= + - @` cells get an apostrophe) and uses a UTF-8 BOM. |
| Updates | Only a zip hosted under `github.com/J-Concept-Agency/recipe-community/releases/download/` is accepted as package. |

## Known limits

- Email security scanners may open the confirmation link before the reader does; this verifies the address but cannot be told apart from a real click.
- Without Imagick or GD with WebP, photo upload fails with a friendly message.
- On servers that ignore `.htaccess` (nginx) block script execution in `uploads/rcom/` in the server config.

Report vulnerabilities privately, see [SECURITY.md](../SECURITY.md).

## Helpful votes
Signed per-article cookie, honeypot, minimum time, rate limits, nonce via REST, text limited to 1000 characters with HTML stripped, links rejected by default, identical texts de-duplicated. Votes never use a captcha; Turnstile for the feedback text is optional.

## Data robustness

Persistent data is only in the plugin's own tables. Transients hold temporary counters only; post meta holds rebuildable caches. A light integrity check (plugin screens and a daily wp-cron job `rcom_daily`) reports missing tables. See the FAQ for safe and unsafe cleanup tools. When a recipe is deleted for good the reviews and photos are deleted, or kept hidden (status `orphan`) when so configured.

## Account page (`[rcom_account]`)

Passwordless, no WordPress accounts. Design and what was tested:

| Topic | Design |
|---|---|
| Login link | Single use, 256-bit random, only its SHA-256 stored in `rcom_tokens`, valid 15 minutes, bound to one identity (a keyed hash of the email address). Compared through the database hash lookup and `hash_equals`. The confirmation link of a new review (7 days) is a separate thing. |
| Mail scanners | A GET on the link never consumes anything. It shows a small page with a **Log in** button; only the POST (with an HMAC nonce bound to the token) consumes the token and starts a session. The landing page sends `Referrer-Policy: no-referrer`, `Cache-Control: private, no-store`, `X-Robots-Tag: noindex`, and after the POST the browser is redirected (303) to the clean account URL without the token. |
| No user enumeration | `login-request` always answers the same text and takes about the same time (the mail is sent after the response when `fastcgi_finish_request` exists, otherwise the call is padded to a fixed duration). Mail goes out only for addresses that have reviews or votes. |
| Sessions | Server side in `rcom_sessions`: random 256-bit id, only its hash stored with the identity hash, created/login/last-seen/expiry (30 days). No user agent, no IP. A new id is issued at every login (rotation, an id offered by the client is never accepted: session fixation). Cookie `rcom_s` (`__Host-rcom_s` on https): HttpOnly, SameSite=Lax, Secure on https, path `/`. "Log out on all devices" and the admin button in People remove all sessions of an identity. A daily cron job removes expired sessions and tokens. |
| Authorisation | Every action checks server side that the object belongs to the session identity (reviews and votes are looked up with `identity_id`). "Not yours" and "does not exist" give the same 404 (no IDOR probing). Ids in the UI are plain row ids, but they only work together with the owning session. |
| CSRF | Every state-changing REST route needs a session-bound nonce in the `X-RCOM-Nonce` header (HMAC of the session id), plus SameSite=Lax. There are no state-changing GET routes. User routes (session) are separate from admin actions (capability + WordPress nonce). |
| Re-authentication | "Download my data", "Delete everything" and the newsletter unsubscribe need a login younger than 15 minutes; otherwise the route answers `stale` and the page offers a fresh link ("reauth", rate limited). |
| Rate limits and Turnstile | Login requests: Turnstile, 6 per hour per hashed IP, and per hashed email 3 per hour and one per 2 minutes. Landing POST and account actions have their own per-IP limits. Counters are HMAC hashes in transients; IP and email are never stored readable or logged. |
| Audit log | `rcom_audit`: identity hash, event type and time only (link requested/used, session created, sessions revoked, review deleted, data exported, newsletter off, data deleted). Kept 90 days, visible to administrators under People. No IP, no email. |
| Output | Everything escaped, no inline scripts, the page carries no-store and noindex headers and `DONOTCACHEPAGE`; the account page and `/wp-json/rcom/v1/` must be excluded from page caches. No full email address in HTML or REST output (masked `a***@example.test`). |

Tests run (all passed): enumeration (same answer, comparable time, mail only for a known address), scanner GET (twice, token unused), wrong nonce, token reuse, expired token, cookie flags, session fixation, only hashes in the database, CSRF without or with a wrong nonce, IDOR on reviews (404, record intact), votes with an injected id, stale re-authentication for export/delete/unsubscribe, delete-all without confirmation, logout everywhere, rate limit (429 after 6), missing Turnstile, wrong nonce, SQL injection in the email, XSS (quotes and tags in a review text), audit table without email or IP columns.

Residual risk (inherent to magic links): whoever takes over a person's mailbox can log in as that person. Mitigations: 15 minute single-use links, re-authentication for destructive actions, "log out everywhere", administrators can end sessions, and a security log.
