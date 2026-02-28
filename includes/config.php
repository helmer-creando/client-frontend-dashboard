<?php
/**
 * ============================================================
 * CFD Configuration — Edit This Per Project
 * ============================================================
 *
 * This is the ONLY file you need to change when deploying
 * the plugin to a new client site. All site-specific values
 * (slugs, CPTs, page IDs) are centralized here.
 *
 * In your original snippets, these values were duplicated
 * across cd_get_config(), cd_login_config(), and several
 * hardcoded 'capitan' / 'mi-espacio' strings. Now there's
 * one source of truth.
 * ============================================================
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Returns the full plugin configuration array.
 *
 * Every module calls this function instead of hardcoding slugs.
 * When you set up a new client site, edit the values below.
 *
 * @return array {
 *     @type string   $dashboard_slug   Slug of the dashboard page (e.g., 'mi-espacio').
 *     @type string   $login_slug       Slug of the login page (e.g., 'capitan').
 *     @type string   $login_redirect   Path to redirect to after login (e.g., '/mi-espacio/').
 *     @type int[]    $editable_pages   Page IDs clients can edit. Empty = auto-detect.
 *     @type string[] $manageable_cpts  CPT slugs clients can list/create/edit/delete.
 * }
 */
function cfd_get_config(): array {
    return array(

        // ── Dashboard page slug ─────────────────────────────
        // The WordPress page where [client_dashboard] lives.
        // Use a human, colloquial slug — not dev-speak.
        // Examples: 'mi-espacio', 'my-space', 'my-dashboard'
        'dashboard_slug'  => 'mi-espacio',

        // ── Login page slug ─────────────────────────────────
        // The WordPress page where [cd_login_form] lives.
        // This replaces the default wp-login.php for clients.
        // Examples: 'capitan', 'login', 'acceso'
        'login_slug'      => 'capitan',

        // ── Post-login redirect path ────────────────────────
        // Where site_editor users land after logging in.
        // Must match the dashboard slug above (with slashes).
        'login_redirect'  => '/mi-espacio/',

        // ── Editable pages ──────────────────────────────────
        // Page IDs the client can edit from the dashboard.
        // Leave empty to auto-detect all published pages that
        // have ACF field groups assigned to them.
        // Or hardcode: array( 2, 15, 23, 40, 55 )
        'editable_pages'  => array(),

        // ── Manageable CPTs ─────────────────────────────────
        // Post type slugs the client can list, create, edit,
        // and delete from the dashboard.
        // These must match your registered CPT slugs exactly.
        'manageable_cpts' => array(
            'retreats',
            'testimonials',
            'faq',
        ),
    );
}

/**
 * Returns the slugs of pages that should never be cached.
 *
 * Both the dashboard and login page show user-specific content
 * (ACF form data, login state) that must always be fresh.
 * This list is derived from the config so you don't repeat yourself.
 *
 * @return string[]
 */
function cfd_get_no_cache_slugs(): array {
    $config = cfd_get_config();
    return array(
        $config['dashboard_slug'],
        $config['login_slug'],
    );
}
