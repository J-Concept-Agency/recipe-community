# FAQ

**Why is the form not visible?** Turnstile keys are missing (only admins see a notice) or the page is not a configured post type.

**Do visitors need an account?** No. They confirm their email address through a link; a cookie remembers them for 30 days.

**Can someone write a review in my name?** They can post with your address, but it is only marked verified when the link in the email is clicked, and only verified reviews count in `avg` (the average meant for schema markup). They cannot remove or edit anything without the link.

**Can I turn off the confirmation email?** No: it is what makes a review verified and what protects the newsletter opt-in.

**Photos fail?** Check GD/Imagick WebP support, the 8 MB limit, and PHP `upload_max_filesize`.

**Confirmation emails land in spam?** Use an SMTP plugin and a sender address on your domain with SPF and DKIM.

**Behind a proxy or CDN, rate limiting blocks everybody?** Return the real visitor IP from the `rcom_client_ip` filter.

**Can I hide the "Powered by J-Concept" link?** Yes, Settings > Credit link. See [licensing](licensing.md).

**Does it work without Bricks?** Yes, it only provides shortcodes.
