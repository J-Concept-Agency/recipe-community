# Theming

The plugin ships **structure** and a **neutral default style** built on CSS custom properties with the prefix `--rcom-`. It contains no site-specific values. A site links the tokens to its own variables in one small snippet (a *skin*), or switches the default style off and styles everything itself.

## How it works

1. The default CSS (`assets/rcom.css`, `rcom-helpful.css`, `rcom-account.css`) only reads tokens, each with a safe fallback: `color: var(--rcom-text, #1f2328)`.
2. **Settings > Appearance > Style** chooses what sets the tokens:
   - **Neutral (default)**: nothing; the fallbacks apply.
   - **Core Framework**: the bundled `assets/skins/core-framework.css` maps every `--rcom-*` token to the Core Framework variable (`--primary`, `--text-title`, `--space-s`, ...). A variable that does not exist simply falls back to the neutral default.
   - **None**: no plugin CSS at all. The markup stays; you style the `rcom-` classes yourself.
3. **Extra CSS** (same card, max 20 KB) is printed right after the plugin CSS, only on pages that render reviews, votes or the account page, so it wins on equal specificity. Use it for site finishing: override a token, add a rule. `<` characters, `@import` and script-like values (`javascript:`, `expression(`, `url()` to anything but `https:`, a path or `data:image/`) are removed on save and on output. Longer than 20 KB: not saved, the previous CSS stays.

The skin and the Extra CSS are attached inline to the plugin's own stylesheet handles at the moment a shortcode renders. Pages without plugin output load nothing and the setting is not even read.

```css
/* Extra CSS example */
:root { --rcom-star-size-lg: 1.5rem; --rcom-font-heading: "My Font", sans-serif; }
#beoordelingen { scroll-margin-top: 9rem; }
```

A theme or plugin can still do it in code: `wp_add_inline_style( 'rcom', $css )` (also `rcom-helpful`, `rcom-account`), or return `false` from `rcom_load_default_css`.

## Filters

| Filter | Purpose |
|---|---|
| `rcom_load_default_css` (bool, default `true`) | Return `false` to load none of the default CSS. The handles stay registered, so inline skins still work. |
| `rcom_css_classes` (string, `$type`) | Extra classes per element type: `button`, `card`, `input`. Lets a site apply its own utility classes without forking. |

## Tokens (all optional)

| Token | Default | Used for |
|---|---|---|
| `--rcom-accent` | `#2b59c3` | Filled stars (unless `--rcom-star`), bar fill, focus ring, own-review label |
| `--rcom-accent-hover` | `#1e429f` | Link hover |
| `--rcom-link` | accent | Text links such as "Show more" |
| `--rcom-text` | `#1f2328` | Headings, labels, review text |
| `--rcom-muted` | `#5f6672` | Dates, hints, counts |
| `--rcom-surface` | `#fff` | Form and cards |
| `--rcom-surface-alt` | `#f5f6f8` | Summary block, owner reply |
| `--rcom-border` | `#dcdfe4` | Borders, dividers |
| `--rcom-radius`, `--rcom-radius-sm` | `6px`, `4px` | Corners |
| `--rcom-btn-bg`, `--rcom-btn-text` | `#1f2328`, `#fff` | Primary buttons |
| `--rcom-star`, `--rcom-star-empty` | accent, muted | Filled / empty star |
| `--rcom-star-size-lg` | `20px` | Stars in a review |
| `--rcom-bar-track`, `--rcom-bar-fill` | border, accent | Distribution bars |
| `--rcom-ok-bg`, `--rcom-ok-text`, `--rcom-err-bg`, `--rcom-err-text`, `--rcom-danger` | greens/reds | Messages, destructive actions |
| `--rcom-font-body`, `--rcom-font-heading` | `inherit` | Fonts |
| `--rcom-heading-weight`, `--rcom-score-weight`, `--rcom-name-weight` | `700`, `800`, `700` | Weights |
| `--rcom-name-size`, `--rcom-text-s`, `--rcom-text-m`, `--rcom-text-l` | `1rem`, `.875rem`, `1rem`, `1.25rem` | Sizes. `--rcom-name-size` is shared by the reviewer name and the owner reply label |
| `--rcom-space-0` ... `--rcom-space-6` | `.125` to `2rem` | Spacing scale |
| `--rcom-line-height` | `1.6` | Review text |
| `--rcom-reply-bg`, `--rcom-reply-pad`, `--rcom-reply-border` | alt surface, `.75rem 1.5rem`, none | Owner reply block |
| `--rcom-btn-height`, `--rcom-btn-height-sm` | `2.75rem`, `2.25rem` | Button heights |
| `--rcom-anchor-offset` | `2rem` | Scroll offset under a sticky header |

## Class names (BEM-like, prefix `rcom-`)

- Section: `rcom`, summary `rcom-sum`, `rcom-score`, `rcom-bars`, `rcom-bar` (width in `--w`); star line `rcom-line`; stars are one element, `rcom-stars`, drawn with a CSS mask (fill in `--r`, size in `--sz`).
- Form: `rcom-form`, `rcom-f`, `rcom-two`, `rcom-in`, `rcom-lbl`, `rcom-opt`, `rcom-check`, `rcom-row` (star input), `rcom-photo-f`, `rcom-fbtn`, `rcom-prev`, `rcom-msg`, `rcom-btn`, `rcom-priv`.
- List: `rcom-list` > `ul.rcom-items` > `li.rcom-item` > `article.rcom-review` > `div.rcom-head` (stars, `rcom-name`, `rcom-small` with a `<time>`, `rcom-own`, `rcom-del`), then the text `<p>`, `rcom-photo`, `rcom-note`, `div.rcom-reply`; `rcom-more`, `rcom-credit`.
- Helpful: `rcom-helpful`, `rcom-hbtn`, `rcom-hcount`, `rcom-hform`, `rcom-hmsg`, `rcom-hrm`.
- Account: `rcom-acc`, `rcom-accform`, `rcom-tabs`, `rcom-tab`, `rcom-sec`, `rcom-vrow`, `rcom-accbtns`, `rcom-danger`.

## Previewing

Settings > About (or `wp rcom demo add --post=ID`) adds demo reviews, replies, a photo and demo votes to one post. Demo rows count on that page only (documented in the admin); remove them with the button or `wp rcom demo clear`.

## Roadmap

A "Display settings" screen (colour and corner pickers) may follow; this version has no colour options by design.
