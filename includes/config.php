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
 * Before the plugin existed, config values were duplicated
 * across multiple places. Now there's one source of truth.
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
 * Number of CPT entries shown per page on the dashboard list view.
 * Used by cfd_render_cpt_list() for pagination.
 */
if ( ! defined( 'CFD_POSTS_PER_PAGE' ) ) {
    define( 'CFD_POSTS_PER_PAGE', 20 );
}

/**
 * Detects whether the current request is inside the Bricks Builder editor.
 *
 * Used to prevent redirects and other frontend-only logic from
 * firing while editing templates in Bricks. Four detection methods
 * cover all Bricks editor contexts:
 *
 * 1. bricks_is_builder()      — standard Bricks function
 * 2. bricks_is_builder_main() — main builder instance check
 * 3. $_GET['bricks']          — URL parameter used by the builder
 * 4. DOING_AJAX               — builder sends data via AJAX
 *
 * @return bool True if currently inside the Bricks editor.
 */
function cfd_is_bricks_builder(): bool {
    return (
        ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) ||
        ( function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main() ) ||
        isset( $_GET['bricks'] ) ||
        ( defined( 'DOING_AJAX' ) && DOING_AJAX )
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
