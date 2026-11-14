# Accessibility

What the markup does for assistive technology, and one deliberate trade-off.

## Review list

```html
<ul class="rcom-items">
  <li class="rcom-item">
    <article class="rcom-review">
      <div class="rcom-head"> stars, <strong class="rcom-name">Anna</strong>, <time datetime="2026-11-13">13 november 2026</time> </div>
      <p>Review text</p>
      <div class="rcom-reply">...</div>
    </article>
  </li>
</ul>
```

This is the same structure WordPress core uses for comments: a list, one item per review, an `article` per review. Screen readers announce "list, 10 items", users can jump between items (list navigation) and between articles. The load-more REST output and the account page ("My reviews") use the same structure.

- **Stars** are one `span role="img"` with the label "4 out of 5 stars" (translated). The visual stars are drawn by CSS, so there is no hidden duplicate text.
- **Dates** are `<time datetime="YYYY-MM-DD">`.
- **Rating input** is a radio group with one label per value ("5 stars" ... "1 star"), keyboard and screen reader friendly.
- **Load more** is a real link without JavaScript (`?rcom_page=N`) and a button-like link with it. After loading, focus moves to the first new review and a polite live region says "More reviews loaded."
- **Messages** (`role="status"`, `aria-live="polite"`) report success and errors.
- **Forced colours** (Windows high contrast): stars and bars use system colours, so filled and empty stars stay distinguishable.
- **Reduced motion**: the plugin has no animation.

## The trade-off: no heading per review

A review has no heading element. The reviewer's name is `<strong>`, not `<h3>`.

- **Why:** a name is a label, not a section title. Fifty headings called "Anna", "Mark", "Lotte" pollute the heading outline of a recipe page, which is what people use to skim the page. It also adds one more element per review.
- **What it costs:** a screen reader user cannot jump from review to review with the heading key. They use list navigation (next list item) or the article quick keys of their screen reader, and in a browse list each review is read from the stars to the text in order.
- **If your audience needs headings:** the markup is easy to change by filter or template override; a heading inside `rcom-head` is enough. We did not make that the default.

## Structured data

The plugin prints no structured data itself. `rcom_get_rating()` returns count and average of the shown reviews for the recipe schema of your SEO plugin. Individual reviews carry no `Comment` or `Review` schema, on purpose: the markup stays small and a recipe's rating is one aggregate.

## Checked

Chrome accessibility tree on a recipe page with three reviews: `list` (3 `listitem`) > `article` > `image "5 out of 5 stars"`, `strong`, `time`, `paragraph`; no headings inside the list. Forms: every input has a label and the honeypot field is `aria-hidden` and has `tabindex="-1"`. Colour contrast depends on the colours you choose.
