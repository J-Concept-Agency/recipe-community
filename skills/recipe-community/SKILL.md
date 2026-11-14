---
name: recipe-community
description: Work on or explain the Recipe Community WordPress plugin (reviews, helpful votes, email-link identity, Turnstile, Laposta). Use when editing the plugin, adding shortcodes, answering how it works, or administering reviews.
---

# Recipe Community

Light WordPress plugin: star reviews with photos, "was this helpful?" votes. Own tables (`rcom_reviews`, `rcom_votes`, `rcom_feedback`), no WordPress comments, no visitor accounts.

## How it works
- Visitor rates, review is live at once. Photo waits for approval ("Photo is being checked").
- One email link (token, only its hash stored, 7 days) marks the review verified, confirms newsletter opt-in and sets a signed 30-day cookie `rcom_id`. Never recognise someone by name and email alone.
- Only verified reviews count in `rcom_get_rating()['avg']`.
- Nonce and form stamp are fetched through `GET /wp-json/rcom/v1/state` so page caches are safe.
- Votes: signed per-article cookie (30 days); identified visitors cannot vote twice but can change or remove their vote.

## Shortcodes
`[rcom_stars]` `[rcom_summary]` `[rcom_form]` `[rcom_list]` `[rcom_reviews]` `[rcom_helpful]`

## Hooks
`rcom_newsletter_optin( $email, $name, $post_id )`, `rcom_reviews_changed`, filters `rcom_post_types`, `rcom_helpful_post_types`, `rcom_newsletter_providers`, `rcom_client_ip`.

## Rules
Never share or log email addresses. No real keys in code. Only a coarse device category is stored, never a raw user agent. Prepared queries, escaped output, nonce and capability checks. Admin abilities (if enabled) are in `docs/ai.md`.
