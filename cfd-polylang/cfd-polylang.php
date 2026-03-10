<?php
/**
 * Plugin Name:       CFD Polylang
 * Plugin URI:        https://autentiweb.com/plugins/cfd-polylang
 * Description:       Polylang integration for Client Frontend Dashboard — language-filtered CPT lists, page cards, translation links, and language badges.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            AutentiWeb
 * Author URI:        https://autentiweb.com
 * License:           GPL-2.0-or-later
 * Text Domain:       cfd-polylang
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CFD_POLYLANG_VERSION', '1.0.0' );
define( 'CFD_POLYLANG_PATH', plugin_dir_path( __FILE__ ) );

/**
 * ─── Dependency Check ───────────────────────────────────────
 *
 * Ensures both CFD (>= 3.1.0) and Polylang are active.
 * Displays admin notices and bails early if either is missing.
 */
function cfd_polylang_check_dependencies(): bool {
    // Check CFD is active and meets minimum version.
    if ( ! defined( 'CFD_VERSION' ) || version_compare( trim( CFD_VERSION ), '3.1.0', '<' ) ) {
        add_action( 'admin_notices', 'cfd_polylang_missing_cfd_notice' );
        return false;
    }

    // Check Polylang is active (function available after plugins_loaded).
    if ( ! function_exists( 'pll_current_language' ) ) {
        add_action( 'admin_notices', 'cfd_polylang_missing_polylang_notice' );
        return false;
    }

    return true;
}

function cfd_polylang_missing_cfd_notice(): void {
    echo '<div class="notice notice-error"><p>';
    echo '<strong>CFD Polylang:</strong> Requiere el plugin <em>Client Frontend Dashboard</em> versión 3.1.0 o superior. Por favor, instala o actualiza CFD.';
    echo '</p></div>';
}

function cfd_polylang_missing_polylang_notice(): void {
    echo '<div class="notice notice-error"><p>';
    echo '<strong>CFD Polylang:</strong> Requiere el plugin <em>Polylang</em> (o Polylang Pro) activo. Por favor, instala y activa Polylang.';
    echo '</p></div>';
}

/**
 * ─── Bootstrap ──────────────────────────────────────────────
 *
 * Runs on `plugins_loaded` so both CFD and Polylang are available.
 */
add_action( 'plugins_loaded', 'cfd_polylang_init' );

function cfd_polylang_init(): void {
    if ( ! cfd_polylang_check_dependencies() ) {
        return;
    }

    // ── Hook: Filter CPT query args by current language ──
    add_filter( 'cfd_cpt_query_args', 'cfd_polylang_filter_cpt_query', 10, 2 );

    // ── Hook: Add language badge to CPT cards ──
    add_filter( 'cfd_cpt_card_badges', 'cfd_polylang_card_language_badge', 10, 3 );

    // ── Hook: Filter page cards by current language ──
    add_filter( 'cfd_page_cards', 'cfd_polylang_filter_page_cards' );

    // ── Hook: Translation links in editor ──
    add_action( 'cfd_editor_before_form', 'cfd_polylang_translation_links', 10, 2 );

    // ── Enqueue minimal styles ──
    add_action( 'wp_enqueue_scripts', 'cfd_polylang_enqueue_styles' );
}

/* ═══════════════════════════════════════════════════════════════
 * 1. LANGUAGE-FILTERED CPT QUERIES
 * ─────────────────────────────────────────────────────────────
 * Passes the Polylang `lang` parameter into WP_Query so CPT
 * lists only show posts in the current dashboard language.
 * ═══════════════════════════════════════════════════════════════ */

function cfd_polylang_filter_cpt_query( array $query_args, string $cpt_slug ): array {
    $lang = pll_current_language();

    if ( $lang ) {
        $query_args['lang'] = $lang;
    }

    return $query_args;
}

/* ═══════════════════════════════════════════════════════════════
 * 2. LANGUAGE BADGE ON CPT CARDS
 * ─────────────────────────────────────────────────────────────
 * Adds a small language indicator (2-letter code) to each card
 * so users can visually confirm the post's language at a glance.
 * ═══════════════════════════════════════════════════════════════ */

function cfd_polylang_card_language_badge( array $badges, WP_Post $post, string $cpt_slug ): array {
    $lang = pll_get_post_language( $post->ID, 'slug' );

    if ( $lang ) {
        $name = pll_get_post_language( $post->ID, 'name' );
        $badges[] = '<span class="cd-cpt-card__lang-badge" title="' . esc_attr( $name ) . '">'
            . esc_html( strtoupper( $lang ) )
            . '</span>';
    }

    return $badges;
}

/* ═══════════════════════════════════════════════════════════════
 * 3. LANGUAGE-FILTERED PAGE CARDS
 * ─────────────────────────────────────────────────────────────
 * Filters the page cards array to only include pages that match
 * the current Polylang language.
 * ═══════════════════════════════════════════════════════════════ */

function cfd_polylang_filter_page_cards( array $pages ): array {
    $current_lang = pll_current_language();

    if ( ! $current_lang ) {
        return $pages;
    }

    return array_filter( $pages, function ( $page ) use ( $current_lang ) {
        $page_lang = pll_get_post_language( $page->ID, 'slug' );
        // Keep pages that match the current language, or that have no language set.
        return ! $page_lang || $page_lang === $current_lang;
    } );
}

/* ═══════════════════════════════════════════════════════════════
 * 4. TRANSLATION LINKS IN EDITOR
 * ─────────────────────────────────────────────────────────────
 * Displays links above the editor form to edit existing
 * translations or create new ones for missing languages.
 * Uses pll_get_post_translations() to find related posts.
 * ═══════════════════════════════════════════════════════════════ */

function cfd_polylang_translation_links( WP_Post $post, string $cpt_slug ): void {
    $translations = pll_get_post_translations( $post->ID );
    $all_languages = pll_languages_list( array( 'fields' => 'slug' ) );
    $language_names = pll_languages_list( array( 'fields' => 'name' ) );
    $current_lang = pll_get_post_language( $post->ID, 'slug' );

    if ( empty( $all_languages ) || count( $all_languages ) < 2 ) {
        return; // No multilingual setup — nothing to show.
    }

    // Build a slug → name map.
    $lang_map = array_combine( $all_languages, $language_names );

    $dashboard_url = function_exists( 'cfd_get_dashboard_url' ) ? cfd_get_dashboard_url() : '';

    echo '<div class="cd-translation-links">';
    echo '<span class="cd-translation-links__label">Traducciones:</span> ';

    $links = array();

    foreach ( $all_languages as $lang_slug ) {
        // Skip the language of the post we're currently editing.
        if ( $lang_slug === $current_lang ) {
            continue;
        }

        $lang_name = $lang_map[ $lang_slug ] ?? strtoupper( $lang_slug );

        if ( ! empty( $translations[ $lang_slug ] ) ) {
            // Translation exists — link to edit it.
            $edit_url = add_query_arg(
                array( 'edit' => $cpt_slug, 'id' => $translations[ $lang_slug ] ),
                $dashboard_url
            );
            $links[] = '<a href="' . esc_url( $edit_url ) . '" class="cd-translation-links__item cd-translation-links__item--exists">'
                . esc_html( $lang_name )
                . ' <span class="cd-translation-links__action">editar</span>'
                . '</a>';
        } else {
            // Translation missing — link to create it.
            $create_url = add_query_arg(
                array(
                    'create'         => $cpt_slug,
                    'translation_of' => $post->ID,
                    'lang'           => $lang_slug,
                ),
                $dashboard_url
            );
            $links[] = '<a href="' . esc_url( $create_url ) . '" class="cd-translation-links__item cd-translation-links__item--missing">'
                . esc_html( $lang_name )
                . ' <span class="cd-translation-links__action">crear</span>'
                . '</a>';
        }
    }

    echo implode( ' <span class="cd-translation-links__sep">|</span> ', $links );
    echo '</div>';
}

/* ═══════════════════════════════════════════════════════════════
 * 5. STYLES
 * ═══════════════════════════════════════════════════════════════ */

function cfd_polylang_enqueue_styles(): void {
    // Only load on the dashboard page (same guard as CFD core styles).
    $config = cfd_get_config();
    if ( ! is_page( $config['dashboard_slug'] ) ) {
        return;
    }
    if ( ! is_user_logged_in() ) {
        return;
    }

    $css = '
/* ── CFD Polylang: Language badge ── */
.cd-cpt-card__lang-badge {
    display: inline-block;
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    padding: 2px 6px;
    border-radius: 4px;
    background: var(--accent, #A69279);
    color: #fff;
    line-height: 1;
    vertical-align: middle;
}

/* ── CFD Polylang: Translation links ── */
.cd-translation-links {
    margin: 0 0 1.25rem;
    padding: 0.75rem 1rem;
    background: var(--bg-light, #FAF8F5);
    border: 1px solid var(--border, #E8E3DD);
    border-radius: var(--radius-s, 8px);
    font-size: var(--text-s, 0.875rem);
    color: var(--text-dark-muted, #8A817A);
}

.cd-translation-links__label {
    font-weight: 600;
    color: var(--text-dark, #3E3A36);
}

.cd-translation-links__item {
    text-decoration: none;
    color: var(--accent, #A69279);
    font-weight: 500;
}

.cd-translation-links__item:hover {
    text-decoration: underline;
}

.cd-translation-links__item--missing {
    opacity: 0.65;
    font-style: italic;
}

.cd-translation-links__action {
    font-size: 0.75em;
    opacity: 0.7;
}

.cd-translation-links__sep {
    color: var(--border, #E8E3DD);
    margin: 0 0.25rem;
}
';

    wp_register_style( 'cfd-polylang', false, array(), CFD_POLYLANG_VERSION );
    wp_enqueue_style( 'cfd-polylang' );
    wp_add_inline_style( 'cfd-polylang', $css );
}
