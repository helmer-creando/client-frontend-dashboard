<?php
/**
 * ============================================================
 * CFD CPT Chips — Registry & Engine
 * ============================================================
 *
 * Provides a registration API for context chips, status chips,
 * quick-toggle actions, and filter facets on CPT listing cards.
 *
 * USAGE (per-site, in functions.php or a code snippet):
 *
 *   add_action( 'cfd_register_chip_configs', function() {
 *       cfd_register_cpt_chips( 'testimonials', array(
 *           'context_chips' => array(
 *               array(
 *                   'type'   => 'taxonomy',
 *                   'source' => 'service_categories',  // taxonomy slug
 *                   'empty'  => 'Sin categoría',       // shown when no terms
 *               ),
 *           ),
 *           'status_chips' => array(
 *               array(
 *                   'type'   => 'acf_boolean',
 *                   'source' => 'homepage_featured',   // ACF field name
 *                   'label'  => 'Destacado',
 *                   'icon'   => 'star',
 *               ),
 *           ),
 *           'quick_toggles' => array(
 *               array(
 *                   'source'   => 'homepage_featured',
 *                   'label'    => 'Destacado en homepage',
 *                   'icon_on'  => 'star',
 *                   'icon_off' => 'star',
 *               ),
 *           ),
 *           'filter_facets' => array(
 *               array(
 *                   'type'   => 'taxonomy',
 *                   'source' => 'service_categories',
 *               ),
 *           ),
 *       ) );
 *   } );
 *
 * Supported chip types:
 *   'taxonomy'    — Terms from a registered WP taxonomy
 *   'acf_boolean' — ACF true/false field (shows chip only when true)
 *   'post_status' — Matches a specific post_status string
 *
 * ============================================================
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ═══════════════════════════════════════════════════════════
// 1. REGISTRY
// ═══════════════════════════════════════════════════════════

/**
 * Internal registry: cpt_slug → config array.
 * Populated via cfd_register_cpt_chips().
 */
$cfd_chip_registry = array();

/**
 * Register a chip config for a CPT.
 *
 * Call inside a 'cfd_register_chip_configs' action callback so
 * the engine is ready before configs are read.
 *
 * @param string $cpt_slug  Post type slug (e.g. 'testimonials').
 * @param array  $config    Config array — see file header for shape.
 */
function cfd_register_cpt_chips( string $cpt_slug, array $config ): void {
    global $cfd_chip_registry;
    $cfd_chip_registry[ $cpt_slug ] = $config;
}

/**
 * Retrieve the chip config for a CPT, or null if none registered.
 *
 * @param string $cpt_slug  Post type slug.
 * @return array|null
 */
function cfd_get_cpt_chip_config( string $cpt_slug ): ?array {
    global $cfd_chip_registry;

    // Ensure configs have been registered (fires once on first call).
    static $fired = false;
    if ( ! $fired ) {
        $fired = true;
        do_action( 'cfd_register_chip_configs' );
    }

    return $cfd_chip_registry[ $cpt_slug ] ?? null;
}

// ═══════════════════════════════════════════════════════════
// 2. CHIP DATA RESOLVERS
// ═══════════════════════════════════════════════════════════

/**
 * Resolve an array of chip source definitions into chip data arrays.
 *
 * Each returned chip has:
 *   'label'    string  Display text
 *   'type'     string  'context' | 'context-empty' | 'status'
 *   'icon'     string  (optional) Material Symbol ligature
 *   'taxonomy' string  (optional) taxonomy slug — for context chips
 *   'slug'     string  (optional) term slug — for context chips
 *
 * @param array   $chip_sources  Array of source definitions from config.
 * @param WP_Post $post          The current post.
 * @return array[]
 */
function cfd_resolve_chips( array $chip_sources, WP_Post $post ): array {
    $chips = array();

    foreach ( $chip_sources as $source ) {
        $type = $source['type'] ?? '';

        switch ( $type ) {

            // ── Taxonomy ────────────────────────────────────────
            case 'taxonomy':
                $taxonomy = $source['source'] ?? '';
                if ( ! $taxonomy ) {
                    break;
                }

                $terms = get_the_terms( $post->ID, $taxonomy );

                if ( $terms && ! is_wp_error( $terms ) ) {
                    foreach ( $terms as $term ) {
                        $chips[] = array(
                            'label'    => $term->name,
                            'slug'     => $term->slug,
                            'taxonomy' => $taxonomy,
                            'type'     => 'context',
                        );
                    }
                } else {
                    $chips[] = array(
                        'label'    => $source['empty'] ?? 'Sin categoría',
                        'slug'     => 'sin-categoria',
                        'taxonomy' => $taxonomy,
                        'type'     => 'context-empty',
                    );
                }
                break;

            // ── ACF boolean field ────────────────────────────────
            case 'acf_boolean':
                if ( ! function_exists( 'get_field' ) ) {
                    break;
                }
                $value = get_field( $source['source'] ?? '', $post->ID );
                if ( $value ) {
                    $chips[] = array(
                        'label' => $source['label'] ?? '',
                        'icon'  => $source['icon'] ?? '',
                        'type'  => 'status',
                    );
                }
                break;

            // ── Post status match ────────────────────────────────
            case 'post_status':
                if ( $post->post_status === ( $source['status'] ?? '' ) ) {
                    $chips[] = array(
                        'label' => $source['label'] ?? $post->post_status,
                        'type'  => 'status',
                    );
                }
                break;
        }
    }

    return $chips;
}

// ═══════════════════════════════════════════════════════════
// 3. CHIP HTML RENDERER
// ═══════════════════════════════════════════════════════════

/**
 * Render a single chip as an HTML string.
 *
 * Colors:
 *   context       → --primary-ultra-light bg / --primary-ultra-dark text
 *   status        → --accent-ultra-light bg  / --accent-ultra-dark text
 *   context-empty → muted / neutral (sin categoría)
 *
 * @param array $chip  Chip data array from cfd_resolve_chips().
 * @return string
 */
function cfd_render_chip_html( array $chip ): string {
    $chip_type = $chip['type'] ?? 'context';
    $label     = esc_html( $chip['label'] ?? '' );

    if ( $label === '' ) {
        return '';
    }

    $class = 'cfd-chip';
    if ( $chip_type === 'status' ) {
        $class .= ' cfd-chip--status';
    } elseif ( $chip_type === 'context-empty' ) {
        $class .= ' cfd-chip--empty';
    }

    $icon_html = '';
    if ( ! empty( $chip['icon'] ) ) {
        $icon_html = '<span class="material-symbols-outlined cfd-chip__icon kh-icon--filled" aria-hidden="true">'
            . esc_html( $chip['icon'] )
            . '</span>';
    }

    return '<span class="' . $class . '">' . $icon_html . $label . '</span>';
}

// ═══════════════════════════════════════════════════════════
// 4. QUICK TOGGLE RENDERER
// ═══════════════════════════════════════════════════════════

/**
 * Render quick-toggle button(s) for a card's action zone.
 *
 * Quick toggles are above the stretched card link (z-index 3)
 * and fire a lightweight AJAX call to flip an ACF boolean field
 * without opening the editor.
 *
 * @param array   $toggles  Array of toggle definitions from config.
 * @param WP_Post $post     The current post.
 * @return string           HTML string (empty when ACF unavailable).
 */
function cfd_render_quick_toggles( array $toggles, WP_Post $post ): string {
    if ( ! function_exists( 'get_field' ) ) {
        return '';
    }

    $html = '';

    foreach ( $toggles as $toggle ) {
        $field  = $toggle['source'] ?? '';
        $label  = $toggle['label'] ?? 'Destacado';
        $active = (bool) get_field( $field, $post->ID );
        $icon   = $active ? ( $toggle['icon_on'] ?? 'star' ) : ( $toggle['icon_off'] ?? 'star' );
        $nonce  = wp_create_nonce( 'cfd_toggle_' . $post->ID );

        $class     = 'cfd-quick-toggle' . ( $active ? ' cfd-quick-toggle--active' : '' );
        $aria_lbl  = esc_attr( $label . ': ' . ( $active ? 'Activado' : 'Desactivado' ) );
        $icon_fill = $active ? ' kh-icon--filled' : '';

        $html .= '<button type="button"'
            . ' class="' . $class . '"'
            . ' data-post-id="' . esc_attr( $post->ID ) . '"'
            . ' data-field="' . esc_attr( $field ) . '"'
            . ' data-active="' . ( $active ? '1' : '0' ) . '"'
            . ' data-nonce="' . esc_attr( $nonce ) . '"'
            . ' aria-label="' . $aria_lbl . '"'
            . ' aria-pressed="' . ( $active ? 'true' : 'false' ) . '"'
            . ' title="' . $aria_lbl . '"'
            . '>';
        $html .= '<span class="material-symbols-outlined' . $icon_fill . '" aria-hidden="true">'
            . esc_html( $icon )
            . '</span>';
        $html .= '</button>';
    }

    return $html;
}

// ═══════════════════════════════════════════════════════════
// 5. FILTER FACET TOOLBAR RENDERER
// ═══════════════════════════════════════════════════════════

/**
 * Render taxonomy filter dropdowns inside the CPT toolbar form.
 *
 * Outputs one <select> group per facet, matching the existing
 * .cd-cpt-toolbar__group style. Uses GET params so the form
 * submission naturally includes the selected values.
 *
 * @param string $cpt_slug  Post type slug (used to scope hidden input).
 * @param array  $facets    Array of facet definitions from config.
 */
function cfd_render_filter_facets( string $cpt_slug, array $facets ): void {
    foreach ( $facets as $facet ) {
        if ( ( $facet['type'] ?? '' ) !== 'taxonomy' ) {
            continue;
        }

        $taxonomy = $facet['source'] ?? '';
        $tax_obj  = get_taxonomy( $taxonomy );
        if ( ! $tax_obj ) {
            continue;
        }

        $label    = $facet['label'] ?? $tax_obj->labels->singular_name;
        $param    = 'cfd_tax_' . $taxonomy;
        $selected = isset( $_GET[ $param ] ) ? sanitize_key( $_GET[ $param ] ) : '';

        $terms = get_terms( array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            continue;
        }

        $field_id = 'cfd-facet-' . esc_attr( $taxonomy );

        echo '<div class="cd-cpt-toolbar__group">';
        echo '  <label for="' . $field_id . '" class="cd-cpt-toolbar__label">' . esc_html( $label ) . '</label>';
        echo '  <select name="' . esc_attr( $param ) . '" id="' . $field_id . '" class="cd-cpt-toolbar__select" onchange="this.form.submit()">';
        echo '    <option value="">Todas</option>';
        foreach ( $terms as $term ) {
            $sel = ( $selected === $term->slug ) ? ' selected' : '';
            echo '    <option value="' . esc_attr( $term->slug ) . '"' . $sel . '>' . esc_html( $term->name ) . '</option>';
        }
        echo '  </select>';
        echo '</div>';
    }
}

// ═══════════════════════════════════════════════════════════
// 6. TAX QUERY BUILDER
// ═══════════════════════════════════════════════════════════

/**
 * Build a WP_Query tax_query array from active facet GET params.
 *
 * Multiple active facets combine with AND (narrow across dimensions).
 * Multiple terms within a single facet use a single select so only
 * one term is active at a time.
 *
 * @param array $facets  Facet definitions from chip config.
 * @return array         tax_query array, empty if no filters active.
 */
function cfd_build_tax_query( array $facets ): array {
    $tax_query = array();

    foreach ( $facets as $facet ) {
        if ( ( $facet['type'] ?? '' ) !== 'taxonomy' ) {
            continue;
        }

        $taxonomy = $facet['source'] ?? '';
        $param    = 'cfd_tax_' . $taxonomy;
        $selected = isset( $_GET[ $param ] ) ? sanitize_key( $_GET[ $param ] ) : '';

        if ( $selected === '' ) {
            continue;
        }

        $tax_query[] = array(
            'taxonomy' => $taxonomy,
            'field'    => 'slug',
            'terms'    => $selected,
        );
    }

    return $tax_query;
}

// ═══════════════════════════════════════════════════════════
// 7. ACTIVE FILTER SUMMARY
// ═══════════════════════════════════════════════════════════

/**
 * Render the "Filtrando: X · ✕ Limpiar" banner when facets are active.
 *
 * Echoes nothing when no facets are selected.
 *
 * @param string $cpt_slug     Post type slug.
 * @param array  $facets       Facet definitions from chip config.
 * @param string $dashboard_url Base dashboard URL.
 */
function cfd_render_active_filter_summary(
    string $cpt_slug,
    array $facets,
    string $dashboard_url
): void {
    $active_labels = array();

    foreach ( $facets as $facet ) {
        if ( ( $facet['type'] ?? '' ) !== 'taxonomy' ) {
            continue;
        }

        $taxonomy = $facet['source'] ?? '';
        $param    = 'cfd_tax_' . $taxonomy;
        $slug     = isset( $_GET[ $param ] ) ? sanitize_key( $_GET[ $param ] ) : '';

        if ( $slug === '' ) {
            continue;
        }

        $term = get_term_by( 'slug', $slug, $taxonomy );
        if ( $term ) {
            $active_labels[] = $term->name;
        }
    }

    if ( empty( $active_labels ) ) {
        return;
    }

    // Build clear URL: preserve manage + sort, drop all facet params.
    $clear_args = array( 'manage' => $cpt_slug );
    if ( ! empty( $_GET['orderby'] ) ) {
        $clear_args['orderby'] = sanitize_key( $_GET['orderby'] );
    }
    if ( ! empty( $_GET['buscar'] ) ) {
        $clear_args['buscar'] = sanitize_text_field( $_GET['buscar'] );
    }
    $clear_url = add_query_arg( $clear_args, $dashboard_url );

    echo '<p class="cd-cpt-search-status">';
    echo '  <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">filter_alt</span> ';
    echo '  Filtrando: <strong>' . esc_html( implode( ', ', $active_labels ) ) . '</strong>';
    echo '  &nbsp;<a href="' . esc_url( $clear_url ) . '" class="cd-cpt-search-clear">✕ Limpiar filtro</a>';
    echo '</p>';
}

// ═══════════════════════════════════════════════════════════
// 8. FACET PARAMS COLLECTOR (for pagination/URL building)
// ═══════════════════════════════════════════════════════════

/**
 * Return an array of active facet GET params (param => value).
 *
 * Used by the listing to preserve facet state across pagination links,
 * search-clear links, and other URL constructions.
 *
 * @param array $facets  Facet definitions from chip config.
 * @return array
 */
function cfd_get_active_facet_params( array $facets ): array {
    $params = array();

    foreach ( $facets as $facet ) {
        if ( ( $facet['type'] ?? '' ) !== 'taxonomy' ) {
            continue;
        }
        $param = 'cfd_tax_' . ( $facet['source'] ?? '' );
        if ( ! empty( $_GET[ $param ] ) ) {
            $params[ $param ] = sanitize_key( $_GET[ $param ] );
        }
    }

    return $params;
}

// ═══════════════════════════════════════════════════════════
// 9. AJAX — QUICK TOGGLE ACF BOOLEAN FIELD
// ═══════════════════════════════════════════════════════════

add_action( 'wp_ajax_cfd_toggle_field', 'cfd_ajax_toggle_field' );

/**
 * AJAX handler: flip an ACF boolean field on a post.
 *
 * Expects POST params: post_id, field (ACF field name/key), nonce.
 * Returns JSON: { success: true, data: { value: bool } }
 */
function cfd_ajax_toggle_field(): void {
    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    $field   = isset( $_POST['field'] )   ? sanitize_key( $_POST['field'] ) : '';
    $nonce   = isset( $_POST['nonce'] )   ? sanitize_text_field( $_POST['nonce'] ) : '';

    if ( ! $post_id || ! $field ) {
        wp_send_json_error( 'Parámetros inválidos.', 400 );
    }

    if ( ! wp_verify_nonce( $nonce, 'cfd_toggle_' . $post_id ) ) {
        wp_send_json_error( 'Nonce inválido.', 403 );
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        wp_send_json_error( 'Sin permiso.', 403 );
    }

    if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
        wp_send_json_error( 'ACF no disponible.', 500 );
    }

    $current   = (bool) get_field( $field, $post_id );
    $new_value = ! $current;

    update_field( $field, $new_value, $post_id );

    wp_send_json_success( array( 'value' => $new_value ) );
}
