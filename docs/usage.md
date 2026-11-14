# Usage

Shortcodes work in any editor or page builder (Bricks: Shortcode element). They only output on the configured post types (default `recipe`, `recept`) and use the current post, or `post_id="123"`.

| Shortcode | Output |
|---|---|
| `[rcom_stars]` | Star line: stars, "4,5 out of 5 · 12 reviews", links to the section. Attribute `href` (default `#rcom-reviews`). Shows an invitation when there are no reviews. |
| `[rcom_summary]` | Average with a bar per star count. |
| `[rcom_form]` | The review form (hidden without Turnstile keys). |
| `[rcom_list per_page="3"]` | Newest reviews (only the first ones are in the HTML, counts come from the cache). "Show all n reviews" loads 10 more per click through REST (cursor based, max 20 per request, focus moves to the first new review, announced via aria-live); without JavaScript it is a link `?rcom_page=2#rcom-list` (10 more per step, `noindex`). |
| `[rcom_helpful]` | "Was this article helpful?" with Yes/No buttons and an optional feedback box. Active on the post types chosen under Settings > Helpful (default `kennis`). Shows the public count from a threshold (default 5). |
| `[rcom_reviews heading="1" per_page="3"]` | Everything in one `<section id="rcom-reviews">`: H2, summary, form, list. `heading="0"` omits the H2 (for a theme heading). |

| `[rcom_account]` | The account page: login by emailed link, then "My reviews", "My votes" and "My data" (a section registry, filter `rcom_account_sections`, lets a later module add sections). |
| `[rcom_account_link]` | Small link to the account page (label = "Name of the account"); empty when no page is set. |

## Typical recipe template

Hero: `[rcom_stars href="#reviews"]`. Lower down, an element with id `reviews`, an H2 "Reviews" and `[rcom_reviews heading="0"]`.

## Markup and styling

Classes start with `rcom-` and the CSS uses your site variables with fallbacks (`--primary`, `--secondary`, `--text-title`, `--text-muted`, `--bg-surface`, `--border-color`, `--radius-m`, `--space-*`, `--text-*`). Override in your theme CSS. Reviews are `h3`, the section title `h2`; stars are radio inputs with labels (keyboard usable), messages use `aria-live`.

## For developers

```php
$r = rcom_get_rating( $post_id );
// [ 'avg' => 4.5 (verified only), 'count' => 12 (all published), 'verified_count' => 9, 'avg_all' => 4.4, 'dist' => [1=>0,...,5=>7] ]
```

The values are cached in post meta `_rcom_avg`, `_rcom_count`, `_rcom_vcount`, `_rcom_dist` and refreshed on every change.

## Visitor flow

1. Visitor rates (required), optionally writes text, adds name, email, BBQ type, one photo, optionally ticks the newsletter box.
2. The review is live at once. A photo shows "Photo is being checked" until you approve it.
3. One email with a link confirms the address (and the newsletter choice). The link works once, 7 days.
4. After the link the visitor has a signed cookie (30 days): name is prefilled, own review can be removed. Later, "Send me a link to manage my review" mails a new link. Nobody is ever recognised by name and email alone.

## Managing your own review

Only visitors identified through the confirmation link see "Your review - Delete" at their own review. Every confirmation mail contains that link. There is no manage page yet; the REST route `POST /rcom/v1/link` ("send me a link") stays available for a future `[rcom_account]` page.

## Demo data

Settings > About > Demo data, or `wp rcom demo add --post=<id>` and `wp rcom demo clear`. Demo reviews, replies, a photo and demo votes show on that one page (average, list, helpful count) and are marked Demo in the admin. They are excluded from the dashboard, people, CSV export, the newsletter and `rcom_get_rating()`. Texts are Dutch for `nl_*` locales, English otherwise.
