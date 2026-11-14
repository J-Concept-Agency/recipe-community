# FAQ

**Why is the form not visible?** Turnstile keys are missing (only admins see a notice) or the page is not a configured post type.

**Do visitors need an account?** No. They confirm their email address through a link; a cookie remembers them for 30 days.

**Can someone write a review in my name?** They can post with your address, but it is only marked verified when the link in the email is clicked, and only verified reviews count in `avg` (the average meant for schema markup). They cannot remove or edit anything without the link.

**Can I turn off the confirmation email?** No: it is what makes a review verified and what protects the newsletter opt-in.

**Photos fail?** Check GD/Imagick WebP support, the 8 MB limit, and PHP `upload_max_filesize`.

**Confirmation emails land in spam?** Use an SMTP plugin and a sender address on your domain with SPF and DKIM.

**Behind a proxy or CDN, rate limiting blocks everybody?** Return the real visitor IP from the `rcom_client_ip` filter.

**Can I hide the "Powered by J-Concept" link?** Yes, it is on by default and can be switched off in Settings > Credit link. See [licensing](licensing.md).

**Does it work without Bricks?** Yes, it only provides shortcodes.

**Do database cleaners and optimisation plugins delete reviews?**
All plugin data lives in its own tables (`rcom_reviews`, `rcom_votes`, `rcom_feedback`, `rcom_terms_versions`), not in comments, post meta or transients. Safe to run: removing post revisions, expired transients, spam comments, optimising/repairing tables. The cached values in post meta (`_rcom_*`) and transients can disappear at any time; they are rebuilt automatically (Settings > "Recalculate cache" forces it). Not safe: "remove unused/orphan tables" tools (they may drop the `rcom_` tables), emptying the trash of recipes (deleting a recipe for good deletes its reviews unless Settings > "When a recipe is deleted for good" is set to keep them hidden), and anything that bulk-deletes tables or the `uploads/rcom/` folder.

**What can the plugin not prevent?** Another plugin or a person that deliberately drops its tables, deletes the uploads folder, or restores an old database. A daily check and a notice on the plugin screens report missing tables and offer "Repair tables"; that recreates the tables empty, lost data cannot be recovered.

**Back-up tip:** include the database and `wp-content/uploads/rcom/` in every back-up.

**Is the version of my terms provable without revisions?** Yes: per version a text copy is stored in `rcom_terms_versions` (Settings > Keep a copy of the terms, on by default). Copies are not deleted automatically while reviews refer to them.
