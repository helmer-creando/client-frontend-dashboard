<?php
/**
 * Plugin Name:       Client Frontend Dashboard
 * Plugin URI:        https://youragency.com/plugins/client-frontend-dashboard
 * Description:       A grandma-proof frontend dashboard for clients to edit pages, images, and CPT content — without ever touching wp-admin.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://youragency.com
 * License:           GPL-2.0-or-later
 * Text Domain:       cfd
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─── Plugin constants ───────────────────────────────────────
define( 'CFD_VERSION', '2.0.0' );
define( 'CFD_PATH',    plugin_dir_path( __FILE__ ) );
define( 'CFD_URL',     plugin_dir_url( __FILE__ ) );

// ─── Load configuration (must be first) ─────────────────────
require_once CFD_PATH . 'includes/config.php';

// ─── Load plugin modules ────────────────────────────────────
require_once CFD_PATH . 'includes/roles-and-access.php';
require_once CFD_PATH . 'includes/dashboard-renderer.php';
require_once CFD_PATH . 'includes/styles.php';
require_once CFD_PATH . 'includes/login.php';

// ─── Activation hook ────────────────────────────────────────
register_activation_hook( __FILE__, 'cfd_activate' );

function cfd_activate(): void {
    cfd_register_site_editor_role();
    cfd_sync_cpt_caps( true );
    flush_rewrite_rules();
}

// ─── Deactivation hook ──────────────────────────────────────
register_deactivation_hook( __FILE__, 'cfd_deactivate' );

function cfd_deactivate(): void {
    delete_option( 'cfd_cpt_caps_version' );
    flush_rewrite_rules();
}
