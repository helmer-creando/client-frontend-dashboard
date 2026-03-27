<?php
/**
 * ============================================================
 * Module: Roles & Access Control
 * ============================================================
 *
 * Handles everything related to who can do what:
 *
 * 1. Registers the "site_editor" custom role with minimal caps.
 * 2. Grants CPT-specific capabilities (with DB-write optimization).
 * 3. Redirects site_editor users to the dashboard after login.
 * 4. Blocks site_editor users from wp-admin (allows admin-ajax).
 * 5. Protects the dashboard page from logged-out visitors.
 * 6. Prevents caching on dashboard and login pages.
 * 7. Hides the admin bar for site_editor users.
 * 8. Redirects logout to the login page (not wp-login.php).
 *
 * Migrated from: Snippet 1 — "Client Dashboard — Role & Lockout"
 *
 * ── Changes from the original snippet ──
 * • SECURITY FIX: Removed 'delete_others_posts' from the base
 *   role. This generic cap allowed deleting any blog post by
 *   anyone — unintended since edit_posts was explicitly off.
 *   CPT-specific delete caps are granted separately via
 *   cfd_sync_cpt_caps().
 *
 * • PERFORMANCE FIX: CPT capabilities are now synced using a
 *   version flag ('cfd_cpt_caps_version' in wp_options). The
 *   original code called $role->add_cap() 24 times on every
 *   single page load — each one a DB write. Now it only writes
 *   when the config changes.
 *
 * • All hardcoded slugs ('mi-espacio', 'capitan') replaced with
 *   cfd_get_config() calls from the centralized config.
 *
 * • Logout redirect in Snippet 1 is now handled here (was
 *   duplicated with Snippet 4's logout handler). This module
 *   handles the wp_logout hook redirect; Snippet 4's module
 *   will handle the custom ?action=logout flow.
 * ============================================================
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// ═══════════════════════════════════════════════════════════
// 1. REGISTER THE CUSTOM ROLE
// ═══════════════════════════════════════════════════════════
//
// Creates a lean "site_editor" role with only the permissions
// clients need. This runs on `init` but bails immediately if
// the role already exists — no DB writes on normal page loads.
//
// The role is also created during plugin activation via
// register_activation_hook() in the main plugin file.
//
// If you change capabilities below, bump CFD_CAPS_VERSION
// and the sync function will update the role automatically.

// ── Ensure CFD pages never 404 for logged-in users ────────
//
// Some environments (security plugins, caching layers, or roles
// without the "read" capability) can cause WordPress to return
// a 404 for logged-in users on the login or dashboard pages.
// We fix this at two levels:
//
// 1. Grant "read" dynamically via user_has_cap so WP_Query
//    doesn't reject the page during capability checks.
// 2. As a fallback, rescue the 404 on template_redirect by
//    re-querying the page directly when we know it exists.

add_filter( 'user_has_cap', 'cfd_grant_read_on_cfd_pages', 10, 4 );

function cfd_grant_read_on_cfd_pages( array $allcaps, array $caps, array $args, $user ): array {
    if ( ! in_array( 'read', $caps, true ) ) {
        return $allcaps;
    }
    if ( ! empty( $allcaps['read'] ) ) {
        return $allcaps;
    }

    $config  = cfd_get_config();
    $slugs   = array( $config['login_slug'], $config['dashboard_slug'] );
    $request = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

    if ( in_array( $request, $slugs, true ) ) {
        $allcaps['read'] = true;
    }

    return $allcaps;
}

add_action( 'template_redirect', 'cfd_rescue_404_on_cfd_pages', 1 );

function cfd_rescue_404_on_cfd_pages(): void {
    if ( ! is_404() ) {
        return;
    }

    $config  = cfd_get_config();
    $slugs   = array( $config['login_slug'], $config['dashboard_slug'] );
    $request = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

    if ( ! in_array( $request, $slugs, true ) ) {
        return;
    }

    // The page exists but WP_Query missed it. Re-query directly.
    $page = get_page_by_path( $request );
    if ( ! $page || $page->post_status !== 'publish' ) {
        return;
    }

    // Override the main query so WordPress renders the page.
    global $wp_query;
    $wp_query = new \WP_Query( array(
        'page_id' => $page->ID,
    ) );

    status_header( 200 );
}

add_action( 'init', 'cfd_register_site_editor_role' );

function cfd_register_site_editor_role(): void {
    // Bail if the role already exists in the database.
    if ( wp_roles()->is_role( 'site_editor' ) ) {
        return;
    }

    add_role( 'site_editor', __( 'Site Editor (Client)', 'cfd' ), array(
        // ── Core reading ──
        'read'                 => true,

        // ── Pages: edit existing only, no create/delete ──
        'edit_pages'           => true,
        'edit_published_pages' => true,
        'edit_others_pages'    => true,  // Needed to edit pages they didn't create.

        // ── Media: upload images via the media modal ──
        'upload_files'         => true,

        // ── Everything else is explicitly OFF ──
        // Being explicit here documents intent and prevents
        // WordPress from granting unexpected defaults.
        'publish_pages'          => false,
        'delete_pages'           => false,
        'delete_published_pages' => false,
        'create_pages'           => false,
        'manage_categories'      => false,
        'edit_posts'             => false,  // "posts" = blog posts; off by default.
        'edit_theme_options'     => false,
        'manage_options'         => false,
        'install_plugins'        => false,
        'edit_plugins'           => false,

        // NOTE: 'delete_others_posts' was removed from this list.
        // The original snippet included it here, which granted
        // permission to delete ANY blog post by any author —
        // even though edit_posts was off. CPT-specific delete
        // caps are granted separately by cfd_sync_cpt_caps().
    ) );
}


// ═══════════════════════════════════════════════════════════
// 2. SYNC CPT CAPABILITIES (with DB-write optimization)
// ═══════════════════════════════════════════════════════════
//
// Grants the site_editor role the capabilities it needs to
// manage the CPTs listed in cfd_get_config()['manageable_cpts'].
//
// ── Why this is different from the original ──
// The original snippet called $role->add_cap() inside a loop
// on every page load. With 3 CPTs × 8 caps = 24 DB writes
// per request. That's unnecessary because capabilities persist
// in wp_options once written.
//
// This version uses a "caps version" flag. It only writes to
// the DB when:
// - The plugin is activated (via register_activation_hook)
// - The version string changes (when you add/remove CPTs)
// - You manually call cfd_sync_cpt_caps( true ) to force it
//
// ── How to trigger a re-sync ──
// Option A: Bump the version string below and reload any page.
// Option B: Deactivate and reactivate the plugin.
// Option C: Delete the 'cfd_cpt_caps_version' option in the DB.

/**
 * Version string for CPT capabilities.
 * Bump this whenever you change the CPT list or the caps granted.
 * Format: 'vX-slug1-slug2-slug3' so changes are self-documenting.
 */
define( 'CFD_CAPS_VERSION', 'v1-retreats-testimonials-faq' );

add_action( 'init', 'cfd_maybe_sync_cpt_caps', 999 );

function cfd_maybe_sync_cpt_caps(): void {
    // Only re-sync if the version has changed.
    if ( get_option( 'cfd_cpt_caps_version' ) === CFD_CAPS_VERSION ) {
        return;
    }

    cfd_sync_cpt_caps();
}

/**
 * Grants CPT capabilities to the site_editor role.
 *
 * @param bool $force If true, skip the version check (used during activation).
 */
function cfd_sync_cpt_caps( bool $force = false ): void {
    // Skip the version check if forced (activation hook).
    if ( ! $force && get_option( 'cfd_cpt_caps_version' ) === CFD_CAPS_VERSION ) {
        return;
    }

    $role = get_role( 'site_editor' );
    if ( ! $role ) {
        return;
    }

    $config    = cfd_get_config();
    $cpt_slugs = $config['manageable_cpts'];

    foreach ( $cpt_slugs as $slug ) {
        $cpt_obj = get_post_type_object( $slug );
        if ( ! $cpt_obj ) {
            // CPT not registered yet. This can happen if the CPT
            // plugin loads after ours. The next page load (or
            // manual re-activation) will catch it.
            continue;
        }

        // Get the actual capability names for this CPT.
        // If the CPT uses capability_type => 'post' (default),
        // these will be generic names like 'edit_posts'.
        // If it uses a custom capability_type, these will be
        // specific names like 'edit_retreats'.
        $caps = $cpt_obj->cap;

        $role->add_cap( $caps->edit_posts );
        $role->add_cap( $caps->edit_published_posts );
        $role->add_cap( $caps->publish_posts );
        $role->add_cap( $caps->delete_posts );
        $role->add_cap( $caps->delete_published_posts );
        $role->add_cap( $caps->delete_others_posts );
        $role->add_cap( $caps->edit_others_posts );
        $role->add_cap( $caps->create_posts );
    }

    // Save the version flag so we don't run this again until
    // the version string changes.
    update_option( 'cfd_cpt_caps_version', CFD_CAPS_VERSION, true );
}


// ═══════════════════════════════════════════════════════════
// 3. REDIRECT AFTER LOGIN
// ═══════════════════════════════════════════════════════════
//
// Sends site_editor users to the frontend dashboard instead
// of wp-admin after logging in.

add_filter( 'login_redirect', 'cfd_login_redirect', 10, 3 );

function cfd_login_redirect( string $redirect_to, string $requested_redirect_to, $user ): string {
    // $user can be a WP_Error on failed login — bail gracefully.
    if ( ! $user instanceof WP_User ) {
        return $redirect_to;
    }

    if ( in_array( 'site_editor', $user->roles, true ) ) {
        $config = cfd_get_config();
        return home_url( $config['login_redirect'] );
    }

    return $redirect_to;
}


// ═══════════════════════════════════════════════════════════
// 4. BLOCK WP-ADMIN ACCESS
// ═══════════════════════════════════════════════════════════
//
// If a site_editor user tries to visit /wp-admin directly,
// redirect them to the dashboard. We MUST allow these through:
// - admin-ajax.php — ACF frontend forms, media uploader, TinyMCE
// - REST API requests — some plugins use these on the frontend

add_action( 'admin_init', 'cfd_block_wp_admin_for_clients' );

function cfd_block_wp_admin_for_clients(): void {
    // Allow AJAX requests — these are headless API calls,
    // not actual admin page visits.
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
        return;
    }

    // Allow REST API requests.
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        return;
    }

    $user = wp_get_current_user();

    if ( in_array( 'site_editor', $user->roles, true ) ) {
        $config = cfd_get_config();
        wp_safe_redirect( home_url( '/' . $config['dashboard_slug'] . '/' ) );
        exit;
    }
}


// ═══════════════════════════════════════════════════════════
// 5. PROTECT DASHBOARD FROM LOGGED-OUT VISITORS
// ═══════════════════════════════════════════════════════════
//
// If someone visits /mi-espacio/ without being logged in,
// redirect them to the login page with a redirect_to param
// so they land back on the dashboard after logging in.

add_action( 'template_redirect', 'cfd_protect_dashboard_page' );

function cfd_protect_dashboard_page(): void {
    $config = cfd_get_config();

    if ( ! is_page( $config['dashboard_slug'] ) ) {
        return;
    }

    if ( is_user_logged_in() ) {
        return;
    }

    // Build the login URL with a redirect_to parameter.
    $login_url = add_query_arg(
        'redirect_to',
        urlencode( home_url( '/' . $config['dashboard_slug'] . '/' ) ),
        home_url( '/' . $config['login_slug'] . '/' )
    );

    wp_safe_redirect( $login_url );
    exit;
}


// ═══════════════════════════════════════════════════════════
// 5b. PREVENT CACHING ON DASHBOARD & LOGIN
// ═══════════════════════════════════════════════════════════
//
// The dashboard shows user-specific ACF form data that must
// always be fresh. Caching plugins (LiteSpeed, WP Super Cache,
// W3 Total Cache) will serve stale HTML if we don't tell them
// to skip these pages.
//
// This sets:
// 1. HTTP no-cache headers (browser + CDN)
// 2. LiteSpeed-specific no-cache header
// 3. DONOTCACHEPAGE constant (respected by most WP cache plugins)

add_action( 'template_redirect', 'cfd_prevent_dashboard_caching', 5 );

function cfd_prevent_dashboard_caching(): void {
    $no_cache_slugs = cfd_get_no_cache_slugs();

    $should_skip = false;
    foreach ( $no_cache_slugs as $slug ) {
        if ( is_page( $slug ) ) {
            $should_skip = true;
            break;
        }
    }

    if ( ! $should_skip ) {
        return;
    }

    // Standard HTTP no-cache headers.
    nocache_headers();

    // LiteSpeed Cache specific — tells LSCWP to skip this response.
    header( 'X-LiteSpeed-Cache-Control: no-cache' );

    // Respected by WP Super Cache, W3 Total Cache, LiteSpeed, etc.
    if ( ! defined( 'DONOTCACHEPAGE' ) ) {
        define( 'DONOTCACHEPAGE', true );
    }
}


// ═══════════════════════════════════════════════════════════
// 6. HIDE THE ADMIN BAR
// ═══════════════════════════════════════════════════════════
//
// The admin bar is confusing for clients and breaks the
// "this is a custom app" illusion. Hide it completely.

add_action( 'after_setup_theme', 'cfd_hide_admin_bar_for_clients' );

function cfd_hide_admin_bar_for_clients(): void {
    if ( ! is_user_logged_in() ) {
        return;
    }

    $user = wp_get_current_user();

    if ( in_array( 'site_editor', $user->roles, true ) ) {
        show_admin_bar( false );
    }
}


// ═══════════════════════════════════════════════════════════
// 7. REDIRECT LOGOUT TO LOGIN PAGE
// ═══════════════════════════════════════════════════════════
//
// After logging out, clients shouldn't land on the bare
// wp-login.php screen. Send them to the custom login page.
//
// NOTE: This handles the standard wp_logout hook. The custom
// ?action=logout flow (needed when Perfmatters blocks
// wp-login.php) is handled in the login module (Module 4).

add_action( 'wp_logout', 'cfd_redirect_after_logout' );

function cfd_redirect_after_logout(): void {
    $config = cfd_get_config();
    wp_safe_redirect( home_url( '/' . $config['login_slug'] . '/' ) );
    exit;
}


// ═══════════════════════════════════════════════════════════
// UTILITY: Reset the role and re-sync capabilities
// ═══════════════════════════════════════════════════════════
//
// Call this from WP-CLI or a temporary snippet if you need
// to completely rebuild the role (e.g., after changing the
// base capabilities above).
//
// Usage (WP-CLI): wp eval 'cfd_reset_role();'
// Usage (snippet): cfd_reset_role();
//
// After running, the role will be re-created on the next
// page load by cfd_register_site_editor_role() and
// cfd_maybe_sync_cpt_caps().

function cfd_reset_role(): void {
    remove_role( 'site_editor' );
    delete_option( 'cfd_cpt_caps_version' );

    // Re-create immediately.
    cfd_register_site_editor_role();
    cfd_sync_cpt_caps( true );
}


// ═══════════════════════════════════════════════════════════
// 8. PER-USER ACCESS — PROFILE FIELDS
// ═══════════════════════════════════════════════════════════
//
// Adds a "Client Dashboard Access" section to the WordPress
// User Edit screen (Users → Edit) for non-admin users.
//
// The admin can:
// - Toggle "Restrict access" to enable per-user filtering
// - Check which CPTs the user can manage
// - Check which pages the user can edit
//
// When restriction is off, the user sees everything enabled
// site-wide (preserves current behavior for existing users).

add_action( 'edit_user_profile', 'cfd_render_user_access_fields' );
add_action( 'show_user_profile', 'cfd_render_user_access_fields' );

/**
 * Renders per-user CFD access fields on the user profile screen.
 *
 * Shown for any non-admin user (site_editor, custom roles, etc.)
 * and only visible to admins who can manage_options.
 *
 * @param WP_User $user The user being edited.
 */
function cfd_render_user_access_fields( WP_User $user ): void {
    // Only show for non-admin users (admins don't need restrictions).
    if ( $user->has_cap( 'manage_options' ) ) {
        return;
    }

    // Only admins can configure access.
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $config        = cfd_get_config();
    $restrict      = get_user_meta( $user->ID, 'cfd_restrict_access', true ) === '1';
    $user_cpts     = get_user_meta( $user->ID, 'cfd_user_cpts', true );
    $user_pages    = get_user_meta( $user->ID, 'cfd_user_pages', true );

    if ( ! is_array( $user_cpts ) )  { $user_cpts  = array(); }
    if ( ! is_array( $user_pages ) ) { $user_pages = array(); }

    // Get the pool of available CPTs and pages.
    $available_cpts = $config['manageable_cpts'];
    $editable_pages = cfd_get_editable_pages( $config );
    ?>

    <h2>Client Dashboard Access</h2>
    <p class="description">
        Control which CPTs and pages this user can see in the frontend dashboard.
        When restriction is <strong>off</strong>, the user sees everything enabled in
        <a href="<?php echo esc_url( admin_url( 'options-general.php?page=cfd-settings' ) ); ?>">Settings → Client Dashboard</a>.
    </p>

    <table class="form-table" role="presentation">
        <!-- Restrict toggle -->
        <tr>
            <th scope="row">Restrict access</th>
            <td>
                <label>
                    <input
                        type="checkbox"
                        name="cfd_restrict_access"
                        id="cfd_restrict_access"
                        value="1"
                        <?php checked( $restrict ); ?>
                    />
                    Enable per-user access restrictions for this user
                </label>
                <p class="description">
                    When checked, only the CPTs and pages selected below will appear
                    in this user's dashboard.
                </p>
            </td>
        </tr>

        <!-- CPT checkboxes -->
        <tr class="cfd-restrict-row" <?php echo ! $restrict ? 'style="display:none;"' : ''; ?>>
            <th scope="row">Allowed CPTs</th>
            <td>
                <?php if ( empty( $available_cpts ) ) : ?>
                    <p class="description" style="color: #888;">
                        No CPTs are enabled site-wide. Enable them in
                        <a href="<?php echo esc_url( admin_url( 'options-general.php?page=cfd-settings' ) ); ?>">Settings → Client Dashboard</a> first.
                    </p>
                <?php else : ?>
                    <fieldset>
                        <?php foreach ( $available_cpts as $cpt_slug ) :
                            $cpt_obj = get_post_type_object( $cpt_slug );
                            $cpt_label = $cpt_obj ? $cpt_obj->labels->name : ucwords( str_replace( array( '-', '_' ), ' ', $cpt_slug ) );
                            $checked = in_array( $cpt_slug, $user_cpts, true );
                        ?>
                        <label style="display: block; margin-bottom: 0.4em;">
                            <input
                                type="checkbox"
                                name="cfd_user_cpts[]"
                                value="<?php echo esc_attr( $cpt_slug ); ?>"
                                <?php checked( $checked ); ?>
                            />
                            <strong><?php echo esc_html( $cpt_label ); ?></strong>
                            <code style="margin-left: 0.3em; color: #888;"><?php echo esc_html( $cpt_slug ); ?></code>
                        </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endif; ?>
            </td>
        </tr>

        <!-- Page checkboxes -->
        <tr class="cfd-restrict-row" <?php echo ! $restrict ? 'style="display:none;"' : ''; ?>>
            <th scope="row">Allowed Pages</th>
            <td>
                <?php if ( empty( $editable_pages ) ) : ?>
                    <p class="description" style="color: #888;">
                        No editable pages found (pages need ACF field groups to appear here).
                    </p>
                <?php else : ?>
                    <fieldset>
                        <?php foreach ( $editable_pages as $page ) :
                            $checked = in_array( $page->ID, $user_pages, true );
                        ?>
                        <label style="display: block; margin-bottom: 0.4em;">
                            <input
                                type="checkbox"
                                name="cfd_user_pages[]"
                                value="<?php echo esc_attr( $page->ID ); ?>"
                                <?php checked( $checked ); ?>
                            />
                            <strong><?php echo esc_html( $page->post_title ); ?></strong>
                            <code style="margin-left: 0.3em; color: #888;">ID: <?php echo esc_html( $page->ID ); ?></code>
                        </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <script>
    jQuery(function($) {
        var $toggle = $('#cfd_restrict_access');
        var $rows   = $('.cfd-restrict-row');

        $toggle.on('change', function() {
            if ( $(this).is(':checked') ) {
                $rows.show();
            } else {
                $rows.hide();
            }
        });
    });
    </script>
    <?php
}


// ── Save per-user access fields ─────────────────────────────

add_action( 'edit_user_profile_update', 'cfd_save_user_access_fields' );
add_action( 'personal_options_update',  'cfd_save_user_access_fields' );

/**
 * Saves per-user CFD access fields from the user profile form.
 *
 * @param int $user_id The user ID being saved.
 */
function cfd_save_user_access_fields( int $user_id ): void {
    // Only admins can change access settings.
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Only process for non-admin users.
    $user = get_userdata( $user_id );
    if ( ! $user || $user->has_cap( 'manage_options' ) ) {
        return;
    }

    // ── Restrict toggle ──
    $restrict = ! empty( $_POST['cfd_restrict_access'] ) ? '1' : '0';
    update_user_meta( $user_id, 'cfd_restrict_access', $restrict );

    // ── CPTs ──
    if ( isset( $_POST['cfd_user_cpts'] ) && is_array( $_POST['cfd_user_cpts'] ) ) {
        $cpts = array_map( 'sanitize_key', $_POST['cfd_user_cpts'] );
    } else {
        $cpts = array();
    }
    update_user_meta( $user_id, 'cfd_user_cpts', $cpts );

    // ── Pages ──
    if ( isset( $_POST['cfd_user_pages'] ) && is_array( $_POST['cfd_user_pages'] ) ) {
        $pages = array_map( 'absint', $_POST['cfd_user_pages'] );
    } else {
        $pages = array();
    }
    update_user_meta( $user_id, 'cfd_user_pages', $pages );
}

