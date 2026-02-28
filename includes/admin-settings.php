<?php
/**
 * ============================================================
 * Module: Admin Settings Page
 * ============================================================
 *
 * Adds a settings page under Settings → Client Dashboard
 * where you can:
 * - Configure the dashboard and login page slugs
 * - See all detected CPTs and toggle which ones clients can manage
 * - Re-sync the site_editor role capabilities after changes
 *
 * Settings are stored in wp_options as 'cfd_settings'.
 * The config.php file merges these with hardcoded defaults.
 * ============================================================
 */

if (!defined('ABSPATH')) {
    exit;
}

// ═══════════════════════════════════════════════════════════
// 1. REGISTER THE SETTINGS PAGE
// ═══════════════════════════════════════════════════════════

add_action('admin_menu', 'cfd_add_settings_page');

function cfd_add_settings_page(): void
{
    add_options_page(
        'Client Dashboard Settings', // Page title
        'Client Dashboard', // Menu title
        'manage_options', // Capability required
        'cfd-settings', // Menu slug
        'cfd_render_settings_page' // Callback
    );
}

// ═══════════════════════════════════════════════════════════
// 2. REGISTER SETTINGS
// ═══════════════════════════════════════════════════════════

add_action('admin_init', 'cfd_register_settings');

function cfd_register_settings(): void
{
    register_setting('cfd_settings_group', 'cfd_settings', array(
        'type' => 'array',
        'sanitize_callback' => 'cfd_sanitize_settings',
        'default' => array(),
    ));
}

/**
 * Sanitize and validate settings before saving.
 */
function cfd_sanitize_settings($input): array
{
    $clean = array();

    // Slugs — sanitize as URL slugs.
    if (isset($input['dashboard_slug'])) {
        $clean['dashboard_slug'] = sanitize_title($input['dashboard_slug']);
    }
    if (isset($input['login_slug'])) {
        $clean['login_slug'] = sanitize_title($input['login_slug']);
    }

    // Login redirect — sanitize as a path.
    if (isset($input['login_redirect'])) {
        $path = sanitize_text_field($input['login_redirect']);
        // Ensure it starts and ends with a slash.
        $path = '/' . trim($path, '/') . '/';
        $clean['login_redirect'] = $path;
    }

    // CPTs — array of slug strings. Only keep valid, registered CPTs.
    if (isset($input['manageable_cpts']) && is_array($input['manageable_cpts'])) {
        $clean['manageable_cpts'] = array_map('sanitize_key', $input['manageable_cpts']);
    }
    else {
        // No checkboxes checked = empty array (valid: means no CPTs).
        $clean['manageable_cpts'] = array();
    }

    // After saving, re-sync role capabilities so the site_editor
    // role gets caps for the newly selected CPTs.
    // We do this by bumping the caps version.
    $cpt_string = implode('-', $clean['manageable_cpts']);
    $new_version = 'v-' . md5($cpt_string);
    update_option('cfd_cpt_caps_version', ''); // Force re-sync on next load.

    return $clean;
}

// ═══════════════════════════════════════════════════════════
// 3. RENDER THE SETTINGS PAGE
// ═══════════════════════════════════════════════════════════

function cfd_render_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $config = cfd_get_config();
    $db_settings = get_option('cfd_settings', array());
    $available_cpts = cfd_detect_available_cpts();
    $selected_cpts = $config['manageable_cpts'];

    // Check if settings have been saved at least once.
    $has_saved = !empty($db_settings);
?>
    <div class="wrap">
        <h1>Client Dashboard Settings</h1>

        <?php if (!$has_saved): ?>
        <div class="notice notice-info">
            <p>
                <strong>First time setup:</strong> The values below are loaded from the
                plugin defaults in <code>config.php</code>. Save once to store them in the
                database. After that, changes here take priority over the defaults.
            </p>
        </div>
        <?php
    endif; ?>

        <?php settings_errors('cfd_settings_group'); ?>

        <form method="post" action="options.php">
            <?php settings_fields('cfd_settings_group'); ?>

            <!-- ── Page Slugs ──────────────────────────────── -->
            <h2>Page Slugs</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="cfd_dashboard_slug">Dashboard page slug</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="cfd_dashboard_slug"
                            name="cfd_settings[dashboard_slug]"
                            value="<?php echo esc_attr($config['dashboard_slug']); ?>"
                            class="regular-text"
                        />
                        <p class="description">
                            The page where <code>[client_dashboard]</code> lives.
                            Example: <code>mi-espacio</code>, <code>my-dashboard</code>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="cfd_login_slug">Login page slug</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="cfd_login_slug"
                            name="cfd_settings[login_slug]"
                            value="<?php echo esc_attr($config['login_slug']); ?>"
                            class="regular-text"
                        />
                        <p class="description">
                            The page where <code>[cd_login_form]</code> lives.
                            Example: <code>capitan</code>, <code>login</code>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="cfd_login_redirect">Post-login redirect</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="cfd_login_redirect"
                            name="cfd_settings[login_redirect]"
                            value="<?php echo esc_attr($config['login_redirect']); ?>"
                            class="regular-text"
                        />
                        <p class="description">
                            Where <code>site_editor</code> users land after login.
                            Should match the dashboard slug. Example: <code>/mi-espacio/</code>
                        </p>
                    </td>
                </tr>
            </table>

            <!-- ── Manageable CPTs ─────────────────────────── -->
            <h2>Manageable Content Types</h2>
            <p>
                Select which post types clients can create, edit, and delete from
                the frontend dashboard. Only public, non-built-in types are shown.
            </p>

            <?php if (empty($available_cpts)): ?>
                <div class="notice notice-warning inline">
                    <p>
                        No custom post types detected. Register CPTs via ACF → Post Types
                        (or code), then return here.
                    </p>
                </div>
            <?php
    else: ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Available CPTs</th>
                        <td>
                            <fieldset>
                                <?php foreach ($available_cpts as $slug => $label):
            $checked = in_array($slug, $selected_cpts, true);
?>
                                <label style="display: block; margin-bottom: 0.5em;">
                                    <input
                                        type="checkbox"
                                        name="cfd_settings[manageable_cpts][]"
                                        value="<?php echo esc_attr($slug); ?>"
                                        <?php checked($checked); ?>
                                    />
                                    <strong><?php echo esc_html($label); ?></strong>
                                    <code style="margin-left: 0.4em; color: #888;"><?php echo esc_html($slug); ?></code>
                                </label>
                                <?php
        endforeach; ?>
                            </fieldset>
                            <p class="description" style="margin-top: 1em;">
                                Saving will automatically update the <code>site_editor</code>
                                role capabilities to match your selection.
                            </p>
                        </td>
                    </tr>
                </table>
            <?php
    endif; ?>

            <!-- ── Role Status ─────────────────────────────── -->
            <h2>Role Status</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">site_editor role</th>
                    <td>
                        <?php
    $role = get_role('site_editor');
    if ($role) {
        echo '<span style="color: #46B450;">✅ Registered</span>';
        $cap_count = count(array_filter($role->capabilities));
        echo ' <span class="description">(' . $cap_count . ' capabilities)</span>';
    }
    else {
        echo '<span style="color: #DC3232;">❌ Not found</span>';
        echo ' <span class="description">— Try deactivating and reactivating the plugin.</span>';
    }
?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Caps version</th>
                    <td>
                        <code><?php echo esc_html(get_option('cfd_cpt_caps_version', '(not set)')); ?></code>
                        <p class="description">
                            Changes automatically when you save CPT selections above.
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Save Settings'); ?>
        </form>

        <!-- ── Shortcode Reference ─────────────────────────── -->
        <hr />
        <h2>Shortcode Reference</h2>
        <table class="widefat fixed" style="max-width: 600px;">
            <thead>
                <tr>
                    <th>Shortcode</th>
                    <th>Where to use</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>[client_dashboard]</code></td>
                    <td>Dashboard page (renders the full dashboard)</td>
                </tr>
                <tr>
                    <td><code>[cd_login_form]</code></td>
                    <td>Login page (renders login/password reset forms)</td>
                </tr>
                <tr>
                    <td><code>[cd_login_error]</code></td>
                    <td>Login page (renders error/success messages)</td>
                </tr>
                <tr>
                    <td><code>[cd_logout_url]</code></td>
                    <td>Any template — outputs the logout URL for links/buttons</td>
                </tr>
                <tr>
                    <td colspan="2" style="background: #f0f0f1; font-weight: 600; padding: 8px 10px;">
                        v3.0 — Composable shortcodes (for Bricks templates)
                    </td>
                </tr>
                <tr>
                    <td><code>[cfd_page_cards]</code></td>
                    <td>Renders the editable page card grid (home view)</td>
                </tr>
                <tr>
                    <td><code>[cfd_cpt_cards]</code></td>
                    <td>Renders the CPT card grid (home view)</td>
                </tr>
                <tr>
                    <td><code>[cfd_view_router]</code></td>
                    <td>Renders edit/manage/create views (non-home views)</td>
                </tr>
                <tr>
                    <td><code>[cfd_sidebar_nav]</code></td>
                    <td>Dynamic sidebar nav with Dashicons (auto from config)</td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}