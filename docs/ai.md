# AI assistants and the Abilities API

Recipe Community ships guidance for AI coding assistants and an optional set of WordPress Abilities.

## Files for assistants

- `AGENTS.md` and `llms.txt` in the repository root: a short map of the data model, shortcodes, hooks and rules.
- `skills/recipe-community/SKILL.md`: a Claude skill with the same facts in skill format.

Rules every assistant must follow: never log, print or share email addresses; never commit real keys (Turnstile, Laposta); do not add remote calls without a setting; keep queries prepared and output escaped.

## Abilities (WordPress 6.9+)

When the Abilities API exists (`wp_register_ability()`), the plugin can expose a small set of abilities for administrator AI assistants (for example through an MCP adapter). **Off by default**: Settings > "Allow AI abilities".

| Ability | Effect |
|---|---|
| `recipe-community/get-stats` | Totals, average, star distribution and top recipes for a period. No personal data. |
| `recipe-community/list-reviews` | List/search reviews. Email addresses only when `include_email` is `true`. |
| `recipe-community/reply-review` | Set or clear the owner reply. |
| `recipe-community/approve-photo` | Approve a review photo. |
| `recipe-community/set-review-visibility` | Hide or show a review. |
| `recipe-community/delete-review` | Delete a review and its photo. Needs `confirm: true`. |

All need the `manage_options` capability, have input schemas, do not make network calls, and live in the category `recipe-community`. If the Abilities API is missing nothing is registered.
