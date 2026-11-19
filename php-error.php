<?php
/**
 * Roci Standby — Fatal-Error Drop-in
 *
 * COPY this file to wp-content/php-error.php on each site (it does nothing
 * from inside the theme folder). WordPress's fatal-error handler loads it in
 * place of the "There has been a critical error" screen. Visitors get the
 * branded standby page with a 503; wp-admin gets a plain message, and
 * WordPress recovery mode (the emailed link) keeps working.
 *
 * Must never fatal itself: every call is guarded, the renderer is used only
 * when its file exists, and an inline fallback page needs nothing at all.
 *
 * 1.0.1 (roci-standby 1.1.0): the inline fallback (used only when the theme's
 * renderer file is missing) carries the new error copy, "Something went wrong
 * on our end". It stays unstyled-simple: no brand, no button.
 *
 * File:    php-error.php
 * Version: 1.0.1
 * Updated: 2026-11-19
 *
 * @package RociStandby
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Drop whatever the broken request had buffered, so the page is not
// appended to half a page. Only removable buffers; stop at the first other.
while ( ob_get_level() > 0 ) {
    $roci_ob = ob_get_status();
    if ( empty( $roci_ob['flags'] ) || ! ( $roci_ob['flags'] & PHP_OUTPUT_HANDLER_REMOVABLE ) ) {
        break;
    }
    if ( ! @ob_end_clean() ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors -- never fatal or warn here.
        break;
    }
}

$roci_is_admin = false;
try {
    $roci_is_admin = function_exists( 'is_admin' ) && is_admin();
} catch ( \Throwable $roci_e ) {
    $roci_is_admin = false;
}

if ( ! headers_sent() ) {
    http_response_code( 503 );
    header( 'Retry-After: 3600' );
    header( 'X-Robots-Tag: noindex' );
    header( 'Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private' );
    header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
    header( 'Content-Type: text/html; charset=utf-8' );
}

try {
    if ( function_exists( 'do_action' ) ) {
        do_action( 'litespeed_control_set_nocache', 'roci-standby php-error' );
    }
} catch ( \Throwable $roci_e ) {
    // Nothing to do.
}

// ------------------------------------------------------------
// wp-admin: a plain message, not the branded page.
// ------------------------------------------------------------
if ( $roci_is_admin ) {
    $roci_msg = 'A critical error occurred. Check the site admin email for a recovery link.';
    try {
        if ( function_exists( '__' ) ) {
            $roci_msg = __( 'A critical error occurred. Check the site admin email for a recovery link.', 'roci-standby' );
        }
    } catch ( \Throwable $roci_e ) {
        // Keep the English string.
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Critical error</title></head>'
        . '<body style="font:16px/1.5 system-ui,sans-serif;margin:2rem;color:#1d2327;background:#fff"><p>'
        . htmlspecialchars( (string) $roci_msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' )
        . '</p></body></html>';
    return;
}

// ------------------------------------------------------------
// Front end: the standby renderer, when the theme is installed.
// ------------------------------------------------------------
$roci_renderer = ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : dirname( __FILE__ ) ) . '/themes/roci-standby/inc/standby-page.php';

if ( is_readable( $roci_renderer ) ) {
    try {
        require_once $roci_renderer;
        if ( function_exists( 'roci_standby_render' ) ) {
            roci_standby_render( array( 'context' => 'error' ) );
            return;
        }
    } catch ( \Throwable $roci_e ) {
        // Fall through to the inline page.
    }
}

// ------------------------------------------------------------
// Fallback: the same look, self-contained.
// ------------------------------------------------------------
$roci_name = '';
try {
    if ( function_exists( 'get_bloginfo' ) ) {
        $roci_name = trim( html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ) );
    }
} catch ( \Throwable $roci_e ) {
    $roci_name = '';
}
$roci_name_esc = htmlspecialchars( $roci_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Something went wrong on our end<?php echo '' !== $roci_name_esc ? ' – ' . $roci_name_esc : ''; ?></title>
<style>
:root{color-scheme:light dark;--bg:#fff;--fg:#1d1d1f;--mu:#555}
@media (prefers-color-scheme:dark){:root{--bg:#121212;--fg:#f2f2f2;--mu:#b3b3b3}}
html,body{margin:0}
body{display:flex;min-height:100vh;align-items:center;justify-content:center;padding:4rem 1rem;box-sizing:border-box;background:var(--bg);color:var(--fg);font:1.0625rem/1.6 system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;text-align:center}
main{max-width:34rem}
.name{margin:0 0 2rem;font-weight:600;font-size:1.25rem}
h1{margin:0 0 .75rem;font-size:clamp(1.75rem,5vw,2.5rem);line-height:1.2}
p{margin:0 0 1rem}
.mu{color:var(--mu)}
</style>
</head>
<body class="roci-standby roci-standby--error">
<main>
<?php if ( '' !== $roci_name_esc ) : ?>
<p class="name"><?php echo $roci_name_esc; ?></p>
<?php endif; ?>
<h1>Something went wrong on our end</h1>
<p class="mu">We're already on it. Please check back in a few minutes.</p>
</main>
</body>
</html>
