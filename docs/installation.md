# Installation

## Requirements

- WordPress 6.2 or newer, PHP 8.0 or newer.
- GD or Imagick with WebP support if you want photo uploads (check Tools > Site Health).
- A way to send email (the plugin uses `wp_mail()`; an SMTP plugin is recommended so confirmation emails arrive).
- A free Cloudflare account for Turnstile ([configuration](configuration.md)).

## Install

1. Download `recipe-community.zip` from the latest [GitHub release](https://github.com/J-Concept-Agency/recipe-community/releases).
2. WordPress admin: Plugins > Add New > Upload Plugin, choose the zip, Install, Activate.
   (Or unzip into `wp-content/plugins/recipe-community/`.)
3. Activation creates the table `{prefix}rcom_reviews` and the folder `wp-content/uploads/rcom/` (with an `index.php` and an `.htaccess` that blocks script execution).
4. Add Turnstile keys, then place the shortcodes ([usage](usage.md)).

## Updates

WordPress shows updates under Dashboard > Updates once a new GitHub release exists. See [development.md](development.md#updates) for details and how to switch the check off.

## Uninstall

Deleting the plugin removes the table, settings, cached rating meta, transients and the photo folder, unless "Keep data on uninstall" is ticked in the settings.

## Page caches

The form fetches a fresh nonce through the REST API when it opens, so full-page caching works. Make sure `/wp-json/rcom/v1/` is not cached by your CDN. The review list in the cached HTML updates when a review changes (a purge hook for LiteSpeed Cache and WP Rocket is fired; other caches can listen to `rcom_reviews_changed`).
