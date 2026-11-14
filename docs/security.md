# Security

| Area | Measure |
|---|---|
| Spam | Cloudflare Turnstile (server-side verification, fails closed), honeypot field, minimum 4 s fill-in time (signed form stamp), max 3 reviews and 5 link requests per hour per visitor. |
| Rate limit | Keyed on an HMAC of the IP address (wp salt) in a one-hour transient. The IP is never stored readable or logged. Behind a proxy use the `rcom_client_ip` filter. |
| CSRF / cache | Nonce fetched fresh through REST when the form opens, so page caches cannot serve a stale one. |
| Input | Server-side validation and sanitising; every query uses `$wpdb->prepare()`; all output escaped; email never in public HTML or JSON. |
| Photos | Only JPG/PNG/WebP, max 8 MB, real type via `finfo` and `getimagesize`, dimension and pixel limits, re-encoded to WebP at max 1600 px (so EXIF/GPS and any appended code are dropped; a RIFF pass also strips EXIF/XMP chunks), random file name, stored outside the media library in `uploads/rcom/` with `index.php` and an `.htaccess` that denies script execution. Photos stay hidden until approved. |
| Identity | One-time token (48 hex chars, only its SHA-256 stored), 7-day expiry. Cookie is HMAC signed, HttpOnly, SameSite=Lax, Secure on https, 30 days. A review can only be removed with that cookie. Manage-link requests always get the same answer (no user enumeration) and one mail per address per 10 minutes. |
| Admin | Capability `manage_options` and nonces on every action; CSV export neutralises formula injection (`= + - @` cells get an apostrophe) and uses a UTF-8 BOM. |
| Updates | Only a zip hosted under `github.com/J-Concept/recipe-community/releases/download/` is accepted as package. |

## Known limits

- Email security scanners may open the confirmation link before the reader does; this verifies the address but cannot be told apart from a real click.
- Without Imagick or GD with WebP, photo upload fails with a friendly message.
- On servers that ignore `.htaccess` (nginx) block script execution in `uploads/rcom/` in the server config.

Report vulnerabilities privately, see [SECURITY.md](../SECURITY.md).
