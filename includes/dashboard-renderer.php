<?php
/**
 * ============================================================
 * Module: Dashboard Renderer
 * ============================================================
 *
 * Migrated from: Snippet 2 — "Client Dashboard — ACF Form Renderer"
 *
 * Changes from the original:
 * • Removed cd_get_config() — now uses cfd_get_config() from config.php
 * • All cd_ prefixes → cfd_
 * • Dashboard page ID is cached via cfd_get_dashboard_url() helper
 *   (was calling get_page_by_path() 5-8 times per request)
 * • Nonce value in delete handler is now sanitized
 * • Inline JS moved to assets/js/dashboard.js (browser-cacheable)
 * ============================================================
 */

if (!defined('ABSPATH')) {
    exit;
}

// ─── HELPER: Render a Material Symbols icon by codepoint ────
// The self-hosted font is subset to ~15 KB by dropping GSUB (ligatures),
// so icons must be rendered by their PUA codepoint, not by typed name.
// Adding a new icon: append a codepoint here AND re-run pyftsubset
// (see docs/feature-plan-v3.8.md §2G).

function cfd_icon(string $name, string $extra_class = ''): string
{
    static $map = array(
        'arrow_back'         => 'E5C4',
        'add_circle'         => 'E3BA',
        'edit'               => 'F097',
        'delete'             => 'E92E',
        'search_off'         => 'EA76',
        'inbox'              => 'E156',
        'check_circle'       => 'F0BE',
        'open_in_new'        => 'E89E',
        'file_copy'          => 'E173',
        'more_vert'          => 'E5D4',
        'save'               => 'E161',
        'auto_awesome'       => 'E65F',
        'auto_fix'           => 'E663',
        'chevron_left'       => 'E5CB',
        'chevron_right'      => 'E5CC',
        'filter_alt'         => 'EF4F',
        'lightbulb'          => 'E90F',
        'web'                => 'E051',
        // Phase 2 (v3.8).
        'visibility'         => 'E8F4',
        'visibility_off'     => 'E8F5',
        'restore_from_trash' => 'E938',
        'delete_forever'     => 'E92B',
        // Chip / quick-toggle icons (admin-configurable in CPT chips).
        'star'               => 'F09A',
        'flag'               => 'F0C6',
        'event'              => 'E878',
        'bookmark'           => 'E8E7',
        'favorite'           => 'E87E',
        'verified'           => 'EF76',
        'category'           => 'E574',
        'label'              => 'E893',
        'circle'             => 'EF4A',
        'schedule'           => 'EFD6',
        'place'              => 'F1DB',
        'language'           => 'E894',
    );
    $cp = isset($map[$name]) ? $map[$name] : '';
    if ($cp === '') {
        // In dev, surface missing icon names; in prod just render nothing.
        return defined('WP_DEBUG') && WP_DEBUG ? '<span class="material-symbols-outlined" aria-hidden="true">?</span>' : '';
    }
    $cls = 'material-symbols-outlined' . ($extra_class !== '' ? ' ' . $extra_class : '');
    return '<span class="' . esc_attr($cls) . '" aria-hidden="true">&#x' . $cp . ';</span>';
}

// ─── HELPER: Get dashboard page URL (cached) ────────────────
// The original snippet called get_page_by_path() + get_permalink()
// in every render function. Each is a DB query. Now we compute
// it once and cache it in a static variable.

function cfd_get_dashboard_url(): string
{
    static $url = null;
    if ($url === null) {
        $config = cfd_get_config();
        $page = get_page_by_path($config['dashboard_slug']);
        $url = $page ? get_permalink($page) : home_url('/' . $config['dashboard_slug'] . '/');

        // Extensibility hook: allow addons to modify the dashboard base URL.
        $url = apply_filters( 'cfd_dashboard_url', $url, array() );
    }
    return $url;
}

// ═══════════════════════════════════════════════════════════
// 1. CALL acf_form_head() BEFORE ANY OUTPUT
// ═══════════════════════════════════════════════════════════

add_action('wp', 'cfd_maybe_load_acf_form_head');

function cfd_maybe_load_acf_form_head(): void
{
    $config = cfd_get_config();
    if (!is_page($config['dashboard_slug'])) {
        return;
    }
    if (!is_user_logged_in()) {
        return;
    }
    if (!function_exists('acf_form_head')) {
        return;
    }
    acf_form_head();
}

// ═══════════════════════════════════════════════════════════
// 2. HANDLE CPT DELETION
// ═══════════════════════════════════════════════════════════

add_action('template_redirect', 'cfd_handle_cpt_delete');

function cfd_handle_cpt_delete(): void
{
    $config = cfd_get_config();

    if (!is_page($config['dashboard_slug'])) {
        return;
    }
    if (!is_user_logged_in()) {
        return;
    }

    $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
    $post_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    // FIX: Sanitize the nonce value (was raw in original).
    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';

    if ($action !== 'trash' || $post_id < 1) {
        return;
    }

    if (!wp_verify_nonce($nonce, 'cfd_trash_' . $post_id)) {
        return;
    }

    if (!current_user_can('delete_post', $post_id)) {
        return;
    }

    // Use per-user config to validate access.
    $user_config = cfd_get_user_config();
    $post = get_post($post_id);
    $cpt_slug = $post ? $post->post_type : '';

    if (!in_array($cpt_slug, $user_config['manageable_cpts'], true)) {
        return;
    }

    wp_trash_post($post_id);

    // Pass the trashed ID + a restore nonce so the post-redirect toast can
    // offer an AJAX "Deshacer" without another round-trip for the nonce.
    $redirect_url = add_query_arg(
        array(
            'manage'         => $cpt_slug,
            'trashed'        => 'true',
            'trashed_id'     => $post_id,
            'trashed_nonce'  => wp_create_nonce('cfd_restore_' . $post_id),
        ),
        cfd_get_dashboard_url()
    );

    wp_safe_redirect($redirect_url);
    exit;
}

// ═══════════════════════════════════════════════════════════
// 2b. HANDLE CPT DUPLICATION
// ═══════════════════════════════════════════════════════════

add_action('template_redirect', 'cfd_handle_cpt_duplicate');

function cfd_handle_cpt_duplicate(): void
{
    $config = cfd_get_config();

    if (!is_page($config['dashboard_slug'])) {
        return;
    }
    if (!is_user_logged_in()) {
        return;
    }

    $cpt_slug = isset($_GET['duplicate']) ? sanitize_key($_GET['duplicate']) : '';
    $post_id  = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $nonce    = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';

    if ($cpt_slug === '' || $post_id < 1) {
        return;
    }

    if (!wp_verify_nonce($nonce, 'cfd_duplicate_' . $post_id)) {
        return;
    }

    $original = get_post($post_id);
    if (!$original || !current_user_can('edit_post', $post_id)) {
        return;
    }

    // Use per-user config to validate access.
    $user_config = cfd_get_user_config();
    if (!in_array($original->post_type, $user_config['manageable_cpts'], true)) {
        return;
    }

    // Clone the post.
    $new_id = wp_insert_post(array(
        'post_type'   => $original->post_type,
        'post_title'  => $original->post_title . ' (copia)',
        'post_status' => 'draft',
        'post_author' => get_current_user_id(),
    ));

    if (is_wp_error($new_id)) {
        return;
    }

    // Clone all post meta (includes ACF fields).
    $meta = get_post_meta($post_id);
    foreach ($meta as $key => $values) {
        if (str_starts_with($key, '_edit_')) {
            continue; // Skip edit-lock meta.
        }
        foreach ($values as $value) {
            add_post_meta($new_id, $key, maybe_unserialize($value));
        }
    }

    // Clone taxonomies.
    $taxonomies = get_object_taxonomies($original->post_type);
    foreach ($taxonomies as $tax) {
        $terms = wp_get_object_terms($post_id, $tax, array('fields' => 'ids'));
        if (!is_wp_error($terms) && !empty($terms)) {
            wp_set_object_terms($new_id, $terms, $tax);
        }
    }

    // Redirect to the new post's editor.
    $redirect_url = add_query_arg(
        array('edit' => $cpt_slug, 'id' => $new_id, 'duplicated' => 'true'),
        cfd_get_dashboard_url()
    );

    wp_safe_redirect($redirect_url);
    exit;
}

// ═══════════════════════════════════════════════════════════
// 2c. HANDLE VISIBILITY TOGGLE (publish ↔ draft)
// ═══════════════════════════════════════════════════════════
// The "hide" mechanism: flip post_status. WP keeps every other field
// untouched, so toggling back is lossless.

add_action('template_redirect', 'cfd_handle_cpt_visibility');

function cfd_handle_cpt_visibility(): void
{
    $config = cfd_get_config();
    if (!is_page($config['dashboard_slug'])) {
        return;
    }
    if (!is_user_logged_in()) {
        return;
    }

    $action  = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
    $post_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $nonce   = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';

    if ($action !== 'toggle_visibility' || $post_id < 1) {
        return;
    }
    if (!wp_verify_nonce($nonce, 'cfd_visibility_' . $post_id)) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $post = get_post($post_id);
    if (!$post) {
        return;
    }
    $cpt_slug = $post->post_type;

    $user_config = cfd_get_user_config();
    if (!in_array($cpt_slug, $user_config['manageable_cpts'], true)) {
        return;
    }

    // Flip status. Anything that isn't 'publish' becomes 'publish'; 'publish'
    // becomes 'draft'. Trashed posts can't be toggled (they aren't visible
    // in the main listing — the Papelera handles those).
    if ($post->post_status === 'trash') {
        return;
    }
    $new_status = ($post->post_status === 'publish') ? 'draft' : 'publish';
    wp_update_post(array(
        'ID'          => $post_id,
        'post_status' => $new_status,
    ));

    $flag = ($new_status === 'draft') ? 'hidden' : 'shown';

    // Preserve return context: if the user came from the editor, send them
    // back there; otherwise to the listing.
    $from_editor = isset($_GET['from']) && $_GET['from'] === 'editor';
    if ($from_editor) {
        $redirect_url = add_query_arg(
            array('edit' => $cpt_slug, 'id' => $post_id, $flag => 'true'),
            cfd_get_dashboard_url()
        );
    } else {
        $redirect_url = add_query_arg(
            array('manage' => $cpt_slug, $flag => 'true'),
            cfd_get_dashboard_url()
        );
    }

    wp_safe_redirect($redirect_url);
    exit;
}

// ═══════════════════════════════════════════════════════════
// 2d. HANDLE TRASH RESTORE (Papelera → Restaurar)
// ═══════════════════════════════════════════════════════════
// wp_untrash_post() reads _wp_trash_meta_status and returns the post
// to its prior state (publish or draft). Free with WP.

add_action('template_redirect', 'cfd_handle_cpt_restore');

function cfd_handle_cpt_restore(): void
{
    $config = cfd_get_config();
    if (!is_page($config['dashboard_slug'])) {
        return;
    }
    if (!is_user_logged_in()) {
        return;
    }

    $action  = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
    $post_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $nonce   = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';

    if ($action !== 'restore' || $post_id < 1) {
        return;
    }
    if (!wp_verify_nonce($nonce, 'cfd_restore_' . $post_id)) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'trash') {
        return;
    }
    $cpt_slug = $post->post_type;

    $user_config = cfd_get_user_config();
    if (!in_array($cpt_slug, $user_config['manageable_cpts'], true)) {
        return;
    }

    wp_untrash_post($post_id);

    $redirect_url = add_query_arg(
        array('manage' => $cpt_slug, 'view' => 'trash', 'restored' => 'true'),
        cfd_get_dashboard_url()
    );
    wp_safe_redirect($redirect_url);
    exit;
}

// ═══════════════════════════════════════════════════════════
// 2e. HANDLE PERMANENT DELETE (Papelera → Eliminar definitivamente)
// ═══════════════════════════════════════════════════════════
// Requires both a nonce AND a server-side POST token ('ELIMINAR')
// so client-side modal logic can't be bypassed.

add_action('template_redirect', 'cfd_handle_cpt_delete_forever');

function cfd_handle_cpt_delete_forever(): void
{
    $config = cfd_get_config();
    if (!is_page($config['dashboard_slug'])) {
        return;
    }
    if (!is_user_logged_in()) {
        return;
    }

    // POST-only (form submission), not GET — extra safety against accidental
    // clicks on copy-pasted URLs.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $action  = isset($_POST['cfd_action']) ? sanitize_key($_POST['cfd_action']) : '';
    $post_id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $nonce   = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';
    $token   = isset($_POST['cfd_confirm']) ? sanitize_text_field(wp_unslash($_POST['cfd_confirm'])) : '';

    if ($action !== 'delete_forever' || $post_id < 1) {
        return;
    }
    if (!wp_verify_nonce($nonce, 'cfd_delete_forever_' . $post_id)) {
        return;
    }
    if (strtoupper(trim($token)) !== 'ELIMINAR') {
        return;
    }
    if (!current_user_can('delete_post', $post_id)) {
        return;
    }

    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'trash') {
        return;
    }
    $cpt_slug = $post->post_type;

    $user_config = cfd_get_user_config();
    if (!in_array($cpt_slug, $user_config['manageable_cpts'], true)) {
        return;
    }

    wp_delete_post($post_id, true); // force=true bypasses trash, hard delete.

    $redirect_url = add_query_arg(
        array('manage' => $cpt_slug, 'view' => 'trash', 'deleted_forever' => 'true'),
        cfd_get_dashboard_url()
    );
    wp_safe_redirect($redirect_url);
    exit;
}

// ═══════════════════════════════════════════════════════════
// 2f. APPLY SAVE-INTENT FROM DASHBOARD FORMS
// ═══════════════════════════════════════════════════════════
// ACF's acf_form() supports only one submit button via html_submit_button.
// We add a secondary button (Guardar borrador / Guardar y ocultar /
// Guardar y publicar) with name="cfd_save_as" and override the resulting
// post_status here, after ACF has written its field values.

add_action('acf/save_post', 'cfd_apply_save_intent', 20);

function cfd_apply_save_intent($post_id): void
{
    if (!is_numeric($post_id)) {
        return;
    }
    $post_id = (int) $post_id;
    if ($post_id < 1) {
        return;
    }

    $intent = isset($_POST['cfd_save_as']) ? sanitize_key($_POST['cfd_save_as']) : '';
    if (!in_array($intent, array('draft', 'publish'), true)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $post = get_post($post_id);
    if (!$post) {
        return;
    }

    $user_config = cfd_get_user_config();
    if (!in_array($post->post_type, $user_config['manageable_cpts'], true)) {
        return;
    }

    if ($post->post_status === $intent) {
        return;
    }

    wp_update_post(array(
        'ID'          => $post_id,
        'post_status' => $intent,
    ));
}

// ═══════════════════════════════════════════════════════════
// 3. ENQUEUE ASSETS ON DASHBOARD
// ═══════════════════════════════════════════════════════════

add_action('wp_enqueue_scripts', 'cfd_enqueue_dashboard_assets');

function cfd_enqueue_dashboard_assets(): void
{
    $config = cfd_get_config();
    if (!is_page($config['dashboard_slug'])) {
        return;
    }
    if (!is_user_logged_in()) {
        return;
    }

    // Material Symbols Outlined — self-hosted, subsetted to ~15 KB.
    // Renders via codepoint (see cfd_icon helper); GSUB stripped to keep size down.
    // `font-display: block` (in the CSS file) prevents the FOIT-to-literal-text flash.
    wp_enqueue_style(
        'cfd-material-symbols',
        CFD_URL . 'assets/css/material-symbols.css',
        array(),
        CFD_VERSION
    );

    // WordPress media uploader (needed for ACF image fields).
    wp_enqueue_media();

    // WordPress color picker (required for ACF to render color picker on frontend).
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');

    // iro.js — touch-friendly color picker replacement (bundled, ~28KB).
    wp_enqueue_script(
        'cfd-iro',
        CFD_URL . 'assets/js/vendor/iro.min.js',
        array(),
        '5.5.2',
        true
    );
    // CDN Fallback for iro.js
    wp_add_inline_script('cfd-iro', 'if(typeof iro === "undefined"){ var s = document.createElement("script"); s.src="https://cdn.jsdelivr.net/npm/@jaames/iro@5"; document.head.appendChild(s); }');

    // ACF field enhancements (color picker swap, Select2 adjustments, etc.)
    wp_enqueue_script(
        'cfd-acf-fields',
        CFD_URL . 'assets/js/acf-fields.js',
        array('wp-color-picker', 'cfd-iro'),
        CFD_VERSION,
        true
    );

    // Dashboard JS (extracted from inline <script> in original).
    wp_enqueue_script(
        'cfd-dashboard',
        CFD_URL . 'assets/js/dashboard.js',
        array(), // No dependencies — vanilla JS.
        CFD_VERSION,
        true // Load in footer.
    );

    // Pass URLs/nonces to dashboard JS for quick-toggle + undo-toast requests.
    wp_localize_script( 'cfd-dashboard', 'cfdData', array(
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'restUrl' => esc_url_raw( rest_url( 'cfd/v1/' ) ),
        'i18n'    => array(
            'trashedToast'  => 'movido a la papelera.',
            'undoLabel'     => 'Deshacer',
            'closeLabel'    => 'Cerrar',
            'restoredToast' => 'Restaurado.',
            'undoError'     => 'No se pudo deshacer. Recarga e inténtalo desde la papelera.',
        ),
    ) );
}

// ═══════════════════════════════════════════════════════════
// 4. REGISTER THE SHORTCODE
// ═══════════════════════════════════════════════════════════

add_shortcode('client_dashboard', 'cfd_render_dashboard');

function cfd_render_dashboard(): string
{
    if (!is_user_logged_in()) {
        return '<p class="cd-error">Por favor, inicia sesión para acceder al panel.</p>';
    }

    $user = wp_get_current_user();
    $config = cfd_get_user_config();

    $action = isset($_GET['edit']) ? sanitize_key($_GET['edit']) : '';
    $manage = isset($_GET['manage']) ? sanitize_key($_GET['manage']) : '';
    $create = isset($_GET['create']) ? sanitize_key($_GET['create']) : '';
    $post_id = isset($_GET['id']) ? absint($_GET['id']) : 0;

    ob_start();

    echo '<div class="cd-dashboard">';

    if ($action === 'page' && $post_id > 0) {
        cfd_render_page_editor($post_id, $user);
    }
    elseif ($action && $post_id > 0 && in_array($action, $config['manageable_cpts'], true)) {
        cfd_render_cpt_editor($action, $post_id, $user);
    }
    elseif ($manage && in_array($manage, $config['manageable_cpts'], true)) {
        $view = isset($_GET['view']) ? sanitize_key($_GET['view']) : '';
        if ($view === 'trash') {
            cfd_render_cpt_trash($manage, $user);
        } else {
            cfd_render_cpt_list($manage, $user);
        }
    }
    elseif ($create && in_array($create, $config['manageable_cpts'], true)) {
        cfd_render_cpt_creator($create, $user);
    }
    else {
        cfd_render_dashboard_home($user, $config);
    }

    echo '</div><!-- .cd-dashboard -->';

    return ob_get_clean();
}

// ═══════════════════════════════════════════════════════════
// 4b. v3.0 — COMPOSABLE SHORTCODES
// ═══════════════════════════════════════════════════════════
//
// These smaller shortcodes are designed for use inside Bricks
// templates. They output individual pieces of the dashboard
// so Bricks can handle the surrounding layout/chrome.
//
// The original [client_dashboard] shortcode (above) is kept
// unchanged for backward compatibility.
//
// ─────────────────────────────────────────────────────────

/**
 * [cfd_sidebar_nav] — Dynamic sidebar navigation.
 *
 * Automatically builds the sidebar nav from the plugin config:
 * 1. Inicio (home link)
 * 2. Páginas (if editable pages exist)
 * 3. Each manageable CPT (from settings)
 *
 * Each item uses the Dashicon registered with the CPT in WordPress
 * (or ACF). Pages use `dashicons-admin-page`, Inicio uses
 * `dashicons-dashboard`. The current view gets an `is-active` class.
 *
 * Usage in Bricks: Add a Shortcode element with [cfd_sidebar_nav]
 * inside the sidebar div.
 */
add_shortcode('cfd_sidebar_nav', 'cfd_render_sidebar_nav');

function cfd_render_sidebar_nav(): string
{
    if (!is_user_logged_in()) {
        return '';
    }

    // Ensure Dashicons are available on the frontend.
    wp_enqueue_style('dashicons');

    $config = cfd_get_user_config();
    $dashboard_url = cfd_get_dashboard_url();
    $view = cfd_get_dashboard_view();

    // Determine which nav item is "active".
    // Note: "Páginas" now serves as the home link (shows on dashboard home view)
    $active_slug = '';
    if ($view === 'home' || $view === '' || $view === 'edit_page') {
        $active_slug = 'pages';
    }
    elseif ($view === 'manage' && isset($_GET['manage'])) {
        $active_slug = sanitize_key($_GET['manage']);
    }
    elseif ($view === 'edit_cpt' && isset($_GET['edit'])) {
        $active_slug = sanitize_key($_GET['edit']);
    }
    elseif ($view === 'create' && isset($_GET['create'])) {
        $active_slug = sanitize_key($_GET['create']);
    }

    ob_start();

    echo '<nav class="cfd-sidebar-nav" aria-label="Dashboard navigation">';
    echo '<ul class="cfd-sidebar-nav__list">';

    // ── 1. Páginas (always first, links to dashboard home) ──
    $pages = cfd_get_editable_pages($config);
    if (!empty($pages)) {
        $active_class = ($active_slug === 'pages') ? ' is-active' : '';
        echo '<li class="cfd-sidebar-nav__item' . $active_class . '">';
        echo '  <a href="' . esc_url($dashboard_url) . '" class="cfd-sidebar-nav__link">';
        echo '    <span class="dashicons dashicons-admin-page cfd-sidebar-nav__icon"></span>';
        echo '    <span class="cfd-sidebar-nav__label">Páginas</span>';
        echo '  </a>';
        echo '</li>';
    }

    // ── 3. Manageable CPTs ──
    if (!empty($config['manageable_cpts'])) {
        echo '<li class="cfd-sidebar-nav__divider" aria-hidden="true"></li>';

        foreach ($config['manageable_cpts'] as $cpt_slug) {
            $cpt_obj = get_post_type_object($cpt_slug);
            if (!$cpt_obj) {
                continue;
            }

            // Check if user has permission to edit this CPT.
            if (!current_user_can($cpt_obj->cap->edit_posts)) {
                continue;
            }

            // Resolve icon: Dashicon class, SVG URL, or fallback.
            $icon_html = cfd_get_cpt_icon_html($cpt_obj);

            $manage_url = add_query_arg(array('manage' => $cpt_slug), $dashboard_url);
            $active_class = ($active_slug === $cpt_slug) ? ' is-active' : '';

            echo '<li class="cfd-sidebar-nav__item' . $active_class . '">';
            echo '  <a href="' . esc_url($manage_url) . '" class="cfd-sidebar-nav__link">';
            echo '    ' . $icon_html;
            echo '    <span class="cfd-sidebar-nav__label">' . esc_html($cpt_obj->labels->name) . '</span>';
            echo '  </a>';
            echo '</li>';
        }
    }

    echo '</ul>';
    echo '</nav>';

    return ob_get_clean();
}

/**
 * Returns the HTML for a CPT's menu icon.
 *
 * WordPress stores CPT icons as:
 * - A Dashicon class string like 'dashicons-calendar'
 * - A full URL to an SVG/PNG
 * - null (no icon set → use default)
 *
 * @param WP_Post_Type $cpt_obj  The post type object.
 * @return string  HTML for the icon.
 */
function cfd_get_cpt_icon_html($cpt_obj): string
{
    $icon = $cpt_obj->menu_icon;
    $css_class = 'cfd-sidebar-nav__icon';

    // No icon → default to dashicons-admin-post.
    if (empty($icon)) {
        return '<span class="dashicons dashicons-admin-post ' . $css_class . '"></span>';
    }

    // Dashicon class (starts with "dashicons-").
    if (strpos($icon, 'dashicons-') === 0) {
        return '<span class="dashicons ' . esc_attr($icon) . ' ' . $css_class . '"></span>';
    }

    // URL to image (SVG or PNG).
    if (filter_var($icon, FILTER_VALIDATE_URL) || strpos($icon, 'data:') === 0) {
        return '<img src="' . esc_url($icon) . '" alt="" class="' . $css_class . ' ' . $css_class . '--img" width="20" height="20">';
    }

    // Fallback.
    return '<span class="dashicons dashicons-admin-post ' . $css_class . '"></span>';
}

/**
 * [cfd_page_cards] — Renders the editable page card grid.
 *
 * Outputs the clickable page cards for the dashboard home view.
 * Does NOT include section titles or hero greeting — those are
 * built in the Bricks template using native Bricks elements.
 *
 * Usage in Bricks: Add a Shortcode element with [cfd_page_cards]
 * inside the "Home View" conditional section.
 */
add_shortcode('cfd_page_cards', 'cfd_render_page_cards_shortcode');

function cfd_render_page_cards_shortcode(): string
{
    if (!is_user_logged_in()) {
        return '';
    }

    $config = cfd_get_user_config();
    $pages = cfd_get_editable_pages($config);

    // Extensibility hook: allow addons to filter the page cards array.
    $pages = apply_filters( 'cfd_page_cards', $pages );

    $dashboard_url = cfd_get_dashboard_url();

    if (empty($pages)) {
        return '<p style="color: var(--text-dark-muted, #8A817A); font-style: italic;">Aún no hay páginas editables.</p>';
    }

    ob_start();

    echo '<div class="cd-page-grid">';

    foreach ($pages as $page) {
        $edit_url = add_query_arg(array('edit' => 'page', 'id' => $page->ID), $dashboard_url);

        echo '<a href="' . esc_url($edit_url) . '" class="cd-page-card">';
        echo '  <span class="cd-page-card__icon"><span class="dashicons dashicons-admin-page"></span></span>';
        echo '  <span class="cd-page-card__title">' . esc_html($page->post_title) . '</span>';
        echo '  <span class="cd-page-card__hint">Editar →</span>';
        echo '</a>';
    }

    echo '</div>';

    return ob_get_clean();
}

/**
 * Helper function for Bricks builder conditions.
 *
 * Usage in Bricks:
 *   - Native condition: "Has Manageable CPTs" (preferred)
 *   - Echo tag: `{echo:cfd_has_manageable_cpts}` == 1 (requires whitelist)
 *
 * Returns true if the user has manageable CPTs, false otherwise.
 *
 * @return bool
 */
function cfd_has_manageable_cpts(): bool
{
    // Safety guard: In Bricks builder AJAX/early contexts, user functions
    // may not be fully loaded yet. Return false to prevent errors.
    // This is safe because the builder context doesn't need real user data.
    if (!function_exists('is_user_logged_in') || !function_exists('current_user_can')) {
        return false;
    }

    if (!is_user_logged_in()) {
        return false;
    }

    $config = cfd_get_user_config();
    if (empty($config['manageable_cpts'])) {
        return false;
    }

    foreach ($config['manageable_cpts'] as $cpt_slug) {
        $cpt_obj = get_post_type_object($cpt_slug);
        if ($cpt_obj && current_user_can($cpt_obj->cap->edit_posts)) {
            return true;
        }
    }

    return false;
}

/**
 * [cfd_cpt_cards] — Renders the CPT card grid.
 *
 * Outputs clickable cards for each manageable CPT, linking to
 * their list/management view. Does NOT include section titles.
 *
 * Usage in Bricks: Add a Shortcode element with [cfd_cpt_cards]
 * inside the "Home View" conditional section.
 */
add_shortcode('cfd_cpt_cards', 'cfd_render_cpt_cards_shortcode');

function cfd_render_cpt_cards_shortcode(): string
{
    if (!is_user_logged_in()) {
        return '';
    }

    $config = cfd_get_user_config();
    $dashboard_url = cfd_get_dashboard_url();

    if (empty($config['manageable_cpts'])) {
        return '';
    }

    ob_start();

    echo '<div class="cd-page-grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">';

    foreach ($config['manageable_cpts'] as $cpt_slug) {
        $cpt_obj = get_post_type_object($cpt_slug);
        if (!$cpt_obj) {
            continue;
        }

        // Check if user has permission to edit this CPT.
        if (!current_user_can($cpt_obj->cap->edit_posts)) {
            continue;
        }

        $manage_url = add_query_arg(array('manage' => $cpt_slug), $dashboard_url);

        // Get the CPT's assigned icon, fallback to a default CPT icon
        $icon = 'dashicons-admin-post';
        if (!empty($cpt_obj->menu_icon) && strpos($cpt_obj->menu_icon, 'dashicons-') === 0) {
            $icon = esc_attr($cpt_obj->menu_icon);
        }

        echo '<a href="' . esc_url($manage_url) . '" class="cd-page-card">';
        echo '  <span class="cd-page-card__icon"><span class="dashicons ' . $icon . '"></span></span>';
        echo '  <span class="cd-page-card__title">' . esc_html($cpt_obj->labels->name) . '</span>';
        echo '  <span class="cd-page-card__hint">Administrar →</span>';
        echo '</a>';
    }

    echo '</div>';

    return ob_get_clean();
}

/**
 * [cfd_quick_links] — Renders the Quick Access Links card grid.
 *
 * Outputs admin-configured shortcut cards that link to external
 * plugin pages (FluentCRM, WooCommerce, Bookly, etc.). Only renders
 * if quick links have been configured in Settings.
 *
 * Usage in Bricks: Add a Shortcode element with [cfd_quick_links]
 * inside the "Home View" conditional section, after CPT cards.
 */
add_shortcode('cfd_quick_links', 'cfd_render_quick_links_shortcode');

function cfd_render_quick_links_shortcode(): string
{
    if (!is_user_logged_in()) {
        return '';
    }

    $settings = get_option('cfd_settings', array());
    $links = isset($settings['quick_links']) ? $settings['quick_links'] : array();
    if (empty($links)) {
        return '';
    }

    // Ensure Dashicons are available on the frontend.
    wp_enqueue_style('dashicons');

    ob_start();

    echo '<div class="cd-section">';
    echo '<h2 class="cd-home__title"><span>Herramientas</span></h2>';
    echo '<div class="cd-page-grid">';

    foreach ($links as $link) {
        $url   = esc_url($link['url'] ?? '');
        $label = esc_html($link['label'] ?? '');
        $icon  = esc_attr($link['icon'] ?? 'dashicons-admin-generic');
        $hint  = esc_html(($link['hint'] ?? '') !== '' ? $link['hint'] : 'Abrir →');

        $target_attr = '';
        if (($link['target'] ?? '_blank') === '_blank') {
            $target_attr = ' target="_blank" rel="noopener noreferrer"';
        }

        echo '<a href="' . $url . '" class="cd-page-card"' . $target_attr . '>';
        echo '  <span class="cd-page-card__icon"><span class="dashicons ' . $icon . '"></span></span>';
        echo '  <span class="cd-page-card__title">' . $label . '</span>';
        echo '  <span class="cd-page-card__hint">' . $hint . '</span>';
        echo '</a>';
    }

    echo '</div>';
    echo '</div>';

    return ob_get_clean();
}

/**
 * [cfd_view_router] — Renders non-home dashboard views.
 *
 * When edit/manage/create URL params are present, this shortcode
 * renders the appropriate view (page editor, CPT list, CPT editor,
 * CPT creator). When no params are present (home view), it returns
 * an empty string — the Bricks template handles the home layout.
 *
 * Usage in Bricks: Add a Shortcode element with [cfd_view_router]
 * inside a "NOT Home View" conditional section.
 */
add_shortcode('cfd_view_router', 'cfd_render_view_router');

function cfd_render_view_router(): string
{
    if (!is_user_logged_in()) {
        return '<p class="cd-error">Por favor, inicia sesión para acceder al panel.</p>';
    }

    $view = cfd_get_dashboard_view();

    // Home view → nothing to render (Bricks handles it).
    if ($view === 'home' || $view === '') {
        return '';
    }

    $user = wp_get_current_user();
    $config = cfd_get_user_config();
    $post_id = isset($_GET['id']) ? absint($_GET['id']) : 0;

    ob_start();

    echo '<div class="cd-dashboard">';

    switch ($view) {
        case 'edit_page':
            cfd_render_page_editor($post_id, $user);
            break;

        case 'edit_cpt':
            $cpt_slug = sanitize_key($_GET['edit']);
            cfd_render_cpt_editor($cpt_slug, $post_id, $user);
            break;

        case 'manage':
            $cpt_slug = sanitize_key($_GET['manage']);
            $sub_view = isset($_GET['view']) ? sanitize_key($_GET['view']) : '';
            if ($sub_view === 'trash') {
                cfd_render_cpt_trash($cpt_slug, $user);
            } else {
                cfd_render_cpt_list($cpt_slug, $user);
            }
            break;

        case 'create':
            $cpt_slug = sanitize_key($_GET['create']);
            cfd_render_cpt_creator($cpt_slug, $user);
            break;
    }

    echo '</div><!-- .cd-dashboard -->';

    return ob_get_clean();
}

// ═══════════════════════════════════════════════════════════
// v3.1 — PERSONALIZED WELCOME & HELP HINTS
// ═══════════════════════════════════════════════════════════

/**
 * Returns a contextual help hint string for a given dashboard view.
 *
 * @param string $view      One of: 'edit_page', 'manage', 'edit_cpt', 'create'.
 * @param string $cpt_label CPT label (plural or singular) for placeholder replacement.
 * @return string The hint text, or empty string if view is unknown.
 */
function cfd_get_view_hint( $view, $cpt_label = '' ): string {
    switch ( $view ) {
        case 'edit_page':
            return 'Edita el contenido y haz clic en "Guardar cambios" cuando termines.';
        case 'manage':
            return sprintf(
                'Aquí puedes ver, buscar y editar tus %s.',
                esc_html( $cpt_label )
            );
        case 'edit_cpt':
            return 'Modifica los campos y guarda. Los cambios se publican de inmediato.';
        case 'create':
            return sprintf(
                'Rellena los campos para crear un nuevo %s.',
                esc_html( $cpt_label )
            );
        default:
            return '';
    }
}

/**
 * Renders a view hint <p> tag if hints are enabled in settings.
 *
 * @param string $view      Dashboard view identifier.
 * @param string $cpt_label CPT label for placeholder replacement.
 */
function cfd_maybe_render_view_hint( $view, $cpt_label = '' ): void {
    $settings = get_option( 'cfd_settings', array() );
    $show_hints = isset( $settings['show_hints'] ) ? (bool) $settings['show_hints'] : true;

    if ( ! $show_hints ) {
        return;
    }

    $hint = cfd_get_view_hint( $view, $cpt_label );
    if ( $hint !== '' ) {
        echo '<p class="cd-view-hint">' . esc_html( $hint ) . '</p>';
    }
}

/**
 * Renders the gold "Concierge Tip" card used across the redesign.
 *
 * @param string $title The tip heading.
 * @param string $text  The tip body text.
 */
function cfd_render_concierge_tip( string $title, string $text ): void {
    echo '<div class="kh-tip-card">';
    echo '  <div class="kh-tip-card__icon">';
    echo '    ' . cfd_icon('lightbulb', 'kh-icon--filled') . '';
    echo '  </div>';
    echo '  <div>';
    echo '    <h3 class="kh-tip-card__title">' . esc_html( $title ) . '</h3>';
    echo '    <p class="kh-tip-card__text">' . esc_html( $text ) . '</p>';
    echo '  </div>';
    echo '</div>';
}

/**
 * Renders the shared delete-confirm modal. The modal stays hidden until
 * a [data-cfd-delete] trigger fires; the JS reads data-title/data-trash-url
 * off the trigger and populates the modal before showing it.
 */
function cfd_render_delete_modal(): void {
    echo '<div class="cfd-confirm-modal" id="cfd-delete-modal" hidden role="dialog" aria-modal="true" aria-labelledby="cfd-delete-modal-title">';
    echo '  <div class="cfd-confirm-modal__backdrop" data-cfd-modal-close></div>';
    echo '  <div class="cfd-confirm-modal__dialog" role="document">';
    echo '    <h2 id="cfd-delete-modal-title" class="cfd-confirm-modal__title">¿Eliminar esta entrada?</h2>';
    echo '    <p class="cfd-confirm-modal__body">Vas a eliminar <strong data-cfd-modal-target></strong>. Podrás recuperarla desde la papelera durante 30 días.</p>';
    echo '    <div class="cfd-confirm-modal__actions">';
    echo '      <button type="button" class="cfd-confirm-modal__btn cfd-confirm-modal__btn--cancel" data-cfd-modal-close autofocus>Cancelar</button>';
    echo '      <a href="#" class="cfd-confirm-modal__btn cfd-confirm-modal__btn--confirm" data-cfd-modal-confirm>Sí, eliminar</a>';
    echo '    </div>';
    echo '  </div>';
    echo '</div>';
}

// ═══════════════════════════════════════════════════════════
// 5. DASHBOARD HOME — List of editable pages + CPTs
// ═══════════════════════════════════════════════════════════

function cfd_render_dashboard_home(WP_User $user, array $config): void
{
    // Use the per-user config passed from the caller.
    $pages = cfd_get_editable_pages($config);
    $dashboard_url = cfd_get_dashboard_url();

    $name = $user->first_name ?: $user->display_name;
    $settings = get_option( 'cfd_settings', array() );
    $welcome_message = isset( $settings['welcome_message'] ) && $settings['welcome_message'] !== ''
        ? $settings['welcome_message']
        : 'Aquí puedes gestionar el contenido de tu sitio web.';

    echo '<div class="cd-hero">';
    echo '  <h1 class="cd-hero__greeting">Hola, ' . esc_html($name) . ' ☀️</h1>';
    echo '  <p class="cd-hero__subtitle">' . esc_html( $welcome_message ) . '</p>';
    echo '</div>';

    echo '<div class="cd-home">';

    echo '<h2 class="cd-home__title kh-content__title" style="margin-bottom: var(--space-xs, 0.5rem);">Tus Páginas</h2>';
    echo '<p class="cd-home__subtitle kh-editor__subtitle" style="margin-bottom: var(--space-m, 1.5rem);">Haz clic en cualquier página para editar su contenido.</p>';

    // Debug mode — only visible to admins when WP_DEBUG is on.
    if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
        echo '<div class="cd-debug-info">';
        echo '<strong>🔧 Debug info (admin only, WP_DEBUG is on):</strong><br>';
        echo 'Pages found: ' . count($pages) . '<br>';
        echo 'manageable_cpts: [' . implode(', ', $config['manageable_cpts']) . ']';
        echo '</div>';
    }

    if (empty($pages)) {
        echo '<p style="color: var(--text-dark-muted, #8A817A); font-style: italic;">Aún no hay páginas editables.</p>';
    }

    echo '<div class="cd-page-grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">';

    foreach ($pages as $page) {
        $edit_url = add_query_arg(array('edit' => 'page', 'id' => $page->ID), $dashboard_url);
        $view_url = get_permalink($page->ID);
        $thumb_url = get_the_post_thumbnail_url($page->ID, 'medium_large');
        
        $modified_ts = get_the_modified_date('U', $page);
        $time_ago = human_time_diff($modified_ts, current_time('timestamp'));

        echo '<div class="kh-page-card">';
        echo '  <div class="kh-page-card__image">';
        if ($thumb_url) {
            echo '    <img src="' . esc_url($thumb_url) . '" alt="' . esc_attr($page->post_title) . '" />';
        } else {
            // Placeholder pattern for pages without a featured image
            echo '    <div class="kh-page-card__placeholder">';
            echo '      ' . cfd_icon('web') . '';
            echo '    </div>';
        }
        
        // Add a badge if it's the home page
        if (get_option('page_on_front') == $page->ID) {
            echo '    <span class="kh-page-card__badge">Principal</span>';
        }
        echo '  </div>'; // End kh-page-card__image
        
        echo '  <div class="kh-page-card__header">';
        echo '    <h2 class="kh-page-card__title">' . esc_html($page->post_title) . '</h2>';
        echo '    <a class="kh-page-card__view" href="' . esc_url($view_url) . '" target="_blank">';
        echo '      ' . cfd_icon('open_in_new') . ' Ver en línea';
        echo '    </a>';
        echo '  </div>'; // End kh-page-card__header
        
        echo '  <p class="kh-page-card__meta">Último cambio: hace ' . esc_html($time_ago) . '</p>';
        echo '  <a href="' . esc_url($edit_url) . '" class="kh-page-card__btn">';
        echo '    ' . cfd_icon('edit', 'kh-icon--filled') . ' Editar esta página';
        echo '  </a>';
        echo '</div>'; // End kh-page-card
    }

    echo '</div>';

    // CPT sections
    echo '</div>'; // End cd-page-grid

    // ── Quick Access Links (only if configured) ──
    $quick_links_html = cfd_render_quick_links_shortcode();
    if ($quick_links_html !== '') {
        echo '<div style="margin-top: var(--space-l, 2rem);">';
        echo $quick_links_html;
        echo '</div>';
    }

    echo '</div>';
}

// ═══════════════════════════════════════════════════════════
// 6. PAGE EDITOR
// ═══════════════════════════════════════════════════════════

function cfd_render_page_editor(int $post_id, WP_User $user): void
{
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'page') {
        echo '<div class="cd-error">Esta página no existe.</div>';
        return;
    }

    if (!current_user_can('edit_page', $post_id)) {
        echo '<div class="cd-error">No tienes permiso para editar esta página.</div>';
        return;
    }

    // Per-user page access check.
    $user_config = cfd_get_user_config();
    if (!empty($user_config['editable_pages']) && !in_array($post_id, $user_config['editable_pages'], true)) {
        echo '<div class="cd-error">No tienes acceso a esta página.</div>';
        return;
    }

    $field_groups = cfd_get_field_groups_for_post($post_id);

    if (empty($field_groups)) {
        echo '<div class="cd-error">No se encontraron campos editables para esta página.</div>';
        return;
    }

    $dashboard_url = cfd_get_dashboard_url();
    $return_url = add_query_arg(
        array('edit' => 'page', 'id' => $post_id, 'updated' => 'true'),
        $dashboard_url
    );

    echo '<a href="' . esc_url($dashboard_url) . '" class="cd-back-link kh-editor__back">' . cfd_icon('arrow_back') . ' Volver a mis páginas</a>';

    echo '<div class="cd-editor">';
    echo '<div class="cd-editor__header kh-editor__header">';
    echo '  <h1 class="cd-editor__title kh-editor__title">' . esc_html($post->post_title) . '</h1>';
    echo '  <a href="' . esc_url(get_permalink($post_id)) . '" target="_blank" class="cd-preview-link kh-editor__preview">' . cfd_icon('open_in_new') . ' Ver online</a>';
    // View-hint sits inside the header so it appears above the divider line.
    cfd_maybe_render_view_hint( 'edit_page' );
    echo '</div>';

    if (isset($_GET['updated']) && $_GET['updated'] === 'true') {
        echo '<div class="cd-success kh-editor__success">';
        echo '  ' . cfd_icon('check_circle', 'kh-icon--filled') . '';
        echo '  <span>¡Tus cambios han sido guardados!</span>';
        echo '</div>';
    }

    echo '<div class="cd-editor-form kh-editor__grid">';

    acf_form(array(
        'post_id' => $post_id,
        'post_title' => false,
        'post_content' => false,
        'field_groups' => $field_groups,
        'submit_value' => 'Guardar cambios',
        'updated_message' => false,
        'return' => $return_url,
        'html_submit_button' => '<button type="submit" class="cd-save-btn kh-editor__save">' . cfd_icon('save') . ' Guardar cambios</button><span class="kh-editor__save-hint">Los cambios se publican de inmediato.</span>',
        'html_submit_spinner' => '',
        'form_attributes' => array('class' => 'cd-acf-form'),
    ));

    echo '</div>'; // End cd-editor-form

    echo '</div>'; // End cd-editor

    echo '<a href="' . esc_url($dashboard_url) . '" class="cd-back-link cd-back-link--bottom kh-editor__back">' . cfd_icon('arrow_back') . ' Volver a mis páginas</a>';
}

// ═══════════════════════════════════════════════════════════
// 7. CPT LIST
// ═══════════════════════════════════════════════════════════

function cfd_render_cpt_list(string $cpt_slug, WP_User $user): void
{
    $cpt_obj = get_post_type_object($cpt_slug);
    if (!$cpt_obj) {
        echo '<div class="cd-error">Tipo de contenido no encontrado.</div>';
        return;
    }

    $dashboard_url = cfd_get_dashboard_url();

    echo '<a href="' . esc_url($dashboard_url) . '" class="cd-back-link kh-editor__back">' . cfd_icon('arrow_back') . ' Volver a mis páginas</a>';

    if (isset($_GET['trashed']) && $_GET['trashed'] === 'true') {
        echo '<div class="cd-success kh-editor__success">' . cfd_icon('check_circle', 'kh-icon--filled') . ' <span>Entrada eliminada correctamente</span></div>';
    }
    if (isset($_GET['created']) && $_GET['created'] === 'true') {
        echo '<div class="cd-success kh-editor__success">' . cfd_icon('check_circle', 'kh-icon--filled') . ' <span>Entrada creada con éxito</span></div>';
    }
    if (isset($_GET['hidden']) && $_GET['hidden'] === 'true') {
        echo '<div class="cd-success kh-editor__success">' . cfd_icon('check_circle', 'kh-icon--filled') . ' <span>Entrada ocultada. Ya no aparece online.</span></div>';
    }
    if (isset($_GET['shown']) && $_GET['shown'] === 'true') {
        echo '<div class="cd-success kh-editor__success">' . cfd_icon('check_circle', 'kh-icon--filled') . ' <span>Entrada publicada. Ya aparece online.</span></div>';
    }
    if (isset($_GET['restored']) && $_GET['restored'] === 'true') {
        echo '<div class="cd-success kh-editor__success">' . cfd_icon('check_circle', 'kh-icon--filled') . ' <span>Entrada restaurada.</span></div>';
    }
    if (isset($_GET['deleted_forever']) && $_GET['deleted_forever'] === 'true') {
        echo '<div class="cd-success kh-editor__success">' . cfd_icon('check_circle', 'kh-icon--filled') . ' <span>Entrada eliminada permanentemente.</span></div>';
    }

    echo '<div class="cd-cpt-list">';
    echo '<div class="cd-cpt-list__header">';
    echo '  <h1 class="cd-cpt-list__title kh-content__title">' . esc_html($cpt_obj->labels->name) . '</h1>';

    $create_url = add_query_arg(array('create' => $cpt_slug), $dashboard_url);
    echo '  <a href="' . esc_url($create_url) . '" class="cd-add-btn kh-content__add">';
    echo '    ' . cfd_icon('add_circle', 'kh-icon--filled') . ' Agregar nuevo';
    echo '  </a>';
    echo '</div>';
    cfd_maybe_render_view_hint( 'manage', $cpt_obj->labels->name );

    // ── Read & sanitize filter/sort/pagination params ────────
    // Sort uses compound values like 'title-asc', 'date-desc' to encode
    // both field and direction in a single dropdown.
    $sort_options = array(
        'title-asc' => 'A → Z',
        'title-desc' => 'Z → A',
        'date-desc' => 'Más recientes',
        'date-asc' => 'Más antiguos',
        'modified-desc' => 'Últimos cambios',
    );

    $raw_sort = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'title-asc';
    if (!isset($sort_options[$raw_sort])) {
        $raw_sort = 'title-asc';
    }

    $sort_parts = explode('-', $raw_sort, 2);
    $orderby = $sort_parts[0];
    $order = strtoupper($sort_parts[1]);

    $search = isset($_GET['buscar']) ? sanitize_text_field($_GET['buscar']) : '';
    $pag = isset($_GET['pag']) ? max(1, absint($_GET['pag'])) : 1;

    $per_page = CFD_POSTS_PER_PAGE;

    // ── Toolbar: sort dropdown + search ─────────────────────
    // The form GETs to the same page, preserving ?manage=slug.
    $toolbar_action = add_query_arg(array('manage' => $cpt_slug), $dashboard_url);

    // ── Chip config for this CPT (null when none registered) ───
    $chip_config        = cfd_get_cpt_chip_config( $cpt_slug );
    $active_facet_params = ( $chip_config && ! empty( $chip_config['filter_facets'] ) )
        ? cfd_get_active_facet_params( $chip_config['filter_facets'] )
        : array();

    // ── Filter accordion wrapper (collapsed on tablet/mobile) ──
    echo '<details class="cd-cpt-filter-toggle" open>';
    echo '  <summary class="cd-cpt-filter-toggle__summary">';
    echo '    <span class="dashicons dashicons-filter"></span> Filtros';
    echo '    <span class="cd-cpt-filter-toggle__chevron"></span>';
    echo '  </summary>';

    echo '<form method="get" action="' . esc_url($toolbar_action) . '" class="cd-cpt-toolbar">';
    // Preserve the manage param (required for routing).
    echo '  <input type="hidden" name="manage" value="' . esc_attr($cpt_slug) . '">';

    // ── Sort dropdown ──
    echo '  <div class="cd-cpt-toolbar__group">';
    echo '    <label for="cd-orderby" class="cd-cpt-toolbar__label">Ordenar</label>';
    echo '    <select name="orderby" id="cd-orderby" class="cd-cpt-toolbar__select" onchange="this.form.submit()">';

    foreach ($sort_options as $value => $label) {
        $selected = ($raw_sort === $value) ? ' selected' : '';
        echo '<option value="' . esc_attr($value) . '"' . $selected . '>' . esc_html($label) . '</option>';
    }

    echo '    </select>';
    echo '  </div>';

    // ── Search input ──
    echo '  <div class="cd-cpt-toolbar__group cd-cpt-toolbar__group--search">';
    echo '    <label for="cd-buscar" class="cd-cpt-toolbar__label">Buscar</label>';
    echo '    <input type="text" name="buscar" id="cd-buscar" class="cd-cpt-toolbar__search" value="' . esc_attr($search) . '" placeholder="Buscar...">';
    echo '  </div>';

    // ── Taxonomy filter facets (registered via cfd_register_cpt_chips) ──
    if ( $chip_config && ! empty( $chip_config['filter_facets'] ) ) {
        cfd_render_filter_facets( $cpt_slug, $chip_config['filter_facets'] );
    }

    echo '  <button type="submit" class="cd-cpt-toolbar__submit">Filtrar</button>';
    echo '</form>';
    echo '</details>';

    // ── Active search indicator (with clear link) ───────────
    if ($search !== '') {
        $clear_args = array_merge(
            array( 'manage' => $cpt_slug, 'orderby' => $raw_sort ),
            $active_facet_params
        );
        $clear_url = add_query_arg( $clear_args, $dashboard_url );
        echo '<p class="cd-cpt-search-status">';
        echo '  Resultados para "<strong>' . esc_html($search) . '</strong>"';
        echo '  <a href="' . esc_url($clear_url) . '" class="cd-cpt-search-clear">✕ Limpiar</a>';
        echo '</p>';
    }

    // ── Active facet filter summary ──────────────────────────
    if ( $chip_config && ! empty( $chip_config['filter_facets'] ) ) {
        cfd_render_active_filter_summary( $cpt_slug, $chip_config['filter_facets'], $dashboard_url );
    }

    // ── Query posts with WP_Query for pagination support ────
    // Include 'draft' so hidden items appear here with an "Oculto" pill.
    // The dashboard's hide/show toggle flips publish ↔ draft via the
    // visibility handler (see cfd_handle_cpt_visibility).
    $query_args = array(
        'post_type' => $cpt_slug,
        'post_status' => array('publish', 'draft'),
        'posts_per_page' => $per_page,
        'paged' => $pag,
        'orderby' => $orderby,
        'order' => $order,
    );

    if ($search !== '') {
        $query_args['s'] = $search;
    }

    // ── Taxonomy filter from chip facets ─────────────────────
    if ( $chip_config && ! empty( $chip_config['filter_facets'] ) ) {
        $tax_q = cfd_build_tax_query( $chip_config['filter_facets'] );
        if ( ! empty( $tax_q ) ) {
            $query_args['tax_query'] = $tax_q;
        }
    }

    // Extensibility hook: allow addons to modify CPT query args (e.g., language filtering).
    $query_args = apply_filters( 'cfd_cpt_query_args', $query_args, $cpt_slug );

    $query = new WP_Query($query_args);
    $total_posts = $query->found_posts;
    $total_pages = $query->max_num_pages;

    // Clamp current page to valid range.
    if ($pag > $total_pages && $total_pages > 0) {
        $pag = $total_pages;
    }

    if (!$query->have_posts()) {
        if ($search !== '') {
            echo '<div class="kh-empty-state">';
            echo '  <div class="kh-empty-state__icon">' . cfd_icon('search_off') . '</div>';
            echo '  <h3 class="kh-empty-state__title">Sin resultados</h3>';
            echo '  <p class="kh-empty-state__text">No se encontraron entradas para "<strong>' . esc_html($search) . '</strong>". Prueba con otra palabra.</p>';
            echo '</div>';
        }
        else {
            $create_url_empty = add_query_arg(array('create' => $cpt_slug), $dashboard_url);
            echo '<div class="kh-empty-state">';
            echo '  <div class="kh-empty-state__icon">' . cfd_icon('inbox') . '</div>';
            echo '  <h3 class="kh-empty-state__title">Todavía no hay entradas</h3>';
            echo '  <p class="kh-empty-state__text">Crea la primera para que aparezca aquí.</p>';
            echo '  <a href="' . esc_url($create_url_empty) . '" class="kh-empty-state__btn cd-add-btn kh-content__add">';
            echo '    ' . cfd_icon('add_circle', 'kh-icon--filled') . ' Agregar nuevo';
            echo '  </a>';
            echo '</div>';
        }
    }
    else {
        // ── Post count + Papelera pill-badge ──
        $first_item = (($pag - 1) * $per_page) + 1;
        $last_item = min($pag * $per_page, $total_posts);

        // Count trashed posts for this CPT (count-gated badge — invisible at 0).
        $trash_count = 0;
        if ( current_user_can( 'edit_posts' ) ) {
            $trash_query = new WP_Query(array(
                'post_type'      => $cpt_slug,
                'post_status'    => 'trash',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => false,
            ));
            $trash_count = (int) $trash_query->found_posts;
        }

        echo '<div class="cd-cpt-count-row">';
        echo '  <p class="cd-cpt-count">Mostrando ' . $first_item . '–' . $last_item . ' de ' . $total_posts . '</p>';
        if ( $trash_count > 0 ) {
            $trash_view_url = add_query_arg(array('manage' => $cpt_slug, 'view' => 'trash'), $dashboard_url);
            echo '  <a href="' . esc_url($trash_view_url) . '" class="kh-trash-badge" aria-label="' . esc_attr( $trash_count . ' en la papelera' ) . '">';
            echo '    ' . cfd_icon('delete') . '<span class="kh-trash-badge__count">' . (int) $trash_count . '</span>';
            echo '  </a>';
        }
        echo '</div>';

        // ── Grid of cards ──
        echo '<div class="cd-cpt-grid">';
        while ($query->have_posts()) {
            $query->the_post();
            $p = get_post();
            $edit_url = add_query_arg(array('edit' => $cpt_slug, 'id' => $p->ID), $dashboard_url);
            $trash_url = add_query_arg(array(
                'action'   => 'trash',
                'id'       => $p->ID,
                '_wpnonce' => wp_create_nonce('cfd_trash_' . $p->ID),
                'manage'   => $cpt_slug,
            ), $dashboard_url);
            $visibility_url = add_query_arg(array(
                'action'   => 'toggle_visibility',
                'id'       => $p->ID,
                '_wpnonce' => wp_create_nonce('cfd_visibility_' . $p->ID),
                'manage'   => $cpt_slug,
            ), $dashboard_url);

            // Extensibility hook: allow addons to add badges/info to CPT cards.
            $card_badges = apply_filters( 'cfd_cpt_card_badges', array(), $p, $cpt_slug );

            // Last-edited human-readable timestamp.
            $modified_ts = get_the_modified_date('U', $p);
            $time_ago = human_time_diff($modified_ts, current_time('timestamp'));

            $is_draft = ($p->post_status === 'draft');

            // ── Resolve chips for this card ──────────────────
            $card_context_chips   = array();
            $card_status_chips    = array();
            $card_quick_toggle_html = '';
            if ( $chip_config ) {
                if ( ! empty( $chip_config['context_chips'] ) ) {
                    $card_context_chips = cfd_resolve_chips( $chip_config['context_chips'], $p );
                }
                if ( ! empty( $chip_config['status_chips'] ) ) {
                    $card_status_chips = cfd_resolve_chips( $chip_config['status_chips'], $p );
                }
                if ( ! empty( $chip_config['quick_toggles'] ) ) {
                    $card_quick_toggle_html = cfd_render_quick_toggles( $chip_config['quick_toggles'], $p );
                }
            }

            echo '<div class="cd-cpt-card kh-content-item' . ($is_draft ? ' kh-content-item--draft' : '') . '">';

            // Stretched link: makes the entire card clickable while still
            // allowing the explicit Editar/Eliminar buttons on top via z-index.
            echo '  <a href="' . esc_url($edit_url) . '" class="kh-content-item__link" aria-label="' . esc_attr('Editar ' . $p->post_title) . '"></a>';

            echo '  <div class="kh-content-item__info">';
            echo '    <div class="kh-content-item__heading">';
            echo '      <h3 class="kh-content-item__title">' . esc_html($p->post_title) . '</h3>';
            if ($is_draft) {
                echo '      <span class="kh-content-item__status">Oculto</span>';
            }
            echo '    </div>';
            // ── Chip row (context + status chips) ───────────────
            if ( ! empty( $card_context_chips ) || ! empty( $card_status_chips ) ) {
                echo '    <div class="cfd-chip-row">';
                foreach ( $card_context_chips as $chip ) {
                    echo cfd_render_chip_html( $chip );
                }
                foreach ( $card_status_chips as $chip ) {
                    echo cfd_render_chip_html( $chip );
                }
                echo '    </div>';
            }

            echo '    <p class="kh-content-item__meta">Editado hace ' . esc_html($time_ago) . '</p>';
            if ( ! empty( $card_badges ) ) {
                echo '    <div class="cd-cpt-card__badges">' . implode( ' ', array_map( 'wp_kses_post', $card_badges ) ) . '</div>';
            }
            echo '  </div>';

            echo '  <div class="kh-content-item__actions">';
            // Quick-toggle star button (before edit, above stretched link via z-index).
            if ( $card_quick_toggle_html ) {
                echo $card_quick_toggle_html;
            }
            echo '    <a href="' . esc_url($edit_url) . '" class="cd-cpt-card__edit kh-content-item__edit">';
            echo '      ' . cfd_icon('edit') . ' Editar';
            echo '    </a>';
            if (current_user_can('edit_post', $p->ID)) {
                $vis_label = $is_draft ? 'Mostrar' : 'Ocultar';
                $vis_icon  = $is_draft ? 'visibility' : 'visibility_off';
                echo '    <a href="' . esc_url($visibility_url) . '" class="cd-cpt-card__visibility kh-content-item__visibility" aria-label="' . esc_attr($vis_label . ' ' . $p->post_title) . '" title="' . esc_attr($vis_label) . '">';
                echo '      ' . cfd_icon($vis_icon);
                echo '    </a>';
            }
            if (current_user_can('delete_post', $p->ID)) {
                echo '    <button type="button" class="cd-cpt-card__delete kh-content-item__delete" data-cfd-delete data-id="' . esc_attr($p->ID) . '" data-title="' . esc_attr($p->post_title) . '" data-trash-url="' . esc_url($trash_url) . '" aria-label="' . esc_attr('Eliminar ' . $p->post_title) . '">';
                echo '      ' . cfd_icon('delete') . '';
                echo '    </button>';
            }
            echo '  </div>';

            echo '</div>'; // End kh-content-item
        }
        wp_reset_postdata();
        echo '</div>';

        // ── Pagination ──
        if ($total_pages > 1) {
            // Build base URL preserving all current filters (sort, search, facets).
            $base_args = array('manage' => $cpt_slug);
            if ($orderby !== 'title') {
                $base_args['orderby'] = $orderby;
            }
            if ($search !== '') {
                $base_args['buscar'] = $search;
            }
            // Preserve active facet filter params across pagination.
            if ( ! empty( $active_facet_params ) ) {
                $base_args = array_merge( $base_args, $active_facet_params );
            }

            echo '<nav class="cd-cpt-pagination kh-pagination" aria-label="Paginación">';

            // ← Previous
            if ($pag > 1) {
                $prev_args = $base_args;
                $prev_args['pag'] = $pag - 1;
                $prev_url = add_query_arg($prev_args, $dashboard_url);
                echo '<a href="' . esc_url($prev_url) . '" class="cd-cpt-pagination__link kh-pagination__link">' . cfd_icon('chevron_left') . ' Anterior</a>';
            }
            else {
                echo '<span class="cd-cpt-pagination__link cd-cpt-pagination__link--disabled kh-pagination__link kh-pagination__link--disabled">' . cfd_icon('chevron_left') . ' Anterior</span>';
            }

            // Page indicator
            echo '<span class="cd-cpt-pagination__current kh-pagination__current">Página ' . $pag . ' de ' . $total_pages . '</span>';

            // Next →
            if ($pag < $total_pages) {
                $next_args = $base_args;
                $next_args['pag'] = $pag + 1;
                $next_url = add_query_arg($next_args, $dashboard_url);
                echo '<a href="' . esc_url($next_url) . '" class="cd-cpt-pagination__link kh-pagination__link">Siguiente ' . cfd_icon('chevron_right') . '</a>';
            }
            else {
                echo '<span class="cd-cpt-pagination__link cd-cpt-pagination__link--disabled kh-pagination__link kh-pagination__link--disabled">Siguiente ' . cfd_icon('chevron_right') . '</span>';
            }

            echo '</nav>';
        }
    }

    cfd_render_delete_modal();

    // Help banner at the bottom of the CPT list
    echo '<div class="kh-help-banner">';
    echo '  <div class="kh-help-banner__icon">';
    echo '    ' . cfd_icon('auto_awesome') . '';
    echo '  </div>';
    echo '  <div class="kh-help-banner__content">';
    echo '    <h3 class="kh-help-banner__title">¿Necesitas ayuda?</h3>';
    echo '    <p class="kh-help-banner__text">Si estas teniendo problemas para editar tu contenido, <a href="mailto:soporte@autentiweb.com" style="color:var(--primary-dark,#5A4535);text-decoration:underline;">contáctanos</a></p>';
    echo '  </div>';
    echo '</div>';

    echo '</div>'; // End cd-cpt-list
}

// ═══════════════════════════════════════════════════════════
// 8. CPT EDITOR
// ═══════════════════════════════════════════════════════════

function cfd_render_cpt_editor(string $cpt_slug, int $post_id, WP_User $user): void
{
    $post = get_post($post_id);

    if (!$post || $post->post_type !== $cpt_slug) {
        echo '<div class="cd-error">Esta entrada no existe.</div>';
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        echo '<div class="cd-error">No tienes permiso para editar esta entrada.</div>';
        return;
    }

    $field_groups = cfd_get_field_groups_for_post($post_id);

    if (empty($field_groups)) {
        echo '<div class="cd-error">No se encontraron campos editables para esta entrada.</div>';
        return;
    }

    $dashboard_url = cfd_get_dashboard_url();
    $back_url = add_query_arg(array('manage' => $cpt_slug), $dashboard_url);
    $return_url = add_query_arg(
        array('edit' => $cpt_slug, 'id' => $post_id, 'updated' => 'true'),
        $dashboard_url
    );

    // Build header-action URLs upfront so they can render in the page header.
    $duplicate_url = add_query_arg(array(
        'duplicate' => $cpt_slug,
        'id'        => $post_id,
        '_wpnonce'  => wp_create_nonce('cfd_duplicate_' . $post_id),
    ), $dashboard_url);

    $can_delete = current_user_can('delete_post', $post_id);
    $trash_url = $can_delete ? add_query_arg(array(
        'action'   => 'trash',
        'id'       => $post_id,
        '_wpnonce' => wp_create_nonce('cfd_trash_' . $post_id),
    ), $dashboard_url) : '';

    $can_edit = current_user_can('edit_post', $post_id);
    $is_hidden = ($post->post_status === 'draft');
    $visibility_url = $can_edit ? add_query_arg(array(
        'action'   => 'toggle_visibility',
        'id'       => $post_id,
        'from'     => 'editor',
        '_wpnonce' => wp_create_nonce('cfd_visibility_' . $post_id),
    ), $dashboard_url) : '';

    echo '<a href="' . esc_url($back_url) . '" class="cd-back-link kh-editor__back">' . cfd_icon('arrow_back') . ' Volver a la lista</a>';

    echo '<div class="cd-editor">';
    echo '<div class="cd-editor__header kh-editor__header kh-editor__header--with-actions">';
    echo '<div class="kh-editor__header-row">';
    echo '  <div class="kh-editor__header-main">';
    echo '    <h1 class="cd-editor__title kh-editor__title">' . esc_html($post->post_title) . '</h1>';
    echo '    <a href="' . esc_url(get_permalink($post_id)) . '" target="_blank" class="cd-preview-link kh-editor__preview">' . cfd_icon('open_in_new') . ' Ver online</a>';
    echo '  </div>';

    // ── Header action zone: Duplicar + Eliminar ──
    // Collapses to a `⋯` overflow menu on mobile (CSS-driven).
    echo '  <div class="kh-editor__header-actions" data-cfd-header-actions>';
    echo '    <button type="button" class="kh-editor__overflow-toggle" data-cfd-overflow-toggle aria-haspopup="true" aria-expanded="false" aria-label="Más acciones">';
    echo '      ' . cfd_icon('more_vert') . '';
    echo '    </button>';
    echo '    <div class="kh-editor__header-actions-menu">';
    if ($can_edit && $visibility_url !== '') {
        $vis_label = $is_hidden ? 'Mostrar online' : 'Ocultar';
        $vis_icon  = $is_hidden ? 'visibility' : 'visibility_off';
        echo '      <a href="' . esc_url($visibility_url) . '" class="cd-visibility-link kh-editor__action">';
        echo '        ' . cfd_icon($vis_icon) . ' ' . esc_html($vis_label);
        echo '      </a>';
    }
    echo '      <a href="' . esc_url($duplicate_url) . '" class="cd-duplicate-btn kh-editor__action">';
    echo '        ' . cfd_icon('file_copy') . ' Duplicar';
    echo '      </a>';
    if ($can_delete) {
        echo '      <button type="button" class="cd-delete-link kh-editor__action kh-editor__action--danger" data-cfd-delete data-id="' . esc_attr($post_id) . '" data-title="' . esc_attr($post->post_title) . '" data-trash-url="' . esc_url($trash_url) . '">';
        echo '        ' . cfd_icon('delete') . ' Eliminar';
        echo '      </button>';
    }
    echo '    </div>';
    echo '  </div>';
    echo '</div>'; // end kh-editor__header-row
    // View-hint sits inside the header so it appears above the divider line.
    cfd_maybe_render_view_hint( 'edit_cpt' );
    echo '</div>';

    if (isset($_GET['updated']) && $_GET['updated'] === 'true') {
        echo '<div class="cd-success kh-editor__success">';
        echo '  ' . cfd_icon('check_circle', 'kh-icon--filled') . '';
        echo '  <span>¡Tus cambios han sido guardados!</span>';
        echo '</div>';
    }
    if (isset($_GET['duplicated']) && $_GET['duplicated'] === 'true') {
        echo '<div class="cd-success kh-editor__success">';
        echo '  ' . cfd_icon('check_circle', 'kh-icon--filled') . '';
        echo '  <span>Contenido duplicado. Puedes editarlo a continuación.</span>';
        echo '</div>';
    }
    if (isset($_GET['hidden']) && $_GET['hidden'] === 'true') {
        echo '<div class="cd-success kh-editor__success">';
        echo '  ' . cfd_icon('check_circle', 'kh-icon--filled') . '';
        echo '  <span>Esta entrada está oculta. Ya no aparece online.</span>';
        echo '</div>';
    }
    if (isset($_GET['shown']) && $_GET['shown'] === 'true') {
        echo '<div class="cd-success kh-editor__success">';
        echo '  ' . cfd_icon('check_circle', 'kh-icon--filled') . '';
        echo '  <span>Esta entrada ya está publicada online.</span>';
        echo '</div>';
    }

    // Extensibility hook: before the editor form (e.g., translation links).
    do_action( 'cfd_editor_before_form', $post, $cpt_slug );

    // Save button cluster:
    //   - Primary "Guardar cambios" stays as ACF's submit (no status change).
    //   - Secondary button posts cfd_save_as=draft|publish; cfd_apply_save_intent()
    //     reads that on acf/save_post and flips post_status accordingly.
    //   - Microcopy adapts to current state.
    if ($is_hidden) {
        $secondary_intent = 'publish';
        $secondary_label  = 'Guardar y publicar';
        $secondary_icon   = 'visibility';
        $save_hint        = 'Guarda para seguir trabajando, o publica para que aparezca online.';
    } else {
        $secondary_intent = 'draft';
        $secondary_label  = 'Guardar y ocultar';
        $secondary_icon   = 'visibility_off';
        $save_hint        = 'Guarda para actualizar, u oculta para que deje de aparecer online.';
    }

    $submit_html = '<div class="kh-editor__save-cluster">';
    $submit_html .= '<button type="submit" class="cd-save-btn kh-editor__save">' . cfd_icon('save') . ' Guardar cambios</button>';
    if ($can_edit) {
        $submit_html .= '<button type="submit" name="cfd_save_as" value="' . esc_attr($secondary_intent) . '" class="kh-editor__save kh-editor__save--secondary">' . cfd_icon($secondary_icon) . ' ' . esc_html($secondary_label) . '</button>';
    }
    $submit_html .= '<span class="kh-editor__save-hint">' . esc_html($save_hint) . '</span>';
    $submit_html .= '</div>';

    acf_form(array(
        'post_id' => $post_id,
        'post_title' => true,
        'post_content' => false,
        'field_groups' => $field_groups,
        'submit_value' => 'Guardar cambios',
        'updated_message' => false,
        'return' => $return_url,
        'html_submit_button' => $submit_html,
        'html_submit_spinner' => '',
        'form_attributes' => array('class' => 'cd-acf-form'),
    ));

    // Extensibility hook: after the editor form.
    do_action( 'cfd_editor_after_form', $post, $cpt_slug );

    echo '</div>'; // End cd-editor

    cfd_render_delete_modal();

    echo '<a href="' . esc_url($back_url) . '" class="cd-back-link cd-back-link--bottom kh-editor__back">' . cfd_icon('arrow_back') . ' Volver a la lista</a>';
}

// ═══════════════════════════════════════════════════════════
// 9. CPT CREATOR
// ═══════════════════════════════════════════════════════════

function cfd_render_cpt_creator(string $cpt_slug, WP_User $user): void
{
    $cpt_obj = get_post_type_object($cpt_slug);
    if (!$cpt_obj) {
        echo '<div class="cd-error">Tipo de contenido no encontrado.</div>';
        return;
    }

    $dashboard_url = cfd_get_dashboard_url();
    $back_url = add_query_arg(array('manage' => $cpt_slug), $dashboard_url);
    $return_url = add_query_arg(array('manage' => $cpt_slug, 'created' => 'true'), $dashboard_url);

    echo '<a href="' . esc_url($back_url) . '" class="cd-back-link kh-editor__back">' . cfd_icon('arrow_back') . ' Volver a la lista</a>';

    echo '<div class="cd-editor">';
    echo '<h1 class="cd-editor__title kh-editor__title">Crear nuevo: ' . esc_html($cpt_obj->labels->singular_name) . '</h1>';
    echo '<p class="cd-editor__sub kh-editor__subtitle">Completa los campos y publica.</p>';
    cfd_maybe_render_view_hint( 'create', $cpt_obj->labels->singular_name );

    // Save cluster: primary publishes, secondary saves as draft (hidden).
    // The 'new_post.post_status' default is 'publish'; the secondary button
    // posts cfd_save_as=draft, which cfd_apply_save_intent() flips after ACF
    // has written field values.
    $submit_html = '<div class="kh-editor__save-cluster">';
    $submit_html .= '<button type="submit" class="cd-save-btn kh-editor__save">' . cfd_icon('auto_fix') . ' Crear y publicar</button>';
    $submit_html .= '<button type="submit" name="cfd_save_as" value="draft" class="kh-editor__save kh-editor__save--secondary">' . cfd_icon('visibility_off') . ' Guardar borrador</button>';
    $submit_html .= '<span class="kh-editor__save-hint">Publica para que aparezca online, o guarda como borrador para terminar después.</span>';
    $submit_html .= '</div>';

    acf_form(array(
        'post_id' => 'new_post',
        'new_post' => array(
            'post_type' => $cpt_slug,
            'post_status' => 'publish',
        ),
        'post_title' => true,
        'post_content' => false,
        'submit_value' => 'Crear y publicar',
        'updated_message' => false,
        'return' => $return_url,
        'html_submit_button' => $submit_html,
        'html_submit_spinner' => '',
        'form_attributes' => array('class' => 'cd-acf-form'),
    ));

    echo '</div>'; // End cd-editor

    echo '<a href="' . esc_url($back_url) . '" class="cd-back-link cd-back-link--bottom kh-editor__back">' . cfd_icon('arrow_back') . ' Volver a la lista</a>';
}

// ═══════════════════════════════════════════════════════════
// 10. PAPELERA (CPT TRASH VIEW)
// ═══════════════════════════════════════════════════════════

function cfd_render_cpt_trash(string $cpt_slug, WP_User $user): void
{
    $cpt_obj = get_post_type_object($cpt_slug);
    if (!$cpt_obj) {
        echo '<div class="cd-error">Tipo de contenido no encontrado.</div>';
        return;
    }

    $dashboard_url = cfd_get_dashboard_url();
    $list_url = add_query_arg(array('manage' => $cpt_slug), $dashboard_url);

    echo '<a href="' . esc_url($list_url) . '" class="cd-back-link kh-editor__back">' . cfd_icon('arrow_back') . ' Volver a ' . esc_html($cpt_obj->labels->name) . '</a>';

    // Success messages.
    if (isset($_GET['restored']) && $_GET['restored'] === 'true') {
        echo '<div class="cd-success kh-editor__success">' . cfd_icon('check_circle', 'kh-icon--filled') . ' <span>Entrada restaurada.</span></div>';
    }
    if (isset($_GET['deleted_forever']) && $_GET['deleted_forever'] === 'true') {
        echo '<div class="cd-success kh-editor__success">' . cfd_icon('check_circle', 'kh-icon--filled') . ' <span>Entrada eliminada permanentemente.</span></div>';
    }

    echo '<div class="cd-cpt-list kh-trash-view">';
    echo '<div class="cd-cpt-list__header">';
    echo '  <h1 class="cd-cpt-list__title kh-content__title">Papelera: ' . esc_html($cpt_obj->labels->name) . '</h1>';
    echo '</div>';

    $purge_days = defined('EMPTY_TRASH_DAYS') ? (int) EMPTY_TRASH_DAYS : 30;
    if ($purge_days > 0) {
        echo '<p class="kh-trash-view__intro">Los elementos eliminados se conservan durante <strong>' . (int) $purge_days . ' días</strong> y luego se borran para siempre.</p>';
    } else {
        echo '<p class="kh-trash-view__intro">Estos elementos están en la papelera.</p>';
    }

    $query = new WP_Query(array(
        'post_type'      => $cpt_slug,
        'post_status'    => 'trash',
        'posts_per_page' => 50,
        'orderby'        => 'modified',
        'order'          => 'DESC',
    ));

    if (!$query->have_posts()) {
        echo '<div class="kh-empty-state">';
        echo '  <div class="kh-empty-state__icon">' . cfd_icon('inbox') . '</div>';
        echo '  <h3 class="kh-empty-state__title">La papelera está vacía</h3>';
        echo '  <p class="kh-empty-state__text">Los elementos que elimines aparecerán aquí';
        if ($purge_days > 0) {
            echo ' durante ' . (int) $purge_days . ' días';
        }
        echo '.</p>';
        echo '</div>';
        echo '</div>'; // .cd-cpt-list
        return;
    }

    echo '<div class="cd-cpt-grid">';
    while ($query->have_posts()) {
        $query->the_post();
        $p = get_post();

        $trashed_ts = (int) get_post_meta($p->ID, '_wp_trash_meta_time', true);
        if ($trashed_ts <= 0) {
            $trashed_ts = strtotime($p->post_modified_gmt . ' UTC') ?: current_time('timestamp');
        }
        $trashed_ago = human_time_diff($trashed_ts, current_time('timestamp'));

        $purge_in_html = '';
        if ($purge_days > 0) {
            $purge_ts = $trashed_ts + ($purge_days * DAY_IN_SECONDS);
            $now      = current_time('timestamp');
            if ($purge_ts > $now) {
                $purge_in_html = '<span class="kh-trash-card__purge">Se borrará en ' . esc_html( human_time_diff($now, $purge_ts) ) . '</span>';
            } else {
                $purge_in_html = '<span class="kh-trash-card__purge">Será borrada pronto</span>';
            }
        }

        $can_restore = current_user_can('edit_post', $p->ID);
        $can_destroy = current_user_can('delete_post', $p->ID);

        $restore_url = $can_restore ? add_query_arg(array(
            'action'   => 'restore',
            'id'       => $p->ID,
            '_wpnonce' => wp_create_nonce('cfd_restore_' . $p->ID),
            'manage'   => $cpt_slug,
            'view'     => 'trash',
        ), $dashboard_url) : '';

        echo '<div class="cd-cpt-card kh-content-item kh-content-item--trashed">';
        echo '  <div class="kh-content-item__info">';
        echo '    <div class="kh-content-item__heading">';
        echo '      <h3 class="kh-content-item__title">' . esc_html($p->post_title !== '' ? $p->post_title : '(sin título)') . '</h3>';
        echo '    </div>';
        echo '    <p class="kh-content-item__meta">Eliminado hace ' . esc_html($trashed_ago) . '</p>';
        if ($purge_in_html !== '') {
            echo '    <p class="kh-content-item__meta kh-content-item__meta--purge">' . $purge_in_html . '</p>';
        }
        echo '  </div>';

        echo '  <div class="kh-content-item__actions">';
        if ($can_restore && $restore_url !== '') {
            echo '    <a href="' . esc_url($restore_url) . '" class="kh-content-item__restore" aria-label="' . esc_attr('Restaurar ' . $p->post_title) . '">';
            echo '      ' . cfd_icon('restore_from_trash') . ' Restaurar';
            echo '    </a>';
        }
        if ($can_destroy) {
            $nonce = wp_create_nonce('cfd_delete_forever_' . $p->ID);
            echo '    <button type="button" class="kh-content-item__delete-forever" data-cfd-delete-forever data-id="' . esc_attr($p->ID) . '" data-title="' . esc_attr($p->post_title) . '" data-nonce="' . esc_attr($nonce) . '" aria-label="' . esc_attr('Eliminar definitivamente ' . $p->post_title) . '">';
            echo '      ' . cfd_icon('delete_forever') . ' Eliminar';
            echo '    </button>';
        }
        echo '  </div>';

        echo '</div>'; // .kh-content-item
    }
    wp_reset_postdata();
    echo '</div>'; // .cd-cpt-grid

    echo '</div>'; // .cd-cpt-list

    // Type-ELIMINAR modal (rendered once per page).
    cfd_render_delete_forever_modal();

    echo '<a href="' . esc_url($list_url) . '" class="cd-back-link cd-back-link--bottom kh-editor__back">' . cfd_icon('arrow_back') . ' Volver a ' . esc_html($cpt_obj->labels->name) . '</a>';
}

// Type-`ELIMINAR` confirmation modal. Used by the Papelera permanent-delete
// action; the JS in dashboard.js wires the input listener.
function cfd_render_delete_forever_modal(): void
{
    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;

    $action_url = cfd_get_dashboard_url();

    echo '<div class="cfd-confirm-modal cfd-confirm-modal--destructive" data-cfd-delete-forever-modal aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="cfd-df-modal-title">';
    echo '  <div class="cfd-confirm-modal__backdrop" data-cfd-modal-cancel></div>';
    echo '  <div class="cfd-confirm-modal__panel">';
    echo '    <h2 class="cfd-confirm-modal__title" id="cfd-df-modal-title">Eliminar permanentemente</h2>';
    echo '    <p class="cfd-confirm-modal__body">';
    echo '      <strong data-cfd-modal-target></strong> se eliminará para siempre.';
    echo '      Esta acción <strong>no se puede deshacer</strong>.';
    echo '    </p>';
    echo '    <form method="post" action="' . esc_url($action_url) . '" class="cfd-confirm-modal__form" data-cfd-delete-forever-form>';
    echo '      <input type="hidden" name="cfd_action" value="delete_forever">';
    echo '      <input type="hidden" name="id" value="" data-cfd-modal-id>';
    echo '      <input type="hidden" name="_wpnonce" value="" data-cfd-modal-nonce>';
    echo '      <label class="cfd-confirm-modal__label" for="cfd-df-confirm-input">';
    echo '        Para confirmar, escribe <strong>ELIMINAR</strong>:';
    echo '      </label>';
    echo '      <input type="text" id="cfd-df-confirm-input" name="cfd_confirm" class="cfd-confirm-modal__input" autocomplete="off" autocorrect="off" autocapitalize="characters" spellcheck="false" required>';
    echo '      <div class="cfd-confirm-modal__actions">';
    echo '        <button type="button" class="cfd-confirm-modal__btn cfd-confirm-modal__btn--cancel" data-cfd-modal-cancel>Cancelar</button>';
    echo '        <button type="submit" class="cfd-confirm-modal__btn cfd-confirm-modal__btn--destroy" disabled>Eliminar para siempre</button>';
    echo '      </div>';
    echo '    </form>';
    echo '  </div>';
    echo '</div>';
}

// ═══════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════

function cfd_get_editable_pages(array $config): array
{
    if (!empty($config['editable_pages'])) {
        return array_filter(array_map('get_post', $config['editable_pages']));
    }

    $all_pages = get_posts(array(
        'post_type' => 'page',
        'post_status' => 'publish',
        'posts_per_page' => 50,
        'orderby' => 'menu_order title',
        'order' => 'ASC',
    ));

    $editable = array();
    foreach ($all_pages as $page) {
        if ($page->post_name === $config['dashboard_slug']) {
            continue;
        }
        $groups = cfd_get_field_groups_for_post($page->ID);
        if (!empty($groups)) {
            $editable[] = $page;
        }
    }

    return $editable;
}

function cfd_get_field_groups_for_post(int $post_id): array
{
    if (!function_exists('acf_get_field_groups')) {
        return array();
    }

    $post = get_post($post_id);
    if (!$post) {
        return array();
    }

    $screen = array(
        'post_id' => $post_id,
        'post_type' => $post->post_type,
        'page' => $post->post_name,
    );

    $template = get_page_template_slug($post_id);
    if ($template) {
        $screen['page_template'] = $template;
    }

    if ($post->post_type === 'page') {
        $front_page_id = (int)get_option('page_on_front');
        $screen['page_type'] = ($post_id === $front_page_id) ? 'front_page' : 'page';
    }

    $all_groups = acf_get_field_groups($screen);

    $keys = array();
    foreach ($all_groups as $group) {
        if (!$group['active']) {
            continue;
        }
        $keys[] = $group['key'];
    }

    return $keys;
}