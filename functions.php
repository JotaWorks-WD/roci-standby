<?php
/**
 * Roci Standby — Theme Bootstrap
 *
 * The WP_DEFAULT_THEME fallback. Every front-end request renders the
 * standby page: a 503 for visitors, a 200 with a notice bar for
 * administrators. wp-admin is untouched.
 *
 * File:    functions.php
 * Version: 1.0.0
 * Updated: 2026-11-19
 *
 * @package RociStandby
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/inc/standby-page.php';


// ============================================================
// THEME SUPPORTS
// ============================================================

function roci_standby_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'custom-logo' );
}
add_action( 'after_setup_theme', 'roci_standby_setup' );


// ============================================================
// REMEMBER THE PREVIOUS THEME (logo fallback)
// ============================================================

/**
 * Core clears the theme_switched option on the request after a switch, so
 * the slug of the theme we replaced is kept here for the renderer's
 * "previous theme's custom_logo" step.
 *
 * @param string         $old_name  Old theme name.
 * @param WP_Theme|false $old_theme Old theme object.
 */
function roci_standby_remember_previous_theme( $old_name, $old_theme = false ) {
    if ( $old_theme instanceof WP_Theme && 'roci-standby' !== $old_theme->get_stylesheet() ) {
        update_option( 'roci_standby_previous_theme', $old_theme->get_stylesheet(), false );
    }
}
add_action( 'after_switch_theme', 'roci_standby_remember_previous_theme', 10, 2 );


// ============================================================
// FRONT-END REQUESTS
// ============================================================

/**
 * roci_standby_viewer_is_admin()
 *
 * @return bool True for a logged-in user who can manage_options.
 */
function roci_standby_viewer_is_admin() {
    return is_user_logged_in() && current_user_can( 'manage_options' );
}

/**
 * Headers for every front-end request. Visitors (anyone without
 * manage_options): 503 + Retry-After + noindex. Administrators: 200.
 * Both: no-cache, and LiteSpeed told not to cache.
 */
function roci_standby_headers() {
    if ( is_admin() ) {
        return;
    }

    if ( roci_standby_viewer_is_admin() ) {
        status_header( 200 );
    } else {
        status_header( 503 );
        header( 'Retry-After: 3600' );
    }

    header( 'X-Robots-Tag: noindex' );
    nocache_headers();
    do_action( 'litespeed_control_set_nocache', 'roci-standby' );
}
add_action( 'template_redirect', 'roci_standby_headers', 1 );

/**
 * roci_standby_template_args()
 *
 * The renderer args for index.php: the admin notice bar for administrators.
 *
 * @return array
 */
function roci_standby_template_args() {
    $args = array( 'context' => 'standby' );

    if ( roci_standby_viewer_is_admin() ) {
        $args['notice'] = sprintf(
            /* translators: %s: link to Appearance → Themes. */
            __( 'Standby theme active. Visitors see this page (HTTP 503). Switch back: %s', 'roci-standby' ),
            '<a href="' . esc_url( admin_url( 'themes.php' ) ) . '">' . esc_html__( 'Appearance → Themes', 'roci-standby' ) . '</a>'
        );
    }

    return $args;
}
