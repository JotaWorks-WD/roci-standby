# Roci Standby Changelog

All notable changes to the Roci Standby theme are recorded here. Entries are newest-first.

---

## [1.1.0] — 2026-11-19

**FEAT: the "back soon" page carries each site's brand (colours, logo, name), with friendlier copy per context and the contact line as a button. Every colour pair is checked for WCAG AA in PHP.**

**In plain English (2026-11-19):** The "back soon" page now matches each site's colours and logo.

### Where the brand comes from (`inc/standby-page.php` 1.1.0)

- **First:** the new `'brand'` arg of `roci_standby_render()`, which the parent's Maintenance mode passes (El Rocinante 8.8.0).
- **Else:** the option `roci_brand_snapshot`, read raw. The parent saves it, so the standby theme and the drop-in carry the brand too.
- **Else:** today's neutral light + dark page.
- **Keys:** `surface`, `text`, `heading`, `muted`, `accent`, `accent_text` (each `#rrggbb` only; anything else is ignored), `logo_url` (http(s)), `name`.
- **Logo and name:** the brand's own come first, then the old logo chain and site name.

### Contrast (`roci_standby_colors()`, `roci_standby_contrast()`, WCAG relative luminance)

- **Text on surface** must reach 4.5, else the default pair (`#1d1d1f` on `#ffffff`) is used.
- **Heading and muted on surface** must reach 4.5, else they use the text colour.
- **Button label on accent** must reach 4.5, else `#000` or `#fff`, whichever is higher. One of them always reaches at least 4.58 (the harness checks all 256 greys), so the default-button fallback is defensive only.
- **Accent edge against surface** must reach 3, else the button gets a 2px border in the text colour.
- **Hover** is the accent mixed 10% toward black, used only while the label still reaches 4.5 on it, and the edge 3 when there's no border.
- **With a brand:** `color-scheme: light` and no dark override. **Without one:** the neutral light + dark page, as before.

### Copy, layout, button

- **Copy:**
  - maintenance: "We're making a few improvements" / "We'll be back shortly. Thanks for your patience."
  - standby: "We'll be right back" / "We're working on the site and will be back soon."
  - error: "Something went wrong on our end" / "We're already on it. Please check back in a few minutes."
  - `roci_standby_message` still filters all three, and the parent's custom Maintenance message still replaces the maintenance line.
- **Layout:** logo (max 18rem wide, auto height, alt = name) or the name → heading → line → the contact **button** → a footer, "© year name".
- **The button:** a real `<a>`, at least 44px tall, rounded, with a `:focus-visible` outline (2px, the text colour, offset 3px) and the hover above.
  - The contact order and rules are unchanged: WhatsApp (Business, else Social; `api.whatsapp.com`), then phone, then email.
  - Labels: "Message us on WhatsApp", "Call {number}", "Email {address}".
- **Size:** the inline `<style>` is 1,348 bytes with a brand and 1,486 without; the limit is 2.5 KB. System fonts, no JS, no external request.

### The drop-in (`php-error.php` 1.0.1)

- Its own minimal fallback, used only when the renderer file is missing, carries the new error copy. It stays unstyled-simple.

---

## [1.0.1] — 2026-11-19

**FIX: the WhatsApp contact link uses `https://api.whatsapp.com/send?phone=` instead of `wa.me`, and falls back to Social → WhatsApp.**

**In plain English (2026-11-19):** The WhatsApp button on the "back soon" page now opens reliably on every network.

- **The link:** `wa.me` fails DNS on some Costa Rican networks, so the contact line now links to `https://api.whatsapp.com/send?phone={digits}` (`inc/standby-page.php` 1.0.1).
- **The number:** Theme Settings → Business → WhatsApp Number first, else Social → WhatsApp. This matches El Rocinante 8.6.0's `roci_whatsapp_number()`.
  - Both are still read raw, with the same guards.
  - A link gives the number in its `phone=` value or its `wa.me/` path.
  - Anything else gives its digits.
  - 7–15 digits, or no WhatsApp line.
- Phone and email, the fallbacks after WhatsApp, are unchanged.

---

## [1.0.0] — 2026-11-19

**FEAT: first release. A fallback theme (`WP_DEFAULT_THEME`) and a `php-error.php` drop-in that show one branded "We'll be right back" page.**

**In plain English (2026-11-19):** If a site's theme breaks or crashes, visitors now see a branded "back soon" page instead of an error.

- **`roci_standby_render( $args )`** (`inc/standby-page.php`) is the only renderer. It prints a complete HTML document and depends on nothing outside its file. Every WordPress call is guarded with `function_exists()` and a `Throwable` catch, so it works from the drop-in while WordPress is half loaded.
  - **Logo:** the active theme's custom logo, else the previous theme's (from `theme_switched`, then `roci_standby_previous_theme`, which the theme records on `after_switch_theme` because core clears `theme_switched`), else the site icon, else the site name.
  - **Message:** the heading plus a line per context (`standby`, `maintenance`, `error`), filterable with `roci_standby_message`.
  - **Contact line:** from the raw `roci_business` option, WhatsApp, then phone, then email. Never the admin email.
  - **Styling:** one inline `<style>` (1,020 bytes), light and dark. No JS, no external requests, `noindex`.
  - **Args:** `context` and `notice`. The notice keeps only `<a href>`, `<strong>` and `<em>` through `wp_kses`, and is fully escaped when `wp_kses` is not loaded.
- **The theme** (`functions.php`, `index.php`): `title-tag` and `custom-logo` support. On `template_redirect`, visitors get 503, `Retry-After: 3600`, `X-Robots-Tag: noindex`, no-cache headers and LiteSpeed no-cache. Users with `manage_options` get a 200 and a notice bar linking to Appearance → Themes. wp-admin is untouched.
- **The drop-in** (`php-error.php`, copied to `wp-content/`):
  - Discards whatever the crashed request had buffered.
  - Sends 503, `Retry-After`, `noindex` and no-cache headers.
  - Renders the page through the theme's renderer when it is installed. Otherwise it prints a self-contained page with the same look.
  - In wp-admin it prints a plain recovery message instead.
- `README.md`: the three pieces, install steps, what it does not catch, and watch-outs.
