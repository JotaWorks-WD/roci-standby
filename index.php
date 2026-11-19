<?php
/**
 * Roci Standby — The Only Template
 *
 * Every front-end route lands here and prints the standby page. Headers
 * (503 / 200) are already set by roci_standby_headers() on template_redirect.
 *
 * File:    index.php
 * Version: 1.0.0
 * Updated: 2026-11-19
 *
 * @package RociStandby
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

roci_standby_render( function_exists( 'roci_standby_template_args' ) ? roci_standby_template_args() : array( 'context' => 'standby' ) );
exit;
