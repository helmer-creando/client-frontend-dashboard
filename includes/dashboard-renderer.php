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

    $redirect_url = add_query_arg(
        array('manage' => $cpt_slug, 'trashed' => 'true'),
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

    // Material Symbols Outlined (for Kindred Hearth design system)
    wp_enqueue_style(
        'cfd-material-symbols',
        'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap',
        array(),
        null
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
        cfd_render_cpt_list($manage, $user);
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
            cfd_render_cpt_list($cpt_slug, $user);
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
    echo '    <span class="material-symbols-outlined kh-icon--filled">lightbulb</span>';
    echo '  </div>';
    echo '  <div>';
    echo '    <h3 class="kh-tip-card__title">' . esc_html( $title ) . '</h3>';
    echo '    <p class="kh-tip-card__text">' . esc_html( $text ) . '</p>';
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
            echo '      <span class="material-symbols-outlined">web</span>';
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
        echo '      <span class="material-symbols-outlined">open_in_new</span> Ver en línea';
        echo '    </a>';
        echo '  </div>'; // End kh-page-card__header
        
        echo '  <p class="kh-page-card__meta">Último cambio: hace ' . esc_html($time_ago) . '</p>';
        echo '  <a href="' . esc_url($edit_url) . '" class="kh-page-card__btn">';
        echo '    <span class="material-symbols-outlined kh-icon--filled">edit</span> Editar esta página';
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

    echo '<a href="' . esc_url($dashboard_url) . '" class="cd-back-link kh-editor__back"><span class="material-symbols-outlined">arrow_back</span> Volver a mis páginas</a>';

    echo '<div class="cd-editor">';
    echo '<div class="cd-editor__header kh-editor__header">';
    echo '  <h1 class="cd-editor__title kh-editor__title">' . esc_html($post->post_title) . '</h1>';
    echo '  <a href="' . esc_url(get_permalink($post_id)) . '" target="_blank" class="cd-preview-link kh-editor__preview"><span class="material-symbols-outlined">open_in_new</span> Ver página online</a>';
    echo '</div>';

    if (isset($_GET['updated']) && $_GET['updated'] === 'true') {
        echo '<div class="cd-success kh-editor__success">';
        echo '  <span class="material-symbols-outlined kh-icon--filled">check_circle</span>';
        echo '  <span>¡Tus cambios han sido guardados!</span>';
        echo '</div>';
    }

    cfd_maybe_render_view_hint( 'edit_page' );

    echo '<div class="cd-editor-form kh-editor__grid">';
    
    // Editor tips
    cfd_render_concierge_tip( 'Color de Acento', 'Puedes usar {llaves} alrededor de las palabras importantes en los textos para aplicar tu color de marca (ejemplo: Nuestro equipo {profesional}).' );

    acf_form(array(
        'post_id' => $post_id,
        'post_title' => false,
        'post_content' => false,
        'field_groups' => $field_groups,
        'submit_value' => 'Guardar mis cambios',
        'updated_message' => false,
        'return' => $return_url,
        'html_submit_button' => '<button type="submit" class="cd-save-btn kh-editor__save"><span class="material-symbols-outlined">save</span> Guardar mis cambios</button>',
        'html_submit_spinner' => '<span class="cd-spinner"></span>',
        'form_attributes' => array('class' => 'cd-acf-form'),
    ));

    echo '</div>'; // End cd-editor-form

    echo '</div>'; // End cd-editor
    
    cfd_render_concierge_tip( 'Consejo', 'Los cambios se guardan y publican de inmediato. Asegúrate de revisarlos en la página online.' );

    echo '<a href="' . esc_url($dashboard_url) . '" class="cd-back-link cd-back-link--bottom kh-editor__back"><span class="material-symbols-outlined">arrow_back</span> Volver a mis páginas</a>';
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

    echo '<a href="' . esc_url($dashboard_url) . '" class="cd-back-link kh-editor__back"><span class="material-symbols-outlined">arrow_back</span> Volver a mis páginas</a>';

    if (isset($_GET['trashed']) && $_GET['trashed'] === 'true') {
        echo '<div class="cd-success kh-editor__success"><span class="material-symbols-outlined kh-icon--filled">check_circle</span> <span>Entrada eliminada correctamente</span></div>';
    }
    if (isset($_GET['created']) && $_GET['created'] === 'true') {
        echo '<div class="cd-success kh-editor__success"><span class="material-symbols-outlined kh-icon--filled">check_circle</span> <span>Entrada creada con éxito</span></div>';
    }

    echo '<div class="cd-cpt-list">';
    echo '<div class="cd-cpt-list__header">';
    echo '  <h1 class="cd-cpt-list__title kh-content__title">' . esc_html($cpt_obj->labels->name) . '</h1>';

    $create_url = add_query_arg(array('create' => $cpt_slug), $dashboard_url);
    echo '  <a href="' . esc_url($create_url) . '" class="cd-add-btn kh-content__add">';
    echo '    <span class="material-symbols-outlined kh-icon--filled">add_circle</span> Agregar nuevo';
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

    echo '  <button type="submit" class="cd-cpt-toolbar__submit">Filtrar</button>';
    echo '</form>';
    echo '</details>';

    // ── Active search indicator (with clear link) ───────────
    if ($search !== '') {
        $clear_url = add_query_arg(
            array('manage' => $cpt_slug, 'orderby' => $raw_sort),
            $dashboard_url
        );
        echo '<p class="cd-cpt-search-status">';
        echo '  Resultados para "<strong>' . esc_html($search) . '</strong>"';
        echo '  <a href="' . esc_url($clear_url) . '" class="cd-cpt-search-clear">✕ Limpiar</a>';
        echo '</p>';
    }

    // ── Query posts with WP_Query for pagination support ────
    $query_args = array(
        'post_type' => $cpt_slug,
        'post_status' => 'publish',
        'posts_per_page' => $per_page,
        'paged' => $pag,
        'orderby' => $orderby,
        'order' => $order,
    );

    if ($search !== '') {
        $query_args['s'] = $search;
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
            echo '<p class="cd-cpt-list__empty">No se encontraron resultados para "' . esc_html($search) . '".</p>';
        }
        else {
            echo '<p class="cd-cpt-list__empty">Todavía no hay entradas. Haz clic en "Agregar nuevo" para crear una.</p>';
        }
    }
    else {
        // ── Post count ──
        $first_item = (($pag - 1) * $per_page) + 1;
        $last_item = min($pag * $per_page, $total_posts);

        echo '<p class="cd-cpt-count">Mostrando ' . $first_item . '–' . $last_item . ' de ' . $total_posts . '</p>';

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

            // Extensibility hook: allow addons to add badges/info to CPT cards.
            $card_badges = apply_filters( 'cfd_cpt_card_badges', array(), $p, $cpt_slug );

            // Last-edited human-readable timestamp.
            $modified_ts = get_the_modified_date('U', $p);
            $time_ago = human_time_diff($modified_ts, current_time('timestamp'));

            // Post date for badge.
            $month = get_the_date('M', $p);
            $day   = get_the_date('d', $p);

            echo '<div class="cd-cpt-card kh-content-item">';
            
            echo '  <div class="kh-content-item__left">';
            echo '    <div class="kh-content-item__date">';
            echo '      <span class="kh-content-item__date-month">' . esc_html($month) . '</span>';
            echo '      <span class="kh-content-item__date-day">' . esc_html($day) . '</span>';
            echo '    </div>';
            echo '    <div class="kh-content-item__info">';
            echo '      <h3 class="kh-content-item__title">' . esc_html($p->post_title) . '</h3>';
            echo '      <p class="kh-content-item__meta">Editado hace ' . esc_html($time_ago) . '</p>';
            if ( ! empty( $card_badges ) ) {
                echo '      <div class="cd-cpt-card__badges">' . implode( ' ', array_map( 'wp_kses_post', $card_badges ) ) . '</div>';
            }
            echo '    </div>';
            echo '  </div>'; // kh-content-item__left
            
            echo '  <div class="kh-content-item__actions">';
            echo '    <a href="' . esc_url($edit_url) . '" class="cd-cpt-card__edit kh-content-item__edit">';
            echo '      <span class="material-symbols-outlined">edit</span> Editar';
            echo '    </a>';
            if (current_user_can('delete_post', $p->ID)) {
                echo '    <a href="' . esc_url($trash_url) . '" class="cd-cpt-card__delete kh-content-item__delete" onclick="return confirm(\'¿Estás seguro de que deseas eliminar esta entrada?\');">';
                echo '      <span class="material-symbols-outlined">delete</span>';
                echo '    </a>';
            }
            echo '  </div>'; // kh-content-item__actions
            
            echo '</div>'; // End kh-content-item
        }
        wp_reset_postdata();
        echo '</div>';

        // ── Pagination ──
        if ($total_pages > 1) {
            // Build base URL preserving all current filters.
            $base_args = array('manage' => $cpt_slug);
            if ($orderby !== 'title') {
                $base_args['orderby'] = $orderby;
            }
            if ($search !== '') {
                $base_args['buscar'] = $search;
            }

            echo '<nav class="cd-cpt-pagination kh-pagination" aria-label="Paginación">';

            // ← Previous
            if ($pag > 1) {
                $prev_args = $base_args;
                $prev_args['pag'] = $pag - 1;
                $prev_url = add_query_arg($prev_args, $dashboard_url);
                echo '<a href="' . esc_url($prev_url) . '" class="cd-cpt-pagination__link kh-pagination__link"><span class="material-symbols-outlined">chevron_left</span> Anterior</a>';
            }
            else {
                echo '<span class="cd-cpt-pagination__link cd-cpt-pagination__link--disabled kh-pagination__link kh-pagination__link--disabled"><span class="material-symbols-outlined">chevron_left</span> Anterior</span>';
            }

            // Page indicator
            echo '<span class="cd-cpt-pagination__current kh-pagination__current">Página ' . $pag . ' de ' . $total_pages . '</span>';

            // Next →
            if ($pag < $total_pages) {
                $next_args = $base_args;
                $next_args['pag'] = $pag + 1;
                $next_url = add_query_arg($next_args, $dashboard_url);
                echo '<a href="' . esc_url($next_url) . '" class="cd-cpt-pagination__link kh-pagination__link">Siguiente <span class="material-symbols-outlined">chevron_right</span></a>';
            }
            else {
                echo '<span class="cd-cpt-pagination__link cd-cpt-pagination__link--disabled kh-pagination__link kh-pagination__link--disabled">Siguiente <span class="material-symbols-outlined">chevron_right</span></span>';
            }

            echo '</nav>';
        }
    }

    // Help banner at the bottom of the CPT list
    echo '<div class="kh-help-banner">';
    echo '  <div class="kh-help-banner__icon">';
    echo '    <span class="material-symbols-outlined">auto_awesome</span>';
    echo '  </div>';
    echo '  <div class="kh-help-banner__content">';
    echo '    <h3 class="kh-help-banner__title">¿Necesitas ayuda?</h3>';
    echo '    <p class="kh-help-banner__text">Si no encuentras lo que buscas o tienes problemas para editar tu contenido, tu equipo de diseño está a un clic de distancia.</p>';
    echo '    <a href="mailto:soporte@autentiweb.com" class="kh-help-banner__btn"><span class="material-symbols-outlined">mail</span> Contactar soporte</a>';
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

    echo '<a href="' . esc_url($back_url) . '" class="cd-back-link kh-editor__back"><span class="material-symbols-outlined">arrow_back</span> Volver a la lista</a>';

    echo '<div class="cd-editor">';
    echo '<div class="cd-editor__header kh-editor__header">';
    echo '  <h1 class="cd-editor__title kh-editor__title">' . esc_html($post->post_title) . '</h1>';
    echo '  <a href="' . esc_url(get_permalink($post_id)) . '" target="_blank" class="cd-preview-link kh-editor__preview"><span class="material-symbols-outlined">open_in_new</span> Ver entrada online</a>';
    echo '</div>';

    if (isset($_GET['updated']) && $_GET['updated'] === 'true') {
        echo '<div class="cd-success kh-editor__success">';
        echo '  <span class="material-symbols-outlined kh-icon--filled">check_circle</span>';
        echo '  <span>¡Tus cambios han sido guardados!</span>';
        echo '</div>';
    }
    if (isset($_GET['duplicated']) && $_GET['duplicated'] === 'true') {
        echo '<div class="cd-success kh-editor__success">';
        echo '  <span class="material-symbols-outlined kh-icon--filled">check_circle</span>';
        echo '  <span>Contenido duplicado. Puedes editarlo a continuación.</span>';
        echo '</div>';
    }

    cfd_maybe_render_view_hint( 'edit_cpt' );

    // Extensibility hook: before the editor form (e.g., translation links).
    do_action( 'cfd_editor_before_form', $post, $cpt_slug );

    acf_form(array(
        'post_id' => $post_id,
        'post_title' => true,
        'post_content' => false,
        'field_groups' => $field_groups,
        'submit_value' => 'Guardar mis cambios',
        'updated_message' => false,
        'return' => $return_url,
        'html_submit_button' => '<button type="submit" class="cd-save-btn kh-editor__save"><span class="material-symbols-outlined">save</span> Guardar mis cambios</button>',
        'html_submit_spinner' => '<span class="cd-spinner"></span>',
        'form_attributes' => array('class' => 'cd-acf-form'),
    ));

    // Actions moved here
    echo '<div class="cd-editor__actions" style="margin-top: var(--space-l, 2rem);">';

    // ── Duplicate button ──
    $duplicate_url = add_query_arg(array(
        'duplicate' => $cpt_slug,
        'id'        => $post_id,
        '_wpnonce'  => wp_create_nonce('cfd_duplicate_' . $post_id),
    ), $dashboard_url);

    echo '  <a href="' . esc_url($duplicate_url) . '" class="cd-duplicate-btn">';
    echo '    <span class="material-symbols-outlined">file_copy</span> Duplicar';
    echo '  </a>';

    if (current_user_can('delete_post', $post_id)) {
        // NOTE: nonce action changed from 'cd_trash_' to 'cfd_trash_'
        // to match the verification in cfd_handle_cpt_delete().
        $trash_url = add_query_arg(array(
            'action' => 'trash',
            'id' => $post_id,
            '_wpnonce' => wp_create_nonce('cfd_trash_' . $post_id),
        ), $dashboard_url);

        echo '  <span class="cd-delete-wrap" id="cd-delete-wrap">';
        echo '    <a href="#" class="cd-delete-link" id="cd-delete-trigger">Eliminar</a>';
        echo '    <span class="cd-delete-confirm" id="cd-delete-confirm" style="display:none;">';
        echo '      <span class="cd-delete-confirm__text">¿Segura?</span>';
        echo '      <a href="' . esc_url($trash_url) . '" class="cd-delete-confirm__yes">Sí, eliminar</a>';
        echo '      <a href="#" class="cd-delete-confirm__no" id="cd-delete-cancel">Cancelar</a>';
        echo '    </span>';
        echo '  </span>';
    }
    
    // Extensibility hook: after the editor form, before actions.
    do_action( 'cfd_editor_after_form', $post, $cpt_slug );

    echo '  </div>'; // End cd-editor__actions
    echo '</div>'; // End cd-editor
    
    cfd_render_concierge_tip( 'Consejo', 'Los cambios se guardan y publican de inmediato. Asegúrate de revisarlos en la página online.' );

    echo '<a href="' . esc_url($back_url) . '" class="cd-back-link cd-back-link--bottom kh-editor__back"><span class="material-symbols-outlined">arrow_back</span> Volver a la lista</a>';
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

    echo '<a href="' . esc_url($back_url) . '" class="cd-back-link kh-editor__back"><span class="material-symbols-outlined">arrow_back</span> Volver a la lista</a>';

    echo '<div class="cd-editor">';
    echo '<h1 class="cd-editor__title kh-editor__title">Crear nuevo: ' . esc_html($cpt_obj->labels->singular_name) . '</h1>';
    echo '<p class="cd-editor__sub kh-editor__subtitle">Completa los campos y publica.</p>';
    cfd_maybe_render_view_hint( 'create', $cpt_obj->labels->singular_name );

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
        'html_submit_button' => '<button type="submit" class="cd-save-btn kh-editor__save"><span class="material-symbols-outlined">magic_button</span> Crear y publicar</button>',
        'html_submit_spinner' => '<span class="cd-spinner"></span>',
        'form_attributes' => array('class' => 'cd-acf-form'),
    ));

    echo '</div>'; // End cd-editor
    
    cfd_render_concierge_tip( 'Consejo', 'Los cambios se guardan y publican de inmediato. Asegúrate de revisarlos en la página online.' );

    echo '<a href="' . esc_url($back_url) . '" class="cd-back-link cd-back-link--bottom kh-editor__back"><span class="material-symbols-outlined">arrow_back</span> Volver a la lista</a>';
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