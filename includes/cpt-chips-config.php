<?php
/**
 * ============================================================
 * CFD CPT Chips — Per-Site Configuration
 * ============================================================
 *
 * This file registers chip configs for each managed CPT on
 * THIS site. Edit the field names and taxonomy slugs below
 * to match your actual ACF fields and WP taxonomies.
 *
 * HOW TO FIND THE RIGHT VALUES:
 *
 *   Taxonomy slug:
 *     WP Admin → Posts → [CPT] → [Taxonomy column header]
 *     or: WP Admin → Custom Fields → (ACF taxonomy field)
 *     or: check the register_taxonomy() call / ACF UI slug.
 *
 *   ACF field name:
 *     WP Admin → Custom Fields → [Field Group] → [Field] → Field Name
 *     (NOT the label — the "Field Name" row, e.g. "homepage_featured")
 *
 * ─────────────────────────────────────────────────────────
 * MULTI-SITE NOTE:
 *   In production this file ships with the plugin but acts as
 *   a stub — the real configs live in each site's theme
 *   functions.php (or a snippet) hooked to 'cfd_register_chip_configs'.
 *   Override this file's callback by registering your own hook
 *   with a higher priority (e.g. priority 20).
 * ============================================================
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'cfd_register_chip_configs', 'cfd_default_chip_configs', 10 );

function cfd_default_chip_configs(): void {

    // ── Testimonials ──────────────────────────────────────────
    // Context chip: taxonomy linking testimonials to service types.
    // Status chip + quick-toggle: homepage featured flag.
    //
    // ↓ UPDATE THESE VALUES to match this site's ACF field names
    //   and registered taxonomy slug.
    cfd_register_cpt_chips( 'testimonials', array(

        'context_chips' => array(
            array(
                'type'   => 'taxonomy',
                // Slug of the taxonomy registered for testimonials.
                // WP Admin → Custom Fields → Taxonomies → Field Name
                'source' => 'service_categories',
                'empty'  => 'Sin categoría',
            ),
        ),

        'status_chips' => array(
            array(
                'type'   => 'acf_boolean',
                // ACF field name (not label) for the "featured on homepage" toggle.
                'source' => 'homepage_featured',
                'label'  => 'Destacado',
                'icon'   => 'star',
            ),
        ),

        'quick_toggles' => array(
            array(
                // Must match the ACF field name above.
                'source'   => 'homepage_featured',
                'label'    => 'Destacar en homepage',
                'icon_on'  => 'star',
                'icon_off' => 'star',
            ),
        ),

        'filter_facets' => array(
            array(
                'type'   => 'taxonomy',
                'source' => 'service_categories',
                // Optional: override the label shown in the toolbar dropdown.
                // Leave unset to use the taxonomy's own singular_name from WP.
                // 'label' => 'Servicio',
            ),
        ),

    ) );

    // ── Add more CPTs here ────────────────────────────────────
    //
    // cfd_register_cpt_chips( 'retreats', array(
    //     'context_chips' => array(
    //         array( 'type' => 'taxonomy', 'source' => 'retreat_type', 'empty' => 'Sin tipo' ),
    //     ),
    //     'filter_facets' => array(
    //         array( 'type' => 'taxonomy', 'source' => 'retreat_type' ),
    //     ),
    // ) );
}
