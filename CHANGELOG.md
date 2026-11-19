# Roci Standby Changelog

All notable changes to the Roci Standby theme are recorded here. Entries are newest-first.

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
