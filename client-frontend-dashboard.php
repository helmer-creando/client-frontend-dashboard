<?php
/**
 * Plugin Name:       Client Frontend Dashboard
 * Plugin URI:        https://autentiweb.com/plugins/client-frontend-dashboard
 * Description:       A grandma-proof frontend dashboard for clients to edit pages, images, and CPT content — without ever touching wp-admin.
 * Version:           3.0.0-beta4
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            AutentiWeb
 * Author URI:        https://autentiweb.com
 * License:           GPL-2.0-or-later
 * Text Domain:       cfd
 */

if (!defined('ABSPATH')) {
    exit;
}

// ─── Plugin constants ───────────────────────────────────────
define('CFD_VERSION', '3.0.0-beta4');
define('CFD_PATH', plugin_dir_path(__FILE__));
define('CFD_URL', plugin_dir_url(__FILE__));

// ─── Auto-updater (checks GitHub Releases for new versions) ──
// Uses YahnisElsts/plugin-update-checker v5.6.
// To release an update: create a GitHub release tagged vX.X.X
// with the version matching the Version header above.
// NOTE: Uses GitHub Releases mode (not branch mode) — the library
// compares the release tag against CFD_VERSION to detect updates.
require_once CFD_PATH . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

try {
    $cfd_update_checker = PucFactory::buildUpdateChecker(
        'https://github.com/helmer-creando/client-frontend-dashboard/',
        __FILE__,
        'client-frontend-dashboard'
    );
    // Include pre-releases (beta, RC) in update checks.
    $cfd_update_checker->getVcsApi()->enableReleaseAssets();
// Use GitHub Releases mode (default) — no setBranch() needed.
// The updater will match release tags like "v2.2.0" against the
// Version header. The "v" prefix is stripped automatically.
}
catch (\Throwable $e) {
    // Never let the update checker crash the site.
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('CFD Update Checker error: ' . $e->getMessage());
    }
}

// ─── Load configuration (must be first) ─────────────────────
require_once CFD_PATH . 'includes/config.php';

// ─── Load plugin modules ────────────────────────────────────
require_once CFD_PATH . 'includes/roles-and-access.php';
require_once CFD_PATH . 'includes/dashboard-renderer.php';
require_once CFD_PATH . 'includes/styles.php';
require_once CFD_PATH . 'includes/login.php';

// ─── Admin settings page (only loads in wp-admin) ────────────
if (is_admin()) {
    require_once CFD_PATH . 'includes/admin-settings.php';

    // Add "Settings" link next to "Deactivate" on the Plugins page.
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
        $settings_url = admin_url('options-general.php?page=cfd-settings');
        $settings_link = '<a href="' . esc_url($settings_url) . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    });
}

// ─── Activation hook ────────────────────────────────────────
register_activation_hook(__FILE__, 'cfd_activate');

function cfd_activate(): void
{
    cfd_register_site_editor_role();
    cfd_sync_cpt_caps(true);
    flush_rewrite_rules();
}

// ─── Deactivation hook ──────────────────────────────────────
register_deactivation_hook(__FILE__, 'cfd_deactivate');

function cfd_deactivate(): void
{
    delete_option('cfd_cpt_caps_version');
    flush_rewrite_rules();
}