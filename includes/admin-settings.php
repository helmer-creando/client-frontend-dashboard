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

    // Accent color source — ACSS palette name or 'custom'.
    $valid_sources = array( '', 'primary', 'secondary', 'accent', 'custom' );
    if (isset($input['accent_source'])) {
        $source = sanitize_key($input['accent_source']);
        $clean['accent_source'] = in_array($source, $valid_sources, true) ? $source : '';
    }

    // Accent color — hex string (only used when accent_source = 'custom').
    if (isset($input['accent_color'])) {
        $color = sanitize_hex_color($input['accent_color']);
        $clean['accent_color'] = $color ? $color : '';
    }

    // Welcome message — plain text string.
    if (isset($input['welcome_message'])) {
        $clean['welcome_message'] = sanitize_text_field($input['welcome_message']);
    }

    // Show help hints — checkbox (boolean).
    $clean['show_hints'] = !empty($input['show_hints']);

    // Client logo — attachment ID.
    if (isset($input['client_logo_id'])) {
        $clean['client_logo_id'] = absint($input['client_logo_id']);
    }

    // Quick links — repeater array of link objects.
    if (isset($input['quick_links']) && is_array($input['quick_links'])) {
        $clean_links = array();
        foreach ($input['quick_links'] as $link) {
            if (!is_array($link)) continue;
            $label = isset($link['label']) ? sanitize_text_field($link['label']) : '';
            // Skip rows with no label (empty/deleted rows).
            if ($label === '') continue;
            $clean_links[] = array(
                'label'  => $label,
                'icon'   => isset($link['icon']) ? sanitize_text_field($link['icon']) : 'dashicons-admin-generic',
                'url'    => isset($link['url']) ? esc_url_raw($link['url']) : '',
                'hint'   => isset($link['hint']) ? sanitize_text_field($link['hint']) : '',
                'target' => (isset($link['target']) && $link['target'] === '_self') ? '_self' : '_blank',
            );
        }
        $clean['quick_links'] = $clean_links;
    } else {
        $clean['quick_links'] = array();
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

    // Enqueue the WP Media uploader.
    wp_enqueue_media();

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

            <!-- ── Client Logo ───────────────────────────── -->
            <h2>Client Logo</h2>
            <p>
                Upload the client&rsquo;s logo. It will be available as a Bricks dynamic tag
                <code>{cfd_client_logo}</code> for use in login forms and navigation.
            </p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Logo</th>
                    <td>
                        <?php
    $logo_id = isset($db_settings['client_logo_id']) ? absint($db_settings['client_logo_id']) : 0;
    $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
?>
                        <div id="cfd-logo-preview" style="margin-bottom: 10px;">
                            <?php if ($logo_url): ?>
                                <img src="<?php echo esc_url($logo_url); ?>" style="max-width: 200px; height: auto; border-radius: 6px;" />
                            <?php
    endif; ?>
                        </div>
                        <input
                            type="hidden"
                            id="cfd_client_logo_id"
                            name="cfd_settings[client_logo_id]"
                            value="<?php echo esc_attr($logo_id); ?>"
                        />
                        <button type="button" class="button" id="cfd-logo-upload">
                            <?php echo $logo_id ? 'Change Logo' : 'Upload Logo'; ?>
                        </button>
                        <?php if ($logo_id): ?>
                        <button type="button" class="button" id="cfd-logo-remove" style="margin-left: 6px; color: #a00;">
                            Remove
                        </button>
                        <?php
    endif; ?>
                        <script>
                        jQuery(function($) {
                            var frame;
                            $('#cfd-logo-upload').on('click', function(e) {
                                e.preventDefault();
                                if (frame) { frame.open(); return; }
                                frame = wp.media({
                                    title: 'Select Client Logo',
                                    button: { text: 'Use this logo' },
                                    multiple: false,
                                    library: { type: 'image' }
                                });
                                frame.on('select', function() {
                                    var attachment = frame.state().get('selection').first().toJSON();
                                    $('#cfd_client_logo_id').val(attachment.id);
                                    $('#cfd-logo-preview').html('<img src="' + attachment.url + '" style="max-width: 200px; height: auto; border-radius: 6px;" />');
                                    $('#cfd-logo-upload').text('Change Logo');
                                    if (!$('#cfd-logo-remove').length) {
                                        $('<button type="button" class="button" id="cfd-logo-remove" style="margin-left: 6px; color: #a00;">Remove</button>').insertAfter('#cfd-logo-upload');
                                        bindRemove();
                                    }
                                });
                                frame.open();
                            });
                            function bindRemove() {
                                $(document).on('click', '#cfd-logo-remove', function(e) {
                                    e.preventDefault();
                                    $('#cfd_client_logo_id').val('0');
                                    $('#cfd-logo-preview').html('');
                                    $('#cfd-logo-upload').text('Upload Logo');
                                    $(this).remove();
                                });
                            }
                            bindRemove();
                        });
                        </script>
                    </td>
                </tr>
            </table>

            <!-- ── Accent Color ──────────────────────────── -->
            <h2>Accent Color</h2>
            <p>
                Controls the dashboard&rsquo;s color scheme. Map it to an
                <strong>Automatic.CSS</strong> palette so it stays in sync with
                your site&rsquo;s design tokens, or pick a custom hex.
            </p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="cfd_accent_source">Color de acento</label>
                    </th>
                    <td>
                        <?php
    $accent_source = isset($db_settings['accent_source']) ? $db_settings['accent_source'] : '';
    $accent_color  = isset($db_settings['accent_color'])  ? $db_settings['accent_color']  : '';
?>
                        <select id="cfd_accent_source" name="cfd_settings[accent_source]">
                            <option value=""         <?php selected($accent_source, ''); ?>>
                                Default (stylesheet)
                            </option>
                            <option value="primary"  <?php selected($accent_source, 'primary'); ?>>
                                ACSS &mdash; Primary
                            </option>
                            <option value="secondary" <?php selected($accent_source, 'secondary'); ?>>
                                ACSS &mdash; Secondary
                            </option>
                            <option value="accent"   <?php selected($accent_source, 'accent'); ?>>
                                ACSS &mdash; Accent
                            </option>
                            <option value="custom"   <?php selected($accent_source, 'custom'); ?>>
                                Custom color&hellip;
                            </option>
                        </select>

                        <span id="cfd-accent-picker-wrap" style="margin-left: 8px;<?php echo $accent_source !== 'custom' ? ' display:none;' : ''; ?>">
                            <input
                                type="color"
                                id="cfd_accent_color"
                                value="<?php echo esc_attr($accent_color ?: '#A69279'); ?>"
                            />
                            <input
                                type="hidden"
                                id="cfd_accent_color_value"
                                name="cfd_settings[accent_color]"
                                value="<?php echo esc_attr($accent_color); ?>"
                            />
                        </span>

                        <p class="description" style="margin-top: 8px;">
                            <strong>ACSS palettes</strong> remap the full shade family
                            (<code>--primary</code>, <code>-dark</code>, <code>-light</code>,
                            <code>-trans</code>) so buttons, hovers, and gradients
                            stay consistent. Changes in ACSS automatically update the dashboard.<br>
                            <strong>Custom</strong> overrides <code>--accent</code> and
                            <code>--primary</code> with a single hex value.
                        </p>

                        <script>
                        jQuery(function($) {
                            var $source = $('#cfd_accent_source');
                            var $pickerWrap = $('#cfd-accent-picker-wrap');
                            var $picker = $('#cfd_accent_color');
                            var $hidden = $('#cfd_accent_color_value');

                            // Show/hide the color picker based on dropdown.
                            $source.on('change', function() {
                                if ($(this).val() === 'custom') {
                                    $pickerWrap.show();
                                    // If no custom color was set yet, seed the hidden input.
                                    if (!$hidden.val()) {
                                        $hidden.val($picker.val());
                                    }
                                } else {
                                    $pickerWrap.hide();
                                }
                            });

                            // Sync color picker to hidden input.
                            $picker.on('input', function() {
                                $hidden.val($(this).val());
                            });
                        });
                        </script>
                    </td>
                </tr>
            </table>

            <!-- ── Welcome & Help Hints ────────────────────── -->
            <h2>Welcome &amp; Help Hints</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="cfd_welcome_message">Mensaje de bienvenida</label>
                    </th>
                    <td>
                        <?php
    $welcome_message = isset($db_settings['welcome_message'])
        ? $db_settings['welcome_message']
        : 'Aquí puedes gestionar el contenido de tu sitio web.';
?>
                        <input
                            type="text"
                            id="cfd_welcome_message"
                            name="cfd_settings[welcome_message]"
                            value="<?php echo esc_attr($welcome_message); ?>"
                            class="large-text"
                        />
                        <p class="description">
                            Subtitle shown below the greeting on the dashboard home.
                            Default: <code>Aquí puedes gestionar el contenido de tu sitio web.</code>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Mostrar mensajes de ayuda</th>
                    <td>
                        <?php
    $show_hints = isset($db_settings['show_hints']) ? (bool) $db_settings['show_hints'] : true;
?>
                        <label>
                            <input
                                type="checkbox"
                                name="cfd_settings[show_hints]"
                                value="1"
                                <?php checked($show_hints); ?>
                            />
                            Show contextual help hints on editor and list views
                        </label>
                        <p class="description">
                            Small hint text below view titles that helps clients understand
                            what each screen does. Disable for experienced clients.
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
            <div class="notice notice-info inline" style="margin: 0.5em 0 1em;">
                <p>
                    💡 These are the CPTs enabled <strong>site-wide</strong>. To restrict
                    individual users, go to <strong>Users → [user] → Edit</strong> and
                    configure their access under "Client Dashboard Access".
                </p>
            </div>

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

            <!-- ── Quick Access Links ─────────────────────────── -->
            <h2>Enlaces rápidos (Herramientas)</h2>
            <p>
                Add shortcut cards to the dashboard home that link to external plugin pages
                (e.g., FluentCRM, WooCommerce, Bookly). Each link appears as a card in a
                &ldquo;Herramientas&rdquo; section below the CPT cards.
            </p>

            <?php
                $quick_links = isset($db_settings['quick_links']) && is_array($db_settings['quick_links'])
                    ? $db_settings['quick_links']
                    : array();
            ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Links</th>
                    <td>
                        <div id="cfd-quick-links-repeater">
                            <?php foreach ($quick_links as $i => $link): ?>
                            <fieldset class="cfd-ql-row" style="border: 1px solid #ccd0d4; padding: 12px 14px; margin-bottom: 10px; border-radius: 4px; background: #f9f9f9;">
                                <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end;">
                                    <label style="flex: 1 1 160px;">
                                        <span style="display:block; font-weight:600; margin-bottom:2px;">Label</span>
                                        <input type="text" name="cfd_settings[quick_links][<?php echo $i; ?>][label]" value="<?php echo esc_attr($link['label'] ?? ''); ?>" class="regular-text" placeholder="Emails automáticos" />
                                    </label>
                                    <label style="flex: 1 1 160px;">
                                        <span style="display:block; font-weight:600; margin-bottom:2px;">Icon <small style="font-weight:normal; color:#888;">(dashicons class)</small></span>
                                        <input type="text" name="cfd_settings[quick_links][<?php echo $i; ?>][icon]" value="<?php echo esc_attr($link['icon'] ?? 'dashicons-admin-generic'); ?>" class="regular-text" placeholder="dashicons-email-alt" />
                                    </label>
                                    <label style="flex: 2 1 240px;">
                                        <span style="display:block; font-weight:600; margin-bottom:2px;">URL</span>
                                        <input type="url" name="cfd_settings[quick_links][<?php echo $i; ?>][url]" value="<?php echo esc_attr($link['url'] ?? ''); ?>" class="regular-text" style="width:100%;" placeholder="/wp-admin/admin.php?page=fluent-crm" />
                                    </label>
                                    <label style="flex: 1 1 160px;">
                                        <span style="display:block; font-weight:600; margin-bottom:2px;">Hint <small style="font-weight:normal; color:#888;">(optional)</small></span>
                                        <input type="text" name="cfd_settings[quick_links][<?php echo $i; ?>][hint]" value="<?php echo esc_attr($link['hint'] ?? ''); ?>" class="regular-text" placeholder="Editar emails de bienvenida" />
                                    </label>
                                    <label style="flex: 0 0 120px;">
                                        <span style="display:block; font-weight:600; margin-bottom:2px;">Target</span>
                                        <select name="cfd_settings[quick_links][<?php echo $i; ?>][target]">
                                            <option value="_blank" <?php selected(($link['target'] ?? '_blank'), '_blank'); ?>>New tab</option>
                                            <option value="_self" <?php selected(($link['target'] ?? '_blank'), '_self'); ?>>Same tab</option>
                                        </select>
                                    </label>
                                    <button type="button" class="button cfd-ql-remove" style="color: #a00; flex: 0 0 auto;">✕ Remove</button>
                                </div>
                            </fieldset>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button" id="cfd-ql-add">+ Add Link</button>
                        <p class="description" style="margin-top: 8px;">
                            URLs can be absolute (<code>https://…</code>) or relative wp-admin paths
                            (<code>/wp-admin/admin.php?page=…</code>). Leave Hint empty to show &ldquo;Abrir &rarr;&rdquo; by default.
                            <br><a href="https://developer.wordpress.org/resource/dashicons/" target="_blank" rel="noopener">Browse Dashicons &rarr;</a>
                        </p>

                        <script>
                        jQuery(function($) {
                            var $repeater = $('#cfd-quick-links-repeater');
                            var rowIndex = $repeater.find('.cfd-ql-row').length;

                            // Add a new empty row.
                            $('#cfd-ql-add').on('click', function() {
                                var i = rowIndex++;
                                var html = '<fieldset class="cfd-ql-row" style="border: 1px solid #ccd0d4; padding: 12px 14px; margin-bottom: 10px; border-radius: 4px; background: #f9f9f9;">'
                                    + '<div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end;">'
                                    + '<label style="flex: 1 1 160px;"><span style="display:block; font-weight:600; margin-bottom:2px;">Label</span>'
                                    + '<input type="text" name="cfd_settings[quick_links][' + i + '][label]" value="" class="regular-text" placeholder="Emails automáticos" /></label>'
                                    + '<label style="flex: 1 1 160px;"><span style="display:block; font-weight:600; margin-bottom:2px;">Icon <small style="font-weight:normal; color:#888;">(dashicons class)</small></span>'
                                    + '<input type="text" name="cfd_settings[quick_links][' + i + '][icon]" value="dashicons-admin-generic" class="regular-text" placeholder="dashicons-email-alt" /></label>'
                                    + '<label style="flex: 2 1 240px;"><span style="display:block; font-weight:600; margin-bottom:2px;">URL</span>'
                                    + '<input type="url" name="cfd_settings[quick_links][' + i + '][url]" value="" class="regular-text" style="width:100%;" placeholder="/wp-admin/admin.php?page=fluent-crm" /></label>'
                                    + '<label style="flex: 1 1 160px;"><span style="display:block; font-weight:600; margin-bottom:2px;">Hint <small style="font-weight:normal; color:#888;">(optional)</small></span>'
                                    + '<input type="text" name="cfd_settings[quick_links][' + i + '][hint]" value="" class="regular-text" placeholder="Editar emails de bienvenida" /></label>'
                                    + '<label style="flex: 0 0 120px;"><span style="display:block; font-weight:600; margin-bottom:2px;">Target</span>'
                                    + '<select name="cfd_settings[quick_links][' + i + '][target]"><option value="_blank">New tab</option><option value="_self">Same tab</option></select></label>'
                                    + '<button type="button" class="button cfd-ql-remove" style="color: #a00; flex: 0 0 auto;">✕ Remove</button>'
                                    + '</div></fieldset>';
                                $repeater.append(html);
                            });

                            // Remove a row.
                            $repeater.on('click', '.cfd-ql-remove', function() {
                                $(this).closest('.cfd-ql-row').remove();
                            });
                        });
                        </script>
                    </td>
                </tr>
            </table>

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
                    <td><code>[cfd_quick_links]</code></td>
                    <td>Renders quick access link cards (from Settings &rarr; Enlaces r&aacute;pidos)</td>
                </tr>
                <tr>
                    <td><code>[cfd_sidebar_nav]</code></td>
                    <td>Dynamic sidebar nav with Dashicons (auto from config)</td>
                </tr>
                <tr>
                    <td><code>[cfd_client_logo]</code></td>
                    <td>Muestra el logo del cliente subido arriba. Ideal como reemplazo si las etiquetas dinámicas de Bricks fallan en el elemento Imagen. Acepta los parámetros <code>class</code> y <code>max_width</code>.</td>
                </tr>
                <tr>
                    <td><code>[cfd_logout_url]</code></td>
                    <td>Devuelve la URL en texto plano para el enlace de cierre de sesión. Útil para menús o elementos personalizados donde no se cuenta con etiquetas dinámicas.</td>
                </tr>
                <tr>
                    <td colspan="2" style="background: #f0f0f1; font-weight: 600; padding: 8px 10px;">
                        Bricks Dynamic Tags (use via ⚡ icon)
                    </td>
                </tr>
                <tr>
                    <td><code>{cfd_client_logo}</code></td>
                    <td>Client logo URL (uploaded in settings above)</td>
                </tr>
                <tr>
                    <td><code>{cfd_logout_url}</code></td>
                    <td>Logout URL for links/buttons</td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}