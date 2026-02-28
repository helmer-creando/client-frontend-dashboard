<?php
/**
 * ============================================================
 * Module: Styles
 * ============================================================
 *
 * Migrated from: Snippet 3 — "Client Dashboard — Styles"
 *
 * Changes from the original:
 * • CSS extracted to assets/css/dashboard.css (browser-cacheable)
 * • Uses wp_enqueue_style() instead of echoing inline <style>
 * • cd_get_config() → cfd_get_config()
 * ============================================================
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_enqueue_scripts', 'cfd_enqueue_dashboard_styles' );

function cfd_enqueue_dashboard_styles(): void {
    $config = cfd_get_config();

    if ( ! is_page( $config['dashboard_slug'] ) ) {
        return;
    }
    if ( ! is_user_logged_in() ) {
        return;
    }

    wp_enqueue_style(
        'cfd-dashboard',
        CFD_URL . 'assets/css/dashboard.css',
        array(), // No dependencies.
        CFD_VERSION
    );
}
