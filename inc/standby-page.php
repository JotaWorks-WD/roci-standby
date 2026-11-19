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
 * 1.0.1: WhatsApp reads roci_business → whatsapp, else roci_social →
 * whatsapp (the parent's rule since 8.6.0), and links to
 * https://api.whatsapp.com/send?phone= instead of wa.me.
 *
 * 1.1.0: the site's brand. Colours, logo and name come from the 'brand' arg
 * (the parent's Maintenance mode passes roci_brand_snapshot()), else the
 * option roci_brand_snapshot the parent saves (read raw), else today's
 * neutral light + dark look. Every colour is checked for WCAG AA contrast in
 * roci_standby_colors(), with fallbacks. New copy per context, the contact
 * line as a button, a "© year name" footer.
 *
 * 1.1.1: the brand's logo_w / logo_h (El Rocinante 8.9.0) become the <img>
 * width / height, and the logo CSS no longer sets width:auto, so the box is
 * reserved before it loads (max-width still scales it down).
 *
 * File:    inc/standby-page.php
 * Version: 1.1.1
 * Updated: 2026-11-19
 *
 * @package RociStandby
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'roci_standby_contrast' ) ) {

    /**
     * roci_standby_contrast()
     *
     * WCAG 2 contrast ratio of two '#rrggbb' colours (1 to 21).
     *
     * @param  string $a
     * @param  string $b
     * @return float
     */
    function roci_standby_contrast( $a, $b ) {
        $lum = static function ( $hex ) {
            $out = array();
            foreach ( array( 1, 3, 5 ) as $i ) {
                $c     = hexdec( substr( $hex, $i, 2 ) ) / 255;
                $out[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
            }
            return 0.2126 * $out[0] + 0.7152 * $out[1] + 0.0722 * $out[2];
        };
        $la = $lum( $a );
        $lb = $lum( $b );
        return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
    }
}

if ( ! function_exists( 'roci_standby_colors' ) ) {

    /**
     * roci_standby_colors()
     *
     * The page's colours from a brand array (surface, text, heading, muted,
     * accent, accent_text; any may be missing), every pair checked:
     *   text on surface ≥ 4.5, else the default text / surface pair;
     *   heading on surface ≥ 4.5, else text;
     *   muted on surface ≥ 4.5, else text (a missing muted is text);
     *   accent_text on accent ≥ 4.5, else #000 or #fff, whichever passes
     *   (the higher), else the default button pair;
     *   accent against surface ≥ 3, else a 2px border in the text colour;
     *   hover = the accent mixed 10% toward black, kept only while the label
     *   stays ≥ 4.5 on it (and ≥ 3 against the surface when borderless).
     * No valid colour at all → the neutral defaults ('brand' false).
     * The parent's roci_brand_page_colors() is the same algorithm; keep both
     * in step.
     *
     * @param  mixed $brand
     * @return array
     */
    function roci_standby_colors( $brand ) {
        $default = array( 'surface' => '#ffffff', 'text' => '#1d1d1f', 'muted' => '#555555', 'accent' => '#0b57d0', 'accent_text' => '#ffffff' );
        $hex     = static function ( $v ) {
            $v = is_string( $v ) ? strtolower( trim( $v ) ) : '';
            return preg_match( '/^#[0-9a-f]{6}$/', $v ) ? $v : '';
        };
        $mix     = static function ( $v ) {
            $out = '#';
            foreach ( array( 1, 3, 5 ) as $i ) {
                $out .= sprintf( '%02x', (int) round( hexdec( substr( $v, $i, 2 ) ) * 0.9 ) );
            }
            return $out;
        };
        $b = array();
        foreach ( array( 'surface', 'text', 'heading', 'muted', 'accent', 'accent_text' ) as $k ) {
            $b[ $k ] = ( is_array( $brand ) && isset( $brand[ $k ] ) ) ? $hex( $brand[ $k ] ) : '';
        }
        $in_use = '' !== implode( '', $b );
        $notes  = array();

        if ( ! $in_use ) {
            $c = $default + array( 'heading' => $default['text'] );
        } else {
            $c = array(
                'surface' => '' !== $b['surface'] ? $b['surface'] : $default['surface'],
                'text'    => '' !== $b['text'] ? $b['text'] : $default['text'],
            );
            if ( roci_standby_contrast( $c['text'], $c['surface'] ) < 4.5 ) {
                $c       = array( 'surface' => $default['surface'], 'text' => $default['text'] );
                $notes[] = 'text/surface failed: default pair';
            }
            $c['heading'] = '' !== $b['heading'] ? $b['heading'] : $c['text'];
            if ( roci_standby_contrast( $c['heading'], $c['surface'] ) < 4.5 ) {
                $c['heading'] = $c['text'];
                $notes[]      = 'heading failed: text';
            }
            $c['muted'] = '' !== $b['muted'] ? $b['muted'] : $c['text'];
            if ( roci_standby_contrast( $c['muted'], $c['surface'] ) < 4.5 ) {
                $c['muted'] = $c['text'];
                $notes[]    = 'muted failed: text';
            }
            $c['accent']      = '' !== $b['accent'] ? $b['accent'] : $default['accent'];
            $c['accent_text'] = '' !== $b['accent_text'] ? $b['accent_text'] : $default['accent_text'];
            if ( roci_standby_contrast( $c['accent_text'], $c['accent'] ) < 4.5 ) {
                $white = roci_standby_contrast( '#ffffff', $c['accent'] );
                $black = roci_standby_contrast( '#000000', $c['accent'] );
                if ( max( $white, $black ) >= 4.5 ) {
                    $c['accent_text'] = $white >= $black ? '#ffffff' : '#000000';
                    $notes[]          = 'accent_text failed: ' . $c['accent_text'];
                } else {
                    $c['accent']      = $default['accent'];
                    $c['accent_text'] = $default['accent_text'];
                    $notes[]          = 'accent pair failed: default button';
                }
            }
        }

        $c['border'] = roci_standby_contrast( $c['accent'], $c['surface'] ) < 3;
        $c['hover']  = $mix( $c['accent'] );
        if ( roci_standby_contrast( $c['accent_text'], $c['hover'] ) < 4.5 || ( ! $c['border'] && roci_standby_contrast( $c['hover'], $c['surface'] ) < 3 ) ) {
            $c['hover'] = $c['accent'];
            $notes[]    = 'hover kept the accent';
        }
        if ( $c['border'] ) {
            $notes[] = 'accent edge < 3:1: border';
        }
        $c['brand']  = $in_use;
        $c['notes']  = $notes;
        $c['ratios'] = array(
            'text/surface'       => round( roci_standby_contrast( $c['text'], $c['surface'] ), 2 ),
            'heading/surface'    => round( roci_standby_contrast( $c['heading'], $c['surface'] ), 2 ),
            'muted/surface'      => round( roci_standby_contrast( $c['muted'], $c['surface'] ), 2 ),
            'accent_text/accent' => round( roci_standby_contrast( $c['accent_text'], $c['accent'] ), 2 ),
            'accent_text/hover'  => round( roci_standby_contrast( $c['accent_text'], $c['hover'] ), 2 ),
            'accent/surface'     => round( roci_standby_contrast( $c['accent'], $c['surface'] ), 2 ),
        );
        return $c;
    }
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
     *     @type array  $brand   Optional: surface, text, heading, muted, accent,
     *                           accent_text (#rrggbb), logo_url, name. Absent →
     *                           the roci_brand_snapshot option, else neutral.
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

        // Brand: the arg, else the parent's saved snapshot (raw), else none.
        $brand = ( isset( $args['brand'] ) && is_array( $args['brand'] ) ) ? $args['brand'] : $call( 'get_option', array( 'roci_brand_snapshot' ), array() );
        $brand = is_array( $brand ) ? $brand : array();
        $col   = roci_standby_colors( $brand );

        // Site name and language.
        $name = ( isset( $brand['name'] ) && is_string( $brand['name'] ) ) ? trim( $brand['name'] ) : '';
        if ( '' === $name ) {
            $name = $call( 'get_bloginfo', array( 'name' ), '' );
            $name = is_string( $name ) ? trim( html_entity_decode( $name, ENT_QUOTES, 'UTF-8' ) ) : '';
        }

        $lang = $call( 'get_bloginfo', array( 'language' ), '' );
        $lang = ( is_string( $lang ) && preg_match( '/^[A-Za-z]{2,3}(-[A-Za-z0-9]{1,8})*$/', $lang ) ) ? $lang : 'en';

        // Logo: the brand's logo_url → the active theme's custom_logo →
        // the previous theme's → the site icon → none.
        $logo = array( 'url' => '', 'w' => 0, 'h' => 0 );
        if ( isset( $brand['logo_url'] ) && is_string( $brand['logo_url'] ) && preg_match( '#^https?://#i', trim( $brand['logo_url'] ) ) ) {
            $logo['url'] = trim( $brand['logo_url'] );
            // 1.1.1: the snapshot's intrinsic size (parent 8.9.0), so the box is
            // reserved before the image loads. Absent for an SVG.
            if ( isset( $brand['logo_w'], $brand['logo_h'] ) && (int) $brand['logo_w'] > 0 && (int) $brand['logo_h'] > 0 ) {
                $logo['w'] = (int) $brand['logo_w'];
                $logo['h'] = (int) $brand['logo_h'];
            }
        }
        $ids = array();
        if ( '' === $logo['url'] ) {
            $ids[]    = (int) $call( 'get_theme_mod', array( 'custom_logo' ), 0 );
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

        // Message, per context.
        $copy = array(
            'maintenance' => array( $t( "We're making a few improvements" ), $t( "We'll be back shortly. Thanks for your patience." ) ),
            'standby'     => array( $t( "We'll be right back" ), $t( "We're working on the site and will be back soon." ) ),
            'error'       => array( $t( 'Something went wrong on our end' ), $t( "We're already on it. Please check back in a few minutes." ) ),
        );
        $message = array(
            'heading' => $copy[ $context ][0],
            'text'    => $copy[ $context ][1],
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

        // Contact button, read raw: WhatsApp (roci_business, else roci_social) →
        // phone → email. The number rule mirrors the parent's
        // roci_whatsapp_digits() (8.6.0): a link gives its phone= value or its
        // wa.me/<number> path, anything else its digits; 7–15 digits or ''.
        $wa_digits = static function ( $value ) {
            $value = trim( (string) $value );
            if ( '' === $value ) {
                return '';
            }
            if ( preg_match( '#^[a-z][a-z0-9+.\-]*:|/#i', $value ) ) {
                if ( preg_match( '/[?&]phone=([^&#]+)/i', $value, $m ) ) {
                    $digits = preg_replace( '/\D+/', '', rawurldecode( $m[1] ) );
                } elseif ( preg_match( '#(?:^|//|\.)wa\.me/\+?(\d+)#i', $value, $m ) ) {
                    $digits = $m[1];
                } else {
                    $digits = '';
                }
            } else {
                $digits = preg_replace( '/\D+/', '', $value );
            }
            $length = strlen( $digits );
            return ( $length >= 7 && $length <= 15 ) ? $digits : '';
        };

        $business = $call( 'get_option', array( 'roci_business' ), array() );
        $business = is_array( $business ) ? $business : array();
        $social   = $call( 'get_option', array( 'roci_social' ), array() );
        $social   = is_array( $social ) ? $social : array();

        $wa = ! empty( $business['whatsapp'] ) ? $wa_digits( $business['whatsapp'] ) : '';
        if ( '' === $wa && ! empty( $social['whatsapp'] ) ) {
            $wa = $wa_digits( $social['whatsapp'] );
        }
        $phone = ! empty( $business['phone'] ) ? trim( (string) $business['phone'] ) : '';
        $email = ! empty( $business['email'] ) ? trim( (string) $business['email'] ) : '';

        $button = array();
        // api.whatsapp.com, never wa.me: wa.me fails DNS on some Costa Rican networks.
        if ( '' !== $wa ) {
            $button = array( 'https://api.whatsapp.com/send?phone=' . $wa, $t( 'Message us on WhatsApp' ) );
        } elseif ( '' !== $phone && preg_match( '/\d/', $phone ) ) {
            $button = array( 'tel:' . preg_replace( '/[^\d+]/', '', $phone ), $t( 'Call' ) . ' ' . $phone );
        } elseif ( '' !== $email && false !== strpos( $email, '@' ) && ! preg_match( '/[\s<>"]/', $email ) ) {
            $button = array( 'mailto:' . $email, $t( 'Email' ) . ' ' . $email );
        }

        // Notice bar.
        $notice_html = '';
        if ( '' !== $notice ) {
            $kses = $call( 'wp_kses', array( $notice, array( 'a' => array( 'href' => array() ), 'strong' => array(), 'em' => array() ) ), null );
            $notice_html = is_string( $kses ) ? $kses : $esc( $notice );
        }

        $year  = $call( 'wp_date', array( 'Y' ), '' );
        $year  = ( is_string( $year ) && preg_match( '/^\d{4}$/', $year ) ) ? $year : gmdate( 'Y' );
        $title = '' !== $name ? $message['heading'] . ' – ' . $name : $message['heading'];

        // Colours: computed brand values, or the neutral light + dark pair.
        $vars = '--bg:' . $col['surface'] . ';--fg:' . $col['text'] . ';--hd:' . $col['heading'] . ';--mu:' . $col['muted'] . ';--ac:' . $col['accent'] . ';--at:' . $col['accent_text'] . ';--ah:' . $col['hover'];
        $root = $col['brand']
            ? ':root{color-scheme:light;' . $vars . '}'
            : ':root{color-scheme:light dark;' . $vars . '}@media (prefers-color-scheme:dark){:root{--bg:#121212;--fg:#f2f2f2;--hd:#f2f2f2;--mu:#b3b3b3;--ac:#8ab4f8;--at:#121212;--ah:#7ca2df}}';

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
<?php echo $root; // Built from validated #rrggbb values only. ?>

*{box-sizing:border-box}
html,body{margin:0}
body{display:flex;flex-direction:column;min-height:100vh;background:var(--bg);color:var(--fg);font:1.0625rem/1.6 system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;text-align:center}
main{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;width:100%;max-width:36rem;margin:0 auto;padding:4rem 1rem 2rem}
.logo{display:block;max-width:min(18rem,100%);height:auto;margin:0 auto 2rem}
.name{margin:0 0 2rem;font-weight:600;font-size:1.25rem;color:var(--hd)}
h1{margin:0 0 .75rem;font-size:clamp(1.75rem,5vw,2.5rem);line-height:1.2;color:var(--hd)}
p{margin:0 0 1.5rem}
.mu{color:var(--mu)}
.btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:.625rem 1.5rem;border:<?php echo $col['border'] ? '2px solid var(--fg)' : '0'; ?>;border-radius:.5rem;background:var(--ac);color:var(--at);font-weight:600;text-decoration:none}
.btn:hover{background:var(--ah)}
.btn:focus-visible{outline:2px solid var(--fg);outline-offset:3px}
footer{padding:1.5rem 1rem;font-size:.875rem;color:var(--mu)}
footer p{margin:0}
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
<?php if ( $button ) : ?>
<p><a class="btn" href="<?php echo $esc( $button[0] ); ?>"><?php echo $esc( $button[1] ); ?></a></p>
<?php endif; ?>
</main>
<footer><p>&copy; <?php echo $esc( $year . ( '' !== $name ? ' ' . $name : '' ) ); ?></p></footer>
</body>
</html>
<?php
    }
}
