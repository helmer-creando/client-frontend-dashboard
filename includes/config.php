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
    $tags[] = array(
        'name' => '{cfd_nav_label}',
        'label' => 'CFD Nav: Label',
        'group' => 'Client Dashboard',
    );
    $tags[] = array(
        'name' => '{cfd_nav_url}',
        'label' => 'CFD Nav: URL',
        'group' => 'Client Dashboard',
    );
    $tags[] = array(
        'name' => '{cfd_nav_icon}',
        'label' => 'CFD Nav: Dashicon class',
        'group' => 'Client Dashboard',
    );
    $tags[] = array(
        'name' => '{cfd_nav_active_class}',
        'label' => 'CFD Nav: Active CSS class',
        'group' => 'Client Dashboard',
    );
    $tags[] = array(
        'name' => '{cfd_client_logo}',
        'label' => 'CFD: Client Logo URL',
        'group' => 'Client Dashboard',
    );
    return $tags;
}

add_filter('bricks/dynamic_data/render_tag', 'cfd_render_dynamic_tags', 10, 3);

function cfd_render_dynamic_tags($tag, $post, $context = 'text')
{
    if ($tag === 'cfd_logout_url') {
        return function_exists('cfd_get_logout_url')
            ? esc_url(cfd_get_logout_url())
            : '';
    }

    if ($tag === 'cfd_client_logo') {
        $settings = get_option('cfd_settings', array());
        $logo_id = isset($settings['client_logo_id']) ? absint($settings['client_logo_id']) : 0;

        if (!$logo_id) {
            return '';
        }

        // If Bricks is specifically asking for an image (e.g., Image element),
        // it needs the attachment ID, not the URL string, so it can build the
        // responsive img tag itself using wp_get_attachment_image().
        if ($context === 'image') {
            return $logo_id;
        }

        // Otherwise (e.g., text, link, background-image), return the URL string.
        $url = wp_get_attachment_image_url($logo_id, 'full');
        return $url ? esc_url($url) : '';
    }

    // Sidebar nav dynamic tags — require the current loop object.
    $nav_tags = array('cfd_nav_label', 'cfd_nav_url', 'cfd_nav_icon', 'cfd_nav_active_class');
    if (!in_array($tag, $nav_tags, true)) {
        return $tag;
    }

    // Get the current loop object (set by our custom query).
    $item = cfd_get_current_nav_item();
    if (!$item) {
        return '';
    }

    switch ($tag) {
        case 'cfd_nav_label':
            return esc_html($item->label);
        case 'cfd_nav_url':
            return esc_url($item->url);
        case 'cfd_nav_icon':
            return esc_attr($item->icon);
        case 'cfd_nav_active_class':
            return $item->is_active ? 'is-active' : '';
    }

    return '';
}

// ─────────────────────────────────────────────────────────
// render_content + render_data: needed for Bricks to find
// and replace our tags in content strings (Image elements,
// background images, etc.) — render_tag alone only fires
// when Bricks already identified the tag.
// ─────────────────────────────────────────────────────────

add_filter('bricks/dynamic_data/render_content', 'cfd_render_dynamic_content', 10, 3);
add_filter('bricks/frontend/render_data', 'cfd_render_dynamic_content', 10, 2);

function cfd_render_dynamic_content($content, $post = null, $context = 'text')
{
    // Only process if our tags exist in the content string.
    if (strpos($content, '{cfd_') === false) {
        return $content;
    }

    // {cfd_logout_url}
    if (strpos($content, '{cfd_logout_url}') !== false) {
        $logout_url = function_exists('cfd_get_logout_url') ? esc_url(cfd_get_logout_url()) : '';
        $content = str_replace('{cfd_logout_url}', $logout_url, $content);
    }

    // {cfd_client_logo}
    if (strpos($content, '{cfd_client_logo}') !== false) {
        $logo_url = '';
        $settings = get_option('cfd_settings', array());
        $logo_id = isset($settings['client_logo_id']) ? absint($settings['client_logo_id']) : 0;
        if ($logo_id) {
            $url = wp_get_attachment_image_url($logo_id, 'full');
            $logo_url = $url ? esc_url($url) : '';
        }
        $content = str_replace('{cfd_client_logo}', $logo_url, $content);
    }

    return $content;
}

// ═══════════════════════════════════════════════════════════
// FALLBACK SHORTCODE: [cfd_client_logo]
// For when Bricks Image element refuses to parse dynamic tags.
// Allows setting max_width or class.
// Example: [cfd_client_logo class="my-logo" max_width="250px"]
// ═══════════════════════════════════════════════════════════

add_shortcode('cfd_client_logo', 'cfd_client_logo_shortcode');

function cfd_client_logo_shortcode($atts)
{
    $settings = get_option('cfd_settings', array());
    $logo_id = isset($settings['client_logo_id']) ? absint($settings['client_logo_id']) : 0;

    if (!$logo_id) {
        return '';
    }

    $atts = shortcode_atts(array(
        'class' => 'cfd-client-logo',
        'max_width' => '100%',
    ), $atts, 'cfd_client_logo');

    $html = wp_get_attachment_image($logo_id, 'full', false, array(
        'class' => esc_attr($atts['class']),
        'style' => 'height: auto; width: auto; max-width: ' . esc_attr($atts['max_width']) . '; object-fit: contain;',
    ));

    return $html;
}

/**
 * Helper: returns the current Bricks loop object if it's a nav item.
 *
 * Bricks stores the current loop object internally via its Query class.
 * We access it through the global Bricks query instance.
 *
 * @return object|null  Nav item with ->label, ->url, ->icon, ->is_active.
 */
function cfd_get_current_nav_item()
{
    if (!class_exists('\Bricks\Query')) {
        return null;
    }
    $loop_obj = \Bricks\Query::get_loop_object();
    if ($loop_obj && isset($loop_obj->cfd_nav_item)) {
        return $loop_obj;
    }
    return null;
}

// ═══════════════════════════════════════════════════════════
// v3.0 — BRICKS QUERY LOOP: SIDEBAR NAV
// ═══════════════════════════════════════════════════════════
//
// Registers "cfd_nav" as a custom Bricks query type so the
// sidebar navigation items can be rendered via a Bricks Query
// Loop. The user designs each nav item visually in Bricks.
//
// ─────────────────────────────────────────────────────────

/**
 * Step 1: Add "CFD Sidebar Nav" to the query type dropdown.
 */
add_filter('bricks/setup/control_options', 'cfd_register_query_type');

function cfd_register_query_type($control_options)
{
    $control_options['queryTypes']['cfd_nav'] = esc_html__('CFD Sidebar Nav', 'cfd');
    return $control_options;
}

/**
 * Step 2: Run the custom query — return nav items as objects.
 */
add_filter('bricks/query/run', 'cfd_run_nav_query', 10, 2);

function cfd_run_nav_query($results, $query)
{
    if ($query->object_type !== 'cfd_nav') {
        return $results;
    }

    return cfd_build_nav_items();
}

/**
 * Step 3: Tell Bricks how to read each loop object.
 */
add_filter('bricks/query/loop_object', 'cfd_nav_loop_object', 10, 3);

function cfd_nav_loop_object($loop_object, $loop_key, $query)
{
    if ($query->object_type !== 'cfd_nav') {
        return $loop_object;
    }
    return $loop_object;
}

/**
 * Step 4: Provide a unique ID for each loop object.
 */
add_filter('bricks/query/loop_object_id', 'cfd_nav_loop_object_id', 10, 3);

function cfd_nav_loop_object_id($object_id, $loop_object, $query)
{
    if ($query->object_type !== 'cfd_nav') {
        return $object_id;
    }
    return isset($loop_object->slug) ? $loop_object->slug : $object_id;
}

/**
 * Step 5: Provide the loop object type.
 */
add_filter('bricks/query/loop_object_type', 'cfd_nav_loop_object_type', 10, 3);

function cfd_nav_loop_object_type($object_type, $loop_object, $query)
{
    if ($query->object_type !== 'cfd_nav') {
        return $object_type;
    }
    return 'cfd_nav';
}

/**
 * Build the nav items array from plugin config.
 *
 * Returns an array of stdClass objects, each with:
 *   ->slug       string  Unique identifier
 *   ->label      string  Display label
 *   ->url        string  Link URL
 *   ->icon       string  Dashicons CSS class (e.g. "dashicons dashicons-dashboard")
 *   ->is_active  bool    Whether this item matches the current view
 *   ->cfd_nav_item bool  Marker so we can identify these in the loop
 *
 * @return object[]
 */
function cfd_build_nav_items(): array
{
    if (!function_exists('cfd_get_config')) {
        return array();
    }

    $config = cfd_get_config();
    $dashboard_url = cfd_get_dashboard_url();
    $view = cfd_get_dashboard_view();

    // Determine active slug.
    $active_slug = 'home';
    if ($view === 'edit_page') {
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

    $items = array();

    // 1. Inicio.
    $item = new \stdClass();
    $item->cfd_nav_item = true;
    $item->slug = 'home';
    $item->label = 'Inicio';
    $item->url = $dashboard_url;
    $item->icon = 'dashicons dashicons-dashboard';
    $item->is_active = ($active_slug === 'home');
    $items[] = $item;

    // 2. Manageable CPTs.
    if (!empty($config['manageable_cpts'])) {
        foreach ($config['manageable_cpts'] as $cpt_slug) {
            $cpt_obj = get_post_type_object($cpt_slug);
            if (!$cpt_obj) {
                continue;
            }

            // Resolve icon.
            $icon_class = 'dashicons dashicons-admin-post'; // fallback
            if (!empty($cpt_obj->menu_icon) && strpos($cpt_obj->menu_icon, 'dashicons-') === 0) {
                $icon_class = 'dashicons ' . $cpt_obj->menu_icon;
            }

            $item = new \stdClass();
            $item->cfd_nav_item = true;
            $item->slug = $cpt_slug;
            $item->label = $cpt_obj->labels->name;
            $item->url = add_query_arg(array('manage' => $cpt_slug), $dashboard_url);
            $item->icon = $icon_class;
            $item->is_active = ($active_slug === $cpt_slug);
            $items[] = $item;
        }
    }

    return $items;
}