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

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─── HELPER: Get dashboard page URL (cached) ────────────────
// The original snippet called get_page_by_path() + get_permalink()
// in every render function. Each is a DB query. Now we compute
// it once and cache it in a static variable.

function cfd_get_dashboard_url(): string {
    static $url = null;
    if ( $url === null ) {
        $config = cfd_get_config();
        $page   = get_page_by_path( $config['dashboard_slug'] );
        $url    = $page ? get_permalink( $page ) : home_url( '/' . $config['dashboard_slug'] . '/' );
    }
    return $url;
}

// ═══════════════════════════════════════════════════════════
// 1. CALL acf_form_head() BEFORE ANY OUTPUT
// ═══════════════════════════════════════════════════════════

add_action( 'wp', 'cfd_maybe_load_acf_form_head' );

function cfd_maybe_load_acf_form_head(): void {
    $config = cfd_get_config();
    if ( ! is_page( $config['dashboard_slug'] ) ) {
        return;
    }
    if ( ! is_user_logged_in() ) {
        return;
    }
    if ( ! function_exists( 'acf_form_head' ) ) {
        return;
    }
    acf_form_head();
}

// ═══════════════════════════════════════════════════════════
// 2. HANDLE CPT DELETION
// ═══════════════════════════════════════════════════════════

add_action( 'template_redirect', 'cfd_handle_cpt_delete' );

function cfd_handle_cpt_delete(): void {
    $config = cfd_get_config();

    if ( ! is_page( $config['dashboard_slug'] ) ) {
        return;
    }
    if ( ! is_user_logged_in() ) {
        return;
    }

    $action  = isset( $_GET['action'] )   ? sanitize_key( $_GET['action'] )           : '';
    $post_id = isset( $_GET['id'] )       ? absint( $_GET['id'] )                     : 0;
    // FIX: Sanitize the nonce value (was raw in original).
    $nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( $_GET['_wpnonce'] )  : '';

    if ( $action !== 'trash' || $post_id < 1 ) {
        return;
    }

    if ( ! wp_verify_nonce( $nonce, 'cfd_trash_' . $post_id ) ) {
        return;
    }

    if ( ! current_user_can( 'delete_post', $post_id ) ) {
        return;
    }

    $post     = get_post( $post_id );
    $cpt_slug = $post ? $post->post_type : '';

    if ( ! in_array( $cpt_slug, $config['manageable_cpts'], true ) ) {
        return;
    }

    wp_trash_post( $post_id );

    $redirect_url = add_query_arg(
        array( 'manage' => $cpt_slug, 'trashed' => 'true' ),
        cfd_get_dashboard_url()
    );

    wp_safe_redirect( $redirect_url );
    exit;
}

// ═══════════════════════════════════════════════════════════
// 3. ENQUEUE ASSETS ON DASHBOARD
// ═══════════════════════════════════════════════════════════

add_action( 'wp_enqueue_scripts', 'cfd_enqueue_dashboard_assets' );

function cfd_enqueue_dashboard_assets(): void {
    $config = cfd_get_config();
    if ( ! is_page( $config['dashboard_slug'] ) ) {
        return;
    }
    if ( ! is_user_logged_in() ) {
        return;
    }

    // WordPress media uploader (needed for ACF image fields).
    wp_enqueue_media();

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

add_shortcode( 'client_dashboard', 'cfd_render_dashboard' );

function cfd_render_dashboard(): string {
    if ( ! is_user_logged_in() ) {
        return '<p class="cd-error">Por favor, inicia sesión para acceder al panel.</p>';
    }

    $user   = wp_get_current_user();
    $config = cfd_get_config();

    $action  = isset( $_GET['edit'] )    ? sanitize_key( $_GET['edit'] )    : '';
    $manage  = isset( $_GET['manage'] )  ? sanitize_key( $_GET['manage'] )  : '';
    $create  = isset( $_GET['create'] )  ? sanitize_key( $_GET['create'] )  : '';
    $post_id = isset( $_GET['id'] )      ? absint( $_GET['id'] )            : 0;

    ob_start();

    echo '<div class="cd-dashboard">';

    if ( $action === 'page' && $post_id > 0 ) {
        cfd_render_page_editor( $post_id, $user );
    } elseif ( $action && $post_id > 0 && in_array( $action, $config['manageable_cpts'], true ) ) {
        cfd_render_cpt_editor( $action, $post_id, $user );
    } elseif ( $manage && in_array( $manage, $config['manageable_cpts'], true ) ) {
        cfd_render_cpt_list( $manage, $user );
    } elseif ( $create && in_array( $create, $config['manageable_cpts'], true ) ) {
        cfd_render_cpt_creator( $create, $user );
    } else {
        cfd_render_dashboard_home( $user, $config );
    }

    echo '</div><!-- .cd-dashboard -->';

    return ob_get_clean();
}

// ═══════════════════════════════════════════════════════════
// 5. DASHBOARD HOME — List of editable pages + CPTs
// ═══════════════════════════════════════════════════════════

function cfd_render_dashboard_home( WP_User $user, array $config ): void {
    $pages        = cfd_get_editable_pages( $config );
    $dashboard_url = cfd_get_dashboard_url();

    $name = $user->first_name ?: $user->display_name;
    echo '<div class="cd-hero">';
    echo '  <h1 class="cd-hero__greeting">Hola, ' . esc_html( $name ) . ' ☀️</h1>';
    echo '  <p class="cd-hero__sub">¿Qué te gustaría actualizar hoy?</p>';
    echo '</div>';

    echo '<div class="cd-home">';

    echo '<h2 class="cd-home__title">Tus Páginas</h2>';
    echo '<p class="cd-home__subtitle">Haz clic en cualquier página para editar su contenido.</p>';

    // Debug mode — only visible to admins when WP_DEBUG is on.
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && current_user_can( 'manage_options' ) ) {
        echo '<div class="cd-debug-info">';
        echo '<strong>🔧 Debug info (admin only, WP_DEBUG is on):</strong><br>';
        echo 'Pages found: ' . count( $pages ) . '<br>';
        echo 'manageable_cpts: [' . implode( ', ', $config['manageable_cpts'] ) . ']';
        echo '</div>';
    }

    if ( empty( $pages ) ) {
        echo '<p style="color: var(--text-dark-muted, #8A817A); font-style: italic;">Aún no hay páginas editables.</p>';
    }

    echo '<div class="cd-page-grid">';

    foreach ( $pages as $page ) {
        $edit_url = add_query_arg( array( 'edit' => 'page', 'id' => $page->ID ), $dashboard_url );

        echo '<a href="' . esc_url( $edit_url ) . '" class="cd-page-card">';
        echo '  <span class="cd-page-card__icon">📄</span>';
        echo '  <span class="cd-page-card__title">' . esc_html( $page->post_title ) . '</span>';
        echo '  <span class="cd-page-card__hint">Editar →</span>';
        echo '</a>';
    }

    echo '</div>';

    // CPT sections
    if ( ! empty( $config['manageable_cpts'] ) ) {
        echo '<h2 class="cd-home__title" style="margin-top: var(--space-l, 2rem);">Tu Contenido</h2>';
        echo '<div class="cd-page-grid">';

        foreach ( $config['manageable_cpts'] as $cpt_slug ) {
            $cpt_obj = get_post_type_object( $cpt_slug );
            if ( ! $cpt_obj ) {
                continue;
            }
            $manage_url = add_query_arg( array( 'manage' => $cpt_slug ), $dashboard_url );

            echo '<a href="' . esc_url( $manage_url ) . '" class="cd-page-card">';
            echo '  <span class="cd-page-card__icon">📦</span>';
            echo '  <span class="cd-page-card__title">' . esc_html( $cpt_obj->labels->name ) . '</span>';
            echo '  <span class="cd-page-card__hint">Administrar →</span>';
            echo '</a>';
        }

        echo '</div>';
    }

    echo '</div>';
}

// ═══════════════════════════════════════════════════════════
// 6. PAGE EDITOR
// ═══════════════════════════════════════════════════════════

function cfd_render_page_editor( int $post_id, WP_User $user ): void {
    $post = get_post( $post_id );

    if ( ! $post || $post->post_type !== 'page' ) {
        echo '<div class="cd-error">Esta página no existe.</div>';
        return;
    }

    if ( ! current_user_can( 'edit_page', $post_id ) ) {
        echo '<div class="cd-error">No tienes permiso para editar esta página.</div>';
        return;
    }

    $field_groups = cfd_get_field_groups_for_post( $post_id );

    if ( empty( $field_groups ) ) {
        echo '<div class="cd-error">No se encontraron campos editables para esta página.</div>';
        return;
    }

    $dashboard_url = cfd_get_dashboard_url();
    $return_url    = add_query_arg(
        array( 'edit' => 'page', 'id' => $post_id, 'updated' => 'true' ),
        $dashboard_url
    );

    echo '<a href="' . esc_url( $dashboard_url ) . '" class="cd-back-link">← Volver al inicio</a>';

    echo '<div class="cd-editor">';
    echo '<div class="cd-editor__header">';
    echo '  <h2 class="cd-editor__title">' . esc_html( $post->post_title ) . '</h2>';
    echo '  <a href="' . esc_url( get_permalink( $post_id ) ) . '" target="_blank" class="cd-preview-link">Ver página ↗</a>';
    echo '</div>';

    if ( isset( $_GET['updated'] ) && $_GET['updated'] === 'true' ) {
        echo '<div class="cd-success">';
        echo '  <span>✅ ¡Cambios guardados!</span>';
        echo '  <a href="' . esc_url( get_permalink( $post_id ) ) . '" target="_blank" class="cd-preview-link" style="margin-left: 0.5rem;">Ver página ↗</a>';
        echo '</div>';
    }

    echo '<p class="cd-editor__sub">Edita el contenido. Los cambios se verán reflejados inmediatamente después de guardar.</p>';

    acf_form( array(
        'post_id'            => $post_id,
        'post_title'         => false,
        'post_content'       => false,
        'field_groups'       => $field_groups,
        'submit_value'       => 'Guardar cambios',
        'updated_message'    => false,
        'return'             => $return_url,
        'html_submit_button' => '<button type="submit" class="cd-save-btn">💾 Guardar cambios</button>',
        'html_submit_spinner'=> '<span class="cd-spinner"></span>',
        'form_attributes'    => array( 'class' => 'cd-acf-form' ),
    ) );

    echo '</div>';

    echo '<a href="' . esc_url( $dashboard_url ) . '" class="cd-back-link cd-back-link--bottom">← Volver al inicio</a>';
}

// ═══════════════════════════════════════════════════════════
// 7. CPT LIST
// ═══════════════════════════════════════════════════════════

function cfd_render_cpt_list( string $cpt_slug, WP_User $user ): void {
    $cpt_obj = get_post_type_object( $cpt_slug );
    if ( ! $cpt_obj ) {
        echo '<div class="cd-error">Tipo de contenido no encontrado.</div>';
        return;
    }

    $dashboard_url = cfd_get_dashboard_url();

    echo '<a href="' . esc_url( $dashboard_url ) . '" class="cd-back-link">← Volver al inicio</a>';

    if ( isset( $_GET['trashed'] ) && $_GET['trashed'] === 'true' ) {
        echo '<div class="cd-success"><span>🗑️ Entrada eliminada correctamente.</span></div>';
    }

    echo '<div class="cd-cpt-list">';
    echo '<div class="cd-cpt-list__header">';
    echo '  <h2 class="cd-cpt-list__title">' . esc_html( $cpt_obj->labels->name ) . '</h2>';

    $create_url = add_query_arg( array( 'create' => $cpt_slug ), $dashboard_url );
    echo '  <a href="' . esc_url( $create_url ) . '" class="cd-add-btn">+ Agregar nuevo</a>';
    echo '</div>';

    // ── Read & sanitize filter/sort/pagination params ────────
    $allowed_orderby = array( 'title', 'date', 'modified' );
    $raw_orderby     = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'title';
    $orderby         = in_array( $raw_orderby, $allowed_orderby, true ) ? $raw_orderby : 'title';

    // Default sort direction: ASC for title, DESC for date/modified.
    $default_order = ( $orderby === 'title' ) ? 'ASC' : 'DESC';
    $raw_order     = isset( $_GET['order'] ) ? strtoupper( sanitize_key( $_GET['order'] ) ) : $default_order;
    $order         = in_array( $raw_order, array( 'ASC', 'DESC' ), true ) ? $raw_order : $default_order;

    $search = isset( $_GET['buscar'] ) ? sanitize_text_field( $_GET['buscar'] ) : '';
    $pag    = isset( $_GET['pag'] )    ? max( 1, absint( $_GET['pag'] ) )       : 1;

    $per_page = CFD_POSTS_PER_PAGE;

    // ── Toolbar: sort dropdown + search ─────────────────────
    // The form GETs to the same page, preserving ?manage=slug.
    $toolbar_action = add_query_arg( array( 'manage' => $cpt_slug ), $dashboard_url );

    echo '<form method="get" action="' . esc_url( $toolbar_action ) . '" class="cd-cpt-toolbar">';
    // Preserve the manage param (required for routing).
    echo '  <input type="hidden" name="manage" value="' . esc_attr( $cpt_slug ) . '">';

    // ── Sort dropdown ──
    echo '  <div class="cd-cpt-toolbar__group">';
    echo '    <label for="cd-orderby" class="cd-cpt-toolbar__label">Ordenar</label>';
    echo '    <select name="orderby" id="cd-orderby" class="cd-cpt-toolbar__select" onchange="this.form.submit()">';

    $sort_options = array(
        'title'    => 'Alfabético',
        'date'     => 'Más recientes',
        'modified' => 'Última modificación',
    );
    foreach ( $sort_options as $value => $label ) {
        $selected = ( $orderby === $value ) ? ' selected' : '';
        echo '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
    }

    echo '    </select>';
    echo '  </div>';

    // ── Search input ──
    echo '  <div class="cd-cpt-toolbar__group cd-cpt-toolbar__group--search">';
    echo '    <label for="cd-buscar" class="cd-cpt-toolbar__label">Buscar</label>';
    echo '    <input type="text" name="buscar" id="cd-buscar" class="cd-cpt-toolbar__search" value="' . esc_attr( $search ) . '" placeholder="Buscar...">';
    echo '  </div>';

    echo '  <button type="submit" class="cd-cpt-toolbar__submit">Filtrar</button>';
    echo '</form>';

    // ── Active search indicator (with clear link) ───────────
    if ( $search !== '' ) {
        $clear_url = add_query_arg(
            array( 'manage' => $cpt_slug, 'orderby' => $orderby ),
            $dashboard_url
        );
        echo '<p class="cd-cpt-search-status">';
        echo '  Resultados para "<strong>' . esc_html( $search ) . '</strong>"';
        echo '  <a href="' . esc_url( $clear_url ) . '" class="cd-cpt-search-clear">✕ Limpiar</a>';
        echo '</p>';
    }

    // ── Query posts with WP_Query for pagination support ────
    $query_args = array(
        'post_type'      => $cpt_slug,
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $pag,
        'orderby'        => $orderby,
        'order'          => $order,
    );

    if ( $search !== '' ) {
        $query_args['s'] = $search;
    }

    $query       = new WP_Query( $query_args );
    $total_posts = $query->found_posts;
    $total_pages = $query->max_num_pages;

    // Clamp current page to valid range.
    if ( $pag > $total_pages && $total_pages > 0 ) {
        $pag = $total_pages;
    }

    if ( ! $query->have_posts() ) {
        if ( $search !== '' ) {
            echo '<p class="cd-cpt-list__empty">No se encontraron resultados para "' . esc_html( $search ) . '".</p>';
        } else {
            echo '<p class="cd-cpt-list__empty">Todavía no hay entradas. Haz clic en "Agregar nuevo" para crear una.</p>';
        }
    } else {
        // ── Post count ──
        $first_item = ( ( $pag - 1 ) * $per_page ) + 1;
        $last_item  = min( $pag * $per_page, $total_posts );

        echo '<p class="cd-cpt-count">Mostrando ' . $first_item . '–' . $last_item . ' de ' . $total_posts . '</p>';

        // ── Grid of cards ──
        echo '<div class="cd-cpt-grid">';
        while ( $query->have_posts() ) {
            $query->the_post();
            $p        = get_post();
            $edit_url = add_query_arg( array( 'edit' => $cpt_slug, 'id' => $p->ID ), $dashboard_url );
            $view_url = get_permalink( $p->ID );

            echo '<a href="' . esc_url( $edit_url ) . '" class="cd-cpt-card">';
            echo '  <span class="cd-cpt-card__title">' . esc_html( $p->post_title ) . '</span>';
            echo '  <span class="cd-cpt-card__view" data-href="' . esc_url( $view_url ) . '">Ver ↗</span>';
            echo '</a>';
        }
        wp_reset_postdata();
        echo '</div>';

        // ── Pagination ──
        if ( $total_pages > 1 ) {
            // Build base URL preserving all current filters.
            $base_args = array( 'manage' => $cpt_slug );
            if ( $orderby !== 'title' ) {
                $base_args['orderby'] = $orderby;
            }
            if ( $search !== '' ) {
                $base_args['buscar'] = $search;
            }

            echo '<nav class="cd-cpt-pagination" aria-label="Paginación">';

            // ← Previous
            if ( $pag > 1 ) {
                $prev_args        = $base_args;
                $prev_args['pag'] = $pag - 1;
                $prev_url         = add_query_arg( $prev_args, $dashboard_url );
                echo '<a href="' . esc_url( $prev_url ) . '" class="cd-cpt-pagination__link">← Anterior</a>';
            } else {
                echo '<span class="cd-cpt-pagination__link cd-cpt-pagination__link--disabled">← Anterior</span>';
            }

            // Page indicator
            echo '<span class="cd-cpt-pagination__current">Página ' . $pag . ' de ' . $total_pages . '</span>';

            // Next →
            if ( $pag < $total_pages ) {
                $next_args        = $base_args;
                $next_args['pag'] = $pag + 1;
                $next_url         = add_query_arg( $next_args, $dashboard_url );
                echo '<a href="' . esc_url( $next_url ) . '" class="cd-cpt-pagination__link">Siguiente →</a>';
            } else {
                echo '<span class="cd-cpt-pagination__link cd-cpt-pagination__link--disabled">Siguiente →</span>';
            }

            echo '</nav>';
        }
    }

    echo '</div>';
}

// ═══════════════════════════════════════════════════════════
// 8. CPT EDITOR
// ═══════════════════════════════════════════════════════════

function cfd_render_cpt_editor( string $cpt_slug, int $post_id, WP_User $user ): void {
    $post = get_post( $post_id );

    if ( ! $post || $post->post_type !== $cpt_slug ) {
        echo '<div class="cd-error">Esta entrada no existe.</div>';
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        echo '<div class="cd-error">No tienes permiso para editar esta entrada.</div>';
        return;
    }

    $field_groups = cfd_get_field_groups_for_post( $post_id );

    if ( empty( $field_groups ) ) {
        echo '<div class="cd-error">No se encontraron campos editables para esta entrada.</div>';
        return;
    }

    $dashboard_url = cfd_get_dashboard_url();
    $back_url      = add_query_arg( array( 'manage' => $cpt_slug ), $dashboard_url );
    $return_url    = add_query_arg(
        array( 'edit' => $cpt_slug, 'id' => $post_id, 'updated' => 'true' ),
        $dashboard_url
    );

    echo '<a href="' . esc_url( $back_url ) . '" class="cd-back-link">← Volver a la lista</a>';

    echo '<div class="cd-editor">';
    echo '<div class="cd-editor__header">';
    echo '  <h2 class="cd-editor__title">' . esc_html( $post->post_title ) . '</h2>';
    echo '  <div class="cd-editor__actions">';
    echo '    <a href="' . esc_url( get_permalink( $post_id ) ) . '" target="_blank" class="cd-preview-link">Ver entrada ↗</a>';

    if ( current_user_can( 'delete_post', $post_id ) ) {
        // NOTE: nonce action changed from 'cd_trash_' to 'cfd_trash_'
        // to match the verification in cfd_handle_cpt_delete().
        $trash_url = add_query_arg( array(
            'action'   => 'trash',
            'id'       => $post_id,
            '_wpnonce' => wp_create_nonce( 'cfd_trash_' . $post_id ),
        ), $dashboard_url );

        echo '  <span class="cd-delete-wrap" id="cd-delete-wrap">';
        echo '    <a href="#" class="cd-delete-link" id="cd-delete-trigger">Eliminar</a>';
        echo '    <span class="cd-delete-confirm" id="cd-delete-confirm" style="display:none;">';
        echo '      <span class="cd-delete-confirm__text">¿Segura?</span>';
        echo '      <a href="' . esc_url( $trash_url ) . '" class="cd-delete-confirm__yes">Sí, eliminar</a>';
        echo '      <a href="#" class="cd-delete-confirm__no" id="cd-delete-cancel">Cancelar</a>';
        echo '    </span>';
        echo '  </span>';
    }

    echo '  </div>';
    echo '</div>';

    if ( isset( $_GET['updated'] ) && $_GET['updated'] === 'true' ) {
        echo '<div class="cd-success">';
        echo '  <span>✅ ¡Cambios guardados!</span>';
        echo '  <a href="' . esc_url( get_permalink( $post_id ) ) . '" target="_blank" class="cd-preview-link" style="margin-left: 0.5rem;">Ver entrada ↗</a>';
        echo '</div>';
    }

    echo '<p class="cd-editor__sub">Edita el contenido de esta entrada.</p>';

    acf_form( array(
        'post_id'            => $post_id,
        'post_title'         => true,
        'post_content'       => false,
        'field_groups'       => $field_groups,
        'submit_value'       => 'Guardar cambios',
        'updated_message'    => false,
        'return'             => $return_url,
        'html_submit_button' => '<button type="submit" class="cd-save-btn">💾 Guardar cambios</button>',
        'html_submit_spinner'=> '<span class="cd-spinner"></span>',
        'form_attributes'    => array( 'class' => 'cd-acf-form' ),
    ) );

    echo '</div>';

    echo '<a href="' . esc_url( $back_url ) . '" class="cd-back-link cd-back-link--bottom">← Volver a la lista</a>';
}

// ═══════════════════════════════════════════════════════════
// 9. CPT CREATOR
// ═══════════════════════════════════════════════════════════

function cfd_render_cpt_creator( string $cpt_slug, WP_User $user ): void {
    $cpt_obj = get_post_type_object( $cpt_slug );
    if ( ! $cpt_obj ) {
        echo '<div class="cd-error">Tipo de contenido no encontrado.</div>';
        return;
    }

    $dashboard_url = cfd_get_dashboard_url();
    $back_url      = add_query_arg( array( 'manage' => $cpt_slug ), $dashboard_url );
    $return_url    = add_query_arg( array( 'manage' => $cpt_slug, 'created' => 'true' ), $dashboard_url );

    echo '<a href="' . esc_url( $back_url ) . '" class="cd-back-link">← Volver a la lista</a>';

    echo '<div class="cd-editor">';
    echo '<h2 class="cd-editor__title">Crear nuevo: ' . esc_html( $cpt_obj->labels->singular_name ) . '</h2>';
    echo '<p class="cd-editor__sub">Completa los campos y publica.</p>';

    acf_form( array(
        'post_id'            => 'new_post',
        'new_post'           => array(
            'post_type'   => $cpt_slug,
            'post_status' => 'publish',
        ),
        'post_title'         => true,
        'post_content'       => false,
        'submit_value'       => 'Crear y publicar',
        'updated_message'    => false,
        'return'             => $return_url,
        'html_submit_button' => '<button type="submit" class="cd-save-btn">✨ Crear y publicar</button>',
        'html_submit_spinner'=> '<span class="cd-spinner"></span>',
        'form_attributes'    => array( 'class' => 'cd-acf-form' ),
    ) );

    echo '</div>';

    echo '<a href="' . esc_url( $back_url ) . '" class="cd-back-link cd-back-link--bottom">← Volver a la lista</a>';
}

// ═══════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════

function cfd_get_editable_pages( array $config ): array {
    if ( ! empty( $config['editable_pages'] ) ) {
        return array_filter( array_map( 'get_post', $config['editable_pages'] ) );
    }

    $all_pages = get_posts( array(
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
    ) );

    $editable = array();
    foreach ( $all_pages as $page ) {
        if ( $page->post_name === $config['dashboard_slug'] ) {
            continue;
        }
        $groups = cfd_get_field_groups_for_post( $page->ID );
        if ( ! empty( $groups ) ) {
            $editable[] = $page;
        }
    }

    return $editable;
}

function cfd_get_field_groups_for_post( int $post_id ): array {
    if ( ! function_exists( 'acf_get_field_groups' ) ) {
        return array();
    }

    $post = get_post( $post_id );
    if ( ! $post ) {
        return array();
    }

    $screen = array(
        'post_id'   => $post_id,
        'post_type' => $post->post_type,
        'page'      => $post->post_name,
    );

    $template = get_page_template_slug( $post_id );
    if ( $template ) {
        $screen['page_template'] = $template;
    }

    if ( $post->post_type === 'page' ) {
        $front_page_id = (int) get_option( 'page_on_front' );
        $screen['page_type'] = ( $post_id === $front_page_id ) ? 'front_page' : 'page';
    }

    $all_groups = acf_get_field_groups( $screen );

    $keys = array();
    foreach ( $all_groups as $group ) {
        if ( ! $group['active'] ) {
            continue;
        }
        $keys[] = $group['key'];
    }

    return $keys;
}
