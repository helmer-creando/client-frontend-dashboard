<?php
/**
 * ============================================================
 * CFD Configuration
 * ============================================================
 *
 * Merges two sources:
 * 1. Hardcoded defaults below (fallbacks + slugs you set per project)
 * 2. Database settings from the admin page (Settings → Client Dashboard)
 *
 * The admin settings page lets you toggle which CPTs appear in the
 * dashboard, and configure slugs. If no DB settings exist yet, the
 * hardcoded defaults below are used as-is.
 *
 * v2.2 additions: CFD_POSTS_PER_PAGE constant, cfd_is_bricks_builder()
 * helper, and cfd_detect_available_cpts() for the settings page.
 * ============================================================
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Returns the full plugin configuration array.
 *
 * Every module calls this function instead of hardcoding slugs.
 * Results are cached per-request via a static variable so we
 * don't query wp_options multiple times per page load.
 *
 * @return array {
 *     @type string   $dashboard_slug   Slug of the dashboard page (e.g., 'mi-espacio').
 *     @type string   $login_slug       Slug of the login page (e.g., 'capitan').
 *     @type string   $login_redirect   Path to redirect to after login (e.g., '/mi-espacio/').
 *     @type int[]    $editable_pages   Page IDs clients can edit. Empty = auto-detect.
 *     @type string[] $manageable_cpts  CPT slugs clients can list/create/edit/delete.
 * }
 */
function cfd_get_config(): array
{
    // Cache the result so we don't query wp_options multiple times
    // per request (this function is called from nearly every module).
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    // ── Hardcoded defaults (edit per project) ────────────────
    $config = array(

        // ── Dashboard page slug ─────────────────────────────
        // The WordPress page where [client_dashboard] lives.
        'dashboard_slug' => 'mi-espacio',

        // ── Login page slug ─────────────────────────────────
        // The WordPress page where [cd_login_form] lives.
        'login_slug' => 'capitan',

        // ── Post-login redirect path ────────────────────────
        // Where site_editor users land after logging in.
        'login_redirect' => '/mi-espacio/',

        // ── Editable pages ──────────────────────────────────
        // Page IDs the client can edit from the dashboard.
        // Leave empty to auto-detect.
        'editable_pages' => array(),

        // ── Manageable CPTs ─────────────────────────────────
        // Fallback list used ONLY if nothing is saved in the DB yet.
        // Once you save from the settings page, the DB value takes over.
        'manageable_cpts' => array('retreats', 'testimonials', 'faq'),
    );

    // ── Merge with DB settings ──────────────────────────────
    // If the admin has saved settings via Settings → Client Dashboard,
    // those values override the hardcoded defaults above.
    $db_settings = get_option('cfd_settings', array());

    if (isset($db_settings['dashboard_slug']) && $db_settings['dashboard_slug'] !== '') {
        $config['dashboard_slug'] = $db_settings['dashboard_slug'];
    }
    if (isset($db_settings['login_slug']) && $db_settings['login_slug'] !== '') {
        $config['login_slug'] = $db_settings['login_slug'];
    }
    if (isset($db_settings['login_redirect']) && $db_settings['login_redirect'] !== '') {
        $config['login_redirect'] = $db_settings['login_redirect'];
    }
    // CPTs: if the admin has saved a selection, use it.
    // An empty array means "none selected" — which is a valid choice.
    if (isset($db_settings['manageable_cpts']) && is_array($db_settings['manageable_cpts'])) {
        $config['manageable_cpts'] = $db_settings['manageable_cpts'];
    }

    return $config;
}

/**
 * Number of CPT entries shown per page on the dashboard list view.
 * Used by cfd_render_cpt_list() for pagination.
 */
if (!defined('CFD_POSTS_PER_PAGE')) {
    define('CFD_POSTS_PER_PAGE', 20);
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
function cfd_is_bricks_builder(): bool
{
    return (
        (function_exists('bricks_is_builder') && bricks_is_builder()) ||
        (function_exists('bricks_is_builder_main') && bricks_is_builder_main()) ||
        isset($_GET['bricks']) ||
        (defined('DOING_AJAX') && DOING_AJAX)
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
function cfd_get_no_cache_slugs(): array
{
    $config = cfd_get_config();
    return array(
        $config['dashboard_slug'],
        $config['login_slug'],
    );
}

/**
 * Detects all custom post types that are candidates for the
 * client dashboard.
 *
 * Returns all public, non-built-in CPTs minus known utility types
 * from ACF, Bricks, WooCommerce internals, etc.
 *
 * Used by the admin settings page to show checkboxes.
 *
 * @return array Associative array of [ 'slug' => 'Label' ] pairs.
 */
function cfd_detect_available_cpts(): array
{
    $all_cpts = get_post_types(array(
        'public' => true,
        '_builtin' => false,
    ), 'objects');

    // Types to always exclude — internal/utility types from
    // WordPress, ACF, Bricks, and other common plugins.
    $exclude = array(
        'attachment', 'revision', 'nav_menu_item', 'custom_css',
        'customize_changeset', 'oembed_cache', 'user_request',
        'wp_block', 'wp_template', 'wp_template_part',
        'wp_global_styles', 'wp_navigation', 'wp_font_family',
        'wp_font_face',
        // ACF internal types
        'acf-field-group', 'acf-field', 'acf-taxonomy',
        'acf-post-type', 'acf-ui-options-page',
        // Bricks internal
        'bricks_template',
    );

    $detected = array();

    foreach ($all_cpts as $slug => $cpt_obj) {
        if (in_array($slug, $exclude, true)) {
            continue;
        }
        $detected[$slug] = $cpt_obj->labels->name;
    }

    asort($detected);

    return $detected;
}

// ═══════════════════════════════════════════════════════════
// v3.0 — DASHBOARD VIEW DETECTION
// ═══════════════════════════════════════════════════════════

/**
 * Detects which dashboard view is active based on URL parameters.
 *
 * Used by the Bricks condition system and the new v3 shortcodes
 * to determine what content to show on the single /mi-espacio/ page.
 *
 * @return string One of: 'home', 'edit_page', 'edit_cpt', 'manage', 'create'.
 */
function cfd_get_dashboard_view(): string
{
    static $view = null;
    if ($view !== null) {
        return $view;
    }

    $config = cfd_get_config();

    // Not on the dashboard page → no dashboard view.
    if (!is_page($config['dashboard_slug'])) {
        $view = '';
        return $view;
    }

    $edit = isset($_GET['edit']) ? sanitize_key($_GET['edit']) : '';
    $manage = isset($_GET['manage']) ? sanitize_key($_GET['manage']) : '';
    $create = isset($_GET['create']) ? sanitize_key($_GET['create']) : '';
    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;

    if ($edit === 'page' && $id > 0) {
        $view = 'edit_page';
    }
    elseif ($edit && $id > 0 && in_array($edit, $config['manageable_cpts'], true)) {
        $view = 'edit_cpt';
    }
    elseif ($manage && in_array($manage, $config['manageable_cpts'], true)) {
        $view = 'manage';
    }
    elseif ($create && in_array($create, $config['manageable_cpts'], true)) {
        $view = 'create';
    }
    else {
        $view = 'home';
    }

    return $view;
}

/**
 * Returns true when the dashboard is showing the home view.
 *
 * Convenience function used as a Bricks condition evaluator
 * and available for use in theme/template logic.
 *
 * @return bool
 */
function cfd_is_dashboard_home(): bool
{
    return cfd_get_dashboard_view() === 'home';
}

// ═══════════════════════════════════════════════════════════
// v3.0 — BRICKS CONDITION REGISTRATION
// ═══════════════════════════════════════════════════════════
//
// Registers a "Client Dashboard" conditions group in Bricks so
// the user can control element visibility based on the current
// dashboard view — directly from the Bricks editor UI.
//
// Usage in Bricks:
//   Element → Conditions → Add condition
//   → Group: "Client Dashboard"
//   → Option: "Home View"
//   → Compare: "is" or "is not"
//
// ─────────────────────────────────────────────────────────

/**
 * Add "Client Dashboard" to the Bricks conditions group list.
 */
add_filter('bricks/conditions/groups', 'cfd_bricks_conditions_groups');

function cfd_bricks_conditions_groups(array $groups): array
{
    $groups[] = array(
        'name' => 'cfd',
        'label' => 'Client Dashboard',
    );
    return $groups;
}

/**
 * Add condition options within the "Client Dashboard" group.
 */
add_filter('bricks/conditions/options', 'cfd_bricks_conditions_options');

function cfd_bricks_conditions_options(array $options): array
{
    $options[] = array(
        'key' => 'cfd_home_view',
        'label' => 'Home View',
        'group' => 'cfd',
        'compare' => array(
            'type' => 'select',
            'options' => array(
                '==' => 'is',
                '!=' => 'is not',
            ),
        ),
        'value' => array(
            'type' => 'select',
            'options' => array(
                'true' => 'Active (home)',
                'false' => 'Active (edit/manage/create)',
            ),
        ),
    );
    return $options;
}

/**
 * Evaluate the "Home View" condition.
 *
 * Bricks calls this filter for every condition on every element.
 * We only handle our own key ('cfd_home_view') and pass through
 * everything else.
 *
 * API reference: https://academy.bricksbuilder.io/article/element-conditions/
 *
 * @param bool   $result        Current result (from previous filters).
 * @param string $condition_key The condition key (e.g., 'cfd_home_view').
 * @param array  $condition     The full condition array with 'compare' and 'value' keys.
 * @return bool
 */
add_filter('bricks/conditions/result', 'cfd_bricks_conditions_result', 10, 3);

function cfd_bricks_conditions_result($result, $condition_key, $condition)
{
    if ($condition_key !== 'cfd_home_view') {
        return $result;
    }

    // Extract compare and value from the condition array.
    $compare = isset($condition['compare']) ? $condition['compare'] : '==';
    $value = isset($condition['value']) ? $condition['value'] : 'true';

    $is_home = cfd_is_dashboard_home();
    $expected = ($value === 'true');

    if ($compare === '==') {
        return $is_home === $expected;
    }

    // '!=' comparison.
    return $is_home !== $expected;
}

// ═══════════════════════════════════════════════════════════
// v3.0 — BRICKS DYNAMIC DATA TAGS
// ═══════════════════════════════════════════════════════════
//
// Registers `{cfd_logout_url}` as a native Bricks dynamic tag.
// This allows you to select the logout URL from the lightning
// bolt icon in Bricks (e.g., for a logout button link).
//
// ─────────────────────────────────────────────────────────

add_filter('bricks/dynamic_tags_list', 'cfd_register_dynamic_tags');

function cfd_register_dynamic_tags($tags)
{
    $tags[] = array(
        'name' => '{cfd_logout_url}',
        'label' => 'CFD: Logout URL',
        'group' => 'Client Dashboard',
    );
    return $tags;
}

add_filter('bricks/dynamic_data/render_tag', 'cfd_render_dynamic_tags', 10, 3);

function cfd_render_dynamic_tags($tag, $post, $context = 'text')
{
    if ($tag !== 'cfd_logout_url') {
        return $tag;
    }

    if (function_exists('cfd_get_logout_url')) {
        return esc_url(cfd_get_logout_url());
    }

    return '';
}