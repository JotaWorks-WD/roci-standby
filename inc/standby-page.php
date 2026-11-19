<?php
/**
 * Standby Page — The One Renderer
 *
 * Prints the complete branded "back soon" HTML document. Used by the theme's
 * index.php, by the php-error.php drop-in (with WordPress possibly half
 * loaded) and by the parent's Maintenance mode. Depends on nothing outside
 * this file: every WordPress call is guarded with function_exists() and
 * wrapped so a failing call falls back instead of fataling.
 *
 * File:    inc/standby-page.php
 * Version: 1.0.0
 * Updated: 2026-11-19
 *
 * @package RociStandby
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'roci_standby_render' ) ) {

    /**
     * roci_standby_render()
     *
     * @param array $args {
     *     @type string $context 'standby' (default) | 'maintenance' | 'error'.
     *     @type string $notice  Optional admin notice bar HTML. Only <a href>,
     *                           <strong> and <em> survive (wp_kses when loaded,
     *                           otherwise the whole string is escaped as text).
     * }
     */
    function roci_standby_render( $args = array() ) {
        $args    = is_array( $args ) ? $args : array();
        $context = ( isset( $args['context'] ) && in_array( $args['context'], array( 'standby', 'maintenance', 'error' ), true ) ) ? $args['context'] : 'standby';
        $notice  = ( isset( $args['notice'] ) && is_string( $args['notice'] ) ) ? trim( $args['notice'] ) : '';

        // Run a WordPress call only if it exists; any Throwable gives $default.
        $call = static function ( $fn, $params = array(), $default = null ) {
            if ( ! function_exists( $fn ) ) {
                return $default;
            }
            try {
                return call_user_func_array( $fn, $params );
            } catch ( \Throwable $e ) {
                return $default;
            }
        };
        $esc = static function ( $text ) {
            return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
        };
        $t = static function ( $text ) use ( $call ) {
            $out = $call( '__', array( $text, 'roci-standby' ), $text );
            return is_string( $out ) ? $out : $text;
        };

        // Site name and language.
        $name = $call( 'get_bloginfo', array( 'name' ), '' );
        $name = is_string( $name ) ? trim( html_entity_decode( $name, ENT_QUOTES, 'UTF-8' ) ) : '';

        $lang = $call( 'get_bloginfo', array( 'language' ), '' );
        $lang = ( is_string( $lang ) && preg_match( '/^[A-Za-z]{2,3}(-[A-Za-z0-9]{1,8})*$/', $lang ) ) ? $lang : 'en';

        // Logo: active theme's custom_logo → previous theme's → site icon → none.
        $logo = array( 'url' => '', 'w' => 0, 'h' => 0 );
        $ids  = array();
        $ids[] = (int) $call( 'get_theme_mod', array( 'custom_logo' ), 0 );

        $previous = array();
        $switched = $call( 'get_option', array( 'theme_switched' ), '' );
        if ( is_string( $switched ) && '' !== $switched ) {
            $previous[] = $switched;
        }
        $recorded = $call( 'get_option', array( 'roci_standby_previous_theme' ), '' );
        if ( is_string( $recorded ) && '' !== $recorded ) {
            $previous[] = $recorded;
        }
        foreach ( array_unique( $previous ) as $slug ) {
            $mods = $call( 'get_option', array( 'theme_mods_' . $slug ), array() );
            if ( is_array( $mods ) && ! empty( $mods['custom_logo'] ) ) {
                $ids[] = (int) $mods['custom_logo'];
            }
        }

        foreach ( $ids as $id ) {
            if ( $id <= 0 ) {
                continue;
            }
            $src = $call( 'wp_get_attachment_image_src', array( $id, 'full' ), false );
            if ( is_array( $src ) && ! empty( $src[0] ) ) {
                $logo = array( 'url' => (string) $src[0], 'w' => (int) $src[1], 'h' => (int) $src[2] );
                break;
            }
        }
        if ( '' === $logo['url'] ) {
            $icon = $call( 'get_site_icon_url', array( 512 ), '' );
            if ( is_string( $icon ) && '' !== $icon ) {
                $logo = array( 'url' => $icon, 'w' => 512, 'h' => 512 );
            }
        }
        if ( '' !== $logo['url'] && ! preg_match( '#^(https?:)?//#i', $logo['url'] ) ) {
            $logo['url'] = '';
        }

        // Message.
        $lines = array(
            'standby'     => $t( 'The site is being updated. Please check back soon.' ),
            'maintenance' => $t( 'We are doing some scheduled maintenance. Please check back soon.' ),
            'error'       => $t( 'Something went wrong on our side. Please check back soon.' ),
        );
        $message = array(
            'heading' => $t( "We'll be right back" ),
            'text'    => $lines[ $context ],
        );
        if ( function_exists( 'apply_filters' ) ) {
            try {
                $filtered = apply_filters( 'roci_standby_message', $message, $context );
                if ( is_array( $filtered ) ) {
                    foreach ( array( 'heading', 'text' ) as $key ) {
                        if ( isset( $filtered[ $key ] ) && is_string( $filtered[ $key ] ) ) {
                            $message[ $key ] = $filtered[ $key ];
                        }
                    }
                }
            } catch ( \Throwable $e ) {
                // Keep the default message.
            }
        }

        // Contact line, raw from roci_business: WhatsApp → phone → email.
        $contact  = '';
        $business = $call( 'get_option', array( 'roci_business' ), array() );
        if ( is_array( $business ) ) {
            $wa    = ! empty( $business['whatsapp'] ) ? preg_replace( '/\D+/', '', (string) $business['whatsapp'] ) : '';
            $phone = ! empty( $business['phone'] ) ? trim( (string) $business['phone'] ) : '';
            $email = ! empty( $business['email'] ) ? trim( (string) $business['email'] ) : '';

            if ( '' !== $wa ) {
                $contact = '<a href="' . $esc( 'https://wa.me/' . $wa ) . '">' . $esc( $t( 'Message us on WhatsApp' ) ) . '</a>';
            } elseif ( '' !== $phone && preg_match( '/\d/', $phone ) ) {
                $contact = $esc( $t( 'Call us:' ) ) . ' <a href="' . $esc( 'tel:' . preg_replace( '/[^\d+]/', '', $phone ) ) . '">' . $esc( $phone ) . '</a>';
            } elseif ( '' !== $email && false !== strpos( $email, '@' ) && ! preg_match( '/[\s<>"]/', $email ) ) {
                $contact = $esc( $t( 'Email us:' ) ) . ' <a href="' . $esc( 'mailto:' . $email ) . '">' . $esc( $email ) . '</a>';
            }
        }

        // Notice bar.
        $notice_html = '';
        if ( '' !== $notice ) {
            $kses = $call( 'wp_kses', array( $notice, array( 'a' => array( 'href' => array() ), 'strong' => array(), 'em' => array() ) ), null );
            $notice_html = is_string( $kses ) ? $kses : $esc( $notice );
        }

        $title = '' !== $name ? $message['heading'] . ' – ' . $name : $message['heading'];

        if ( ! headers_sent() ) {
            header( 'Content-Type: text/html; charset=utf-8' );
        }
        ?>
<!doctype html>
<html lang="<?php echo $esc( $lang ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo $esc( $title ); ?></title>
<style>
:root{color-scheme:light dark;--bg:#fff;--fg:#1d1d1f;--mu:#555;--ln:#0b57d0}
@media (prefers-color-scheme:dark){:root{--bg:#121212;--fg:#f2f2f2;--mu:#b3b3b3;--ln:#8ab4f8}}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%}
body{display:flex;min-height:100vh;align-items:center;justify-content:center;padding:4rem 1rem;background:var(--bg);color:var(--fg);font:1.0625rem/1.6 system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;text-align:center}
main{max-width:34rem}
.logo{display:block;max-width:18rem;width:auto;height:auto;max-height:8rem;margin:0 auto 2rem}
.name{margin:0 0 2rem;font-weight:600;font-size:1.25rem}
h1{margin:0 0 .75rem;font-size:clamp(1.75rem,5vw,2.5rem);line-height:1.2}
p{margin:0 0 1rem}
.mu{color:var(--mu)}
a{color:var(--ln);text-underline-offset:.15em}
a:focus-visible{outline:2px solid currentColor;outline-offset:2px}
.bar{position:fixed;top:0;left:0;right:0;margin:0;padding:.6rem 1rem;background:#1d2327;color:#fff;font-size:.9rem}
.bar a{color:#72aee6}
</style>
</head>
<body class="roci-standby roci-standby--<?php echo $esc( $context ); ?>">
<?php if ( '' !== $notice_html ) : ?>
<p class="bar" role="status"><?php echo $notice_html; // Filtered by wp_kses above, or fully escaped. ?></p>
<?php endif; ?>
<main>
<?php if ( '' !== $logo['url'] ) : ?>
<img class="logo" src="<?php echo $esc( $logo['url'] ); ?>" alt="<?php echo $esc( $name ); ?>"<?php echo ( $logo['w'] > 0 && $logo['h'] > 0 ) ? ' width="' . (int) $logo['w'] . '" height="' . (int) $logo['h'] . '"' : ''; ?>>
<?php elseif ( '' !== $name ) : ?>
<p class="name"><?php echo $esc( $name ); ?></p>
<?php endif; ?>
<h1><?php echo $esc( $message['heading'] ); ?></h1>
<p class="mu"><?php echo $esc( $message['text'] ); ?></p>
<?php if ( '' !== $contact ) : ?>
<p><?php echo $contact; // Built from escaped parts above. ?></p>
<?php endif; ?>
</main>
</body>
</html>
<?php
    }
}
