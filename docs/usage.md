# Usage

Shortcodes work in any editor or page builder (Bricks: Shortcode element). They only output on the configured post types (default `recipe`, `recept`) and use the current post, or `post_id="123"`.

| Shortcode | Output |
|---|---|
| `[rcom_stars]` | Star line: stars, "4,5 out of 5 · 12 reviews", links to the section. Attribute `href` (default `#rcom-reviews`). Shows an invitation when there are no reviews. |
| `[rcom_summary]` | Average with a bar per star count. |
| `[rcom_form]` | The review form (hidden without Turnstile keys). |
| `[rcom_list per_page="3"]` | Newest reviews; "Read all n reviews" loads the rest without a page reload. |
| `[rcom_reviews heading="1" per_page="3"]` | Everything in one `<section id="rcom-reviews">`: H2, summary, form, list. `heading="0"` omits the H2 (for a theme heading). |

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
