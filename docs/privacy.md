# Privacy (GDPR)

This text is a starting point for your privacy statement; it is not legal advice.

## What is stored

**Reviews** (table `{prefix}rcom_reviews`): first name, rating, optional text, optional BBQ type, date, email address (to send the confirmation link and recognise the author; **never shown**), optional photo (re-encoded, metadata removed, approval status), whether the newsletter box was ticked and confirmed, the moment the terms box was ticked (`terms_accepted_at`), the terms page id and the terms version (the last-modified date of that page at that moment; every edit of the terms page, even a typo fix, counts as a new version, so keep post revisions enabled to be able to look up the text that applied), your reply, and a hash of the one-time token until it is used.

**"Was this helpful?" votes** (tables `rcom_votes`, `rcom_feedback`): the article, Yes/No, the optional feedback text, date. Votes are anonymous. If the visitor is identified through a review confirmation link, the vote is linked to a keyed hash of that identity (never the email itself) so it can be changed or removed; the feedback text then belongs to that identity. Anonymous voters stay anonymous. A signed cookie per article (30 days) remembers the choice on the device. Anonymous visitors can vote again after the cookie has expired (known limitation).

**Device category**: for reviews and votes only one coarse category is kept (`iphone`, `ipad`, `android_phone`, `android_tablet`, `windows`, `mac`, `linux`, `other`). The User-Agent and Client Hints are read in memory during the request and discarded; the raw User-Agent and any version or browser detail are **never stored**. Bots are not stored. The category is shown only as aggregated counts on the Dashboard, never next to a name or email, and is not part of the People export. Limit: an iPad in desktop mode reports itself as a Mac and is counted as Mac. Conversion per device (relative to page views) is not built; combine with GA4 if you need it.

**Not stored**: IP addresses, raw user agents. For rate limiting an HMAC of the IP lives in a transient for one hour.

Cookies: `rcom_id` (30 days, HttpOnly, only after the visitor clicked an email link) and `rcom_v_{post}` (30 days, HttpOnly, only after voting).

## Why

Showing reviews, confirming the author's address, proving agreement to the terms, and (only if ticked and confirmed) the newsletter (consent, double opt-in). A review does not depend on the newsletter.

## Third parties

Cloudflare Turnstile (script loaded when the form comes into view), Laposta (confirmed email address and first name, if enabled), your mail service.

## Retention and removal

Kept until the author or an administrator deletes it, or the article/recipe is permanently deleted (reviews, votes, feedback and photos go with it). Authors remove their review with the cookie or manage link; identified voters can remove their vote. WordPress Tools > Export/Erase Personal Data covers reviews, photos and identified votes by email address. Deleting the plugin removes everything unless "keep data" is set.

## Example sentence for your privacy statement

"Wanneer je een beoordeling plaatst bewaren we je voornaam, e-mailadres, beoordeling en eventuele foto. Je e-mailadres tonen we nooit; we gebruiken het om je een bevestigingslink te sturen. Van apparaten bewaren we alleen een grove categorie (bijvoorbeeld iPhone of Windows) voor anonieme statistieken, nooit je browsergegevens. Je kunt je gegevens laten inzien of verwijderen via ons contact."
