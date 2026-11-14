# Privacy (GDPR)

This text is a starting point for your privacy statement; it is not legal advice.

## What is stored

Per review, in the table `{prefix}rcom_reviews`:

- First name, rating, optional text, optional BBQ type, date.
- Email address (needed to send the confirmation link and to recognise the author; **never shown** publicly).
- Optional photo (re-encoded, metadata removed), approval status.
- Whether the newsletter box was ticked and whether the address was confirmed.
- A hash of the one-time token until it is used.
- Your reply, if any.

Not stored: IP addresses or user agents. For rate limiting an HMAC hash of the IP is kept in a transient for one hour.

A signed cookie `rcom_id` (30 days, HttpOnly) is set only after the visitor clicked the email link. It lets the visitor see their own review and remove it.

## Why

Showing reviews (legitimate interest / consent by posting), confirming the author's address, and, only if ticked and confirmed, the newsletter (consent, double opt-in). A review never depends on the newsletter.

## Third parties

- Cloudflare Turnstile: the visitor's browser loads the Turnstile script when the form comes into view; Cloudflare processes technical data to detect bots. Mention it in your privacy statement.
- Laposta (if enabled): the confirmed email address and first name.
- Your mail server or SMTP service sends the confirmation emails.

## Retention and removal

Data is kept until the author or an administrator deletes the review, or the recipe is permanently deleted (reviews and photos go with it). Authors remove their own review with the cookie/manage-link. WordPress Tools > Export/Erase Personal Data works by email address (reviews, photos included). Deleting the plugin removes everything unless "keep data" is set.

Newsletter unsubscription is handled in your newsletter service.
