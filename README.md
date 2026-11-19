# Roci Standby

A fallback theme and a fatal-error drop-in for El Rocinante sites. When a site's theme breaks or crashes, visitors see a short branded "We'll be right back" page instead of a blank screen or WordPress's "There has been a critical error" message.

**This theme is not a fatal-error shield on its own.** As a theme it only takes over when WordPress *switches* to it. Catching PHP fatals is the job of the `php-error.php` drop-in, which has to be copied into `wp-content/` on each site (step 3 below).

## The three pieces

| Piece | What it does | Where it lives |
|---|---|---|
| **The theme** | The `WP_DEFAULT_THEME` fallback. When WordPress finds the active theme missing or structurally broken, it switches to this theme. Every front-end request then shows the standby page, with HTTP 503 for visitors. It also makes Site Health's "default theme" check pass. | `wp-content/themes/roci-standby/` (installed with Git Updater, never activated by hand) |
| **The drop-in** | Replaces WordPress's "critical error" screen on PHP fatals with the same branded page (HTTP 503). In wp-admin it prints a plain message instead, and recovery mode keeps working. | `wp-content/php-error.php` (a copy of this repo's `php-error.php`) |
| **Parent Maintenance mode** | El Rocinante's Maintenance mode (parent 8.5.0) uses this theme's page renderer, so planned downtime looks the same. | The parent theme. It needs this theme installed. |

All three use one renderer, `roci_standby_render()` in `inc/standby-page.php`. It prints a complete HTML document and depends on nothing outside that file, so it also works while WordPress is half loaded.

### The brand snapshot (1.1.0)

The page takes the site's brand without loading any parent code.

- **What it is:** El Rocinante (8.8.0+) builds a small brand array from its palette: `surface`, `text`, `heading`, `muted`, `accent`, `accent_text` (`#rrggbb`), `logo_url` and `name`. It saves this to the option `roci_brand_snapshot` whenever it changes, checked on admin page loads and after a theme switch.
- **Where the page gets it:** Maintenance mode passes it straight in. The standby theme and the `php-error.php` drop-in read the saved option.
- **No snapshot** (a parent older than 8.8.0, or nobody has opened wp-admin since updating): the page is the neutral light + dark version.
- **Contrast:** every colour pair is checked for WCAG AA before use, with fallbacks. A failing text colour falls back to the defaults, a failing button label switches to black or white, and a pale button gets a border.
- **Changing what a site sends:** a child adjusts it with the parent's `roci_brand_snapshot` filter.

### What the page shows

- **Logo:** the snapshot's logo, else the active theme's custom logo, else the previous theme's custom logo, else the site icon, else just the site name.
- **Message, by context:**
  - maintenance: "We're making a few improvements" / "We'll be back shortly. Thanks for your patience."
  - standby: "We'll be right back" / "We're working on the site and will be back soon."
  - error: "Something went wrong on our end" / "We're already on it. Please check back in a few minutes."

  A plugin or child can change them with the `roci_standby_message` filter, which receives `array( 'heading', 'text' )` and the context.
- **Contact button** (in the brand's accent colour): the first of these that is filled in:
  - WhatsApp: Theme Settings → Business → WhatsApp Number, else Social → WhatsApp. It links to `https://api.whatsapp.com/send?phone=…`, never `wa.me`, which fails on some Costa Rican networks.
  - Business → Phone.
  - Business → Email.

  If none is set there is no button, and the admin email is never shown.
- **Footer:** "© year name".
- No JavaScript, no external requests, system fonts, one small inline stylesheet. Light only with a brand, light and dark without one. `noindex`.

## Install (per site)

1. **Install the theme with Git Updater** (Git Updater → Install Theme, repo `JotaWorks-WD/roci-standby`, branch `main`). **Do not activate it.**
2. **Set it as the default theme** in `wp-config.php`, above the "That's all, stop editing!" line:
   ```php
   define( 'WP_DEFAULT_THEME', 'roci-standby' );
   ```
3. **Copy the drop-in** from `wp-content/themes/roci-standby/php-error.php` to `wp-content/php-error.php`. Copy it again when this repo's `php-error.php` changes, because Git Updater only updates the copy inside the theme folder.
4. **Delete the leftover Twenty-something theme(s).** Site Health is now satisfied by `roci-standby`.

## What it does NOT catch

- **Errors before WordPress registers its fatal-error handler,** such as a broken `wp-config.php`, or a site with `WP_DISABLE_FATAL_ERROR_HANDLER` set to true.
- **Database connection failures.** Those use the separate `db-error.php` drop-in, which this repo does not ship.
- **Server-level failures:** a 500 from the web server, a PHP-FPM crash, or a broken `.htaccess`.
- **Pages that load but look wrong:** broken CSS or JS, or a template that prints nothing without a PHP fatal.
- **Non-fatal PHP errors** (warnings, notices, deprecations).
- With `WP_DEBUG_DISPLAY` on, PHP's own error text can print before the page.
- **While the theme is active,** REST API and admin-ajax responses are untouched. Feeds, sitemaps and robots.txt are still served by core, with the 503 status.

## Watch-outs

- **Switching to this theme is a full theme switch.** Menus, widgets and theme mods are stored per theme. They are not lost, and they come back when you switch back to the real theme.
- **The child theme's admin screens disappear while on standby,** including Theme Settings → Pages and anything else the child or parent registers. Switch back to get them back.
- **Purge LiteSpeed both ways,** after switching to standby and again after switching back. The page asks LiteSpeed not to cache it, but pages cached before the switch are still served until purged.
- **A 503 is for hours or days, not weeks.** Search engines tolerate a short 503. A long one starts costing rankings.
- **Never activate it on a healthy site.** Every visitor would get a 503. For planned downtime, use the parent's Maintenance mode.
- Logged-in administrators see the same page with HTTP 200 and a notice bar linking to Appearance → Themes. All other visitors, logged in or not, get the 503.

## Requirements

WordPress 6.5+, PHP 8.0+.
