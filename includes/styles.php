<?php
/**
 * ============================================================
 * Module: Styles
 * ============================================================
 *
 * Migrated from: Snippet 3 — "Client Dashboard — Styles"
 *
 * Changes from the original:
 * • CSS extracted to assets/css/dashboard.css (browser-cacheable)
 * • Uses wp_enqueue_style() instead of echoing inline <style>
 * • cd_get_config() → cfd_get_config()
 * ============================================================
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'cfd_enqueue_dashboard_styles');

function cfd_enqueue_dashboard_styles(): void
{
    $config = cfd_get_config();

    if (!is_page($config['dashboard_slug']) && !cfd_is_bricks_builder()) {
        return;
    }
    if (!is_user_logged_in()) {
        return;
    }

    wp_enqueue_style(
        'cfd-dashboard',
        CFD_URL . 'assets/css/dashboard.css',
        array(), // No dependencies.
        CFD_VERSION
    );

    // Inject custom accent color as inline CSS if configured.
    cfd_inline_accent_color();
}

/**
 * Injects accent color CSS variables via wp_add_inline_style.
 *
 * Supports two modes:
 *
 * 1. ACSS palette mapping (primary / secondary / accent)
 *    Remaps the full shade family (--primary, --primary-dark, -light,
 *    -ultra-dark, -ultra-light, -hover, -trans-*) to the chosen ACSS
 *    palette. Scoped to .cd-dashboard so it doesn't leak into the
 *    surrounding Bricks template or global ACSS variables.
 *
 * 2. Custom hex color
 *    Overrides --accent and --primary with a single hex value.
 *    Scoped to .cd-dashboard for the same reason.
 *
 * When accent_source is empty (default), no CSS is injected and the
 * stylesheet fallbacks are used as-is.
 */
function cfd_inline_accent_color(): void
{
    $settings = get_option( 'cfd_settings', [] );
    $source   = $settings['accent_source'] ?? '';

    if ( empty( $source ) ) {
        return;
    }

    // ── ACSS palette mapping ────────────────────────────────
    $acss_palettes = array( 'primary', 'secondary', 'accent' );

    if ( in_array( $source, $acss_palettes, true ) ) {
        $s = $source; // shorthand for the template below.

        $css = ".cd-dashboard {
  --primary: var(--{$s});
  --primary-dark: var(--{$s}-dark);
  --primary-light: var(--{$s}-light);
  --primary-ultra-dark: var(--{$s}-ultra-dark);
  --primary-ultra-light: var(--{$s}-ultra-light);
  --primary-hover: var(--{$s}-hover);
  --primary-trans: var(--{$s}-trans);
  --primary-trans-10: var(--{$s}-trans-10);
  --primary-trans-20: var(--{$s}-trans-20);
  --primary-trans-30: var(--{$s}-trans-30);
  --accent: var(--{$s});
}";
        wp_add_inline_style( 'cfd-dashboard', $css );
        return;
    }

    // ── Custom hex color ────────────────────────────────────
    if ( $source === 'custom' ) {
        $color = $settings['accent_color'] ?? '';
        if ( empty( $color ) ) {
            return;
        }

        $color = sanitize_hex_color( $color );
        if ( ! $color ) {
            return;
        }

        $css = ".cd-dashboard { --accent: {$color}; --primary: {$color}; }";
        wp_add_inline_style( 'cfd-dashboard', $css );
    }
}