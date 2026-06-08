<?php
/**
 * ============================================================
 * Module: ACF Options Page Editing (frontend)
 * ============================================================
 *
 * Lets non-technical clients edit ACF Options Pages (site-wide
 * settings — featured content, archive intro copy, contact info,
 * hero text, etc.) from the frontend dashboard, without ever
 * touching wp-admin.
 *
 * OPT-IN, CONFIGURED IN THE UI. Options pages are auto-detected
 * from ACF, then the admin ticks which ones clients may edit in
 * Settings → Client Dashboard — exactly like Manageable CPTs.
 * No per-site code snippet required.
 *
 * For each enabled page the plugin reads everything it needs
 * straight from ACF:
 *   - label        ← the page's menu/page title
 *   - icon         ← the page's Dashicon (icon_url)
 *   - options_id   ← the page's ACF post_id (NOT always 'option')
 *   - field_groups ← the field groups located to that page
 *   - capability   ← the page's required capability (def. edit_posts)
 *
 * If no pages are enabled (or the user can't access any), this
 * module renders nothing — no sidebar section, no routes. Sites
 * that never enable a page behave byte-identically to before.
 * ============================================================
 */

if (!defined('ABSPATH')) {
    exit;
}

// ═══════════════════════════════════════════════════════════
// 1. DETECTION + REGISTRY
// ═══════════════════════════════════════════════════════════

/**
 * Detects every ACF Options Page registered on the site and
 * normalizes each into the plugin's internal shape.
 *
 * Reads label/icon/post_id/capability from ACF, and resolves the
 * field groups located to each page. Returns all detected pages
 * (the admin selection is applied later in cfd_get_options_pages()).
 *
 * @return array<string,array> Map of sanitized menu_slug => config.
 */
function cfd_detect_options_pages(): array
{
    if (!function_exists('acf_get_options_pages')) {
        return array();
    }

    $raw = acf_get_options_pages();
    if (empty($raw) || !is_array($raw)) {
        return array();
    }

    $detected = array();

    foreach ($raw as $page) {
        if (empty($page['menu_slug'])) {
            continue;
        }

        $key = sanitize_key($page['menu_slug']);
        if ($key === '') {
            continue;
        }

        // Resolve the field groups whose location targets this page.
        $group_keys = array();
        if (function_exists('acf_get_field_groups')) {
            $groups = acf_get_field_groups(array('options_page' => $page['menu_slug']));
            foreach ((array) $groups as $group) {
                if (!empty($group['key'])) {
                    $group_keys[] = $group['key'];
                }
            }
        }

        $detected[$key] = array(
            'label'        => $page['menu_title'] ?: ($page['page_title'] ?: $key),
            'description'  => '',
            'icon'         => cfd_normalize_options_icon($page['icon_url'] ?? ''),
            'options_id'   => $page['post_id'] ?? 'options',
            'field_groups' => $group_keys,
            'capability'   => $page['capability'] ?? 'edit_posts',
            'menu_slug'    => $page['menu_slug'],
        );
    }

    return $detected;
}

/**
 * Normalizes an ACF options-page icon into a Dashicon class.
 *
 * ACF stores `icon_url` as false, a Dashicon string, or a URL.
 * The sidebar renders a Dashicon span, so URL/SVG icons fall back
 * to a generic Dashicon rather than breaking the markup.
 *
 * @param string $icon Raw ACF icon_url value.
 * @return string Dashicon class.
 */
function cfd_normalize_options_icon($icon): string
{
    $icon = (string) $icon;

    if ($icon === '') {
        return 'dashicons-admin-generic';
    }
    if (strpos($icon, 'dashicons-') === 0) {
        return $icon;
    }
    // URL or data URI → can't be a Dashicon class; use a safe default.
    if (strpos($icon, '/') !== false || strpos($icon, ':') !== false) {
        return 'dashicons-admin-generic';
    }
    // Bare name (e.g. "star-filled") → prefix it.
    return 'dashicons-' . $icon;
}

/**
 * Returns the options pages the admin has enabled for the dashboard.
 *
 * Detected pages filtered to the selection saved in
 * cfd_settings['options_pages']. Cached per request.
 *
 * @return array<string,array> Map of key => normalized config.
 */
function cfd_get_options_pages(): array
{
    static $pages = null;
    if ($pages !== null) {
        return $pages;
    }

    $detected = cfd_detect_options_pages();

    $settings = get_option('cfd_settings', array());
    $enabled  = isset($settings['options_pages']) && is_array($settings['options_pages'])
        ? $settings['options_pages']
        : array();

    $pages = array();
    foreach ($detected as $key => $page) {
        if (in_array($key, $enabled, true)) {
            $pages[$key] = $page;
        }
    }

    // Extensibility hook: advanced sites can tweak labels, help text,
    // icons, or field groups for the enabled pages. Not the primary
    // configuration path — enabling is done via the settings page.
    $pages = apply_filters('cfd_options_pages', $pages);

    return $pages;
}

/**
 * Returns a single normalized options-page config, or null.
 *
 * @param string $key Registry key.
 * @return array|null
 */
function cfd_get_options_page(string $key): ?array
{
    $pages = cfd_get_options_pages();
    return $pages[$key] ?? null;
}

// ═══════════════════════════════════════════════════════════
// 2. ACCESS CONTROL
// ═══════════════════════════════════════════════════════════

/**
 * Whether the given user may access a specific options page.
 *
 * Two gates, mirroring the rest of the plugin:
 *   1. The configured `capability` (default 'edit_posts').
 *   2. The per-user allowlist — same shape as CPTs/pages. Only
 *      applies to non-admins who have `cfd_restrict_access` on.
 *      If the user is restricted but the options allowlist meta
 *      was never set, access is unrestricted (matches how
 *      `cfd_user_cpts` behaves in cfd_get_user_config()).
 *
 * @param string   $key     Registry key.
 * @param int|null $user_id Defaults to current user.
 * @return bool
 */
function cfd_user_can_access_options_page(string $key, ?int $user_id = null): bool
{
    $page = cfd_get_options_page($key);
    if (!$page) {
        return false;
    }

    if ($user_id === null) {
        $user_id = get_current_user_id();
    }

    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }

    // Capability gate.
    if (!user_can($user, $page['capability'])) {
        return false;
    }

    // Admins bypass the per-user allowlist.
    if ($user->has_cap('manage_options')) {
        return true;
    }

    // Per-user restriction off → full access.
    if (get_user_meta($user_id, 'cfd_restrict_access', true) !== '1') {
        return true;
    }

    // Restriction on. Allowlist not configured → unrestricted (mirrors CPTs).
    $allowed = get_user_meta($user_id, 'cfd_user_options_pages', true);
    if (!is_array($allowed)) {
        return true;
    }

    return in_array($key, $allowed, true);
}

/**
 * Returns the options pages the given user can access, keyed by slug.
 *
 * @param int|null $user_id Defaults to current user.
 * @return array<string,array>
 */
function cfd_get_accessible_options_pages(?int $user_id = null): array
{
    $out = array();
    foreach (cfd_get_options_pages() as $key => $page) {
        if (cfd_user_can_access_options_page($key, $user_id)) {
            $out[$key] = $page;
        }
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════
// 3. RENDERER
// ═══════════════════════════════════════════════════════════

/**
 * Renders the editing form for one options page.
 *
 * Echoes its output (the caller wraps it in output buffering, like
 * every other dashboard view). Renders nothing for unknown keys or
 * users without access — they should never see options they can't
 * reach.
 *
 * @param string $key Registry key.
 */
function cfd_render_options_page(string $key): void
{
    $page = cfd_get_options_page($key);
    if (!$page || !cfd_user_can_access_options_page($key)) {
        return;
    }

    if (empty($page['field_groups'])) {
        echo '<div class="cd-error">Esta sección de ajustes no tiene campos configurados.</div>';
        return;
    }

    $dashboard_url = cfd_get_dashboard_url();
    $return_url = add_query_arg(
        array('options' => $key, 'updated' => 'true'),
        $dashboard_url
    );

    echo '<a href="' . esc_url($dashboard_url) . '" class="cd-back-link kh-editor__back">' . cfd_icon('arrow_back') . ' Volver al inicio</a>';

    echo '<div class="cd-editor">';
    echo '<div class="cd-editor__header kh-editor__header">';
    echo '  <h1 class="cd-editor__title kh-editor__title">' . esc_html($page['label']) . '</h1>';
    if ($page['description'] !== '') {
        echo '  <p class="cd-view-hint">' . esc_html($page['description']) . '</p>';
    }
    echo '</div>';

    if (isset($_GET['updated']) && $_GET['updated'] === 'true') {
        echo '<div class="cd-success kh-editor__success">';
        echo '  ' . cfd_icon('check_circle', 'kh-icon--filled') . '';
        echo '  <span>¡Tus cambios han sido guardados!</span>';
        echo '</div>';
    }

    $submit_html = '<div class="kh-editor__save-cluster">';
    $submit_html .= '<button type="submit" class="cd-save-btn kh-editor__save">' . cfd_icon('save') . ' Guardar cambios</button>';
    $submit_html .= '<span class="kh-editor__save-hint">Los cambios se publican de inmediato.</span>';
    $submit_html .= '</div>';

    echo '<div class="cd-editor-form kh-editor__grid">';

    acf_form(array(
        'post_id'            => $page['options_id'],
        'post_title'         => false,
        'post_content'       => false,
        'field_groups'       => $page['field_groups'],
        'submit_value'       => 'Guardar cambios',
        'updated_message'    => false,
        'return'             => $return_url,
        'html_submit_button' => $submit_html,
        'html_submit_spinner' => '',
        'form_attributes'    => array('class' => 'cd-acf-form'),
    ));

    echo '</div>'; // .cd-editor-form
    echo '</div>'; // .cd-editor

    echo '<a href="' . esc_url($dashboard_url) . '" class="cd-back-link cd-back-link--bottom kh-editor__back">' . cfd_icon('arrow_back') . ' Volver al inicio</a>';
}
